<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/../../class/class.conectorDB.php";

$db = new conectorDB();
$pdo = $db->getDatabase();

$message = '';
$message_type = '';

$token = $_GET['token'] ?? '';
$user_id = null;

// === VALIDAR TOKEN ===
if (empty($token)) {
    $message = 'El enlace de restablecimiento es inválido o ha expirado.';
    $message_type = 'error';
} else {
    try {
        $sql_select = "SELECT idUsuario FROM t_usuario WHERE reset_token = :token AND reset_expira > NOW()";
        $stmt_select = $pdo->prepare($sql_select);
        $stmt_select->execute(['token' => $token]);
        $user = $stmt_select->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $user_id = $user['idUsuario'];
        } else {
            $message = 'El enlace de restablecimiento es inválido o ha expirado.';
            $message_type = 'error';
        }
    } catch (PDOException $e) {
        $message = 'Error al verificar el enlace. Intente nuevamente más tarde.';
        $message_type = 'error';
    }
}

// === PROCESAR FORMULARIO DE NUEVA CONTRASEÑA ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user_id) {
    $new_password = $_POST['contrasena'] ?? '';
    $confirm_password = $_POST['confirmar_contrasena'] ?? '';

    if (empty($new_password) || empty($confirm_password)) {
        $message = 'Por favor, complete todos los campos.';
        $message_type = 'error';
    } elseif ($new_password !== $confirm_password) {
        $message = 'Las contraseñas no coinciden.';
        $message_type = 'error';
    } else {
        $errors = [];
        if (strlen($new_password) < 8) {
            $errors[] = 'Debe tener al menos 8 caracteres.';
        }
        if (!preg_match('/[A-Z]/', $new_password)) {
            $errors[] = 'Debe incluir al menos una letra mayúscula.';
        }
        if (!preg_match('/[a-z]/', $new_password)) {
            $errors[] = 'Debe incluir al menos una letra minúscula.';
        }
        if (!preg_match('/[\W_]/', $new_password)) {
            $errors[] = 'Debe incluir al menos un símbolo o carácter especial.';
        }

        if (!empty($errors)) {
            $message = 'La contraseña no cumple con los requisitos:<br>- ' . implode('<br>- ', $errors);
            $message_type = 'error';
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

            try {
                $sql_update = "UPDATE t_usuario 
                               SET contrasena = :pass, reset_token = NULL, reset_expira = NULL 
                               WHERE idUsuario = :id";
                $stmt_update = $pdo->prepare($sql_update);
                $stmt_update->execute([
                    'pass' => $hashed_password,
                    'id' => $user_id
                ]);

                $message = '¡Contraseña restablecida con éxito! Ahora puede iniciar sesión.';
                $message_type = 'success';
                $user_id = null;
            } catch (PDOException $e) {
                $message = 'Error al actualizar la contraseña. Intente nuevamente.';
                $message_type = 'error';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Restablecer Contraseña</title>
    <link href="/coregedoc/public/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .recovery-box {
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            max-width: 450px;
            width: 90%;
            text-align: center;
        }
        .logo {
            max-width: 150px;
            margin-bottom: 30px;
        }
        .recovery-box h2 {
            font-size: 1.5rem;
            margin-bottom: 20px;
            font-weight: bold;
        }
        .input-group {
            margin-bottom: 20px;
            text-align: left;
            position: relative;
        }
        .input-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .input-group input {
            width: 100%;
            padding: 12px 40px 12px 12px; /* espacio para el ícono */
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .toggle-password {
            position: absolute;
            right: 12px;
            top: 39px;
            cursor: pointer;
            color: #666;
        }
        .toggle-password:hover {
            color: #000;
        }
        .btn-black {
            background: black;
            color: white;
            border: none;
            padding: 12px 0;
            font-size: 1rem;
            cursor: pointer;
            width: 100%;
            margin-top: 10px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .message-box {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
            font-size: 0.9rem;
        }
        .message-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .message-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            text-align: left;
        }
        .password-rules {
            background: #fafafa;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: .8rem;
            line-height: 1.4rem;
            padding: 12px 15px;
            margin-top: -5px;
            margin-bottom: 15px;
            text-align: left;
        }
        .password-rules ul {
            padding-left: 18px;
            margin: 0;
        }
        .password-rules li {
            margin-bottom: 6px;
            font-weight: 500;
        }
        .rule-ok {
            color: #155724;
            font-weight: bold;
        }
        .rule-fail {
            color: #721c24;
            font-weight: bold;
        }
    </style>
</head>

<body>
<div class="recovery-box">
    <img src="/coregedoc/public/img/logoCore1.png" alt="CORE Vota Logo" class="logo">
    <h2>RESTABLECER CONTRASEÑA</h2>

    <?php if ($message): ?>
        <div class="message-box message-<?php echo htmlspecialchars($message_type); ?>">
            <?php echo $message; ?>
            <?php if ($message_type === 'success'): ?>
                <p><a href="/coregedoc/views/pages/login.php" style="color:#155724;">Ir a Iniciar Sesión</a></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($user_id && $message_type !== 'success'): ?>
        <form action="" method="post" autocomplete="off">
            <div class="input-group">
                <label for="contrasena">Nueva Contraseña</label>
                <input
                    type="password"
                    id="contrasena"
                    name="contrasena"
                    required
                    placeholder="Ej: MiClave@2025"
                    pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*[\W_]).{8,}"
                    title="Debe tener al menos 8 caracteres, una mayúscula, una minúscula y un símbolo."
                    oninput="validarPasswordCliente()"
                >
                <i class="fas fa-eye toggle-password" id="togglePass1" onclick="togglePassword('contrasena', 'togglePass1')"></i>
            </div>

            <div class="input-group">
                <label for="confirmar_contrasena">Confirmar Contraseña</label>
                <input
                    type="password"
                    id="confirmar_contrasena"
                    name="confirmar_contrasena"
                    required
                    oninput="validarPasswordCliente()"
                >
                <i class="fas fa-eye toggle-password" id="togglePass2" onclick="togglePassword('confirmar_contrasena', 'togglePass2')"></i>
            </div>

            <div class="password-rules" id="password-rules">
                <strong>La contraseña debe cumplir:</strong>
                <ul class="list-unstyled m-0">
                    <li id="rule-length"  class="rule-fail">• Mínimo 8 caracteres</li>
                    <li id="rule-upper"   class="rule-fail">• Al menos 1 mayúscula (A-Z)</li>
                    <li id="rule-lower"   class="rule-fail">• Al menos 1 minúscula (a-z)</li>
                    <li id="rule-symbol"  class="rule-fail">• Al menos 1 símbolo (!@#...)</li>
                    <li id="rule-match"   class="rule-fail">• Ambas contraseñas coinciden</li>
                </ul>
            </div>

            <button type="submit" class="btn-black">ACTUALIZAR CONTRASEÑA</button>
        </form>
    <?php endif; ?>
</div>

<script>
function validarPasswordCliente() {
    const p1 = document.getElementById('contrasena').value;
    const p2 = document.getElementById('confirmar_contrasena').value;

    const cumpleLargo   = p1.length >= 8;
    const cumpleMayus   = /[A-Z]/.test(p1);
    const cumpleMinus   = /[a-z]/.test(p1);
    const cumpleSimbolo = /[\W_]/.test(p1);
    const coincide      = (p1 !== '' && p1 === p2);

    setRuleState('rule-length',  cumpleLargo);
    setRuleState('rule-upper',   cumpleMayus);
    setRuleState('rule-lower',   cumpleMinus);
    setRuleState('rule-symbol',  cumpleSimbolo);
    setRuleState('rule-match',   coincide);
}

function setRuleState(id, ok) {
    const li = document.getElementById(id);
    if (!li) return;
    if (ok) {
        li.classList.remove('rule-fail');
        li.classList.add('rule-ok');
    } else {
        li.classList.remove('rule-ok');
        li.classList.add('rule-fail');
    }
}

function togglePassword(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    if (input.type === "password") {
        input.type = "text";
        icon.classList.remove("fa-eye");
        icon.classList.add("fa-eye-slash");
    } else {
        input.type = "password";
        icon.classList.remove("fa-eye-slash");
        icon.classList.add("fa-eye");
    }
}
</script>

</body>
</html>
