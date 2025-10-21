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
     * READ: Obtiene minutas por estado con filtros opcionales.
     */
    public function getMinutasByEstado($estado, $startDate = null, $endDate = null, $themeName = null)
    {
        // Base de la consulta (los placeholders :nombre siguen igual)
        $sql = "SELECT
                    m.idMinuta AS idMinuta,
                    m.t_usuario_idPresidente,
                    m.estadoMinuta,
                    m.pathArchivo,
                    m.fechaMinuta, /* <-- Fecha de creación */
                    GROUP_CONCAT(DISTINCT t.nombreTema SEPARATOR '; ') AS nombreTema,
                    GROUP_CONCAT(DISTINCT t.objetivo SEPARATOR '; ') AS objetivo,
                    GROUP_CONCAT(DISTINCT CONCAT(u.pNombre, ' ', u.aPaterno) SEPARATOR ', ') AS asistentes
                FROM t_minuta m
                LEFT JOIN t_tema t ON t.t_minuta_idMinuta = m.idMinuta
                LEFT JOIN t_asistencia a ON a.t_minuta_idMinuta = m.idMinuta
                LEFT JOIN t_usuario u ON a.t_usuario_idUsuario = u.idUsuario
                WHERE m.estadoMinuta = :estado"; // Filtro base por estado

        // --- INICIO DE LA CORRECCIÓN ---
        // Construimos el array $valores con claves SIN los dos puntos ':'
        // para que coincida con lo que espera class.conectorDB.php
        $valores = ['estado' => $estado];

        // Añadir filtros condicionales (también con claves sin ':')
        if ($startDate) {
            $sql .= " AND m.fechaMinuta >= :startDate";
            $valores['startDate'] = $startDate; // Clave 'startDate' (sin ':')
        }

        if ($endDate) {
            $sql .= " AND m.fechaMinuta <= :endDateWithTime";
            // Usamos una clave diferente ('endDateWithTime') para evitar colisiones
            $valores['endDateWithTime'] = $endDate . ' 23:59:59'; // Clave 'endDateWithTime' (sin ':')
        }

        if ($themeName) {
            $sql .= " AND t.nombreTema LIKE :themeName";
            $valores['themeName'] = '%' . $themeName . '%'; // Clave 'themeName' (sin ':')
        }
        // --- FIN DE LA CORRECCIÓN ---

        // Agrupar y Ordenar (SQL sigue igual)
        $sql .= " GROUP BY m.idMinuta
                  ORDER BY m.fechaMinuta DESC";


        // --- Ejecutar ---
        // Llamamos a consultarBD con el array $valores ahora formateado correctamente
        $result = $this->db_connector->consultarBD($sql, $valores);


        // Manejo de error si la consulta falla (devuelve false)
        if ($result === false) {
            // Es útil registrar el error para depuración futura
            error_log("Error en consulta SQL (getMinutasByEstado): " . $sql . " Valores: " . print_r($valores, true));
            return []; // Devolver vacío en caso de error SQL
        }

        // Devolver el resultado (puede ser un array vacío si no hay coincidencias)
        return is_array($result) ? $result : [];
    } // Fin de getMinutasByEstado


    // --- El resto de tus funciones (sin cambios) ---
    public function getAllMinutas()
    {
        $sql = "SELECT idTema, t_minuta_idMinuta, nombreTema, objetivo, compromiso, observacion
                FROM t_tema ORDER BY t_minuta_idMinuta DESC, idTema DESC";
        // Asumiendo que esta consulta no usa parámetros o usa $valores vacío
        $result = $this->db_connector->consultarBD($sql, []);
        return is_array($result) ? $result : [];
    }

    public function getTemaById($id)
    {
        $sql = "SELECT idTema, t_minuta_idMinuta, nombreTema, objetivo, compromiso, observacion
                FROM t_tema WHERE idTema = :idTema";
        // Ajustamos los valores aquí también
        $valores = ['idTema' => (int)$id]; // Clave sin ':'
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
        // Ajustamos los valores aquí también
        $valores = [
            'nombreTema' => $data['nombreTema'],
            'objetivo' => $data['objetivo'],
            'compromiso' => $data['compromiso'],
            'observacion' => $data['observacion'],
            'idTema' => (int)$id // Claves sin ':'
        ];
        // Aquí asumimos que consultarBD devuelve true/false para UPDATE
        return $this->db_connector->consultarBD($sql, $valores);
    }
} // Fin de la clase MinutaModel