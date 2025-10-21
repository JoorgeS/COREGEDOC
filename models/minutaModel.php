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
     * @param string $estado 'PENDIENTE' o 'APROBADA'
     * @param string|null $startDate Fecha de inicio (YYYY-MM-DD)
     * @param string|null $endDate Fecha de fin (YYYY-MM-DD)
     * @param string|null $themeName Parte del nombre del tema a buscar
     * @return array
     */
    public function getMinutasByEstado($estado, $startDate = null, $endDate = null, $themeName = null)
    {
        // --- INICIO DIAGNÓSTICO TEMPRANO ---
        // Muestra los parámetros recibidos por la función
        echo "";
        // --- FIN DIAGNÓSTICO TEMPRANO ---

        // Base de la consulta
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

        $valores = [':estado' => $estado];

        // Añadir filtros condicionales
        if ($startDate) {
            $sql .= " AND m.fechaMinuta >= :startDate";
            $valores[':startDate'] = $startDate;
        }
        if ($endDate) {
            $sql .= " AND m.fechaMinuta <= :endDate";
            $valores[':endDate'] = $endDate;
        }
        if ($themeName) {
            // Busca en CUALQUIERA de los temas asociados a la minuta
            $sql .= " AND t.nombreTema LIKE :themeName";
            $valores[':themeName'] = '%' . $themeName . '%';
        }

        // Agrupar y Ordenar
        $sql .= " GROUP BY m.idMinuta
                  ORDER BY m.fechaMinuta DESC";


        // --- INICIO DIAGNÓSTICO SQL ---
        // Imprime la consulta SQL completa y los valores que se usarán
        echo "";
        echo "";
        // --- FIN DIAGNÓSTICO SQL ---


        // --- Ejecutar ---
        $result = $this->db_connector->consultarBD($sql, $valores);


        // --- INICIO DIAGNÓSTICO RESULTADO ---
        // Imprime lo que devolvió la base de datos (false, array vacío, o array con datos)
        echo "";
        // --- FIN DIAGNÓSTICO RESULTADO ---


        return is_array($result) ? $result : [];
    } // Fin de getMinutasByEstado


    // --- El resto de tus funciones (getAllMinutas, getTemaById, updateTema) ---
    // (Van aquí sin cambios)

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
