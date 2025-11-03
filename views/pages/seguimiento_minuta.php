<?php
// Las variables $minuta y $seguimiento son proporcionadas
// por controllers/MinutaController.php (case 'seguimiento')
?>

<style>
    .timeline-horizontal-container {
        /* Permite el scroll horizontal en pantallas pequeñas */
        overflow-x: auto;
        padding: 25px 10px;
        white-space: nowrap; /* Evita que los pasos salten a la siguiente línea */
    }

    .timeline-horizontal {
        list-style: none;
        padding: 0;
        margin: 0;
        display: flex; /* La magia: pone los <li> uno al lado del otro */
        min-width: 100%;
    }

    .timeline-horizontal li {
        position: relative;
        flex: 1; /* Cada paso intenta tomar el mismo espacio */
        min-width: 200px; /* Ancho mínimo para que cada paso sea legible */
        padding-top: 50px; /* Espacio para el ícono y la línea */
        padding-left: 15px;
        padding-right: 15px;
        text-align: center;
        white-space: normal; /* Permite que el texto dentro del paso se ajuste */
    }

    /* El Ícono/Badge */
    .timeline-badge-horizontal {
        color: #fff;
        width: 36px;
        height: 36px;
        line-height: 36px;
        font-size: 1.2em;
        text-align: center;
        position: absolute;
        top: 0;
        left: 50%;
        margin-left: -18px; /* Centra el badge (mitad de su ancho) */
        background-color: #999999;
        z-index: 100;
        border-radius: 50%;
        border: 3px solid #fff; /* Borde blanco para "levantarlo" de la línea */
    }

    /* La Línea de Conexión */
    .timeline-horizontal li:not(:first-child):before {
        content: " ";
        position: absolute;
        top: 18px; /* Centrado verticalmente (mitad de la altura del badge) */
        left: -50%; /* Inicia desde la mitad del <li> anterior */
        width: 100%;
        height: 3px;
        background-color: #eeeeee;
        z-index: 99;
    }

    /* El Panel de Contenido */
    .timeline-panel-horizontal {
        background: #f9f9f9;
        border: 1px solid #d4d4d4;
        border-radius: 4px;
        padding: 15px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        text-align: left;
    }

    .timeline-panel-horizontal h5 {
        margin-top: 0;
        font-size: 1rem;
        font-weight: bold;
        margin-bottom: 8px;
    }

    .timeline-panel-horizontal p {
        font-size: 0.85rem;
        margin-bottom: 3px;
        color: #555;
    }

    /* Colores de badges (igual que antes) */
    .timeline-badge-horizontal.primary { background-color: #007bff !important; }
    .timeline-badge-horizontal.success { background-color: #28a745 !important; }
    .timeline-badge-horizontal.warning { background-color: #ffc107 !important; }
    .timeline-badge-horizontal.danger { background-color: #dc3545 !important; }
    .timeline-badge-horizontal.info { background-color: #17a2b8 !important; }
    .timeline-badge-horizontal.dark { background-color: #343a40 !important; }
</style>
<div class="container-fluid">
    <div class="card mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                Línea de Tiempo de Minuta: #<?php echo htmlspecialchars($minuta['idMinuta']); ?>
                (Estado: <?php echo htmlspecialchars($minuta['estadoMinuta'] ?? 'N/A'); ?>)
            </h6>
        </div>
        <div class="card-body">
            
            <div class="timeline-horizontal-container">
                <ul class="timeline-horizontal">
                    
                    <?php if (empty($seguimiento)): ?>
                        <li>
                            <div class="timeline-badge-horizontal primary"><i class="fas fa-folder-open"></i></div>
                            <div class="timeline-panel-horizontal">
                                <h5 class="timeline-title">Sin Acciones</h5>
                                <p>Aún no hay acciones registradas para esta minuta.</p>
                            </div>
                        </li>
                    <?php else: ?>
                        <?php foreach ($seguimiento as $evento): ?>
                            <?php
                                // Asignar un ícono y color basado en la acción
                                $badge_class = 'primary';
                                $icon_class = 'fas fa-info';
                                
                                switch ($evento['accion']) {
                                    case 'CREADA':
                                        $badge_class = 'primary';
                                        $icon_class = 'fas fa-pencil-alt';
                                        break;
                                    case 'ENVIADA_APROBACION':
                                        $badge_class = 'info';
                                        $icon_class = 'fas fa-paper-plane';
                                        break;
                                    case 'FEEDBACK_RECIBIDO':
                                        $badge_class = 'warning';
                                        $icon_class = 'fas fa-comments';
                                        break;
                                    case 'FEEDBACK_APLICADO':
                                        $badge_class = 'primary';
                                        $icon_class = 'fas fa-check-double';
                                        break;
                                    case 'FIRMADA_PARCIAL':
                                        $badge_class = 'info';
                                        $icon_class = 'fas fa-signature';
                                        break;
                                    case 'APROBADA_FINAL':
                                        $badge_class = 'success';
                                        $icon_class = 'fas fa-check-circle';
                                        break;
                                    case 'PDF_GENERADO':
                                        $badge_class = 'dark';
                                        $icon_class = 'fas fa-file-pdf';
                                        break;
                                }
                            ?>
                            <li>
                                <div class="timeline-badge-horizontal <?php echo $badge_class; ?>">
                                    <i class="<?php echo $icon_class; ?>"></i>
                                </div>
                                <div class="timeline-panel-horizontal">
                                    <h5 class="timeline-title"><?php echo htmlspecialchars($evento['detalle']); ?></h5>
                                    <p>
                                        <i class="fas fa-clock fa-fw"></i> <?php echo date('d-m-Y H:i', strtotime($evento['fecha_hora'])); ?>
                                    </p>
                                    <p>
                                        <i class="fas fa-user fa-fw"></i> <?php echo htmlspecialchars($evento['usuario_nombre']); ?>
                                    </p>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    
                </ul>
            </div> </div> </div>
</div>