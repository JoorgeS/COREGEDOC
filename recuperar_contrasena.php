<?php
// Usar la forma segura de iniciar sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Incluir clases (Ajusta la ruta si es necesario)
require_once __DIR__ . "/class/class.conectorDB.php"; 
require 'vendor/autoload.php'; 

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$message = '';
$message_type = '';
$db = new conectorDB();
$captcha_to_verify = $_SESSION['captcha_code'] ?? ''; 

// --- Lógica de Generación de Captcha ---
// Solo generar un nuevo CAPTCHA si no existe uno o si venimos de un envío NO exitoso.
if (empty($captcha_to_verify) || (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'GET')) {
    $_SESSION['captcha_code'] = substr(md5(microtime()), rand(0, 26), 5);
    $captcha_to_verify = $_SESSION['captcha_code'];
}
$captcha_display = $captcha_to_verify;


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo_input = trim($_POST['correo']);
    $captcha_input = trim($_POST['captcha']);
    
    // 2. VERIFICACIÓN DEL CAPTCHA
    if (strtolower($captcha_input) !== strtolower($captcha_to_verify)) {
        $message = 'El código de seguridad ingresado es incorrecto.';
        $message_type = 'error';
        
        // Generar un nuevo CAPTCHA inmediatamente para el siguiente intento
        $_SESSION['captcha_code'] = substr(md5(microtime()), rand(0, 26), 5);
        $captcha_display = $_SESSION['captcha_code']; 
        
    } else {
        // --- INICIO DE LA LÓGICA DE ENVÍO DE CORREO (AQUÍ DEBE IR TU CÓDIGO) ---
        
        // 1. Generar Token y Expiración
        $token = bin2hex(random_bytes(32)); 
        $expira = date("Y-m-d H:i:s", time() + 3600); 

        // 2. BUSCAR USUARIO y GUARDAR TOKEN en DB
        $user_data = $db->guardarTokenRestablecimiento($correo_input, $token, $expira);
        
        // 3. Mensaje de seguridad
        $message = 'Si la dirección de correo electrónico está registrada, se han enviado instrucciones para restablecer la contraseña.';
        $message_type = 'success';

        if ($user_data && $user_data['correo']) {
            // 4. ENVIAR CORREO (AJUSTAR CREDENCIALES)
            try {
                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com'; 
                $mail->SMTPAuth   = true;
                $mail->Username   = 'equiposieteduocuc@gmail.com'; 
                $mail->Password   = 'iohe aszm lkfl ucsq'; 
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;
                $mail->CharSet = 'UTF-8';

                $mail->setFrom('no-responder@corevota.cl', 'CORE Vota');
                $mail->addAddress($user_data['correo']);
                
                $reset_link = "http://localhost/corevota/restablecer_contrasena.php?token=" . $token;

                $mail->isHTML(true);
                $mail->Subject = 'Recuperación de Contraseña CORE Vota';
                $mail->Body    = "
                    <h2>Recuperación de Contraseña</h2>
                    <p>Has solicitado restablecer tu contraseña. Haz clic en el enlace a continuación:</p>
                    <p><a href=\"{$reset_link}\">Restablecer Contraseña</a></p>
                ";
                $mail->send();
            } catch (Exception $e) {
                error_log("Error enviando correo de recuperación: " . $mail->ErrorInfo);
            }
        }
        
        // --- FIN DE LA LÓGICA DE ENVÍO DE CORREO ---
        
        // Generar un NUEVO CAPTCHA para la próxima vez
        $_SESSION['captcha_code'] = substr(md5(microtime()), rand(0, 26), 5);
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Recuperación de Contraseña</title>
    <style> 
        /* [PEGA AQUÍ TUS ESTILOS CSS COMPLETOS] */
        body { font-family: Arial, sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; background-color: #f5f5f5; }
        .recovery-box { background: white; padding: 40px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); max-width: 450px; width: 90%; text-align: center; }
        .logo { max-width: 150px; margin-bottom: 30px; }
        .recovery-box h2 { font-size: 1.5rem; margin-bottom: 20px; font-weight: bold; }
        .recovery-box p { margin-bottom: 25px; color: #555; }
        .input-group { margin-bottom: 20px; text-align: left; }
        .input-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .input-group input { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .captcha-container { display: flex; align-items: center; gap: 15px; }
        .captcha-display { background: #000; color: white; padding: 8px 15px; font-size: 1.2rem; font-weight: bold; border-radius: 4px; user-select: none; }
        .btn-black { background: black; color: white; border: none; padding: 12px 0; font-size: 1rem; cursor: pointer; width: 100%; margin-top: 10px; font-weight: bold; text-transform: uppercase; }
        .btn-white { background: white; color: black; border: 1px solid black; padding: 12px 0; font-size: 1rem; cursor: pointer; width: 100%; margin-top: 10px; font-weight: bold; text-transform: uppercase; text-align: center;}
        .message-box { padding: 10px; margin-bottom: 15px; border-radius: 4px; font-size: 0.9rem; }
        .message-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message-error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .actions-row { display: flex; justify-content: space-between; gap: 10px; }
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

        <form action="" method="post">
            <div class="input-group">
                <label for="correo">Email</label>
                <input type="email" id="correo" name="correo" required>
            </div>

            <div class="input-group">
                <label for="captcha">Código de Seguridad</label>
                <div class="captcha-container">
                    <span class="captcha-display"><?php echo htmlspecialchars($captcha_display); ?></span>
                    <input type="text" id="captcha" name="captcha" placeholder="Ingrese el código" style="flex-grow: 1;" required>
                </div>
            </div>

            <div class="actions-row">
                <button type="submit" class="btn-black" style="width: 48%;">RECUPERAR</button>
                <a href="/corevota/views/pages/login.php" class="btn-white" style="width: 48%; line-height: 2.2;">VOLVER</a>
            </div>
        </form>
    </div>
</body>
</html>