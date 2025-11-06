<?php
// /corevota/controllers/obtener_asistencia_actual.php
header('Content-Type: application/json');
error_reporting(0);
ini_set('display_errors', 0);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../class/class.conectorDB.php';

// Seguridad: Solo usuarios logueados
if (!isset($_SESSION['idUsuario'])) {
    http_response_code(403); // Forbidden
    echo json_encode(['status' => 'error', 'message' => 'Acceso no autorizado.']);
    exit;
}

$idMinuta = $_GET['idMinuta'] ?? null;

if (!$idMinuta) {
    http_response_code(400); // Bad Request
    echo json_encode(['status' => 'error', 'message' => 'ID de Minuta no proporcionado.']);
    exit;
}

try {
    $db = new conectorDB();
    $pdo = $db->getDatabase();

    // Consultamos la tabla de asistencia y devolvemos solo los IDs de usuario
    $sql_asistencia = "SELECT t_usuario_idUsuario FROM t_asistencia WHERE t_minuta_idMinuta = :idMinuta";
    $stmt_asistencia = $pdo->prepare($sql_asistencia);
    $stmt_asistencia->execute([':idMinuta' => $idMinuta]);
    
    // Usamos FETCH_COLUMN para obtener un array simple de IDs [15, 37, 40]
    $asistencia_ids = $stmt_asistencia->fetchAll(PDO::FETCH_COLUMN, 0);

    echo json_encode(['status' => 'success', 'data' => $asistencia_ids]);

} catch (Exception $e) {
    http_response_code(500); // Internal Server Error
    error_log("Error en obtener_asistencia_actual.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Error de base de datos.']);
}
?>