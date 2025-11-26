<?php
// /coregedoc/views/home.php
$tareas = $data['tareas_pendientes'];
$reuniones = $data['proximas_reuniones'];
$actividad = $data['actividad_reciente'];
$minutas = $data['minutas_recientes_aprobadas'];
?>

<div class="container-fluid mb-4">
    <div class="row align-items-center">
        <div class="col-md-8">
            <h2 class="display-6">Hola, <?php echo htmlspecialchars($data['usuario']['nombre']); ?></h2>
            <p class="lead text-muted">Bienvenido al panel de gestión.</p>
        </div>
        <div class="col-md-4 text-md-end">
            <p id="temperatura-actual" class="fs-5 text-muted">Cargando clima...</p>
        </div>
    </div>
    <hr>
</div>

<div class="container mt-4">
    <div id="carouselZonasRegion" class="carousel slide carousel-fade" data-bs-ride="carousel">
        <div class="carousel-inner rounded shadow-sm bg-dark">
             <div class="carousel-item active">
                 <div style="height: 200px; display:flex; align-items:center; justify-content:center; color:white;">
                     <h3>Panel de Gestión CORE</h3>
                 </div>
             </div>
        </div>
    </div>
</div>

<div class="container my-4">
    <div class="row g-4">
        <div class="col-lg-8">
            
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white border-0 pt-3">
                    <h5 class="mb-0 fw-bold"><i class="fas fa-tasks me-2 text-primary"></i> Mis Tareas Pendientes</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($tareas)): ?>
                        <div class="text-center p-3">
                            <i class="fas fa-check-circle fa-3x text-success mb-2"></i>
                            <p class="text-muted">¡Todo al día!</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($tareas as $t): ?>
                                <a href="<?php echo $t['link']; ?>" class="list-group-item list-group-item-action d-flex align-items-center p-3">
                                    <i class="fas <?php echo $t['icono']; ?> fa-fw fa-lg text-<?php echo $t['color']; ?> me-3"></i>
                                    <div><?php echo $t['texto']; ?></div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($actividad)): ?>
            <div class="card shadow-sm">
                <div class="card-header bg-white border-0 pt-3">
                    <h5 class="mb-0 fw-bold"><i class="fas fa-history me-2 text-primary"></i> Actividad Reciente</h5>
                </div>
                <div class="card-body">
                    <ul class="timeline">
                        <?php foreach ($actividad as $a): ?>
                            <li class="timeline-item">
                                <div class="timeline-content">
                                    <p><strong><?php echo htmlspecialchars($a['usuario_nombre']); ?></strong> <?php echo htmlspecialchars($a['detalle']); ?></p>
                                    <span class="time text-muted"><?php echo $a['fecha_hora']; ?></span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <?php endif; ?>

        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header bg-white border-0 pt-3">
                    <h5 class="mb-0 fw-bold"><i class="fas fa-calendar-alt me-2 text-primary"></i> Próximas Reuniones</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($reuniones)): ?>
                        <p class="text-muted text-center">No hay reuniones programadas.</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($reuniones as $r): ?>
                                <div class="list-group-item p-3">
                                    <strong><?php echo htmlspecialchars($r['nombreReunion']); ?></strong><br>
                                    <small class="text-primary"><i class="fas fa-clock"></i> <?php echo $r['fechaInicioReunion']; ?></small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>