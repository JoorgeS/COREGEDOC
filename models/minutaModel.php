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
    public function getMinutasByEstado($estado, $startDate, $endDate)
    {
        // 1. Validar y formatear fechas
        // $endDate = $endDate . ' 23:59:59';  <-- LÍNEA ELIMINADA
        // Se asume que el controlador ya envía la $endDate con la hora 23:59:59

        // 2. Definir la lógica de filtrado de path (como en tu archivo original)
        $pathFilter = "";
        if ($estado == 'BORRADOR') {
            $pathFilter = "AND COALESCE(m.pathArchivo, '') = ''";
        } else {
            // Para 'PENDIENTE' y 'APROBADA'
            $pathFilter = "AND COALESCE(m.pathArchivo, '') <> ''";
        }

        // 3. CONSTRUIR LA CONSULTA UNIFICADA Y CORREGIDA
        // Nota: $pathFilter se interpola directamente en la string (requiere comillas dobles)
        $sql = "
            SELECT 
                m.idMinuta,
                m.t_comision_idComision, 
                c.nombreComision, 
                u.pNombre AS presidenteNombre,
                u.aPaterno AS presidenteApellido,
                m.fechaMinuta AS fecha,
                m.horaMinuta AS hora,
                m.estadoMinuta,
                m.presidentesRequeridos,
                m.pathArchivo,

                (SELECT COUNT(DISTINCT am_count.t_usuario_idPresidente) 
                 FROM t_aprobacion_minuta am_count 
                 WHERE am_count.t_minuta_idMinuta = m.idMinuta
                 AND am_count.estado_firma = 'FIRMADO') AS firmasActuales,

                (SELECT COUNT(*) 
                 FROM t_aprobacion_minuta am3 
                 WHERE am3.t_minuta_idMinuta = m.idMinuta 
                 AND am3.estado_firma = 'REQUIERE_REVISION') AS tieneFeedback,

                IFNULL(GROUP_CONCAT(DISTINCT t.nombreTema ORDER BY t.idTema SEPARATOR '<br>'), 'N/A') AS nombreTemas,
                IFNULL(GROUP_CONCAT(DISTINCT t.objetivo   ORDER BY t.idTema SEPARATOR '<br>'), 'N/A') AS objetivos,
                COUNT(DISTINCT adj.idAdjunto) AS totalAdjuntos

            FROM t_minuta m
            
            LEFT JOIN t_comision c ON m.t_comision_idComision = c.idComision
            LEFT JOIN t_usuario u ON m.t_usuario_idPresidente = u.idUsuario
            LEFT JOIN t_tema t ON t.t_minuta_idMinuta = m.idMinuta
            LEFT JOIN t_adjunto adj ON adj.t_minuta_idMinuta = m.idMinuta

            WHERE 
                m.estadoMinuta = :estado
                AND m.fechaMinuta >= :startDate 
                AND m.fechaMinuta <= :endDate
                $pathFilter 

            GROUP BY
                m.idMinuta, 
                m.t_comision_idComision, c.nombreComision, 
                u.pNombre, u.aPaterno,
                m.fechaMinuta, m.horaMinuta,
                m.estadoMinuta, m.presidentesRequeridos, m.pathArchivo
        
            ORDER BY m.fechaMinuta DESC, m.idMinuta DESC";

        // 4. Preparar y ejecutar la consulta USANDO TU MÉTODO 'consultarBD'
        try {
            $valores = [
                ':estado' => $estado,
                ':startDate' => $startDate,
                ':endDate' => $endDate
            ];

            // Usamos el método que SÍ existe en tu conector
            $result = $this->db_connector->consultarBD($sql, $valores);

            // Asumiendo que consultarBD devuelve un array en éxito
            if (is_array($result)) {
                return $result;
            } else {
                // Si 'consultarBD' devuelve false o un error
                error_log("Error en MinutaModel::getMinutasByEstado - consultarBD no devolvió un array. SQL: " . $sql);
                return [];
            }
        } catch (Exception $e) { // Captura genérica por si acaso
            // Registra el error
            error_log("Error (getMinutasByEstado): " . $e->getMessage() . " | SQL: " . $sql);
            return []; // Devuelve vacío para que la página no se rompa
        }
    }

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
