<?php
// views/pages/minutas_dashboard.php
// Este archivo ahora actúa como un panel de navegación para el módulo de Minutas.
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">Módulo de Minutas</h2>
        </div>

    <p class="lead text-muted mb-4">Selecciona una categoría para revisar las minutas.</p>

    <div class="row g-4">

        <div class="col-md-6">
            <a href="menu.php?pagina=minutas_pendientes" class="dashboard-card h-100">
                <i class="fas fa-clock text-warning"></i>
                <h5 class="mt-3">Minutas Pendientes</h5>
                <p class="mb-0 text-muted">Revisar, firmar y gestionar las minutas que requieren aprobación.</p>
            </a>
        </div>

        <div class="col-md-6">
            <a href="menu.php?pagina=minutas_aprobadas" class="dashboard-card h-100">
                <i class="fas fa-check-circle text-success"></i>
                <h5 class="mt-3">Minutas Aprobadas</h5>
                <p class="mb-0 text-muted">Consultar el archivo histórico de todas las minutas firmadas y finalizadas.</p>
            </a>
        </div>

    </div>
</div>