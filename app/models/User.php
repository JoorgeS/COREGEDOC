<?php
namespace App\Models;
use Exception;   
use PDOException;

use App\Config\Database;
use PDO;

class User {
    private $conn;
    private $table = 't_usuario';

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    // ============================================================
    // 🔑 MÉTODO CRÍTICO PARA LOGIN (Este es el que faltaba)
    // ============================================================
    public function findByEmail($email) {
        $query = "SELECT * FROM " . $this->table . " WHERE correo = :email LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':email' => $email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // ============================================================
    // 🛠️ MÉTODOS PARA GESTIÓN DE USUARIOS (CRUD)
    // ============================================================

    // Listar todos (Activos)
    public function getAll() {
        $sql = "SELECT u.*, t.descTipoUsuario, p.nombrePartido 
                FROM t_usuario u
                LEFT JOIN t_tipousuario t ON u.tipoUsuario_id = t.idTipoUsuario
                LEFT JOIN t_partido p ON u.partido_id = p.idPartido
                WHERE u.estado = 1
                ORDER BY u.aPaterno ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Obtener uno por ID
    public function getById($id) {
        $sql = "SELECT * FROM " . $this->table . " WHERE idUsuario = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Crear Usuario
    public function create($data) {
        // Nota: t_partido_nombrePartido se inserta vacío por defecto o según tu lógica
        $sql = "INSERT INTO " . $this->table . " 
                (pNombre, sNombre, aPaterno, aMaterno, correo, contrasena, tipoUsuario_id, partido_id, provincia_id, estado, t_partido_nombrePartido) 
                VALUES (:pNombre, :sNombre, :aPaterno, :aMaterno, :correo, :contrasena, :rol, :partido, :provincia, 1, '')";
        
        $stmt = $this->conn->prepare($sql);
        
        // Encriptar contraseña
        $hash = password_hash($data['contrasena'], PASSWORD_DEFAULT);

        return $stmt->execute([
            ':pNombre' => $data['pNombre'],
            ':sNombre' => $data['sNombre'] ?? '',
            ':aPaterno'=> $data['aPaterno'],
            ':aMaterno'=> $data['aMaterno'] ?? '',
            ':correo'  => $data['correo'],
            ':contrasena' => $hash,
            ':rol'     => $data['tipoUsuario_id'],
            ':partido' => !empty($data['partido_id']) ? $data['partido_id'] : null,
            ':provincia' => !empty($data['provincia_id']) ? $data['provincia_id'] : 0
        ]);
    }

    // Actualizar Usuario
    public function update($id, $data) {
        // Solo actualizamos la contraseña si el usuario escribió una nueva
        $sqlPass = !empty($data['contrasena']) ? ", contrasena = :contrasena" : "";
        
        $sql = "UPDATE " . $this->table . " SET 
                    pNombre = :pNombre, 
                    sNombre = :sNombre, 
                    aPaterno = :aPaterno, 
                    aMaterno = :aMaterno, 
                    correo = :correo, 
                    tipoUsuario_id = :rol,
                    partido_id = :partido,
                    provincia_id = :provincia
                    $sqlPass
                WHERE idUsuario = :id";

        $stmt = $this->conn->prepare($sql);
        
        $params = [
            ':pNombre' => $data['pNombre'],
            ':sNombre' => $data['sNombre'] ?? '',
            ':aPaterno'=> $data['aPaterno'],
            ':aMaterno'=> $data['aMaterno'] ?? '',
            ':correo'  => $data['correo'],
            ':rol'     => $data['tipoUsuario_id'],
            ':partido' => !empty($data['partido_id']) ? $data['partido_id'] : null,
            ':provincia' => !empty($data['provincia_id']) ? $data['provincia_id'] : 0,
            ':id'      => $id
        ];

        if (!empty($data['contrasena'])) {
            $params[':contrasena'] = password_hash($data['contrasena'], PASSWORD_DEFAULT);
        }

        return $stmt->execute($params);
    }

    // Eliminar (Lógico)
    public function delete($id) {
        $sql = "UPDATE " . $this->table . " SET estado = 0 WHERE idUsuario = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }

    // --- Helpers para los Selects ---
    public function getRoles() {
        return $this->conn->query("SELECT * FROM t_tipousuario ORDER BY descTipoUsuario")->fetchAll(PDO::FETCH_ASSOC);
    }
    public function getPartidos() {
        return $this->conn->query("SELECT * FROM t_partido ORDER BY nombrePartido")->fetchAll(PDO::FETCH_ASSOC);
    }
    public function getProvincias() {
        return $this->conn->query("SELECT * FROM t_provincia ORDER BY nombreProvincia")->fetchAll(PDO::FETCH_ASSOC);
    }
}