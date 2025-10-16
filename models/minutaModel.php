<?php
// models/MinutaModel.php

require_once __DIR__ . '/../class/class.conectorDB.php';

class MinutaModel {
    private $db_connector;

    public function __construct() {
        $this->db_connector = new conectorDB(); 
    }

    /**
     * READ: Obtiene el listado de temas/minutas guardados directamente de t_tema.
     * @return array
     */
    public function getAllMinutas() {
        $sql = "SELECT 
                    idTema, 
                    nombreTema, 
                    objetivo, 
                    compromiso,
                    observacion
                FROM 
                    t_tema
                ORDER BY 
                    idTema DESC";
        
        $valores = [];
        $result = $this->db_connector->consultarBD($sql, $valores);
        
        return is_array($result) ? $result : [];
    }
    
    /**
     * READ: Obtiene un solo tema (minuta) por ID.
     * ESTE ES EL MÉTODO QUE FALTA EN TU CÓDIGO (LÍNEAS 25 y 39 del controlador)
     * @param int $id
     * @return array|null
     */
    public function getTemaById($id) {
        $sql = "SELECT 
                    idTema, 
                    nombreTema, 
                    objetivo, 
                    compromiso,
                    observacion
                FROM 
                    t_tema
                WHERE 
                    idTema = :idTema";
        
        $valores = ['idTema' => (int)$id];

        $result = $this->db_connector->consultarBD($sql, $valores);
        
        // Si hay un resultado, retorna solo la fila (el primer elemento)
        if (is_array($result) && count($result) > 0) {
            return $result[0]; 
        }
        return null;
    }

    /**
     * UPDATE: Actualiza el contenido de un tema existente.
     * ESTE ES EL MÉTODO NECESARIO PARA EL case 'update' del controlador
     * @param int $id
     * @param array $data (nombreTema, objetivo, compromiso, observacion)
     * @return bool
     */
    public function updateTema($id, $data) {
        $sql = "UPDATE t_tema 
                SET nombreTema = :nombreTema, 
                    objetivo = :objetivo, 
                    compromiso = :compromiso, 
                    observacion = :observacion 
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
}
?>