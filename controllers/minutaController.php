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

        // --- INICIO DIAGNÓSTICO CONTROLLER ---
        echo ""; // (Dejamos los echos en blanco del archivo original)
        // --- FIN DIAGNÓSTICO CONTROLLER ---

        // 2. LÓGICA DE FECHAS POR DEFECTO (Tu nueva función)
        $today = date('Y-m-d'); // Obtenemos la fecha de hoy

        // Capturar filtros (usar 'hoy' como default si no vienen)
        $startDate = $_GET['startDate'] ?? date('Y-m-01');
        $endDate = $_GET['endDate'] ?? date('Y-m-d');
        $themeName = $_GET['themeName'] ?? '';
        // ---------------------------------------

        // 3. Validar estado y llamar al Modelo
        if ($estado_filtro !== 'PENDIENTE' && $estado_filtro !== 'APROBADA') {
            $minutas = []; // Estado inválido, no buscar nada
            echo ""; 
        } else {
            // Llamar al modelo con todos los parámetros
            $minutas = $model->getMinutasByEstado($estado_filtro, $startDate, $endDate, $themeName);
        }

        // 4. Preparar variables para la Vista (con nombres limpios)
        $estadoActual = $estado_filtro;
        $currentStartDate = $startDate; // Pasa la fecha (de filtro o de hoy)
        $currentEndDate = $endDate;     // Pasa la fecha (de filtro o de hoy)
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


    default:
        // Redirigir a una página por defecto si la acción no se reconoce
        header('Location: menu.php?pagina=minutas_pendientes');
        exit;
}