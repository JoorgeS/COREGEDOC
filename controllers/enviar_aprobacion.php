<?php
// controllers/enviar_aprobacion.php
// Este script se llama con el botón rojo "Enviar para Aprobación"

ini_set('display_errors', 0);
error_reporting(E_ALL);

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
$idUsuarioSecretario = $_SESSION['idUsuario'] ?? 0;

if (empty($idMinuta) || empty($idUsuarioSecretario)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Datos incompletos o sesión inválida.']);
    exit;
}

class AprobacionSender extends BaseConexion
{
    private $db;

    public function __construct()
    {
        $this->db = $this->conectar();
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /**
     * Obtiene la lista precisa de IDs de presidentes requeridos para firmar.
     */
    private function getListaPresidentesRequeridos(int $idMinuta): array
    {
        try {
            // 1. Obtener Presidente 1 (guardado en t_minuta)
            $sqlMinuta = "SELECT t_usuario_idPresidente FROM t_minuta WHERE idMinuta = ?";
            $stmtMinuta = $this->db->prepare($sqlMinuta);
            $stmtMinuta->execute([$idMinuta]);
            $idPresidente1 = $stmtMinuta->fetchColumn();
            $presidentes = [$idPresidente1];

            // 2. Obtener Presidentes 2 y 3 (de comisiones mixtas en t_reunion)
            $sqlReunion = "SELECT r.t_comision_idComision_mixta, r.t_comision_idComision_mixta2 
                           FROM t_reunion r WHERE r.t_minuta_idMinuta = ?";
            $stmtReunion = $this->db->prepare($sqlReunion);
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
                    $stmtComision = $this->db->prepare($sqlComision);
                    $stmtComision->execute($idComisiones);
                    $idsPresidentesMixtos = $stmtComision->fetchAll(PDO::FETCH_COLUMN, 0);
                    $presidentes = array_merge($presidentes, $idsPresidentesMixtos);
                }
            }
            $presidentesUnicos = array_map('intval', array_unique(array_filter($presidentes)));
            return $presidentesUnicos;
        } catch (Exception $e) {
            error_log("ERROR idMinuta {$idMinuta}: No se pudo OBTENER la lista de presidentes (enviar_aprobacion). Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Notifica a todos los presidentes requeridos que la minuta está lista.
     */
    private function notificarPresidentes(int $idMinuta, array $listaPresidentes, bool $esReenvioFeedback)
    {
        if (empty($listaPresidentes)) {
            return;
        }
        try {
            $placeholders = implode(',', array_fill(0, count($listaPresidentes), '?'));
            $sql = "SELECT correo, pNombre, aPaterno FROM t_usuario WHERE idUsuario IN ($placeholders)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($listaPresidentes);
            $destinatarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if ($esReenvioFeedback) {
                $asunto = "Minuta N° {$idMinuta} ACTUALIZADA - Requiere su firma";
                $cuerpo = "<p>Le informamos que la Minuta N° {$idMinuta} ha sido actualizada por el Secretario Técnico en base al feedback recibido.</p>
                        <p>Su aprobación ha sido reiniciada. Por favor, ingrese a CoreVota para revisar la nueva versión y registrar su firma.</p>";
            } else {
                $asunto = "Minuta N° {$idMinuta} lista para su firma";
                $cuerpo = "<p>Le informamos que la Minuta N° {$idMinuta} se encuentra disponible para su revisión y firma.</p>
                        <p>Por favor, ingrese a CoreVota para gestionarla.</p>";
            }

            foreach ($destinatarios as $destinatario) {
                $mail = new PHPMailer(true);
                // Configuración SMTP (tomada de tus scripts)
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'equiposieteduocuc@gmail.com';
                $mail->Password = 'ioheaszmlkflucsq';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;
                $mail->CharSet = 'UTF-8';
                $mail->setFrom('equiposieteduocuc@gmail.com', 'CoreVota - Sistema de Minutas');
                
                // Asegurarse de no enviar a emails nulos o vacíos
                if(empty($destinatario['correo'])) {
                    error_log("ADVERTENCIA idMinuta {$idMinuta}: No se envió correo al presidente {$destinatario['pNombre']} por email vacío.");
                    continue;
                }
                
                $mail->addAddress($destinatario['correo'], $destinatario['pNombre'] . ' ' . $destinatario['aPaterno']);
                $mail->isHTML(true);
                $mail->Subject = $asunto;
                $mail->Body = "<html><body><p>Estimado(a) {$destinatario['pNombre']} {$destinatario['aPaterno']},</p>{$cuerpo}<p>Saludos cordiales,<br>Sistema CoreVota</p></body></html>";
                $mail->send();
            }
        } catch (Exception $e) {
            error_log("ERROR idMinuta {$idMinuta}: El correo de APROBACIÓN (enviar_aprobacion) NO se pudo enviar. Mailer Error: {$mail->ErrorInfo}");
        }
    }

    public function enviarParaAprobacion($idMinuta, $idUsuarioSecretario)
    {
        try {
            // 1. (NUEVO) Verificar si es una respuesta a Feedback (Punto 7)
            $sqlFeedback = "SELECT COUNT(*) FROM t_aprobacion_minuta 
                            WHERE t_minuta_idMinuta = :idMinuta 
                            AND estado_firma = 'REQUIERE_REVISION'";
            $stmtFeedback = $this->db->prepare($sqlFeedback);
            $stmtFeedback->execute([':idMinuta' => $idMinuta]);
            $esRespuestaAFeedback = $stmtFeedback->fetchColumn() > 0;

            $this->db->beginTransaction();

            // 2. (NUEVO) Gestionar Sello ST si es respuesta a Feedback (Punto 7)
            if ($esRespuestaAFeedback) {
                // Añadir sello verde
                // *** ¡IMPORTANTE! Asegúrate que esta imagen exista ***
                $pathSello = 'public/img/sello_verde.png'; 
                
                $sqlSello = "INSERT INTO t_validacion_st (t_minuta_idMinuta, t_usuario_idSecretario, path_sello)
                             VALUES (:idMinuta, :idSecretario, :pathSello)";
                $this->db->prepare($sqlSello)->execute([
                    ':idMinuta' => $idMinuta,
                    ':idSecretario' => $idUsuarioSecretario,
                    ':pathSello' => $pathSello
                ]);

                // Marcar feedback como resuelto
                $sqlFeedbackResuelto = "UPDATE t_minuta_feedback SET resuelto = 1 
                                       WHERE t_minuta_idMinuta = :idMinuta AND resuelto = 0";
                $this->db->prepare($sqlFeedbackResuelto)->execute([':idMinuta' => $idMinuta]);
            }

            // 3. Obtener la lista de presidentes REQUERIDOS
            $listaPresidentes = $this->getListaPresidentesRequeridos($idMinuta);
            $totalRequeridos = count($listaPresidentes);
            $totalRequeridos = max(1, $totalRequeridos); // Asegurar al menos 1
            
            if (empty($listaPresidentes)) {
                 throw new Exception('No se encontraron presidentes para esta minuta. Revise la configuración de la comisión.');
            }
            
            // 4. Actualizar conteo en t_minuta y ponerla PENDIENTE
            $sqlUpdateMinuta = "UPDATE t_minuta 
                                SET presidentesRequeridos = :conteo, estadoMinuta = 'PENDIENTE' 
                                WHERE idMinuta = :idMinuta";
            $this->db->prepare($sqlUpdateMinuta)->execute([
                ':conteo' => $totalRequeridos,
                ':idMinuta' => $idMinuta
            ]);

            // 5. BORRAR todas las aprobaciones/revisiones anteriores (CRÍTICO)
            $sqlDeleteAprobaciones = "DELETE FROM t_aprobacion_minuta WHERE t_minuta_idMinuta = :idMinuta";
            $this->db->prepare($sqlDeleteAprobaciones)->execute([':idMinuta' => $idMinuta]);

            // 6. (RE)CREAR los registros de aprobación para todos los presidentes
            $sqlInsertAprobacion = "INSERT INTO t_aprobacion_minuta (t_minuta_idMinuta, t_usuario_idPresidente, estado_firma, fechaAprobacion) 
                                    VALUES (:idMinuta, :idPresidente, 'EN_ESPERA', NOW())";
            $stmtInsertAprobacion = $this->db->prepare($sqlInsertAprobacion);
            foreach ($listaPresidentes as $idPresidente) {
                $stmtInsertAprobacion->execute([
                    ':idMinuta' => $idMinuta,
                    ':idPresidente' => $idPresidente
                ]);
            }
            
            // 7. COMMIT
            $this->db->commit();

            // 8. ENVIAR NOTIFICACIONES (Fuera de la transacción)
            $this->notificarPresidentes($idMinuta, $listaPresidentes, $esRespuestaAFeedback);
            
            $mensaje = "Minuta enviada con éxito. Se ha notificado a {$totalRequeridos} presidente(s).";
            if ($esRespuestaAFeedback) {
                 $mensaje = 'Minuta actualizada (feedback resuelto), sello verde guardado y presidentes notificados.';
            }

            return ['status' => 'success', 'message' => $mensaje];

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("ERROR CATCH idMinuta {$idMinuta} (enviar_aprobacion): " . $e->getMessage());
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}

// --- Ejecución ---
$manager = new AprobacionSender();
$resultado = $manager->enviarParaAprobacion($idMinuta, $idUsuarioSecretario);
if ($resultado['status'] === 'error') {
    http_response_code(500);
}
echo json_encode($resultado);
exit;