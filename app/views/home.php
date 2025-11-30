<?php
// app/views/pages/home.php

// Extraemos los datos que deben ser provistos por el controlador (HomeController.php)
$tareas = $data['tareas_pendientes'] ?? [];
$reuniones = $data['proximas_reuniones'] ?? [];
$actividad = $data['actividad_reciente'] ?? [];
$minutasRecientes = $data['minutas_recientes_aprobadas'] ?? [];
$usuarioNombre = htmlspecialchars($data['usuario']['nombre'] ?? 'Usuario');

// Datos del carrusel y saludo movidos al controlador
$saludo = $data['saludo'] ?? 'Hola';
$imagenesZonas = $data['imagenes_zonas'] ?? [];
?>

<style>
    /* Hace que la transición del carrusel sea más lenta */
    .carousel-fade .carousel-item {
        transition: opacity 5s ease-in-out;
    }

    /* Estilos para el overlay de texto centrado */
    .carousel-overlay {
        position: absolute;
        top: 0;
        right: 0;
        bottom: 0;
        left: 0;
        text-align: center;
        color: white;
        /* Color de texto para el contraste */
        /* Centrado con flexbox */
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        z-index: 10;
        /* Sombra de texto para mejorar la visibilidad sobre la imagen */
        text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.7);
    }

    /* Contenedor del icono y el título para el fondo gris transparente */
    .carousel-content-box {
        background-color: rgba(90, 90, 90, 0.4);
        /* Gris semi-transparente */
        padding: 20px 30px;
        /* Espaciado interno */
        border-radius: 10px;
        /* Bordes ligeramente redondeados */
        display: flex;
        /* Asegura el flexbox para centrar dentro de la caja */
        flex-direction: column;
        align-items: center;
        justify-content: center;
        max-width: 80%;
        /* Ancho máximo para que no ocupe todo */
    }

    /* Ajustes para el icono */
    .carousel-content-box i {
        font-size: 3rem;
        /* Tamaño grande para el icono */
        margin-bottom: 0.5rem;
    }

    /* Opcional: Aumentar el tamaño del título para el impacto visual */
    .carousel-content-box h3 {
        font-size: 1.75rem;
        font-weight: bold;
        margin-bottom: 0;
        /* Elimina el margen inferior por defecto de h3 */
    }

    .carousel-content-box p.carousel-subtitle {
        font-size: 1.25rem;
        /* Tamaño más grande para impacto */
        font-weight: normal;
        color: #fff;
        /* Asegura color blanco */
        opacity: 0.9;
        /* Ligeramente transparente */
        margin-top: 5px;
        /* Pequeño espacio entre título y subtítulo */
        margin-bottom: 0;
    }

    /* Se asegura que el caption inferior se oculte si no se usa */
    .carousel-caption {
        display: none !important;
    }
</style>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-0 text-gray-800"><?php echo $saludo; ?> <?php echo $usuarioNombre; ?></h1>
        <p class="mb-0 text-muted">Bienvenido al Gestor Documental del CORE Valparaíso.</p>
    </div>
    <div class="d-none d-sm-inline-block">
        <a href="index.php?action=minutas_dashboard" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm">
            <i class="fas fa-file-alt fa-sm text-white-50 me-1"></i> Ir a Minutas
        </a>
    </div>
</div>

<div class="row g-4 mb-4">

    <div class="col-lg-8">
        <div class="card shadow mb-4 h-100">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between bg-white">
                <h6 class="m-0 fw-bold text-primary"></h6>
            </div>
            <div class="card-body p-0">
                <div id="carouselZonasRegion" class="carousel slide" data-bs-ride="carousel" data-bs-interval="5000" style="height: 350px !important;">
                    <div class="carousel-indicators">
                        <?php foreach ($imagenesZonas as $index => $imagen): ?>
                            <button type="button" data-bs-target="#carouselZonasRegion" data-bs-slide-to="<?php echo $index; ?>" class="<?php echo $index === 0 ? 'active' : ''; ?>" aria-current="<?php echo $index === 0 ? 'true' : 'false'; ?>" aria-label="Slide <?php echo $index + 1; ?>"></button>
                        <?php endforeach; ?>
                    </div>

                    <div class="carousel-inner h-100 rounded-bottom">
                        <?php foreach ($imagenesZonas as $index => $imagen): ?>
                            <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>" style="height: 350px !important;">
                                <img src="<?php echo htmlspecialchars($imagen['file']); ?>" class="d-block w-100 h-100 object-fit-cover" alt="<?php echo htmlspecialchars($imagen['title']); ?>" style="opacity: 0.7;">

                                
                                <div class="carousel-overlay">
                                    <div class="carousel-content-box">
                                        <i class="<?php echo htmlspecialchars($imagen['icon']); ?> mb-3"></i>
                                        <h3 class="mb-0"><?php echo htmlspecialchars($imagen['title']); ?></h3>

                                        <?php if (isset($imagen['subtitle'])): ?>
                                            <p class="carousel-subtitle mt-2 mb-0">
                                                <?php echo htmlspecialchars($imagen['subtitle']); ?>
                                            </p>
                                        <?php endif; ?>

                                    </div>
                                </div>
                                

                            </div>
                        <?php endforeach; ?>
                    </div>

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

    <div class="col-lg-4">
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

<div class="row g-4">

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

    <div class="col-lg-6">
        <div class="card shadow mb-4 h-100">
            <div class="card-header py-3 bg-white border-bottom-info">
                <h6 class="m-0 fw-bold text-info"><i class="far fa-calendar-alt me-2"></i> Próximas Reuniones</h6>
            </div>

            <div class="card-footer bg-white text-center border-0 pb-3">
                <a href="index.php?action=reunion_calendario" class="small text-decoration-none fw-bold text-info">
                    Ver Calendario Completo <i class="fas fa-arrow-right ms-1"></i>
                </a>
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
        </div>
    </div>

</div>

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
            if (tempEl) tempEl.innerText = '19°C'; // Valor simulado
        }, 1000);
    });
</script>