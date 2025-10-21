<?php
// controllers/MinutaController.php

require_once __DIR__ . '/../models/minutaModel.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** @var MinutaModel $model */
$model = new MinutaModel();

// Determinar acción (GET o POST)
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

// Capturar estado (siempre necesario para 'list')
$estado_filtro = $_GET['estado'] ?? null;

switch ($action) {

    case 'list':
        // Asignar estado por defecto si no viene
        $estado_filtro = $estado_filtro ?? 'PENDIENTE';

        // --- INICIO DIAGNÓSTICO CONTROLLER ---
        echo "";
        // --- FIN DIAGNÓSTICO CONTROLLER ---

        // Capturar filtros de fecha y tema
        $startDate = $_GET['startDate'] ?? null;
        $endDate = $_GET['endDate'] ?? null;
        $themeName = $_GET['themeName'] ?? null;

        // Validar estado
        if ($estado_filtro !== 'PENDIENTE' && $estado_filtro !== 'APROBADA') {
            $minutas = []; // Estado inválido, no buscar nada
            echo ""; // Mensaje extra
        } else {
            // Llamar al modelo con todos los parámetros
            $minutas = $model->getMinutasByEstado($estado_filtro, $startDate, $endDate, $themeName);
        }

        // Pasar filtros a la vista para que los recuerde
        $filtro_startDate = $startDate;
        $filtro_endDate = $endDate;
        $filtro_themeName = $themeName;

        // Incluir la vista
        include __DIR__ . '/../views/pages/minutas_listado_general.php';
        break; // Fin case 'list'


    case 'view':
        // ... (Tu código view sin cambios) ...
        $id = (int)($_GET['id'] ?? 0);
        $tema = $model->getTemaById($id); // OJO: Esto busca por ID de TEMA, no de MINUTA
        if (!$tema) {
            $_SESSION['error'] = 'Tema no encontrado.';
            header('Location: menu.php?pagina=minutas_pendientes'); // Redirige a menu.php
            exit;
        }
        include __DIR__ . '/../views/pages/minuta_detalle.php'; // Asumiendo que existe
        break;


    case 'edit': // Esta acción es manejada por menu.php incluyendo crearMinuta.php
        // No debería llegar aquí directamente si la navegación es correcta.
        // Podríamos redirigir por seguridad.
        header('Location: menu.php?pagina=editar_minuta&id=' . ($_GET['id'] ?? 0));
        exit;
        break;


    case 'update': // Esta acción SÍ se ejecuta directamente por el POST del formulario
        // ... (Tu código update sin cambios) ...
        $id = (int)($_POST['idTema'] ?? 0); // OJO: ¿Debería ser idMinuta? Revisa tu form.
        $data = [ /* ... tus datos ... */];
        // ... validaciones ...
        if ($model->updateTema($id, $data)) { // OJO: updateTema actualiza t_tema, no t_minuta
            $_SESSION['success'] = 'Actualizado con éxito.';
        } else {
            $_SESSION['error'] = 'Error al actualizar.';
        }
        header('Location: menu.php?pagina=minutas_pendientes'); // Redirige a lista en menu.php
        exit;
        break;


    default:
        // Redirigir a una página por defecto si la acción no se reconoce
        header('Location: menu.php?pagina=minutas_pendientes');
        exit;
}
