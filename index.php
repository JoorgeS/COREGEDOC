<?php

// 1. Zona Horaria: Santiago de Chile (UTC-3 / UTC-4 según época)
date_default_timezone_set('America/Santiago');

// 2. Idioma local (Opcional pero recomendado): Para que strftime o fechas salgan en español
setlocale(LC_TIME, 'es_CL.UTF-8', 'es_CL', 'esp');
ob_start();
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
use App\Controllers\PublicController;



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

        case 'reportes_index':
            $reporteController->index();
            break;

        case 'reporte_generar':
            $reporteController->generarPdf();
            break;

        case 'minutas_pendientes':
            $minutaController->pendientes();
            break;

        case 'minutas_aprobadas':
            $controller = new App\Controllers\MinutaController();
            $controller->aprobadas();
            break;

        case 'api_filtrar_aprobadas': // <--- AGREGAR ESTO
            $controller = new App\Controllers\MinutaController();
            $controller->apiFiltrarAprobadas();
            break;

        case 'api_filtrar_pendientes':
            $controller = new App\Controllers\MinutaController();
            $controller->apiFiltrarPendientes();
            break;

        case 'api_ver_adjuntos_minuta': // <--- Y ESTO
            $controller = new App\Controllers\MinutaController();
            $controller->apiVerAdjuntosMinuta();
            break;

        case 'minuta_gestionar':
            $minutaController->gestionar();
            break;

        case 'api_guardar_asistencia':
            $minutaController->apiGuardarAsistencia();
            break;

        case 'api_enviar_aprobacion':
            $minutaController->apiEnviarAprobacion();
            break;

        case 'api_filtrar_reuniones': // <--- AGREGAR ESTE CASE
            $controller = new App\Controllers\ReunionController();
            $controller->apiFiltrarReuniones();
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




        case 'store_reunion':
            $reunionController->store();
            break;



        case 'update_reunion':
            $reunionController->update();
            break;

        case 'reunion_eliminar':
            $reunionController->delete();
            break;

        case 'reunion_guardar':
            $reunionController->store();
            break;

        case 'reunion_iniciar_minuta':
            $reunionController->iniciarMinuta();
            break;

        case 'minuta_ver_historial':
            $minutaController->verHistorial();
            break;

        case 'reunion_calendario':
            $reunionController->calendario();
            break;
        case 'usuarios_dashboard':
            $userController->index();
            break;
        case 'usuario_crear':
            $userController->form();
            break;
        case 'usuario_editar':
            $userController->form();
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






        case 'seguimiento_general':
            $minutaController->seguimientoGeneral();
            break;

        case 'api_validar_asistencia':
            $minutaController->apiValidarAsistencia();
            break;

        case 'validar':
            $controller = new PublicController();
            $controller->validarDocumento();
            break;

        case 'ver_archivo_adjunto': // <--- NUEVA RUTA
            $controller = new App\Controllers\MinutaController();
            $controller->verArchivoAdjunto();
            break;

        // 2. ASISTENCIA (Tiempo Real)
        case 'api_get_asistencia':
            $controller = new App\Controllers\MinutaController();
            $controller->apiGetAsistencia();
            break;

        case 'api_alternar_asistencia':
            $controller = new App\Controllers\MinutaController();
            $controller->apiAlternarAsistencia();
            break;

        // 3. VOTACIONES
        case 'api_crear_votacion':
            $controller = new App\Controllers\MinutaController();
            $controller->apiCrearVotacion();
            break;

        case 'api_cerrar_votacion':
            $controller = new App\Controllers\MinutaController();
            $controller->apiCerrarVotacion();
            break;

        case 'api_get_votaciones':
            $controller = new App\Controllers\MinutaController();
            $controller->apiGetVotaciones();
            break;

        case 'api_get_detalle_voto':
            $controller = new App\Controllers\MinutaController();
            $controller->apiGetDetalleVoto();
            break;
        case 'api_guardar_borrador':
            $minutaController->apiGuardarBorrador();
            break;

        case 'api_finalizar_reunion':
            $minutaController->apiFinalizarReunion();
            break;
        case 'reunion_form':      // <--- ESTA ES LA QUE TE FALTA O FALLA
            $controller = new ReunionController();
            if (isset($_GET['id'])) {
                $controller->edit();  // Si hay ID, es editar
            } else {
                $controller->create(); // Si no, es crear nueva
            }
            break;

        case 'store_reunion':     // Guardar nueva
            $controller = new ReunionController();
            $controller->store();
            break;

        case 'update_reunion':    // Guardar edición
            $controller = new ReunionController();
            $controller->update();
            break;

        case 'reunion_eliminar':
            $controller = new ReunionController();
            $controller->delete();
            break;


        case 'reunion_calendario':
            $controller = new ReunionController();
            $controller->calendario();
            break;

        case 'reunion_iniciar_minuta': // La acción mágica de iniciar
            $controller = new ReunionController();
            $controller->iniciarMinuta();
            break;


        case 'ver_minuta_borrador':
            $minutaController->verBorrador();
            break;

        case 'api_ver_feedback':
            $minutaController->apiVerFeedback();
            break;



        case 'recuperar_password':
            $authController->recuperarPassword();
            break;

        case 'restablecer_password':
            $authController->restablecerPassword();
            break;

        case 'api_iniciar_reunion':
            $minutaController->apiIniciarReunion();
            break;


        case 'reuniones_dashboard':

            $controller = new ReunionController();
            $controller->index();
            break;


        case 'reunion_listado':
            $controller = new ReunionController();
            $controller->listar();
            break;

        case 'perfil':
            $controller = new App\Controllers\UserController();
            $controller->perfil();
            break;

        case 'update_perfil':
            $controller = new App\Controllers\UserController();
            $controller->update_perfil();
            break;

        case 'configuracion':
            $controller = new App\Controllers\UserController();
            $controller->configuracion();
            break;

        case 'update_password':
            $controller = new App\Controllers\UserController();
            $controller->update_password();
            break;

        case 'logout':
            $controller = new App\Controllers\UserController();
            $controller->logout();
            break;

        case 'reunion_listado':
            $controller = new App\Controllers\ReunionController();
            $controller->listar();
            break;

        // Rutas de Adjuntos (Estas deben coincidir con lo que pide el JS)
        case 'api_adjunto_subir':
            // 1. Cargamos el controlador correcto (donde pegaste el código nuevo)
            require_once 'app/controllers/AdjuntoController.php';

            // 2. Instanciamos el AdjuntoController
            $adjuntoController = new AdjuntoController();

            // 3. Llamamos a la función que arreglamos (apiSubir)
            $adjuntoController->apiSubir();
            break;
        case 'api_adjunto_link':
            $controller->apiGuardarLink();
            break;
        case 'api_adjunto_eliminar':
            $controller->apiEliminarAdjunto();
            break;

        case 'api_filtrar_seguimiento': // <--- NUEVO CASE
            $controller = new App\Controllers\MinutaController();
            $controller->apiFiltrarSeguimiento();
            break;

        case 'api_adjunto_listar':

            $controller->apiVerAdjuntosMinuta();
            break;


        case 'api_filtrar_comisiones':
            $controller = new App\Controllers\ComisionController();
            $controller->apiFiltrarComisiones();
            break;

        case 'api_comision_estado': // <--- NUEVA RUTA
            $controller = new App\Controllers\ComisionController();
            $controller->apiCambiarEstado();
            break;

        case 'api_filtrar_usuarios':
            $controller = new App\Controllers\UserController();
            $controller->apiFiltrarUsuarios();
            break;

        // Agregar en el switch
        case 'api_historial_asistencia':
            $asistenciaController->apiHistorial();
            break;

        case 'api_historial_global':
            $votacionController->apiHistorialGlobal();
            break;

        default:
            // Si la acción no existe, manda al login
            header('Location: index.php?action=login');
            exit();
    } // Fin del switch principal

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
