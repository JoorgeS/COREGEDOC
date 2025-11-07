<?php
// /corevota/controllers/eliminar_adjunto.php

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Incluir dependencias
define('ROOT_PATH', dirname(__DIR__) . '/');
require_once ROOT_PATH . 'class/class.conectorDB.php';

// 2. Validar sesión y datos de entrada
$idUsuarioLogueado = $_SESSION['idUsuario'] ?? null;

// [✅ CORRECCIÓN INICIADA]
// 2.1. Intentar leer idAdjunto desde el cuerpo JSON (si es un fetch con body)
$data = json_decode(file_get_contents('php://input'), true);
$idAdjunto = $data['idAdjunto'] ?? null;

// 2.2. Si no se encontró en el cuerpo JSON, intentar leer desde el Query String (para llamadas GET/URL)
if (is_null($idAdjunto)) {
    $idAdjunto = $_GET['idAdjunto'] ?? null;
}
// [✅ CORRECCIÓN FINALIZADA]

if (!$idUsuarioLogueado || !$idAdjunto || !is_numeric($idAdjunto)) {
    echo json_encode(['status' => 'error', 'message' => 'Datos insuficientes o sesión no válida.']);
    exit;
}

$db = null;
try {
    $db = new conectorDB();
    $pdo = $db->getDatabase();

    $pdo->beginTransaction();

    // 1. Obtener la info del adjunto ANTES de borrarlo
    $sql_select = "SELECT pathAdjunto, tipoAdjunto FROM t_adjunto WHERE idAdjunto = :idAdjunto";
    $stmt_select = $pdo->prepare($sql_select);
    $stmt_select->execute([':idAdjunto' => $idAdjunto]);
    $adjunto = $stmt_select->fetch(PDO::FETCH_ASSOC);

    if (!$adjunto) {
        throw new Exception('El adjunto no existe o ya fue eliminado.');
    }

    // 2. Si es un archivo ('file'), borrarlo del disco
    if ($adjunto['tipoAdjunto'] === 'file') {

        // Construir la ruta física completa
        // DB path = DocumentosAdjuntos/PNG/adj_...
        // Physical path = /ruta/a/corevota/public/docs/DocumentosAdjuntos/PNG/adj_...
        $physicalPath = ROOT_PATH . 'public/docs/' . $adjunto['pathAdjunto'];

        // Borrar el archivo si existe
        if (file_exists($physicalPath)) {
            if (!unlink($physicalPath)) {
                // Si falla el borrado, hacemos rollback
                throw new Exception('No se pudo eliminar el archivo físico del servidor.');
            }
        }
        // Si no existe, no hacemos nada, solo borramos el registro
    }
    // Si es un 'link', no hay archivo físico que borrar

    // 3. Borrar el registro de la Base de Datos
    $sql_delete = "DELETE FROM t_adjunto WHERE idAdjunto = :idAdjunto";
    $stmt_delete = $pdo->prepare($sql_delete);
    $stmt_delete->execute([':idAdjunto' => $idAdjunto]);

    // 4. Confirmar la transacción
    $pdo->commit();

    echo json_encode(['status' => 'success', 'message' => 'Adjunto eliminado correctamente.']);
} catch (Exception $e) {
    // Si algo falla, revertir cambios
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error en eliminar_adjunto.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
} finally {
    $pdo = null;
    $db = null;
}
