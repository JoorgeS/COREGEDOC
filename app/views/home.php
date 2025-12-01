<?php
// --- INICIO DE SESIÓN Y DATOS DEL USUARIO ---
$nombreUser = htmlspecialchars($_SESSION['pNombre'] ?? 'Consejero');
$apellidoUser = htmlspecialchars($_SESSION['aPaterno'] ?? '');
$rolUser = $_SESSION['tipoUsuario_id'] ?? 0;
$fotoPerfil = !empty($_SESSION['rutaImagenPerfil']) && file_exists($_SESSION['rutaImagenPerfil']) 
    ? $_SESSION['rutaImagenPerfil'] 
    : 'public/img/user_placeholder.png';

// Rol en texto
$nombreRol = 'Usuario';
if ($rolUser == 1) $nombreRol = 'Consejero Regional';
if ($rolUser == 2) $nombreUser = 'Secretario Técnico';
if ($rolUser == 3) $nombreRol = 'Presidente de Comisión';
?>

<style>
    /* Paleta Institucional */
    .c-naranja-dark { color: #e87b00 !important; }
    .bg-naranja-dark { background-color: #e87b00 !important; }
    
    .c-naranja { color: #f7931e !important; }
    .bg-naranja { background-color: #f7931e !important; }
    
    .c-verde { color: #00a650 !important; }
    .bg-verde { background-color: #00a650 !important; }
    .border-verde { border-color: #00a650 !important; }

    .c-azul { color: #0071bc !important; }
    .bg-azul { background-color: #0071bc !important; }
    
    .c-gris { color: #808080 !important; }
    
    /* Ajustes generales */
    .card-hover:hover { transition: transform 0.2s, box-shadow 0.2s; }
    .card-hover:hover { transform: translateY(-3px); box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important; }

    /* --- ESTILOS DEL CARRUSEL --- */
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
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        z-index: 10;
        text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.7);
    }

    /* Contenedor del icono y el título para el fondo gris transparente */
    .carousel-content-box {
        background-color: rgba(90, 90, 90, 0.7); /* Transparencia del cuadro de texto */
        backdrop-filter: blur(2px);
        padding: 20px 30px;
        border-radius: 10px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        max-width: 80%;
    }

    /* Ajustes para el icono usando la paleta Naranja Claro */
    .carousel-content-box i {
        font-size: 3rem;
        margin-bottom: 0.5rem;
        color: #f7931e; /* Naranja Claro */
    }

    /* Opcional: Aumentar el tamaño del título para el impacto visual */
    .carousel-content-box h3 {
        font-size: 1.75rem;
        font-weight: bold;
        margin-bottom: 0;
    }

    .carousel-content-box p.carousel-subtitle {
        font-size: 1.25rem;
        font-weight: normal;
        color: #fff;
        opacity: 0.9;
        margin-top: 5px;
        margin-bottom: 0;
    }

    /* Se asegura que el caption inferior se oculte si no se usa */
    .carousel-caption {
        display: none !important;
    }
</style>

<div class="container-fluid p-4">

    <div class="row mb-4 align-items-stretch">
        
        <div class="col-lg-8 mb-3 mb-lg-0">
            <div class="card shadow-sm border-0 h-100 overflow-hidden">
                <div class="card-body p-0 d-flex align-items-center bg-white">
                    <div class="bg-azul h-100" style="width: 10px;"></div>
                    
                    <div class="p-4 d-flex align-items-center w-100">
                        <div class="me-4 flex-shrink-0">
                            <img src="<?php echo $fotoPerfil; ?>" 
                                 alt="Foto Perfil" 
                                 class="rounded shadow border border-3 border-light"
                                 style="height: 110px; width: auto; max-width: 100%;">
                        </div>
                        
                        <div>
                            <h2 class="fw-bold text-dark mb-1">
                                <!-- CORRECCIÓN AQUÍ: Usamos solo la variable saludo del controlador -->
                                <?php echo $data['saludo']; ?>
                            </h2>
                            <span class="badge bg-light c-azul border border-primary rounded-pill px-3 py-2">
                                <i class="fas fa-user-tie me-1"></i> <?php echo $nombreRol; ?>
                            </span>
                            <p class="c-gris mt-3 mb-0 small">
                                <i class="far fa-clock me-1"></i> Último acceso: <?php echo date('d/m/Y H:i'); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm border-0 h-100 bg-white">
                <div class="card-body d-flex flex-column justify-content-center align-items-center">
                    
                    <h5 class="fw-bold mb-0 text-uppercase ls-1 c-gris">
                        Valparaíso
                    </h5>
                    
                    <div class="d-flex align-items-center justify-content-center my-3">
                        <i class="fas fa-sun fa-3x me-3 c-naranja"></i>
                        <span class="display-4 fw-bold text-dark">18°</span>
                    </div>

                    <div class="d-flex align-items-center small c-gris">
                        <span class="me-3"><i class="fas fa-wind me-1"></i> 12 km/h</span>
                        <span><i class="fas fa-tint me-1"></i> 45%</span>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <div class="row mb-5">
        <div class="col-12">
            <div class="card shadow border-0 overflow-hidden">
                <div class="card-header bg-white border-0 py-3">
                     <h5 class="mb-0 fw-bold c-azul"><i class="fas fa-map-marked-alt me-2"></i>Ejes de Gestión Regional</h5>
                </div>
                <div class="card-body p-0">
                    <div id="carouselZonas" class="carousel slide" data-bs-interval="5000" style="height: 400px;">
                        
                        <div class="carousel-indicators">
                            <?php foreach ($data['imagenes_zonas'] as $index => $zona): ?>
                                <button type="button" data-bs-target="#carouselZonas" data-bs-slide-to="<?php echo $index; ?>" 
                                        class="<?php echo $index === 0 ? 'active' : ''; ?>" aria-current="<?php echo $index === 0 ? 'true' : 'false'; ?>" aria-label="Slide <?php echo $index + 1; ?>"></button>
                            <?php endforeach; ?>
                        </div>

                        <div class="carousel-inner h-100 rounded-bottom">
                            <?php foreach ($data['imagenes_zonas'] as $index => $zona): ?>
                                <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>" style="height: 400px !important;">
                                    
                                    <img src="<?php echo htmlspecialchars($zona['file']); ?>" class="d-block w-100 h-100 object-fit-cover" alt="<?php echo htmlspecialchars($zona['title']); ?>" style="opacity: 0.8;">

                                    <div class="carousel-overlay">
                                        <div class="carousel-content-box">
                                            <i class="<?php echo htmlspecialchars($zona['icon']); ?> mb-3"></i>
                                            <h3 class="mb-0"><?php echo htmlspecialchars($zona['title']); ?></h3>

                                            <?php if (isset($zona['subtitle'])): ?>
                                                <p class="carousel-subtitle mt-2 mb-0">
                                                    <?php echo htmlspecialchars($zona['subtitle']); ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <button class="carousel-control-prev" type="button" data-bs-target="#carouselZonas" data-bs-slide="prev">
                            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">Anterior</span>
                        </button>
                        <button class="carousel-control-next" type="button" data-bs-target="#carouselZonas" data-bs-slide="next">
                            <span class="carousel-control-next-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">Siguiente</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        
        <div class="col-md-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold c-naranja-dark">
                        <i class="fas fa-exclamation-circle me-2"></i>Tareas Pendientes
                    </h5>
                    <span class="badge bg-naranja-dark rounded-pill"><?php echo count($data['tareas_pendientes']); ?></span>
                </div>
                <div class="card-body">
                    <?php if (empty($data['tareas_pendientes'])): ?>
                        <div class="text-center py-5 c-gris">
                            <i class="fas fa-check-circle fa-3x mb-3 c-verde opacity-50"></i>
                            <p class="mb-0">¡Excelente! No tienes tareas pendientes.</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($data['tareas_pendientes'] as $tarea): ?>
                                <a href="<?php echo $tarea['link']; ?>" class="list-group-item list-group-item-action d-flex gap-3 py-3 border-0 border-bottom" aria-current="true">
                                    <div class="d-flex gap-2 w-100 justify-content-between">
                                        <div>
                                            <div class="mb-1 text-dark">
                                                <i class="fas <?php echo $tarea['icono']; ?> c-naranja-dark me-2"></i>
                                                <?php echo $tarea['texto']; ?>
                                            </div>
                                            <small class="c-gris">Acción requerida inmediatamente</small>
                                        </div>
                                        <small class="opacity-50 text-nowrap"><i class="fas fa-chevron-right"></i></small>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold c-azul">
                        <i class="fas fa-calendar-alt me-2"></i>Próximas Reuniones
                    </h5>

                </div>
                <div class="card-body p-0">
                    <?php if (empty($data['proximas_reuniones'])): ?>
                        <div class="text-center py-5 c-gris">
                            <i class="far fa-calendar-times fa-3x mb-3 opacity-25"></i>
                            <p class="mb-0">No hay reuniones programadas próximamente.</p>
                        </div>
                    <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($data['proximas_reuniones'] as $reunion): 
                                // Mantenemos $fecha solo para la hora, pero el mes/día viene del controlador
                                $fecha = new DateTime($reunion['fechaInicioReunion']);
                            ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                                    <div class="d-flex align-items-center">
                                        <div class="text-center bg-light rounded p-2 me-3 border border-verde" style="min-width: 60px;">
                                            <!-- CORRECCIÓN AQUÍ: Usamos 'mes_esp' del controlador -->
                                            <div class="small text-uppercase fw-bold c-verde">
                                                <?php echo $reunion['mes_esp'] ?? $fecha->format('M'); ?>
                                            </div>
                                            <!-- CORRECCIÓN AQUÍ: Usamos 'dia_fmt' del controlador -->
                                            <div class="h4 mb-0 fw-bold text-dark">
                                                <?php echo $reunion['dia_fmt'] ?? $fecha->format('d'); ?>
                                            </div>
                                        </div>
                                        <div>
                                            <h6 class="mb-1 fw-bold text-dark"><?php echo htmlspecialchars($reunion['nombreReunion']); ?></h6>
                                            <small class="c-gris">
                                                <i class="far fa-clock me-1"></i> <?php echo $fecha->format('H:i'); ?> hrs
                                                &nbsp;|&nbsp; 
                                                <i class="fas fa-users me-1"></i> <?php echo htmlspecialchars($reunion['nombreComision']); ?>
                                            </small>
                                        </div>
                                    </div>
                                    <a href="index.php?action=reunion_gestionar&id=<?php echo $reunion['idReunion']; ?>" class="btn btn-sm btn-light c-azul">
                                        <i class="fas fa-external-link-alt"></i>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
    // Usamos window.onload para asegurar que todos los elementos HTML se hayan cargado.
    window.onload = function() {
        const carouselElement = document.getElementById('carouselZonas');
        
        // Verificamos si la clase Carousel de Bootstrap existe
        if (carouselElement && typeof bootstrap !== 'undefined' && bootstrap.Carousel) {
            
            // Inicialización explícita, que ahora es la ÚNICA inicialización.
            const carousel = new bootstrap.Carousel(carouselElement, {
                interval: 5000, // Ajusta el intervalo automático
                ride: false // Desactiva la auto-reproducción si el usuario interactúa
            });
            console.log("Carrusel inicializado por script, listo para interacción manual.");
        }
    };
</script>