<?php

namespace App\Models;

use App\Config\Database;
use PDO;

class User {
    private $conn;
    private $table = 't_usuario'; // Nombre de tu tabla en la BD

    public function __construct() {
        // Instanciamos la base de datos y obtenemos la conexión
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    /**
     * Busca un usuario por su correo electrónico
     * @param string $email
     * @return mixed Array asociativo con datos del usuario o false si no existe
     */
    public function findByEmail($email) {
        // Consulta optimizada para traer solo los datos necesarios
        $query = "SELECT idUsuario, correo, contrasena, pNombre, aPaterno, tipoUsuario_id 
                  FROM " . $this->table . " 
                  WHERE correo = :email 
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);
        
        // Limpieza básica y vinculación de parámetros
        $email = htmlspecialchars(strip_tags($email));
        $stmt->bindParam(':email', $email);

        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}