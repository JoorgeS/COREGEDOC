<?php
// controllers/obtener_preview_seguimiento.php

session_start();
if (!isset($_SESSION['idUsuario'])) {
    echo '<p class="alert alert-danger">Acceso denegado. Inicie sesión de nuevo.</p>';
    exit;
}

// 1. Incluir dependencias
require_once __DIR__ . '/../cfg/config.php';
require_once __DIR__ . '/../class/class.conectorDB.php';
require_once __DIR__ . '/../models/minutaModel.php';

$html = '';
$idMinuta = $_GET['id'] ?? 0;

if ($idMinuta <= 0) {
    echo '<p class="alert alert-danger">ID de minuta no válido.</p>';
    exit;
}

try {
    // 2. Crear instancia del modelo
    $model = new MinutaModel();

    // 3. Obtener los datos
    $historial = $model->getSeguimiento($idMinuta);
    $feedbacks = $model->getFeedbackDeMinuta($idMinuta); // <-- Esta es la función que debías añadir a tu modelo

    // 4. Construir el HTML

    // --- Historial de Estados ---
    if (!empty($historial)) {
        $html .= '<h5><i class="fas fa-history"></i> Historial de Estados</h5>';
        $html .= '<ul class="list-group list-group-flush mb-3">';
        foreach ($historial as $item) {
            $html .= '<li class="list-group-item d-flex justify-content-between align-items-center" style="background-color: transparent;">'; // Hacemos fondo transparente
            $html .= '<div>';
            $html .= '<strong>' . htmlspecialchars($item['detalle']) . '</strong>';
            $html .= '<br><small class="text-muted">Por: ' . htmlspecialchars($item['usuario_nombre']) . '</small>';
            $html .= '</div>';
            $html .= '<span class="badge bg-secondary rounded-pill">' . date('d-m-Y H:i', strtotime($item['fecha_hora'])) . '</span>';
            $html .= '</li>';
        }
        $html .= '</ul>';
    } else {
        $html .= '<p class="text-muted">No se encontró historial de estados.</p>';
    }

    // --- Feedbacks ---
    if (!empty($feedbacks)) {
        $html .= '<h5 class="mt-4"><i class="fas fa-comments"></i> Feedback Recibido</h5>';
        $html .= '<div class="list-group list-group-flush">';
        foreach ($feedbacks as $fb) {
            $html .= '<div class="list-group-item" style="background-color: transparent;">'; // Hacemos fondo transparente
            $html .= '<div class="d-flex w-100 justify-content-between">';
            $html .= '<h6 class="mb-1">De: ' . htmlspecialchars($fb['nombreUsuario']) . '</h6>';
            $html .= '<small>' . date('d-m-Y H:i', strtotime($fb['fecha_feedback'])) . '</small>';
            $html .= '</div>';
            $html .= '<p class="mb-1">' . nl2br(htmlspecialchars($fb['feedback'])) . '</p>';
            $html .= '</div>';
        }
        $html .= '</div>';
    } else {
        $html .= '<p class="mt-3 text-muted">No hay feedback registrado para esta minuta.</p>';
    }

    // 5. Devolver el HTML
    echo $html;
} catch (Exception $e) {
    // Si ves este error, es probable que te falte la función getFeedbackDeMinuta() en tu modelo
    echo "<div class='alert alert-danger'>Error al cargar datos: " . $e->getMessage() . "</div>";
}
