<?php
require_once __DIR__ . '/../cfg/config.php';

class FetchData extends BaseConexion
{
    private $db;

    public function __construct()
    {
        $this->db = $this->conectar();
    }

    // 🟢 Listado de comisiones (sin cambios)
    public function getComisiones()
    {
        $sql = "SELECT idComision, nombreComision FROM t_comision ORDER BY nombreComision ASC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // 🔴 Listado de PRESIDENTES (Uso: selector Presidente Comisión)
    // Trae SOLO Presidentes Comisión (Tipo 3)
    public function getPresidentesSolo()
    {
        // Uso de CONCAT_WS para manejar campos NULL y evitar espacios sobrantes.
        $sql = "SELECT u.idUsuario, 
                       TRIM(CONCAT_WS(' ', u.pNombre, u.sNombre, u.aPaterno, u.aMaterno)) AS nombreCompleto 
                FROM t_usuario u
                WHERE u.tipoUsuario_id = 3 
                ORDER BY u.aPaterno ASC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // 🔵 Listado de TODOS LOS USUARIOS (Uso: selector de Asistencia)
    // Trae TODOS los usuarios, independientemente de su tipo.
    public function getAllUsuarios()
    {
        // Uso de CONCAT_WS para manejar campos NULL y evitar espacios sobrantes.
        $sql = "SELECT u.idUsuario, 
                       TRIM(CONCAT_WS(' ', u.pNombre, u.sNombre, u.aPaterno, u.aMaterno)) AS nombreCompleto 
                FROM t_usuario u
                ORDER BY u.aPaterno ASC";
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

        case 'presidentes':
            // Llama solo a los presidentes (Tipo 3)
            echo json_encode($fetch->getPresidentesSolo());
            break;

        case 'asistencia_all':
            // NUEVA ACCIÓN: Llama a todos los usuarios para la lista de asistencia
            echo json_encode($fetch->getAllUsuarios());
            break;

        default:
            echo json_encode([]);
            break;
    }
}
?>