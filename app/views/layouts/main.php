<?php
// Definición de constantes si no están
if (!defined('ROL_ADMINISTRADOR')) define('ROL_ADMINISTRADOR', 6);
if (!defined('ROL_SECRETARIO_TECNICO')) define('ROL_SECRETARIO_TECNICO', 2);
if (!defined('ROL_PRESIDENTE_COMISION')) define('ROL_PRESIDENTE_COMISION', 3);
if (!defined('ROL_CONSEJERO')) define('ROL_CONSEJERO', 1);

// Variables de vista
$paginaActual = $data['pagina_actual'] ?? 'home';
$nombreUsuario = isset($data['usuario']) ? ($data['usuario']['nombre'] . ' ' . $data['usuario']['apellido']) : 'Invitado';
$tipoUsuario = $data['usuario']['rol'] ?? 0;

// Helper Menu Activo
function esActivo($grupo, $actual) {
    $grupos = [
        'home' => ['home'],
        'minutas' => ['minutas_dashboard', 'minutas_pendientes', 'minutas_aprobadas', 'crear_minuta', 'minuta_gestionar', 'minuta_ver_historial'],
        'reuniones' => ['reuniones_dashboard', 'reunion_form', 'reunion_editar'],
    ];
    return isset($grupos[$grupo]) && in_array($actual, $grupos[$grupo]);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>COREGEDOC - Gestión Documental</title>
    
    <link href="/coregedoc/public/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="/coregedoc/public/css/layout.css" rel="stylesheet">
    <link href="/coregedoc/public/css/dashboard.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
</head>
<body>

    <div class="d-flex" id="wrapper">
        
        <?php include __DIR__ . '/../partials/sidebar.php'; ?>

        <div id="page-content-wrapper" class="d-flex flex-column min-vh-100 w-100">
            
            <?php include __DIR__ . '/../partials/navbar.php'; ?>

            <main class="container-fluid px-4 py-4 flex-grow-1">
                <?php 
                if (isset($childView) && file_exists($childView)) {
                    include $childView;
                } else {
                    echo "<div class='alert alert-danger'>Vista no encontrada: " . htmlspecialchars($childView ?? 'N/A') . "</div>";
                }
                ?>
            </main>

            <?php include __DIR__ . '/../partials/footer.php'; ?>
            
        </div>
    </div>

    <script src="/coregedoc/public/vendor/jquery/jquery-3.7.1.min.js"></script>
    <script src="/coregedoc/public/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="/coregedoc/public/js/main.js"></script>
    
    <script>
        document.getElementById('sidebarToggle')?.addEventListener('click', function () {
            document.body.classList.toggle('sb-sidenav-toggled');
        });
    </script>
</body>
</html>