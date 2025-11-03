<?php
// controllers/MinutaController.php

// 1. FORZAR QUE SE MUESTREN LOS ERRORES (Lo mantenemos)
error_reporting(E_ALL);
ini_set('display_errors', 0);
// ---------------------------------------------

// Ajustamos la ruta para que sea infalible, subiendo al directorio raíz
require_once __DIR__ . '/../models/minutaModel.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** @var MinutaModel $model */
$model = new MinutaModel();

// 1. Determinar acción y estado (vienen desde menu.php)
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';
$estado_filtro = $_GET['estado'] ?? null; // menu.php nos da esto

switch ($action) {

    case 'list':
        // Si 'estado' no vino por la URL, ponemos 'PENDIENTE' por defecto.
        if ($estado_filtro === null) {
            $estado_filtro = 'PENDIENTE';
        }

        // --- INICIO BLOQUE DE SEGURIDAD POR ROL ---

        // Definimos los roles para que el código sea legible.
        // (Asegúrate de que estos números coincidan con tu BBDD)
        if (!defined('ROL_ADMINISTRADOR')) define('ROL_ADMINISTRADOR', 1);
        if (!defined('ROL_SECRETARIO')) define('ROL_SECRETARIO', 2);
        if (!defined('ROL_PRESIDENTE')) define('ROL_PRESIDENTE', 3);
        if (!defined('ROL_CONSEJERO')) define('ROL_CONSEJERO', 4);

        $tipoUsuario = $_SESSION['tipoUsuario_id'] ?? 0;

        // REGLA 1: La lista 'PENDIENTE' es SÓLO para el Secretario (o Admin).
        if ($estado_filtro == 'PENDIENTE' && ($tipoUsuario != ROL_SECRETARIO && $tipoUsuario != ROL_ADMINISTRADOR)) {
            // ¡Acceso Denegado!
            // Redirigimos al dashboard con un mensaje de error.
            header('Location: menu.php?pagina=minutas_dashboard&error=acceso_denegado');
            exit;
        }

        // REGLA 2: La lista 'APROBADA' es para todos.
        // No se necesita hacer nada, simplemente dejamos que el script continúe.

        // --- FIN BLOQUE DE SEGURIDAD ---


        // 2. LÓGICA DE FECHAS POR DEFECTO
        $today = date('Y-m-d'); // Obtenemos la fecha de hoy
        $startDate = $_GET['startDate'] ?? date('Y-m-01');
        $endDate = $_GET['endDate'] ?? date('Y-m-d');
        $themeName = $_GET['themeName'] ?? '';

        // 3. Validar estado y llamar al Modelo
        // (La validación de roles ya se hizo arriba, aquí solo validamos el string)
        if ($estado_filtro !== 'PENDIENTE' && $estado_filtro !== 'APROBADA') {
            $minutas = []; // Estado inválido, no buscar nada
        } else {
            // Llamar al modelo con todos los parámetros
            // (Asegúrate de que tu modelo acepte $themeName, mira el Paso 3)
            $minutas = $model->getMinutasByEstado($estado_filtro, $startDate, $endDate, $themeName);
        }

        // 4. Preparar variables para la Vista (con nombres limpios)
        $estadoActual = $estado_filtro;
        $currentStartDate = $startDate;
        $currentEndDate = $endDate;
        $currentThemeName = $themeName;
        // $minutas ya está definida por el modelo

        // 5. Incluir la Vista (Paso final)
        include __DIR__ . '/../views/pages/minutas_listado_general.php';
        break; // Fin case 'list'
    case 'view':
        // ... (Tu código view original) ...
        $id = (int)($_GET['id'] ?? 0);
        $tema = $model->getTemaById($id);
        if (!$tema) {
            $_SESSION['error'] = 'Tema no encontrado.';
            header('Location: menu.php?pagina=minutas_pendientes');
            exit;
        }
        include __DIR__ . '/../views/pages/minuta_detalle.php';
        break;


    case 'edit':
        // ... (Tu código edit original) ...
        header('Location: menu.php?pagina=editar_minuta&id=' . ($_GET['id'] ?? 0));
        exit;
        break;


    case 'update':
        // ... (Tu código update original) ...
        $id = (int)($_POST['idTema'] ?? 0);
        $data = [ /* ... tus datos ... */];

        if ($model->updateTema($id, $data)) {
            $_SESSION['success'] = 'Actualizado con éxito.';
        } else {
            $_SESSION['error'] = 'Error al actualizar.';
        }
        header('Location: menu.php?pagina=minutas_pendientes');
        exit;
        break;

    case 'seguimiento':
        if (!isset($_GET['id'])) {
            echo "<div class='alert alert-danger'>Error: No se proporcionó ID de minuta.</div>";
            break; // Detiene la ejecución de este case
        }

        $minuta_id = (int)$_GET['id'];

        // $model ya está definido al inicio del archivo
        $minuta = $model->getMinutaById($minuta_id);
        $seguimiento = $model->getSeguimiento($minuta_id);

        if (!$minuta) {
            echo "<div class='alert alert-danger'>Error: Minuta no encontrada (ID: $minuta_id).</div>";
            break; // Detiene la ejecución de este case
        }

        // Ahora que $minuta y $seguimiento existen, incluimos la vista
        // Esto es igual a como funciona tu case 'list'
        include __DIR__ . '/../views/pages/seguimiento_minuta.php';
        break;


    default:
        // Redirigir a una página por defecto si la acción no se reconoce
        header('Location: menu.php?pagina=minutas_pendientes');
        exit;
}
