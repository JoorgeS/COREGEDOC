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
     */
    public function getMinutasByEstado($estado, $startDate = null, $endDate = null, $themeName = null)
    {
        // --- INICIO: AJUSTE CONSULTA SQL ---
        $sql = "SELECT
                    m.idMinuta AS idMinuta,
                    m.t_usuario_idPresidente,
                    m.estadoMinuta,
                    m.pathArchivo,
                    m.fechaMinuta,
                    -- Usaremos IFNULL para devolver 'N/A' si no hay temas
                    IFNULL(GROUP_CONCAT(DISTINCT t.nombreTema SEPARATOR '<br>'), 'N/A') AS nombreTemas, 
                    IFNULL(GROUP_CONCAT(DISTINCT t.objetivo SEPARATOR '<br>'), 'N/A') AS objetivos,
                    COUNT(DISTINCT adj.idAdjunto) AS totalAdjuntos 
                    
                FROM t_minuta m
                -- LEFT JOIN a t_tema para obtener nombres y objetivos
                LEFT JOIN t_tema t ON t.t_minuta_idMinuta = m.idMinuta
                -- LEFT JOIN a t_adjunto para contarlos
                LEFT JOIN t_adjunto adj ON adj.t_minuta_idMinuta = m.idMinuta 
                
                WHERE m.estadoMinuta = :estado"; 
        // --- FIN: AJUSTE CONSULTA SQL ---

        $valores = ['estado' => $estado];

        if ($startDate) {
            $sql .= " AND m.fechaMinuta >= :startDate";
            $valores['startDate'] = $startDate;
        }

        if ($endDate) {
            $sql .= " AND m.fechaMinuta <= :endDateWithTime";
            $valores['endDateWithTime'] = $endDate . ' 23:59:59';
        }

        // CORRECCIÓN FILTRO TEMA: Debe estar en HAVING porque filtra sobre GROUP_CONCAT
        // PERO es más eficiente filtrar ANTES de agrupar si es posible.
        // Lo dejamos en WHERE por ahora, podría necesitar ajuste si causa problemas con minutas sin temas.
        if (!empty($themeName)) {
             $sql .= " AND t.nombreTema LIKE :themeName"; // Filtra ANTES de agrupar
             $valores['themeName'] = '%' . $themeName . '%';
        }

        $sql .= " GROUP BY m.idMinuta
                  ORDER BY m.fechaMinuta DESC, m.idMinuta DESC";


        $result = $this->db_connector->consultarBD($sql, $valores);

        if ($result === false) {
            error_log("Error SQL (getMinutasByEstado): " . $sql . " | Valores: " . print_r($valores, true));
            return []; 
        }

        // Asegurarse de que siempre sea un array
        $minutas = is_array($result) ? $result : [];

        // --- INICIO: VERIFICACIÓN ADICIONAL ---
        // Vamos a asegurarnos de que las claves esperadas existan en cada minuta
        foreach ($minutas as &$minuta) { // Usamos '&' para modificar el array original
            $minuta['nombreTemas'] = $minuta['nombreTemas'] ?? 'N/A';
            $minuta['objetivos'] = $minuta['objetivos'] ?? 'N/A';
            $minuta['totalAdjuntos'] = $minuta['totalAdjuntos'] ?? 0;
        }
        unset($minuta); // Romper la referencia
        // --- FIN: VERIFICACIÓN ADICIONAL ---

        return $minutas;

    } // Fin de getMinutasByEstado


    // --- El resto de tus funciones (sin cambios) ---
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
            'nombreTema' => $data['nombreTema'],
            'objetivo' => $data['objetivo'],
            'compromiso' => $data['compromiso'],
            'observacion' => $data['observacion'],
            'idTema' => (int)$id
        ];
        return $this->db_connector->consultarBD($sql, $valores);
    }
} // Fin de la clase MinutaModel