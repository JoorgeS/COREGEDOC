<?php
// controllers/enviar_feedback.php

// ----------------------------------------------------------------------
// Configuración de errores: NO MOSTRAR (rompe JSON), SÍ registrarlos.
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
     * (ADECUADO)
     * Obtiene el email y nombre del Secretario Técnico que CREÓ la minuta.
     * Utiliza la lógica SQL que proporcionaste.
     */
    private function getSecretarioTecnicoInfo(int $idMinuta): array
    {
        // Esta es la consulta que proporcionaste
        $sql = "SELECT u.correo, u.pNombre
                FROM t_minuta m
                JOIN t_usuario u ON m.t_usuario_idSecretario = u.idUsuario
                WHERE m.idMinuta = :idMinuta AND u.tipoUsuario_id = 2"; // tipoUsuario 2 = Secretario

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':idMinuta' => $idMinuta]);
        $secretario = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$secretario) {
            // Fallback por si no se encuentra. Usamos el correo anterior como emergencia.
            error_log("ADVERTENCIA (enviar_feedback.php): No se encontró Secretario (tipo 2) para la minuta {$idMinuta}. Usando fallback.");
            return [
                'email' => 'genesis.contreras.vargas@gmail.com', // Correo de fallback
                'nombre' => 'Secretario Técnico'
            ];
        } else {
            // Devuelve los datos encontrados
            return [
                'email' => $secretario['correo'],
                'nombre' => $secretario['pNombre']
            ];
        }
    }

    /**
     * (ADECUADO)
     * Envía la notificación. Ahora acepta el nombre del ST para personalizar el saludo.
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

            $mail->setFrom('equiposieteduocuc@gmail.com', 'CoreVota - Sistema de Minutas');

            // (ADECUADO) Añade la dirección con el nombre del secretario
            $mail->addAddress($emailST, $nombreST);

            $mail->isHTML(true);
            $mail->Subject = "Feedback Requerido - Minuta N° {$idMinuta}";

            // (ADECUADO) Cuerpo del correo personalizado con el nombre del ST
            $mail->Body = "
                <html><body>
                <p>Estimado {$nombreST},</p>
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

    /**
     * (ADECUADO)
     * Procesa el feedback y llama a las funciones actualizadas de búsqueda y notificación.
     */
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

            // (ADECUADO) Llama a la nueva función
            $secretarioInfo = $this->getSecretarioTecnicoInfo($idMinuta);

            // El nombre del Presidente se saca de la sesión actual
            $nombrePresidente = $_SESSION['pNombre'] . ' ' . $_SESSION['aPaterno'];

            // (ADECUADO) Llama a la función de notificación con los nuevos parámetros
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
            // Mostrar el mensaje de error de la excepción es útil para depurar
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}

// --- Ejecución ---
$manager = new FeedbackManager();
$resultado = $manager->procesarFeedback($idMinuta, $idUsuarioPresidente, $feedbackTexto);
echo json_encode($resultado);
exit;
