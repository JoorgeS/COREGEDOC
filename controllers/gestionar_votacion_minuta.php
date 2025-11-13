<?php
// /corevota/controllers/gestionar_votacion_minuta.php
// --- VERSIÓN CORREGIDA (sin PDF) ---

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Incluimos los controladores que necesitamos
require_once __DIR__ . '/../class/class.conectorDB.php';
require_once __DIR__ . '/VotacionController.php'; // Para habilitar/deshabilitar
require_once __DIR__ . '/VotoController.php';     // Para registrar voto ST

// Seguridad: Solo Secretarios Técnicos
if (!isset($_SESSION['idUsuario']) || $_SESSION['tipoUsuario_id'] != 2) { // 2 = ST
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Acceso no autorizado.']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? null;
$idMinuta = $_POST['idMinuta'] ?? $_GET['idMinuta'] ?? null;
$idUsuarioLogueado = $_SESSION['idUsuario'];

$db = new conectorDB();
$pdo = $db->getDatabase();

try {
    switch ($action) {
        // ==========================================================
        // --- 🚀 INICIO: BLOQUE CORREGIDO ---
        // ==========================================================
        case 'change_status':
            $idVotacion = $_POST['idVotacion'] ?? null;
            $nuevoEstado = $_POST['nuevoEstado'] ?? null; // 0 para cerrar, 1 para abrir

            if (!$idVotacion || $nuevoEstado === null || !is_numeric($nuevoEstado)) {
                throw new Exception('Faltan parámetros (idVotacion, nuevoEstado).');
            }

            $votacionCtrl = new VotacionController();

            // CORRECCIÓN: El método se llama "cambiarEstado", no "habilitar"
            $resultado = $votacionCtrl->cambiarEstado($idVotacion, $nuevoEstado);

            if ($resultado['status'] === 'success') {
                echo json_encode(['status' => 'success', 'message' => 'Estado de la votación actualizado.']);
            } else {
                throw new Exception($resultado['message'] ?? 'Error desconocido desde VotacionController.');
            }
            break;
        // ==========================================================
        // --- 🚀 FIN: BLOQUE CORREGIDO ---
        // ==========================================================

        case 'create':
            $idReunion = $_POST['idReunion'] ?? null;
            $idComision = $_POST['idComision'] ?? null;
            $nombreVotacion = $_POST['nombreVotacion'] ?? null;

            if (!$idMinuta || !$idReunion || !$idComision || !$nombreVotacion) {
                throw new Exception('Faltan parámetros para crear la votación.');
            }

            $votacionCtrl = new VotacionController();
            // Usamos la función storeVotacion() de tu VotacionController
            $resultado = $votacionCtrl->storeVotacion([
                'nombreVotacion' => $nombreVotacion,
                't_comision_idComision' => $idComision, // Ajustado al nombre esperado por el controller
                'habilitada' => 1 // Habilitada por defecto
            ]);

            // Esta lógica de SQL la mantenemos por si storeVotacion no guarda la relación
            if ($resultado['status'] === 'success') {
                try {
                    // Intentamos obtener el ID de la votación recién creada
                    $lastId = $pdo->lastInsertId();

                    if (empty($lastId) && !empty($resultado['id'])) {
                        $lastId = $resultado['id'];
                    } elseif (empty($lastId)) {
                        // Fallback si lastInsertId falla (ej. triggers)
                        $stmtLast = $pdo->prepare("SELECT idVotacion FROM t_votacion WHERE nombreVotacion = :nombre ORDER BY idVotacion DESC LIMIT 1");
                        $stmtLast->execute([':nombre' => $nombreVotacion]);
                        $lastId = $stmtLast->fetchColumn();
                    }

                    if ($lastId) {
                        // Actualizamos la votación con los IDs de minuta y reunión
                        $sql = "UPDATE t_votacion SET t_minuta_idMinuta = :idMinuta, t_reunion_idReunion = :idReunion 
                                WHERE idVotacion = :idVotacion";
                        $stmtUpdate = $pdo->prepare($sql);
                        $stmtUpdate->execute([
                            ':idMinuta' => $idMinuta,
                            ':idReunion' => $idReunion,
                            ':idVotacion' => $lastId
                        ]);
                    }
                } catch (Exception $e) {
                    // No es fatal, pero lo registramos
                    error_log("Error al vincular votación a minuta: " . $e->getMessage());
                }
            }

            echo json_encode($resultado);
            break;

        case 'list':
            if (!$idMinuta) {
                throw new Exception('ID de Minuta no proporcionado.');
            }

            $sqlReunion = $pdo->prepare("SELECT idReunion FROM t_reunion WHERE t_minuta_idMinuta = :idMinuta LIMIT 1");
            $sqlReunion->execute([':idMinuta' => $idMinuta]);
            $idReunion = $sqlReunion->fetchColumn();

            $sqlVotaciones = $pdo->prepare("
                SELECT v.idVotacion, v.nombreVotacion, v.habilitada, c.nombreComision 
                FROM t_votacion v
                LEFT JOIN t_comision c ON v.idComision = c.idComision
                WHERE v.t_minuta_idMinuta = :idMinuta OR v.t_reunion_idReunion = :idReunion
                ORDER BY v.idVotacion DESC
            ");
            $sqlVotaciones->execute([':idMinuta' => $idMinuta, ':idReunion' => $idReunion]);
            $votaciones = $sqlVotaciones->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['status' => 'success', 'data' => $votaciones]);
            break;

        case 'get_status':
            $idVotacion = $_POST['idVotacion'] ?? null;
            $asistentes_ids_json = $_POST['asistentes_ids'] ?? '[]';
            $asistentes_ids = json_decode($asistentes_ids_json);

            if (!$idVotacion || empty($asistentes_ids)) {
                throw new Exception('ID de Votación o lista de asistentes no proporcionada.');
            }

            $placeholders = implode(',', array_fill(0, count($asistentes_ids), '?'));

            // 1. Obtener nombres de asistentes
            $sqlAsistentes = $pdo->prepare("
                SELECT idUsuario, CONCAT(pNombre, ' ', aPaterno) as nombreCompleto 
                FROM t_usuario 
                WHERE idUsuario IN ($placeholders)
                ORDER BY aPaterno, pNombre
            ");
            $sqlAsistentes->execute($asistentes_ids);
            $asistentes = $sqlAsistentes->fetchAll(PDO::FETCH_ASSOC);

            // 2. Obtener votos ya emitidos
            $sqlVotos = $pdo->prepare("
                SELECT t_usuario_idUsuario, opcionVoto 
                FROM t_voto 
                WHERE t_votacion_idVotacion = :idVotacion AND t_usuario_idUsuario IN ($placeholders)
            ");
            $params = array_merge([":idVotacion" => $idVotacion], $asistentes_ids);
            $sqlVotos->execute($params);
            $votos = $sqlVotos->fetchAll(PDO::FETCH_KEY_PAIR); // Mapea idUsuario => opcionVoto

            echo json_encode(['status' => 'success', 'data' => ['asistentes' => $asistentes, 'votos' => $votos]]);
            break;

        case 'register_vote':
            $idVotacion = $_POST['idVotacion'] ?? null;
            $idUsuario = $_POST['idUsuario'] ?? null; // El ID del consejero que vota
            $voto = $_POST['voto'] ?? null;
            $idSecretario = $_POST['idSecretario'] ?? null; // El ST que registra

            if (!$idVotacion || !$idUsuario || !$voto || !$idSecretario) {
                throw new Exception('Faltan parámetros para registrar el voto.');
            }

            $votoCtrl = new VotoController();
            $resultado = $votoCtrl->registrarVotoVotacion(
                (int)$idVotacion,
                (int)$idUsuario,
                (string)$voto,
                (int)$idSecretario // ID del ST que registra
            );

            echo json_encode($resultado);
            break;

        default:
            throw new Exception('Acción no válida.');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
