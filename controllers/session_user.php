<?php
session_start();

ini_set('session.gc_maxlifetime', 3600);
session_set_cookie_params(3600);

if (isset($_SESSION["pNombre"]) && isset($_SESSION["aPaterno"])) {
    $nombre = $_SESSION["pNombre"] . ' ' . $_SESSION["aPaterno"];
    echo json_encode(["nombreUsuario" => $nombre]);
} else {
    echo json_encode(["nombreUsuario" => "No definido"]);
}
?>
