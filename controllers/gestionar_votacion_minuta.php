<?php
// /controllers/gestionar_votacion_minuta.php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Dependencias
require_once __DIR__ . '/../class/class.conectorDB.php';
require_once __DIR__ . '/VotacionController.php'; // Para crear
require_once __DIR__ . '/VotoController.php';     // Para registrar
require_once __DIR__ . '/../cfg/config.php';       // Para BaseConexion

class VotacionMinutaManager extends BaseConexion {
    private $pdo;

    public function __construct() {
        $this->pdo = $this->conectar();
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    // Acción 1: Listar votaciones para la minuta
    public function listVotaciones($idMinuta) {
        $sql = "SELECT v.idVotacion, v.nombreVotacion, v.habilitada, c.nombreComision
                FROM t_votacion v
                JOIN t_comision c ON v.idComision = c.idComision
                WHERE v.t_minuta_idMinuta = :idMinuta
                ORDER BY v.idVotacion ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':idMinuta' => $idMinuta]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Acción 2: Obtener estado de votos (para el modal del secretario)
    public function getVotacionStatus($idVotacion, $asistentes_ids) {
        if (empty($asistentes_ids)) {
            return ['asistentes' => [], 'votos' => []];
        }

        // 1. Nombres de los asistentes
        $placeholders = implode(',', array_fill(0, count($asistentes_ids), '?'));
        $sqlAsistentes = "SELECT idUsuario, CONCAT(pNombre, ' ', aPaterno) as nombreCompleto 
                          FROM t_usuario 
                          WHERE idUsuario IN ($placeholders)
                          ORDER BY aPaterno, pNombre";
        $stmtA = $this->pdo->prepare($sqlAsistentes);
        $stmtA->execute($asistentes_ids);
        $asistentes = $stmtA->fetchAll(PDO::FETCH_ASSOC);

        // 2. Votos ya registrados
        $sqlVotos = "SELECT t_usuario_idUsuario, opcionVoto 
                     FROM t_voto 
                     WHERE t_votacion_idVotacion = :idVotacion";
        $stmtV = $this->pdo->prepare($sqlVotos);
        $stmtV->execute([':idVotacion' => $idVotacion]);
        // Indexar por idUsuario para fácil acceso en JS
        $votos = $stmtV->fetchAll(PDO::FETCH_KEY_PAIR); 

        return ['asistentes' => $asistentes, 'votos' => $votos];
    }
}


// --- ENRUTADOR DE ACCIONES ---
$action = $_POST['action'] ?? $_GET['action'] ?? null;
$idUsuarioLogueado = $_SESSION['idUsuario'] ?? null;

if (!$idUsuarioLogueado) {
    echo json_encode(['status' => 'error', 'message' => 'Sesión expirada.']);
    exit;
}

try {
    $manager = new VotacionMinutaManager();

    switch ($action) {
        // JS: guardarNuevaVotacion()
        case 'create':
            $votacionCtrl = new VotacionController();
            $data = [
                'nombreVotacion' => $_POST['nombreVotacion'] ?? '',
                't_comision_idComision' => $_POST['idComision'] ?? 0,
                'habilitada' => 1 // Habilitada por defecto
            ];
            $response = $votacionCtrl->storeVotacion($data); // Crea en t_votacion

            if ($response['status'] === 'success') {
                // Ahora, vinculamos a la minuta y reunión
                $db = new conectorDB();
                $pdo = $db->getDatabase();
                $idVotacionCreada = $pdo->lastInsertId();
                
                $sql_link = "UPDATE t_votacion SET 
                                t_minuta_idMinuta = :idMinuta, 
                                t_reunion_idReunion = :idReunion 
                             WHERE idVotacion = :idVotacion";
                $stmt_link = $pdo->prepare($sql_link);
                $stmt_link->execute([
                    ':idMinuta' => $_POST['idMinuta'],
                    ':idReunion' => $_POST['idReunion'],
                    ':idVotacion' => $idVotacionCreada
                ]);
                echo json_encode(['status' => 'success', 'message' => 'Votación creada y vinculada.']);
            } else {
                echo json_encode($response);
            }
            break;

        // JS: cargarVotacionesDeLaMinuta()
        case 'list':
            $idMinuta = $_GET['idMinuta'] ?? 0;
            $votaciones = $manager->listVotaciones($idMinuta);
            echo json_encode(['status' => 'success', 'data' => $votaciones]);
            break;

        // JS: abrirModalVoto()
        case 'get_status':
            $idVotacion = $_POST['idVotacion'] ?? 0;
            $asistentes_json = $_POST['asistentes_ids'] ?? '[]';
            $asistentes_ids = json_decode($asistentes_json, true);
            $data = $manager->getVotacionStatus($idVotacion, $asistentes_ids);
            echo json_encode(['status' => 'success', 'data' => $data]);
            break;

        // JS: registrarVotoSecretario()
        case 'register_vote':
            $votoCtrl = new VotoController();
            $response = $votoCtrl->registrarVoto(
                $_POST['idVotacion'] ?? 0,
                $_POST['idUsuario'] ?? 0,          // ID del asistente
                $_POST['opcionVoto'] ?? '',
                $_POST['idUsuarioRegistra'] ?? null // ID del secretario
            );
            echo json_encode($response);
            break;

        default:
            echo json_encode(['status' => 'error', 'message' => 'Acción no válida.']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error fatal del controlador: ' . $e->getMessage()]);
}