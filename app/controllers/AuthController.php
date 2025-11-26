<?php

namespace App\Controllers;

use App\Models\User;

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
}
