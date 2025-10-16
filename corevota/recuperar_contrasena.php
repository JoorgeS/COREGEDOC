<?php
session_start();

// Asumiendo que tu archivo de conexión se encuentra aquí. AJUSTA LA RUTA.
require_once(__DIR__ . "/class/class.conectorDB.php");// Ejemplo de ruta
require 'vendor/autoload.php'; 

// Instanciar la conexión
$db = new conectorDB();

// Variables de estado
$message = '';
$message_type = ''; // 'success' o 'error'
$usuario_o_email = ''; // Para mantener el valor en el formulario

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $usuario_o_email = trim($_POST['usuario_o_email']);

    if (empty($usuario_o_email)) {
        $message = 'Por favor, ingrese su nombre de usuario o correo electrónico.';
        $message_type = 'error';
    } else {
        // 1. Generar token y tiempo de expiración
        $token = bin2hex(random_bytes(32)); // Genera un token aleatorio seguro
        $expira = date("Y-m-d H:i:s", time() + 3600); // Expira en 1 hora (3600 segundos)

        // 2. Usar el nuevo método de la clase conectorDB
        $user_data = $db->guardarTokenRestablecimiento($usuario_o_email, $token, $expira);
        
        // El mensaje siempre debe ser genérico por seguridad, sin importar si $user_data es null o no.
        $message = 'Si su cuenta existe, le hemos enviado un correo electrónico con instrucciones para restablecer su contraseña.';
        $message_type = 'success';

        if ($user_data) {
    
            // Usar las clases necesarias
            use PHPMailer\PHPMailer\PHPMailer;
            use PHPMailer\PHPMailer\Exception;

            $mail = new PHPMailer(true);
            try {
                // Configuración del Servidor SMTP (Ejemplo con Gmail)
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com'; // O el host de tu proveedor
                $mail->SMTPAuth   = true;
                
                // ¡CAMBIA ESTOS VALORES!
                $mail->Username   = 'tu.email.de.prueba@gmail.com'; // Tu correo real
                $mail->Password   = 'TU_CONTRASEÑA_DE_APLICACION'; // Tu contraseña o Contraseña de Aplicación
                
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // Usar TLS
                $mail->Port       = 587; // Puerto estándar para TLS

                // Destinatarios y Contenido
                $mail->setFrom('no-responder@corevalparaiso.cl', 'CORE Valparaíso');
                $mail->addAddress($user_data['email']);
                
                $mail->isHTML(false);
                $mail->Subject = 'Recuperación de Contraseña CORE Valparaíso';
                
                // Definir el enlace de restablecimiento
                $base_url = "http://localhost/COREVOTA"; 
                $reset_link = $base_url . "/restablecer_contrasena.php?token=" . $token;
                $mail->Body    = "Ha solicitado restablecer su contraseña. Haga clic en el siguiente enlace para continuar: \n\n" . $reset_link;

                $mail->send();
                // Si se envía con éxito, el mensaje genérico ya se mostrará al usuario
                
            } catch (Exception $e) {
                // En caso de fallo, puedes registrar el error
                error_log("El correo NO se pudo enviar. Mailer Error: {$mail->ErrorInfo}");
                // ¡IMPORTANTE! El usuario seguirá viendo el mensaje genérico por seguridad.
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Contraseña - CORE Valparaíso</title>
    <style>
        /* Copia tus estilos aquí */
        body { margin: 0; font-family: Arial, sans-serif; height: 100vh; display: flex; justify-content: center; align-items: center; background: linear-gradient(90deg, #f2f2f2, #c5c4c4); }
        .login-box { background: white; padding: 40px; border-radius: 20px; box-shadow: 0px 4px 20px rgba(0, 0, 0, 0.1); width: 400px; text-align: center; }
        .login-box h2 { font-size: 18px; margin-bottom: 20px; }
        .login-box label { display: block; text-align: left; font-weight: bold; font-size: 12px; margin: 10px 0 5px; }
        .login-box input { width: 100%; padding: 12px; margin-bottom: 15px; border: 2px solid #000; border-radius: 12px; font-size: 14px; }
        .btn { width: 100%; padding: 14px; background: #06c167; color: white; font-weight: bold; border: none; border-radius: 12px; cursor: pointer; font-size: 16px; margin-top: 15px;}
        .btn:hover { background: #04a257; }
        .icon { font-size: 40px; margin-bottom: 10px; }
        .message-success { color: white; background: #27ae60; padding: 12px; border-radius: 5px; margin-bottom: 20px; font-size: 14px; }
        .message-error { color: white; background: #c0392b; padding: 12px; border-radius: 5px; margin-bottom: 20px; font-size: 14px; }
        .back-link { display: block; margin-top: 20px; font-size: 12px; color: #555; text-decoration: none; }
    </style>
</head>

<body>
    <div class="login-box">
        <div class="icon">📧</div>
        <h2>Recuperar Contraseña <br> Consejo Regional de Valparaíso</h2>

        <?php
        if (!empty($message)):
        ?>
            <div class="message-<?php echo htmlspecialchars($message_type); ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form action="" method="post">
            <label for="usuario_o_email">USUARIO O CORREO ELECTRÓNICO</label>
            <input type="text" id="usuario_o_email" name="usuario_o_email" required value="<?php echo htmlspecialchars($usuario_o_email); ?>">

            <button type="submit" class="btn">ENVIAR INSTRUCCIONES</button>
        </form>

        <a href="/COREVOTA/login.php" class="back-link">Volver al Inicio de Sesión</a>

    </div>
</body>
</html>