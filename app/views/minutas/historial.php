<?php
$minuta = $data['minuta'];
$seguimiento = $data['seguimiento'];
?>

<style>
    /* --- ESTILOS DE TU TIMELINE ORIGINAL --- */
    .timeline-horizontal-container {
        overflow-x: auto;
        padding: 20px 10px;
        white-space: nowrap;
    }
    .timeline-horizontal {
        list-style: none;
        padding: 0;
        margin: 0;
        display: flex;
        min-width: 100%;
    }
    .timeline-horizontal li {
        position: relative;
        flex: 1;
        min-width: 220px;
        padding-top: 50px;
        padding-left: 15px;
        padding-right: 15px;
        text-align: center;
        white-space: normal;
    }
    /* Ícono/Badge */
    .timeline-badge-horizontal {
        color: #fff;
        width: 40px;
        height: 40px;
        line-height: 36px;
        font-size: 1.2em;
        text-align: center;
        position: absolute;
        top: 0;
        left: 50%;
        margin-left: -20px;
        background-color: #999;
        z-index: 100;
        border-radius: 50%;
        border: 3px solid #fff;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    /* Línea conectora */
    .timeline-horizontal li:not(:first-child):before {
        content: " ";
        position: absolute;
        top: 20px;
        left: -50%;
        width: 100%;
        height: 4px;
        background-color: #e9ecef;
        z-index: 99;
    }
    /* Panel */
    .timeline-panel-horizontal {
        background: #fff;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: 15px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        text-align: left;
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }
    .timeline-title {
        font-size: 0.95rem;
        font-weight: bold;
        margin-bottom: 5px;
        color: #333;
    }
    .text-small { font-size: 0.85rem; color: #6c757d; }
    
    /* Colores */
    .bg-primary { background-color: #0d6efd !important; }
    .bg-success { background-color: #198754 !important; }
    .bg-warning { background-color: #ffc107 !important; }
    .bg-danger { background-color: #dc3545 !important; }
    .bg-info { background-color: #0dcaf0 !important; }
    .bg-dark { background-color: #212529 !important; }
</style>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3>
            <i class="fas fa-history text-primary me-2"></i> 
            Trazabilidad Minuta #<?php echo $minuta['idMinuta']; ?>
        </h3>
        <a href="javascript:history.back()" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Volver
        </a>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white border-bottom-0 pt-4 px-4">
            <h5 class="mb-0">Línea de Tiempo de Eventos</h5>
            <p class="text-muted small">Estado actual: <strong><?php echo $minuta['estadoMinuta']; ?></strong></p>
        </div>
        <div class="card-body">
            <div class="timeline-horizontal-container">
                <ul class="timeline-horizontal">
                    <?php if (empty($seguimiento)): ?>
                        <li>
                            <div class="timeline-badge-horizontal bg-secondary"><i class="fas fa-hourglass-start"></i></div>
                            <div class="timeline-panel-horizontal">
                                <h5 class="timeline-title">Sin Historial</h5>
                                <p class="text-small">No hay eventos registrados aún.</p>
                            </div>
                        </li>
                    <?php else: ?>
                        <?php foreach ($seguimiento as $evento): 
                            $color = 'bg-secondary';
                            $icon = 'fa-info';
                            
                            switch ($evento['accion']) {
                                case 'CREADA': $color = 'bg-primary'; $icon = 'fa-plus'; break;
                                case 'ENVIADA_APROBACION': $color = 'bg-info'; $icon = 'fa-paper-plane'; break;
                                case 'FEEDBACK_RECIBIDO': $color = 'bg-warning'; $icon = 'fa-comment-dots'; break;
                                case 'FEEDBACK_APLICADO': $color = 'bg-primary'; $icon = 'fa-tools'; break;
                                case 'FIRMADA_PARCIAL': $color = 'bg-info'; $icon = 'fa-file-signature'; break;
                                case 'APROBADA_FINAL': $color = 'bg-success'; $icon = 'fa-check-double'; break;
                                case 'PDF_GENERADO': $color = 'bg-dark'; $icon = 'fa-file-pdf'; break;
                            }
                        ?>
                        <li>
                            <div class="timeline-badge-horizontal <?php echo $color; ?>">
                                <i class="fas <?php echo $icon; ?>"></i>
                            </div>
                            <div class="timeline-panel-horizontal">
                                <div class="text-small mb-1 text-uppercase fw-bold text-primary">
                                    <?php echo $evento['accion']; ?>
                                </div>
                                <h5 class="timeline-title"><?php echo htmlspecialchars($evento['detalle']); ?></h5>
                                <div class="mt-2 pt-2 border-top">
                                    <p class="text-small mb-0">
                                        <i class="far fa-clock"></i> <?php echo date('d/m/Y H:i', strtotime($evento['fecha_hora'])); ?><br>
                                        <i class="far fa-user"></i> <?php echo htmlspecialchars($evento['usuario_nombre']); ?>
                                    </p>
                                </div>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>