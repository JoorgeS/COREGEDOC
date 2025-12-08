<?php
// app/views/layouts/main.php

// Definición de constantes de seguridad
if (!defined('ROL_ADMINISTRADOR')) define('ROL_ADMINISTRADOR', 6);
if (!defined('ROL_SECRETARIO_TECNICO')) define('ROL_SECRETARIO_TECNICO', 2);
if (!defined('ROL_PRESIDENTE_COMISION')) define('ROL_PRESIDENTE_COMISION', 3);
if (!defined('ROL_CONSEJERO')) define('ROL_CONSEJERO', 1);

// Variables de vista
$paginaActual = $data['pagina_actual'] ?? 'home';// --- CORRECCIÓN AQUÍ ---
// Usamos ?? para evitar el "Undefined array key"
$u = $data['usuario'] ?? [];
$nombreUsuario = trim(($u['nombre'] ?? '') . ' ' . ($u['apellido'] ?? ''));
if (empty($nombreUsuario)) {
    $nombreUsuario = 'Usuario Invitado';
}
// -----------------------
$tipoUsuario = $data['usuario']['rol'] ?? 0;

// Helper Menu Activo (Opcional, para lógica de visualización)
function esActivo($grupo, $actual) {
    // ... tu lógica de menú ...
    return false; 
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>COREGEDOC - Gestión Documental</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <link href="<?= BASE_URL ?>/public/css/layout.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/public/css/dashboard.css" rel="stylesheet">
    
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

    <script src="<?= BASE_URL ?>/public/vendor/jquery/jquery-3.7.1.min.js"></script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script src="<?= BASE_URL ?>/public/js/main.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebarToggle = document.getElementById('sidebarToggle');
            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', function (event) {
                    event.preventDefault();
                    document.body.classList.toggle('sb-sidenav-toggled');
                });
            }
        });
    </script>
</body>
</html>