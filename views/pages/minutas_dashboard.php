<?php
// views/pages/minutas_dashboard.php

// Definimos los roles aquí también para poder ocultar la "card"
if (!defined('ROL_ADMINISTRADOR')) define('ROL_ADMINISTRADOR', 1);
if (!defined('ROL_SECRETARIO')) define('ROL_SECRETARIO', 2);

$tipoUsuario = $_SESSION['tipoUsuario_id'] ?? 0;
?>

<div class="container-fluid">
    <div class="d-flex justify-content/between align-items-center mb-4">
        <h2 class="mb-0">Módulo de Minutas</h2>
    </div>

    <p class="lead text-muted mb-4">Selecciona una categoría para revisar las minutas.</p>

    <div class="row g-4">

        <?php
        // --- INICIO DE LÓGICA DE VISIBILIDAD ---
        // Mostramos esta "card" SÓLO si es Secretario o Administrador
        if ($tipoUsuario == ROL_SECRETARIO || $tipoUsuario == ROL_ADMINISTRADOR):
        ?>
        <div class="col-md-6">
            <a href="menu.php?pagina=minutas_pendientes" class="dashboard-card h-100">
                <i class="fas fa-clock text-warning"></i>
                <h5 class="mt-3">Minutas Pendientes</h5>
                <p class="mb-0 text-muted">Gestionar las minutas que requieren edición, feedback o envío.</p>
            </a>
        </div>
        <?php endif; // --- FIN DE LÓGICA DE VISIBILIDAD --- ?>


        <?php
        // --- LÓGICA PARA LA CARD DE APROBADAS ---
        // Si el usuario es Secretario, la "card" de aprobadas comparte la fila (col-md-6)
        // Si NO es Secretario, la "card" de aprobadas ocupa todo el ancho (col-md-12)
        
        $colClass = ($tipoUsuario == ROL_SECRETARIO || $tipoUsuario == ROL_ADMINISTRADOR) ? 'col-md-6' : 'col-md-12';
        ?>
        <div class="<?php echo $colClass; ?>">
            <a href="menu.php?pagina=minutas_aprobadas" class="dashboard-card h-100">
                <i class="fas fa-check-circle text-success"></i>
                <h5 class="mt-3">Minutas Aprobadas</h5>
                <p class="mb-0 text-muted">Consultar el archivo histórico de todas las minutas firmadas y finalizadas.</p>
            </a>
        </div>

    </div>
</div>