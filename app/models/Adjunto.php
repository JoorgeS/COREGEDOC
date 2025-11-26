<?php
namespace App\Models;

use App\Config\Database;
use PDO;

class Adjunto
{
    private $conn;

    public function __construct()
    {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public function obtenerPorMinuta($idMinuta)
    {
        $sql = "SELECT * FROM t_adjunto WHERE t_minuta_idMinuta = :id ORDER BY idAdjunto DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $idMinuta]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPorId($idAdjunto)
    {
        $sql = "SELECT * FROM t_adjunto WHERE idAdjunto = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $idAdjunto]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function guardar($idMinuta, $path, $tipo = 'file')
    {
        $sql = "INSERT INTO t_adjunto (t_minuta_idMinuta, pathAdjunto, tipoAdjunto) 
                VALUES (:idMinuta, :path, :tipo)";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':idMinuta' => $idMinuta,
            ':path' => $path,
            ':tipo' => $tipo
        ]);
        return $this->conn->lastInsertId();
    }

    public function eliminar($idAdjunto)
    {
        $sql = "DELETE FROM t_adjunto WHERE idAdjunto = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':id' => $idAdjunto]);
    }
}