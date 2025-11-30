<?php

namespace App\Controllers;

use App\Models\User;
use App\Services\MailService;

class AuthController
{

    public function login()
    {
        // Iniciamos sesión si no está iniciada
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Si ya está logueado, redirigir al home
        if (isset($_SESSION['idUsuario'])) {
            header('Location: index.php?action=home'); // Asegúrate de usar la ruta correcta de tu nueva carpeta
            exit();
        }

        $error_message = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $usuario = isset($_POST['correo']) ? trim($_POST['correo']) : ''; // OJO: En tu form login.php el name es "correo", no "usuario"
            $clave = isset($_POST['contrasena']) ? $_POST['contrasena'] : ''; // OJO: name="contrasena"

            // --- DEBUG 1: Verificamos si llegan los datos ---
            // echo "DATOS RECIBIDOS: Usuario: [$usuario] - Clave: [$clave] <br>";
            
            if (empty($usuario) || empty($clave)) {
                $error_message = 'Por favor, ingrese usuario y contraseña.';
            } else {
                $userModel = new User();
                $user = $userModel->findByEmail($usuario);

                // --- DEBUG 2: Verificamos si la BD devolvió algo ---
                // echo "DATOS DB: <pre>"; var_dump($user); echo "</pre>"; 
                // die(); // Detener aquí para ver el dump

                if ($user) {
                    // --- DEBUG 3: Verificamos el hash ---
                    // echo "Hash en BD: " . $user['contrasena'] . "<br>";
                    // echo "Password ingresado: " . $clave . "<br>";
                    // $verificacion = password_verify($clave, $user['contrasena']) ? 'TRUE' : 'FALSE';
                    // echo "Resultado password_verify: " . $verificacion;
                    // die(); 

                    if (password_verify($clave, $user['contrasena'])) {
                        $_SESSION['idUsuario'] = $user['idUsuario'];
                        $_SESSION['pNombre'] = $user['pNombre']; // Guardamos nombre para el home
                        $_SESSION['aPaterno'] = $user['aPaterno'];
                        $_SESSION['tipoUsuario_id'] = $user['tipoUsuario_id'];
                        
                        header('Location: index.php?action=home');
                        exit();
                    } else {
                        $error_message = 'La contraseña es incorrecta.';
                    }
                } else {
                    $error_message = 'Usuario no encontrado.';
                }
            }
        }
        
        require_once __DIR__ . '/../views/login.php';
    }

    public function logout()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        session_destroy();
        header('Location: /coregedoc/index.php');
        exit();
    }

    // ... Agrega al inicio del archivo: use App\Services\MailService; ...

    // 1. VISTA: RECUPERAR (Ingresar Email)
    public function recuperarPassword()
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        
        $message = '';
        $message_type = '';
        
        // Captcha simple
        if (!isset($_SESSION['captcha_code'])) {
            $_SESSION['captcha_code'] = substr(md5(microtime()), rand(0, 26), 5);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = trim($_POST['correo'] ?? '');
            $captchaInput = trim($_POST['captcha'] ?? '');
            $captchaSession = $_SESSION['captcha_code'] ?? '';

            if (strtolower($captchaInput) !== strtolower($captchaSession)) {
                $message = 'El código de seguridad es incorrecto.';
                $message_type = 'danger'; // Bootstrap class
            } else {
                $token = bin2hex(random_bytes(32));
                $userModel = new \App\Models\User();
                
                // Guardamos token solo si el email existe
                if ($userModel->guardarTokenRecuperacion($email, $token)) {
                    $mailService = new \App\Services\MailService();
                    $mailService->enviarInstruccionesRecuperacion($email, $token);
                }

                // Mensaje genérico por seguridad
                $message = 'Si el correo está registrado, recibirás instrucciones en breve.';
                $message_type = 'success';
            }
            // Rotar captcha
            $_SESSION['captcha_code'] = substr(md5(microtime()), rand(0, 26), 5);
        }

        require_once __DIR__ . '/../views/auth/recuperar.php';
    }

    // 2. VISTA: RESTABLECER (Ingresar Nueva Clave)
    public function restablecerPassword()
    {
        $token = $_GET['token'] ?? '';
        $userModel = new \App\Models\User();
        $usuario = $userModel->verificarToken($token);
        
        $message = '';
        $message_type = '';
        $tokenValido = ($usuario !== false);

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tokenValido) {
            $pass = $_POST['contrasena'] ?? '';
            $confirm = $_POST['confirmar_contrasena'] ?? '';

            if ($pass !== $confirm) {
                $message = 'Las contraseñas no coinciden.';
                $message_type = 'danger';
            } elseif (strlen($pass) < 8) {
                $message = 'La contraseña es muy corta (mínimo 8 caracteres).';
                $message_type = 'danger';
            } else {
                $hash = password_hash($pass, PASSWORD_DEFAULT);
                if ($userModel->actualizarPassword($usuario['idUsuario'], $hash)) {
                    // Éxito: Redirigir al login
                    echo "<script>alert('Contraseña actualizada. Inicie sesión.'); window.location.href='index.php?action=login';</script>";
                    exit;
                } else {
                    $message = 'Error al actualizar en base de datos.';
                    $message_type = 'danger';
                }
            }
        }

        require_once __DIR__ . '/../views/auth/restablecer.php';
    }
}
