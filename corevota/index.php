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
        try {
            // Conexión a la base de datos
            $conector = new conectorDB();
            // Llama al método que devuelve el objeto PDO (ya implementado en conectorDB)
            $db = $conector->getDatabase(); 

            // Consulta segura (Sentencia preparada)
            $sql = 'SELECT idUsuario, correo, contrasena 
                    FROM t_usuario 
                    WHERE correo = :user_input';

            $stmt = $db->prepare($sql);
            $stmt->execute([':user_input' => $usuario]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($clave, $user['contrasena'])) {
                
                // ✅ ÉXITO: Iniciar la sesión
                $_SESSION['user_id'] = $user['idUsuario'];
                $_SESSION['user_email'] = $user['correo'];

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
?>