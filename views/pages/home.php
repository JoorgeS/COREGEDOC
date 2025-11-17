<?php
// views/pages/home.php
// Este archivo es incluido por menu.php, por lo que las variables
// de sesión ($tipoUsuario, $idUsuarioLogueado) y las constantes ROL_...
// ya están disponibles.

// 1. CONEXIÓN A BBDD
// Es necesario instanciarla aquí, ya que menu.php no la provee globalmente.
require_once __DIR__ . '/../../class/class.conectorDB.php';
$db  = new conectorDB();
$pdo = $db->getDatabase();

// 2. PREPARAR VARIABLES
$tareas_pendientes = [];
$actividad_reciente = [];
$proximas_reuniones = [];
$minutas_recientes_aprobadas = []; // <- NUEVA VARIABLE

// Obtenemos el ID del usuario logueado desde la sesión
$idUsuarioLogueado = $_SESSION['idUsuario'] ?? 0;

try {
    // =================================================================
    // 3. OBTENER TAREAS PENDIENTES (Según el Rol)
    // =================================================================
    
    // --- TAREAS PARA PRESIDENTE DE COMISIÓN (ROL 3) ---
    if ($tipoUsuario == ROL_PRESIDENTE_COMISION) {
        // Contar minutas pendientes de firma para ESTE presidente
        $sql_firmas = "SELECT COUNT(DISTINCT m.idMinuta) 
                       FROM t_aprobacion_minuta am
                       JOIN t_minuta m ON am.t_minuta_idMinuta = m.idMinuta
                       WHERE am.t_usuario_idPresidente = :idUsuario 
                       AND am.estado_firma = 'PENDIENTE'
                       AND m.estadoMinuta IN ('PENDIENTE', 'PARCIAL')";
        $stmt_firmas = $pdo->prepare($sql_firmas);
        $stmt_firmas->execute([':idUsuario' => $idUsuarioLogueado]);
        $conteo_firmas = $stmt_firmas->fetchColumn();
        
        if ($conteo_firmas > 0) {
            $s = $conteo_firmas > 1 ? 's' : '';
            $tareas_pendientes[] = [
                'texto' => "Tienes <strong>{$conteo_firmas} minuta{$s}</strong> esperando tu firma.",
                'link'  => "menu.php?pagina=minutaPendiente",
                'icono' => "fa-file-signature",
                'color' => "danger"
            ];
        }
    }

    // --- TAREAS PARA CONSEJERO (ROL 1) O PRESIDENTE (ROL 3) ---
    if ($tipoUsuario == ROL_CONSEJERO || $tipoUsuario == ROL_PRESIDENTE_COMISION) {
        // Contar votaciones activas donde este usuario NO haya votado
        $sql_votos = "SELECT COUNT(v.idVotacion) 
                      FROM t_votacion v
                      WHERE v.habilitada = 1
                      AND NOT EXISTS (
                          SELECT 1 FROM t_voto 
                          WHERE t_votacion_idVotacion = v.idVotacion 
                          AND t_usuario_idUsuario = :idUsuario
                      )";
        $stmt_votos = $pdo->prepare($sql_votos);
        $stmt_votos->execute([':idUsuario' => $idUsuarioLogueado]);
        $conteo_votos = $stmt_votos->fetchColumn();

        if ($conteo_votos > 0) {
            $s = $conteo_votos > 1 ? 'es' : '';
            $tareas_pendientes[] = [
                'texto' => "Tienes <strong>{$conteo_votos} votacion{$s} activa{$s}</strong> pendiente{$s}.",
                'link'  => "menu.php?pagina=voto_autogestion",
                'icono' => "fa-vote-yea",
                'color' => "primary"
            ];
        }
    }
    
    // --- TAREAS PARA SECRETARIO TÉCNICO (ROL 2) ---
    if ($tipoUsuario == ROL_SECRETARIO_TECNICO) {
        // Contar minutas que requieren revisión (feedback)
        $sql_feedback = "SELECT COUNT(*) FROM t_minuta WHERE estadoMinuta = 'REQUIERE_REVISION'";
        $stmt_feedback = $pdo->query($sql_feedback);
        $conteo_feedback = $stmt_feedback->fetchColumn();

        if ($conteo_feedback > 0) {
            $s = $conteo_feedback > 1 ? 's' : '';
            $tareas_pendientes[] = [
                'texto' => "Hay <strong>{$conteo_feedback} minuta{$s}</strong> que requiere{$s} tu revisión (feedback recibido).",
                'link'  => "menu.php?pagina=minutas_pendientes",
                'icono' => "fa-comment-dots",
                'color' => "danger"
            ];
        }
        
        // Contar minutas en borrador
        $sql_borrador = "SELECT COUNT(*) FROM t_minuta WHERE estadoMinuta = 'BORRADOR'";
        $stmt_borrador = $pdo->query($sql_borrador);
        $conteo_borrador = $stmt_borrador->fetchColumn();
        
        if ($conteo_borrador > 0) {
             $s = $conteo_borrador > 1 ? 's' : '';
             $tareas_pendientes[] = [
                'texto' => "Tienes <strong>{$conteo_borrador} minuta{$s} en borrador</strong> lista{$s} para enviar.",
                'link'  => "menu.php?pagina=minutas_pendientes",
                'icono' => "fa-pencil-alt",
                'color' => "info"
            ];
        }
    }
    
    // =================================================================
    // 4. OBTENER ACTIVIDAD RECIENTE o MINUTAS APROBADAS (Según Rol)
    // =================================================================
    
    // --- ✅ INICIO DE LA MODIFICACIÓN ---
    if ($tipoUsuario == ROL_CONSEJERO) { 
        // --- Para Consejero: Mostrar Minutas Aprobadas Recientemente ---
        $sql_minutas_recientes = "SELECT idMinuta, fechaAprobacion, pathArchivo, nombreMinuta 
                                  FROM t_minuta
                                  WHERE estadoMinuta = 'APROBADA'
                                    AND fechaAprobacion >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                                  ORDER BY fechaAprobacion DESC
                                  LIMIT 5";
        $stmt_minutas_recientes = $pdo->query($sql_minutas_recientes);
        $minutas_recientes_aprobadas = $stmt_minutas_recientes->fetchAll(PDO::FETCH_ASSOC);
    
    } else {
        // --- Para otros roles (Admin, ST): Mostrar Timeline de Actividad ---
        $sql_actividad = "SELECT 
                              s.fecha_hora, 
                              s.accion, 
                              s.detalle, 
                              COALESCE(TRIM(CONCAT(u.pNombre, ' ', u.aPaterno)), 'Sistema') as usuario_nombre
                          FROM 
                              t_minuta_seguimiento s
                          LEFT JOIN 
                              t_usuario u ON s.t_usuario_idUsuario = u.idUsuario
                          ORDER BY 
                              s.fecha_hora DESC
                          LIMIT 5";
        $stmt_actividad = $pdo->query($sql_actividad);
        $actividad_reciente = $stmt_actividad->fetchAll(PDO::FETCH_ASSOC);
    }
    // --- ✅ FIN DE LA MODIFICACIÓN ---

    // =================================================================
    // 5. OBTENER PRÓXIMAS REUNIONES (Corregido)
    // =================================================================
    $sql_reuniones = "SELECT 
                          r.idReunion, r.nombreReunion, r.fechaInicioReunion, 
                          c.nombreComision
                      FROM t_reunion r
                      LEFT JOIN t_comision c ON r.t_comision_idComision = c.idComision
                      WHERE r.fechaInicioReunion >= NOW()
                      ORDER BY r.fechaInicioReunion ASC
                      LIMIT 3";
                      
    $stmt_reuniones = $pdo->query($sql_reuniones);
    $proximas_reuniones = $stmt_reuniones->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    // Manejar el error de forma silenciosa en el dashboard
    error_log("Error al cargar datos del dashboard home.php: " . $e->getMessage());
    echo "<div class='alert alert-danger'>Error al cargar el dashboard: " . $e->getMessage() . "</div>";
}
?>

<style>
    /* Estilos para el Timeline de Actividad Reciente */
    .timeline {
        list-style: none;
        padding: 0;
        position: relative;
    }
    .timeline:before {
        content: '';
        position: absolute;
        top: 5px;
        bottom: 5px;
        left: 20px;
        width: 2px;
        background-color: #e9ecef;
    }
    .timeline-item {
        margin-bottom: 20px;
        position: relative;
    }
    .timeline-icon {
        position: absolute;
        left: 0;
        top: 0;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background-color: #fff;
        border: 2px solid #e9ecef;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        z-index: 1;
    }
    .timeline-content {
        margin-left: 60px;
        padding-top: 5px;
    }
    .timeline-content .time {
        font-size: 0.8rem;
        color: #6c757d;
    }
    .timeline-content p {
        margin-bottom: 0;
        font-size: 0.95rem;
    }
    
    /* Estilos para que el Carrusel sea semi-transparente */
    .carousel-image-transparent {
        opacity: 0.7;
    }
    .carousel-caption h5 {
        font-weight: bold;
        text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.7);
    }
</style>

<div class="container mt-4">
    <h4>Nuestra querida quinta región</h4>

    <div id="carouselZonasRegion" class="carousel slide carousel-fade" data-bs-ride="carousel" data-bs-interval="5000">

        <div class="carousel-indicators">
            <button type="button" data-bs-target="#carouselZonasRegion" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Zona 3"></button>
            <button type="button" data-bs-target="#carouselZonasRegion" data-bs-slide-to="1" aria-label="Zona 2"></button>
            <button type="button" data-bs-target="#carouselZonasRegion" data-bs-slide-to="2" aria-label="Zona 1"></button>
            <button type="button" data-bs-target="#carouselZonasRegion" data-bs-slide-to="3" aria-label="Zona 4"></button>
            <button type="button" data-bs-target="#carouselZonasRegion" data-bs-slide-to="4" aria-label="Zona 5"></button>
            <button type="button" data-bs-target="#carouselZonasRegion" data-bs-slide-to="5" aria-label="Zona 6"></button>
            <button type="button" data-bs-target="#carouselZonasRegion" data-bs-slide-to="6" aria-label="Zona 7"></button>
            <button type="button" data-bs-target="#carouselZonasRegion" data-bs-slide-to="7" aria-label="Zona 8"></button>
        </div>

        <div class="carousel-inner rounded shadow-sm">
            <div class="carousel-item active">
                <img src="/corevota/public/img/zonas_region/imagen_zona_3.jpg" class="d-block w-100 carousel-image-transparent" style="max-height: 400px; object-fit: cover;" alt="PROVINCIA DE VALPARAÍSO">
                <div class="carousel-caption d-none d-md-block">
                    <h5>PROVINCIA DE QUILLOTA</h5>
                </div>
            </div>
            <div class="carousel-item">
                <img src="/corevota/public/img/zonas_region/imagen_zona_2.jpg" class="d-block w-100 carousel-image-transparent" style="max-height: 400px; object-fit: cover;" alt="PROVINCIA DE MARGA MARGA">
                <div class="carousel-caption d-none d-md-block">
                    <h5>PROVINCIA DE MARGA MARGA</h5>
                </div>
            </div>
            <div class="carousel-item">
                <img src="/corevota/public/img/zonas_region/imagen_zona_1.jpg" class="d-block w-100 carousel-image-transparent" style="max-height: 400px; object-fit: cover;" alt="PROVINCIA DE QUILLOTA">
                <div class="carousel-caption d-none d-md-block">
                    <h5>PROVINCIA DE VALPARAÍSO</h5>
                </div>
            </div>
            <div class="carousel-item">
                <img src="/corevota/public/img/zonas_region/imagen_zona_4.jpg" class="d-block w-100 carousel-image-transparent" style="max-height: 400px; object-fit: cover;" alt="PROVINCIA DE SAN ANTONIO">
                <div class="carousel-caption d-none d-md-block">
                    <h5>PROVINCIA DE SAN ANTONIO</h5>
                </div>
            </div>
            <div class="carousel-item">
                <img src="/corevota/public/img/zonas_region/imagen_zona_5.jpg" class="d-block w-100 carousel-image-transparent" style="max-height: 400px; object-fit: cover;" alt="PROVINCIA DE LOS ANDES">
                <div class="carousel-caption d-none d-md-block">
                    <h5>PROVINCIA DE LOS ANDES</h5>
                </div>
            </div>
            <div class="carousel-item">
                <img src="/corevota/public/img/zonas_region/imagen_zona_6.jpg" class="d-block w-100 carousel-image-transparent" style="max-height: 400px; object-fit: cover;" alt="PROVINCIA DE PETORCA">
                <div class="carousel-caption d-none d-md-block">
                    <h5>PROVINCIA DE PETORCA</h5>
                </div>
            </div>
            <div class="carousel-item">
                <img src="/corevota/public/img/zonas_region/imagen_zona_7.jpg" class="d-block w-100 carousel-image-transparent" style="max-height: 400px; object-fit: cover;" alt="PROVINCIA SAN FELIPE DE ACONCAGUA">
                <div class="carousel-caption d-none d-md-block">
                    <h5>PROVINCIA SAN FELIPE DE ACONCAGUA</h5>
                </div>
            </div>
            <div class="carousel-item">
                <img src="/corevota/public/img/zonas_region/imagen_zona_8.jpg"
                     class="d-block w-100 carousel-image-transparent"
                     style="max-height: 400px; object-fit: cover;"
                     alt="PROVINCIA DE ISLA DE PASCUA">
                <div class="carousel-caption d-none d-md-block">
                    <h5>PROVINCIA DE ISLA DE PASCUA</h5>
                </div>
            </div>
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

<hr>

<div class="container my-4">
    <div class="row g-4">
        
        <div class="col-lg-8">

            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white border-0 pt-3">
                    <h5 class="mb-0 fw-bold"><i class="fas fa-tasks me-2 text-primary"></i> Mis Tareas Pendientes</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($tareas_pendientes)): ?>
                        <div class="text-center p-3">
                            <i class="fas fa-check-circle fa-3x text-success mb-2"></i>
                            <h5 class="mb-0">¡Excelente!</h5>
                            <p class="text-muted">No tienes tareas pendientes.</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($tareas_pendientes as $tarea): ?>
                                <a href="<?php echo $tarea['link']; ?>" class="list-group-item list-group-item-action d-flex align-items-center p-3">
                                    <i class="fas <?php echo $tarea['icono']; ?> fa-fw fa-lg text-<?php echo $tarea['color']; ?> me-3"></i>
                                    <div>
                                        <?php echo $tarea['texto']; ?>
                                        <small class="d-block text-primary fw-bold">Ir a la tarea &rarr;</small>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($tipoUsuario == ROL_CONSEJERO): ?>
                <div class="card shadow-sm">
                    <div class="card-header bg-white border-0 pt-3">
                        <h5 class="mb-0 fw-bold"><i class="fas fa-file-check me-2 text-primary"></i> Minutas Aprobadas (Últimos 7 días)</h5>
                    </div>
                    <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                        <?php if (empty($minutas_recientes_aprobadas)): ?>
                            <p class="text-muted text-center p-3">No hay minutas aprobadas recientemente.</p>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($minutas_recientes_aprobadas as $minuta): 
                                    // Aseguramos que la ruta al PDF sea correcta
                                    $urlPdf = "/corevota/" . ltrim(htmlspecialchars($minuta['pathArchivo']), '/');
                                ?>
                                    <a href="<?php echo $urlPdf; ?>" target="_blank" class="list-group-item list-group-item-action p-3">
                                        <strong>Minuta N° <?php echo $minuta['idMinuta']; ?>: <?php echo htmlspecialchars($minuta['nombreMinuta'] ?? 'Ver Minuta'); ?></strong>
                                        <small class="d-block text-muted">
                                            Aprobada el: <?php echo htmlspecialchars(date('d-m-Y', strtotime($minuta['fechaAprobacion']))); ?>
                                        </small>
                                        <small class="d-block text-success fw-bold mt-1">
                                            <i class="fas fa-file-pdf me-1"></i> Ver PDF
                                        </small>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            <?php else: ?>
                <div class="card shadow-sm">
                    <div class="card-header bg-white border-0 pt-3">
                        <h5 class="mb-0 fw-bold"><i class="fas fa-history me-2 text-primary"></i> Actividad Reciente</h5>
                    </div>
                    <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                        <?php if (empty($actividad_reciente)): ?>
                            <p class="text-muted text-center p-3">No hay actividad reciente en el sistema.</p>
                        <?php else: ?>
                            <ul class="timeline">
                                <?php foreach ($actividad_reciente as $actividad): 
                                    $icono_actividad = 'fa-info-circle'; // Default
                                    if (str_contains(strtolower($actividad['accion']), 'firm')) $icono_actividad = 'fa-file-signature text-success';
                                    if (str_contains(strtolower($actividad['accion']), 'enviad')) $icono_actividad = 'fa-paper-plane text-primary';
                                    if (str_contains(strtolower($actividad['accion']), 'cread')) $icono_actividad = 'fa-plus-circle text-muted';
                                    if (str_contains(strtolower($actividad['accion']), 'valid')) $icono_actividad = 'fa-check-circle text-success';
                                    if (str_contains(strtolower($actividad['accion']), 'feedback')) $icono_actividad = 'fa-comment-dots text-danger';
                                    if (str_contains(strtolower($actividad['accion']), 'eliminad')) $icono_actividad = 'fa-trash text-danger';
                                ?>
                                    <li class="timeline-item">
                                        <div class="timeline-icon bg-light">
                                            <i class="fas <?php echo $icono_actividad; ?>"></i>
                                        </div>
                                        <div class="timeline-content">
                                            <p>
                                                <strong><?php echo htmlspecialchars($actividad['usuario_nombre']); ?></strong> 
                                                <?php echo htmlspecialchars($actividad['detalle']); ?>
                                            </p>
                                            <span class="time"><i class="fas fa-clock"></i> 
                                                <?php echo htmlspecialchars(date('d-m-Y H:i', strtotime($actividad['fecha_hora']))); ?> hrs.
                                            </span>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            </div> <div class="col-lg-4">
            
            <div class="card shadow-sm">
                <div class="card-header bg-white border-0 pt-3">
                    <h5 class="mb-0 fw-bold"><i class="fas fa-calendar-alt me-2 text-primary"></i> Próximas Reuniones</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($proximas_reuniones)): ?>
                        <p class="text-muted text-center p-3">No hay reuniones programadas.</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($proximas_reuniones as $reunion): ?>
                                <a href="menu.php?pagina=sala_reuniones" class="list-group-item list-group-item-action p-3">
                                    <strong><?php echo htmlspecialchars($reunion['nombreReunion']); ?></strong>
                                    <small class="d-block text-muted">
                                        <?php echo htmlspecialchars($reunion['nombreComision'] ?? 'Comisión no especificada'); ?>
                                    </small>
                                    <small class="d-block text-primary fw-bold mt-1">
                                        <i class="fas fa-clock me-1"></i>
                                        <?php echo htmlspecialchars(date('d-m-Y \a \l\a\s H:i', strtotime($reunion['fechaInicioReunion']))); ?> hrs.
                                    </small>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <div class="d-grid mt-3">
                        <a href="menu.php?pagina=reunion_calendario" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-calendar-alt me-1"></i> Ver Calendario Completo
                        </a>
                    </div>
                </div>
            </div>
        </div>

    </div> </div> <div class="container my-4">
    <hr class="mb-4">
    <h2 class="h5 fw-bold mb-3"><i class="fas fa-rocket me-2 text-primary"></i> Acciones Rápidas</h2>
    <div class="row g-3">
        
        <?php
        // La visibilidad de estas tarjetas depende del ROL del usuario ($tipoUsuario)
        ?>

        <?php if ($tipoUsuario == ROL_SECRETARIO_TECNICO || $tipoUsuario == ROL_ADMINISTRADOR): ?>
        <div class="col-xl-3 col-md-6 mb-3">
            <a class="dashboard-card h-100" href="menu.php?pagina=minutas_dashboard">
                <i class="fas fa-file-alt"></i>
                <h5>Gestión de Minutas</h5>
            </a>
        </div>
        <?php endif; ?>

        <?php if ($tipoUsuario == ROL_CONSEJERO || $tipoUsuario == ROL_PRESIDENTE_COMISION): ?>
        <div class="col-xl-3 col-md-6 mb-3">
            <a class="dashboard-card h-100" href="menu.php?pagina=minutas_aprobadas">
                <i class="fa-solid fa-file-pdf"></i>
                <h5>Ver Minutas Aprobadas</h5>
            </a>
        </div>
        <?php endif; ?>

        <?php if ($tipoUsuario == ROL_SECRETARIO_TECNICO || $tipoUsuario == ROL_ADMINISTRADOR): ?>
        <div class="col-xl-3 col-md-6 mb-3">
            <a class="dashboard-card h-100" href="menu.php?pagina=reuniones_dashboard">
                <i class="fas fa-calendar-plus"></i>
                <h5>Gestión de Reuniones</h5>
            </a>
        </div>
        <?php endif; ?>

        <?php if ($tipoUsuario == ROL_CONSEJERO || $tipoUsuario == ROL_PRESIDENTE_COMISION || $tipoUsuario == ROL_ADMINISTRADOR): ?>
        <div class="col-xl-3 col-md-6 mb-3">
            <a class="dashboard-card h-100" href="menu.php?pagina=sala_reuniones">
                <i class="fas fa-chalkboard-teacher"></i>
                <h5>Sala de Reuniones</h5>
            </a>
        </div>
        <?php endif; ?>

        <?php if ($tipoUsuario == ROL_SECRETARIO_TECNICO || $tipoUsuario == ROL_ADMINISTRADOR): ?>
        <div class="col-xl-3 col-md-6 mb-3">
            <a class="dashboard-card h-100" href="menu.php?pagina=votaciones_dashboard">
                <i class="fas fa-tasks"></i>
                <h5>Gestión de Votaciones</h5>
            </a>
        </div>
        <?php endif; ?>

        <?php if ($tipoUsuario == ROL_CONSEJERO || $tipoUsuario == ROL_PRESIDENTE_COMISION): ?>
        <div class="col-xl-3 col-md-6 mb-3">
            <a class="dashboard-card h-100" href="menu.php?pagina=voto_autogestion">
                <i class="fa-solid fa-person-booth me-2"></i>
                <h5>Sala de Votaciones</h5>
            </a>
        </div>
        <?php endif; ?>
        
        <?php if ($tipoUsuario == ROL_SECRETARIO_TECNICO || $tipoUsuario == ROL_ADMINISTRADOR): ?>
        <div class="col-xl-3 col-md-6 mb-3">
            <a class="dashboard-card h-100" href="menu.php?pagina=comisiones_dashboard">
                <i class="fas fa-landmark"></i>
                <h5>Gestión de Comisiones</h5>
            </a>
        </div>
        <?php endif; ?>

        <?php if ($tipoUsuario == ROL_ADMINISTRADOR): ?>
        <div class="col-xl-3 col-md-6 mb-3">
            <a class="dashboard-card h-100" href="menu.php?pagina=usuarios_dashboard">
                <i class="fas fa-users-cog"></i>
                <h5>Gestión de Usuarios</h5>
            </a>
        </div>
        <?php endif; ?>
        
    </div>
</div>