<?php
// models/MinutaModel.php

require_once __DIR__ . '/../class/class.conectorDB.php';

class MinutaModel
{
    private $db_connector;

    public function __construct()
    {
        $this->db_connector = new conectorDB();
    }

    /**
     * READ: Obtiene minutas por estado con filtros opcionales y cuenta de adjuntos.
     * - Estado calculado por pathArchivo (sin depender de columna estadoMinuta en BD).
     * - Filtro "themeName" busca en t_tema.nombreTema O t_tema.objetivo.
     * - Mantiene alias usados por las vistas.
     */
    /**
     * READ: Obtiene minutas por estado con filtros opcionales y cuenta de adjuntos.
     * - Estado calculado por pathArchivo (sin depender de columna estadoMinuta en BD).
     * - Filtro "themeName" busca en t_tema.nombreTema O t_tema.objetivo.
     * - Mantiene alias usados por las vistas.
     */
    /**
     * READ: Obtiene minutas por estado con filtros opcionales y cuenta de adjuntos.
     * - Estado calculado por pathArchivo (sin depender de columna estadoMinuta en BD).
     * - Filtro "themeName" busca en t_tema.nombreTema O t_tema.objetivo.
     * - Mantiene alias usados por las vistas.
     */
    // models/MinutaModel.php

    /**
     * READ: Obtiene minutas por estado con filtros opcionales y cuenta de adjuntos.
     * (Versión corregida que agrupa estados pendientes y filtra por tema)
     */
    public function getMinutasByEstado($estado, $startDate = null, $endDate = null, $themeName = null)
    {
        // Normalizamos estado (Esta es tu lógica original, la respetamos)
        $estado = strtoupper(trim((string)$estado)) === 'APROBADA' ? 'APROBADA' : 'PENDIENTE';

        // SELECT con alias esperados por las vistas
        $sql = "
            SELECT
                m.idMinuta AS idMinuta,
                
                /* CAMPOS NUEVOS AÑADIDOS */
                c.nombreComision, 
                u.pNombre AS presidenteNombre,
                u.aPaterno AS presidenteApellido,
                /* FIN CAMPOS NUEVOS */

                CASE 
                    WHEN COALESCE(m.pathArchivo,'') <> '' THEN 'APROBADA'
                    ELSE 'PENDIENTE'
                END AS estadoMinuta,
                m.pathArchivo,
                m.fechaMinuta,

                IFNULL(GROUP_CONCAT(DISTINCT t.nombreTema ORDER BY t.idTema SEPARATOR '<br>'), 'N/A') AS nombreTemas,
                IFNULL(GROUP_CONCAT(DISTINCT t.objetivo   ORDER BY t.idTema SEPARATOR '<br>'), 'N/A') AS objetivos,
                COUNT(DISTINCT adj.idAdjunto) AS totalAdjuntos

            FROM t_minuta m
            
            /* JOINS de tu lógica original */
            LEFT JOIN t_tema    t   ON t.t_minuta_idMinuta   = m.idMinuta
            LEFT JOIN t_adjunto adj ON adj.t_minuta_idMinuta = m.idMinuta

            /* JOINS NUEVOS AÑADIDOS */
            LEFT JOIN t_comision c ON m.t_comision_idComision = c.idComision
            LEFT JOIN t_usuario u ON c.t_usuario_idPresidente = u.idUsuario

            WHERE 1=1
        ";

        $valores = [];

        // Filtro por estado usando pathArchivo (Tu lógica original)
        if ($estado === 'APROBADA') {
            $sql .= " AND COALESCE(m.pathArchivo,'') <> '' ";
        } else { // PENDIENTE
            $sql .= " AND COALESCE(m.pathArchivo,'') = '' ";
        }

        // Filtros de fecha (Tu lógica original)
        if (!empty($startDate)) {
            $sql .= " AND m.fechaMinuta >= :startDate ";
            $valores['startDate'] = $startDate;
        }
        if (!empty($endDate)) {
            // ¡IMPORTANTE! Tu lógica antigua era MÁS INTELIGENTE aquí.
            // Añadía ' 23:59:59' para incluir el día completo. La mantendremos.
            $sql .= " AND m.fechaMinuta <= :endDate ";
            $valores['endDate'] = $endDate . ' 23:59:59';
        }

        // Filtro por palabra clave (Tu lógica original, que usa EXISTS y es excelente)
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

        // Agrupación y orden (MODIFICADO para incluir los campos nuevos)
        $sql .= "
            GROUP BY m.idMinuta, c.nombreComision, u.pNombre, u.aPaterno, m.pathArchivo, m.fechaMinuta
            ORDER BY m.fechaMinuta DESC, m.idMinuta DESC
        ";

        $result = $this->db_connector->consultarBD($sql, $valores);

        if ($result === false) {
            error_log("Error SQL (getMinutasByEstado): " . $sql . " | Valores: " . print_r($valores, true));
            return [];
        }

        // Asegurarnos de devolver siempre un array (Tu lógica original)
        $minutas = is_array($result) ? $result : [];

        // Claves seguras para la vista (Tu lógica original)
        foreach ($minutas as &$minuta) {
            $minuta['nombreTemas']   = $minuta['nombreTemas']   ?? 'N/A';
            $minuta['objetivos']     = $minuta['objetivos']     ?? 'N/A';
            $minuta['totalAdjuntos'] = $minuta['totalAdjuntos'] ?? 0;

            // (NUEVO) Aseguramos los campos nuevos por si el JOIN falla
            $minuta['nombreComision'] = $minuta['nombreComision'] ?? 'Comisión no asignada';
            $minuta['presidenteNombre'] = $minuta['presidenteNombre'] ?? 'Presidente no asignado';
            $minuta['presidenteApellido'] = $minuta['presidenteApellido'] ?? '';
        }
        unset($minuta);

        return $minutas;
    } // Fin de getMinutasByEstado
    // --- El resto de tus funciones (SIN CAMBIOS) ---
    public function getAllMinutas()
    {
        $sql = "SELECT idTema, t_minuta_idMinuta, nombreTema, objetivo, compromiso, observacion
                FROM t_tema ORDER BY t_minuta_idMinuta DESC, idTema DESC";
        $result = $this->db_connector->consultarBD($sql, []);
        return is_array($result) ? $result : [];
    }

    public function getTemaById($id)
    {
        $sql = "SELECT idTema, t_minuta_idMinuta, nombreTema, objetivo, compromiso, observacion
                FROM t_tema WHERE idTema = :idTema";
        $valores = ['idTema' => (int)$id];
        $result = $this->db_connector->consultarBD($sql, $valores);
        if (is_array($result) && count($result) > 0) {
            return $result[0];
        }
        return null;
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
        return $this->db_connector->consultarBD($sql, $valores);
    }
}
