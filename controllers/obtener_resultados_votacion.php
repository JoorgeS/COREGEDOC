<?php
// /corevota/controllers/obtener_resultados_votacion.php
header('Content-Type: application/json');
error_reporting(0); // No queremos warnings en nuestra respuesta JSON
ini_set('display_errors', 0);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../class/class.conectorDB.php';

// Seguridad: Solo usuarios logueados pueden ver el dashboard de resultados.
if (!isset($_SESSION['idUsuario'])) {
    http_response_code(403); // Forbidden
    echo json_encode(['status' => 'error', 'message' => 'Acceso no autorizado. Debe iniciar sesión.']);
    exit;
}

$idMinuta = $_GET['idMinuta'] ?? null;
// 💡 NUEVAS VARIABLES DE CONTROL
$idUsuarioLogueado = $_SESSION['idUsuario'];
$idTipoUsuario = $_SESSION['tipoUsuario_id'];
$esSecretarioTecnico = ($idTipoUsuario == 2); // 2 es ST

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
    $sqlVotaciones = $pdo->prepare("SELECT idVotacion, nombreVotacion, idComision, habilitada FROM t_votacion 
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

    // 3. OBTENER TOTAL DE CONSEJEROS ASISTENTES (para calcular Faltan Votar)
    $sqlAsistentes = $pdo->prepare("SELECT t_usuario_idUsuario FROM t_asistencia 
                                   WHERE t_minuta_idMinuta = :idMinuta AND t_usuario_idUsuario IN (SELECT idUsuario FROM t_usuario WHERE tipoUsuario_id = 1 OR tipoUsuario_id = 3)");
    $sqlAsistentes->execute([':idMinuta' => $idMinuta]);
    $asistentesIDs = $sqlAsistentes->fetchAll(PDO::FETCH_COLUMN, 0);
    $totalAsistentes = count($asistentesIDs);

    $resultadosFinales = [];

    // 4. OBTENER VOTOS Y CONTEO PARA CADA VOTACIÓN
    foreach ($votaciones as $votacion) {
        $idVotacion = $votacion['idVotacion'];
        // ... (dentro del foreach)
        $resultado = [
            'idVotacion' => $idVotacion,
            'nombreVotacion' => $votacion['nombreVotacion'],
            'habilitada' => (int)$votacion['habilitada'], // ⬅️ AÑADE ESTA LÍNEA
            'totalSi' => 0,
            'totalNo' => 0,
            'totalAbstencion' => 0,
            'votoPersonal' => null,
            'votos' => [],
        ];
        // ... (el resto de la función sigue igual)

        $sqlVotos = $pdo->prepare("
            SELECT v.opcionVoto, 
                   v.t_usuario_idUsuario,
                   CONCAT(u.pNombre, ' ', u.aPaterno) as nombreVotante
            FROM t_voto v
            JOIN t_usuario u ON v.t_usuario_idUsuario = u.idUsuario
            WHERE v.t_votacion_idVotacion = :idVotacion
        ");
        $sqlVotos->execute([':idVotacion' => $idVotacion]);
        $votosData = $sqlVotos->fetchAll(PDO::FETCH_ASSOC);

        $votosEmitidos = 0;

        foreach ($votosData as $voto) {
            $votosEmitidos++;
            $opcion = strtoupper($voto['opcionVoto']);

            // 4a. Cargar totales
            if ($opcion === 'SI') $resultado['totalSi']++;
            elseif ($opcion === 'NO') $resultado['totalNo']++;
            elseif ($opcion === 'ABSTENCION') $resultado['totalAbstencion']++;

            // 4b. Cargar voto personal
            if ($voto['t_usuario_idUsuario'] == $idUsuarioLogueado) {
                $resultado['votoPersonal'] = $opcion;
            }

            // 4c. Cargar detalle de nombres (SOLO si es ST)
            if ($esSecretarioTecnico) {
                $resultado['votos'][] = [
                    'nombreVotante' => $voto['nombreVotante'],
                    'opcionVoto' => $opcion,
                    't_usuario_idUsuario' => (int)$voto['t_usuario_idUsuario']
                ];
            }
        }

        // 4d. Calcular faltan votar
        $resultado['faltanVotar'] = $totalAsistentes - $votosEmitidos;
        $resultadosFinales[] = $resultado;
    }

    echo json_encode(['status' => 'success', 'data' => $resultadosFinales, 'esSecretarioTecnico' => $esSecretarioTecnico]);
} catch (Exception $e) {
    http_response_code(500); // Internal Server Error
    error_log("Error en obtener_resultados_votacion.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Error de base de datos.']);
}
