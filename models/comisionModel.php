<?php
// models/ComisionModel.php

// Incluye tu clase de conexión existente
require_once __DIR__ . '/../class/class.conectorDB.php';

class ComisionModel {
    private $db_connector;

    public function __construct() {
        // Instancia tu clase de conexión para acceder a los métodos
        // Asumo que tu conectorDB se conecta automáticamente en su constructor
        $this->db_connector = new conectorDB(); 
    }

    /**
     * READ: Obtiene todas las comisiones.
     * @param bool $incluir_inactivas Si es true, incluye las comisiones con vigencia = 0.
     * @return array
     */
    public function getAllComisiones($incluir_inactivas = false) {
        $sql = "SELECT idComision, nombreComision, vigencia 
                FROM t_comision";
        
        if (!$incluir_inactivas) {
            $sql .= " WHERE vigencia = :vigencia_activa";
            $valores = ['vigencia_activa' => 1];
        } else {
            $valores = [];
        }
        
        $sql .= " ORDER BY nombreComision ASC";

        // Usar tu método genérico para ejecutar la consulta
        $result = $this->db_connector->consultarBD($sql, $valores);
        
        // Si result es un array (resultados) lo retornamos, si no, retornamos []
        return is_array($result) ? $result : [];
    }

    /**
     * CREATE: Inserta una nueva comisión.
     * @param string $nombre
     * @param int $vigencia
     * @return bool
     */
    public function createComision($nombre, $vigencia) {
        $sql = "INSERT INTO t_comision (nombreComision, vigencia) 
                VALUES (:nombreComision, :vigencia)";
        
        $valores = [
            'nombreComision' => $nombre,
            'vigencia' => $vigencia
        ];

        // Usar tu método genérico (debería retornar true/false para INSERT)
        return $this->db_connector->consultarBD($sql, $valores);
    }

    /**
     * READ: Obtiene una comisión por ID.
     * @param int $id
     * @return array|null
     */
    public function getComisionById($id) {
        $sql = "SELECT idComision, nombreComision, vigencia 
                FROM t_comision WHERE idComision = :idComision";
        
        // Asegúrate de que $id sea tratado como un entero
        $valores = ['idComision' => (int)$id];

        // Usar tu método genérico
        $result = $this->db_connector->consultarBD($sql, $valores);
        
        // Si hay un resultado, fetchAll retorna un array de un elemento; si no, false.
        if (is_array($result) && count($result) > 0) {
            return $result[0]; // Retornar solo la fila (el primer elemento)
        }
        return null;
    }

    /**
     * UPDATE: Actualiza una comisión existente.
     * @param int $id
     * @param string $nombre
     * @param int $vigencia
     * @return bool
     */
    public function updateComision($id, $nombre, $vigencia) {
        $sql = "UPDATE t_comision 
                SET nombreComision = :nombreComision, vigencia = :vigencia 
                WHERE idComision = :idComision";
        
        $valores = [
            'nombreComision' => $nombre,
            'vigencia' => $vigencia,
            'idComision' => $id
        ];

        // Usar tu método genérico (debería retornar true/false para UPDATE)
        return $this->db_connector->consultarBD($sql, $valores);
    }

    /**
     * DELETE: Cambia la vigencia a 0 para "eliminar" lógicamente la comisión.
     * @param int $id
     * @return bool
     */
    public function deleteComision($id) {
        $sql = "UPDATE t_comision SET vigencia = :vigencia WHERE idComision = :idComision";
        
        $valores = [
            'vigencia' => 0,
            'idComision' => $id
        ];

        // Usar tu método genérico (debería retornar true/false para UPDATE)
        return $this->db_connector->consultarBD($sql, $valores);
    }
}
?>