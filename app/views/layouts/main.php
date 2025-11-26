<?php
// Asegurar que las constantes estén disponibles si el layout se carga directamente (caso raro pero posible)
require_once __DIR__ . '/../../config/Constants.php';

// Variables de visualización (con valores por defecto para evitar errores)
$paginaActual = $data['pagina_actual'] ?? 'home';
$nombreUsuario = isset($data['usuario']) ? ($data['usuario']['nombre'] . ' ' . $data['usuario']['apellido']) : 'Usuario';
$tipoUsuario = $data['usuario']['rol'] ?? 0;

// Función helper para menú activo (Simple y directa en el layout)
function esActivo($grupo, $paginaActual) {
    $grupos = [
        'home' => ['home'],
        'minutas' => ['minutas_dashboard', 'minutas_pendientes', 'minutas_aprobadas', 'crear_minuta'],
        'usuarios' => ['usuarios_dashboard', 'usuarios_listado'],
        'votaciones' => ['votaciones_dashboard', 'voto_autogestion'],
        'reuniones' => ['reuniones_dashboard', 'sala_reuniones', 'reunion_calendario']
    ];
    return isset($grupos[$grupo]) && in_array($paginaActual, $grupos[$grupo]);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>COREGEDOC - Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="/coregedoc/public/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="/coregedoc/public/css/layout.css" rel="stylesheet"> <link href="/coregedoc/public/css/dashboard.css" rel="stylesheet"> <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
</head>
<body>
    <div class="app-container">
        <nav class="sidebar d-flex flex-column flex-shrink-0">
            <div class="sidebar-header-box">
                <img src="/coregedoc/public/img/logoCore1.png" alt="Logo CORE" class="sidebar-logo">
            </div>
            <div class="flex-grow-1 overflow-auto">
                <ul class="nav nav-pills flex-column mb-auto px-2">
                    <li class="nav-item">
                        <a href="index.php?action=home" class="nav-link <?php echo esActivo('home', $paginaActual) ? 'active' : ''; ?>">
                            <i class="fas fa-home fa-fw"></i> Inicio
                        </a>
                    </li>
                    <?php if (in_array($tipoUsuario, [ROL_ADMINISTRADOR, ROL_SECRETARIO_TECNICO, ROL_PRESIDENTE_COMISION])): ?>
                    <li class="nav-item">
                        <a href="index.php?action=minutas_dashboard" class="nav-link <?php echo esActivo('minutas', $paginaActual) ? 'active' : ''; ?>">
                            <i class="fas fa-file-alt fa-fw"></i> Minutas
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <li class="nav-item mt-auto">
                        <a href="index.php?action=logout" class="nav-link nav-link-logout">
                            <i class="fas fa-sign-out-alt fa-fw"></i> Cerrar Sesión
                        </a>
                    </li>
                </ul>
            </div>
        </nav>

        <header class="core-header d-flex justify-content-between align-items-center p-3">
            <h6 class="titulo-sistema mb-0 fw-bold">Gestor Documental CORE Valparaíso</h6>
            <div class="d-flex align-items-center gap-3">
                <span class="usuario fw-semibold">
                    <i class="fas fa-user-circle me-1"></i> <?php echo htmlspecialchars($nombreUsuario); ?>
                </span>
            </div>
        </header>

        <main>
            <?php 
            // Aquí se inyecta la vista específica (home.php, etc)
            if (isset($childView) && file_exists($childView)) {
                include $childView;
            } else {
                echo "<p class='text-danger'>Error: Vista no encontrada.</p>";
            }
            ?>
        </main>
    </div>

    <script src="/coregedoc/public/vendor/jquery/jquery-3.7.1.min.js"></script>
    <script src="/coregedoc/public/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="/coregedoc/public/js/main.js"></script> </body>
</html>