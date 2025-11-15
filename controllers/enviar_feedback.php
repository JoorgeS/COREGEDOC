<?php
// controllers/enviar_feedback.php

// ----------------------------------------------------------------------
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);
// ----------------------------------------------------------------------

require_once __DIR__ . '/../cfg/config.php';
require_once __DIR__ . '/../class/class.conectorDB.php';
require_once __DIR__ . '/../models/minutaModel.php';
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

// --- ¡NUEVO! ---
// Capturamos el objeto de campos (ej: {"asistencia": true, "temas": true, ...})
$feedbackCampos = $data['feedbackCampos'] ?? [];
// --- FIN NUEVO ---

$idUsuarioPresidente = $_SESSION['idUsuario'] ?? 0; // El usuario de la sesión es el Presidente

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

    /**
     * (Sin cambios)
     */
    private function getSecretarioTecnicoInfo(int $idMinuta): array
    {
        $sql = "SELECT u.correo, u.pNombre
                FROM t_minuta m
                JOIN t_usuario u ON m.t_usuario_idSecretario = u.idUsuario
                WHERE m.idMinuta = :idMinuta";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':idMinuta' => $idMinuta]);
        $secretario = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$secretario) {
            error_log("ADVERTENCIA (enviar_feedback.php): No se encontró Secretario (tipo 2) para la minuta {$idMinuta}. Usando fallback.");
            return [
                'email' => 'genesis.contreras.vargas@gmail.com', // Correo de fallback
                'nombre' => 'Secretario Técnico'
            ];
        } else {
            return [
                'email' => $secretario['correo'],
                'nombre' => $secretario['pNombre']
            ];
        }
    }

    /**
     * (Sin cambios)
     */
    private function notificarSecretario($emailST, $nombreST, $idMinuta, $nombrePresidente, $feedbackTexto)
    {
        $mail = new PHPMailer(true);
        try {
            // Configuración SMTP
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'equiposieteduocuc@gmail.com';
            $mail->Password = 'ioheaszmlkflucsq';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;
            $mail->CharSet = 'UTF-8';

            $mail->setFrom('equiposieteduocuc@gmail.com', 'Gestor Documental del CORE');
            $mail->addAddress($emailST, $nombreST);

            $mail->isHTML(true);
            $mail->Subject = "Feedback Requerido - Minuta N° {$idMinuta}";

            // --- INICIO: LÓGICA DE FIRMA AÑADIDA ---

            // 1. Definir la ruta raíz (asumiendo que este archivo está un nivel dentro del root, ej: /class/)
            $rootPath = dirname(__DIR__) . '/';

            // 2. Definir la ruta relativa de la firma
            $firmaPathRelativa = 'public/img/firma.jpeg';
            $fullPathFirma = $rootPath . $firmaPathRelativa;

            $firmaHTML = ""; // Variable para el HTML de la firma

            // 3. Comprobar si existe y adjuntar la imagen "embebida"
            if (file_exists($fullPathFirma)) {
                // Adjunta la imagen y le da un 'cid' (Content ID)
                $mail->AddEmbeddedImage($fullPathFirma, 'firma_institucional', 'firma.jpeg');
                // Prepara el HTML para mostrar la imagen usando el 'cid'
                $firmaHTML = "<br><img src=\"cid:firma_institucional\" alt=\"Firma Institucional\">";
            } else {
                // Registrar un error si la firma no se encuentra
                error_log("ADVERTENCIA (notificarSecretario): No se encontró el archivo de firma en: " . $fullPathFirma);
            }
            // --- FIN: LÓGICA DE FIRMA AÑADIDA ---


            $mail->Body = "
            <html><body>
            <p>Estimado {$nombreST},</p>
            <p>La minuta <b>N° {$idMinuta}</b> ha recibido feedback del presidente <b>{$nombrePresidente}</b> y requiere su revisión.</p>
            <hr>
            <p><b>Comentario del Presidente:</b></p>
            <p><i>" . nl2br(htmlspecialchars($feedbackTexto)) . "</i></p>
            <hr>
            <p>Por favor, ingrese a COREGEDOC para revisar las observaciones, editar la minuta y volver a enviarla para su aprobación.</p>
            <p>Saludos,<br>Sistema COREGEDOC</p>
            
            {$firmaHTML}

            </body></html>
        ";

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("ERROR idMinuta {$idMinuta}: El correo de FEEDBACK no se pudo enviar a {$emailST}. Mailer Error: {$mail->ErrorInfo}");
            return false;
        }
    }

    /**
     * --- ¡MODIFICADO! ---
     * Ahora acepta $feedbackCampos para guardarlos en la BBDD.
     */
    public function procesarFeedback($idMinuta, $idUsuarioPresidente, $feedbackTexto, $feedbackCampos)
    {
        try {
            $this->db->beginTransaction();

            // 1. Verificar que el usuario puede enviar feedback (Sin cambios)
            $sqlCheck = "SELECT COUNT(*) FROM t_aprobacion_minuta 
                         WHERE t_minuta_idMinuta = :idMinuta 
                         AND t_usuario_idPresidente = :idPresidente
                         AND estado_firma = 'EN_ESPERA'";
            $stmtCheck = $this->db->prepare($sqlCheck);
            $stmtCheck->execute([':idMinuta' => $idMinuta, ':idPresidente' => $idUsuarioPresidente]);

            if ($stmtCheck->fetchColumn() == 0) {
                throw new Exception('No tiene permisos para enviar feedback. Es posible que ya haya firmado o enviado feedback previamente.');
            }

            // 2. Guardar el feedback en la nueva tabla (¡MODIFICADO!)
            $sqlInsert = "INSERT INTO t_minuta_feedback (t_minuta_idMinuta, t_usuario_idPresidente, textoFeedback, feedback_json)
                          VALUES (:idMinuta, :idPresidente, :feedback, :feedbackJson)";
            $stmtInsert = $this->db->prepare($sqlInsert);
            $stmtInsert->execute([
                ':idMinuta' => $idMinuta,
                ':idPresidente' => $idUsuarioPresidente,
                ':feedback' => $feedbackTexto,
                ':feedbackJson' => json_encode($feedbackCampos) // <-- ¡NUEVO! Guardamos el JSON
            ]);

            // 3. Marcar el estado de ESTE presidente como 'REQUIERE_REVISION' (Sin cambios)
            $sqlUpdate = "UPDATE t_aprobacion_minuta 
                          SET estado_firma = 'REQUIERE_REVISION' 
                          WHERE t_minuta_idMinuta = :idMinuta 
                          AND t_usuario_idPresidente = :idPresidente";
            $stmtUpdate = $this->db->prepare($sqlUpdate);
            $stmtUpdate->execute([':idMinuta' => $idMinuta, ':idPresidente' => $idUsuarioPresidente]);

            // 4. Resetear el estado de la minuta a PENDIENTE (Sin cambios)
            $sqlResetMinuta = "UPDATE t_minuta SET estadoMinuta = 'PENDIENTE' WHERE idMinuta = :idMinuta";
            $this->db->prepare($sqlResetMinuta)->execute([':idMinuta' => $idMinuta]);

            $this->db->commit();

            try {
                $minutaModel = new MinutaModel($this->db);
                $nombrePresidenteLog = $_SESSION['pNombre'] . ' ' . $_SESSION['aPaterno'];
                $minutaModel->logAccion(
                    $idMinuta,
                    $idUsuarioPresidente,
                    'FEEDBACK_RECIBIDO',
                    "Presidente ($nombrePresidenteLog) ha enviado feedback. Requiere revisión."
                );
            } catch (Exception $logException) {
                error_log("ADVERTENCIA idMinuta {$idMinuta}: No se pudo registrar el log de 'FEEDBACK_RECIBIDO': " . $logException->getMessage());
            }




            // 5. Enviar notificación al ST (Sin cambios)
            $secretarioInfo = $this->getSecretarioTecnicoInfo($idMinuta);
            $nombrePresidente = $_SESSION['pNombre'] . ' ' . $_SESSION['aPaterno'];
            $this->notificarSecretario(
                $secretarioInfo['email'],
                $secretarioInfo['nombre'],
                $idMinuta,
                $nombrePresidente,
                $feedbackTexto
            );

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

// --- Ejecución (¡MODIFICADO!) ---
$manager = new FeedbackManager();
// Pasamos la nueva variable $feedbackCampos a la función
$resultado = $manager->procesarFeedback($idMinuta, $idUsuarioPresidente, $feedbackTexto, $feedbackCampos);
echo json_encode($resultado);
exit;
