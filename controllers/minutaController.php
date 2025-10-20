<?php
// controllers/MinutaController.php

require_once __DIR__ . '/../models/minutaModel.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** @var MinutaModel $model */ //
$model = new MinutaModel();

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

// Capturar el estado del filtro de la URL (si existe)
$estado_filtro = $_GET['estado'] ?? null;

switch ($action) {
    case 'list':
        // 2. Filtrar solo si el estado es válido (PENDIENTE o APROBADA)
        if ($estado_filtro === 'PENDIENTE' || $estado_filtro === 'APROBADA') {
            $minutas = $model->getMinutasByEstado($estado_filtro);
        } else {
            // Si no hay filtro o es inválido, muestra todo
            $minutas = $model->getAllMinutas();
        }

        // El listado general ahora será el filtro
        include __DIR__ . '/../views/pages/minutas_listado_general.php';
        break;

    case 'view':
        // CÓDIGO ORIGINAL DEL CASO 'VIEW'
        $id = (int)$_GET['id'];
        $tema = $model->getTemaById($id);

        if (!$tema) {
            $_SESSION['error'] = 'Tema de minuta no encontrado.';
            header('Location: MinutaController.php?action=list');
            exit;
        }

        include __DIR__ . '/../views/pages/minuta_detalle.php';
        break;

    case 'edit':
        // CÓDIGO ORIGINAL DEL CASO 'EDIT'
        $id = (int)$_GET['id'];
        $tema = $model->getTemaById($id);

        if (!$tema) {
            $_SESSION['error'] = 'Tema no encontrado para edición.';
            header('Location: MinutaController.php?action=list');
            exit;
        }
        $title = "Editar Minuta (Tema #{$id})";

        // Carga la nueva vista de formulario de edición
        include __DIR__ . '/../views/pages/minuta_form.php';
        break;

    case 'update':
        // CÓDIGO ORIGINAL DEL CASO 'UPDATE'
        $id = (int)($_POST['idTema'] ?? 0);
        $data = [
            'nombreTema' => trim($_POST['nombreTema'] ?? ''),
            'objetivo' => trim($_POST['objetivo'] ?? ''),
            'compromiso' => trim($_POST['compromiso'] ?? ''),
            'observacion' => trim($_POST['observacion'] ?? '')
        ];

        if ($id === 0 || empty($data['nombreTema'])) {
            $_SESSION['error'] = 'Datos incompletos o ID inválido.';
            header('Location: MinutaController.php?action=edit&id=' . $id);
            exit;
        }

        if ($model->updateTema($id, $data)) {
            $_SESSION['success'] = 'Minuta actualizada con éxito.';
        } else {
            $_SESSION['error'] = 'Error al actualizar la minuta.';
        }

        header('Location: MinutaController.php?action=list');
        exit;

    default:
        header('Location: MinutaController.php?action=list');
        exit;
}
