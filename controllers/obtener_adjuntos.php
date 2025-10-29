<?php
// /corevota/controllers/obtener_adjuntos.php
// --- VERSIÓN ACTUALIZADA (v2) ---
// Ahora devuelve idAdjunto y tipoAdjunto para la página de EDICIÓN.

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

    // 3. --- CONSULTA SQL ACTUALIZADA ---
    // Ahora seleccionamos MÁS campos: idAdjunto y tipoAdjunto
    $sql = "SELECT 
                idAdjunto, 
                pathAdjunto,
                tipoAdjunto 
            FROM t_adjunto
            WHERE t_minuta_idMinuta = :idMinuta";
    // Quitamos el filtro de 'file' para que también traiga los 'link'

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':idMinuta' => $idMinuta]);
    $adjuntos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. Procesar resultados para el JSON
    $adjuntosProcesados = [];
    foreach ($adjuntos as $adjunto) {

        $pathOriginal = $adjunto['pathAdjunto'];
        $tipo = $adjunto['tipoAdjunto'];
        $pathWebFinal = '';

        if ($tipo === 'link') {
            // Si es un link, el path es la URL completa
            $pathWebFinal = $pathOriginal;
            $nombreArchivo = $pathOriginal; // Para links, el nombre es la URL
        } else {
            // Si es un archivo (file, asistencia, etc.), construimos la ruta
            $pathCorregido = $pathOriginal;
            if (strpos($pathCorregido, 'public/') !== 0) {
                // Asumimos que es un 'file' y le prefijamos la ruta
                $pathCorregido = 'public/docs/' . $pathCorregido;
            }
            $pathWebFinal = '/corevota/' . $pathCorregido;
            $nombreArchivo = basename($pathOriginal); // Extrae solo el nombre
        }

        $adjuntosProcesados[] = [
            'idAdjunto'     => (int)$adjunto['idAdjunto'], // <-- ¡NUEVO!
            'tipoAdjunto'   => $tipo,                       // <-- ¡NUEVO!
            'nombreArchivo' => $nombreArchivo,
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
