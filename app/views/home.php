<?php
// app/views/pages/home.php

// Extraemos los datos para facilitar el uso en la vista
$tareas = $data['tareas_pendientes'] ?? [];
$reuniones = $data['proximas_reuniones'] ?? [];
$actividad = $data['actividad_reciente'] ?? [];
$minutasRecientes = $data['minutas_recientes_aprobadas'] ?? [];
$usuarioNombre = htmlspecialchars($data['usuario']['nombre'] ?? 'Usuario');

// Definimos un saludo según la hora
$hora = date('G');
if ($hora >= 5 && $hora < 12) {
    $saludo = "Buenos días";
} elseif ($hora >= 12 && $hora < 19) {
    $saludo = "Buenas tardes";
} else {
    $saludo = "Buenas noches";
}
?>

<!-- Encabezado de Bienvenida -->
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-0 text-gray-800"><?php echo $saludo; ?>, <?php echo $usuarioNombre; ?></h1>
        <p class="mb-0 text-muted">Bienvenido al Panel de Gestión Documental del CORE.</p>
    </div>
    <div class="d-none d-sm-inline-block">
        <a href="index.php?action=minutas_dashboard" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm">
            <i class="fas fa-file-alt fa-sm text-white-50 me-1"></i> Ir a Minutas
        </a>
    </div>
</div>

<!-- Fila Principal: Carrusel y Novedades -->
<div class="row g-4 mb-4">
    
    <!-- Columna Izquierda: Carrusel Informativo -->
    <div class="col-lg-8">
        <div class="card shadow mb-4 h-100">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between bg-white">
                <h6 class="m-0 fw-bold text-primary"><i class="fas fa-images me-2"></i> Galería Regional</h6>
            </div>
            <div class="card-body p-0">
                <div id="carouselZonasRegion" class="carousel slide carousel-fade h-100" data-bs-ride="carousel">
                    <!-- Indicadores -->
                    <div class="carousel-indicators">
                        <button type="button" data-bs-target="#carouselZonasRegion" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Slide 1"></button>
                        <button type="button" data-bs-target="#carouselZonasRegion" data-bs-slide-to="1" aria-label="Slide 2"></button>
                        <button type="button" data-bs-target="#carouselZonasRegion" data-bs-slide-to="2" aria-label="Slide 3"></button>
                    </div>

                    <!-- Slides -->
                    <div class="carousel-inner h-100 rounded-bottom">
                        <!-- Slide 1 -->
                        <div class="carousel-item active h-100">
                            <div class="d-flex align-items-center justify-content-center bg-dark text-white h-100 position-relative" 
                                 style="min-height: 300px; background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);">
                                <!-- Si tienes imágenes reales, usa: <img src="..." class="d-block w-100 h-100 object-fit-cover" alt="..."> -->
                                <div class="text-center p-5">
                                    <i class="fas fa-building fa-4x mb-3 text-white-50"></i>
                                    <h3>Gestión Transparente</h3>
                                    <p class="lead">Plataforma oficial para la gestión de actas y votaciones.</p>
                                </div>
                            </div>
                            <div class="carousel-caption d-none d-md-block bg-dark bg-opacity-50 rounded p-2">
                                <h5>Consejo Regional</h5>
                                <p>Compromiso con el desarrollo regional.</p>
                            </div>
                        </div>
                        <!-- Slide 2 -->
                        <div class="carousel-item h-100">
                            <div class="d-flex align-items-center justify-content-center bg-secondary text-white h-100" 
                                 style="min-height: 300px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                <div class="text-center p-5">
                                    <i class="fas fa-users fa-4x mb-3 text-white-50"></i>
                                    <h3>Participación Activa</h3>
                                    <p class="lead">Facilitando la labor de los consejeros.</p>
                                </div>
                            </div>
                        </div>
                        <!-- Slide 3 -->
                        <div class="carousel-item h-100">
                            <div class="d-flex align-items-center justify-content-center bg-success text-white h-100" 
                                 style="min-height: 300px; background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                                <div class="text-center p-5">
                                    <i class="fas fa-file-signature fa-4x mb-3 text-white-50"></i>
                                    <h3>Firma Digital</h3>
                                    <p class="lead">Procesos más rápidos y seguros.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Controles -->
                    <button class="carousel-control-prev" type="button" data-bs-target="#carouselZonasRegion" data-bs-slide="prev">
                        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">Anterior</span>
                    </button>
                    <button class="carousel-control-next" type="button" data-bs-target="#carouselZonasRegion" data-bs-slide="next">
                        <span class="carousel-control-next-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">Siguiente</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Columna Derecha: Novedades y Accesos Rápidos -->
    <div class="col-lg-4">
        <!-- Panel de Novedades -->
        <div class="card shadow mb-4">
            <div class="card-header py-3 bg-warning bg-opacity-10 border-bottom-warning">
                <h6 class="m-0 fw-bold text-dark"><i class="fas fa-bullhorn me-2 text-warning"></i> Novedades</h6>
            </div>
            <div class="card-body">
                <div class="list-group list-group-flush">
                    <div class="list-group-item px-0">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1 fw-bold text-primary">Módulo de Votación v2.0</h6>
                            <small class="text-muted">Hace 2 días</small>
                        </div>
                        <p class="mb-1 small text-muted">Ahora los presidentes pueden ver resultados en tiempo real durante las sesiones.</p>
                    </div>
                    <div class="list-group-item px-0">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1 fw-bold text-success">Firma Digital Activa</h6>
                            <small class="text-muted">Hace 1 semana</small>
                        </div>
                        <p class="mb-1 small text-muted">El sistema de firma electrónica avanzada ya está operativo para todas las actas.</p>
                    </div>
                     <div class="list-group-item px-0 border-bottom-0">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1 fw-bold text-info">Autogestión de Asistencia</h6>
                            <small class="text-muted">Nuevo</small>
                        </div>
                        <p class="mb-1 small text-muted">Recuerda marcar tu asistencia desde el menú lateral al ingresar a la sala.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Widget de Clima (Placeholder) -->
        <div class="card shadow mb-4 bg-info text-white border-0">
            <div class="card-body d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="fw-bold mb-1">Valparaíso</h6>
                    <small id="fecha-actual"><?php echo date('d/m/Y'); ?></small>
                </div>
                <div class="text-end">
                    <span class="h4 fw-bold mb-0" id="temperatura-actual">--°C</span>
                    <i class="fas fa-cloud-sun fa-lg ms-2"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Fila Secundaria: Tareas y Actividad -->
<div class="row g-4">
    
    <!-- Columna: Mis Tareas Pendientes -->
    <div class="col-lg-6">
        <div class="card shadow mb-4 h-100">
            <div class="card-header py-3 bg-white border-bottom-primary d-flex justify-content-between align-items-center">
                <h6 class="m-0 fw-bold text-primary"><i class="fas fa-tasks me-2"></i> Mis Tareas Pendientes</h6>
                <?php if (!empty($tareas)): ?>
                    <span class="badge bg-danger rounded-pill"><?php echo count($tareas); ?></span>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <?php if (empty($tareas)): ?>
                    <div class="text-center py-5">
                        <div class="mb-3">
                            <i class="fas fa-check-circle fa-4x text-success opacity-50"></i>
                        </div>
                        <h5 class="text-muted fw-normal">¡Todo al día!</h5>
                        <p class="text-muted small mb-0">No tienes tareas pendientes por ahora.</p>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($tareas as $t): ?>
                            <a href="<?php echo $t['link']; ?>" class="list-group-item list-group-item-action p-3 border-start-0 border-end-0 d-flex align-items-center">
                                <div class="me-3">
                                    <div class="icon-circle bg-light text-<?php echo $t['color']; ?> rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                        <i class="fas <?php echo $t['icono']; ?>"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="fw-bold text-dark mb-1"><?php echo $t['texto']; ?></div>
                                    <small class="text-muted">Requiere tu atención inmediata.</small>
                                </div>
                                <i class="fas fa-chevron-right text-muted small"></i>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Columna: Próximas Reuniones -->
    <div class="col-lg-6">
        <div class="card shadow mb-4 h-100">
            <div class="card-header py-3 bg-white border-bottom-info">
                <h6 class="m-0 fw-bold text-info"><i class="far fa-calendar-alt me-2"></i> Próximas Reuniones</h6>
            </div>
            
            <div class="card-body p-0">
                <?php if (empty($reuniones)): ?>
                    <div class="text-center py-5 text-muted">
                        <p class="mb-0">No hay reuniones programadas próximamente.</p>
                    </div>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($reuniones as $r): ?>
                            <?php 
                                $fechaReu = new DateTime($r['fechaInicioReunion']);
                                $esHoy = $fechaReu->format('Y-m-d') === date('Y-m-d');
                            ?>
                            <li class="list-group-item p-3 border-start-0 border-end-0">
                                <div class="d-flex w-100 justify-content-between align-items-center mb-1">
                                    <h6 class="mb-0 fw-bold text-dark">
                                        <?php echo htmlspecialchars($r['nombreReunion']); ?>
                                    </h6>
                                    <?php if ($esHoy): ?>
                                        <span class="badge bg-danger">HOY</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><?php echo $fechaReu->format('d/m'); ?></span>
                                    <?php endif; ?>
                                </div>
                                <p class="mb-1 small text-muted">
                                    <i class="far fa-clock me-1"></i> <?php echo $fechaReu->format('H:i'); ?> hrs
                                    &nbsp;|&nbsp; 
                                    <i class="fas fa-users me-1"></i> <?php echo htmlspecialchars($r['nombreComision'] ?? 'Comisión'); ?>
                                </p>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
             <div class="card-footer bg-white text-center border-0 pb-3">
                <a href="index.php?action=reuniones_dashboard" class="small text-decoration-none fw-bold text-info">
                    Ver Calendario Completo <i class="fas fa-arrow-right ms-1"></i>
                </a>
            </div>
        </div>
    </div>

</div>

<!-- Fila Terciaria: Actividad Reciente (Opcional, si hay datos) -->
<?php if (!empty($actividad)): ?>
<div class="row">
    <div class="col-12">
        <div class="card shadow mb-4">
             <div class="card-header py-3 bg-white">
                <h6 class="m-0 fw-bold text-secondary"><i class="fas fa-history me-2"></i> Actividad Reciente del Sistema</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-borderless mb-0 align-middle">
                        <tbody>
                            <?php foreach (array_slice($actividad, 0, 5) as $a): ?>
                                <tr class="border-bottom">
                                    <td style="width: 50px;" class="text-center text-muted">
                                        <i class="fas fa-circle fa-xs"></i>
                                    </td>
                                    <td>
                                        <span class="fw-bold text-dark"><?php echo htmlspecialchars($a['usuario_nombre']); ?></span>
                                        <span class="text-muted"><?php echo htmlspecialchars($a['detalle']); ?></span>
                                    </td>
                                    <td class="text-end text-muted small" style="width: 150px;">
                                        <?php echo date('d/m H:i', strtotime($a['fecha_hora'])); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
    // Script simple para simular carga de clima (puedes conectarlo a una API real luego)
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(() => {
            const tempEl = document.getElementById('temperatura-actual');
            if(tempEl) tempEl.innerText = '19°C'; // Valor simulado
        }, 1000);
    });
</script>