<?php
// La contraseña de prueba que quieres usar
$password = '123'; 
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

echo "Contraseña de prueba: " . $password . "<br>";
echo "HASH generado: <b>" . $hashed_password . "</b><br><br>";
echo "Copia el texto del HASH (comienza con \$2y\$...)";
?>