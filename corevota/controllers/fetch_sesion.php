<?php
require_once __DIR__ . '/../cfg/config.php';

class FetchSesion extends BaseConexion {
    private $db;

    public function __construct() {
        $this->db = $this->conectar();
    }

    public function getNumeroSesion() {
        $sql = "SELECT MAX(numeroSesion) AS maxNum FROM t_sesion";
        $stmt = $this->db->query($sql);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return ($row && $row['maxNum']) ? $row['maxNum'] + 1 : 1;
    }
}

$fetch = new FetchSesion();
echo json_encode(["numeroSesion" => $fetch->getNumeroSesion()]);
?>
