<?php
// /coregedoc/controllers/verificar_votacion_activa.php
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Seguridad: Solo usuarios logueados
if (!isset($_SESSION['idUsuario'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Acceso no autorizado.']);
    exit;
}

require_once __DIR__ . '/../class/class.conectorDB.php';

$votacionActiva = false;

try {
    $db = new conectorDB();
    $pdo = $db->getDatabase();

    // Lógica simple para ver si hay *alguna* votación habilitada
    // (Basado en la lógica de voto_autogestion.php)
    $sqlCheck = "SELECT idVotacion FROM t_votacion WHERE habilitada = 1 LIMIT 1";
    $stmtCheck = $pdo->query($sqlCheck);
    
    if ($stmtCheck && $stmtCheck->fetchColumn() > 0) {
        $votacionActiva = true;
    }
    
    echo json_encode(['status' => 'success', 'votacionActiva' => $votacionActiva]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>