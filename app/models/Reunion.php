<?php
namespace App\Models;

use App\Config\Database;
use PDO;
use Exception;

class Reunion
{
    private $conn;

    public function __construct()
    {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public function listar()
    {
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

    public function obtenerPorId($id)
    {
        $sql = "SELECT * FROM t_reunion WHERE idReunion = :id AND vigente = 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function crear($data)
    {
        $sql = "INSERT INTO t_reunion (nombreReunion, t_comision_idComision, fechaInicioReunion, fechaTerminoReunion, vigente) 
                VALUES (:nombre, :comision, :inicio, :termino, 1)";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':nombre' => $data['nombre'],
            ':comision' => $data['comision'],
            ':inicio' => $data['inicio'],
            ':termino' => $data['termino']
        ]);
        return $this->conn->lastInsertId();
    }

    public function actualizar($id, $data)
    {
        $sql = "UPDATE t_reunion 
                SET nombreReunion = :nombre, t_comision_idComision = :comision, 
                    fechaInicioReunion = :inicio, fechaTerminoReunion = :termino 
                WHERE idReunion = :id";
        
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':nombre' => $data['nombre'],
            ':comision' => $data['comision'],
            ':inicio' => $data['inicio'],
            ':termino' => $data['termino'],
            ':id' => $id
        ]);
    }

    public function eliminar($id)
    {
        // Borrado lógico
        $sql = "UPDATE t_reunion SET vigente = 0 WHERE idReunion = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }

    public function vincularMinuta($idReunion, $idMinuta)
    {
        $sql = "UPDATE t_reunion SET t_minuta_idMinuta = :idMinuta WHERE idReunion = :idReunion";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':idMinuta' => $idMinuta, ':idReunion' => $idReunion]);
    }
    
    // Helper para obtener datos necesarios para crear la minuta automáticamente
    public function obtenerDatosParaMinuta($idReunion)
    {
        $sql = "SELECT r.t_comision_idComision, r.fechaInicioReunion, c.t_usuario_idPresidente
                FROM t_reunion r
                JOIN t_comision c ON r.t_comision_idComision = c.idComision
                WHERE r.idReunion = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $idReunion]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}