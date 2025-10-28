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

        // Caso para la tabla de Asistencia (Consejeros tipo 1 y 3)
        case 'asistencia_all':
            $sql_asist = "SELECT idUsuario, pNombre, aPaterno, aMaterno
                          FROM t_usuario
                          WHERE tipoUsuario_id IN (1, 3)
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

        // --- NUEVO CASO: Obtener adjuntos por ID de Minuta ---
        case 'adjuntos_por_minuta':
            $idMinuta = $_GET['idMinuta'] ?? null;

            if (!$idMinuta || !is_numeric($idMinuta)) {
                $response['message'] = 'ID de minuta inválido o faltante para obtener adjuntos.';
                // Como ya estamos dentro del try-catch, no podemos salir aquí directamente.
                // En lugar de `exit`, usamos `break` y el flujo continuará a la respuesta de error.
                $data = null; // Marcar que hubo un error lógico
            } else {
                $sqlAdjuntos = "SELECT idAdjunto, pathAdjunto, tipoAdjunto
                                FROM t_adjunto
                                WHERE t_minuta_idMinuta = :idMinuta
                                ORDER BY idAdjunto ASC"; // Ordenar para consistencia
                $stmtAdjuntos = $pdo->prepare($sqlAdjuntos);
                $stmtAdjuntos->execute([':idMinuta' => $idMinuta]);
                $data = $stmtAdjuntos->fetchAll(PDO::FETCH_ASSOC);
                // Si no se encuentran adjuntos, $data será un array vacío [], lo cual es correcto.
            }
            // Si $data es null (por ID inválido), la respuesta final será de error.
            // Si $data es un array (incluso vacío), la respuesta será 'success'.
            break;
        // --- FIN NUEVO CASO ---

        default:
            // Si la acción no se reconoce, salimos con el error
            $response['message'] = 'Acción desconocida.';
            echo json_encode($response);
            $pdo = null;
            exit;
    }

    $pdo = null;

    // Verificar si hubo un error lógico en un caso (como adjuntos_por_minuta con ID inválido)
    if ($data === null) {
        // El mensaje de error ya se estableció dentro del 'case'
        echo json_encode($response);
    } else {
        // ¡SALIDA ESTANDARIZADA DE ÉXITO!
        $response = ['status' => 'success', 'data' => $data];
        echo json_encode($response);
    }
    exit;
} catch (Exception $e) {
    error_log("Error en fetch_data.php (action: $action): " . $e->getMessage());
    if ($pdo) {
        $pdo = null;
    }
    // Usar el mensaje de error que ya podría estar definido o el genérico de la excepción
    $response['message'] = $response['message'] !== 'Acción no válida.' ? $response['message'] : ('Error de base de datos: ' . $e->getMessage());
    http_response_code(500); // Indicar error del servidor
    echo json_encode($response); // Devolver error estandarizado
    exit;
}
