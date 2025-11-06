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
    $feedbacks = $model->getFeedbackDeMinuta($idMinuta);

    // 4. Construir el HTML

    // --- Historial de Estados (AHORA COMO TABLA) ---
    $html .= '<h5><i class="fas fa-history"></i> Historial de Estados</h5>';
    if (!empty($historial)) {
        $html .= '<div class="table-responsive mb-3">';
        $html .= '<table class="table table-bordered table-striped table-sm" id="tabla-seguimiento-detalle" width="100%" cellspacing="0">';
        $html .= '<thead class="thead-light">';
        $html .= '<tr>';
        $html .= '<th>ID Seguimiento</th>';
        $html .= '<th>Estado</th>';
        $html .= '<th>Usuario</th>';
        $html .= '<th>Fecha y Hora</th>';
        $html .= '</tr>';
        $html .= '</thead>';
        $html .= '<tbody>';
        
        foreach ($historial as $item) {
            $html .= '<tr>';
            // Asumiendo que la columna se llama 'id_seguimiento', si no, ajústala.
            $html .= '<td>' . htmlspecialchars($item['id_seguimiento'] ?? $item['id'] ?? 'N/A') . '</td>'; 
            $html .= '<td>' . htmlspecialchars($item['detalle']) . '</td>';
            $html .= '<td>' . htmlspecialchars($item['usuario_nombre']) . '</td>';
            $html .= '<td>' . date('d-m-Y H:i', strtotime($item['fecha_hora'])) . '</td>';
            $html .= '</tr>';
        }
        
        $html .= '</tbody>';
        $html .= '</table>';
        $html .= '</div>';
    } else {
        $html .= '<p class="text-muted">No se encontró historial de estados.</p>';
    }

    // --- Feedbacks (Se mantiene como lista, igual que en tu código original) ---
    if (!empty($feedbacks)) {
        $html .= '<h5 class="mt-4"><i class="fas fa-comments"></i> Feedback Recibido</h5>';
        $html .= '<div class="list-group list-group-flush">';
        foreach ($feedbacks as $fb) {
            $html .= '<div class="list-group-item" style="background-color: transparent;">';
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
?>