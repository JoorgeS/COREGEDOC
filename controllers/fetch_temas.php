<?php
require_once __DIR__ . '/../cfg/config.php';

class FetchTemas extends BaseConexion {
    private $db;

    public function __construct() {
        $this->db = $this->conectar();
    }

    public function obtenerTemas() {
        $sql = "SELECT 
                    t.idTema,
                    t.nombreTema,
                    t.objetivo,
                    t.compromiso,
                    t.observacion,
                    a.descAcuerdo
                FROM t_tema t
                LEFT JOIN t_acuerdo a ON a.idTema = t.idTema
                ORDER BY t.idTema ASC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

$fetch = new FetchTemas();
echo json_encode($fetch->obtenerTemas());
?>
