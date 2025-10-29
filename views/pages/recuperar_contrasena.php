<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/../../class/class.conectorDB.php";
require_once __DIR__ . '/../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$message = '';
$message_type = '';

$db = new conectorDB();
$pdo = $db->getDatabase();

// Captcha actual en sesión
$captcha_to_verify = $_SESSION['captcha_code'] ?? '';

// Generar/recargar el captcha si GET o no existe
if (empty($captcha_to_verify) || $_SERVER['REQUEST_METHOD'] === 'GET') {
    $_SESSION['captcha_code'] = substr(md5(microtime()), rand(0, 26), 5);
    $captcha_to_verify = $_SESSION['captcha_code'];
}
$captcha_display = $captcha_to_verify;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $correo_input  = trim($_POST['correo']  ?? '');
    $captcha_input = trim($_POST['captcha'] ?? '');

    // 1. Validar captcha
    if (strtolower($captcha_input) !== strtolower($captcha_to_verify)) {
        $message = 'El código de seguridad ingresado es incorrecto.';
        $message_type = 'error';

        // refrescar captcha tras error
        $_SESSION['captcha_code'] = substr(md5(microtime()), rand(0, 26), 5);
        $captcha_display = $_SESSION['captcha_code'];

    } else {
        // 2. Generar token único y expiración (1 hora)
        $token  = bin2hex(random_bytes(32));
        $expira = date("Y-m-d H:i:s", time() + 3600);

        // 3. Guardar token si existe el usuario
        $user_data = null;
        try {
            $user_data = $db->guardarTokenRestablecimiento($correo_input, $token, $expira);
        } catch (Throwable $t) {
            // silencio controlado (no rompemos UX)
        }

        // 4. Mensaje genérico (no decimos si el correo existe o no)
        $message = 'Si la dirección de correo electrónico está registrada, se han enviado instrucciones para restablecer la contraseña.';
        $message_type = 'success';

        // 5. Enviar el correo solo si el usuario existe
        if ($user_data && !empty($user_data['correo'])) {
            try {
                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'equiposieteduocuc@gmail.com'; // Mover a variable entorno en prod
                $mail->Password   = 'iohe aszm lkfl ucsq';         // Mover a variable entorno en prod
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;
                $mail->CharSet    = 'UTF-8';

                $mail->setFrom('no-responder@corevota.cl', 'CORE Vota');
                $mail->addAddress($user_data['correo']);

                // URL del link de restablecimiento
                $reset_link = "http://localhost/corevota/views/pages/restablecer_contrasena.php?token=" . urlencode($token);

                $mail->isHTML(true);
                $mail->Subject = 'Recuperación de Contraseña CORE Vota';
                $mail->Body    = "
                    <h2>Recuperación de Contraseña</h2>
                    <p>Has solicitado restablecer tu contraseña.</p>
                    <p>Haz clic en el siguiente enlace (válido por 1 hora):</p>
                    <p><a href=\"{$reset_link}\">Restablecer Contraseña</a></p>
                    <p>Si no solicitaste este cambio, ignora este mensaje.</p>
                ";

                $mail->AltBody = "Has solicitado restablecer tu contraseña.\n".
                                 "Enlace (válido por 1 hora):\n".
                                 $reset_link . "\n\n".
                                 "Si no solicitaste este cambio, ignora este mensaje.";

                $mail->send();
            } catch (Exception $e) {
                error_log("Error enviando correo de recuperación: " . $mail->ErrorInfo);
            }
        }

        // 6. Refrescar captcha para la siguiente vista
        $_SESSION['captcha_code'] = substr(md5(microtime()), rand(0, 26), 5);
        $captcha_display = $_SESSION['captcha_code'];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Recuperación de Contraseña</title>
    <style>
        body { font-family: Arial, sans-serif; display:flex; justify-content:center; align-items:center; min-height:100vh; background-color:#f5f5f5; }
        .recovery-box { background:white; padding:40px; border-radius:8px; box-shadow:0 4px 12px rgba(0,0,0,0.1); max-width:450px; width:90%; text-align:center; }
        .logo { max-width:150px; margin-bottom:30px; }
        .recovery-box h2 { font-size:1.5rem; margin-bottom:20px; font-weight:bold; }
        .recovery-box p { margin-bottom:25px; color:#555; }
        .input-group { margin-bottom:20px; text-align:left; }
        .input-group label { display:block; margin-bottom:5px; font-weight:bold; }
        .input-group input { width:100%; padding:12px; border:1px solid #ccc; border-radius:4px; box-sizing:border-box; }
        .captcha-container { display:flex; align-items:center; gap:15px; }
        .captcha-display { background:#000; color:white; padding:8px 15px; font-size:1.2rem; font-weight:bold; border-radius:4px; user-select:none; }
        .btn-black { background:black; color:white; border:none; padding:12px 0; font-size:1rem; cursor:pointer; width:100%; margin-top:10px; font-weight:bold; text-transform:uppercase; text-align:center; }
        .btn-white { background:white; color:black; border:1px solid black; padding:12px 0; font-size:1rem; cursor:pointer; width:100%; margin-top:10px; font-weight:bold; text-transform:uppercase; text-align:center; }
        .message-box { padding:10px; margin-bottom:15px; border-radius:4px; font-size:0.9rem; text-align:left; }
        .message-success { background-color:#d4edda; color:#155724; border:1px solid #c3e6cb; }
        .message-error { background-color:#f8d7da; color:#721c24; border:1px solid #f5c6cb; }
        .actions-row { display:flex; justify-content:space-between; gap:10px; }
    </style>
</head>
<body>
    <div class="recovery-box">
        <img src="/corevota/public/img/logoCore1.png" alt="CORE Vota Logo" class="logo">
        <h2>RECUPERACIÓN DE CONTRASEÑA</h2>
        <p>¿HAS OLVIDADO TU CONTRASEÑA?</p>

        <?php if ($message): ?>
            <div class="message-box message-<?php echo htmlspecialchars($message_type); ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form action="" method="post" autocomplete="off">
            <div class="input-group">
                <label for="correo">Email</label>
                <input type="email" id="correo" name="correo" required>
            </div>

            <div class="input-group">
                <label for="captcha">Código de Seguridad</label>
                <div class="captcha-container">
                    <span class="captcha-display"><?php echo htmlspecialchars($captcha_display); ?></span>
                    <input type="text" id="captcha" name="captcha" placeholder="Ingrese el código" style="flex-grow:1;" required>
                </div>
            </div>

            <div class="actions-row">
                <button type="submit" class="btn-black" style="width:48%;">RECUPERAR</button>
                <a href="/corevota/views/pages/login.php" class="btn-white" style="width:48%; line-height:2.2;">VOLVER</a>
            </div>
        </form>
    </div>
</body>
</html>
