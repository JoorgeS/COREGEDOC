<?php

// 1. Cargar el Autoloader de Composer (Carga automática de clases)
require_once __DIR__ . '/vendor/autoload.php';


use App\Controllers\AuthController;
use App\Controllers\HomeController;

// 2. Iniciar la sesión globalmente para toda la app
session_start();

// 3. Enrutador Básico
// Verificamos si hay una acción específica en la URL (ej: index.php?action=logout)
$action = isset($_GET['action']) ? $_GET['action'] : 'index';

// Instanciamos el controlador de autenticación
$auth = new AuthController();

switch ($action) {
    case 'logout':
        // Acción de cerrar sesión
        $auth->logout();
        break;

    case 'login':
        // Procesar formulario o mostrar vista de login
        $auth->login();
        break;

    default:
        // Lógica principal (Home o Login)
        if (isset($_SESSION['idUsuario'])) {
            // ✅ NUEVO: Usar el HomeController en lugar de incluir archivo directo
            $home = new HomeController();
            $home->index();
        } else {
            $auth->login();
        }
        break;
}
