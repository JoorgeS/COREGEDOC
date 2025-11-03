<?php
// controllers/obtener_feedback_json.php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . "/../class/class.conectorDB.php";

$idMinuta = $_GET['idMinuta'] ?? 0;
$idSecretario = $_SESSION['idUsuario'] ?? 0;

if (empty($idMinuta) || empty($idSecretario)) {
    echo json_encode(['status' => 'error', 'message' => 'ID de Minuta o Sesión inválida.']);
    exit;
}

try {
    $db = new conectorDB();
    $conexion = $db->getDatabase();

    // --- MODIFICADO ---
    // Ahora seleccionamos ambos campos: el JSON para bloquear y el TEXTO para mostrar
    $sql = "SELECT feedback_json, textoFeedback 
            FROM t_minuta_feedback 
            WHERE t_minuta_idMinuta = :idMinuta 
            AND resuelto = 0 -- (NUEVO) Solo traemos feedback no resuelto
            ORDER BY idFeedback DESC 
            LIMIT 1";
            
    $stmt = $conexion->prepare($sql);
    $stmt->execute([':idMinuta' => $idMinuta]);
    $feedback = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($feedback && !empty($feedback['feedback_json'])) {
        $feedbackData = json_decode($feedback['feedback_json'], true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
             throw new Exception('Error al decodificar el JSON del feedback.');
        }

        // Devolvemos ambos datos
        echo json_encode([
            'status' => 'success', 
            'data' => $feedbackData, // El JSON para bloquear campos
            'textoFeedback' => $feedback['textoFeedback'] // El texto para mostrar al ST
        ]);

    } else {
        // No es un error, simplemente no hay feedback
        echo json_encode(['status' => 'no_feedback']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>