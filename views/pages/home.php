<?php
// views/pages/home.php

// NO necesitas session_start() aquí.
// NO necesitas verificar la sesión aquí (menu.php ya lo hizo).
// NO incluyas menu.php aquí.
?>

<div class="container mt-4">
    <h4>Nuestra querida Región</h4>

    <div id="carouselZonasRegion" class="carousel slide carousel-fade" data-bs-ride="carousel" data-bs-interval="5000">

        <div class="carousel-indicators">
            <button type="button" data-bs-target="#carouselZonasRegion" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Zona 1"></button>
            <button type="button" data-bs-target="#carouselZonasRegion" data-bs-slide-to="1" aria-label="Zona 2"></button>
            <button type="button" data-bs-target="#carouselZonasRegion" data-bs-slide-to="2" aria-label="Zona 3"></button>
            <button type="button" data-bs-target="#carouselZonasRegion" data-bs-slide-to="3" aria-label="Zona 4"></button>
            <button type="button" data-bs-target="#carouselZonasRegion" data-bs-slide-to="4" aria-label="Zona 5"></button>
            <button type="button" data-bs-target="#carouselZonasRegion" data-bs-slide-to="5" aria-label="Zona 6"></button>
            <button type="button" data-bs-target="#carouselZonasRegion" data-bs-slide-to="6" aria-label="Zona 7"></button>
            <button type="button" data-bs-target="#carouselZonasRegion" data-bs-slide-to="7" aria-label="Zona 8"></button>
        </div>

        <div class="carousel-inner">
            <div class="carousel-item active">
                <img src="/corevota/public/img/zonas_region/imagen_zona_1.jpg" class="d-block w-100 carousel-image-transparent" style="max-height: 400px; object-fit: cover;" alt="Descripción Zona 1">
                <div class="carousel-caption d-none d-md-block">
                    <h5>PROVINCIA DE VALPARAÍSO</h5>
                </div>
            </div>
            <div class="carousel-item">
                <img src="/corevota/public/img/zonas_region/imagen_zona_2.jpg" class="d-block w-100 carousel-image-transparent" style="max-height: 400px; object-fit: cover;" alt="Descripción Zona 2">
                <div class="carousel-caption d-none d-md-block">
                    <h5>PROVINCIA DE MARGA MARGA</h5>
                </div>
            </div>
            <div class="carousel-item">
                <img src="/corevota/public/img/zonas_region/imagen_zona_3.jpg" class="d-block w-100 carousel-image-transparent" style="max-height: 400px; object-fit: cover;" alt="Descripción Zona 3">
                <div class="carousel-caption d-none d-md-block">
                    <h5>PROVINCIA DE QUILLOTA</h5>
                </div>
            </div>
            <div class="carousel-item">
                <img src="/corevota/public/img/zonas_region/imagen_zona_4.jpg" class="d-block w-100 carousel-image-transparent" style="max-height: 400px; object-fit: cover;" alt="Descripción Zona 4">
                <div class="carousel-caption d-none d-md-block">
                    <h5>PROVINCIA DE SAN ANTONIO</h5>
                </div>
            </div>
            <div class="carousel-item">
                <img src="/corevota/public/img/zonas_region/imagen_zona_5.jpg" class="d-block w-100 carousel-image-transparent" style="max-height: 400px; object-fit: cover;" alt="Descripción Zona 5">
                <div class="carousel-caption d-none d-md-block">
                    <h5>PROVINCIA DE LOS ANDES</h5>
                </div>
            </div>
            <div class="carousel-item">
                <img src="/corevota/public/img/zonas_region/imagen_zona_6.jpg" class="d-block w-100 carousel-image-transparent" style="max-height: 400px; object-fit: cover;" alt="Descripción Zona 6">
                <div class="carousel-caption d-none d-md-block">
                    <h5>PROVINCIA DE PETORCA</h5>
                </div>
            </div>
            <div class="carousel-item">
                <img src="/corevota/public/img/zonas_region/imagen_zona_7.jpg" class="d-block w-100 carousel-image-transparent" style="max-height: 400px; object-fit: cover;" alt="Descripción Zona 7">
                <div class="carousel-caption d-none d-md-block">
                    <h5>PROVINCIA SAN FELIPE DE ACONCAGUA</h5>
                </div>
            </div>
            <div class="carousel-item">
                <img src="/corevota/public/img/zonas_region/imagen_zona_8.jpg"
                     class="d-block w-100 carousel-image-transparent"
                     style="max-height: 400px; object-fit: cover;"
                     alt="Descripción Zona 8">
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

<?php
require_once __DIR__ . '/../../class/class.conectorDB.php';
$db  = new conectorDB();
$pdo = $db->getDatabase();

/** MINUTAS: últimos 7 días, máximo 7 filas */
$sqlMinutas = "
    SELECT idMinuta, fechaMinuta, pathArchivo
    FROM t_minuta
    WHERE estadoMinuta = 'APROBADA'
      AND fechaMinuta >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
      AND fechaMinuta <= CURDATE()
    ORDER BY fechaMinuta DESC, idMinuta DESC
    LIMIT 7
";
$stmt = $pdo->query($sqlMinutas);
$minutasRecientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

/** REUNIONES: próximos 7 días, máximo 7 filas */
$sqlReuniones = "
    SELECT idReunion, nombreReunion, fechaInicioReunion
    FROM t_reunion
    WHERE DATE(fechaInicioReunion) BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ORDER BY fechaInicioReunion ASC, idReunion ASC
    LIMIT 7
";
$stmt = $pdo->query($sqlReuniones);
$reunionesProximas = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container my-4">
    <h4 class="mb-3">
        <i class="fas fa-bell text-primary me-2"></i>Novedades Recientes
    </h4>

    <?php if (empty($minutasRecientes) && empty($reunionesProximas)): ?>
        <div class="alert alert-secondary" role="alert">
            <i class="fas fa-info-circle me-2"></i>No hay novedades recientes.
        </div>
    <?php else: ?>
        <ul class="list-group shadow-sm">

            <?php if (!empty($minutasRecientes)): ?>
                <li class="list-group-item active fw-bold d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-file-alt me-2"></i>Minutas Aprobadas (últimos 7 días)</span>
                    <button id="toggleMinutasBtn" class="btn btn-sm btn-outline-light">
                        <i class="fas fa-expand-arrows-alt"></i> Agrandar
                    </button>
                </li>
                <div id="minutasContainer" class="collapse show">
                    <?php foreach ($minutasRecientes as $m): 
                        $path = trim((string)($m['pathArchivo'] ?? ''));
                        $urlPdf = $path !== '' 
                            ? (preg_match('~^https?://~i', $path) ? $path : "/corevota/{$path}")
                            : '';
                    ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span class="me-2">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                Minuta N° <?= htmlspecialchars($m['idMinuta']) ?>
                                <span class="text-muted small ms-2"><?= date('d/m/Y', strtotime($m['fechaMinuta'])) ?></span>
                            </span>

                            <?php if ($urlPdf !== ''): ?>
                                <a href="<?= htmlspecialchars($urlPdf) ?>" target="_blank" rel="noopener" class="btn btn-success btn-sm">
                                    <i class="fas fa-file-pdf"></i> Ver PDF
                                </a>
                            <?php else: ?>
                                <span class="text-muted small">Sin PDF</span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Reuniones: título y botón SIEMPRE visibles -->
            <li class="list-group-item active fw-bold d-flex justify-content-between align-items-center mt-3">
                <span><i class="fas fa-calendar-day me-2"></i>Reuniones Agendadas (próximos 7 días)</span>
                <button id="toggleReunionesBtn" class="btn btn-sm btn-outline-light">
                    <i class="fas fa-expand-arrows-alt"></i> Agrandar
                </button>
            </li>
            <div id="reunionesContainer" class="collapse show">
                <?php if (!empty($reunionesProximas)): ?>
                    <?php foreach ($reunionesProximas as $r): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>
                                <i class="fas fa-clock text-warning me-2"></i>
                                <?= htmlspecialchars($r['nombreReunion'] ?: 'Reunión sin nombre') ?>
                            </span>
                            <span class="text-muted small"><?= date('d/m/Y', strtotime($r['fechaInicioReunion'])) ?></span>
                        </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li class="list-group-item text-muted small">
                        No hay reuniones agendadas en los próximos 7 días.
                    </li>
                <?php endif; ?>
            </div>

        </ul>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const toggleMinutasBtn = document.getElementById('toggleMinutasBtn');
    const minutasContainer = document.getElementById('minutasContainer');
    let minutasExpanded = true;

    toggleMinutasBtn?.addEventListener('click', function() {
        minutasContainer.classList.toggle('show');
        minutasExpanded = !minutasExpanded;
        toggleMinutasBtn.innerHTML = minutasExpanded
            ? '<i class="fas fa-compress-arrows-alt"></i> Achicar'
            : '<i class="fas fa-expand-arrows-alt"></i> Agrandar';
    });

    const toggleReunionesBtn = document.getElementById('toggleReunionesBtn');
    const reunionesContainer = document.getElementById('reunionesContainer');
    let reunionesExpanded = true;

    toggleReunionesBtn?.addEventListener('click', function() {
        reunionesContainer.classList.toggle('show');
        reunionesExpanded = !reunionesExpanded;
        toggleReunionesBtn.innerHTML = reunionesExpanded
            ? '<i class="fas fa-compress-arrows-alt"></i> Achicar'
            : '<i class="fas fa-expand-arrows-alt"></i> Agrandar';
    });
});
</script>
