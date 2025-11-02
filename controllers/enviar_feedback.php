<?php
// controllers/enviar_feedback.php

// ----------------------------------------------------------------------
// (CORREGIDO) Configuración de errores: NO MOSTRAR (rompe JSON), SÍ registrarlos.
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);
// ----------------------------------------------------------------------

require_once __DIR__ . '/../cfg/config.php';
require_once __DIR__ . '/../class/class.conectorDB.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Obtener datos
$data = json_decode(file_get_contents('php://input'), true);
$idMinuta = $data['idMinuta'] ?? null;
$feedbackTexto = $data['feedback'] ?? null;
$idUsuarioPresidente = $_SESSION['idUsuario'] ?? 0;

if (empty($idMinuta) || empty($feedbackTexto) || empty($idUsuarioPresidente)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Datos incompletos.']);
    exit;
}
if (mb_strlen($feedbackTexto) < 10) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'El feedback debe tener al menos 10 caracteres.']);
    exit;
}


class FeedbackManager extends BaseConexion
{
    private $db;

    public function __construct()
    {
        $this->db = $this->conectar();
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    private function getEmailSecretarioTecnico(int $idMinuta)
    {
        // --- ADVERTENCIA: LÓGICA DE ST HARDCODEADA ---
        // (Tu lógica original para obtener el email del ST)

        // (Lógica ideal futura: buscar el ST de la comisión asociada a la minuta)
        // $sql = "SELECT u.correo FROM t_usuario u 
        //         JOIN t_comision c ON u.idUsuario = c.id_secretario_tecnico
        //         JOIN t_minuta m ON m.t_comision_idComision = c.idComision
        //         WHERE m.idMinuta = ?";

        return 'genesis.contreras.vargas@gmail.com';
    }

    private function notificarSecretario($emailST, $idMinuta, $nombrePresidente, $feedbackTexto)
    {
        $mail = new PHPMailer(true);
        try {
            // Configuración SMTP (tomada de tu script guardar_minuta_completa.php)
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'equiposieteduocuc@gmail.com';
            $mail->Password = 'ioheaszmlkflucsq';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;
            $mail->CharSet = 'UTF-8';

            $mail->setFrom('equiposieteduocuc@gmail.com', 'CoreVota - Sistema de Minutas');
            $mail->addAddress($emailST);

            $mail->isHTML(true);
            $mail->Subject = "Feedback Requerido - Minuta N° {$idMinuta}";
            $mail->Body = "
                <html><body>
                <p>Estimado Secretario Técnico,</p>
                <p>La minuta <b>N° {$idMinuta}</b> ha recibido feedback del presidente <b>{$nombrePresidente}</b> y requiere su revisión.</p>
                <hr>
                <p><b>Comentario del Presidente:</b></p>
                <p><i>" . nl2br(htmlspecialchars($feedbackTexto)) . "</i></p>
                <hr>
                <p>Por favor, ingrese a CoreVota para revisar las observaciones, editar la minuta y volver a enviarla para su aprobación.</p>
                <p>Saludos,<br>Sistema CoreVota</p>
                </body></html>
            ";

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("ERROR idMinuta {$idMinuta}: El correo de FEEDBACK no se pudo enviar a {$emailST}. Mailer Error: {$mail->ErrorInfo}");
            return false;
        }
    }

    public function procesarFeedback($idMinuta, $idUsuarioPresidente, $feedbackTexto)
    {
        try {
            $this->db->beginTransaction();

            // 1. Verificar que el usuario puede enviar feedback
            $sqlCheck = "SELECT COUNT(*) FROM t_aprobacion_minuta 
                         WHERE t_minuta_idMinuta = :idMinuta 
                         AND t_usuario_idPresidente = :idPresidente
                         AND estado_firma = 'EN_ESPERA'";
            $stmtCheck = $this->db->prepare($sqlCheck);
            $stmtCheck->execute([':idMinuta' => $idMinuta, ':idPresidente' => $idUsuarioPresidente]);

            if ($stmtCheck->fetchColumn() == 0) {
                throw new Exception('No tiene permisos para enviar feedback. Es posible que ya haya firmado o enviado feedback previamente.');
            }

            // 2. Guardar el feedback en la nueva tabla
            $sqlInsert = "INSERT INTO t_minuta_feedback (t_minuta_idMinuta, t_usuario_idPresidente, textoFeedback)
                          VALUES (:idMinuta, :idPresidente, :feedback)";
            $stmtInsert = $this->db->prepare($sqlInsert);
            $stmtInsert->execute([
                ':idMinuta' => $idMinuta,
                ':idPresidente' => $idUsuarioPresidente,
                ':feedback' => $feedbackTexto
            ]);

            // 3. Marcar el estado de ESTE presidente como 'REQUIERE_REVISION'
            $sqlUpdate = "UPDATE t_aprobacion_minuta 
                          SET estado_firma = 'REQUIERE_REVISION' 
                          WHERE t_minuta_idMinuta = :idMinuta 
                          AND t_usuario_idPresidente = :idPresidente";
            $stmtUpdate = $this->db->prepare($sqlUpdate);
            $stmtUpdate->execute([':idMinuta' => $idMinuta, ':idPresidente' => $idUsuarioPresidente]);

            // 4. Resetear el estado de la minuta a PENDIENTE
            $sqlResetMinuta = "UPDATE t_minuta SET estadoMinuta = 'PENDIENTE' WHERE idMinuta = :idMinuta";
            $this->db->prepare($sqlResetMinuta)->execute([':idMinuta' => $idMinuta]);

            $this->db->commit();

            // 5. Enviar notificación al ST (Fuera de la transacción)
            $emailST = $this->getEmailSecretarioTecnico($idMinuta);
            $nombrePresidente = $_SESSION['pNombre'] . ' ' . $_SESSION['aPaterno'];
            $this->notificarSecretario($emailST, $idMinuta, $nombrePresidente, $feedbackTexto);

            return ['status' => 'success', 'message' => 'Feedback enviado con éxito.'];
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("ERROR procesarFeedback: " . $e->getMessage());
            http_response_code(500);
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}

// --- Ejecución ---
$manager = new FeedbackManager();
$resultado = $manager->procesarFeedback($idMinuta, $idUsuarioPresidente, $feedbackTexto);
echo json_encode($resultado);
exit;
