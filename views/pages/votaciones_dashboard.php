<?php
// views/pages/votaciones_dashboard.php
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">Módulo de Votaciones</h2>
        </div>

    <div class="row g-4">
        <div class="col-md-6 col-lg-4">
            <a href="menu.php?pagina=votacion_listado" class="dashboard-card">
                <i class="fas fa-list-check"></i>
                <h5>Votaciones Activas</h5>
                <p class="mb-0 text-muted">Ver todas las votaciones disponibles.</p>
            </a>
        </div>

        <div class="col-md-6 col-lg-4">
            <a href="menu.php?pagina=voto_autogestion" class="dashboard-card">
                <i class="fas fa-check-to-slot"></i>
                <h5>Registrar Mi Votación</h5>
                <p class="mb-0 text-muted">Emitir mi voto en una votación activa.</p>
            </a>
        </div>

        <div class="col-md-6 col-lg-4">
            <a href="menu.php?pagina=historial_votacion" class="dashboard-card">
                <i class="fas fa-clipboard-list"></i>
                <h5>Mi Historial de Votación</h5>
                <p class="mb-0 text-muted">Revisar mis votaciones pasadas.</p>
            </a>
        </div>
    </div>
</div>