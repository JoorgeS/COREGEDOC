<?php
// controllers/VotoController.php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../cfg/config.php';
require_once __DIR__ . '/../class/class.conectorDB.php';

$idPropuesta = $_POST['idPropuesta'] ?? null;
$idUsuario = $_SESSION['idUsuario'] ?? null;
$opcionVoto = $_POST['opcionVoto'] ?? null; // 1: Aprueba, 2: Rechaza, 3: Abstiene

if (!$idPropuesta || !$idUsuario || !$opcionVoto) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Datos incompletos para registrar el voto.']);
    exit;
}

$conector = new conectorDB();
$db = $conector->getDatabase();

try {
    // ==================================================================
    // --- INICIO DE LA NUEVA LÓGICA (AJUSTE #2) ---
    // ==================================================================

    // 1. Obtener la Minuta asociada a la Propuesta
    $sqlMinutaId = "
        SELECT t.t_minuta_idMinuta
        FROM t_propuesta p
        JOIN t_acuerdo a ON p.t_acuerdo_idAcuerdo = a.idAcuerdo
        JOIN t_tema t ON a.t_tema_idTema = t.idTema
        WHERE p.idPropuesta = :idPropuesta
    ";
    $stmtMinutaId = $db->prepare($sqlMinutaId);
    $stmtMinutaId->execute([':idPropuesta' => $idPropuesta]);
    $minuta = $stmtMinutaId->fetch(PDO::FETCH_ASSOC);

    if (!$minuta || empty($minuta['t_minuta_idMinuta'])) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'No se pudo encontrar la reunión asociada a esta votación.']);
        exit;
    }

    $idMinuta = $minuta['t_minuta_idMinuta'];

    // 2. Validar que el usuario esté presente en esa Minuta
    $sqlCheckAsistencia = "
        SELECT COUNT(*) AS presente
        FROM t_asistencia
        WHERE t_minuta_idMinuta = :idMinuta AND t_usuario_idUsuario = :idUsuario
    ";
    $stmtCheckAsistencia = $db->prepare($sqlCheckAsistencia);
    $stmtCheckAsistencia->execute([
        ':idMinuta' => $idMinuta,
        ':idUsuario' => $idUsuario
    ]);
    $asistencia = $stmtCheckAsistencia->fetch(PDO::FETCH_ASSOC);

    // 3. Si no está presente, denegar el voto.
    if (!$asistencia || (int)$asistencia['presente'] === 0) {
        http_response_code(403); // Código "Forbidden"
        echo json_encode([
            'status' => 'error',
            'message' => 'Error: No puede votar si no ha registrado asistencia para esta reunión.'
        ]);
        exit;
    }

    // ==================================================================
    // --- FIN DE LA NUEVA LÓGICA (AJUSTE #2) ---
    // ==================================================================


    // --- LÓGICA DE VOTACIÓN EXISTENTE ---

    // 1. Validar que la propuesta exista (Aunque ya lo hicimos arriba, mantenemos por si acaso)
    $sql = "SELECT * FROM t_propuesta WHERE idPropuesta = :idPropuesta";
    $stmt = $db->prepare($sql);
    $stmt->execute([':idPropuesta' => $idPropuesta]);
    $propuesta = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$propuesta) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'La propuesta de votación no fue encontrada.']);
        exit;
    }

    // 2. Verificar si el usuario ya votó en esta propuesta (Lógica de reemplazo de voto)
    $sql = "SELECT idVoto FROM t_voto 
            WHERE t_usuario_idUsuario = :idUsuario 
            AND t_propuesta_idPropuesta = :idPropuesta";
    $stmt = $db->prepare($sql);
    $stmt->execute([':idUsuario' => $idUsuario, ':idPropuesta' => $idPropuesta]);
    $votoExistente = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($votoExistente) {
        // Actualizar voto existente
        $sql_update = "UPDATE t_voto 
                       SET opcionVoto = :opcionVoto, fechaVoto = NOW(), origenVoto = 'AUTOGESTION-MOD'
                       WHERE idVoto = :idVoto";
        $stmt_update = $db->prepare($sql_update);
        $stmt_update->execute([
            ':opcionVoto' => $opcionVoto,
            ':idVoto' => $votoExistente['idVoto']
        ]);
        echo json_encode(['status' => 'success', 'message' => 'Voto actualizado con éxito.']);
    
    } else {
        // Insertar nuevo voto
        $sql_insert = "INSERT INTO t_voto (t_usuario_idUsuario, t_propuesta_idPropuesta, opcionVoto, fechaVoto, origenVoto) 
                       VALUES (:idUsuario, :idPropuesta, :opcionVoto, NOW(), 'AUTOGESTION')";
        
        $stmt_insert = $db->prepare($sql_insert);
        $stmt_insert->execute([
            ':idUsuario' => $idUsuario,
            ':idPropuesta' => $idPropuesta,
            ':opcionVoto' => $opcionVoto
        ]);
        echo json_encode(['status' => 'success', 'message' => 'Voto registrado con éxito.']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Error en VotoController: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Error interno del servidor.', 'error' => $e->getMessage()]);
}
?>