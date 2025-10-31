<?php
// views/pages/comisiones_dashboard.php
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">Módulo de Comisiones</h2>
    </div>

    <p class="lead text-muted mb-4">Selecciona una acción para gestionar las comisiones.</p>

    <div class="row g-4">

        <div class="col-md-6 col-lg-4">
            <a href="menu.php?pagina=comision_listado" class="dashboard-card h-100">
                <i class="fas fa-list"></i>
                <h5 class="mt-3">Listado de Comisiones</h5>
                <p class="mb-0 text-muted">Ver y administrar todas las comisiones existentes.</p>
            </a>
        </div>

        <div class="col-md-6 col-lg-4">
            <a href="menu.php?pagina=comision_crear" class="dashboard-card h-100">
                <i class="fas fa-plus text-primary"></i>
                <h5 class="mt-3">Crear Nueva Comisión</h5>
                <p class="mb-0 text-muted">Añadir una nueva comisión al sistema.</p>
            </a>
        </div>

    </div>
</div>