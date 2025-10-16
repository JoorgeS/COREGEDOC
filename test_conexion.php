<?php
// Incluye la clase que contiene el código de conexión (config.php)
require_once 'cfg/config.php'; 
require_once 'class/class.conectorDB.php'; // Si tienes una clase que lo usa

// 1. Instanciar la clase de conexión (o la clase que la usa)
// Usaremos la clase conectorDB si es la que usa la configuración
try {
    // ASUMIMOS que conectorDB.php usa la lógica de configuración para conectar
    // Si la conexión se establece correctamente, la variable $db tendrá el objeto PDO.
    $conector = new conectorDB(); 
    $db = $conector->getDatabase(); // Asume que conectorDB tiene un método para obtener la conexión PDO

    // Si llegamos a esta línea, la conexión fue exitosa.
    echo "<h1>✅ ¡Conexión Exitosa!</h1>";
    
    // Opcional: Ejecutar una pequeña consulta para verificar la comunicación
    $stmt = $db->query('SELECT NOW() AS hora_actual');
    $hora = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p>El servidor de la base de datos está respondiendo. Hora: " . $hora['hora_actual'] . "</p>";

    // Cerrar la conexión (PDO la cierra automáticamente, pero puedes anularla si es necesario)
    $db = null; 

} catch (PDOException $e) {
    // Si la conexión falla, se ejecuta este bloque y se muestra el error.
    echo "<h1>❌ Error de Conexión o Base de Datos.</h1>";
    echo "<p>Mensaje de error: " . $e->getMessage() . "</p>";
} catch (Exception $e) {
    // Para errores generales (como si no encuentra el archivo .ini)
    echo "<h1>❌ Error General.</h1>";
    echo "<p>Mensaje de error: " . $e->getMessage() . "</p>";
}

?>