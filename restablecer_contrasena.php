<?php
session_start();
require_once __DIR__ . "/class/class.conectorDB.php"; 

$message = '';
$message_type = '';
$db = new conectorDB();
$token = $_GET['token'] ?? '';
$user_id = null; 

// 1. VALIDAR TOKEN
if (empty($token)) {
    $message = 'El enlace de restablecimiento es inválido o ha expirado.';
    $message_type = 'error';
} else {
    try {
        // Buscar usuario por token y verificar que no haya expirado (NOW() en SQL)
        // NOTA: Reemplaza 'idUsuario' si tu clave primaria es diferente.
        $sql_select = "SELECT idUsuario FROM t_usuario WHERE reset_token = :token AND reset_expira > NOW()";
        $stmt_select = $db->getDatabase()->prepare($sql_select);
        $stmt_select->execute(['token' => $token]);
        $user = $stmt_select->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $user_id = $user['idUsuario'];
        } else {
            $message = 'El enlace de restablecimiento es inválido o ha expirado.';
            $message_type = 'error';
        }
    } catch (PDOException $e) {
        $message = 'Error de conexión. Intente más tarde.';
        $message_type = 'error';
    }
}

// 2. PROCESAR EL FORMULARIO DE NUEVA CONTRASEÑA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user_id) {
    $new_password = $_POST['contrasena'];
    $confirm_password = $_POST['confirmar_contrasena'];

    if (empty($new_password) || $new_password !== $confirm_password) {
        $message = 'Las contraseñas no coinciden o están vacías.';
        $message_type = 'error';
    } else {
        // HASH SEGURO DE LA CONTRASEÑA
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        try {
            // ACTUALIZAR LA CONTRASEÑA Y LIMPIAR EL TOKEN DE SEGURIDAD
            $sql_update = "UPDATE t_usuario SET contrasena = :pass, reset_token = NULL, reset_expira = NULL WHERE idUsuario = :id";
            $stmt_update = $db->getDatabase()->prepare($sql_update);
            $stmt_update->execute([
                'pass' => $hashed_password,
                'id' => $user_id
            ]);

            $message = '¡Contraseña restablecida con éxito! Ahora puede iniciar sesión.';
            $message_type = 'success';
            $user_id = null; // Invalida el formulario
            
        } catch (PDOException $e) {
            $message = 'Error al actualizar la contraseña.';
            $message_type = 'error';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Restablecer Contraseña</title>
    <style> 
        /* [PEGA AQUÍ LOS ESTILOS DEL PRIMER ARCHIVO (recuperar_contrasena.php)] */
        body { font-family: Arial, sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; background-color: #f5f5f5; }
        .recovery-box { background: white; padding: 40px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); max-width: 450px; width: 90%; text-align: center; }
        .logo { max-width: 150px; margin-bottom: 30px; }
        .recovery-box h2 { font-size: 1.5rem; margin-bottom: 20px; font-weight: bold; }
        .recovery-box p { margin-bottom: 25px; color: #555; }
        .input-group { margin-bottom: 20px; text-align: left; }
        .input-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .input-group input { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .btn-black { background: black; color: white; border: none; padding: 12px 0; font-size: 1rem; cursor: pointer; width: 100%; margin-top: 10px; font-weight: bold; text-transform: uppercase; }
        .message-box { padding: 10px; margin-bottom: 15px; border-radius: 4px; font-size: 0.9rem; }
        .message-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message-error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        /* Puedes omitir las clases captcha-container y btn-white */
    </style>
</head>
<body>
    <div class="recovery-box">
        <img src="/corevota/public/img/logoCore1.png" alt="CORE Vota Logo" class="logo">
        <h2>RESTABLECER CONTRASEÑA</h2>

        <?php if ($message): ?>
            <div class="message-box message-<?php echo htmlspecialchars($message_type); ?>">
                <?php echo htmlspecialchars($message); ?>
                <?php if ($message_type === 'success'): ?>
                    <p><a href="/corevota/views/pages/login.php" style="color: #155724;">Ir a Iniciar Sesión</a></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($user_id && $message_type !== 'success'): // Muestra el formulario solo si el token es válido ?>
        <form action="" method="post">
            <div class="input-group">
                <label for="contrasena">Nueva Contraseña</label>
                <input type="password" id="contrasena" name="contrasena" required>
            </div>
            <div class="input-group">
                <label for="confirmar_contrasena">Confirmar Contraseña</label>
                <input type="password" id="confirmar_contrasena" name="confirmar_contrasena" required>
            </div>

            <button type="submit" class="btn-black">ACTUALIZAR CONTRASEÑA</button>
        </form>
        <?php endif; ?>
    </div>
</body>
</html>