<?php
// ============================================================
// models/MinutaModel.php
// ============================================================

// 1. Incluir la clase base de conexión
require_once __DIR__ . '/../class/class.conectorDB.php';

// ============================================================
// Clase: MinutaModel
// Descripción: Maneja todas las operaciones relacionadas con las
// minutas del sistema CORE.
// ============================================================
class MinutaModel extends BaseConexion
{
    private $db;

    // ------------------------------------------------------------
    // CONSTRUCTOR: establece conexión con la base de datos
    // ------------------------------------------------------------
    public function __construct()
    {
        $this->db = $this->conectar();
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    // ------------------------------------------------------------
    // OBTENER MINUTAS POR ESTADO (APROBADA / PENDIENTE)
    // ------------------------------------------------------------
    public function getMinutasByEstado($estado, $startDate = null, $endDate = null, $themeName = null)
    {
        // Normalizar estado
        $estado = strtoupper(trim((string)$estado)) === 'APROBADA' ? 'APROBADA' : 'PENDIENTE';

        // Consulta base
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
                m.fechaMinuta,
                IFNULL(GROUP_CONCAT(DISTINCT t.nombreTema ORDER BY t.idTema SEPARATOR '<br>'), 'N/A') AS nombreTemas,
                IFNULL(GROUP_CONCAT(DISTINCT t.objetivo ORDER BY t.idTema SEPARATOR '<br>'), 'N/A') AS objetivos,
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
            LEFT JOIN t_tema t ON t.t_minuta_idMinuta = m.idMinuta
            LEFT JOIN t_adjunto adj ON adj.t_minuta_idMinuta = m.idMinuta
            LEFT JOIN t_comision c ON m.t_comision_idComision = c.idComision
            LEFT JOIN t_usuario u ON c.t_usuario_idPresidente = u.idUsuario
            WHERE 1=1
        ";

        $valores = [];

        // Filtro por estado
        if ($estado === 'APROBADA') {
            $sql .= " AND COALESCE(m.pathArchivo,'') <> '' ";
        } else {
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

        // ------------------------------------------------------------
        // FILTRO POR PALABRA CLAVE (nombre de comisión, tema u objetivo)
        // ------------------------------------------------------------
        if (!empty($themeName)) {
            $sql .= " AND (
                c.nombreComision LIKE :kw
                OR EXISTS (
                    SELECT 1
                    FROM t_tema tt
                    WHERE tt.t_minuta_idMinuta = m.idMinuta
                      AND (
                          tt.nombreTema LIKE :kw
                          OR tt.objetivo LIKE :kw
                      )
                )
            )";
            $valores['kw'] = '%' . $themeName . '%';
        }

        $sql .= "
            GROUP BY m.idMinuta, c.nombreComision, u.pNombre, u.aPaterno, 
                     m.pathArchivo, m.fechaMinuta, m.presidentesRequeridos
            ORDER BY m.fechaMinuta DESC, m.idMinuta DESC
        ";

        // ------------------------------------------------------------
        // EJECUCIÓN SEGURA
        // ------------------------------------------------------------
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($valores);
            $minutas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log('Error SQL (getMinutasByEstado): ' . $e->getMessage());
            return [];
        }

        // Limpieza y valores por defecto
        foreach ($minutas as &$minuta) {
            $minuta['nombreTemas'] = $minuta['nombreTemas'] ?? 'N/A';
            $minuta['objetivos'] = $minuta['objetivos'] ?? 'N/A';
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

    // ------------------------------------------------------------
    // OTRAS FUNCIONES DEL MODELO (Estructura base)
    // ------------------------------------------------------------

    public function getAllMinutas()
    {
        // TODO: Implementar
    }

    public function getTemaById($id)
    {
        // TODO: Implementar
    }

    public function updateTema($id, $data)
    {
        // TODO: Implementar
    }

    public function actualizarPathBorrador($idMinuta, $path)
    {
        // TODO: Implementar
    }

    public function logAccion($minuta_id, $usuario_id, $accion, $detalle = '')
    {
        // TODO: Implementar
    }

    public function getSeguimiento($minuta_id)
    {
        // TODO: Implementar
    }

    public function getMinutaById($idMinuta)
    {
        // TODO: Implementar
    }

    public function getUltimoSeguimientoParaPendientes($filters = [])
    {
        // TODO: Implementar
    }

    public function getComisiones()
    {
        // TODO: Implementar
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
}
