<?php
// controllers/aprobar_minuta.php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. INCLUIR DEPENDENCIAS Y CONFIGURACIÓN
define('ROOT_PATH', dirname(__DIR__) . '/');
require_once ROOT_PATH . 'class/class.conectorDB.php';
require_once ROOT_PATH . 'vendor/autoload.php'; // Dompdf

use Dompdf\Dompdf;
use Dompdf\Options;

// 2. OBTENER DATOS DE ENTRADA Y SESIÓN
$data = json_decode(file_get_contents('php://input'), true);
$idMinuta = $data['idMinuta'] ?? null;
$idPresidenteLogueado = $_SESSION['idUsuario'] ?? null;
$nombrePresidenteLogueado = trim(($_SESSION['pNombre'] ?? '') . ' ' . ($_SESSION['aPaterno'] ?? ''));

if (!$idMinuta || !$idPresidenteLogueado) {
    echo json_encode(['status' => 'error', 'message' => 'Datos incompletos o sesión no válida.']);
    exit;
}

// ❗️❗️ FUNCIÓN ImageToDataUrl (Definida aquí) ❗️❗️
function ImageToDataUrl(String $filename): String
{
    // Verificar si el archivo existe usando la ruta completa
    if (!file_exists($filename)) {
        // Loggear error o retornar un placeholder/string vacío
        error_log("ImageToDataUrl Error: File not found at " . $filename);
        // throw new Exception('File not found: '.$filename); // Podrías lanzar excepción si prefieres detener
        return ''; // Retornar vacío si no se encuentra
    }

    $mime = mime_content_type($filename);
    if ($mime === false || strpos($mime, 'image/') !== 0) { // Asegurarse que sea una imagen
        error_log("ImageToDataUrl Error: Illegal MIME type for " . $filename . " (Type: " . $mime . ")");
        // throw new Exception('Illegal MIME type.');
        return '';
    }

    $raw_data = file_get_contents($filename);
    if ($raw_data === false || empty($raw_data)) { // Verificar lectura correcta
        error_log("ImageToDataUrl Error: File not readable or empty at " . $filename);
        // throw new Exception('File not readable or empty.');
        return '';
    }

    return "data:{$mime};base64," . base64_encode($raw_data);
}


// 3. FUNCIÓN PARA GENERAR HTML (Modificada para usar Data URI)
function generateMinutaHtml($data, $logoGoreUri, $logoCoreUri)
{ // Recibe los Data URIs
    $comisiones = htmlspecialchars($data['nombreComision1'] ?? 'N/A');
    if (!empty($data['nombreComision2'])) {
        $comisiones .= ' / ' . htmlspecialchars($data['nombreComision2']);
    }

    // Usar directamente $logoGoreUri y $logoCoreUri en las etiquetas <img> src
    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Minuta Aprobada</title><style>' .
        'body{font-family:Helvetica,sans-serif;font-size:10pt;line-height:1.4;}' .
        '.header{margin-bottom:20px;overflow:hidden;}' .
        '.logo-left{float:left;width:80px;height:auto;}' .
        '.logo-right{float:right;width:120px;height:auto;margin-top:10px;}' .
        '.header-center{text-align:center;margin:0 140px 0 90px;}' .
        '.header-center p{margin:0;padding:0;font-size:9pt;font-weight:bold;}' .
        '.header-center .consejo{font-size:10pt;}' .
        '.titulo-minuta{text-align:center;font-weight:bold;font-size:12pt;margin:20px 0 15px 0;text-decoration:underline;}' .
        '.info-tabla{width:100%;border-collapse:collapse;margin-bottom:20px;font-size:9pt;}' .
        '.info-tabla td{padding:4px 8px;border:1px solid #ccc;}' .
        '.info-tabla .label{font-weight:bold;width:15%;background-color:#f2f2f2;}' .
        '.seccion-titulo{font-weight:bold;font-size:10pt;margin:20px 0 5px 0;text-decoration:underline;}' .
        '.asistentes-lista ul{list-style:none;padding:0;margin:0;columns:2;-webkit-columns:2;}' .
        '.asistentes-lista li{margin-bottom:3px;font-size:9pt;line-height:1.2;}' .
        '.sintesis-sesion, .desarrollo-tema{margin-bottom:15px;font-size:10pt;text-align:justify;}' .
        '.desarrollo-tema h4{font-size:10pt;font-weight:bold;margin:10px 0 3px 0;}' .
        '.desarrollo-tema p{margin:0 0 5px 0;}' .
        '.signature-box{margin-top:50px;text-align:center;}' .
        '.signature-line{border-top:1px solid #000;width:50%;margin:30px auto 5px auto;}' .
        '</style></head><body>' .
        '<div class="header">' .
        // ❗️ Usar Data URIs directamente en src ❗️
        '<img src="' . htmlspecialchars($logoGoreUri) . '" class="logo-left" alt="Logo GORE">' .
        '<img src="' . htmlspecialchars($logoCoreUri) . '" class="logo-right" alt="Logo CORE">' .
        '<div class="header-center">' .
        '<p>GOBIERNO REGIONAL. REGIÓN DE VALPARAÍSO</p>' .
        '<p class="consejo">CONSEJO REGIONAL</p>' .
        '<p>COMISIÓN ' . strtoupper($comisiones) . '</p>' .
        '<p>PRESIDENTE: ' . strtoupper(htmlspecialchars($data['nombrePresidente1'] ?? 'N/A')) . '</p>' .
        '</div>' .
        '</div>' .
        // ... (Resto del HTML sin cambios: título, tabla info, asistentes, temas, firma) ...
        '<div class="titulo-minuta">MINUTA REUNIÓN</div>' .
        '<table class="info-tabla">' .
        '<tr><td class="label">Fecha:</td><td>' . htmlspecialchars($data['fechaMinuta'] ?? 'N/A') . '</td><td class="label">Hora:</td><td>' . htmlspecialchars($data['horaMinuta'] ?? 'N/A') . '</td></tr>' .
        '<tr><td class="label">Lugar:</td><td colspan="3">Salón de Plenarios</td></tr>' .
        (!empty($data['nombreComision2']) ? '<tr><td class="label">Pdte. Mixta:</td><td colspan="3">' . htmlspecialchars($data['nombrePresidente2'] ?? 'N/A') . '</td></tr>' : '') .
        '<tr><td class="label">Secretario T.:</td><td colspan="3">' . htmlspecialchars($data['nombreSecretario'] ?? 'N/A') . '</td></tr>' .
        '</table>' .
        '<div class="seccion-titulo">Tabla de la sesión</div><div><ol>';
    if (!empty($data['temas']) && is_array($data['temas'])) {
        foreach ($data['temas'] as $tema) {
            if (!empty(strip_tags($tema['nombreTema'] ?? ''))) {
                $html .= '<li>' . strip_tags($tema['nombreTema']) . '</li>';
            }
        }
    } else {
        $html .= '<li>N/A</li>';
    }
    $html .= '<li>Varios</li></ol></div>' .
        '<div class="seccion-titulo">Asistentes:</div><div class="asistentes-lista"><ul>';
    if (!empty($data['asistentes']) && is_array($data['asistentes'])) {
        foreach ($data['asistentes'] as $asistente) {
            $html .= '<li>' . htmlspecialchars($asistente['nombreCompleto']) . '</li>';
        }
    } else {
        $html .= '<li>No registrados.</li>';
    }
    $html .= '</ul></div>' .
        '<div class="seccion-titulo">Síntesis de la sesión</div><div class="sintesis-sesion"><p>' . ($data['sintesis'] ?? 'N/A') . '</p></div>' .
        '<div class="seccion-titulo">Desarrollo / Acuerdos / Compromisos</div>';
    if (!empty($data['temas']) && is_array($data['temas'])) {
        foreach ($data['temas'] as $index => $tema) {
            if (empty(strip_tags($tema['nombreTema'] ?? ''))) continue;
            $html .= '<div class="desarrollo-tema"><h4>TEMA ' . ($index + 1) . ': ' . strip_tags($tema['nombreTema']) . '</h4>';
            if (!empty($tema['objetivo'])) {
                $html .= '<p><strong>Objetivo:</strong> ' . ($tema['objetivo']) . '</p>';
            }
            if (!empty($tema['descAcuerdo'])) {
                $html .= '<p><strong>Acuerdo:</strong> ' . ($tema['descAcuerdo']) . '</p>';
            }
            if (!empty($tema['compromiso'])) {
                $html .= '<p><strong>Compromiso:</strong> ' . ($tema['compromiso']) . '</p>';
            }
            if (!empty($tema['observacion'])) {
                $html .= '<p><strong>Obs:</strong> ' . ($tema['observacion']) . '</p>';
            }
            $html .= '</div>';
        }
    } else {
        $html .= '<p>No hay detalles registrados.</p>';
    }
    '<div class="signature-box"><div class="signature-line"></div><p>' . htmlspecialchars($data['nombrePresidente1'] ?? 'N/A') . '</p><p>Presidente</p><p>Comisión ' . $comisiones . '</p></div>' .
        '</body></html>';

    return $html;
}

// 4. LÓGICA PRINCIPAL
// -----------------------------------------------------------------------------
$db = null;
$pdfWebPath = null;
$idDocumentoCreado = null;

try {
    $db = new conectorDB();
    $pdo = $db->getDatabase();
    $pdo->beginTransaction();

    // 4.1. VERIFICAR PERMISOS (igual)
    $sql_check = "SELECT t_usuario_idPresidente FROM t_minuta WHERE idMinuta = :idMinuta";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute([':idMinuta' => $idMinuta]);
    $minuta = $stmt_check->fetch(PDO::FETCH_ASSOC);
    if (!$minuta || (int)$minuta['t_usuario_idPresidente'] !== (int)$idPresidenteLogueado) {
        throw new Exception('No tiene permisos para firmar esta minuta.');
    }

    // 4.2. OBTENER DATOS COMPLETOS PARA EL PDF (igual)
    $datosParaPDF = [];
    $sql_minuta_data = "SELECT m.fechaMinuta, m.horaMinuta, m.t_comision_idComision, m.t_usuario_idPresidente FROM t_minuta m WHERE m.idMinuta = :idMinuta";
    $stmt_minuta_data = $pdo->prepare($sql_minuta_data);
    $stmt_minuta_data->execute([':idMinuta' => $idMinuta]);
    $minutaData = $stmt_minuta_data->fetch(PDO::FETCH_ASSOC);
    if (!$minutaData) throw new Exception('Minuta no encontrada.');
    $datosParaPDF = $minutaData;
    $datosParaPDF['nombreSecretario'] = $nombrePresidenteLogueado;
    $sql_nombres = "SELECT c.nombreComision, CONCAT(u.pNombre, ' ', u.aPaterno) as nombrePresidente FROM t_comision c, t_usuario u WHERE c.idComision = :idCom AND u.idUsuario = :idPres";
    $stmt_nombres = $pdo->prepare($sql_nombres);
    $stmt_nombres->execute([':idCom' => $datosParaPDF['t_comision_idComision'], ':idPres' => $datosParaPDF['t_usuario_idPresidente']]);
    $nombres = $stmt_nombres->fetch(PDO::FETCH_ASSOC);
    $datosParaPDF['nombreComision1'] = $nombres['nombreComision'] ?? 'N/A';
    $datosParaPDF['nombrePresidente1'] = $nombres['nombrePresidente'] ?? 'N/A';
    $datosParaPDF['nombreComision2'] = null;
    $datosParaPDF['nombrePresidente2'] = null; // Placeholders
    $sql_asist = "SELECT CONCAT(u.pNombre, ' ', u.aPaterno) as nombreCompleto FROM t_asistencia a JOIN t_usuario u ON a.t_usuario_idUsuario = u.idUsuario WHERE a.t_minuta_idMinuta = :idMinuta ORDER BY u.aPaterno, u.pNombre";
    $stmt_asist = $pdo->prepare($sql_asist);
    $stmt_asist->execute([':idMinuta' => $idMinuta]);
    $datosParaPDF['asistentes'] = $stmt_asist->fetchAll(PDO::FETCH_ASSOC);
    $sql_temas = "SELECT t.nombreTema, t.objetivo, t.compromiso, t.observacion, a.descAcuerdo FROM t_tema t LEFT JOIN t_acuerdo a ON a.t_tema_idTema = t.idTema WHERE t.t_minuta_idMinuta = :idMinuta ORDER BY t.idTema ASC";
    $stmt_temas = $pdo->prepare($sql_temas);
    $stmt_temas->execute([':idMinuta' => $idMinuta]);
    $datosParaPDF['temas'] = $stmt_temas->fetchAll(PDO::FETCH_ASSOC);
    $datosParaPDF['numeroSesion'] = 'XX'; // Placeholder

    // ❗️❗️ 4.3. CONVERTIR LOGOS A DATA URI ❗️❗️
    $logoGoreFilesystemPath = ROOT_PATH . 'public/img/logo2.png'; // Asegúrate que este sea el nombre correcto
    $logoCoreFilesystemPath = ROOT_PATH . 'public/img/logoCore1.png'; // Asegúrate que este sea el nombre correcto
    $logoGoreDataUri = '';
    $logoCoreDataUri = '';
    try {
        $logoGoreDataUri = ImageToDataUrl($logoGoreFilesystemPath);
        $logoCoreDataUri = ImageToDataUrl($logoCoreFilesystemPath);
    } catch (Exception $e) {
        // Loggear el error pero continuar sin los logos si fallan
        error_log("Error convirtiendo logos a Data URI: " . $e->getMessage());
    }

    // 4.4. ACTUALIZAR ESTADO DE LA MINUTA (igual)
    $sql_update_status = "UPDATE t_minuta SET estadoMinuta = 'APROBADA', fechaAprobacion = NOW() WHERE idMinuta = :idMinuta AND estadoMinuta = 'PENDIENTE'";
    $stmt_update_status = $pdo->prepare($sql_update_status);
    $exito_status = $stmt_update_status->execute([':idMinuta' => $idMinuta]);
    if (!$exito_status || $stmt_update_status->rowCount() == 0) {
        throw new Exception('No se pudo actualizar el estado o ya estaba aprobada.');
    }

    // 4.5. GENERAR Y GUARDAR EL PDF FÍSICO (Usando Data URIs)
    $options = new Options();
    $options->set('defaultFont', 'Helvetica');
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', false); // Ya no necesitamos remoto si usamos Data URIs
    // $options->set('chroot', ROOT_PATH); // No necesario con Data URIs
    $options->set('isPhpEnabled', true); // Mantener por si acaso

    $dompdf = new Dompdf($options);

    // Llamar a generateMinutaHtml pasando los Data URIs
    $htmlContent = generateMinutaHtml($datosParaPDF, $logoGoreDataUri, $logoCoreDataUri);

    $dompdf->loadHtml($htmlContent);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    // (El resto del guardado del archivo, inserción en t_documento y actualización de t_minuta es igual)
    $saveDir = ROOT_PATH . 'public/docs/minutas_aprobadas/';
    $filename = "minuta_aprobada_" . $idMinuta . "_" . date('Ymd_His') . ".pdf";
    $fullSavePath = $saveDir . $filename;
    $pdfWebPath = "/corevota/public/docs/minutas_aprobadas/" . $filename;

    if (!is_dir($saveDir)) {
        if (!mkdir($saveDir, 0775, true)) {
            throw new Exception('No se pudo crear directorio PDF.');
        }
    }
    if (file_put_contents($fullSavePath, $dompdf->output()) === false) {
        throw new Exception('No se pudo guardar PDF.');
    }

    // 4.6. INSERTAR EN T_DOCUMENTO (igual)
    $sql_doc = "INSERT INTO t_documento (nombreArchivo, pathArchivo, fechaCreacion, tipoDocumento, t_usuario_idCreador) VALUES (:nombre, :path, NOW(), 'MINUTA_APROBADA', :idCreador)";
    $stmt_doc = $pdo->prepare($sql_doc);
    $stmt_doc->execute([':nombre' => $filename, ':path' => $pdfWebPath, ':idCreador' => $idPresidenteLogueado]);
    $idDocumentoCreado = $pdo->lastInsertId();
    if (!$idDocumentoCreado) throw new Exception('No se pudo crear registro t_documento.');

    // 4.7. ACTUALIZAR T_MINUTA (igual)
    $sql_update_links = "UPDATE t_minuta SET pathArchivo = :pathPDF, t_documento_idFijo = :idDoc WHERE idMinuta = :idMinuta";
    $stmt_update_links = $pdo->prepare($sql_update_links);
    $exito_links = $stmt_update_links->execute([':pathPDF' => $pdfWebPath, ':idDoc' => $idDocumentoCreado, ':idMinuta' => $idMinuta]);
    if (!$exito_links) throw new Exception('No se pudo actualizar t_minuta con ruta PDF.');

    // 4.8. COMMIT FINAL (igual)
    $pdo->commit();

    echo json_encode(['status' => 'success', 'message' => 'Minuta aprobada y PDF generado.', 'pdfPath' => $pdfWebPath]);
} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error en aprobar_minuta.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Error al aprobar: ' . $e->getMessage()]);
} finally {
    $pdo = null;
    $db = null;
}
