<?php
// views/pages/minutas_dashboard.php

// Definimos los roles para controlar la visibilidad
if (!defined('ROL_ADMINISTRADOR')) define('ROL_ADMINISTRADOR', 6);
if (!defined('ROL_SECRETARIO_TECNICO')) define('ROL_SECRETARIO_TECNICO', 2);
if (!defined('ROL_PRESIDENTE_COMISION')) define('ROL_PRESIDENTE_COMISION', 3);

$tipoUsuario = $_SESSION['tipoUsuario_id'] ?? 0;
?>

<div class="container-fluid">
    <nav aria-label="breadcrumb" class="mb-2">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="menu.php?pagina=home">Home</a></li>
            <li class="breadcrumb-item active" aria-current="page">Módulo de Minutas</li>
        </ol>
    </nav>


    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">Módulo de Minutas</h2>
    </div>

    <p class="lead text-muted mb-4">Selecciona una categoría para revisar las minutas.</p>

    <div class="row g-4">

        <?php
        // --- INICIO DE LÓGICA DE VISIBILIDAD (CORREGIDA) ---
        // Mostramos esta "card" SÓLO si es Secretario, Presidente o Admin
        if ($tipoUsuario == ROL_SECRETARIO_TECNICO || $tipoUsuario == ROL_PRESIDENTE_COMISION || $tipoUsuario == ROL_ADMINISTRADOR):
        ?>
            <div class="col-md-6">
                <a href="menu.php?pagina=minutas_pendientes" class="dashboard-card h-100">
                    <i class="fas fa-clock text-warning"></i>
                    <h5 class="mt-3">Minutas Pendientes</h5>
                    <p class="mb-0 text-muted">Revisar, firmar y gestionar las minutas que requieren aprobación.</p>
                </a>
            </div>
        <?php endif; // --- FIN DE LÓGICA DE VISIBILIDAD --- 
        ?>


        <?php
        // --- LÓGICA PARA LA CARD DE APROBADAS ---
        // Si el usuario puede ver pendientes, la card de aprobadas comparte la fila (col-md-6)
        // Si no (como un Consejero), la card de aprobadas ocupa todo el ancho (col-md-12)

        $esAdminSecretarioOPresidente = ($tipoUsuario == ROL_SECRETARIO_TECNICO || $tipoUsuario == ROL_PRESIDENTE_COMISION || $tipoUsuario == ROL_ADMINISTRADOR);
        $colClass = $esAdminSecretarioOPresidente ? 'col-md-6' : 'col-md-12';
        ?>

        <div class="<?php echo $colClass; ?>">
            <a href="menu.php?pagina=minutas_aprobadas" class="dashboard-card h-100">
                <i class="fas fa-check-circle text-success"></i>
                <h5 class="mt-3">Minutas Aprobadas</h5>
                <p class="mb-0 text-muted">Consultar el archivo histórico de todas las minutas firmadas y finalizadas.</p>
            </a>
        </div>

        <div class="col-xl-4 col-md-6 mb-4">
            <a href="menu.php?pagina=seguimiento_general" class="dashboard-card text-decoration-none">
                <div class="card-body text-center">
                    <i class="fas fa-tasks fa-3x mb-3 text-info"></i>
                    <h5 class="card-title mb-2 font-weight-bold text-info">Seguimiento General</h5>
                    <p class="card-text text-muted">Ver el estado actual y la última acción de todas las minutas en proceso.</p>
                </div>
            </a>
        </div>

    </div>
</div>