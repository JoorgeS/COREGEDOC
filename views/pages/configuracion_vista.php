<?php
/**
 * --------------------------------------
 * GUARDIA DE ACCESO (ADMIN ONLY)
 * --------------------------------------
 * Las variables $tipoUsuario y ROL_ADMINISTRADOR son definidas 
 * automáticamente por el archivo menu.php que incluye este script.
 */
if (!isset($tipoUsuario) || $tipoUsuario != ROL_ADMINISTRADOR) {
    
    // Oculta el contenido y redirige al inicio
    echo "<div class='alert alert-danger m-3'>Acceso Denegado: No tiene permisos para ver esta página.</div>";
    echo '<script>setTimeout(function() { window.location.href = "menu.php?pagina=home"; }, 2000);</script>';
    
    // Detiene la ejecución del resto de la página de admin
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Configuración de la Vista</title>
    <link href="/coregedoc/public/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* CSS simulado para aplicar el tema/fuente (se requeriría JS para la funcionalidad real) */
        .card-body button { margin-right: 10px; }
    </style>
</head>
<body>
<div class="container mt-4">
    <h3 class="mb-4">Configuración de la Interfaz</h3>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-info text-white">
            <i class="fa-solid fa-palette me-2"></i> Tema de Color
        </div>
        <div class="card-body">
            <p class="card-title">Selecciona el tema de color para la aplicación:</p>
            <button class="btn btn-sm btn-light border" onclick="aplicarTema('claro')">Claro (Predeterminado)</button>
            <button class="btn btn-sm btn-dark" onclick="aplicarTema('oscuro')">Oscuro</button>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-info text-white">
            <i class="fa-solid fa-text-height me-2"></i> Tamaño de Fuente
        </div>
        <div class="card-body">
            <p class="card-title">Ajusta el tamaño del texto:</p>
            <button class="btn btn-sm btn-outline-secondary" onclick="aplicarFuente('pequena')">Pequeña</button>
            <button class="btn btn-sm btn-outline-secondary" onclick="aplicarFuente('normal')">Normal</button>
            <button class="btn btn-sm btn-outline-secondary" onclick="aplicarFuente('grande')">Grande</button>
        </div>
    </div>
</div>

<script>
    function aplicarTema(tema) {
        alert("Función para aplicar el tema '" + tema + "' a través de JavaScript no implementada aún.");
    }
    function aplicarFuente(tamanio) {
        alert("Función para aplicar fuente '" + tamanio + "' a través de JavaScript no implementada aún.");
    }
</script>
</body>
</html>