<?php
// home.php (Dentro de /views/pages/home.php)

// IMPORTANTE: session_start() ya fue llamado en index.php, 
// por lo que $_SESSION ya está disponible.

// Verificación de Acceso Restringido
if (!isset($_SESSION['user_id'])) {
    // Si la sesión no existe, redirigir inmediatamente al login (index.php)
    header('Location: /COREVOTA/index.php');
    exit(); // Detiene la ejecución para evitar que se cargue el contenido
}

// ✅ Si la sesión es válida, se procede a cargar el contenido
// Punto de entrada único de la app
// Redirige al layout principal con el sidebar
include __DIR__ . '/menu.php'; 
?>