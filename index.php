<?php
// coregedoc/index.php

// 1. Cargar el autoloader de Composer
require_once __DIR__ . '/vendor/autoload.php';

// 2. Usar los controladores
use App\Controllers\AuthController;
use App\Controllers\HomeController;

// 3. Iniciar sesión globalmente
session_start();

// 4. Enrutador Simple (Switch Case)
// Capturamos la variable ?action= de la URL. Si no existe, asumimos 'login'.
$action = $_GET['action'] ?? 'login';

// Instanciamos los controladores
$authController = new AuthController();
$homeController = new HomeController();

try {
    switch ($action) {
        case 'login':
            // Si envía datos por POST, procesa el login. Si no, muestra el formulario.
            $authController->login();
            break;

        case 'logout':
            $authController->logout();
            break;

        case 'home':
            // Aquí deberíamos verificar si está logueado antes de dejarlo entrar
            // (Tu HomeController ya tiene una validación comentada, úsala luego)
            $homeController->index();
            break;
            
        // Agrega aquí más casos: 'minutas_dashboard', 'usuarios', etc.
        case 'minutaPendiente':
             echo "Aquí iría el controlador de Minutas Pendientes";
             break;

        case 'voto_autogestion':
             echo "Aquí iría el controlador de Votos";
             break;

        default:
            // Si la acción no existe, mandamos al login o 404
            header('Location: index.php?action=login');
            exit();
    }
} catch (Exception $e) {
    // Manejo básico de errores globales
    echo "Ocurrió un error: " . $e->getMessage();
}