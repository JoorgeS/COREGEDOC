<?php
// controllers/enviar_asistencia_validada.php
// --- VERSIÓN AJUSTADA (Usa PDF existente y verifica rutas) ---

session_start();
// Cargar todas las dependencias de Composer
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../cfg/config.php';
require_once __DIR__ . '/../class/class.conectorDB.php';

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
  $stmtMinuta = $pdo->prepare("SELECT m.t_comision_idComision, m.fechaMinuta, c.nombreComision 
                FROM t_minuta m
                LEFT JOIN t_comision c ON m.t_comision_idComision = c.idComision
                WHERE m.idMinuta = :id");
  $stmtMinuta->execute(['id' => $idMinuta]);
  $minutaData = $stmtMinuta->fetch(PDO::FETCH_ASSOC);

  $nombreComision = $minutaData['nombreComision'] ?? 'Comisión no especificada';

  // 3. Encontrar el PDF de asistencia "bonito" existente
  // Se espera que la minuta haya sido "guardada como borrador" previamente para generar este PDF.
  $sqlPdf = "SELECT pathAdjunto FROM t_adjunto 
       WHERE t_minuta_idMinuta = :idMinuta AND tipoAdjunto = 'asistencia'
       ORDER BY idAdjunto DESC LIMIT 1";
  $stmtPdf = $pdo->prepare($sqlPdf);
  $stmtPdf->execute([':idMinuta' => $idMinuta]);
  $pdfPathRelativo = $stmtPdf->fetchColumn();

  if (empty($pdfPathRelativo)) {
    throw new Exception("No se encontró ningún PDF de asistencia. Por favor, 'Guarde Borrador' primero.");
  }

  // 4. Construir la ruta física completa al archivo
  $rootPath = dirname(__DIR__) . '/';
  // 🚨 CORRECCIÓN DE RUTA: Concatenamos ROOT_PATH con el path relativo de la BD
  $fullPathPdf = $rootPath . $pdfPathRelativo; 

  if (!file_exists($fullPathPdf)) {
    error_log("ERROR CRÍTICO idMinuta {$idMinuta}: Archivo no encontrado en: '{$fullPathPdf}'.");
    throw new Exception("Error crítico: El archivo PDF de asistencia ('{$pdfPathRelativo}') no existe en el servidor. Reintente guardando el borrador.");
  }

  // 5. Enviar el Correo con PHPMailer
  $mail = new PHPMailer(true);

  // Configuración del servidor SMTP 
  $mail->isSMTP();
  $mail->Host    = 'smtp.gmail.com';
  $mail->SMTPAuth  = true;
  $mail->Username  = 'equiposieteduocuc@gmail.com';
  $mail->Password  = 'iohe aszm lkfl ucsq';
  $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
  $mail->Port    = 587;
  $mail->CharSet  = 'UTF-8';

  // Email DE (Desde)
  $mail->setFrom('equiposieteduocuc@gmail.com', 'COREGEDOC'); 

  // Email PARA (Para Genesis Contreras)
  // Usamos la dirección de correo de prueba, se puede parametrizar si es necesario
  $mail->addAddress('genesis.contreras.vargas@gmail.com', 'Genesis Contreras');

  // Contenido
  $mail->isHTML(true);
  $mail->Subject = "Validación de Asistencia - Minuta N° {$idMinuta} ({$nombreComision})";

  // Lógica para adjuntar la firma (se mantiene)
  $firmaPathRelativa = 'public/img/firma.jpeg'; 
  $fullPathFirma = $rootPath . $firmaPathRelativa;

  $firmaHTML = ""; 
  if (file_exists($fullPathFirma)) {
    $mail->AddEmbeddedImage($fullPathFirma, 'firma_institucional', 'firma.jpeg'); 
    $firmaHTML = "<br><br><img src=\"cid:firma_institucional\" alt=\"Firma Institucional\">"; 
  } else {
    error_log("ADVERTENCIA (enviar_asistencia_validada.php): No se encontró el archivo de firma en: " . $fullPathFirma);
  }
  
  $mail->Body = "<html><body>
          <p>El Secretario Técnico ha validado la asistencia de la <strong>Minuta N° {$idMinuta}</strong>.</p>
          <p>Se adjunta el documento PDF con el detalle.</p>
          <p>Atte,<br>Sistema COREGEDOC</p>
          {$firmaHTML}
         </body></html>";
  
  // Adjuntar el PDF "bonito"
  $mail->addAttachment($fullPathPdf, "Asistencia_Minuta_{$idMinuta}.pdf");

  $mail->send();

  // 6. Actualizar la Base de Datos
  // Si el correo se envió CON ÉXITO, marcamos la minuta como validada.
  $stmt = $pdo->prepare("UPDATE t_minuta SET asistencia_validada = 1 WHERE idMinuta = ?");
  $stmt->execute([$idMinuta]);

  $response = ['success' => true, 'message' => 'Correo enviado y asistencia validada.'];
} catch (Exception $e) {
  // Captura errores de PHPMailer o PDO
  http_response_code(500);
  error_log("Error en enviar_asistencia_validada.php: " . $e->getMessage());
  // Usamos el mensaje específico de PHPMailer si está disponible, si no, el mensaje genérico.
  $errorMessage = $e instanceof PHPMailer ? 'Error de envío de correo: ' . $e->getMessage() : 'Error al procesar la solicitud: ' . $e->getMessage();
  $response = ['success' => false, 'message' => $errorMessage];
}

echo json_encode($response);
exit;