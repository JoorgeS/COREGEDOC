<?php
// views/pages/minutas_dashboard.php

// Define la pestaña activa (por defecto 'pendientes')
$tabActiva = $_GET['tab'] ?? 'pendientes';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">Gestión de Minutas</h2>
        <a href="menu.php?pagina=crear_minuta" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Crear Nueva Minuta
        </a>
    </div>

    <ul class="nav nav-tabs" id="minutasTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <a class="nav-link <?php echo ($tabActiva === 'pendientes') ? 'active' : ''; ?>" href="menu.php?pagina=minutas_dashboard&tab=pendientes" role="tab">
                <i class="fas fa-clock me-2"></i>Minutas Pendientes
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link <?php echo ($tabActiva === 'aprobadas') ? 'active' : ''; ?>" href="menu.php?pagina=minutas_dashboard&tab=aprobadas" role="tab">
                <i class="fas fa-check-circle me-2"></i>Minutas Aprobadas
            </a>
        </li>
    </ul>

    <div class="tab-content" id="minutasTabsContent">
        
        <div class="tab-pane fade <?php echo ($tabActiva === 'pendientes') ? 'show active' : ''; ?>" id="pendientes-content" role="tabpanel">
            <div class="card card-body border-top-0 rounded-bottom">
                <?php
                // --- AJUSTE IMPORTANTE ---
                // Solo cargar este bloque si la pestaña está activa
                if ($tabActiva === 'pendientes') {
                    $_GET['action'] = 'list';
                    $_GET['estado'] = 'PENDIENTE';
                    include __DIR__ . '/../../controllers/MinutaController.php';
                }
                ?>
            </div>
        </div>

        <div class="tab-pane fade <?php echo ($tabActiva === 'aprobadas') ? 'show active' : ''; ?>" id="aprobadas-content" role="tabpanel">
            <div class="card card-body border-top-0 rounded-bottom">
                 <?php
                // --- AJUSTE IMPORTANTE ---
                // Solo cargar este bloque si la pestaña está activa
                if ($tabActiva === 'aprobadas') {
                    $_GET['action'] = 'list';
                    $_GET['estado'] = 'APROBADA';
                    include __DIR__ . '/../../controllers/MinutaController.php';
                }
                ?>
            </div>
        </div>
    </div>
</div>