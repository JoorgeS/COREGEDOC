<?php
// views/pages/usuarios_dashboard.php
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">Gestión de Usuarios</h2>
        <a href="menu.php?pagina=usuario_crear" class="btn btn-primary">
            <i class="fas fa-user-plus me-2"></i>Registrar Nuevo Usuario
        </a>
    </div>

    <div class="card">
        <div class="card-body">
            <?php
            // Incluimos la vista que ya tienes para el listado
            include __DIR__ . '/usuarios_listado.php';
            ?>
        </div>
    </div>
</div>