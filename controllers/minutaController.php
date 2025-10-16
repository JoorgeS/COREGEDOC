<?php
// controllers/MinutaController.php

require_once __DIR__ . '/../models/minutaModel.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** @var MinutaModel $model */ //
$model = new MinutaModel();

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

switch ($action) {
    case 'list':
        // ... (Código existente para listar) ...
        $minutas = $model->getAllMinutas();
        include __DIR__ . '/../views/pages/minutas_listado_general.php';
        break;

    case 'view':
        // ... (Código existente para ver detalle) ...
        $id = (int)$_GET['id'];
        $tema = $model->getTemaById($id);

        if (!$tema) {
            $_SESSION['error'] = 'Tema de minuta no encontrado.';
            header('Location: minutaController.php?action=list');
            exit;
        }

        include __DIR__ . '/../views/pages/minuta_detalle.php';
        break;

    case 'edit':
        // 🎯 Muestra el formulario para editar un tema
        $id = (int)$_GET['id'];
        $tema = $model->getTemaById($id);

        if (!$tema) {
            $_SESSION['error'] = 'Tema no encontrado para edición.';
            header('Location: minutaController.php?action=list');
            exit;
        }
        $title = "Editar Minuta (Tema #{$id})";

        // Carga la nueva vista de formulario de edición
        include __DIR__ . '/../views/pages/minuta_form.php';
        break;

    case 'update':
        // 🎯 Procesa la actualización del formulario
        $id = (int)($_POST['idTema'] ?? 0);
        $data = [
            'nombreTema' => trim($_POST['nombreTema'] ?? ''),
            'objetivo' => trim($_POST['objetivo'] ?? ''),
            'compromiso' => trim($_POST['compromiso'] ?? ''),
            'observacion' => trim($_POST['observacion'] ?? '')
        ];

        if ($id === 0 || empty($data['nombreTema'])) {
            $_SESSION['error'] = 'Datos incompletos o ID inválido.';
            header('Location: minutaController.php?action=edit&id=' . $id);
            exit;
        }

        if ($model->updateTema($id, $data)) {
            $_SESSION['success'] = 'Minuta actualizada con éxito.';
        } else {
            $_SESSION['error'] = 'Error al actualizar la minuta.';
        }

        header('Location: minutaController.php?action=list');
        exit;

    default:
        header('Location: minutaController.php?action=list');
        exit;
}
