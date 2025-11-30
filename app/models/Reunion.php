<?php
namespace App\Models;

use App\Config\Database;
use PDO;

class Reunion
{
    private $conn;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public function listar() {
        // Traemos también las comisiones mixtas para mostrarlas si es necesario
        $sql = "SELECT r.*, c.nombreComision, m.estadoMinuta, m.idMinuta as minutaId
                FROM t_reunion r
                LEFT JOIN t_comision c ON r.t_comision_idComision = c.idComision
                LEFT JOIN t_minuta m ON r.t_minuta_idMinuta = m.idMinuta
                WHERE r.vigente = 1
                ORDER BY r.fechaInicioReunion DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPorId($id) {
        $sql = "SELECT * FROM t_reunion WHERE idReunion = :id AND vigente = 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // AQUI ESTABA EL ERROR: Faltaba guardar las mixtas
    public function crear($data) {
        $sql = "INSERT INTO t_reunion 
                (nombreReunion, t_comision_idComision, t_comision_idComision_mixta, t_comision_idComision_mixta2, fechaInicioReunion, fechaTerminoReunion, vigente) 
                VALUES (:nombre, :com1, :com2, :com3, :inicio, :termino, 1)";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':nombre'  => $data['nombre'],
            ':com1'    => $data['comision'],
            ':com2'    => !empty($data['comision2']) ? $data['comision2'] : null, // Manejo de nulos
            ':com3'    => !empty($data['comision3']) ? $data['comision3'] : null,
            ':inicio'  => $data['inicio'],
            ':termino' => $data['termino']
        ]);
        return $this->conn->lastInsertId();
    }

    public function actualizar($id, $data) {
        $sql = "UPDATE t_reunion 
                SET nombreReunion = :nombre, 
                    t_comision_idComision = :com1,
                    t_comision_idComision_mixta = :com2,
                    t_comision_idComision_mixta2 = :com3,
                    fechaInicioReunion = :inicio, 
                    fechaTerminoReunion = :termino 
                WHERE idReunion = :id";
        
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':nombre'  => $data['nombre'],
            ':com1'    => $data['comision'],
            ':com2'    => !empty($data['comision2']) ? $data['comision2'] : null,
            ':com3'    => !empty($data['comision3']) ? $data['comision3'] : null,
            ':inicio'  => $data['inicio'],
            ':termino' => $data['termino'],
            ':id'      => $id
        ]);
    }

    public function eliminar($id) {
        // Solo permite eliminar si NO tiene minuta asociada (Lógica de negocio)
        $sql = "UPDATE t_reunion SET vigente = 0 WHERE idReunion = :id AND t_minuta_idMinuta IS NULL";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }

    public function vincularMinuta($idReunion, $idMinuta) {
        $sql = "UPDATE t_reunion SET t_minuta_idMinuta = :idMinuta WHERE idReunion = :idReunion";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':idMinuta' => $idMinuta, ':idReunion' => $idReunion]);
    }
    
    public function obtenerDatosParaMinuta($idReunion) {
        // Necesitamos datos para crear la minuta, incluyendo el presidente de la comision principal
        $sql = "SELECT r.t_comision_idComision, r.fechaInicioReunion, c.t_usuario_idPresidente
                FROM t_reunion r
                JOIN t_comision c ON r.t_comision_idComision = c.idComision
                WHERE r.idReunion = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $idReunion]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}