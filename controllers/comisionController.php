<?php
// controllers/ComisionController.php

require_once __DIR__ . '/../models/comisionModel.php';

// SEGURIDAD: Iniciar la sesión si es necesario (para mensajes flash)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$model = new ComisionModel();

// Determinar la acción solicitada (por GET o POST)
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

switch ($action) {
    case 'list':
        // Muestra la vista de listado
        // Obtenemos todas las comisiones (activas e inactivas) para la administración
        $comisiones = $model->getAllComisiones(true); 
        
        // Incluye la vista del listado.
        // Ruta: Sale de 'controllers' (../) y entra a 'views/pages/'
        include __DIR__ . '/../views/pages/comisiones_listado.php';
        break;

    case 'create':
        // Muestra el formulario vacío para crear una nueva comisión
        $comision = null; // Indica que es una operación de creación
        $title = "Crear Nueva Comisión";
        
        // Incluye la vista del formulario
        include __DIR__ . '/../views/pages/comision_form.php';
        break;

    case 'store':
        // Procesa los datos del formulario de creación y los guarda en la DB
        $nombre = trim($_POST['nombreComision'] ?? '');
        // La vigencia se establece a 1 (Activa) por defecto en la creación
        $vigencia = (int)($_POST['vigencia'] ?? 1); 

        if (empty($nombre)) {
            $_SESSION['error'] = 'El nombre de la comisión es obligatorio.';
            header('Location: ?action=create');
            exit;
        }

        if ($model->createComision($nombre, $vigencia)) {
            $_SESSION['success'] = 'Comisión creada con éxito.';
        } else {
            $_SESSION['error'] = 'Error al crear la comisión. Revise logs.';
        }
        
        // Redirige al listado después de la operación
        header('Location: ComisionController.php?action=list');
        exit;

    case 'edit':
        // Muestra el formulario precargado para editar
        $id = (int)$_GET['id'];
        $comision = $model->getComisionById($id);
        
        if (!$comision) {
            $_SESSION['error'] = 'Comisión no encontrada.';
            header('Location: ComisionController.php?action=list');
            exit;
        }
        
        $title = "Editar Comisión: " . htmlspecialchars($comision['nombreComision']);
        
        // Incluye la vista del formulario
        include __DIR__ . '/../views/pages/comision_form.php';
        break;

    case 'update':
        // Procesa los datos del formulario de edición y actualiza la DB
        $id = (int)($_POST['idComision'] ?? 0);
        $nombre = trim($_POST['nombreComision'] ?? '');
        $vigencia = (int)($_POST['vigencia'] ?? 1);

        if (empty($nombre) || $id === 0) {
            $_SESSION['error'] = 'Datos de actualización incompletos.';
            header('Location: ComisionController.php?action=edit&id=' . $id);
            exit;
        }

        if ($model->updateComision($id, $nombre, $vigencia)) {
            $_SESSION['success'] = 'Comisión actualizada con éxito.';
        } else {
            $_SESSION['error'] = 'Error al actualizar la comisión.';
        }
        
        // Redirige al listado
        header('Location: ComisionController.php?action=list');
        exit;

    case 'delete':
        // Procesa la eliminación (cambio de vigencia a 0)
        $id = (int)$_GET['id'];
        
        if ($model->deleteComision($id)) {
            $_SESSION['success'] = 'Comisión deshabilitada con éxito.';
        } else {
            $_SESSION['error'] = 'Error al deshabilitar la comisión.';
        }
        
        // Redirige al listado
        header('Location: ComisionController.php?action=list');
        exit;

    default:
        // Redirecciona al listado por defecto si la acción no es válida
        header('Location: ComisionController.php?action=list');
        exit;
}
?>