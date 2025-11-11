<?php
// controllers/enviar_asistencia_validada.php
// --- VERSIÓN AJUSTADA (Usa PDF existente) ---

session_start();
// Cargar todas las dependencias de Composer
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../cfg/config.php';
require_once __DIR__ . '/../class/class.conectorDB.php';

// Ya no necesitamos Dompdf, porque no vamos a crear un PDF, solo a enviarlo.
// use Dompdf\Dompdf; 
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'Error desconocido.'];

try {
    // 1. Verificación de Seguridad y Sesión
    if (!isset($_SESSION['idUsuario']) || $_SESSION['tipoUsuario_id'] != 2) {
        throw new Exception('Acceso no autorizado. Debe ser Secretario Técnico.');
    }

    if (!isset($_POST['idMinuta']) || !is_numeric($_POST['idMinuta'])) {
        throw new Exception('ID de minuta inválido o no proporcionado.');
    }

    $idMinuta = (int)$_POST['idMinuta'];
    $idUsuarioST = $_SESSION['idUsuario'];

    $dbCon = new conectorDB();
    $pdo = $dbCon->getDatabase();

    // 2. Obtener datos de la minuta (Comisión y Fecha)
    // (Lo mantenemos para el asunto del correo)
    $stmtMinuta = $pdo->prepare("SELECT m.t_comision_idComision, m.fechaMinuta, c.nombreComision 
                                FROM t_minuta m
                                LEFT JOIN t_comision c ON m.t_comision_idComision = c.idComision
                                WHERE m.idMinuta = :id");
    $stmtMinuta->execute(['id' => $idMinuta]);
    $minutaData = $stmtMinuta->fetch(PDO::FETCH_ASSOC);

    $nombreComision = $minutaData['nombreComision'] ?? 'Comisión no especificada';


    // 3. Encontrar el PDF de asistencia "bonito" existente
    // (Esta es la lógica que tomamos de tu script 'enviar_aprobacion.php')
    $sqlPdf = "SELECT pathAdjunto FROM t_adjunto 
               WHERE t_minuta_idMinuta = :idMinuta AND tipoAdjunto = 'asistencia'
               ORDER BY idAdjunto DESC LIMIT 1";
    $stmtPdf = $pdo->prepare($sqlPdf);
    $stmtPdf->execute([':idMinuta' => $idMinuta]);
    $pdfPathRelativo = $stmtPdf->fetchColumn();

    if (empty($pdfPathRelativo)) {
        // Si no hay PDF, es porque el ST no ha guardado la minuta, lo cual es un requisito
        throw new Exception("No se encontró ningún PDF de asistencia (tipoAdjunto = 'asistencia') para la Minuta N° {$idMinuta}. Por favor, 'Guarde Borrador' primero para generar el PDF de asistencia.");
    }

    // 4. Construir la ruta física completa al archivo
    $fullPathPdf = __DIR__ . '/../' . $pdfPathRelativo;

    if (!file_exists($fullPathPdf)) {
        error_log("ERROR CRÍTICO idMinuta {$idMinuta}: El PDF de asistencia se encontró en la BD ('{$pdfPathRelativo}') pero el archivo no existe en el servidor ('{$fullPathPdf}').");
        throw new Exception("Error crítico: El archivo PDF de asistencia ('{$pdfPathRelativo}') no existe en el servidor.");
    }

    // 5. Enviar el Correo con PHPMailer
    $mail = new PHPMailer(true);

    // Configuración del servidor SMTP (Tomada de tu script de referencia 'recuperar_contrasena.php')
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'equiposieteduocuc@gmail.com';
    $mail->Password   = 'iohe aszm lkfl ucsq';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->CharSet    = 'UTF-8';

    // Email DE (Desde)
    $mail->setFrom('equiposieteduocuc@gmail.com', 'CORE Vota'); //

    // Email PARA (Para)
    $mail->addAddress('genesis.contreras.vargas@gmail.com', 'Genesis Contreras');

    // Contenido
    $mail->isHTML(true);
    $mail->Subject = "Validación de Asistencia - Minuta N° {$idMinuta} ({$nombreComision})";
    $mail->Body    = "El Secretario Técnico ha validado la asistencia de la minuta N° {$idMinuta}.<br>Se adjunta el documento PDF con el detalle.<br><br>Atte,<br>Sistema CoreVota.";


    // 1. Definir la ruta de la firma
    $firmaPathRelativa = 'public/img/firma.jpeg'; //
    $fullPathFirma = __DIR__ . '/../' . $firmaPathRelativa;

    // 2. Definir el HTML de la firma
    $firmaHTML = ""; // Inicia vacío
    if (file_exists($fullPathFirma)) {
        // 3. Adjuntar la imagen si existe
        $mail->AddEmbeddedImage($fullPathFirma, 'firma_institucional', 'firma.jpeg'); //
        $firmaHTML = "<br><br><img src=\"cid:firma_institucional\" alt=\"Firma Institucional\">"; //
    } else {
        error_log("ADVERTENCIA (enviar_asistencia_validada.php): No se encontró el archivo de firma en: " . $fullPathFirma);
    }
    
    // 4. Construir el Body final
    $mail->Body = "<html><body>
                    <p>El Secretario Técnico ha validado la asistencia de la <strong>Minuta N° {$idMinuta}</strong>.</p>
                    <p>Se adjunta el documento PDF con el detalle.</p>
                    <p>Atte,<br>Sistema CoreVota</p>
                    {$firmaHTML}
                   </body></html>";
    // --- FIN: LÓGICA DE LA FIRMA ---
    // Adjuntar el PDF "bonito" (encontrado en el disco)
    $mail->addAttachment($fullPathPdf, "Asistencia_Minuta_{$idMinuta}.pdf");

    $mail->send();

    // --- FIN DE LA MODIFICACIÓN ---


    // 6. Actualizar la Base de Datos
    // Si el correo se envió CON ÉXITO, marcamos la minuta como validada.
    $stmt = $pdo->prepare("UPDATE t_minuta SET asistencia_validada = 1 WHERE idMinuta = ?");
    $stmt->execute([$idMinuta]);

    $response = ['success' => true, 'message' => 'Correo enviado y asistencia validada.'];
} catch (Exception $e) {
    // Captura errores de PHPMailer o PDO
    http_response_code(500);
    error_log("Error en enviar_asistencia_validada.php: " . $e->getMessage());
    $response = ['success' => false, 'message' => 'Error al procesar la solicitud: ' . $e->getMessage()];
}

echo json_encode($response);
exit;
