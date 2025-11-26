<?php
// app/views/minutas/dashboard.php

// Recuperamos el rol desde la data que pasó el controlador
$tipoUsuario = $data['usuario']['rol'];
?>

<div class="container-fluid">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php?action=home">Inicio</a></li>
            <li class="breadcrumb-item active" aria-current="page">Minutas</li>
        </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-file-alt me-2 text-primary"></i> Gestión de Minutas</h2>
    </div>

    <div class="row g-4">
        
        <?php if (in_array($tipoUsuario, [ROL_SECRETARIO_TECNICO, ROL_PRESIDENTE_COMISION, ROL_ADMINISTRADOR])): ?>
            <div class="col-md-6 col-xl-4">
                <a href="index.php?action=minutas_pendientes" class="dashboard-card"> <div class="card-body text-center py-5">
                        <i class="fas fa-clock fa-3x text-warning mb-3"></i>
                        <h5 class="card-title">Minutas Pendientes</h5>
                        <p class="card-text text-muted">Firmar y gestionar aprobaciones.</p>
                    </div>
                </a>
            </div>
        <?php endif; ?>

        <div class="col-md-6 col-xl-4">
            <a href="index.php?action=minutas_aprobadas" class="dashboard-card"> <div class="card-body text-center py-5">
                    <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                    <h5 class="card-title">Minutas Aprobadas</h5>
                    <p class="card-text text-muted">Histórico de minutas finalizadas.</p>
                </div>
            </a>
        </div>

        <?php if ($tipoUsuario == ROL_ADMINISTRADOR): ?>
            <div class="col-md-6 col-xl-4">
                <a href="index.php?action=seguimiento_general" class="dashboard-card"> <div class="card-body text-center py-5">
                        <i class="fas fa-tasks fa-3x text-info mb-3"></i>
                        <h5 class="card-title">Seguimiento General</h5>
                        <p class="card-text text-muted">Monitor de estados y procesos.</p>
                    </div>
                </a>
            </div>
        <?php endif; ?>

    </div>
</div>