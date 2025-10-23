<?php
// /corevota/controllers/fetch_data.php
// ¡VERSIÓN ESTANDARIZADA DEFINITIVA!
require_once __DIR__ . '/../class/class.conectorDB.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? null;
// Respuesta por defecto estandarizada
$response = ['status' => 'error', 'message' => 'Acción no válida.', 'data' => []];
$pdo = null; 

if (!$action) {
    echo json_encode($response);
    exit;
}

try {
    $db = new conectorDB();
    $pdo = $db->getDatabase();
    $data = [];

    switch ($action) {
        
        // Caso para dropdowns de Comisión
        case 'comisiones':
            $sql = "SELECT idComision, nombreComision 
                    FROM t_comision 
                    WHERE vigencia = 1 
                    ORDER BY nombreComision ASC";
            $stmt = $pdo->query($sql);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        // Caso para dropdowns de Presidente (Consejeros tipo 1)
        case 'presidentes':
            $sql_pres = "SELECT idUsuario, pNombre, aPaterno, aMaterno
                         FROM t_usuario 
                         WHERE tipoUsuario_id = 1 
                         ORDER BY aPaterno ASC, pNombre ASC";
            $stmt_pres = $pdo->query($sql_pres);
            $usuarios = $stmt_pres->fetchAll(PDO::FETCH_ASSOC);
            foreach ($usuarios as $u) {
                $data[] = [
                    'idUsuario' => $u['idUsuario'],
                    'nombreCompleto' => trim($u['pNombre'] . ' ' . $u['aPaterno'] . ' ' . $u['aMaterno'])
                ];
            }
            break;

        // Caso para la tabla de Asistencia (Consejeros tipo 1)
        case 'asistencia_all':
            $sql_asist = "SELECT idUsuario, pNombre, aPaterno, aMaterno
                          FROM t_usuario 
                          WHERE tipoUsuario_id = 1 
                          ORDER BY aPaterno ASC, aMaterno ASC, pNombre ASC";
            $stmt_asist = $pdo->query($sql_asist);
            $usuarios_asist = $stmt_asist->fetchAll(PDO::FETCH_ASSOC);
            foreach ($usuarios_asist as $u) {
                $data[] = [
                    'idUsuario' => $u['idUsuario'],
                    'nombreCompleto' => trim($u['pNombre'] . ' ' . $u['aPaterno'] . ' ' . $u['aMaterno'])
                ];
            }
            break;
        
        default:
            // Si la acción no se reconoce, salimos con el error
            $response['message'] = 'Acción desconocida.';
            echo json_encode($response);
            $pdo = null;
            exit;
    }

    $pdo = null;
    // ¡SALIDA ESTANDARIZADA!
    $response = ['status' => 'success', 'data' => $data];
    echo json_encode($response);
    exit;

} catch (Exception $e) {
    error_log("Error en fetch_data.php (action: $action): " . $e->getMessage());
    if ($pdo) { $pdo = null; }
    $response['message'] = 'Error de base de datos: ' . $e->getMessage();
    echo json_encode($response); // Devolver error estandarizado
    exit;
}
?>