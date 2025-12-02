<?php

namespace App\Services;

// Cargar autoloader si es necesario
$autoloadPath = __DIR__ . '/../../vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class MailService
{
    // DATOS DE CONFIGURACIÓN (GMAIL)
    private $host = 'smtp.gmail.com';
    private $username = 'equiposieteduocuc@gmail.com';
    private $password = 'iohe aszm lkfl ucsq'; // Tu App Password
    private $port = 587;
    private $fromEmail = 'equiposieteduocuc@gmail.com';
    
    // CAMBIO 1: Nombre del sistema actualizado
    private $fromName = 'Sistema COREGEDOC';

    /**
     * Configuración centralizada de PHPMailer
     */
    private function getConfiguredMailer()
    {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = $this->host;
        $mail->SMTPAuth   = true;
        $mail->Username   = $this->username;
        $mail->Password   = $this->password;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $this->port;
        $mail->CharSet    = 'UTF-8';
        
        // Evitar problemas de certificados SSL en desarrollo local
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        $mail->setFrom($this->fromEmail, $this->fromName);
        return $mail;
    }

    /**
     * Correo 1: Envío de Asistencia (Con PDF adjunto)
     */
    public function enviarAsistencia($destinatario, $rutaArchivo, $infoReunion)
    {
        try {
            $mail = $this->getConfiguredMailer();
            $mail->addAddress($destinatario);

            $idMinuta = $infoReunion['idMinuta'] ?? 'S/N';
            $nombreReunion = $infoReunion['nombreReunion'] ?? 'Reunión';
            
            $mail->Subject = "Validación de Asistencia - Minuta N° {$idMinuta}";

            // Preparar firma
            $firmaTag = $this->obtenerTagFirma($mail);

            $mail->isHTML(true);
            $mail->Body = "
            <html>
            <body style='font-family: Arial, sans-serif; color: #333;'>
                <h3 style='color: #0056b3;'>Reporte de Asistencia Finalizado</h3>
                <p>Estimada,</p>
                <p>Se adjunta el certificado de asistencia validado para la <strong>Minuta N° {$idMinuta}</strong> ($nombreReunion).</p>
                <p>Este documento incluye la hora exacta de registro de cada consejero y las validaciones del Secretario Técnico.</p>
                <br>
                <p>Atentamente,<br><strong>Sistema COREGEDOC</strong></p>
                {$firmaTag}
            </body>
            </html>";

            if (file_exists($rutaArchivo)) {
                $mail->addAttachment($rutaArchivo, basename($rutaArchivo));
            }

            $mail->send();
            return true;

        } catch (Exception $e) {
            error_log("MailService Error (Asistencia): " . $mail->ErrorInfo);
            // Fallback: Guardar log local si falla el envío real
            $this->logLocal($destinatario, "Error enviando asistencia: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Correo 2: Notificación a Presidentes (Solicitud de Firma)
     */
    public function notificarFirma($destinatario, $nombreUsuario, $idMinuta)
    {
        try {
            $mail = $this->getConfiguredMailer();
            $mail->addAddress($destinatario);

            $mail->Subject = "Acción Requerida: Firma de Minuta N° {$idMinuta}";

            // Preparar firma
            $firmaTag = $this->obtenerTagFirma($mail);

            $mail->isHTML(true);
            $mail->Body = "
            <html>
            <body style='font-family: Arial, sans-serif; color: #333;'>
                <h3 style='color: #28a745;'>Solicitud de Aprobación Electrónica</h3>
                <p>Estimado(a) <strong>$nombreUsuario</strong>,</p>
                <p>Le informamos que la <strong>Minuta N° {$idMinuta}</strong> ya se encuentra disponible en la plataforma <strong>COREGEDOC</strong> para su revisión y firma.</p>
                <div style='background-color: #f8f9fa; padding: 15px; border-left: 4px solid #28a745; margin: 20px 0;'>
                    <strong>Instrucciones:</strong><br>
                    1. Ingrese al sistema con sus credenciales.<br>
                    2. Diríjase a la sección de 'Minutas Pendientes'.<br>
                    3. Revise el contenido y presione el botón 'Firmar Minuta'.
                </div>
                <p>Agradecemos su pronta gestión para finalizar el proceso administrativo.</p>
                <br>
                <p>Atentamente,<br><strong>Sistema COREGEDOC</strong></p>
                {$firmaTag}
            </body>
            </html>";

            $mail->send();
            return true;

        } catch (Exception $e) {
            error_log("MailService Error (Firma): " . $mail->ErrorInfo);
            $this->logLocal($destinatario, "Error notificando firma: " . $e->getMessage());
            return false;
        }
    }

    // Helpers privados

    private function obtenerTagFirma($mail) {
        $rootPath = __DIR__ . '/../../';
        $firmaPath = $rootPath . 'public/img/firma.jpeg';
        
        if (file_exists($firmaPath)) {
            $mail->AddEmbeddedImage($firmaPath, 'firma_inst', 'firma.jpeg');
            
            // CAMBIO 2: Aumenté el ancho a 500px para mayor legibilidad del logo COREGEDOC
            return "<br><br><img src=\"cid:firma_inst\" alt=\"Firma Institucional\" style=\"width:500px; max-width:100%; height:auto;\">";
        }
        return "";
    }

    private function logLocal($to, $msg) {
        $logDir = __DIR__ . '/../../public/docs/emails_enviados/';
        if (!is_dir($logDir)) mkdir($logDir, 0777, true);
        file_put_contents($logDir . 'error_log_' . date('Ymd') . '.txt', date('H:i:s') . " - To: $to - Msg: $msg\n", FILE_APPEND);
    }

    // ... dentro de MailService ...

    /**
     * Correo 3: Recuperación de Contraseña (Estandarizado)
     */
    public function enviarInstruccionesRecuperacion($email, $token)
    {
        try {
            $mail = $this->getConfiguredMailer();
            $mail->addAddress($email);
            
            $mail->Subject = 'Recuperación de Contraseña - COREGEDOC';

            // 1. IMPORTANTE: Generamos la firma igual que en los otros métodos
            $firmaTag = $this->obtenerTagFirma($mail);

            $mail->isHTML(true);

            // Construir enlace MVC
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            
            // Asegúrate que la ruta '/coregedoc/' sea correcta para tu servidor
            $link = "{$protocol}://{$host}/coregedoc/index.php?action=restablecer_password&token={$token}";

            // 2. CUERPO DEL CORREO CON ESTILO ESTANDARIZADO
            $mail->Body = "
            <html>
            <body style='font-family: Arial, sans-serif; color: #333; line-height: 1.6;'>
                
                <h3 style='color: #0056b3;'>Solicitud de Restablecimiento de Clave</h3>
                
                <p>Estimado(a) Usuario,</p>
                
                <p>Hemos recibido una solicitud para restablecer su contraseña en la plataforma <strong>COREGEDOC</strong>.</p>
                
                <div style='background-color: #f8f9fa; padding: 15px; border-left: 4px solid #0056b3; margin: 20px 0;'>
                    <strong>Instrucciones:</strong><br>
                    1. Haga clic en el botón de abajo para acceder al formulario de cambio de clave.<br>
                    2. Ingrese su nueva contraseña.<br>
                    <br>
                    <div style='text-align: center; margin: 10px 0;'>
                        <a href='{$link}' style='background-color: #212529; color: #fff; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 14px;'>RESTABLECER CONTRASEÑA</a>
                    </div>
                    <br>
                    <small>Nota: Este enlace vencerá en 1 hora por seguridad.</small>
                </div>

                <p>Si usted no ha solicitado este cambio, por favor ignore este correo electrónico; su contraseña permanecerá segura.</p>
                
                <br>
                <p>Atentamente,<br><strong>Sistema COREGEDOC</strong></p>
                
                {$firmaTag}
            </body>
            </html>";

            $mail->send();
            return true;
            
        } catch (Exception $e) {
            error_log("Error Mail Recuperación: " . $mail->ErrorInfo);
            // Opcional: Log local
            $this->logLocal($email, "Error enviando recuperación: " . $e->getMessage());
            return false;
        }
    }

    public function notificarFeedback($datos, $autorAccion, $mensajeFeedback)
    {
        $asunto = "⚠️ Corrección Solicitada - Minuta N° " . $datos['minuta']['id'];
        
        $cuerpo = "<h3>Solicitud de Corrección</h3>";
        $cuerpo .= "<p>El Presidente <b>$autorAccion</b> ha solicitado correcciones en la minuta de la comisión <b>{$datos['minuta']['comision']}</b>.</p>";
        $cuerpo .= "<p><strong>Observación:</strong> <br><i>\"$mensajeFeedback\"</i></p>";
        $cuerpo .= "<p>El estado de las firmas se ha reiniciado. Por favor, ingrese al sistema para editar y reenviar.</p>";
        
        $this->enviarA_Todos($datos, $asunto, $cuerpo);
    }

    /**
     * Notifica que un presidente firmó (pero faltan otros)
     */
    public function notificarFirmaParcial($datos, $autorAccion)
    {
        $asunto = "✍️ Firma Recibida - Minuta N° " . $datos['minuta']['id'];
        
        $cuerpo = "<h3>Firma Registrada</h3>";
        $cuerpo .= "<p>El Presidente <b>$autorAccion</b> ha firmado y aprobado la minuta de la comisión <b>{$datos['minuta']['comision']}</b>.</p>";
        $cuerpo .= "<p>El documento queda a la espera de las firmas restantes para su aprobación final.</p>";
        
        $this->enviarA_Todos($datos, $asunto, $cuerpo);
    }

    /**
     * Notifica que la minuta fue APROBADA (Todos firmaron)
     */
    public function notificarAprobacionFinal($datos)
    {
        $asunto = "✅ Minuta Aprobada Oficialmente - N° " . $datos['minuta']['id'];
        
        $cuerpo = "<h3>Proceso Finalizado</h3>";
        $cuerpo .= "<p>La minuta de la comisión <b>{$datos['minuta']['comision']}</b> ha completado el ciclo de firmas.</p>";
        $cuerpo .= "<p>El documento oficial ya se encuentra disponible en el sistema.</p>";
        
        $this->enviarA_Todos($datos, $asunto, $cuerpo);
    }

    /**
     * Notifica a los presidentes que el ST envió/reenvió la minuta para firma
     */
    public function notificarSolicitudFirma($datos, $esReenvio)
    {
        $tipo = $esReenvio ? "Corrección Aplicada" : "Solicitud de Firma";
        $asunto = "📄 $tipo - Minuta N° " . $datos['minuta']['id'];
        
        $cuerpo = "<h3>Documento Disponible para Firma</h3>";
        $cuerpo .= "<p>El Secretario Técnico ha " . ($esReenvio ? "aplicado las correcciones" : "generado el borrador") . " de la minuta <b>{$datos['minuta']['comision']}</b>.</p>";
        $cuerpo .= "<p>Por favor, ingrese al sistema para revisar y firmar.</p>";
        
        // En este caso particular, quizás solo quieras notificar a los presidentes, 
        // pero según tu requerimiento de "mantener a todos informados", enviamos a todos.
        $this->enviarA_Todos($datos, $asunto, $cuerpo);
    }

    // --- Helper Privado para iterar destinatarios ---
    private function enviarA_Todos($datos, $asunto, $cuerpo)
    {
        // 1. Enviar al Secretario Técnico
        if (!empty($datos['st']['email'])) {
            $this->sendEmail($datos['st']['email'], $datos['st']['nombre'], $asunto, $cuerpo);
        }

        // 2. Enviar a TODOS los Presidentes
        foreach ($datos['presidentes'] as $presi) {
            if (!empty($presi['correo'])) {
                $this->sendEmail($presi['correo'], $presi['nombre'], $asunto, $cuerpo);
            }
        }
    }
    
    // Función mockup por si no tienes la tuya definida
    private function sendEmail($to, $name, $subject, $body) {
    try {
        $mail = $this->getConfiguredMailer();
        $mail->addAddress($to, $name);
        $mail->Subject = $subject;
        
        // Agregar la firma institucional al cuerpo
        $firmaTag = $this->obtenerTagFirma($mail);
        
        $mail->isHTML(true);
        $mail->Body = "
            <html>
            <body style='font-family: Arial, sans-serif; color: #333;'>
                $body
                <br>
                <p>Atentamente,<br><strong>Sistema COREGEDOC</strong></p>
                $firmaTag
            </body>
            </html>";
            
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Error genérico MailService: " . $mail->ErrorInfo);
        return false;
    }
}
}