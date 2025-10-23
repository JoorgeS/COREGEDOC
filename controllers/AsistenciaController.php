<?php
// controllers/AsistenciaController.php
require_once __DIR__ . '/../class/class.conectorDB.php';
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$idUsuarioLogueado = $_SESSION['idUsuario'] ?? null;
$tipoUsuario = $_SESSION['tipoUsuario_id'] ?? null; // Asumiendo que guardas tipoUsuario_id en sesión
$response = ['status' => 'error', 'message' => 'Error desconocido.'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$idUsuarioLogueado) {
    echo json_encode(['status' => 'error', 'message' => 'Acceso no válido.']);
    exit;
}

// Solo Consejeros Regionales (idTipoUsuario = 1) pueden autogestionar
if ($tipoUsuario != 1) {
     echo json_encode(['status' => 'error', 'message' => 'Acción no permitida para este tipo de usuario.']);
     exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$idMinuta = $data['idMinuta'] ?? null;

if (!$idMinuta) {
    echo json_encode(['status' => 'error', 'message' => 'Falta ID de la minuta/reunión.']);
    exit;
}

try {
    $db = new conectorDB();
    $pdo = $db->getDatabase();

    // Revisar si ya existe
    $sql_check = "SELECT idAsistencia FROM t_asistencia WHERE t_minuta_idMinuta = :idMinuta AND t_usuario_idUsuario = :idUsuario";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute([':idMinuta' => $idMinuta, ':idUsuario' => $idUsuarioLogueado]);
    $existe = $stmt_check->fetch();

    if ($existe) {
        // Si ya existe, solo actualizamos la hora (o no hacemos nada, pero damos éxito)
        // Optamos por actualizar la hora para registrar el último "check-in"
        $sql_update = "UPDATE t_asistencia SET fechaRegistroAsistencia = NOW() 
                       WHERE idAsistencia = :idAsistencia";
        $stmt_update = $pdo->prepare($sql_update);
        $stmt_update->execute([':idAsistencia' => $existe['idAsistencia']]);
        $response = ['status' => 'success', 'message' => 'Asistencia re-confirmada.'];

    } else {
        // Si no existe, la insertamos
        $sql_insert = "INSERT INTO t_asistencia (t_minuta_idMinuta, t_usuario_idUsuario, t_tipoReunion_idTipoReunion, fechaRegistroAsistencia)
                       VALUES (:idMinuta, :idUsuario, 1, NOW())"; // Asumimos t_tipoReunion_idTipoReunion = 1
        $stmt_insert = $pdo->prepare($sql_insert);
        $stmt_insert->execute([':idMinuta' => $idMinuta, ':idUsuario' => $idUsuarioLogueado]);
        $response = ['status' => 'success', 'message' => 'Asistencia registrada con éxito.'];
    }

} catch (Exception $e) {
    error_log("Error en AsistenciaController: " . $e->getMessage());
    $response = ['status' => 'error', 'message' => 'Error de base de datos.'];
}

echo json_encode($response);
exit;
?>