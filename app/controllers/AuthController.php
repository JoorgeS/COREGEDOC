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
            header('Location: index.php?action=home');
            exit();
        }

        $error_message = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $usuario = isset($_POST['correo']) ? trim($_POST['correo']) : '';
            $clave = isset($_POST['contrasena']) ? $_POST['contrasena'] : '';

            if (empty($usuario) || empty($clave)) {
                $error_message = 'Por favor, ingrese usuario y contraseña.';
            } else {
                $userModel = new User();
                $user = $userModel->findByEmail($usuario);

                // VALIDACIÓN DE SEGURIDAD: Aseguramos que $user sea un array y tenga contraseña
                if ($user && is_array($user) && isset($user['contrasena'])) {
                    
                    if (password_verify($clave, $user['contrasena'])) {
                        // --- INICIO SESIÓN EXITOSO ---
                        $_SESSION['idUsuario'] = $user['idUsuario'];
                        $_SESSION['pNombre'] = $user['pNombre'];
                        $_SESSION['aPaterno'] = $user['aPaterno'];
                        $_SESSION['tipoUsuario_id'] = $user['tipoUsuario_id'];
                        
                        // SOLUCIÓN A ALERTAS NAVBAR Y PERFIL:
                        $_SESSION['email'] = $user['correo']; // Guardamos el correo
                        $_SESSION['rutaImagenPerfil'] = $user['foto_perfil'];
                        
                        // (RUT excluido por regla de negocio)

                        header('Location: index.php?action=home');
                        exit();
                    } else {
                        $error_message = 'La contraseña es incorrecta.';
                    }
                } else {
                    // Si $user no es array o no se encontró
                    $error_message = 'Usuario no encontrado o error en base de datos.';
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
        header('Location: index.php'); // Redirige al index general
        exit();
    }

    // --- RECUPERACIÓN DE CONTRASEÑA ---

    public function recuperarPassword()
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        
        $message = '';
        $message_type = '';
        
        if (!isset($_SESSION['captcha_code'])) {
            $_SESSION['captcha_code'] = substr(md5(microtime()), rand(0, 26), 5);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = trim($_POST['correo'] ?? '');
            $captchaInput = trim($_POST['captcha'] ?? '');
            $captchaSession = $_SESSION['captcha_code'] ?? '';

            if (strtolower($captchaInput) !== strtolower($captchaSession)) {
                $message = 'El código de seguridad es incorrecto.';
                $message_type = 'danger';
            } else {
                $token = bin2hex(random_bytes(32));
                $userModel = new User();
                
                if ($userModel->guardarTokenRecuperacion($email, $token)) {
                    $mailService = new MailService();
                    $mailService->enviarInstruccionesRecuperacion($email, $token);
                }

                $message = 'Si el correo está registrado, recibirás instrucciones en breve.';
                $message_type = 'success';
            }
            $_SESSION['captcha_code'] = substr(md5(microtime()), rand(0, 26), 5);
        }

        require_once __DIR__ . '/../views/auth/recuperar.php';
    }

    public function restablecerPassword()
    {
        $token = $_GET['token'] ?? '';
        $userModel = new User();
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
                // Se usa idUsuario porque el modelo devuelve un array
                if ($userModel->actualizarPassword($usuario['idUsuario'], $hash)) {
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