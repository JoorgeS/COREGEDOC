<?php
// /corevota/controllers/aplicar_feedback.php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . "/../class/class.conectorDB.php";

$idMinuta = $_POST['idMinuta'] ?? 0;
$idSecretario = $_SESSION['idUsuario'] ?? 0;
$pathSello = 'public/img/aprobacion.png'; //

if (empty($idMinuta) || empty($idSecretario)) {
    echo json_encode(['status' => 'error', 'message' => 'ID de Minuta o Sesión inválida.']);
    exit;
}

try {
    $db = new conectorDB();
    $conexion = $db->getDatabase();
    $conexion->beginTransaction();

    // 1. Insertar el "Sello Verde" en el historial de validación del ST
    $sqlSello = "INSERT INTO t_validacion_st (t_minuta_idMinuta, t_usuario_idSecretario, fechaValidacion, path_sello)
                 VALUES (:idMinuta, :idSecretario, NOW(), :pathSello)";
    $stmtSello = $conexion->prepare($sqlSello);
    $stmtSello->execute([
        ':idMinuta' => $idMinuta,
        ':idSecretario' => $idSecretario,
        ':pathSello' => $pathSello
    ]);

    // 2. Resetear TODAS las aprobaciones de esta minuta a 'EN_ESPERA'
    //    para que los presidentes deban firmar de nuevo la versión corregida.
    $sqlReset = "UPDATE t_aprobacion_minuta
                 SET estado_firma = 'EN_ESPERA', fechaAprobacion = NOW()
                 WHERE t_minuta_idMinuta = :idMinuta 
                 AND estado_firma IN ('FIRMADO', 'REQUIERE_REVISION')";
    $stmtReset = $conexion->prepare($sqlReset);
    $stmtReset->execute([':idMinuta' => $idMinuta]);
    
    // 3. (Opcional pero recomendado) Actualizar estado de la minuta a 'PENDIENTE'
    $sqlUpdMinuta = "UPDATE t_minuta SET estadoMinuta = 'PENDIENTE' WHERE idMinuta = :idMinuta";
    $conexion->prepare($sqlUpdMinuta)->execute([':idMinuta' => $idMinuta]);

    // 4. (IMPORTANTE) Aquí deberías re-usar la lógica de
    //    enviar_aprobacion.php para notificar por email A TODOS 
    //    los presidentes que la minuta fue actualizada y requiere su firma de nuevo.

    $conexion->commit();
    echo json_encode(['status' => 'success', 'message' => 'Minuta corregida y reenviada para aprobación.']);

} catch (Exception $e) {
    $conexion->rollBack();
    echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
}
?>