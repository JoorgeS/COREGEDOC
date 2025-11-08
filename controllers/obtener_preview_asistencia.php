<?php
// controllers/obtener_preview_asistencia.php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

require_once __DIR__ . '/../cfg/config.php';
require_once __DIR__ . '/../class/class.conectorDB.php';

$idMinuta = $_GET['idMinuta'] ?? null;

if (empty($idMinuta) || !is_numeric($idMinuta)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ID de minuta inválido.']);
    exit;
}

try {
    $conector = new conectorDB();
    $db = $conector->getDatabase();

    // 1. Obtener los IDs de los usuarios que SÍ tienen asistencia registrada
    $sqlPresentes = "SELECT t_usuario_idUsuario FROM t_asistencia 
                     WHERE t_minuta_idMinuta = :idMinuta";
    $stmtPresentes = $db->prepare($sqlPresentes);
    $stmtPresentes->execute([':idMinuta' => $idMinuta]);
    
    // Crear un mapa de IDs presentes para búsqueda rápida
    $mapaPresentes = [];
    foreach ($stmtPresentes->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $mapaPresentes[$row['t_usuario_idUsuario']] = true;
    }

    // 2. Obtener TODOS los usuarios que deberían estar (Consejeros y Presidentes)
    $sqlMiembros = "SELECT 
                        idUsuario, 
                        TRIM(CONCAT(pNombre, ' ', COALESCE(sNombre, ''), ' ', aPaterno, ' ', aMaterno)) AS nombreCompleto
                    FROM 
                        t_usuario
                    WHERE 
                        tipoUsuario_id IN (1, 3) 
                    ORDER BY 
                        aPaterno, aMaterno, pNombre";
    
    $stmtMiembros = $db->prepare($sqlMiembros);
    $stmtMiembros->execute();
    $miembros = $stmtMiembros->fetchAll(PDO::FETCH_ASSOC);

    // 3. Construir la lista final de asistencia
    $listaAsistenciaFinal = [];
    foreach ($miembros as $miembro) {
        $idUsuario = (int) $miembro['idUsuario'];
        
        $listaAsistenciaFinal[] = [
            'nombreCompleto' => $miembro['nombreCompleto'],
            'presente' => isset($mapaPresentes[$idUsuario]) // true si está en el mapa, false si no
        ];
    }

    // Devolver la lista como JSON
    echo json_encode(['status' => 'success', 'asistencia' => $listaAsistenciaFinal]);

} catch (Exception $e) {
    http_response_code(500);
    error_log("Error en obtener_preview_asistencia.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Error al cargar la lista de asistencia.', 'error' => $e->getMessage()]);
}
exit;
?>