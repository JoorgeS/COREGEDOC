<?php
// controllers/agregar_adjunto.php

// ----------------------------------------------------------------------
// (CORREGIDO) Configuración de errores: NO MOSTRAR (rompe JSON), SÍ registrarlos.
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);
// ----------------------------------------------------------------------

require_once __DIR__ . '/../cfg/config.php';
require_once __DIR__ . '/../class/class.conectorDB.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// (Recomendado) Verificar sesión de usuario
if (!isset($_SESSION['idUsuario'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'No autorizado. Inicie sesión.']);
    exit;
}

$action = $_GET['action'] ?? null;
$idMinuta = $_POST['idMinuta'] ?? null;

if (empty($idMinuta) || !is_numeric($idMinuta)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ID de minuta no proporcionado o inválido.']);
    exit;
}

$db = new conectorDB();
$pdo = $db->getDatabase();

// --- LÓGICA PARA SUBIR ARCHIVOS ---
if ($action === 'upload') {
    if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Error en la subida del archivo: ' . ($_FILES['archivo']['error'] ?? 'Sin archivo.')]);
        exit;
    }

    $file = $_FILES['archivo'];
    $fileName = $file['name'];
    $tmpName = $file['tmp_name'];

    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'xlsx', 'mp4', 'ppt', 'pptx', 'doc', 'docx'];

    if (!in_array($fileExtension, $allowedExtensions)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Extensión de archivo no permitida.']);
        exit;
    }

    // --- ⭐ INICIO DE LA CORRECCIÓN DE RUTA ⭐ ---

    // (CORREGIDO) Ruta física donde se guardará el archivo
    $baseUploadPath = __DIR__ . '/../public/docs/DocumentosAdjuntos/'; // <-- AÑADIDO 'docs'
    $targetDir = $baseUploadPath . strtoupper($fileExtension) . '/';

    // --- ⭐ FIN DE LA CORRECCIÓN DE RUTA ⭐ ---

    if (!is_dir($targetDir)) {
        if (!mkdir($targetDir, 0775, true)) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Error: No se pudo crear el directorio de subida.']);
            exit;
        }
    }

    $safeOriginalName = preg_replace("/[^a-zA-Z0-9.\-_]/", "_", basename($fileName));
    $newFileName = uniqid('adj_', true) . '_' . $safeOriginalName;
    $targetPath = $targetDir . $newFileName;

    // (CORREGIDO) Ruta relativa que se guardará en la BD
    $relativePath = 'public/docs/DocumentosAdjuntos/' . strtoupper($fileExtension) . '/' . $newFileName; // <-- AÑADIDO 'docs'

    try {
        if (move_uploaded_file($tmpName, $targetPath)) {
            $sql = "INSERT INTO t_adjunto (t_minuta_idMinuta, pathAdjunto, tipoAdjunto) VALUES (:idMinuta, :path, 'file')";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':idMinuta' => $idMinuta,
                ':path' => $relativePath // Guardamos la ruta correcta
            ]);
            $lastId = $pdo->lastInsertId();

            echo json_encode([
                'status' => 'success',
                'message' => 'Archivo subido con éxito.',
                'data' => [
                    'idAdjunto' => $lastId,
                    'pathAdjunto' => $relativePath,
                    'tipoAdjunto' => 'file'
                ]
            ]);
        } else {
            throw new Exception('No se pudo mover el archivo subido.');
        }
    } catch (Exception $e) {
        http_response_code(500);
        error_log("Error al guardar adjunto (upload): " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Error en el servidor al guardar el archivo: ' . $e->getMessage()]);
    }
    exit;
}

// --- LÓGICA PARA AGREGAR LINKS ---
if ($action === 'link') {
    $urlLink = $_POST['urlLink'] ?? null;

    if (empty($urlLink) || !filter_var($urlLink, FILTER_VALIDATE_URL)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'La URL proporcionada no es válida.']);
        exit;
    }

    try {
        $sql = "INSERT INTO t_adjunto (t_minuta_idMinuta, pathAdjunto, tipoAdjunto) VALUES (:idMinuta, :path, 'link')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':idMinuta' => $idMinuta,
            ':path' => $urlLink
        ]);
        $lastId = $pdo->lastInsertId();

        echo json_encode([
            'status' => 'success',
            'message' => 'Enlace agregado con éxito.',
            'data' => [
                'idAdjunto' => $lastId,
                'pathAdjunto' => $urlLink,
                'tipoAdjunto' => 'link'
            ]
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        error_log("Error al guardar adjunto (link): " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Error en el servidor al guardar el enlace: ' . $e->getMessage()]);
    }
    exit;
}

// --- Si no hay acción ---
http_response_code(404);
echo json_encode(['status' => 'error', 'message' => 'Acción no válida.']);
exit;
