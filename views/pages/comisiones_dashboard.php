<?php
// views/pages/comisiones_dashboard.php
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">Gestión de Comisiones</h2>
        <a href="menu.php?pagina=comision_crear" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Crear Nueva Comisión
        </a>
    </div>

    <div class="card">
        <div class="card-body">
            <?php
            // Incluimos el controlador que carga el listado
            $_GET['action'] = 'list';
            include __DIR__ . '/../../controllers/ComisionController.php';
            ?>
        </div>
    </div>
</div>