<?php
// /coregedoc/views/login.php

// 1. BORRA O COMENTA ESTAS LÍNEAS PARA QUE NO PISE LA VARIABLE DEL CONTROLADOR
/* $error_message = '';
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']); 
}
*/

// 2. AGREGA ESTO EN SU LUGAR:
// Aseguramos que la variable exista, pero respetamos si el controlador ya le puso texto.
if (!isset($error_message)) {
    $error_message = '';
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>COREGEDOC - Login</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/coregedoc/public/css/login_style.css">
</head>

<body>

    <div class="background-overlay"></div>

    <div class="login-container">
        <div class="login-box">

            <div class="text-center mb-3">
                <img src="/coregedoc/public/img/logoCore1.png" alt="Logo CORE Valparaíso" class="login-logo">
            </div>

            <h5 class="text-center fw-bold mb-1">Plataforma Gestión Documental</h5>
            <p class="text-center subtitle mb-4">Consejo Regional de Valparaíso</p>


            <form action="index.php?action=login" method="POST">

                <div class="mb-3">
                    <label for="correo" class="form-label small">USUARIO</label>
                    <input type="email" class="form-control" id="correo" name="correo" required autocomplete="username">
                </div>

                <div class="mb-3">
                    <label for="contrasena" class="form-label small">CONTRASEÑA</label>
                    <input type="password" class="form-control" id="contrasena" name="contrasena" required autocomplete="current-password">
                </div>


                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <div class="recover-link mb-4">
                    <a href="/coregedoc/views/pages/recuperar_contrasena.php">RECUPERAR CONTRASEÑA</a>
                </div>

                <button type="submit" class="btn btn-submit">INGRESAR</button>
            </form>
        </div>
    </div>

    

</body>

</html>