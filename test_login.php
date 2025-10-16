<?php
// Incluye las clases necesarias
require_once 'cfg/config.php'; 
require_once 'class/class.conectorDB.php';

// ----------------------------------------------------
// DATOS A PROBAR
// ----------------------------------------------------
// Usuario: Debe existir en t_usuario
$usuario_a_probar = 'pedro1994_02@gmail.com'; 
// Contraseña: La clave en texto plano que usas para iniciar sesión (ej. '123')
$clave_ingresada = '123'; 

echo "<h2>Prueba de Validación de Contraseña (test_login.php)</h2>";
echo "Intentando usuario: <strong>" . $usuario_a_probar . "</strong> con clave: <strong>" . $clave_ingresada . "</strong><br><br>";

try {
    // 1. Conectar a la BD
    $conector = new conectorDB(); 
    $db = $conector->getDatabase(); 

    // 2. Ejecutar la misma consulta que el login.php
    $sql = 'SELECT idUsuario, correo, contrasena 
            FROM t_usuario 
            WHERE correo = :user_input';
    
    $stmt = $db->prepare($sql);
    $stmt->execute([':user_input' => $usuario_a_probar]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        echo "✅ **Paso 1: Usuario encontrado.**<br>";
        $hash_db = $user['contrasena'];
        echo "Hash de la BD: <code>" . htmlspecialchars($hash_db) . "</code><br><br>";

        // 3. Ejecutar password_verify
        $es_valida = password_verify($clave_ingresada, $hash_db);

        echo "⭐ **Paso 2: Resultado de password_verify()**:<br>";
        if ($es_valida) {
            echo "   <strong style='color: green;'>¡VALIDACIÓN EXITOSA!</strong> La contraseña ingresada es CORRECTA.<br>";
        } else {
            echo "   <strong style='color: red;'>¡VALIDACIÓN FALLIDA!</strong> La contraseña ingresada es INCORRECTA para este hash.<br>";
        }
    } else {
        echo "❌ Usuario <strong>" . $usuario_a_probar . "</strong> NO encontrado en la base de datos.";
    }

} catch (PDOException $e) {
    echo "<h1>❌ Error de Conexión o Consulta.</h1>";
    echo "<p>Mensaje de error: " . $e->getMessage() . "</p>";
}
?>