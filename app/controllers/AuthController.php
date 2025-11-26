<?php

namespace App\Controllers;

use App\Models\User;

class AuthController {
    
    public function login() {
        // Iniciamos sesión si no está iniciada
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Si ya está logueado, redirigir al home
        if (isset($_SESSION['idUsuario'])) {
            header('Location: /coregedoc/index.php'); // Asegúrate de usar la ruta correcta de tu nueva carpeta
            exit();
        }

        $error_message = '';

        // Verificar si se envió el formulario (POST)
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $usuario = isset($_POST['usuario']) ? trim($_POST['usuario']) : '';
            $clave = isset($_POST['password']) ? $_POST['password'] : '';

            if (empty($usuario) || empty($clave)) {
                $error_message = 'Por favor, ingrese usuario y contraseña.';
            } else {
                // Usamos el Modelo de Usuario que creamos en el Paso 2
                $userModel = new User();
                $user = $userModel->findByEmail($usuario);

                if ($user && password_verify($clave, $user['contrasena'])) {
                    // ✅ Credenciales Correctas: Guardar sesión
                    $_SESSION['idUsuario'] = $user['idUsuario'];
                    $_SESSION['pNombre'] = $user['pNombre'];
                    $_SESSION['aPaterno'] = $user['aPaterno'];
                    $_SESSION['correo'] = $user['correo'];
                    $_SESSION['tipoUsuario_id'] = $user['tipoUsuario_id'];

                    // Redirección al dashboard/home
                    header('Location: /coregedoc/index.php'); 
                    exit();
                } else {
                    $error_message = 'Credenciales incorrectas.';
                }
            }
        }

        // Cargar la vista de login
        // Nota: Asumimos que moveremos las vistas pronto, por ahora incluimos la ruta antigua o preparamos la nueva
        // Para mantener el orden, vamos a esperar al siguiente paso para mover la vista, 
        // pero dejaremos este require listo apuntando a donde DEBERÍA estar.
        
        require_once __DIR__ . '/../views/login.php'; 
    }

    public function logout() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        session_destroy();
        header('Location: /coregedoc/index.php');
        exit();
    }
}