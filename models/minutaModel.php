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
    public function getMinutasByEstado($estado, $startDate = null, $endDate = null, $themeName = null)
    {
        // Normalizamos estado
        $estado = strtoupper(trim((string)$estado)) === 'APROBADA' ? 'APROBADA' : 'PENDIENTE';

        // SELECT con alias esperados por las vistas
        $sql = "
            SELECT 
    m.idMinuta,
    m.t_comision_idComision, 
    c.nombreComision, 
    u.pNombre AS presidenteNombre,
    u.aPaterno AS presidenteApellido,
    m.fechaMinuta AS fecha,
    m.horaMinuta AS hora,
    m.estadoMinuta, -- Se mantiene el estado de la nueva consulta
    m.presidentesRequeridos,
    m.pathArchivo, -- Se agrega el pathArchivo desde la consulta original

    -- (NUEVO) Conteo de firmas actuales (de la nueva consulta)
    (SELECT COUNT(DISTINCT am_count.t_usuario_idPresidente) 
     FROM t_aprobacion_minuta am_count 
     WHERE am_count.t_minuta_idMinuta = m.idMinuta
     AND am_count.estado_firma = 'FIRMADO') AS firmasActuales,

    -- (NUEVO) Verificar si tiene feedback (de la nueva consulta)
    (SELECT COUNT(*) 
     FROM t_aprobacion_minuta am3 
     WHERE am3.t_minuta_idMinuta = m.idMinuta 
       AND am3.estado_firma = 'REQUIERE_REVISION') AS tieneFeedback,

    -- (ORIGINAL) Temas y objetivos agregados
    IFNULL(GROUP_CONCAT(DISTINCT t.nombreTema ORDER BY t.idTema SEPARATOR '<br>'), 'N/A') AS nombreTemas,
    IFNULL(GROUP_CONCAT(DISTINCT t.objetivo   ORDER BY t.idTema SEPARATOR '<br>'), 'N/A') AS objetivos,

    -- (ORIGINAL) Conteo de adjuntos reales
    COUNT(DISTINCT adj.idAdjunto) AS totalAdjuntos

FROM t_minuta m
/* Joins de la NUEVA consulta */
LEFT JOIN t_comision c ON m.t_comision_idComision = c.idComision
LEFT JOIN t_usuario u ON m.t_usuario_idPresidente = u.idUsuario
/* Joins de la consulta ORIGINAL */
LEFT JOIN t_tema    t   ON t.t_minuta_idMinuta   = m.idMinuta
LEFT JOIN t_adjunto adj ON adj.t_minuta_idMinuta = m.idMinuta

/* Filtro de la NUEVA consulta */
WHERE m.estadoMinuta = :estado

/* (REQUERIDO) GROUP BY para las funciones de agregación (COUNT y GROUP_CONCAT) */
GROUP BY
    m.idMinuta,
    m.t_comision_idComision, 
    c.nombreComision, 
    u.pNombre,
    u.aPaterno,
    m.fechaMinuta,
    m.horaMinuta,
    m.estadoMinuta,
    m.presidentesRequeridos,
    m.pathArchivo

/* Orden de la NUEVA consulta */
ORDER BY m.idMinuta DESC
        ";

        $valores = [];

        // Filtro por estado usando pathArchivo
        if ($estado === 'APROBADA') {
            $sql .= " AND COALESCE(m.pathArchivo,'') <> '' ";
        } else { // PENDIENTE
            $sql .= " AND COALESCE(m.pathArchivo,'') = '' ";
        }

        // Filtros de fecha (incluye fin de día)
        if (!empty($startDate)) {
            $sql .= " AND m.fechaMinuta >= :startDate ";
            $valores['startDate'] = $startDate;
        }
        if (!empty($endDate)) {
            $sql .= " AND m.fechaMinuta <= :endDate ";
            $valores['endDate'] = $endDate . ' 23:59:59';
        }

        // Filtro por palabra clave: busca en NOMBRE DEL TEMA O OBJETIVO
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

        // Agrupación y orden
        $sql .= "
            GROUP BY m.idMinuta, m.t_usuario_idPresidente, m.pathArchivo, m.fechaMinuta
            ORDER BY m.fechaMinuta DESC, m.idMinuta DESC
        ";

        $result = $this->db_connector->consultarBD($sql, $valores);

        if ($result === false) {
            error_log("Error SQL (getMinutasByEstado): " . $sql . " | Valores: " . print_r($valores, true));
            return [];
        }

        // Asegurarnos de devolver siempre un array
        $minutas = is_array($result) ? $result : [];

        // Claves seguras para la vista
        foreach ($minutas as &$minuta) {
            $minuta['nombreTemas']   = $minuta['nombreTemas']   ?? 'N/A';
            $minuta['objetivos']     = $minuta['objetivos']     ?? 'N/A';
            $minuta['totalAdjuntos'] = $minuta['totalAdjuntos'] ?? 0;
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
