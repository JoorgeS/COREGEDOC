<?php
// views/pages/reuniones_dashboard.php
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">Módulo de Reuniones</h2>
        <a href="menu.php?pagina=reunion_crear" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Crear Nueva Reunión
        </a>
    </div>

    <div class="row g-4">
        <div class="col-md-6 col-lg-4">
            <a href="menu.php?pagina=reunion_listado" class="dashboard-card">
                <i class="fas fa-list"></i>
                <h5>Listado de Reuniones</h5>
                <p class="mb-0 text-muted">Ver y administrar todas las reuniones.</p>
            </a>
        </div>

        <div class="col-md-6 col-lg-4">
            <a href="menu.php?pagina=reunion_calendario" class="dashboard-card">
                <i class="fas fa-calendar-alt"></i>
                <h5>Vista Calendario</h5>
                <p class="mb-0 text-muted">Ver reuniones en el calendario.</p>
            </a>
        </div>

        <div class="col-md-6 col-lg-4">
            <a href="menu.php?pagina=reunion_autogestion_asistencia" class="dashboard-card">
                <i class="fas fa-hand-pointer"></i>
                <h5>Registrar Mi Asistencia</h5>
                <p class="mb-0 text-muted">Marcar asistencia a una reunión activa.</p>
            </a>
        </div>

        <div class="col-md-6 col-lg-4">
            <a href="menu.php?pagina=historial_asistencia" class="dashboard-card">
                <i class="fas fa-clipboard-list"></i>
                <h5>Mi Historial de Asistencia</h5>
                <p class="mb-0 text-muted">Revisar mi historial personal.</p>
            </a>
        </div>
    </div>
</div>