<?php
// app/config/Constants.php

// --------------------------------------------------------------------------
// 1. Definición de la URL Base (Detección Automática)
// --------------------------------------------------------------------------
// Esto detecta si es http o https, el servidor (localhost) y la carpeta del proyecto
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST']; // ej: localhost
$path = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\'); // ej: /coregedoc

if (!defined('BASE_URL')) {
    define('BASE_URL', $protocol . "://" . $host . $path);
}

// --------------------------------------------------------------------------
// 2. Definición de Roles de Usuario (IDs según tu base de datos)
// --------------------------------------------------------------------------
if (!defined('ROL_CONSEJERO')) define('ROL_CONSEJERO', 1);
if (!defined('ROL_SECRETARIO_TECNICO')) define('ROL_SECRETARIO_TECNICO', 2);
if (!defined('ROL_PRESIDENTE_COMISION')) define('ROL_PRESIDENTE_COMISION', 3);
if (!defined('ROL_ADMINISTRADOR')) define('ROL_ADMINISTRADOR', 6); 

// --------------------------------------------------------------------------
// 3. Otras constantes generales
// --------------------------------------------------------------------------
if (!defined('APP_NAME')) define('APP_NAME', 'COREGEDOC');

// Define la ruta física del sistema para inclusiones de PHP (require/include)
if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(dirname(dirname(__DIR__))));