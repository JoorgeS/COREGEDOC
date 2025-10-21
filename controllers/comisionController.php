<?php
// controllers/ComisionController.php
require_once __DIR__ . '/../models/comisionModel.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$model = new ComisionModel();
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

// Define la URL base para redireccionar
$redirectUrl = '/corevota/views/pages/menu.php?pagina=comision_listado'; // <-- URL Correcta

switch ($action) {
    case 'list':
        $comisiones = $model->getAllComisiones(true);
        include __DIR__ . '/../views/pages/comisiones_listado.php';
        break;

    case 'create':
        $comision = null;
        $title = "Crear Nueva Comisión";
        include __DIR__ . '/../views/pages/comision_form.php';
        break;

    case 'store':
        $nombre = trim($_POST['nombreComision'] ?? '');
        $vigencia = (int)($_POST['vigencia'] ?? 1);
        if (empty($nombre)) {
            $_SESSION['error'] = 'El nombre es obligatorio.';
            // Redirige al formulario de creación DENTRO de menu.php
            header('Location: /corevota/views/pages/menu.php?pagina=comision_crear');
            exit;
        }
        if ($model->createComision($nombre, $vigencia)) {
            $_SESSION['success'] = 'Comisión creada con éxito.';
        } else {
            $_SESSION['error'] = 'Error al crear la comisión.';
        }
        // ❗️❗️ REDIRECCIÓN CORREGIDA ❗️❗️
        header('Location: ' . $redirectUrl);
        exit;

    case 'edit':
        $id = (int)($_GET['id'] ?? 0); // Asegura obtener ID de GET
        $comision = $model->getComisionById($id);
        if (!$comision) {
            $_SESSION['error'] = 'Comisión no encontrada.';
            header('Location: ' . $redirectUrl); // Redirige a lista si no existe
            exit;
        }
        $title = "Editar Comisión: " . htmlspecialchars($comision['nombreComision']);
        include __DIR__ . '/../views/pages/comision_form.php';
        break;

    case 'update':
        $id = (int)($_POST['idComision'] ?? 0);
        $nombre = trim($_POST['nombreComision'] ?? '');
        $vigencia = isset($_POST['vigencia']) ? 1 : 0; // Checkbox o valor 1/0? Ajusta si es necesario
        if (empty($nombre) || $id === 0) {
            $_SESSION['error'] = 'Datos incompletos.';
            // Redirige al formulario de edición DENTRO de menu.php
            header('Location: /corevota/views/pages/menu.php?pagina=comision_editar&id=' . $id);
            exit;
        }
        if ($model->updateComision($id, $nombre, $vigencia)) {
            $_SESSION['success'] = 'Comisión actualizada.';
        } else {
            $_SESSION['error'] = 'Error al actualizar.';
        }
        // ❗️❗️ REDIRECCIÓN CORREGIDA ❗️❗️
        header('Location: ' . $redirectUrl);
        exit;

    case 'delete':
        $id = (int)($_GET['id'] ?? 0); // Asegura obtener ID de GET
        if ($id > 0 && $model->deleteComision($id)) {
            $_SESSION['success'] = 'Comisión deshabilitada.';
        } else {
            $_SESSION['error'] = 'Error al deshabilitar.';
        }
        // ❗️❗️ REDIRECCIÓN CORREGIDA ❗️❗️
        header('Location: /corevota/views/pages/menu.php?pagina=comision_listado');
        exit;

    default:
        // ❗️❗️ REDIRECCIÓN CORREGIDA ❗️❗️
        header('Location: ' . $redirectUrl);
        exit;
}
