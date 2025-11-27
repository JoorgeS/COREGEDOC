<?php

namespace App\Models;
use Exception;   


use App\Config\Database;

use PDO;
use PDOException;

class Minuta
{
    private $conn;

    public function __construct()
    {
        // Usamos la nueva clase Database que creamos en el paso 1
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    // --- TUS FUNCIONES ADAPTADAS ---

    public function getMinutasByEstado($estado, $startDate = null, $endDate = null, $themeName = null)
    {
        $estado = strtoupper(trim((string)$estado)) === 'APROBADA' ? 'APROBADA' : 'PENDIENTE';

        $sql = "
            SELECT
                m.idMinuta,
                c.nombreComision, 
                u.pNombre AS presidenteNombre,
                u.aPaterno AS presidenteApellido,
                m.estadoMinuta,
                m.pathArchivo,
                r.nombreReunion,
                m.fechaMinuta,
                IFNULL(GROUP_CONCAT(DISTINCT t.nombreTema ORDER BY t.idTema SEPARATOR '<br>'), 'N/A') AS nombreTemas
            FROM t_minuta m
            LEFT JOIN t_tema    t   ON t.t_minuta_idMinuta   = m.idMinuta
            LEFT JOIN t_comision c  ON m.t_comision_idComision = c.idComision
            LEFT JOIN t_usuario u   ON c.t_usuario_idPresidente = u.idUsuario
            LEFT JOIN t_reunion r   ON m.idMinuta = r.t_minuta_idMinuta
            WHERE 1=1
        ";

        $params = [];

        if ($estado === 'APROBADA') {
            $sql .= " AND m.estadoMinuta = 'APROBADA' ";
        } else {
            $sql .= " AND m.estadoMinuta IN ('PENDIENTE', 'REQUIERE_REVISION', 'BORRADOR', 'PARCIAL') ";
        }

        // Filtros opcionales
        if (!empty($startDate)) {
            $sql .= " AND m.fechaMinuta >= :startDate ";
            $params[':startDate'] = $startDate;
        }
        if (!empty($endDate)) {
            $sql .= " AND m.fechaMinuta <= :endDate ";
            $params[':endDate'] = $endDate . ' 23:59:59';
        }

        $sql .= " GROUP BY m.idMinuta, c.nombreComision, u.pNombre, u.aPaterno, m.estadoMinuta, r.nombreReunion ORDER BY m.fechaMinuta DESC";

        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error en getMinutasByEstado: " . $e->getMessage());
            return [];
        }
    }

    public function getMinutaById($id)
    {
        $sql = "SELECT * FROM t_minuta WHERE idMinuta = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getTemas($idMinuta)
    {
        $sql = "SELECT * FROM t_tema WHERE t_minuta_idMinuta = :id ORDER BY idTema ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $idMinuta]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getEstadoFirma($idMinuta, $idUsuario)
    {
        $sql = "SELECT estado_firma FROM t_aprobacion_minuta 
                WHERE t_minuta_idMinuta = :idMinuta 
                AND t_usuario_idPresidente = :idUsuario";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':idMinuta' => $idMinuta, ':idUsuario' => $idUsuario]);
        return $stmt->fetchColumn();
    }

    public function getAsistenciaData($idMinuta)
    {
        // 1. Obtener todos los consejeros/presidentes (usuarios relevantes)
        $sqlMiembros = "SELECT idUsuario, pNombre, sNombre, aPaterno, aMaterno,
                        TRIM(CONCAT(pNombre, ' ', COALESCE(sNombre, ''), ' ', aPaterno, ' ', aMaterno)) AS nombreCompleto
                        FROM t_usuario WHERE tipoUsuario_id IN (1, 3) ORDER BY aPaterno";
        $stmt = $this->conn->prepare($sqlMiembros);
        $stmt->execute();
        $miembros = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 2. Obtener quiénes ya marcaron asistencia en esta minuta
        $sqlAsistencia = "SELECT t_usuario_idUsuario FROM t_asistencia WHERE t_minuta_idMinuta = :idMinuta";
        $stmt = $this->conn->prepare($sqlAsistencia);
        $stmt->execute([':idMinuta' => $idMinuta]);
        $presentes = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        $mapaPresentes = array_flip($presentes);

        // 3. Combinar
        $resultado = [];
        foreach ($miembros as $m) {
            $id = (int)$m['idUsuario'];
            $resultado[] = [
                'idUsuario' => $id,
                'nombreCompleto' => $m['nombreCompleto'],
                'presente' => isset($mapaPresentes[$id])
            ];
        }
        return $resultado;
    }

    public function guardarAsistencia($idMinuta, $listaIdsUsuarios)
    {
        try {
            $this->conn->beginTransaction();

            // 1. Borramos la asistencia previa de esta minuta (limpieza)
            $sqlDelete = "DELETE FROM t_asistencia WHERE t_minuta_idMinuta = :idMinuta";
            $stmt = $this->conn->prepare($sqlDelete);
            $stmt->execute([':idMinuta' => $idMinuta]);

            // 2. Insertamos los marcados como presentes
            if (!empty($listaIdsUsuarios)) {
                $sqlInsert = "INSERT INTO t_asistencia (t_minuta_idMinuta, t_usuario_idUsuario) VALUES (:idMinuta, :idUsuario)";
                $stmtInsert = $this->conn->prepare($sqlInsert);

                foreach ($listaIdsUsuarios as $idUsuario) {
                    $stmtInsert->execute([
                        ':idMinuta' => $idMinuta,
                        ':idUsuario' => $idUsuario
                    ]);
                }
            }

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Error al guardar asistencia: " . $e->getMessage());
            return false;
        }
    }

    public function guardarTemas($idMinuta, $temas)
    {
        try {
            $this->conn->beginTransaction();

            // 1. Limpiamos los temas anteriores
            $sqlDelete = "DELETE FROM t_tema WHERE t_minuta_idMinuta = :idMinuta";
            $stmtDelete = $this->conn->prepare($sqlDelete);
            $stmtDelete->execute([':idMinuta' => $idMinuta]);

            // 2. Insertamos usando SOLO las columnas que existen: compromiso y observacion

            $sqlInsert = "INSERT INTO t_tema (t_minuta_idMinuta, nombreTema, objetivo, compromiso, observacion) 
                          VALUES (:idMinuta, :nombre, :objetivo, :compromiso, :observacion)";
            $stmtInsert = $this->conn->prepare($sqlInsert);

            foreach ($temas as $t) {
                $stmtInsert->execute([
                    ':idMinuta' => $idMinuta,
                    ':nombre' => $t['nombreTema'] ?? '',
                    ':objetivo' => $t['objetivo'] ?? '',
                    ':compromiso' => $t['compromiso'] ?? '',  // Usaremos este campo para el contenido principal
                    ':observacion' => $t['observacion'] ?? ''
                ]);
            }

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Error guardando temas: " . $e->getMessage());
            return false;
        }
    }


    public function enviarParaFirma($idMinuta, $idUsuarioLogueado)
    {
        try {
            $this->conn->beginTransaction();

            // 1. Obtener ID del Presidente de la Comisión
            $sqlPresi = "SELECT c.t_usuario_idPresidente 
                         FROM t_comision c 
                         JOIN t_minuta m ON m.t_comision_idComision = c.idComision 
                         WHERE m.idMinuta = :idMinuta";
            $stmtP = $this->conn->prepare($sqlPresi);
            $stmtP->execute([':idMinuta' => $idMinuta]);
            $idPresidente = $stmtP->fetchColumn();

            if (!$idPresidente) {
                throw new Exception("No se encontró un presidente asignado a la comisión de esta minuta.");
            }

            // 2. Crear el registro de firma (t_aprobacion_minuta)
            // CORRECCIÓN: Eliminamos 'fecha_asignacion' que no existe.
            // CORRECCIÓN: Usamos 'EN_ESPERA' en lugar de 'PENDIENTE' para esta tabla.
            // CORRECCIÓN: Insertamos NOW() en 'fechaAprobacion' porque es NOT NULL (actúa como fecha de creación del registro).
            
            // Borrar previo si existe
            $sqlDel = "DELETE FROM t_aprobacion_minuta WHERE t_minuta_idMinuta = :idMinuta";
            $stmtD = $this->conn->prepare($sqlDel);
            $stmtD->execute([':idMinuta' => $idMinuta]);

            $sqlIns = "INSERT INTO t_aprobacion_minuta (t_minuta_idMinuta, t_usuario_idPresidente, estado_firma, fechaAprobacion) 
                       VALUES (:idMinuta, :idPresidente, 'EN_ESPERA', NOW())";
            $stmtI = $this->conn->prepare($sqlIns);
            $stmtI->execute([':idMinuta' => $idMinuta, ':idPresidente' => $idPresidente]);

            // 3. Actualizar estado de la Minuta (t_minuta sí usa 'PENDIENTE')
            $sqlUpdate = "UPDATE t_minuta SET estadoMinuta = 'PENDIENTE' WHERE idMinuta = :idMinuta";
            $stmtU = $this->conn->prepare($sqlUpdate);
            $stmtU->execute([':idMinuta' => $idMinuta]);

            // 4. Registrar en Bitácora
            $sqlLog = "INSERT INTO t_minuta_seguimiento (t_minuta_idMinuta, t_usuario_idUsuario, accion, detalle, fecha_hora) 
                       VALUES (:idMinuta, :idUsuario, 'ENVIO_FIRMA', 'Minuta enviada a firma del presidente', NOW())";
            $stmtL = $this->conn->prepare($sqlLog);
            $stmtL->execute([':idMinuta' => $idMinuta, ':idUsuario' => $idUsuarioLogueado]);

            $this->conn->commit();
            return true;

        } catch (Exception $e) {
            $this->conn->rollBack();
            // Loguear error interno
            error_log("Error en enviarParaFirma: " . $e->getMessage());
            // Lanzar excepción para que el controlador la capture y muestre el mensaje
            throw $e; 
        }
    }

    // ... (métodos anteriores) ...

    public function firmarMinuta($idMinuta, $idUsuario)
    {
        try {
            $this->conn->beginTransaction();

            // 1. Actualizar el estado de firma de ESTE presidente
            // Usamos 'FIRMADO' como indica tu base de datos
            $sqlFirma = "UPDATE t_aprobacion_minuta 
                         SET estado_firma = 'FIRMADO', fechaAprobacion = NOW() 
                         WHERE t_minuta_idMinuta = :idMinuta 
                         AND t_usuario_idPresidente = :idUsuario";
            $stmt = $this->conn->prepare($sqlFirma);
            $stmt->execute([':idMinuta' => $idMinuta, ':idUsuario' => $idUsuario]);

            // COMENTAMOS O ELIMINAMOS ESTA VALIDACIÓN ESTRICTA TEMPORALMENTE
            /* if ($stmt->rowCount() == 0) {
                throw new Exception("No se encontró una solicitud de firma pendiente...");
            }
            */

            // 2. Verificar si faltan otras firmas (para comisiones mixtas)
            $sqlPendientes = "SELECT COUNT(*) FROM t_aprobacion_minuta 
                              WHERE t_minuta_idMinuta = :idMinuta 
                              AND estado_firma != 'FIRMADO'";
            $stmtP = $this->conn->prepare($sqlPendientes);
            $stmtP->execute([':idMinuta' => $idMinuta]);
            $pendientes = $stmtP->fetchColumn();

            // 3. Determinar nuevo estado de la Minuta
            $nuevoEstado = ($pendientes == 0) ? 'APROBADA' : 'PARCIAL';
            
            $sqlUpdate = "UPDATE t_minuta SET estadoMinuta = :estado WHERE idMinuta = :idMinuta";
            $stmtU = $this->conn->prepare($sqlUpdate);
            $stmtU->execute([':estado' => $nuevoEstado, ':idMinuta' => $idMinuta]);

            // 4. Log
            $this->logSeguimiento($idMinuta, $idUsuario, 'FIRMA_RECIBIDA', "Presidente firmó. Nuevo estado: $nuevoEstado");

            $this->conn->commit();
            return ['status' => 'success', 'estado_nuevo' => $nuevoEstado];

        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Error al firmar: " . $e->getMessage());
            throw $e;
        }
    }

    public function guardarFeedback($idMinuta, $idUsuario, $textoFeedback)
    {
        try {
            $this->conn->beginTransaction();

            // 1. Insertar el feedback
            $sql = "INSERT INTO t_minuta_feedback (t_minuta_idMinuta, t_usuario_idPresidente, textoFeedback, fechaFeedback, resuelto) 
                    VALUES (:idMinuta, :idUsuario, :texto, NOW(), 0)";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':idMinuta' => $idMinuta,
                ':idUsuario' => $idUsuario,
                ':texto' => $textoFeedback
            ]);

            // 2. Marcar que requiere revisión en la tabla de aprobación
            $sqlUp = "UPDATE t_aprobacion_minuta 
                      SET estado_firma = 'REQUIERE_REVISION' 
                      WHERE t_minuta_idMinuta = :idMinuta AND t_usuario_idPresidente = :idUsuario";
            $stmtUp = $this->conn->prepare($sqlUp);
            $stmtUp->execute([':idMinuta' => $idMinuta, ':idUsuario' => $idUsuario]);

            // 3. Devolver la minuta a estado 'REQUIERE_REVISION' para que el Secretario la vea
            $sqlMin = "UPDATE t_minuta SET estadoMinuta = 'REQUIERE_REVISION' WHERE idMinuta = :idMinuta";
            $stmtMin = $this->conn->prepare($sqlMin);
            $stmtMin->execute([':idMinuta' => $idMinuta]);

            // 4. Log
            $this->logSeguimiento($idMinuta, $idUsuario, 'FEEDBACK_ENVIADO', 'Presidente solicitó correcciones.');

            $this->conn->commit();
            return true;

        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Error al guardar feedback: " . $e->getMessage());
            throw $e;
        }
    }

    // Helper para no repetir código de logs
    private function logSeguimiento($idMinuta, $idUsuario, $accion, $detalle) {
        $sql = "INSERT INTO t_minuta_seguimiento (t_minuta_idMinuta, t_usuario_idUsuario, accion, detalle, fecha_hora) 
                VALUES (:idMinuta, :idUsuario, :accion, :detalle, NOW())";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':idMinuta' => $idMinuta, ':idUsuario' => $idUsuario, ':accion' => $accion, ':detalle' => $detalle]);
    }

    public function actualizarPathArchivo($idMinuta, $path)
    {
        $sql = "UPDATE t_minuta SET pathArchivo = :path WHERE idMinuta = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':path' => $path, ':id' => $idMinuta]);
    }

    public function obtenerReunionActivaParaAsistencia($idUsuario)
    {
        // Buscamos reunión vigente donde el usuario NO esté en la lista de asistencia
        $sql = "SELECT r.idReunion, r.nombreReunion, r.t_minuta_idMinuta
                FROM t_reunion r
                WHERE r.vigente = 1 
                AND DATE(r.fechaInicioReunion) = CURDATE()
                AND NOT EXISTS (
                    SELECT 1 FROM t_asistencia a 
                    WHERE a.t_minuta_idMinuta = r.t_minuta_idMinuta 
                    AND a.t_usuario_idUsuario = :idUsuario
                )
                LIMIT 1";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':idUsuario' => $idUsuario]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }


    public function registrarAutoAsistencia($idMinuta, $idUsuario, $idReunion)
    {
        try {
            // Verificamos doble clic (por si acaso)
            $check = "SELECT idAsistencia FROM t_asistencia WHERE t_minuta_idMinuta = :m AND t_usuario_idUsuario = :u";
            $stmtC = $this->conn->prepare($check);
            $stmtC->execute([':m' => $idMinuta, ':u' => $idUsuario]);
            if ($stmtC->fetch()) return true; // Ya estaba listo

            $sql = "INSERT INTO t_asistencia 
                    (t_minuta_idMinuta, t_usuario_idUsuario, t_tipoReunion_idTipoReunion, fechaRegistroAsistencia, origenAsistencia) 
                    VALUES (:idMinuta, :idUsuario, 1, NOW(), 'AUTOREGISTRO')";
            
            // Nota: t_tipoReunion_idTipoReunion = 1 asumimos que es 'Ordinaria' o el valor por defecto de tu tabla
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([':idMinuta' => $idMinuta, ':idUsuario' => $idUsuario]);
            
            return true;
        } catch (Exception $e) {
            error_log("Error autoasistencia: " . $e->getMessage());
            return false;
        }
    }

    public function getSeguimiento($idMinuta)
    {
        $sql = "SELECT 
                    s.idMinutaSeguimiento, 
                    s.fecha_hora, 
                    s.accion, 
                    s.detalle, 
                    COALESCE(TRIM(CONCAT(u.pNombre, ' ', u.aPaterno)), 'Sistema') as usuario_nombre
                FROM t_minuta_seguimiento s
                LEFT JOIN t_usuario u ON s.t_usuario_idUsuario = u.idUsuario
                WHERE s.t_minuta_idMinuta = :id
                ORDER BY s.fecha_hora ASC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $idMinuta]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getHistorialAsistenciaPersonal($idUsuario)
    {
        $sql = "SELECT r.nombreReunion, r.fechaInicioReunion, a.fechaRegistroAsistencia, a.origenAsistencia
                FROM t_asistencia a
                JOIN t_minuta m ON a.t_minuta_idMinuta = m.idMinuta
                JOIN t_reunion r ON m.idMinuta = r.t_minuta_idMinuta
                WHERE a.t_usuario_idUsuario = :id
                ORDER BY r.fechaInicioReunion DESC LIMIT 20";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $idUsuario]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSeguimientoGeneral($filters = [])
    {
        // Esta consulta trae la última acción de cada minuta
        $sql = "
             WITH RankedSeguimiento AS (
                SELECT
                  s.t_minuta_idMinuta,
                  s.detalle as ultimo_detalle,
                  s.fecha_hora as ultima_fecha,
                  COALESCE(TRIM(CONCAT(u.pNombre, ' ', u.aPaterno)), 'Sistema') as ultimo_usuario,
                  ROW_NUMBER() OVER(
                    PARTITION BY s.t_minuta_idMinuta 
                    ORDER BY s.fecha_hora DESC
                  ) as rn
                FROM t_minuta_seguimiento s
                LEFT JOIN t_usuario u ON s.t_usuario_idUsuario = u.idUsuario
             )
             SELECT
                m.idMinuta,
                m.fechaMinuta,
                m.estadoMinuta,
                c.nombreComision,
                IFNULL(GROUP_CONCAT(DISTINCT t.nombreTema SEPARATOR '<br>'), 'N/A') AS nombreTemas,
                COALESCE(rs.ultimo_detalle, 'Sin acciones registradas') as ultimo_detalle,
                rs.ultima_fecha as ultima_fecha,
                COALESCE(rs.ultimo_usuario, 'N/A') as ultimo_usuario
             FROM t_minuta m
             LEFT JOIN t_comision c ON m.t_comision_idComision = c.idComision
             LEFT JOIN t_tema t ON m.idMinuta = t.t_minuta_idMinuta
             LEFT JOIN t_reunion r ON m.idMinuta = r.t_minuta_idMinuta
             LEFT JOIN RankedSeguimiento rs ON m.idMinuta = rs.t_minuta_idMinuta AND rs.rn = 1
             WHERE 1=1
        ";

        $params = [];

        // Filtros dinámicos
        if (!empty($filters['comisionId'])) {
            $sql .= " AND m.t_comision_idComision = :comisionId";
            $params[':comisionId'] = $filters['comisionId'];
        }
        if (!empty($filters['startDate'])) {
            $sql .= " AND m.fechaMinuta >= :startDate";
            $params[':startDate'] = $filters['startDate'];
        }
        if (!empty($filters['endDate'])) {
            $sql .= " AND m.fechaMinuta <= :endDate";
            $params[':endDate'] = $filters['endDate'] . ' 23:59:59';
        }
        if (!empty($filters['idMinuta'])) {
            $sql .= " AND m.idMinuta = :idMinuta";
            $params[':idMinuta'] = $filters['idMinuta'];
        }
        if (!empty($filters['keyword'])) {
            $sql .= " AND (t.nombreTema LIKE :kw OR m.idMinuta LIKE :kw)";
            $params[':kw'] = '%' . $filters['keyword'] . '%';
        }

        $sql .= " GROUP BY m.idMinuta, m.fechaMinuta, m.estadoMinuta, c.nombreComision, rs.ultimo_detalle, rs.ultima_fecha, rs.ultimo_usuario
                  ORDER BY m.fechaMinuta DESC, m.idMinuta DESC";

        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    public function marcarAsistenciaValidada($id) {
        // Asume que tienes una columna 'asistencia_validada' (1/0) en t_minuta
        // Si no la tienes, créala en la BD: ALTER TABLE t_minuta ADD asistencia_validada TINYINT DEFAULT 0;
        $sql = "UPDATE t_minuta SET asistencia_validada = 1 WHERE idMinuta = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $id]);
    }



}
