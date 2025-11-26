<?php
namespace App\Models;

use App\Config\Database;
use PDO;

class Comision
{
    private $conn;
    private $table = 't_comision';

    public function __construct()
    {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    // --- Listar todas (con nombres de autoridades) ---
    public function getAll()
    {
        $sql = "SELECT c.*, 
                       CONCAT(up.pNombre, ' ', up.aPaterno) as nombrePresidente,
                       CONCAT(uv.pNombre, ' ', uv.aPaterno) as nombreVicepresidente
                FROM t_comision c
                LEFT JOIN t_usuario up ON c.t_usuario_idPresidente = up.idUsuario
                LEFT JOIN t_usuario uv ON c.t_usuario_idVicepresidente = uv.idUsuario
                WHERE c.vigencia = 1
                ORDER BY c.nombreComision ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // --- Obtener una por ID ---
    public function getById($id)
    {
        $sql = "SELECT * FROM " . $this->table . " WHERE idComision = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // --- Crear ---
    public function create($data)
    {
        $sql = "INSERT INTO " . $this->table . " (nombreComision, t_usuario_idPresidente, t_usuario_idVicepresidente, vigencia) 
                VALUES (:nombre, :presi, :vice, 1)";
        
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':nombre' => $data['nombreComision'],
            ':presi'  => !empty($data['presidente']) ? $data['presidente'] : null,
            ':vice'   => !empty($data['vicepresidente']) ? $data['vicepresidente'] : null
        ]);
    }

    // --- Actualizar ---
    public function update($id, $data)
    {
        $sql = "UPDATE " . $this->table . " SET 
                    nombreComision = :nombre, 
                    t_usuario_idPresidente = :presi,
                    t_usuario_idVicepresidente = :vice
                WHERE idComision = :id";
        
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':nombre' => $data['nombreComision'],
            ':presi'  => !empty($data['presidente']) ? $data['presidente'] : null,
            ':vice'   => !empty($data['vicepresidente']) ? $data['vicepresidente'] : null,
            ':id'     => $id
        ]);
    }

    // --- Eliminar (Lógico) ---
    public function delete($id)
    {
        $sql = "UPDATE " . $this->table . " SET vigencia = 0 WHERE idComision = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }

    // --- Helper: Obtener candidatos (Consejeros) ---
    public function getPosiblesAutoridades()
    {
        // Traemos usuarios que sean Consejeros (1), Presidentes (3) o Vicepresidentes (7)
        $sql = "SELECT idUsuario, CONCAT(pNombre, ' ', aPaterno) as nombreCompleto 
                FROM t_usuario 
                WHERE tipoUsuario_id IN (1, 3, 7) AND estado = 1 
                ORDER BY aPaterno ASC";
        $stmt = $this->conn->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Método antiguo (para compatibilidad si lo usaste en otro lado)
    public function listarTodas($soloVigentes = true) { return $this->getAll(); }
}