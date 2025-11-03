<?php
// controllers/enviar_aprobacion.php
// Este script se llama con el botón rojo "Enviar para Aprobación"

// ==================
// INICIO DE LA CORRECCIÓN
// ==================
// Forzar que los errores se muestren en la respuesta JSON
// Tu 'ini_set('display_errors', 0);' estaba escondiendo el error.
ini_set('display_errors', 1); 
ini_set('display_startup_errors', 1);
// ==================
// FIN DE LA CORRECCIÓN
// ==================
error_reporting(E_ALL);


require_once __DIR__ . '/../cfg/config.php';
require_once __DIR__ . '/../class/class.conectorDB.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../models/minutaModel.php';

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
        
        // ==================
        // INICIO DE LA CORRECCIÓN
        // ==================
        // El envío de correos también debe estar en un try...catch,
        // pero si falla, DEBE lanzar una excepción para que la función
        // principal sepa que la notificación falló.
        // Tu catch original solo hacía error_log() y seguía.
        
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
            $mail->setFrom('equiposieteduocuc@gmail.com', 'CoreVota - Sistema de Minutas');
            $mail->isHTML(true);
            $mail->Subject = $asunto;

            foreach ($destinatarios as $destinatario) {
                // Asegurarse de no enviar a emails nulos o vacíos
                if(empty($destinatario['correo'])) {
                    error_log("ADVERTENCIA idMinuta {$idMinuta}: No se envió correo al presidente {$destinatario['pNombre']} por email vacío.");
                    continue; // Salta a este usuario, pero no detiene el bucle
                }
                
                $mail->clearAddresses(); // Limpiar destinatario anterior
                $mail->addAddress($destinatario['correo'], $destinatario['pNombre'] . ' ' . $destinatario['aPaterno']);
                $mail->Body = "<html><body><p>Estimado(a) {$destinatario['pNombre']} {$destinatario['aPaterno']},</p>{$cuerpo}<p>Saludos cordiales,<br>Sistema CoreVota</p></body></html>";
                $mail->send();
            }
        } catch (Exception $e) {
            error_log("ERROR CRÍTICO idMinuta {$idMinuta}: El correo de APROBACIÓN (enviar_aprobacion) NO se pudo enviar. Mailer Error: {$mail->ErrorInfo}");
            // Lanzar la excepción de nuevo para que la transacción principal falle
            throw new Exception("Error al enviar notificación por correo: " . $mail->ErrorInfo);
        }
        // ==================
        // FIN DE LA CORRECCIÓN
        // ==================
    }

    public function enviarParaAprobacion($idMinuta, $idUsuarioSecretario)
    {
        // ==================
        // INICIO DE LA CORRECCIÓN
        // ==================
        // La lógica de notificación (enviar correo) debe estar DENTRO
        // del try...catch de la transacción. Si el correo falla,
        // la base de datos debe hacer rollback para que la minuta no
        // quede en "PENDIENTE" si nadie fue notificado.
        
        try {
            // 1. (NUEVO) Verificar si es una respuesta a Feedback (Punto 7)
            $sqlFeedback = "SELECT COUNT(*) FROM t_aprobacion_minuta 
                             WHERE t_minuta_idMinuta = :idMinuta 
                             AND estado_firma = 'REQUIERE_REVISION'";
            $stmtFeedback = $this->db->prepare($sqlFeedback);
            $stmtFeedback->execute([':idMinuta' => $idMinuta]);
            $esRespuestaAFeedback = $stmtFeedback->fetchColumn() > 0;

            $this->db->beginTransaction();
            
            error_log("[LOG MINUTA $idMinuta]: Transacción INICIADA."); // Log 1

            // 2. (NUEVO) Gestionar Sello ST si es respuesta a Feedback (Punto 7)
            if ($esRespuestaAFeedback) {
                $pathSello = 'public/img/sello_verde.png'; 
                
                $sqlSello = "INSERT INTO t_validacion_st (t_minuta_idMinuta, t_usuario_idSecretario, path_sello)
                             VALUES (:idMinuta, :idSecretario, :pathSello)";
                $this->db->prepare($sqlSello)->execute([
                    ':idMinuta' => $idMinuta,
                    ':idSecretario' => $idUsuarioSecretario,
                    ':pathSello' => $pathSello
                ]);

                $sqlFeedbackResuelto = "UPDATE t_minuta_feedback SET resuelto = 1 
                                        WHERE t_minuta_idMinuta = :idMinuta AND resuelto = 0";
                $this->db->prepare($sqlFeedbackResuelto)->execute([':idMinuta' => $idMinuta]);
                
                error_log("[LOG MINUTA $idMinuta]: Sello de Feedback guardado."); // Log 2
            }

            // 3. Obtener la lista de presidentes REQUERIDOS
            $listaPresidentes = $this->getListaPresidentesRequeridos($idMinuta);
            $totalRequeridos = count($listaPresidentes);
            $totalRequeridos = max(1, $totalRequeridos); // Asegurar al menos 1
            
            if (empty($listaPresidentes)) {
                 throw new Exception('No se encontraron presidentes para esta minuta. Revise la configuración de la comisión.');
            }
            
            error_log("[LOG MINUTA $idMinuta]: Presidentes requeridos: " . implode(',', $listaPresidentes) . " (Total: $totalRequeridos)"); // Log 3

            // 4. Actualizar conteo en t_minuta y ponerla PENDIENTE
            $sqlUpdateMinuta = "UPDATE t_minuta 
                                SET presidentesRequeridos = :conteo, estadoMinuta = 'PENDIENTE' 
                                WHERE idMinuta = :idMinuta";
            $this->db->prepare($sqlUpdateMinuta)->execute([
                ':conteo' => $totalRequeridos,
                ':idMinuta' => $idMinuta
            ]);
            
            error_log("[LOG MINUTA $idMinuta]: t_minuta actualizada a PENDIENTE con $totalRequeridos requeridos."); // Log 4

            // 5. BORRAR todas las aprobaciones/revisiones anteriores (CRÍTICO)
            $sqlDeleteAprobaciones = "DELETE FROM t_aprobacion_minuta WHERE t_minuta_idMinuta = :idMinuta";
            $this->db->prepare($sqlDeleteAprobaciones)->execute([':idMinuta' => $idMinuta]);
            
            error_log("[LOG MINUTA $idMinuta]: Registros antiguos de t_aprobacion_minuta BORRADOS."); // Log 5

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
            
            error_log("[LOG MINUTA $idMinuta]: Nuevos registros insertados en t_aprobacion_minuta."); // Log 6
            
            // 7. ENVIAR NOTIFICACIONES (DENTRO de la transacción)
            // Si esto falla, toda la transacción se revierte.
            $this->notificarPresidentes($idMinuta, $listaPresidentes, $esRespuestaAFeedback);
            
            error_log("[LOG MINUTA $idMinuta]: Notificaciones por correo ENVIADAS."); // Log 7

            $this->db->commit();
            
            error_log("[LOG MINUTA $idMinuta]: Transacción COMMIT exitosa."); // Log 8
            
            try {
                $minutaModel = new MinutaModel($this->db);
                
                if ($esRespuestaAFeedback) {
                    // Log 1: El ST aplicó el feedback (ya que este script guarda el sello)
                    $minutaModel->logAccion(
                        $idMinuta,
                        $idUsuarioSecretario,
                        'FEEDBACK_APLICADO',
                        "Secretario Técnico ha aplicado feedback (Sello Verde)."
                    );
                    // Log 2: Se re-envió
                    $minutaModel->logAccion(
                        $idMinuta,
                        $idUsuarioSecretario,
                        'ENVIADA_APROBACION',
                        'Minuta actualizada y reenviada a Presidencia.'
                    );
                } else {
                    // Log único: Primer envío
                    $minutaModel->logAccion(
                        $idMinuta,
                        $idUsuarioSecretario,
                        'ENVIADA_APROBACION',
                        'Minuta enviada a Presidencia para aprobación por primera vez.'
                    );
                }
                error_log("[LOG MINUTA $idMinuta]: Acción(es) registradas en bitácora.");
            } catch (Exception $logException) {
                error_log("ADVERTENCIA idMinuta {$idMinuta}: No se pudo registrar el log: " . $logException->getMessage());
                // No detenemos la transacción por un error de log
            }

            // 8. COMMIT (Solo si la BD y los Correos funcionaron)
            

            $mensaje = "Minuta enviada con éxito. Se ha notificado a {$totalRequeridos} presidente(s).";
            if ($esRespuestaAFeedback) {
                 $mensaje = 'Minuta actualizada (feedback resuelto), sello verde guardado y presidentes notificados.';
            }

            return ['status' => 'success', 'message' => $mensaje];

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
                error_log("[LOG MINUTA $idMinuta]: Transacción REVERTIDA (Rollback)."); // Log 9 (Error)
            }
            // Loguear el error fatal
            error_log("ERROR CATCH idMinuta {$idMinuta} (enviar_aprobacion): " . $e->getMessage());
            // Devolver el error al front-end
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
        // ==================
        // FIN DE LA CORRECCIÓN
        // ==================
    }
}

// --- Ejecución ---
$manager = new AprobacionSender();
$resultado = $manager->enviarParaAprobacion($idMinuta, $idUsuarioSecretario);

if ($resultado['status'] === 'error') {
    http_response_code(500); // Enviar código de error HTTP
}

echo json_encode($resultado);
exit;
