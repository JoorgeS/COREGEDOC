<?php
// controllers/AsistenciaController.php
require_once __DIR__ . '/../class/class.conectorDB.php';
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$idUsuarioLogueado = $_SESSION['idUsuario'] ?? null;
$idtipoUsuario = $_SESSION['tipoUsuario_id'] ?? null;

$response = ['status' => 'error', 'message' => 'Error desconocido.'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$idUsuarioLogueado) {
    echo json_encode(['status' => 'error', 'message' => 'Acceso no válido.']);
    exit;
}

// Permite que Consejeros (1) y Presidentes (3) registren.
if (!($idtipoUsuario == 1 || $idtipoUsuario == 3)) {
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

    // --- NUEVO: Verificar el plazo de 30 minutos ---
    // Consulta para obtener la hora de inicio de la reunión (t_reunion) a partir del idMinuta
    $sql_time = "SELECT fechaInicioReunion FROM t_reunion WHERE t_minuta_idMinuta = :idMinuta";
    $stmt_time = $pdo->prepare($sql_time);
    $stmt_time->execute([':idMinuta' => $idMinuta]);
    $reunionTime = $stmt_time->fetch(PDO::FETCH_ASSOC);

    if (!$reunionTime) {
        echo json_encode(['status' => 'error', 'message' => 'Reunión no encontrada.']);
        exit;
    }

    $inicioReunion = new DateTime($reunionTime['fechaInicioReunion']);
    // Calculamos el límite añadiendo 30 minutos a la hora de inicio.
    $limiteRegistro = (clone $inicioReunion)->modify('+30 minutes');
    $ahora = new DateTime();

    if ($ahora > $limiteRegistro) {
        $limiteFormat = $limiteRegistro->format('H:i');
        echo json_encode([
            'status' => 'error',
            'message' => "El plazo de registro de asistencia ha expirado. El límite era a las {$limiteFormat} hrs."
        ]);
        exit;
    }
    // --- FIN NUEVO: Verificar el plazo de 30 minutos ---

    // Revisar si ya existe
    $sql_check = "SELECT idAsistencia FROM t_asistencia WHERE t_minuta_idMinuta = :idMinuta AND t_usuario_idUsuario = :idUsuario";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute([':idMinuta' => $idMinuta, ':idUsuario' => $idUsuarioLogueado]);
    $existe = $stmt_check->fetch();

    if ($existe) {
        // Si ya existe, actualizamos la hora y AÑADIMOS el campo de trazabilidad
        $sql_update = "UPDATE t_asistencia 
                       SET fechaRegistroAsistencia = NOW(), origenAsistencia = 'AUTOREGISTRO' 
                       WHERE idAsistencia = :idAsistencia";
        $stmt_update = $pdo->prepare($sql_update);
        $stmt_update->execute([':idAsistencia' => $existe['idAsistencia']]);
        $response = ['status' => 'success', 'message' => 'Asistencia re-confirmada.'];
    } else {
        // Si no existe, la insertamos y AÑADIMOS el campo de trazabilidad
        $sql_insert = "INSERT INTO t_asistencia (t_minuta_idMinuta, t_usuario_idUsuario, t_tipoReunion_idTipoReunion, fechaRegistroAsistencia, origenAsistencia)
                       VALUES (:idMinuta, :idUsuario, 1, NOW(), 'AUTOREGISTRO')";
        $stmt_insert = $pdo->prepare($sql_insert);
        $stmt_insert->execute([':idMinuta' => $idMinuta, ':idUsuario' => $idUsuarioLogueado]);
        $response = ['status' => 'success', 'message' => 'Asistencia registrada con éxito.'];
    }
} catch (Exception $e) {
    error_log("Error en AsistenciaController: " . $e->getMessage());
    $response = ['status' => 'error', 'message' => 'Error de base de datos: ' . $e->getMessage()];
}

echo json_encode($response);
exit;
