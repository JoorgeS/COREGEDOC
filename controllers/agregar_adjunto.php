<?php
// /corevota/controllers/agregar_adjunto.php

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Incluir dependencias
define('ROOT_PATH', dirname(__DIR__) . '/');
require_once ROOT_PATH . 'class/class.conectorDB.php';

// 2. Validar sesión y datos de entrada
$idUsuarioLogueado = $_SESSION['idUsuario'] ?? null;
$idMinuta = $_POST['idMinuta'] ?? null;
$tipoAdjunto = $_POST['tipoAdjunto'] ?? null; // 'file' o 'link'

if (!$idUsuarioLogueado || !$idMinuta || !$tipoAdjunto) {
    echo json_encode(['status' => 'error', 'message' => 'Datos insuficientes o sesión no válida.']);
    exit;
}

$db = null;
try {
    $db = new conectorDB();
    $pdo = $db->getDatabase();

    $dbPath = '';
    $nombreOriginal = '';
    $webPath = '';

    // --------------------------------------------------
    // CASO 1: Se está subiendo un ARCHIVO ('file')
    // --------------------------------------------------
    if ($tipoAdjunto === 'file') {
        if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Error al recibir el archivo. Código: ' . ($_FILES['archivo']['error'] ?? 'N/A'));
        }

        $file = $_FILES['archivo'];
        $nombreOriginal = basename($file['name']);

        // 1. Obtener extensión (ej: 'png', 'xlsx') y crear carpeta
        $extension = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));
        if (empty($extension)) {
            $extension = 'misc'; // Carpeta para archivos sin extensión
        }
        $folderExtension = strtoupper($extension); // 'PNG', 'XLSX'

        // 2. Definir rutas
        $baseSaveDir = ROOT_PATH . 'public/docs/DocumentosAdjuntos/';
        $targetDir = $baseSaveDir . $folderExtension . '/'; // ej: .../DocumentosAdjuntos/PNG/

        // 3. Crear directorio si no existe (replicando tu estructura)
        if (!is_dir($targetDir)) {
            if (!mkdir($targetDir, 0775, true)) {
                throw new Exception('No se pudo crear el directorio de destino: ' . $targetDir);
            }
        }

        // 4. Crear nombre de archivo único
        $uniqueName = 'adj_' . uniqid() . '_' . preg_replace("/[^a-zA-Z0-9._-]/", "_", $nombreOriginal);
        $physicalPath = $targetDir . $uniqueName;

        // 5. Mover el archivo
        if (!move_uploaded_file($file['tmp_name'], $physicalPath)) {
            throw new Exception('No se pudo mover el archivo subido.');
        }

        // 6. Definir rutas para BD y respuesta JSON
        $dbPath = 'DocumentosAdjuntos/' . $folderExtension . '/' . $uniqueName;
        $webPath = '/corevota/public/docs/' . $dbPath;

        // --------------------------------------------------
        // CASO 2: Se está guardando un ENLACE ('link')
        // --------------------------------------------------
    } elseif ($tipoAdjunto === 'link') {
        $urlLink = $_POST['urlLink'] ?? null;
        if (empty($urlLink) || !filter_var($urlLink, FILTER_VALIDATE_URL)) {
            throw new Exception('La URL proporcionada no es válida.');
        }
        $dbPath = $urlLink;
        $nombreOriginal = $urlLink; // Para links, el nombre y el path son lo mismo
        $webPath = $urlLink;
    } else {
        throw new Exception('Tipo de adjunto no reconocido.');
    }

    // 7. Insertar en la Base de Datos
    $sql = "INSERT INTO t_adjunto (pathAdjunto, t_minuta_idMinuta, tipoAdjunto) 
            VALUES (:path, :idMinuta, :tipo)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':path' => $dbPath,
        ':idMinuta' => $idMinuta,
        ':tipo' => $tipoAdjunto
    ]);

    $lastId = $pdo->lastInsertId();

    // 8. Devolver respuesta éxitosa con los datos del nuevo adjunto
    $nuevoAdjunto = [
        'idAdjunto'     => (int)$lastId,
        'tipoAdjunto'   => $tipoAdjunto,
        'nombreArchivo' => $nombreOriginal, // El nombre original o la URL
        'pathArchivo'   => $webPath         // La ruta web o la URL
    ];

    echo json_encode(['status' => 'success', 'nuevoAdjunto' => $nuevoAdjunto]);
} catch (Exception $e) {
    error_log("Error en agregar_adjunto.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
} finally {
    $pdo = null;
    $db = null;
}
