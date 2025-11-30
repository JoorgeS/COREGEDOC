<?php
// /COREGEDOC/logout.php

// 1. Inicia la sesión
session_start();

// 2. Destruye todas las variables de sesión (limpia el array $_SESSION)
$_SESSION = array();

// 3. Borra la cookie de sesión (la mejor práctica para una limpieza completa)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 4. Finalmente, destruye la sesión
session_destroy();

// 5. Redirige al login (el punto de entrada index.php)
header("Location: /coregedoc/index.php");
exit;
?>