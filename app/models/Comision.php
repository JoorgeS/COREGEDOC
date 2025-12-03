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

    // --- Listar todas (Usado por el Controller antiguo, opcional si usas solo API) ---
    public function getAll()
    {
        // Modificado para traer TAMBIÉN las inactivas, así puedes reactivarlas
        $sql = "SELECT c.*, 
                       CONCAT(up.pNombre, ' ', up.aPaterno) as nombrePresidente,
                       CONCAT(uv.pNombre, ' ', uv.aPaterno) as nombreVicepresidente
                FROM t_comision c
                LEFT JOIN t_usuario up ON c.t_usuario_idPresidente = up.idUsuario
                LEFT JOIN t_usuario uv ON c.t_usuario_idVicepresidente = uv.idUsuario
                ORDER BY c.nombreComision ASC"; // Quitamos el WHERE vigencia=1 para ver todo
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id)
    {
        $sql = "SELECT * FROM " . $this->table . " WHERE idComision = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

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

    // --- NUEVO: Alternar Estado (Habilitar/Deshabilitar) ---
    public function toggleVigencia($id, $estadoActual)
    {
        // Si viene 1 lo pasamos a 0, si viene 0 lo pasamos a 1
        $nuevoEstado = ($estadoActual == 1) ? 0 : 1;
        $sql = "UPDATE " . $this->table . " SET vigencia = :nuevo WHERE idComision = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':nuevo' => $nuevoEstado, ':id' => $id]);
    }

    public function getPosiblesAutoridades()
    {
        $sql = "SELECT idUsuario, CONCAT(pNombre, ' ', aPaterno) as nombreCompleto 
                FROM t_usuario 
                WHERE tipoUsuario_id IN (1, 3, 7) AND estado = 1 
                ORDER BY aPaterno ASC";
        $stmt = $this->conn->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function listarTodas() { return $this->getAll(); }
}