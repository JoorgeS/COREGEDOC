<?php
// views/pages/votaciones_dashboard.php
?>

<div class="container-fluid">

    <nav aria-label="breadcrumb" class="mb-2">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="menu.php?pagina=home">Home</a></li>
            <li class="breadcrumb-item active" aria-current="page">Gestión de Votaciones</li>
        </ol>
    </nav>


    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">Módulo de Votaciones</h2>
    </div>

    <p class="lead text-muted mb-4">Selecciona una acción para gestionar las votaciones.</p>

    <div class="row g-4">
        <div class="col-md-6 col-lg-4">
            <a href="menu.php?pagina=votacion_listado" class="dashboard-card h-100">
                <i class="fas fa-list-check"></i>
                <h5 class="mt-3">Votaciones Activas</h5>
                <p class="mb-0 text-muted">Ver todas las votaciones disponibles.</p>
            </a>
        </div>

        <div class="col-md-6 col-lg-4">
            <a href="menu.php?pagina=voto_autogestion" class="dashboard-card h-100">
                <i class="fas fa-check-to-slot text-success"></i>
                <h5 class="mt-3">Registrar Mi Votación</h5>
                <p class="mb-0 text-muted">Emitir mi voto en una votación activa.</p>
            </a>
        </div>

        <div class="col-md-6 col-lg-4">
            <a href="menu.php?pagina=historial_votacion" class="dashboard-card h-100">
                <i class="fas fa-clipboard-list"></i>
                <h5 class="mt-3">Mi Historial de Votación</h5>
                <p class="mb-0 text-muted">Revisar mis votaciones pasadas.</p>
            </a>
        </div>
    </div>
</div>