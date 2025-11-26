<?php
// controllers/enviar_asistencia_validada.php
// --- VERSIÓN FINAL: Ajustada a tu estructura SQL (coregedoc.sql) ---

session_start();
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../cfg/config.php';
require_once __DIR__ . '/../class/class.conectorDB.php';

use Dompdf\Dompdf;
use Dompdf\Options;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'Error desconocido.'];

// Función para imágenes en PDF
function ImageToDataUrl(String $filename): String {
    if (!file_exists($filename)) return '';
    $mime = @mime_content_type($filename);
    if ($mime === false || strpos($mime, 'image/') !== 0) return '';
    $raw_data = @file_get_contents($filename);
    return "data:{$mime};base64," . base64_encode($raw_data);
}

try {
    // 1. SEGURIDAD
    if (!isset($_SESSION['idUsuario']) || $_SESSION['tipoUsuario_id'] != 2) {
        throw new Exception('Acceso no autorizado. Rol requerido: Secretario Técnico.');
    }
    if (!isset($_POST['idMinuta']) || !is_numeric($_POST['idMinuta'])) {
        throw new Exception('ID de minuta inválido.');
    }

    $idMinuta = (int)$_POST['idMinuta'];
    $db = new conectorDB();
    $pdo = $db->getDatabase();

    // 2. DATOS MINUTA
    $stmtMinuta = $pdo->prepare("SELECT m.fechaMinuta, m.horaMinuta, c.nombreComision 
                                 FROM t_minuta m
                                 LEFT JOIN t_comision c ON m.t_comision_idComision = c.idComision
                                 WHERE m.idMinuta = :id");
    $stmtMinuta->execute(['id' => $idMinuta]);
    $minutaData = $stmtMinuta->fetch(PDO::FETCH_ASSOC);
    
    if (!$minutaData) throw new Exception("Minuta no encontrada.");
    
    $nombreComision = $minutaData['nombreComision'] ?? 'Comisión General';
    $fechaReunion = date('d-m-Y', strtotime($minutaData['fechaMinuta']));
    $horaReunion = date('H:i', strtotime($minutaData['horaMinuta']));

    // 3. DATOS ASISTENCIA (Con la columna correcta de tu SQL)
    // Usamos 'fechaRegistroAsistencia' según tu archivo .sql
    $sqlAsis = "SELECT 
                    CONCAT(u.pNombre, ' ', u.aPaterno, ' ', u.aMaterno) as nombreCompleto,
                    a.idAsistencia, 
                    a.fechaRegistroAsistencia  -- <--- NOMBRE CORREGIDO SEGÚN TU DB
                FROM t_usuario u
                LEFT JOIN t_asistencia a ON a.t_usuario_idUsuario = u.idUsuario AND a.t_minuta_idMinuta = :id
                WHERE u.tipoUsuario_id IN (1, 3, 7) AND u.estado = 1
                ORDER BY u.pNombre ASC, u.aPaterno ASC";
    
    $stmtAsis = $pdo->prepare($sqlAsis);
    $stmtAsis->execute(['id' => $idMinuta]);
    $asistentes = $stmtAsis->fetchAll(PDO::FETCH_ASSOC);

    // 4. HTML DEL PDF
    $rootPath = dirname(__DIR__) . '/';
    $logoGore = ImageToDataUrl($rootPath . 'public/img/logo2.png');
    $logoCore = ImageToDataUrl($rootPath . 'public/img/logoCore1.png');
    $selloImg = ImageToDataUrl($rootPath . 'public/img/aprobacion.png');

    $fechaValidacion = date('d-m-Y H:i:s');

    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Helvetica, sans-serif; font-size: 10pt; color: #333; }
            .header-table { width: 100%; border-bottom: 2px solid #ccc; margin-bottom: 20px; padding-bottom: 10px; }
            .titulo { text-align: center; font-size: 14pt; font-weight: bold; text-transform: uppercase; margin: 10px 0; }
            .info-box { width: 100%; margin-bottom: 20px; border-collapse: collapse; }
            .info-box td { border: 1px solid #999; padding: 6px; }
            .label { background-color: #f0f0f0; font-weight: bold; width: 25%; }
            .tabla-asistencia { width: 100%; border-collapse: collapse; margin-top: 10px; }
            .tabla-asistencia th { background-color: #e9ecef; padding: 8px; border: 1px solid #999; text-align: left; }
            .tabla-asistencia td { padding: 6px; border: 1px solid #ccc; vertical-align: middle; }
            
            .presente { color: green; font-weight: bold; text-align: center; }
            .ausente { color: red; font-weight: bold; text-align: center; }
            .fecha-registro { font-size: 8pt; color: #666; text-align: center; display: block; margin-top: 2px; }

            .sello-container { text-align: center; margin-top: 40px; }
            .sello-img { width: 120px; opacity: 0.8; }
            .firma-txt { font-weight: bold; margin-top: 5px; font-size: 9pt; }
            .footer { position: fixed; bottom: 20px; left: 0; right: 0; text-align: center; font-size: 8pt; color: #888; border-top: 1px solid #eee; padding-top: 5px; }
        </style>
    </head>
    <body>
        <table class="header-table">
            <tr>
                <td width="15%"><img src="' . $logoGore . '" style="width: 80px;"></td>
                <td width="70%" align="center">
                    <strong>GOBIERNO REGIONAL DE VALPARAÍSO</strong><br>
                    CONSEJO REGIONAL
                </td>
                <td width="15%" align="right"><img src="' . $logoCore . '" style="width: 80px;"></td>
            </tr>
        </table>

        <div class="titulo">Certificado de Asistencia Validada</div>

        <table class="info-box">
            <tr>
                <td class="label">N° Minuta:</td><td>' . $idMinuta . '</td>
                <td class="label">Fecha Reunión:</td><td>' . $fechaReunion . '</td>
            </tr>
            <tr>
                <td class="label">Comisión:</td><td>' . htmlspecialchars($nombreComision) . '</td>
                <td class="label">Hora Inicio:</td><td>' . $horaReunion . '</td>
            </tr>
        </table>

        <h3>Detalle de Asistencia</h3>
        <table class="tabla-asistencia">
            <thead>
                <tr>
                    <th>Nombre Consejero(a)</th>
                    <th width="160" style="text-align:center;">Estado</th>
                </tr>
            </thead>
            <tbody>';

    $totalPresentes = 0;
    foreach ($asistentes as $p) {
        if ($p['idAsistencia']) {
            $totalPresentes++;
            
            // Formatear la fecha de registro desde la columna correcta
            $fechaRegStr = '';
            if (!empty($p['fechaRegistroAsistencia'])) {
                $fechaRegStr = date('d-m-Y H:i', strtotime($p['fechaRegistroAsistencia']));
            }

            $stHtml = '<div class="presente">PRESENTE</div>';
            if ($fechaRegStr) {
                $stHtml .= '<span class="fecha-registro">(' . $fechaRegStr . ')</span>';
            }
        } else {
            $stHtml = '<div class="ausente">AUSENTE</div>';
        }

        $html .= '<tr>
                    <td>' . htmlspecialchars($p['nombreCompleto']) . '</td>
                    <td>' . $stHtml . '</td>
                  </tr>';
    }

    $html .= '</tbody></table>
        <p style="text-align:right; margin-top:10px;"><strong>Total Asistentes: ' . $totalPresentes . '</strong></p>

        <div class="sello-container">
            <img src="' . $selloImg . '" class="sello-img"><br>
            <div class="firma-txt">VALIDADO POR SECRETARÍA TÉCNICA</div>
            <div style="font-size: 8pt; color: #555;">' . $fechaValidacion . '</div>
        </div>

        <div class="footer">
            Documento oficial generado el ' . date('d-m-Y H:i:s') . '
        </div>
    </body>
    </html>';

    // 5. RENDER PDF
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('letter', 'portrait');
    $dompdf->render();
    $pdfContent = $dompdf->output();

    // 6. GUARDADO Y LIMPIEZA (Usando tipoAdjunto que SÍ existe en tu DB)
    
    // A. Borrar archivos viejos (Limpieza)
    $sqlOld = "SELECT idAdjunto, pathAdjunto FROM t_adjunto 
               WHERE t_minuta_idMinuta = :id AND tipoAdjunto = 'asistencia'";
    $stmtOld = $pdo->prepare($sqlOld);
    $stmtOld->execute([':id' => $idMinuta]);
    $oldFiles = $stmtOld->fetchAll(PDO::FETCH_ASSOC);

    foreach ($oldFiles as $old) {
        $rutaOld = $rootPath . $old['pathAdjunto'];
        if (file_exists($rutaOld)) @unlink($rutaOld);
        $pdo->prepare("DELETE FROM t_adjunto WHERE idAdjunto = ?")->execute([$old['idAdjunto']]);
    }

    // B. Guardar nuevo PDF (Único)
    $nombreArchivo = "Asistencia_Minuta_{$idMinuta}.pdf";
    $relativePath = "public/docs/asistencia/" . $nombreArchivo;
    $fullPath = $rootPath . $relativePath;
    
    if (!is_dir(dirname($fullPath))) mkdir(dirname($fullPath), 0777, true);
    file_put_contents($fullPath, $pdfContent);

    // C. Registrar en BD (Usamos la columna tipoAdjunto que confirmamos que existe)
    $sqlIns = "INSERT INTO t_adjunto (t_minuta_idMinuta, pathAdjunto, tipoAdjunto) 
               VALUES (:id, :path, 'asistencia')";
    $pdo->prepare($sqlIns)->execute([':id' => $idMinuta, ':path' => $relativePath]);

    // 7. ENVIAR CORREO
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'equiposieteduocuc@gmail.com';
    $mail->Password   = 'iohe aszm lkfl ucsq';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->CharSet    = 'UTF-8';

    $mail->setFrom('equiposieteduocuc@gmail.com', 'COREGEDOC');
    $mail->addAddress('genesis.contreras.vargas@gmail.com', 'Genesis Contreras');

    $mail->isHTML(true);
    $mail->Subject = "Validación de Asistencia - Minuta N° {$idMinuta}";
    
    // Firma embebida
    $firmaPath = $rootPath . 'public/img/firma.jpeg';
    $firmaTag = "";
    if(file_exists($firmaPath)) {
        $mail->AddEmbeddedImage($firmaPath, 'firma_inst', 'firma.jpeg');
        $firmaTag = "<br><br><img src=\"cid:firma_inst\" alt=\"Firma\">";
    }

    $mail->Body = "<html><body>
        <p>Estimado(a),</p>
        <p>Se adjunta el certificado de asistencia validado para la <strong>Minuta N° {$idMinuta}</strong>.</p>
        <p>Este documento incluye la hora exacta de registro de cada consejero.</p>
        <p>Atentamente,<br><strong>Sistema COREGEDOC</strong></p>
        {$firmaTag}
        </body></html>";

    $mail->addAttachment($fullPath, $nombreArchivo);
    $mail->send();

    // 8. UPDATE ESTADO (Columna confirmada en tu SQL)
    try {
        $pdo->prepare("UPDATE t_minuta SET asistencia_validada = 1 WHERE idMinuta = ?")->execute([$idMinuta]);
    } catch (Exception $e) {}

    $response = ['success' => true, 'message' => 'Proceso completado: PDF generado, enviado y asistencia validada.'];

} catch (Exception $e) {
    http_response_code(500);
    error_log("Error enviar_asistencia: " . $e->getMessage());
    $response['message'] = "Error: " . $e->getMessage();
}

echo json_encode($response);
exit;
?>