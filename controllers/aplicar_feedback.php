<?php
// /corevota/controllers/aplicar_feedback.php
header('Content-Type: application/json');
ini_set('display_errors', 0); // No mostrar errores, pero sí loguear
ini_set('log_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) session_start();

// --- DEPENDENCIAS COMPLETAS ---
require_once __DIR__ . '/../cfg/config.php';
require_once __DIR__ . '/../class/class.conectorDB.php';
require_once __DIR__ . '/../models/minutaModel.php';
require_once __DIR__ . '/../vendor/autoload.php'; // Para PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
// --- FIN DEPENDENCIAS ---

$idMinuta = $_POST['idMinuta'] ?? 0;
$idSecretario = $_SESSION['idUsuario'] ?? 0;
$pathSello = 'public/img/aprobacion.png';

if (empty($idMinuta) || empty($idSecretario)) {
    echo json_encode(['status' => 'error', 'message' => 'ID de Minuta o Sesión inválida.']);
    exit;
}


/**
 * ==========================================================
 * INICIO DE LÓGICA COPIADA DE 'enviar_aprobacion.php'
 * ==========================================================
 */

/**
 * Obtiene la lista precisa de IDs de presidentes requeridos para firmar.
 */
function getListaPresidentesRequeridos(PDO $db, int $idMinuta): array
{
    try {
        // 1. Obtener Presidente 1 (guardado en t_minuta)
        $sqlMinuta = "SELECT t_usuario_idPresidente FROM t_minuta WHERE idMinuta = ?";
        $stmtMinuta = $db->prepare($sqlMinuta);
        $stmtMinuta->execute([$idMinuta]);
        $idPresidente1 = $stmtMinuta->fetchColumn();
        $presidentes = [$idPresidente1];

        // 2. Obtener Presidentes 2 y 3 (de comisiones mixtas en t_reunion)
        $sqlReunion = "SELECT r.t_comision_idComision_mixta, r.t_comision_idComision_mixta2 
                       FROM t_reunion r WHERE r.t_minuta_idMinuta = ?";
        $stmtReunion = $db->prepare($sqlReunion);
        $stmtReunion->execute([$idMinuta]);
        $comisionesMixtas = $stmtReunion->fetch(PDO::FETCH_ASSOC);

        if ($comisionesMixtas) {
            $idComisiones = array_filter([
                $comisionesMixtas['t_comision_idComision_mixta'],
                $comisionesMixtas['t_comision_idComision_mixta2']
            ]);
            if (!empty($idComisiones)) {
                $placeholders = implode(',', array_fill(0, count($idComisiones), '?'));
                $sqlComision = "SELECT t_usuario_idPresidente FROM t_comision WHERE idComision IN ($placeholders)";
                $stmtComision = $db->prepare($sqlComision);
                $stmtComision->execute($idComisiones);
                $idsPresidentesMixtos = $stmtComision->fetchAll(PDO::FETCH_COLUMN, 0);
                $presidentes = array_merge($presidentes, $idsPresidentesMixtos);
            }
        }
        $presidentesUnicos = array_map('intval', array_unique(array_filter($presidentes)));
        return $presidentesUnicos;
    } catch (Exception $e) {
        error_log("ERROR idMinuta {$idMinuta}: No se pudo OBTENER la lista de presidentes (aplicar_feedback). Error: " . $e->getMessage());
        return [];
    }
}

/**
 * Notifica a todos los presidentes requeridos que la minuta fue ACTUALIZADA.
 */
function notificarPresidentes(PDO $db, int $idMinuta, array $listaPresidentes)
{
    if (empty($listaPresidentes)) {
        throw new Exception('No se econtraron destinatarios para notificar.');
    }

    try {
        $placeholders = implode(',', array_fill(0, count($listaPresidentes), '?'));
        $sql = "SELECT correo, pNombre, aPaterno FROM t_usuario WHERE idUsuario IN ($placeholders)";
        $stmt = $db->prepare($sql);
        $stmt->execute($listaPresidentes);
        $destinatarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // --- TEXTOS PARA UN RE-ENVÍO ---
        $asunto = "Minuta N° {$idMinuta} ACTUALIZADA - Requiere su firma";
        $cuerpo = "<p>Le informamos que la Minuta N° {$idMinuta} ha sido actualizada por el Secretario Técnico en base al feedback recibido.</p>
                 <p>Su aprobación ha sido reiniciada. Por favor, ingrese a CoreVota para revisar la nueva versión y registrar su firma.</p>";

        $mail = new PHPMailer(true); // Instancia única
        // Configuración SMTP (tomada de tus scripts)
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'equiposieteduocuc@gmail.com';
        $mail->Password = 'ioheaszmlkflucsq';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->CharSet = 'UTF-8';
        $mail->setFrom('equiposieteduocuc@gmail.com', 'Gestor Documental del CORE');
        $mail->isHTML(true);
        $mail->Subject = $asunto;

        foreach ($destinatarios as $destinatario) {
            if (empty($destinatario['correo'])) {
                error_log("ADVERTENCIA idMinuta {$idMinuta}: No se envió correo al presidente {$destinatario['pNombre']} por email vacío.");
                continue;
            }

            $mail->clearAddresses(); // Limpiar destinatario anterior
            $mail->addAddress($destinatario['correo'], $destinatario['pNombre'] . ' ' . $destinatario['aPaterno']);
            $mail->Body = "<html><body><p>Estimado(a) {$destinatario['pNombre']} {$destinatario['aPaterno']},</p>{$cuerpo}<p>Saludos cordiales,<br>Sistema CoreVota</p></body></html>";
            $mail->send();
        }
    } catch (Exception $e) {
        error_log("ERROR CRÍTICO idMinuta {$idMinuta}: El correo de RE-ENVÍO (aplicar_feedback) NO se pudo enviar. Mailer Error: {$mail->ErrorInfo}");
        // Lanzar la excepción de nuevo para que la transacción principal falle
        throw new Exception("Error al enviar notificación por correo: " . $mail->ErrorInfo);
    }
}

/**
 * ==========================================================
 * FIN DE LÓGICA COPIADA
 * ==========================================================
 */


try {
    $db = new conectorDB();
    $conexion = $db->getDatabase();
    $conexion->beginTransaction();

    // 1. Insertar el "Sello Verde" (Tu lógica original)
    $sqlSello = "INSERT INTO t_validacion_st (t_minuta_idMinuta, t_usuario_idSecretario, fechaValidacion, path_sello)
                 VALUES (:idMinuta, :idSecretario, NOW(), :pathSello)";
    $stmtSello = $conexion->prepare($sqlSello);
    $stmtSello->execute([
        ':idMinuta' => $idMinuta,
        ':idSecretario' => $idSecretario,
        ':pathSello' => $pathSello
    ]);

    // 2. Resetear TODAS las aprobaciones (Tu lógica original)
    $sqlReset = "UPDATE t_aprobacion_minuta
                 SET estado_firma = 'EN_ESPERA', fechaAprobacion = NOW()
                 WHERE t_minuta_idMinuta = :idMinuta 
                 AND estado_firma IN ('FIRMADO', 'REQUIERE_REVISION')";
    $stmtReset = $conexion->prepare($sqlReset);
    $stmtReset->execute([':idMinuta' => $idMinuta]);

    // 3. Actualizar estado de la minuta a 'PENDIENTE' (Tu lógica original)
    $sqlUpdMinuta = "UPDATE t_minuta SET estadoMinuta = 'PENDIENTE' WHERE idMinuta = :idMinuta";
    $conexion->prepare($sqlUpdMinuta)->execute([':idMinuta' => $idMinuta]);

    // 4. (NUEVO) Marcar el feedback como resuelto
    $sqlFeedbackResuelto = "UPDATE t_minuta_feedback SET resuelto = 1 
                            WHERE t_minuta_idMinuta = :idMinuta AND resuelto = 0";
    $conexion->prepare($sqlFeedbackResuelto)->execute([':idMinuta' => $idMinuta]);


    // 5. (NUEVO) Obtener la lista de presidentes a notificar
    $listaPresidentes = getListaPresidentesRequeridos($conexion, $idMinuta);
    if (empty($listaPresidentes)) {
        throw new Exception('No se encontraron presidentes para notificar. No se puede reenviar.');
    }

    // ... (código anterior) ...
    // 6. (NUEVO) Enviar los correos de notificación
    notificarPresidentes($conexion, $idMinuta, $listaPresidentes);

    // 7. COMMIT (SOLUCIÓN: Mover el commit ANTES del log)
    // Esto cierra la transacción y libera el bloqueo de la base de datos.
    $conexion->commit();

    // Enviamos la respuesta al usuario INMEDIATAMENTE.
    echo json_encode(['status' => 'success', 'message' => 'Minuta corregida y reenviada para aprobación a ' . count($listaPresidentes) . ' presidente(s).']);

    // 8. LOG (POST-TRANSACCIÓN)
    // Ahora que la transacción terminó, podemos registrar la bitácora sin bloqueos.
    try {
        // Creamos una NUEVA instancia del modelo (sin pasar $conexion)
        // ya que la transacción anterior está cerrada.
        $minutaModel = new MinutaModel();
        $minutaModel->logAccion(
            $idMinuta,
            $idSecretario,
            'FEEDBACK_APLICADO',
            "Secretario Técnico ha aplicado feedback y reenviado (desde listado)."
        );
    } catch (Exception $logException) {
        error_log("ADVERTENCIA idMinuta {$idMinuta}: No se pudo registrar el log de 'FEEDBACK_APLICADO': " . $logException->getMessage());
    }
} catch (Exception $e) {
    if (isset($conexion) && $conexion->inTransaction()) {
        $conexion->rollBack();
    }
    http_response_code(500);
    error_log("Error fatal en aplicar_feedback.php (Minuta ID: {$idMinuta}): " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
}
