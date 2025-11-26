<?php
// /coregedoc/controllers/obtener_adjuntos.php
// --- VERSIÓN CORREGIDA Y ROBUSTA (Maneja GET y POST) ---

header('Content-Type: application/json');
error_reporting(0); // Desactivar errores en producción

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Incluir dependencias
define('ROOT_PATH', dirname(__DIR__) . '/');
require_once ROOT_PATH . 'class/class.conectorDB.php';

// 2. Obtener datos de entrada y sesión
$idUsuarioLogueado = $_SESSION['idUsuario'] ?? null;

// Intentar leer idMinuta de la URL (GET) primero (usado por minutas_listado_general.php)
$idMinuta = $_GET['idMinuta'] ?? null;

// Si no se encontró en GET, intentar leer desde el cuerpo JSON (POST)
if (is_null($idMinuta)) {
    $data = json_decode(file_get_contents('php://input'), true);
    $idMinuta = $data['idMinuta'] ?? null;
}

if (!$idMinuta || !is_numeric($idMinuta) || !$idUsuarioLogueado) {
    // Usamos 400 Bad Request si faltan datos
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Acceso no autorizado o datos incompletos.', 'adjuntos' => []]);
    exit;
}

$db = null;
try {
    $db = new conectorDB();
    $pdo = $db->getDatabase();

    // 3. CONSULTA SQL
    $sql = "SELECT idAdjunto, pathAdjunto, tipoAdjunto 
        FROM t_adjunto 
        WHERE t_minuta_idMinuta = :idMinuta
        AND tipoAdjunto <> 'asistencia'"; //aqui añadimos la excepcion para que no se muestre la asistencia en el pdf adjuunto. si quisieran verlo, aqui esta la sql comentada

    //$sql = "SELECT idAdjunto, pathAdjunto, tipoAdjunto 
    //  FROM t_adjunto 
    // WHERE t_minuta_idMinuta = :idMinuta";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':idMinuta' => $idMinuta]);
    $adjuntos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. Procesar resultados para el JSON
    $adjuntosProcesados = [];
    foreach ($adjuntos as $adjunto) {
        $pathOriginal = $adjunto['pathAdjunto'];
        $tipo = $adjunto['tipoAdjunto'];
        $pathWebFinal = '';

        // Corregir la ruta web-accessible
        if ($tipo === 'link') {
            $pathWebFinal = $pathOriginal;
            $nombreArchivo = $pathOriginal;
        } else {
            // Asumimos que la BD guarda la ruta relativa (e.g., 'public/docs/...')
            $pathCorregido = (strpos($pathOriginal, 'public/') === 0) ? $pathOriginal : ('public/docs/' . $pathOriginal);

            // ----------------------------------------------------
            // !!! INICIO DE LA CORRECCIÓN DE LA RUTA FINAL !!!
            // ----------------------------------------------------

            // Verificamos si la extensión (ej. .png) ya está en la ruta guardada en la BD.
            // Si no está, la añadimos. Esto asume que el archivo SIEMPRE es .png en esa carpeta.
            $extensionFaltante = '.png';

            if (!str_ends_with(strtolower($pathCorregido), $extensionFaltante)) {
                $pathCorregido .= $extensionFaltante;
            }

            // Construir la URL absoluta para el navegador
            $pathWebFinal = '/coregedoc/' . $pathCorregido;
            $nombreArchivo = basename($pathOriginal) . $extensionFaltante; // Aseguramos que el nombre mostrado también tenga la extensión

            // ----------------------------------------------------
            // !!! FIN DE LA CORRECCIÓN DE LA RUTA FINAL !!!
            // ----------------------------------------------------
        }

        $adjuntosProcesados[] = [
            'idAdjunto'   => (int)$adjunto['idAdjunto'],
            'tipoAdjunto'  => $tipo,
            'nombreArchivo' => $nombreArchivo,
            'pathArchivo'  => $pathWebFinal
        ];
    }


    // 5. Devolver la respuesta en JSON
    echo json_encode(['status' => 'success', 'adjuntos' => $adjuntosProcesados]);
} catch (Exception $e) {
    error_log("Error en obtener_adjuntos.php (Minuta ID: $idMinuta): " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error al consultar la base de datos.', 'adjuntos' => []]);
} finally {
    $pdo = null;
    $db = null;
}
