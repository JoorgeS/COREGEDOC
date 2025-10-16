<?php
// Inicia la sesión de PHP
// Si ya está iniciada en el controlador principal o en otro archivo incluido, 
// PHP emitirá un "Notice", pero el código funcionará.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$error_message = '';

// 1. Verificar si el controlador nos envió un mensaje de error a través de la sesión
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];

    // 2. CRÍTICO: Eliminar el mensaje de la sesión para que no se muestre de nuevo 
    // cuando el usuario recargue la página o intente ingresar de nuevo.
    unset($_SESSION['error_message']);
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - CORE Valparaíso</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: linear-gradient(90deg, #f2f2f2, #c5c4c4);
        }

        .container {
            display: flex;
            align-items: center;
            gap: 50px;
        }

        .logo img {
            max-width: 350px;
        }

        .login-box {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0px 4px 20px rgba(0, 0, 0, 0.1);
            width: 350px;
            text-align: center;
        }

        .login-box h2 {
            font-size: 18px;
            margin-bottom: 20px;
        }

        .login-box label {
            display: block;
            text-align: left;
            font-weight: bold;
            font-size: 12px;
            margin: 10px 0 5px;
        }

        .login-box input {
            width: 100%;
            padding: 12px;
            margin-bottom: 15px;
            border: 2px solid #000;
            border-radius: 12px;
            font-size: 14px;
        }

        .login-box a {
            display: block;
            text-align: left;
            font-size: 11px;
            margin-bottom: 20px;
            text-decoration: none;
            color: black;
            font-weight: bold;
        }

        .btn {
            width: 100%;
            padding: 14px;
            background: #06c167;
            color: white;
            font-weight: bold;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-size: 16px;
        }

        .btn:hover {
            background: #04a257;
        }

        .icon {
            font-size: 40px;
            margin-bottom: 10px;
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Logo -->
        <div class="logo">
            <img src="/corevota/public/img/logoCore1.png" alt="CORE Valparaíso">
        </div>

        <!-- Login -->
        <div class="login-box">
            <div class="icon">⤳</div>
            <h2>Plataforma Gestión Documental <br> Consejo Regional de Valparaíso</h2>

            <?php
            // Muestra el mensaje de error si la variable $error_message existe y no está vacía.
            if (!empty($error_message)):
            ?>
                <div style="color: white; background: #c0392b; padding: 10px; border-radius: 5px; margin-bottom: 15px; font-size: 14px;">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>



            <form action="/corevota/controllers/loginController.php" method="post">
                <label for="correo">USUARIO</label>
                <input type="email" id="correo" name="correo" required>

                <label for="contrasena">CONTRASEÑA</label>
                <input type="password" id="contrasena" name="contrasena" required>

                <a href="/corevota/recuperar_contrasena.php">RECUPERAR CONTRASEÑA</a>

                <button type="submit" class="btn">INGRESAR</button>
            </form>



        </div>
    </div>
</body>

</html>