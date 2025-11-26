<?php
// controllers/ComisionController.php
require_once __DIR__ . '/../models/comisionModel.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$model = new ComisionModel();
$action = $_POST['action'] ?? $_GET['action'] ?? 'list';
$redirectUrl = '/coregedoc/views/pages/menu.php?pagina=comision_listado';

switch ($action) {

    /* LISTADO GENERAL DE COMISIONES*/
    case 'list':
        $comisiones = $model->getAllComisiones(true);
        include __DIR__ . '/../views/pages/comisiones_listado.php';
        break;

    /* FORMULARIO NUEVO*/
    case 'create':
        $comision = null;
        $title = "Crear Nueva Comisión";
        include __DIR__ . '/../views/pages/comision_form.php';
        break;

    /* GUARDAR NUEVA COMISIÓN*/
    case 'store':
        $nombre = trim($_POST['nombreComision'] ?? '');
        $vigencia = (int)($_POST['vigencia'] ?? 1);

        // IDs de presidente y vicepresidente
        $presidenteId = $_POST['t_usuario_idPresidente'] ?? null;
        $vicepresidenteId = $_POST['t_usuario_idVicepresidente'] ?? null;

        // Normalizar vacíos a null
        $presidenteId = ($presidenteId === '') ? null : (int)$presidenteId;
        $vicepresidenteId = ($vicepresidenteId === '') ? null : (int)$vicepresidenteId;

        if (empty($nombre)) {
            $_SESSION['error'] = 'El nombre de la comisión es obligatorio.';
            header('Location: /coregedoc/views/pages/menu.php?pagina=comision_crear');
            exit;
        }

        if ($model->createComision($nombre, $vigencia, $presidenteId, $vicepresidenteId)) {
            $_SESSION['success'] = 'Comisión creada con éxito.';
        } else {
            $_SESSION['error'] = 'Error al crear la comisión.';
        }

        header('Location: ' . $redirectUrl);
        exit;

    /* EDITAR FORMULARIO*/
    case 'edit':
        $id = (int)($_GET['id'] ?? 0);
        $comision = $model->getComisionById($id);

        if (!$comision) {
            $_SESSION['error'] = 'Comisión no encontrada.';
            header('Location: ' . $redirectUrl);
            exit;
        }

        $title = "Editar Comisión: " . htmlspecialchars($comision['nombreComision']);
        include __DIR__ . '/../views/pages/comision_form.php';
        break;

    /* ACTUALIZAR COMISIÓN*/
    case 'update':
        $id = (int)($_POST['idComision'] ?? 0);
        $nombre = trim($_POST['nombreComision'] ?? '');
        $vigencia = (int)($_POST['vigencia'] ?? 0);

        // Capturar IDs
        $presidenteId = $_POST['t_usuario_idPresidente'] ?? null;
        $vicepresidenteId = $_POST['t_usuario_idVicepresidente'] ?? null;

        // Normalizar
        $presidenteId = ($presidenteId === '') ? null : (int)$presidenteId;
        $vicepresidenteId = ($vicepresidenteId === '') ? null : (int)$vicepresidenteId;

        if (empty($nombre) || $id === 0) {
            $_SESSION['error'] = 'Datos incompletos para actualizar.';
            header('Location: /coregedoc/views/pages/menu.php?pagina=comision_editar&id=' . $id);
            exit;
        }

        if ($model->updateComision($id, $nombre, $vigencia, $presidenteId, $vicepresidenteId)) {
            $_SESSION['success'] = 'Comisión actualizada correctamente.';
        } else {
            $_SESSION['error'] = 'Error al actualizar la comisión.';
        }

        header('Location: ' . $redirectUrl);
        exit;

    /* DESHABILITAR COMISIÓN*/
    case 'delete':
        $id = (int)($_GET['id'] ?? 0);
        if ($id > 0 && $model->deleteComision($id)) {
            $_SESSION['success'] = 'Comisión deshabilitada correctamente.';
        } else {
            $_SESSION['error'] = 'Error al deshabilitar la comisión.';
        }
        header('Location: ' . $redirectUrl);
        exit;

    /* DEFAULT */
    default:
        header('Location: ' . $redirectUrl);
        exit;
}
