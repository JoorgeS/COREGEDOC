<?php
session_start();

if (isset($_SESSION["pNombre"]) && isset($_SESSION["aPaterno"])) {
    $nombre = $_SESSION["pNombre"] . ' ' . $_SESSION["aPaterno"];
    echo json_encode(["nombreUsuario" => $nombre]);
} else {
    echo json_encode(["nombreUsuario" => "No definido"]);
}
?>
