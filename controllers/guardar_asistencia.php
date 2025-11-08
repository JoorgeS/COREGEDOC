<?php
// controllers/guardar_asistencia.php

ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../cfg/config.php';
require_once __DIR__ . '/../class/class.conectorDB.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Validar que la petición sea POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido.']);
    exit;
}

// --- 1. Recepción de Datos desde $_POST ---
$idMinuta = $_POST['idMinuta'] ?? null;
$asistenciaJson = $_POST['asistencia'] ?? '[]'; // JSON string

// Validar ID Minuta
if (!$idMinuta || !is_numeric($idMinuta)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ID de minuta inválido o faltante.']);
    exit;
}

// --- 2. Decodificar JSON ---
$asistenciaIDs = json_decode($asistenciaJson, true);

if ($asistenciaIDs === null || !is_array($asistenciaIDs)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Error al decodificar datos de asistencia.']);
    exit;
}

$pdo = null;
try {
    $db = new conectorDB();
    $pdo = $db->getDatabase();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->beginTransaction();

    // 3.1 Convertir IDs a enteros
    $asistenciaIDs_clean = array_map('intval', array_filter($asistenciaIDs, 'is_numeric'));
    
    // 3.2 [NUEVA LÓGICA] Respaldar TODOS los datos originales de la minuta
    $mapaDatosOriginales = [];
    $sqlFechasOriginales = "SELECT t_usuario_idUsuario, fechaRegistroAsistencia, origenAsistencia 
                            FROM t_asistencia 
                            WHERE t_minuta_idMinuta = ?";
    $stmtFechasOriginales = $pdo->prepare($sqlFechasOriginales);
    $stmtFechasOriginales->execute([$idMinuta]);
    
    foreach ($stmtFechasOriginales->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $mapaDatosOriginales[(int)$row['t_usuario_idUsuario']] = [
            'fecha' => $row['fechaRegistroAsistencia'],
            'origen' => $row['origenAsistencia']
        ];
    }

    // 3.3 ELIMINAR TODAS LAS ASISTENCIAS DE LA MINUTA (Reset)
    $sqlDeleteAsistencia = "DELETE FROM t_asistencia WHERE t_minuta_idMinuta = :idMinuta";
    $stmtDeleteAsistencia = $pdo->prepare($sqlDeleteAsistencia);
    $stmtDeleteAsistencia->execute([':idMinuta' => $idMinuta]);

    // 3.4 INSERTAR SOLO LOS USUARIOS MARCADOS COMO PRESENTES (Con lógica de preservación)
    $idTipoReunion = 1; // Asumido

    if (!empty($asistenciaIDs_clean)) {
        
        $sqlAsistencia = "INSERT INTO t_asistencia (
                            t_minuta_idMinuta, 
                            t_usuario_idUsuario, 
                            t_tipoReunion_idTipoReunion, 
                            fechaRegistroAsistencia, 
                            origenAsistencia
                          ) VALUES (
                            :idMinuta, 
                            :idUsuario, 
                            :idTipoReunion, 
                            :fechaAsistencia, 
                            :origen
                          )";
                          
        $stmtAsistencia = $pdo->prepare($sqlAsistencia);
        
        foreach ($asistenciaIDs_clean as $idUsuario) {
            
            $dataOriginal = $mapaDatosOriginales[$idUsuario] ?? null;
            
            if ($dataOriginal) {
                // El usuario ya existía: Preservar sus datos
                $fechaRegistro = $dataOriginal['fecha'];
                $origen = $dataOriginal['origen']; // <-- ¡AQUÍ SE RESPETA EL AUTOREGISTRO!
            } else {
                // El usuario es nuevo (recién marcado como 'Presente' por el ST)
                $fechaRegistro = (new DateTime())->format('Y-m-d H:i:s');
                $origen = 'SECRETARIO';
            }
            
            $stmtAsistencia->execute([
                ':idMinuta' => $idMinuta,
                ':idUsuario' => $idUsuario,
                ':idTipoReunion' => $idTipoReunion,
                ':fechaAsistencia' => $fechaRegistro,
                ':origen' => $origen
            ]);
        }
    }
    
    // 3.5 COMMIT
    $pdo->commit();

    echo json_encode(['status' => 'success', 'message' => 'Asistencia guardada con éxito.']);

} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error en guardar_asistencia.php (ID Minuta: {$idMinuta}): " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Ocurrió un error al guardar los datos.', 'error' => $e->getMessage()]);
}

exit;