<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/app/config/Constants.php';

use App\Controllers\AuthController;
use App\Controllers\HomeController;
use App\Controllers\MinutaController;
use App\Controllers\VotacionController;
use App\Controllers\AdjuntoController;
use App\Controllers\AsistenciaController;
use App\Controllers\ReunionController;
use App\Controllers\UserController;
use App\Controllers\ComisionController;


session_start();

$action = $_GET['action'] ?? $_POST['action'] ?? 'login';

$authController = new AuthController();
$homeController = new HomeController();
$minutaController = new MinutaController();
$votacionController = new VotacionController();
$adjuntoController = new AdjuntoController();
$asistenciaController = new AsistenciaController();
$reunionController = new ReunionController();
$userController = new UserController();
$comisionController = new ComisionController();

try {
    switch ($action) {
        case 'login':
            $authController->login();
            break;
        case 'logout':
            $authController->logout();
            break;
        case 'home':
            $homeController->index();
            break;

        case 'minutas_dashboard':
            $minutaController->dashboard();
            break;

        case 'minutas_pendientes':
            $minutaController->pendientes();
            break;

        case 'minutas_aprobadas':
            $minutaController->aprobadas();

        case 'minuta_gestionar':
            $minutaController->gestionar();
            break;

        case 'api_guardar_asistencia':
            $minutaController->apiGuardarAsistencia();
            break;

        case 'api_guardar_borrador':
            $minutaController->apiGuardarBorrador();
            break;

        case 'api_enviar_aprobacion':
            $minutaController->apiEnviarAprobacion();
            break;

        case 'api_firmar_minuta':
            $minutaController->apiFirmarMinuta();
            break;

        case 'api_enviar_feedback':
            $minutaController->apiEnviarFeedback();
            break;

        case 'api_votacion_crear':
            $votacionController->apiCrear();
            break;
        case 'api_votacion_listar':
            $votacionController->apiListar();
            break;
        case 'api_votacion_estado':
            $votacionController->apiCambiarEstado();
            break;
        case 'api_votacion_resultados':
            $votacionController->apiResultados();
            break;

        case 'voto_autogestion':
            $votacionController->sala();
            break;

        case 'api_voto_check':
            $votacionController->apiCheckActive();
            break;

        case 'api_voto_emitir':
            $votacionController->apiEmitirVoto();
            break;

        case 'api_adjunto_listar':
            $adjuntoController->apiListar();
            break;
        case 'api_adjunto_subir':
            $adjuntoController->apiSubir();
            break;
        case 'api_adjunto_link':
            $adjuntoController->apiAgregarLink();
            break;
        case 'api_adjunto_eliminar':
            $adjuntoController->apiEliminar();
            break;
        case 'asistencia_sala':
            $asistenciaController->sala();
            break;
        case 'api_asistencia_check':
            $asistenciaController->apiCheck();
            break;
        case 'api_asistencia_marcar':
            $asistenciaController->apiMarcar();
            break;

        case 'reuniones_dashboard': // Listado
            $reunionController->index();
            break;

        case 'reunion_form': // Formulario de creación
            $reunionController->create();
            break;

        case 'store_reunion': // Acción de guardar (POST)
            $reunionController->store();
            break;

        case 'reunion_editar': // Formulario de edición
            $reunionController->edit();
            break;

        case 'update_reunion': // Acción de actualizar (POST)
            $reunionController->update();
            break;

        case 'reunion_eliminar': // Acción de borrar
            $reunionController->delete();
            break;

        case 'reunion_iniciar_minuta': // Acción mágica
            $reunionController->iniciarMinuta();
            break;

        case 'minuta_ver_historial': // <--- ESTA ES LA RUTA
            $minutaController->verHistorial();
            break;


        case 'reunion_calendario':
            $reunionController->calendario();
            break;
        case 'usuarios_dashboard':
            $userController->index();
            break;
        case 'usuario_crear':
            $userController->form(); // Formulario vacío
            break;
        case 'usuario_editar':
            $userController->form(); // Formulario con datos (detecta $_GET['id'])
            break;
        case 'usuario_guardar':
            $userController->store();
            break;
        case 'usuario_eliminar':
            $userController->delete();
            break;
        case 'comisiones_dashboard':
            $comisionController->index();
            break;
        case 'comision_crear':
            $comisionController->form();
            break;
        case 'comision_editar':
            $comisionController->form();
            break;
        case 'comision_guardar':
            $comisionController->store();
            break;
        case 'comision_eliminar':
            $comisionController->delete();
            break;

        case 'seguimiento_general':
            $minutaController->seguimientoGeneral();
            break;

        case 'api_validar_asistencia':
            $minutaController->apiValidarAsistencia();
            break;


        default:
            header('Location: index.php?action=login');
            exit();
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
