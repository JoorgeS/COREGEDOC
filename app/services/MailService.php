<?php
namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class MailService {
    
    public function enviarAsistencia($destinatario, $rutaPdf, $datosReunion) {
        $mail = new PHPMailer(true);

        try {
            // Configuración del Servidor (Toma los datos de tu config/database.ini o settings.php)
            // Aquí hardcodeamos los datos seguros de tu prompt inicial para que funcione ya.
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'equiposieteduocuc@gmail.com';
            $mail->Password   = 'ioheaszmlkflucsq'; // Tu clave de aplicación
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            $mail->CharSet    = 'UTF-8';

            // Remitente y Destinatario
            $mail->setFrom('equiposieteduocuc@gmail.com', 'Sistema COREGEDOC');
            $mail->addAddress($destinatario); 

            // Adjunto
            $mail->addAttachment($rutaPdf);

            // Contenido
            $mail->isHTML(true);
            $mail->Subject = 'Validación de Asistencia - Minuta #' . $datosReunion['idMinuta'];
            $mail->Body    = "
                <h3>Reporte de Asistencia Validada</h3>
                <p>Estimada Gestión,</p>
                <p>El Secretario Técnico ha validado la asistencia para la siguiente reunión:</p>
                <ul>
                    <li><strong>Reunión:</strong> {$datosReunion['nombreReunion']}</li>
                    <li><strong>Comisión:</strong> {$datosReunion['nombreComision']}</li>
                    <li><strong>Fecha:</strong> {$datosReunion['fecha']}</li>
                </ul>
                <p>Se adjunta el documento PDF con el detalle.</p>
                <hr>
                <small>Sistema de Gestión Documental CORE</small>
            ";

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Error Mailer: {$mail->ErrorInfo}");
            return false;
        }
    }
}