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

        // <-- CORREGIDO: Roles eliminados.
        // Ya no se definen roles aquí. Se usan los definidos en menu.php
        // (ROL_ADMINISTRADOR = 6, ROL_SECRETARIO_TECNICO = 2, etc.)

        $tipoUsuario = $_SESSION['tipoUsuario_id'] ?? 0;

        // REGLA 1: La lista 'PENDIENTE' es SÓLO para el Secretario (o Admin).
        // <-- CORREGIDO: Usamos ROL_SECRETARIO_TECNICO
        if ($estado_filtro == 'PENDIENTE' && ($tipoUsuario != ROL_SECRETARIO_TECNICO && $tipoUsuario != ROL_ADMINISTRADOR)) {
            
            // <-- CORREGIDO: ¡No usar header()! Mostramos un error en la página.
            echo "<div class='container-fluid mt-4'>";
            echo "  <div class='alert alert-danger text-center'>";
            echo "      <h4 class='alert-heading'><i class='fas fa-exclamation-triangle'></i> Acceso Denegado</h4>";
            echo "      <p>No tiene los permisos necesarios para acceder a esta sección.</p>";
            echo "  </div>";
            echo "</div>";
            
            // Salimos del switch
            break;
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
            // NOTA: Este header() SÍ funciona porque 'view' no se incluye desde menu.php
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

    // <-- CORREGIDO: El 'case seguimiento_general' se eliminó completamente.
    // La lógica de permisos para esa página se moverá a menu.php

    default:
        // Redirigir a una página por defecto si la acción no se reconoce
        header('Location: menu.php?pagina=minutas_pendientes');
        exit;
}