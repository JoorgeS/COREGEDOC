<?php
// controllers/fetch_data.php

/* * Este script centraliza la obtención de datos para los
 * desplegables (combobox) de la aplicación.
 */

// Incluir conexión a la base de datos
require_once __DIR__ . '/../class/class.conectorDB.php';

header('Content-Type: application/json');
$action = $_GET['action'] ?? null;
$response = ['status' => 'error', 'message' => 'Acción no válida'];
$pdo = null; // Inicializar PDO

try {
    $db = new conectorDB();
    $pdo = $db->getDatabase(); // Obtener la conexión PDO

    switch ($action) {

        case 'comisiones':
            // Obtiene todas las comisiones vigentes
            $sql = "SELECT idComision, nombreComision FROM t_comision WHERE vigencia = 1 ORDER BY nombreComision ASC";
            $stmt = $pdo->query($sql);
            $response = ['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
            break;

        case 'presidentes':
            // ❗️❗️ CONSULTA CORREGIDA ❗️❗️
            // Obtiene solo usuarios que son Consejeros Regionales (idTipoUsuario = 1)
            $sql = "SELECT idUsuario, CONCAT(pNombre, ' ', aPaterno) as nombreCompleto
                    FROM t_usuario
                    WHERE tipoUsuario_id = 1
                    ORDER BY aPaterno, pNombre ASC";
            $stmt = $pdo->query($sql);
            $response = ['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
            break;

        case 'asistencia_all':
            // Obtiene todos los usuarios para la lista de asistencia
            // (Tu dump SQL muestra que la tabla t_usuario tiene perfil_id y tipoUsuario_id, usaremos eso)
            $sql = "SELECT idUsuario, CONCAT(pNombre, ' ', aPaterno, ' ', aMaterno) as nombreCompleto
                    FROM t_usuario
                    /* Quizás quieras filtrar solo los que pueden asistir? 
                       Ej: WHERE tipoUsuario_id IN (1, 2, 3, 4, 6) */
                    ORDER BY aPaterno, aMaterno, pNombre ASC";
            $stmt = $pdo->query($sql);
            $response = ['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
            break;

        case 'comision_presidente_map':
            // Obtiene el mapeo [idComision => idPresidente] desde la BD
            $sql = "SELECT idComision, t_usuario_idPresidente
                    FROM t_comision
                    WHERE t_usuario_idPresidente IS NOT NULL AND vigencia = 1";
            $stmt = $pdo->query($sql);
            $map = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // Formato [idComision => idPresidente]
            $response = ['status' => 'success', 'data' => $map];
            break;

        default:
            $response = ['status' => 'error', 'message' => 'Acción no válida solicitada.'];
            break;
    }
} catch (PDOException $e) {
    // Capturar cualquier error de base de datos
    error_log("Error en fetch_data.php: " . $e->getMessage()); // Registrar error
    $response = ['status' => 'error', 'message' => 'Error de conexión a la base de datos: ' . $e->getMessage()];
} catch (Exception $e) {
    // Capturar otros errores
    error_log("Error general en fetch_data.php: " . $e->getMessage());
    $response = ['status' => 'error', 'message' => 'Error inesperado: ' . $e->getMessage()];
} finally {
    $pdo = null; // Cerrar la conexión
}

echo json_encode($response);
exit;
