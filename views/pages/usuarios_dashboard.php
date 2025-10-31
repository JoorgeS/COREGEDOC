<?php
// views/pages/usuarios_dashboard.php
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">Módulo de Usuarios</h2>
    </div>

    <p class="lead text-muted mb-4">Selecciona una acción para gestionar los usuarios del sistema.</p>

    <div class="row g-4">

        <div class="col-md-6 col-lg-4">
            <a href="menu.php?pagina=usuarios_listado" class="dashboard-card h-100">
                <i class="fas fa-list"></i>
                <h5 class="mt-3">Listado de Usuarios</h5>
                <p class="mb-0 text-muted">Ver, editar y administrar todos los usuarios del sistema.</p>
            </a>
        </div>

        <div class="col-md-6 col-lg-4">
            <a href="menu.php?pagina=usuario_crear" class="dashboard-card h-100">
                <i class="fas fa-user-plus text-primary"></i>
                <h5 class="mt-3">Registrar Nuevo Usuario</h5>
                <p class="mb-0 text-muted">Añadir un nuevo consejero o administrador al sistema.</p>
            </a>
        </div>

    </div>
</div>