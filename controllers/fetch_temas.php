<?php
// -----------------------------------------------------------------------
// CONFIGURACIÓN DE ERRORES: Previene que los Notices/Warnings rompan el JSON
// -----------------------------------------------------------------------
// Esto evita que PHP imprima HTML (como <br /> <b>Notice</b>...) antes del JSON.
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../cfg/config.php';

// Asegúrate de que no hay espacios en blanco o nuevas líneas ANTES de esta etiqueta.

class FetchTemas extends BaseConexion {
    private $db;

    public function __construct() {
        // Asegúrate de que la conexión funcione correctamente o atrape el error
        $this->db = $this->conectar();
        if (!$this->db) {
            // Si la conexión falla, devolvemos un array vacío como JSON
            http_response_code(500);
            echo json_encode(["error" => "Fallo de conexión a la base de datos."]);
            exit;
        }
    }

    public function obtenerTemas() {
        // Tu consulta SQL es correcta para traer los datos combinados de tema y acuerdo
        $sql = "SELECT 
                    t.idTema,
                    t.nombreTema,
                    t.objetivo,
                    t.compromiso,
                    t.observacion,
                    a.descAcuerdo
                FROM t_tema t
                LEFT JOIN t_acuerdo a ON a.t_tema_idTema = t.idTema
                ORDER BY t.idTema ASC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// -----------------------------------------------------------------------
// CONTROLADOR DE EJECUCIÓN
// -----------------------------------------------------------------------
header('Content-Type: application/json');

try {
    $fetch = new FetchTemas();
    echo json_encode($fetch->obtenerTemas());
} catch (Exception $e) {
    // Captura cualquier excepción de la clase (como errores de PDO)
    http_response_code(500);
    echo json_encode(["error" => "Error al procesar la solicitud: " . $e->getMessage()]);
}

// Asegúrate de que no hay espacios en blanco o nuevas líneas DESPUÉS de esta etiqueta.
?>