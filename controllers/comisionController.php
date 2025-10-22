<?php
// controllers/ComisionController.php
require_once __DIR__ . '/../models/comisionModel.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$model = new ComisionModel();
$action = $_POST['action'] ?? $_GET['action'] ?? 'list'; // Priorizar POST para formularios

$redirectUrl = '/corevota/views/pages/menu.php?pagina=comision_listado';

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

    case 'store': // Se llama al CREAR
        $nombre = trim($_POST['nombreComision'] ?? '');
        $vigencia = (int)($_POST['vigencia'] ?? 1);
        // ❗️ NUEVO: Capturar ID del presidente (puede ser string vacío si no se selecciona)
        $presidenteId = $_POST['t_usuario_idPresidente'] ?? null;
        // Convertir a null si es un string vacío
        $presidenteId = ($presidenteId === '') ? null : (int)$presidenteId;

        if (empty($nombre)) {
            $_SESSION['error'] = 'El nombre es obligatorio.';
            header('Location: /corevota/views/pages/menu.php?pagina=comision_crear');
            exit;
        }

        // ❗️ Pasar presidenteId al modelo
        if ($model->createComision($nombre, $vigencia, $presidenteId)) {
            $_SESSION['success'] = 'Comisión creada con éxito.';
        } else {
            $_SESSION['error'] = 'Error al crear la comisión.';
        }
        header('Location: ' . $redirectUrl);
        exit;

    case 'edit':
        $id = (int)($_GET['id'] ?? 0);
        $comision = $model->getComisionById($id); // getComisionById debe devolver t_usuario_idPresidente
        if (!$comision) {
            $_SESSION['error'] = 'Comisión no encontrada.';
            header('Location: ' . $redirectUrl);
            exit;
        }
        $title = "Editar Comisión: " . htmlspecialchars($comision['nombreComision']);
        include __DIR__ . '/../views/pages/comision_form.php'; // Incluir form con datos
        break;

    case 'update': // Se llama al EDITAR
        $id = (int)($_POST['idComision'] ?? 0);
        $nombre = trim($_POST['nombreComision'] ?? '');
        $vigencia = (int)($_POST['vigencia'] ?? 0); // Si no viene, es 0
        // ❗️ NUEVO: Capturar ID del presidente
        $presidenteId = $_POST['t_usuario_idPresidente'] ?? null;
        $presidenteId = ($presidenteId === '') ? null : (int)$presidenteId;

        if (empty($nombre) || $id === 0) {
            $_SESSION['error'] = 'Datos incompletos.';
            header('Location: /corevota/views/pages/menu.php?pagina=comision_editar&id=' . $id);
            exit;
        }

        // ❗️ Pasar presidenteId al modelo
        if ($model->updateComision($id, $nombre, $vigencia, $presidenteId)) {
            $_SESSION['success'] = 'Comisión actualizada.';
        } else {
            $_SESSION['error'] = 'Error al actualizar.';
        }
        header('Location: ' . $redirectUrl);
        exit;

    case 'delete':
        $id = (int)($_GET['id'] ?? 0);
        if ($id > 0 && $model->deleteComision($id)) { // deleteComision solo cambia vigencia
            $_SESSION['success'] = 'Comisión deshabilitada.';
        } else {
            $_SESSION['error'] = 'Error al deshabilitar.';
        }
        header('Location: ' . $redirectUrl);
        exit;

    default:
        header('Location: ' . $redirectUrl);
        exit;
}
