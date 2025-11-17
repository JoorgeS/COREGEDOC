<?php
// views/pages/reuniones_dashboard.php

if (session_status() === PHP_SESSION_NONE) session_start();
$tipoUsuario = $_SESSION['tipoUsuario_id'] ?? 0;

// --- INICIO DE LA MODIFICACIÓN ---
// Definir constantes (o incluirlas desde un archivo global)
// Añadimos los roles 1 y 3 que faltaban para la lógica
if (!defined('ROL_CONSEJERO')) define('ROL_CONSEJERO', 1);
if (!defined('ROL_SECRETARIO_TECNICO')) define('ROL_SECRETARIO_TECNICO', 2);
if (!defined('ROL_PRESIDENTE_COMISION')) define('ROL_PRESIDENTE_COMISION', 3);
if (!defined('ROL_ADMINISTRADOR')) define('ROL_ADMINISTRADOR', 6);
// --- FIN DE LA MODIFICACIÓN ---
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">Módulo de Reuniones</h2>
    </div>

    <p class="lead text-muted mb-4">Selecciona una acción para gestionar las reuniones, asistencias y calendarios.</p>

    <div class="row g-4">

        <div class="col-md-6 col-lg-4">
            <a href="menu.php?pagina=reunion_crear" class="dashboard-card h-100">
                <i class="fas fa-plus text-primary"></i>
                <h5 class="mt-3">Crear Nueva Reunión</h5>
                <p class="mb-0 text-muted">Agendar una nueva sesión o reunión.</p>
            </a>
        </div>
        
        <div class="col-md-6 col-lg-4">
            <a href="menu.php?pagina=reunion_listado" class="dashboard-card h-100">
                <i class="fas fa-list"></i>
                <h5 class="mt-3">Listado de Reuniones</h5>
                <p class="mb-0 text-muted">Ver y administrar todas las reuniones.</p>
            </a>
        </div>

        <div class="col-md-6 col-lg-4">
            <a href="menu.php?pagina=reunion_calendario" class="dashboard-card h-100">
                <i class="fas fa-calendar-alt"></i>
                <h5 class="mt-3">Vista Calendario</h5>
                <p class="mb-0 text-muted">Ver reuniones en el calendario.</p>
            </a>
        </div>


        <?php 
        // --- INICIO DE LA MODIFICACIÓN ---
        // Estas dos tarjetas solo son visibles para los "participantes" (Consejeros y Presidentes)
        // Se ocultarán para Secretarios (2) y Administradores (6)
        if ($tipoUsuario == ROL_CONSEJERO || $tipoUsuario == ROL_PRESIDENTE_COMISION):
        ?>
        
            <div class="col-md-6 col-lg-4">
                <a href="menu.php?pagina=reunion_autogestion_asistencia" class="dashboard-card h-100">
                    <i class="fas fa-hand-pointer text-success"></i>
                    <h5 class="mt-3">Registrar Mi Asistencia</h5>
                    <p class="mb-0 text-muted">Marcar asistencia a una reunión activa.</p>
                </a>
            </div>

            <div class="col-md-6 col-lg-4">
                <a href="menu.php?pagina=historial_asistencia" class="dashboard-card h-100">
                    <i class="fas fa-clipboard-list"></i>
                    <h5 class="mt-3">Mi Historial de Asistencia</h5>
                    <p class="mb-0 text-muted">Revisar mi historial personal.</p>
                </a>
            </div>

        <?php 
        endif; 
        // --- FIN DE LA MODIFICACIÓN ---
        ?>
    </div>
</div>