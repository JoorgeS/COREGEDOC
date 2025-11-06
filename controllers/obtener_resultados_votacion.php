<?php
// /corevota/controllers/obtener_resultados_votacion.php
header('Content-Type: application/json');
error_reporting(0); // No queremos warnings en nuestra respuesta JSON
ini_set('display_errors', 0);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../class/class.conectorDB.php';

// Seguridad: Solo usuarios logueados (Secretarios o Admin) pueden ver esto
if (!isset($_SESSION['idUsuario']) || ($_SESSION['tipoUsuario_id'] != 2 && $_SESSION['tipoUsuario_id'] != 6)) {
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

    // 1. OBTENER IDREUNION (para buscar votaciones asociadas a la reunión)
    $sqlReunion = $pdo->prepare("SELECT idReunion FROM t_reunion WHERE t_minuta_idMinuta = :idMinuta LIMIT 1");
    $sqlReunion->execute([':idMinuta' => $idMinuta]);
    $reunion = $sqlReunion->fetch(PDO::FETCH_ASSOC);
    $idReunion = $reunion ? $reunion['idReunion'] : null;

    // 2. OBTENER VOTACIONES (de la minuta O de la reunión)
    $sqlVotaciones = $pdo->prepare("SELECT * FROM t_votacion 
                                   WHERE t_minuta_idMinuta = :idMinuta 
                                   OR t_reunion_idReunion = :idReunion
                                   ORDER BY idVotacion ASC");
    $sqlVotaciones->execute([
        ':idMinuta' => $idMinuta,
        ':idReunion' => $idReunion
    ]);
    $votaciones = $sqlVotaciones->fetchAll(PDO::FETCH_ASSOC);

    if (empty($votaciones)) {
        echo json_encode(['status' => 'success', 'data' => []]);
        exit;
    }

    // 3. OBTENER LOS VOTOS PARA CADA VOTACIÓN
    // (Usamos los nombres correctos de tu BBDD: t_votacion_idVotacion, t_usuario_idUsuario)
    $sqlVotos = $pdo->prepare("
         SELECT v.opcionVoto, CONCAT(u.pNombre, ' ', u.aPaterno) as nombreVotante
         FROM t_voto v
         JOIN t_usuario u ON v.t_usuario_idUsuario = u.idUsuario
         WHERE v.t_votacion_idVotacion = :idVotacion
         ORDER BY nombreVotante ASC
    ");

    foreach ($votaciones as $i => $votacion) {
        $sqlVotos->execute([':idVotacion' => $votacion['idVotacion']]);
        $votaciones[$i]['votos'] = $sqlVotos->fetchAll(PDO::FETCH_ASSOC);
    }

    // 4. DEVOLVER TODO
    echo json_encode(['status' => 'success', 'data' => $votaciones]);
} catch (Exception $e) {
    http_response_code(500); // Internal Server Error
    error_log("Error en obtener_resultados_votacion.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Error de base de datos: ' . $e->getMessage()]);
}
