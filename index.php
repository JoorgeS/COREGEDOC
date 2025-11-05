<?php

// ====================================================
// 0. INICIO DEL SCRIPT Y GESTIÓN DE SESIÓN
// ====================================================
session_start();

// ----------------------------------------------------
// 1. INCLUSIONES Y CONFIGURACIÓN INICIAL
// ----------------------------------------------------
// Rutas relativas a la raíz
require_once __DIR__ . '/cfg/config.php';
require_once __DIR__ . '/class/class.conectorDB.php';

// Variable para el mensaje de error de login
$error_message = '';

// ====================================================
// 2. LÓGICA DE PROCESAMIENTO DEL LOGIN (Método POST)
// ====================================================
// CRÍTICO: Se corrige el error sintáctico aquí (de 'e' a solo el operador '===').
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Saneamiento y recolección de datos
    $usuario = isset($_POST['usuario']) ? trim($_POST['usuario']) : '';
    $clave = isset($_POST['password']) ? $_POST['password'] : '';

    if (empty($usuario) || empty($clave)) {
        $_SESSION['login_error'] = 'Por favor, ingrese usuario y contraseña.';
    } else {
        // CÓDIGO CORREGIDO (Pégalo en index.php)
        try {
            // Conexión a la base de datos
            $conector = new conectorDB();
            $db = $conector->getDatabase(); 

            // 1. MODIFICACIÓN: Pedir TODOS los datos que menu.php necesita
            $sql = 'SELECT idUsuario, correo, contrasena, pNombre, aPaterno, tipoUsuario_id 
                    FROM t_usuario 
                    WHERE correo = :user_input';

            $stmt = $db->prepare($sql);
            $stmt->execute([':user_input' => $usuario]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($clave, $user['contrasena'])) {
                
                // ✅ ÉXITO: Iniciar la sesión
                
                // 2. MODIFICACIÓN: Guardar TODAS las variables que menu.php usa
                $_SESSION['idUsuario'] = $user['idUsuario'];      // Clave correcta para menu.php
                $_SESSION['pNombre'] = $user['pNombre'];          // Clave correcta para menu.php
                $_SESSION['aPaterno'] = $user['aPaterno'];       // Clave correcta para menu.php
                $_SESSION['correo'] = $user['correo'];
                $_SESSION['tipoUsuario_id'] = $user['tipoUsuario_id']; // ¡La clave que faltaba!

                header('Location: /COREVOTA/index.php');
                exit(); // CRÍTICO: Detiene el script después de la redirección.

            } else {
                // Fallo: Credenciales incorrectas
                $_SESSION['login_error'] = 'Credenciales incorrectas.';
            }
            
        } catch (PDOException $e) {

            // Error de conexión o consulta
            // No exponer detalles del error al usuario final.
            $_SESSION['login_error'] = 'Error interno en el sistema. Intente de nuevo.';
        }
    }

    // Redirige siempre después de un POST para evitar el reenvío del formulario
    header('Location: /COREVOTA/index.php');
    exit();
}

// ====================================================
// 3. CONTROL DE ACCESO Y RENDERIZADO (Método GET)
// ====================================================

// 1. Gestionar y limpiar el mensaje de error de la sesión
if (isset($_SESSION['login_error'])) {
    $error_message = $_SESSION['login_error'];
    unset($_SESSION['login_error']);
}

if (isset($_SESSION['user_id'])) {
    // Usuario logueado: Mostrar vista principal
    include __DIR__ . '/views/pages/home.php';
} else {
    // Usuario NO logueado: Mostrar formulario de login

    // Headers de Seguridad: Evita el caché para la página de login
    header("Cache-Control: no-cache, no-store, must-revalidate");
    header("Pragma: no-cache");
    header("Expires: 0");

    // Incluir la vista de login. $error_message estará disponible aquí.
    include __DIR__ . '/views/pages/login.php';
}
// Fin del script PHP
