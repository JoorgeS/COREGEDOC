<?php
// models/MinutaModel.php

// 1. Incluimos la clase que SÍ funciona (la que contiene BaseConexion)
require_once __DIR__ . '/../class/class.conectorDB.php';

// 2. Heredamos de BaseConexion (PDO), igual que tus Controllers
class MinutaModel extends BaseConexion
{
    // 3. Definimos la conexión PDO
    private $db;

    // 4. El constructor ahora usa el método conectar() de BaseConexion
    public function __construct()
    {
        $this->db = $this->conectar();
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /**
     * FUNCIÓN ANTIGUA (getMinutasByEstado) - REESCRITA A PDO
     * Esta es tu función original, pero actualizada para usar $this->db (PDO)
     * en lugar de $this->db_connector->consultarBD().
     */
    public function getMinutasByEstado($estado, $startDate = null, $endDate = null, $themeName = null)
    {
        // Normalizamos estado
        $estado = strtoupper(trim((string)$estado)) === 'APROBADA' ? 'APROBADA' : 'PENDIENTE';

        // Misma SQL, pero los valores se manejarán con PDO
        $sql = "
            SELECT
                m.idMinuta AS idMinuta,
                c.nombreComision, 
                u.pNombre AS presidenteNombre,
                u.aPaterno AS presidenteApellido,
                CASE 
                    WHEN COALESCE(m.pathArchivo,'') <> '' THEN 'APROBADA'
                    ELSE 'PENDIENTE'
                END AS estadoMinuta,
                m.pathArchivo,
                r.nombreReunion,
                m.fechaMinuta,
                IFNULL(GROUP_CONCAT(DISTINCT t.nombreTema ORDER BY t.idTema SEPARATOR '<br>'), 'N/A') AS nombreTemas,
                IFNULL(GROUP_CONCAT(DISTINCT t.objetivo   ORDER BY t.idTema SEPARATOR '<br>'), 'N/A') AS objetivos,
                COUNT(DISTINCT adj.idAdjunto) AS totalAdjuntos,
                (SELECT COUNT(DISTINCT am_count.t_usuario_idPresidente) 
                 FROM t_aprobacion_minuta am_count 
                 WHERE am_count.t_minuta_idMinuta = m.idMinuta
                 AND am_count.estado_firma = 'FIRMADO') AS firmasActuales,
                (SELECT COUNT(*) 
                 FROM t_aprobacion_minuta am3 
                 WHERE am3.t_minuta_idMinuta = m.idMinuta 
                 AND am3.estado_firma = 'REQUIERE_REVISION') AS tieneFeedback,
                m.presidentesRequeridos
            FROM t_minuta m
            LEFT JOIN t_tema    t   ON t.t_minuta_idMinuta   = m.idMinuta
            LEFT JOIN t_adjunto adj ON adj.t_minuta_idMinuta = m.idMinuta
            LEFT JOIN t_comision c  ON m.t_comision_idComision = c.idComision
            LEFT JOIN t_usuario u   ON c.t_usuario_idPresidente = u.idUsuario
            LEFT JOIN t_reunion r ON m.idMinuta = r.t_minuta_idMinuta
            WHERE 1=1
        ";

        $valores = [];

        // Filtro por estado
        if ($estado === 'APROBADA') {
            $sql .= " AND COALESCE(m.pathArchivo,'') <> '' ";
        } else { // PENDIENTE
            $sql .= " AND COALESCE(m.pathArchivo,'') = '' ";
        }

        // Filtros de fecha
        if (!empty($startDate)) {
            $sql .= " AND m.fechaMinuta >= :startDate ";
            $valores['startDate'] = $startDate;
        }
        if (!empty($endDate)) {
            $sql .= " AND m.fechaMinuta <= :endDate ";
            $valores['endDate'] = $endDate . ' 23:59:59';
        }

        // Filtro por palabra clave
        if (!empty($themeName)) {
            $sql .= "
                AND EXISTS (
                    SELECT 1
                    FROM t_tema tt
                    WHERE tt.t_minuta_idMinuta = m.idMinuta
                      AND (
                           tt.nombreTema LIKE :kw
                        OR tt.objetivo   LIKE :kw
                      )
                )
            ";
            $valores['kw'] = '%' . $themeName . '%';
        }

        $sql .= "
            GROUP BY m.idMinuta, c.nombreComision, u.pNombre, u.aPaterno, m.pathArchivo, m.fechaMinuta, m.presidentesRequeridos
            ORDER BY m.fechaMinuta DESC, m.idMinuta DESC
        ";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($valores);
            $minutas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error SQL (getMinutasByEstado): " . $e->getMessage());
            return [];
        }

        // Claves seguras (sin cambios)
        foreach ($minutas as &$minuta) {
            $minuta['nombreTemas']   = $minuta['nombreTemas']   ?? 'N/A';
            $minuta['objetivos']     = $minuta['objetivos']     ?? 'N/A';
            $minuta['totalAdjuntos'] = $minuta['totalAdjuntos'] ?? 0;
            $minuta['nombreComision'] = $minuta['nombreComision'] ?? 'Comisión no asignada';
            $minuta['presidenteNombre'] = $minuta['presidenteNombre'] ?? 'Presidente no asignado';
            $minuta['presidenteApellido'] = $minuta['presidenteApellido'] ?? '';
            $minuta['firmasActuales'] = $minuta['firmasActuales'] ?? 0;
            $minuta['tieneFeedback'] = $minuta['tieneFeedback'] ?? 0;
            $minuta['presidentesRequeridos'] = $minuta['presidentesRequeridos'] ?? 1;
        }
        unset($minuta);

        return $minutas;
    }

    // --- El resto de tus funciones antiguas (getAllMinutas, getTemaById, updateTema, actualizarPathBorrador) ---
    // --- REESCRITAS A PDO ---

    public function getAllMinutas()
    {
        $sql = "SELECT idTema, t_minuta_idMinuta, nombreTema, objetivo, compromiso, observacion
                FROM t_tema ORDER BY t_minuta_idMinuta DESC, idTema DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTemaById($id)
    {
        $sql = "SELECT idTema, t_minuta_idMinuta, nombreTema, objetivo, compromiso, observacion
                FROM t_tema WHERE idTema = :idTema";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['idTema' => (int)$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC); // fetch() en lugar de fetchAll() si solo esperas uno
    }

    public function updateTema($id, $data)
    {
        $sql = "UPDATE t_tema SET nombreTema = :nombreTema, objetivo = :objetivo,
                compromiso = :compromiso, observacion = :observacion
                WHERE idTema = :idTema";
        $valores = [
            'nombreTema'  => $data['nombreTema'],
            'objetivo'    => $data['objetivo'],
            'compromiso'  => $data['compromiso'],
            'observacion' => $data['observacion'],
            'idTema'      => (int)$id
        ];
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($valores); // Devuelve true/false
    }

    public function actualizarPathBorrador($idMinuta, $path)
    {
        $sql = "UPDATE t_minuta 
                SET pathArchivoBorrador = :pathArchivoBorrador 
                WHERE idMinuta = :idMinuta";
        $valores = [
            'pathArchivoBorrador' => $path,
            'idMinuta'            => (int)$idMinuta
        ];

        try {
            $stmt = $this->db->prepare($sql);
            return $stmt->execute($valores); // Devuelve true en éxito
        } catch (Exception $e) {
            error_log("Error SQL (actualizarPathBorrador): " . $e->getMessage());
            return false;
        }
    }


    /* ==================================================================
     * NUEVAS FUNCIONES (logAccion, getSeguimiento, getMinutaById)
     * AHORA ESCRITAS CON PDO PARA COINCIDIR CON EL RESTO DE TU APP.
     * ==================================================================
     */

    /**
     * NUEVA FUNCIÓN (CORREGIDA A PDO): Registra una acción en la bitácora.
     */
    public function logAccion($minuta_id, $usuario_id, $accion, $detalle = '')
    {
        $sql = "INSERT INTO t_minuta_seguimiento 
                  (t_minuta_idMinuta, t_usuario_idUsuario, accion, detalle) 
                VALUES (:minuta_id, :usuario_id, :accion, :detalle)";

        $valores = [
            'minuta_id'  => (int)$minuta_id,
            'usuario_id' => $usuario_id ? (int)$usuario_id : null,
            'accion'     => $accion,
            'detalle'    => $detalle
        ];

        try {
            $stmt = $this->db->prepare($sql);
            return $stmt->execute($valores); // Devuelve true en éxito
        } catch (Exception $e) {
            // Esto es lo que estás viendo en tus logs
            error_log("Error SQL (logAccion): No se pudo registrar la acción " . $accion . " para idMinuta " . $minuta_id . ". Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * NUEVA FUNCIÓN (CORREGIDA A PDO): Obtiene el historial de seguimiento.
     */
    /**
     * NUEVA FUNCIÓN (CORREGIDA A PDO): Obtiene el historial de seguimiento.
     */
    public function getSeguimiento($minuta_id)
    {
        // ---- INICIO DE LA CORRECCIÓN ----
        // Se cambió 'u.nombreCompleto' por CONCAT(u.pNombre, ' ', u.aPaterno)
        $sql = "SELECT 
                    s.fecha_hora, 
                    s.accion, 
                    s.detalle, 
                    COALESCE(TRIM(CONCAT(u.pNombre, ' ', u.aPaterno)), 'Sistema') as usuario_nombre
                FROM 
                    t_minuta_seguimiento s
                LEFT JOIN 
                    t_usuario u ON s.t_usuario_idUsuario = u.idUsuario
                WHERE 
                    s.t_minuta_idMinuta = :minuta_id
                ORDER BY 
                    s.fecha_hora ASC";
        // ---- FIN DE LA CORRECCIÓN ----

        $valores = ['minuta_id' => (int)$minuta_id];

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($valores);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error SQL (getSeguimiento): No se pudo obtener el seguimiento para idMinuta " . $minuta_id . ". Error: " . $e->getMessage());
            return []; // Devuelve array vacío en caso de error
        }
    }

    /**
     * NUEVA FUNCIÓN (CORREGIDA A PDO): Obtiene datos básicos de la minuta.
     */
    /**
     * NUEVA FUNCIÓN (CORREGIDA CON EL JOIN CORRECTO)
     * Obtiene datos básicos de la minuta.
     */
    /**
     * NUEVA FUNCIÓN (CORREGIDA SIN JOIN)
     * Obtiene datos básicos de la minuta.
     */
    public function getMinutaById($idMinuta)
    {
        // ---- INICIO DE LA CORRECCIÓN ----
        // La columna 'tipoReunion' SÍ está en 't_minuta' (alias 'm').
        // Esta consulta es la versión simple y correcta.
        $sql = "SELECT 
                    m.idMinuta, 
   
                    m.estadoMinuta
                FROM t_minuta m
                WHERE m.idMinuta = :idMinuta";
        // ---- FIN DE LA CORRECCIÓN ----

        $valores = ['idMinuta' => (int)$idMinuta];

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($valores);
            $minuta = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$minuta) {
                error_log("Error SQL (getMinutaById): Minuta no encontrada con ID " . $idMinuta);
                return null;
            }
            return $minuta;
        } catch (Exception $e) {
            error_log("Error SQL (getMinutaById): " . $e->getMessage());
            return null;
        }
    }

    /**
     * NUEVA FUNCIÓN: Obtiene la última acción de seguimiento para todas
     * las minutas que no están APROBADAS.
     */
    /**
     * FUNCIÓN ACTUALIZADA: Obtiene la última acción de seguimiento
     * con filtros de comisión y fecha.
     */
    public function getUltimoSeguimientoParaPendientes($filters = [])
    {
        // Esta consulta busca el último seguimiento (rn = 1)
        $sql = "
            WITH RankedSeguimiento AS (
                SELECT
                    s.t_minuta_idMinuta,
                    s.detalle,
                    s.fecha_hora,
                    COALESCE(TRIM(CONCAT(u.pNombre, ' ', u.aPaterno)), 'Sistema') as usuario_nombre,
                    ROW_NUMBER() OVER(
                        PARTITION BY s.t_minuta_idMinuta 
                        ORDER BY s.fecha_hora DESC
                    ) as rn
                FROM
                    t_minuta_seguimiento s
                LEFT JOIN
                    t_usuario u ON s.t_usuario_idUsuario = u.idUsuario
            )
            SELECT
                m.idMinuta,
                m.fechaMinuta,
                c.nombreComision,
                IFNULL(GROUP_CONCAT(DISTINCT t.nombreTema SEPARATOR '<br>'), 'N/A') AS nombreTemas,
                COALESCE(rs.detalle, 'Sin acciones registradas') as ultimo_detalle,
                rs.fecha_hora as ultima_fecha,
                COALESCE(rs.usuario_nombre, 'N/A') as ultimo_usuario
            FROM
                t_minuta m
            LEFT JOIN
                t_comision c ON m.t_comision_idComision = c.idComision
            LEFT JOIN
                t_tema t ON m.idMinuta = t.t_minuta_idMinuta
            LEFT JOIN
                RankedSeguimiento rs ON m.idMinuta = rs.t_minuta_idMinuta AND rs.rn = 1
            WHERE
                m.estadoMinuta <> 'APROBADA'
        ";

        $params = [];

        // --- Aplicar Filtros Dinámicos ---

        // 1. Filtro de Comisión
        if (!empty($filters['comisionId'])) {
            $sql .= " AND m.t_comision_idComision = :comisionId";
            $params['comisionId'] = $filters['comisionId'];
        }

        // 2. Filtro de Rango de Fechas (basado en la FECHA DE CREACIÓN)
        if (!empty($filters['startDate'])) {
            $sql .= " AND m.fechaMinuta >= :startDate";
            $params['startDate'] = $filters['startDate'];
        }
        if (!empty($filters['endDate'])) {
            $sql .= " AND m.fechaMinuta <= :endDate";
            // Añadimos la hora final para incluir todo el día
            $params['endDate'] = $filters['endDate'] . ' 23:59:59';
        }

        // --- Fin de Filtros ---

        $sql .= "
            GROUP BY 
                m.idMinuta, c.nombreComision, rs.detalle, rs.fecha_hora, rs.usuario_nombre
            ORDER BY
                rs.fecha_hora DESC, m.idMinuta DESC;
        ";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params); // Pasamos los parámetros de los filtros
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error SQL (getUltimoSeguimientoParaPendientes): " . $e->getMessage());
            return []; // Devuelve array vacío en caso de error
        }
    }

    /**
     * NUEVA FUNCIÓN: Obtiene una lista simple de comisiones.
     */
    /**
     * NUEVA FUNCIÓN: Obtiene una lista simple de comisiones.
     * (Versión corregida FINAL: se elimina el filtro 'vigente' que no existe)
     */
    public function getComisiones()
    {
        // --- INICIO DE LA CORRECCIÓN ---
        // Se eliminó la cláusula 'WHERE vigente = 1' porque la columna
        // 'vigente' no existe en tu tabla 't_comision'.
        $sql = "SELECT idComision, nombreComision 
                FROM t_comision 
                ORDER BY nombreComision ASC";
        // --- FIN DE LA CORRECCIÓN ---

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error SQL (getComisiones): " . $e->getMessage());
            return [];
        }
    }

    /**
     * NUEVA FUNCIÓN: Obtiene los feedbacks de una minuta.
     */
    public function getFeedbackDeMinuta($idMinuta)
    {
        // Asumo que la tabla de feedback es 't_aprobacion_minuta'
        // y que el comentario está en la columna 'feedback'.
        // ¡Ajusta el nombre de las columnas si es necesario!
        $sql = "SELECT 
                    f.feedback, 
                    f.fecha_feedback, 
                    COALESCE(TRIM(CONCAT(u.pNombre, ' ', u.aPaterno)), 'Usuario') as nombreUsuario
                FROM 
                    t_aprobacion_minuta f
                LEFT JOIN 
                    t_usuario u ON f.t_usuario_idPresidente = u.idUsuario
                WHERE 
                    f.t_minuta_idMinuta = :idMinuta
                    AND f.estado_firma = 'REQUIERE_REVISION'
                    AND f.feedback IS NOT NULL 
                    AND f.feedback <> ''
                ORDER BY 
                    f.fecha_feedback DESC";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['idMinuta' => (int)$idMinuta]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error SQL (getFeedbackDeMinuta): " . $e->getMessage());
            return []; // Devuelve array vacío en caso de error
        }
    }

    public function obtenerMinutaPorHash(string $hashValidacion): ?array
    {
        try {
            $sql = "SELECT idMinuta, pathArchivo, fechaAprobacion, nombreArchivo
                        FROM t_minuta 
                        WHERE hashValidacion = :hash 
                        LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':hash' => $hashValidacion]);
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            return $resultado ?: null;
        } catch (Exception $e) {
            error_log("❌ Error al obtener minuta por hash: " . $e->getMessage());
            return null;
        }
    }

    public function actualizarHashValidacion(int $idMinuta, string $hashValidacion): bool
    {
        try {
            $sql = "UPDATE t_minuta 
                    SET hashValidacion = :hash, fechaAprobacion = NOW()
                    WHERE idMinuta = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':hash' => $hashValidacion,
                ':id'   => $idMinuta
            ]);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            error_log("❌ Error al actualizar hashValidacion (MinutaModel): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verifica si un usuario ya ha realizado una acción específica sobre una minuta.
     * @param int $idMinuta
     * @param int $idUsuario
     * @param string $tipoAccion
     * @return bool true si existe la acción, false si no
     */

    public function verificarAccion(int $idMinuta, int $idUsuario, string $tipoAccion): bool
    {
        try {
            $sql = "SELECT COUNT(*) FROM t_minuta_log
                        WHERE t_minuta_idMinuta = :idMinuta
                        AND t_usuario_idUsuario = :idUsuario
                        AND tipoAccion = :tipoAccion";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':idMinuta' => $idMinuta,
                ':idUsuario' => $idUsuario,
                ':tipoAccion' => $tipoAccion
            ]);
            return $stmt->fetchColumn() > 0;
        } catch (Exception $e) {
            error_log("Error en verificarAccion (MinutaModel): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Guarda o actualiza el hash de validación de una minuta.
     * Si ya existe, lo reemplaza.
     */

}
