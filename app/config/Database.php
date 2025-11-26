<?php

namespace App\Config;

use PDO;
use PDOException;
use Exception;

class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $port;
    public $conn;

    public function __construct() {
        $this->conn = null;
        $ini_file = __DIR__ . '/database.ini';

        if (!file_exists($ini_file)) {
            throw new Exception("Error Crítico: No se encontró el archivo de configuración de base de datos en: " . $ini_file);
        }

        $ajustes = parse_ini_file($ini_file, true);
        
        if (!$ajustes) {
            throw new Exception("Error Crítico: No se pudo leer el archivo de configuración.");
        }

        $this->host = $ajustes["database"]["host"];
        $this->db_name = $ajustes["database"]["schema"];
        $this->username = $ajustes['database']['username'];
        $this->password = $ajustes['database']['password'];
        $this->port = $ajustes["database"]["port"];
    }

    public function getConnection() {
        try {
            $dsn = "mysql:host=" . $this->host . ";port=" . $this->port . ";dbname=" . $this->db_name . ";charset=utf8mb4";
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ];

            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
            
        } catch(PDOException $e) {
            // En producción, es mejor registrar esto en un log y no mostrarlo en pantalla
            die("Error de conexión a la base de datos: " . $e->getMessage());
        }

        return $this->conn;
    }
}