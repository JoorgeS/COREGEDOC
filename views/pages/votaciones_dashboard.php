<?php
// views/pages/votaciones_dashboard.php

// --- INICIO DE LA MODIFICACIÓN ---
if (session_status() === PHP_SESSION_NONE) session_start();
$tipoUsuario = $_SESSION['tipoUsuario_id'] ?? 0;

// Definir constantes de rol (basadas en menu.php)
if (!defined('ROL_CONSEJERO')) define('ROL_CONSEJERO', 1);
if (!defined('ROL_SECRETARIO_TECNICO')) define('ROL_SECRETARIO_TECNICO', 2);
if (!defined('ROL_PRESIDENTE_COMISION')) define('ROL_PRESIDENTE_COMISION', 3);
if (!defined('ROL_ADMINISTRADOR')) define('ROL_ADMINISTRADOR', 6);
// --- FIN DE LA MODIFICACIÓN ---
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

        <?php 
        // --- INICIO DE LA MODIFICACIÓN ---
        // Estas dos tarjetas solo son visibles para los "participantes" (Consejeros y Presidentes)
        // Se ocultarán para Secretarios (2) y Administradores (6)
        if ($tipoUsuario == ROL_CONSEJERO || $tipoUsuario == ROL_PRESIDENTE_COMISION):
        ?>

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

        <?php 
        endif; 
        // --- FIN DE LA MODIFICACIÓN ---
        ?>
    </div>
</div>