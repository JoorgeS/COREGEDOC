<?php
// /corevota/controllers/obtener_adjuntos.php
// --- VERSIÓN CORREGIDA BASADA EN TU TABLA t_adjunto ---

header('Content-Type: application/json');
error_reporting(0); // Desactivar errores en producción

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Incluir dependencias
define('ROOT_PATH', dirname(__DIR__) . '/');
require_once ROOT_PATH . 'class/class.conectorDB.php';

// 2. Obtener datos de entrada y sesión
$data = json_decode(file_get_contents('php://input'), true);
$idMinuta = $data['idMinuta'] ?? null;
$idUsuarioLogueado = $_SESSION['idUsuario'] ?? null;

if (!$idMinuta || !is_numeric($idMinuta) || !$idUsuarioLogueado) {
    echo json_encode(['status' => 'error', 'message' => 'Acceso no autorizado o datos incompletos.', 'adjuntos' => []]);
    exit;
}

$db = null;
try {
    $db = new conectorDB();
    $pdo = $db->getDatabase();

    // 3. --- CONSULTA SQL CORREGIDA ---
    // Basado en tu tabla t_adjunto:
    // - Seleccionamos de t_adjunto
    // - Filtramos por t_minuta_idMinuta (el ID que nos llega)
    // - Filtramos por tipoAdjunto = 'file' (para ignorar los de 'asistencia')
    
    $sql = "SELECT 
                idAdjunto, 
                pathAdjunto
            FROM t_adjunto
            WHERE t_minuta_idMinuta = :idMinuta
            AND tipoAdjunto = 'file'"; // <-- ¡Este filtro es clave!

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':idMinuta' => $idMinuta]);
    $adjuntos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. Procesar resultados para el JSON
    // Tu tabla t_adjunto tiene rutas inconsistentes.
    // Fila 1: DocumentosAdjuntos/PNG/adj_...
    // Fila 9: public/docs/asistencia/minuta_...
    // Este código corrige eso para que JavaScript reciba una URL web válida.

    $adjuntosProcesados = [];
    foreach ($adjuntos as $adjunto) {
        
        $pathOriginal = $adjunto['pathAdjunto'];
        $pathCorregido = $pathOriginal;

        // Si el path NO empieza con 'public/docs/' (como la Fila 1)
        if (strpos($pathCorregido, 'public/') !== 0) {
            // ...entonces asumimos que es un 'file' y le prefijamos la ruta de documentos
            $pathCorregido = 'public/' . $pathCorregido;
        }
        
        // Ahora, todos los paths son 'public/docs/...'
        // Finalmente, prefijamos la raíz del proyecto '/corevota/'
        $pathWebFinal = '/corevota/' . $pathCorregido;


        $adjuntosProcesados[] = [
            // Extrae solo el nombre del archivo para mostrarlo
            'nombreArchivo' => basename($pathOriginal), 
            // Envía la ruta web completa y corregida
            'pathArchivo'   => $pathWebFinal 
        ];
    }


    // 5. Devolver la respuesta en JSON
    echo json_encode(['status' => 'success', 'adjuntos' => $adjuntosProcesados]);

} catch (Exception $e) {
    error_log("Error en obtener_adjuntos.php (Minuta ID: $idMinuta): " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Error al consultar la base de datos.', 'adjuntos' => []]);
} finally {
    $pdo = null;
    $db = null;
}