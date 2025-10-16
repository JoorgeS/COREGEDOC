<?php
require_once __DIR__ . '/../cfg/config.php';

class FetchData extends BaseConexion
{
    private $db;

    public function __construct()
    {
        $this->db = $this->conectar();
    }

    // 🟢 Listado de comisiones
    public function getComisiones()
    {
        $sql = "SELECT idComision, nombreComision FROM t_comision ORDER BY nombreComision ASC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // 🟢 Listado de presidentes de comisión (consejeros)
    public function getConsejeros()
    {
        $sql = "SELECT u.idUsuario, CONCAT(u.pNombre, ' ', u.aPaterno) AS nombreCompleto,
                    c.nombreProvincia, p.nombrePartido
                FROM t_usuario u
                LEFT JOIN t_provincia c ON u.comuna_id = c.idProvincia
                LEFT JOIN t_partido p ON u.partido_id = p.idPartido
                WHERE u.tipoUsuario_id = 1
                ORDER BY u.pNombre ASC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// --- Controlador AJAX ---
if (isset($_GET['action'])) {
    $fetch = new FetchData();

    switch ($_GET['action']) {
        case 'comisiones':
            echo json_encode($fetch->getComisiones());
            break;

        case 'consejeros':
            echo json_encode($fetch->getConsejeros());
            break;

        default:
            echo json_encode([]);
            break;
    }
}
?>

