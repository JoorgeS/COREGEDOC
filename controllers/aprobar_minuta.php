<?php
// /corevota/controllers/aprobar_minuta.php
header('Content-Type: application/json');
error_reporting(E_ALL); // Mantener errores visibles por ahora
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
$idUsuarioLogueado = $_SESSION['idUsuario'] ?? null;
$nombreUsuarioLogueado = trim(($_SESSION['pNombre'] ?? '') . ' ' . ($_SESSION['aPaterno'] ?? 'N/A'));

if (!$idMinuta || !$idUsuarioLogueado || !is_numeric($idMinuta)) {
    echo json_encode(['status' => 'error', 'message' => 'Datos incompletos, sesión no válida o ID de minuta inválido.']);
    exit;
}

// -----------------------------------------------------------------------------
// FUNCIÓN ImageToDataUrl (se mantiene por si usas logos; si no existen, no rompe)
// -----------------------------------------------------------------------------
function ImageToDataUrl(String $filename): String {
    if (!file_exists($filename)) {
        error_log("ImageToDataUrl Error: File not found at " . $filename);
        return '';
    }
    $mime = @mime_content_type($filename);
    if ($mime === false || strpos($mime, 'image/') !== 0) {
        error_log("ImageToDataUrl Error: Illegal MIME type for " . $filename . " (Type: " . $mime . ")");
        return '';
    }
    $raw_data = @file_get_contents($filename);
    if ($raw_data === false || empty($raw_data)) {
        error_log("ImageToDataUrl Error: File not readable or empty at " . $filename);
        return '';
    }
    return "data:{$mime};base64," . base64_encode($raw_data);
}

// -----------------------------------------------------------------------------
// FUNCIÓN PARA GENERAR HTML (mantiene todo + añade bloque de firma en texto)
// -----------------------------------------------------------------------------
function generateMinutaHtml($data, $logoGoreUri, $logoCoreUri) {
    // --- Preparar datos del encabezado ---
    $idMinuta = htmlspecialchars($data['minuta_info']['idMinuta'] ?? 'N/A');
    $fecha = htmlspecialchars(date('d-m-Y', strtotime($data['minuta_info']['fechaMinuta'] ?? 'now')));
    $hora = htmlspecialchars(date('H:i', strtotime($data['minuta_info']['horaMinuta'] ?? 'now')));
    $secretario = htmlspecialchars($data['secretario_info']['nombreCompleto'] ?? 'N/A');

    // Comisiones y presidentes
    $com1 = $data['comisiones_info']['com1'] ?? null;
    $com2 = $data['comisiones_info']['com2'] ?? null;
    $com3 = $data['comisiones_info']['com3'] ?? null;

    $comision1_nombre    = htmlspecialchars($com1['nombre']     ?? 'N/A');
    $presidente1_nombre  = htmlspecialchars($com1['presidente'] ?? 'N/A');
    $comision2_nombre    = isset($com2['nombre'])    ? htmlspecialchars($com2['nombre'])    : null;
    $presidente2_nombre  = isset($com2['presidente'])? htmlspecialchars($com2['presidente']): null;
    $comision3_nombre    = isset($com3['nombre'])    ? htmlspecialchars($com3['nombre'])    : null;
    $presidente3_nombre  = isset($com3['presidente'])? htmlspecialchars($com3['presidente']): null;

    $esMixta = ($comision2_nombre || $comision3_nombre);

    // Título de comisiones en header
    $tituloComisionesHeader = $comision1_nombre;
    if ($comision2_nombre) $tituloComisionesHeader .= " / " . $comision2_nombre;
    if ($comision3_nombre) $tituloComisionesHeader .= " / " . $comision3_nombre;

    // Datos de firma (enviados desde PHP)
    $firmaNombre   = htmlspecialchars($data['firma']['nombre'] ?? 'N/A');
    $firmaFecha    = htmlspecialchars($data['firma']['fechaHora'] ?? '');
    $firmaRut      = htmlspecialchars($data['firma']['rut'] ?? '');
    $firmaCorreo   = htmlspecialchars($data['firma']['correo'] ?? '');
    $firmaCargo    = htmlspecialchars($data['firma']['cargo'] ?? '');
    $firmaUnidad   = htmlspecialchars($data['firma']['unidad'] ?? '');

    // --- HTML ---
    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Minuta Aprobada '.$idMinuta.'</title><style>' .
        'body{font-family:Helvetica,sans-serif;font-size:10pt;line-height:1.4;}' .
        '.header{margin-bottom:20px;overflow:hidden;border-bottom:1px solid #ccc;padding-bottom:10px;}' .
        '.logo-left{float:left;width:70px;height:auto;margin-top:5px;}' .
        '.logo-right{float:right;width:100px;height:auto;}' .
        '.header-center{text-align:center;margin:0 110px 0 80px;}' .
        '.header-center p{margin:0;padding:0;font-size:9pt;font-weight:bold;}' .
        '.header-center .consejo{font-size:10pt;}' .
        '.titulo-minuta{text-align:center;font-weight:bold;font-size:12pt;margin:20px 0 15px 0;text-decoration:underline;}' .
        '.info-tabla{width:100%;border-collapse:collapse;margin-bottom:20px;font-size:9pt;}' .
        '.info-tabla td{padding:4px 8px;border:1px solid #ccc;vertical-align:top;}' .
        '.info-tabla .label{font-weight:bold;width:150px;background-color:#f2f2f2;}' .
        '.seccion-titulo{font-weight:bold;font-size:11pt;margin:25px 0 8px 0;text-decoration:underline;page-break-after:avoid;}' .
        '.asistentes-lista ul{list-style:disc;padding-left:20px;margin:5px 0;columns:2;-webkit-columns:2;column-gap:30px;}' .
        '.asistentes-lista li{margin-bottom:3px;font-size:9pt;line-height:1.2;page-break-inside:avoid;}' .
        '.desarrollo-tema{margin-bottom:15px;font-size:10pt;text-align:justify;page-break-inside:avoid;}' .
        '.desarrollo-tema h4{font-size:10pt;font-weight:bold;margin:10px 0 3px 0;background-color:#f2f2f2;padding:3px 5px;border:1px solid #ddd;}' .
        '.desarrollo-tema div{margin:0 0 8px 5px;padding-left:5px;border-left:2px solid #eee;}' .
        '.desarrollo-tema strong{display:block;margin-bottom:2px;font-size:9pt;color:#555;}' .
        '.signature-box{margin-top:40px;text-align:center;page-break-inside:avoid;}' .
        '.signature-line{border-top:1px solid #000;width:50%;margin:20px auto 5px auto;}' .
        '.firma-chip{font-size:9pt;color:#222;text-align:left;width:70%;margin:10px auto;border:1px dashed #aaa;padding:8px;border-radius:6px;}' .
        '</style></head><body>' .

        '<div class="header">' .
        ($logoGoreUri ? '<img src="'.htmlspecialchars($logoGoreUri).'" class="logo-left" alt="Logo GORE">' : '') .
        ($logoCoreUri ? '<img src="'.htmlspecialchars($logoCoreUri).'" class="logo-right" alt="Logo CORE">' : '') .
        '<div class="header-center">' .
        '<p>GOBIERNO REGIONAL. REGIÓN DE VALPARAÍSO</p>' .
        '<p class="consejo">CONSEJO REGIONAL</p>' .
        '<p>COMISIÓN(ES): ' . strtoupper($tituloComisionesHeader) . '</p>' .
        '</div>' .
        '</div>' .

        '<div class="titulo-minuta">MINUTA REUNIÓN</div>' .
        '<table class="info-tabla">' .
        '<tr><td class="label">N° Minuta:</td><td>' . $idMinuta . '</td><td class="label">Secretario T.:</td><td>' . $secretario . '</td></tr>' .
        '<tr><td class="label">Fecha:</td><td>' . $fecha . '</td><td class="label">Hora:</td><td>' . $hora . '</td></tr>';

    if (!$esMixta) {
        $html .= '<tr><td class="label">Comisión:</td><td>' . $comision1_nombre . '</td><td class="label">Presidente:</td><td>' . $presidente1_nombre . '</td></tr>';
    } else {
        $html .= '<tr><td class="label">1° Comisión:</td><td>' . $comision1_nombre . '</td><td class="label">1° Presidente:</td><td>' . $presidente1_nombre . '</td></tr>';
        if ($comision2_nombre) { $html .= '<tr><td class="label">2° Comisión:</td><td>' . $comision2_nombre . '</td><td class="label">2° Presidente:</td><td>' . $presidente2_nombre . '</td></tr>'; }
        if ($comision3_nombre) { $html .= '<tr><td class="label">3° Comisión:</td><td>' . $comision3_nombre . '</td><td class="label">3° Presidente:</td><td>' . $presidente3_nombre . '</td></tr>'; }
    }
    $html .= '</table>';

    // Asistentes
    $html .= '<div class="seccion-titulo">Asistentes:</div><div class="asistentes-lista"><ul>';
    if (!empty($data['asistentes']) && is_array($data['asistentes'])) {
        foreach ($data['asistentes'] as $asistente) {
            $html .= '<li>' . htmlspecialchars($asistente['nombreCompleto']) . '</li>';
        }
    } else {
        $html .= '<li>No se registraron asistentes.</li>';
    }
    $html .= '</ul></div>';

    // Tabla de la sesión (temas título)
    $html .= '<div class="seccion-titulo">Tabla de la sesión:</div><div><ol style="font-size:9pt;padding-left:20px;">';
    $temasExisten = false;
    if (!empty($data['temas']) && is_array($data['temas'])) {
        foreach ($data['temas'] as $tema) {
            $nombreTemaLimpio = trim(strip_tags($tema['nombreTema'] ?? ''));
            if ($nombreTemaLimpio !== '') {
                $html .= '<li>' . $nombreTemaLimpio . '</li>';
                $temasExisten = true;
            }
        }
    }
    if (!$temasExisten) { $html .= '<li>No se definieron temas específicos.</li>'; }
    $html .= '</ol></div>';

    // Desarrollo / acuerdos / compromisos
    $html .= '<div class="seccion-titulo">Desarrollo / Acuerdos / Compromisos:</div>';
    $temasExisten = false;
    if (!empty($data['temas']) && is_array($data['temas'])) {
        foreach ($data['temas'] as $index => $tema) {
            $nombreTemaLimpio = trim(strip_tags($tema['nombreTema'] ?? ''));
            if ($nombreTemaLimpio === '') continue;
            $temasExisten = true;

            $html .= '<div class="desarrollo-tema"><h4>TEMA ' . ($index + 1) . ': ' . $nombreTemaLimpio . '</h4>';
            if (!empty(trim($tema['objetivo'] ?? '')))     { $html .= '<div><strong>Objetivo:</strong> '     . $tema['objetivo']     . '</div>'; }
            if (!empty(trim($tema['descAcuerdo'] ?? '')))  { $html .= '<div><strong>Acuerdo:</strong> '      . $tema['descAcuerdo']  . '</div>'; }
            if (!empty(trim($tema['compromiso'] ?? '')))   { $html .= '<div><strong>Compromiso:</strong> '    . $tema['compromiso']   . '</div>'; }
            if (!empty(trim($tema['observacion'] ?? '')))  { $html .= '<div><strong>Observaciones:</strong> ' . $tema['observacion']  . '</div>'; }
            $html .= '</div>';
        }
    }
    if (!$temasExisten) {
        $html .= '<p style="font-size:10pt;">No hay detalles registrados para los temas.</p>';
    }

    // Firma en texto (sin imágenes)
    $html .= '<div class="signature-box">
                <div class="signature-line"></div>
                <p>' . $presidente1_nombre . '</p>
                <p>Presidente</p>
                <p>Comisión ' . $comision1_nombre . '</p>
                <div class="firma-chip">
                    <strong>Firmado electrónicamente por:</strong> ' . $firmaNombre . '<br/>' .
                    ($firmaCargo  ? ('<strong>Cargo:</strong> '  . $firmaCargo  . '<br/>') : '') .
                    ($firmaUnidad ? ('<strong>Unidad:</strong> ' . $firmaUnidad . '<br/>') : '') .
                    ($firmaRut    ? ('<strong>RUT:</strong> '    . $firmaRut    . '<br/>') : '') .
                    ($firmaCorreo ? ('<strong>Correo:</strong> ' . $firmaCorreo . '<br/>') : '') .
                    '<strong>Fecha y hora de firma:</strong> ' . $firmaFecha . '
                </div>
              </div>';

    $html .= '</body></html>';
    return $html;
}

// -----------------------------------------------------------------------------
// FUNCIÓN PARA OBTENER DATOS COMPLETOS (mantiene todo + soporta columnas mixtas si existen)
// -----------------------------------------------------------------------------
function getMinutaCompletaParaPDF($pdo, $idMinuta) {
    $datos = [
        'minuta_info'     => null,
        'comisiones_info' => ['com1' => null, 'com2' => null, 'com3' => null],
        'secretario_info' => null,
        'asistentes'      => [],
        'temas'           => []
    ];

    // 1) Intento "completo" con posibles columnas de mixta (si existen)
    $minuta = null;
    try {
        $sql_base_try = "SELECT 
                            m.idMinuta, m.fechaMinuta, m.horaMinuta,
                            r.t_comision_idComision,
                            r.t_comision_idComision_mixta,
                            r.t_comision_idComision_mixta2
                         FROM t_minuta m
                         LEFT JOIN t_reunion r ON m.idMinuta = r.t_minuta_idMinuta
                         WHERE m.idMinuta = :idMinuta";
        $stmt = $pdo->prepare($sql_base_try);
        $stmt->execute([':idMinuta' => $idMinuta]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) $minuta = $row;
    } catch (Throwable $e) {
        // Si falla (columnas o tabla no existen), hacemos el fallback
        error_log("INFO: columnas mixta no disponibles, usando fallback simple. " . $e->getMessage());
    }

    // 2) Fallback simple (solo comisión principal) si no obtuvimos nada
    if (!$minuta) {
        $sql_base = "SELECT m.idMinuta, m.fechaMinuta, m.horaMinuta, m.t_comision_idComision
                     FROM t_minuta m
                     WHERE m.idMinuta = :idMinuta";
        $stmt = $pdo->prepare($sql_base);
        $stmt->execute([':idMinuta' => $idMinuta]);
        $minuta = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$minuta) {
        throw new Exception("Minuta base no encontrada para ID: $idMinuta");
    }
    $datos['minuta_info'] = $minuta;

    // 3) Armar lista de comisiones a consultar (las que existan y no sean null)
    $idsComisiones = [];
    if (!empty($minuta['t_comision_idComision']))            $idsComisiones['com1'] = (int)$minuta['t_comision_idComision'];
    if (!empty($minuta['t_comision_idComision_mixta']))      $idsComisiones['com2'] = (int)$minuta['t_comision_idComision_mixta'];
    if (!empty($minuta['t_comision_idComision_mixta2']))     $idsComisiones['com3'] = (int)$minuta['t_comision_idComision_mixta2'];

    if (!empty($idsComisiones)) {
        $placeholders = implode(',', array_fill(0, count($idsComisiones), '?'));
        $sql_com_pres = "SELECT c.idComision, c.nombreComision, c.t_usuario_idPresidente,
                                u.pNombre, u.aPaterno
                         FROM t_comision c
                         LEFT JOIN t_usuario u ON c.t_usuario_idPresidente = u.idUsuario
                         WHERE c.idComision IN ($placeholders)";
        $stmt = $pdo->prepare($sql_com_pres);
        $stmt->execute(array_values($idsComisiones));
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC | PDO::FETCH_UNIQUE);

        foreach ($idsComisiones as $slot => $idC) {
            if (isset($rows[$idC])) {
                $r = $rows[$idC];
                $datos['comisiones_info'][$slot] = [
                    'nombre'     => $r['nombreComision'],
                    'presidente' => trim(($r['pNombre'] ?? '') . ' ' . ($r['aPaterno'] ?? '')) ?: 'No Asignado'
                ];
            }
        }
    }

    // 4) Secretario técnico (primer usuario tipo 2 o 6)
    $sql_sec = "SELECT CONCAT(pNombre,' ',aPaterno) AS nombreCompleto
                FROM t_usuario WHERE tipoUsuario_id IN (2,6) LIMIT 1";
    $stmt = $pdo->query($sql_sec);
    $datos['secretario_info'] = $stmt->fetch(PDO::FETCH_ASSOC);

    // 5) Asistentes (consejeros tipo 1)
    $sql_asist = "SELECT CONCAT(u.pNombre,' ',u.aPaterno) AS nombreCompleto
                  FROM t_asistencia a
                  JOIN t_usuario u ON a.t_usuario_idUsuario = u.idUsuario
                  WHERE a.t_minuta_idMinuta = :idMinuta AND u.tipoUsuario_id = 1
                  ORDER BY u.aPaterno, u.pNombre";
    $stmt = $pdo->prepare($sql_asist);
    $stmt->execute([':idMinuta' => $idMinuta]);
    $datos['asistentes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 6) Temas y acuerdos
    $sql_temas = "SELECT t.nombreTema, t.objetivo, t.compromiso, t.observacion, a.descAcuerdo
                  FROM t_tema t
                  LEFT JOIN t_acuerdo a ON a.t_tema_idTema = t.idTema
                  WHERE t.t_minuta_idMinuta = :idMinuta
                  ORDER BY t.idTema ASC";
    $stmt = $pdo->prepare($sql_temas);
    $stmt->execute([':idMinuta' => $idMinuta]);
    $datos['temas'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return $datos;
}

// -----------------------------------------------------------------------------
// LÓGICA PRINCIPAL
// -----------------------------------------------------------------------------
$db = null;
$pdfWebPath = null;
$idDocumentoCreado = null;

try {
    $db = new conectorDB();
    $pdo = $db->getDatabase();
    $pdo->beginTransaction();

    // 1) Permisos: solo el presidente asignado puede aprobar y debe estar PENDIENTE
    $sql_check = "SELECT t_usuario_idPresidente FROM t_minuta 
                  WHERE idMinuta = :idMinuta AND estadoMinuta = 'PENDIENTE'";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute([':idMinuta' => $idMinuta]);
    $minuta = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if (!$minuta) {
        throw new Exception('Minuta no encontrada o ya está aprobada.');
    }
    if ((int)$minuta['t_usuario_idPresidente'] !== (int)$idUsuarioLogueado) {
        throw new Exception('No tiene permisos para firmar esta minuta (solo el presidente asignado).');
    }

    // 2) Datos completos para el PDF
    $datosParaPDF = getMinutaCompletaParaPDF($pdo, $idMinuta);

    // 3) Datos de firma (texto, sin imágenes)
    $datosParaPDF['firma'] = [
        'nombre'    => $nombreUsuarioLogueado,
        'rut'       => $_SESSION['rut']    ?? null,   // si lo manejas en sesión
        'correo'    => $_SESSION['email']  ?? null,
        'cargo'     => $_SESSION['cargo']  ?? null,
        'unidad'    => $_SESSION['unidad'] ?? null,
        'fechaHora' => date('Y-m-d H:i:s')
    ];

    // 4) Logos en Data URI (si no existen, se omiten)
    $logoGoreDataUri = ImageToDataUrl(ROOT_PATH . 'public/img/logo2.png');
    $logoCoreDataUri = ImageToDataUrl(ROOT_PATH . 'public/img/logoCore1.png');

    // 5) Generar HTML y PDF
    $htmlContent = generateMinutaHtml($datosParaPDF, $logoGoreDataUri, $logoCoreDataUri);

    $options = new Options();
    $options->set('defaultFont', 'Helvetica');
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', false);
    $options->set('isPhpEnabled', true);
    $dompdf = new Dompdf($options);

    $dompdf->loadHtml($htmlContent);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    $saveDir = ROOT_PATH . 'public/docs/minutas_aprobadas/';
    $filename = "minuta_aprobada_" . $idMinuta . "_" . date('Ymd_His') . ".pdf";
    $fullSavePath = $saveDir . $filename;
    $pdfWebPath   = "/corevota/public/docs/minutas_aprobadas/" . $filename;

    if (!is_dir($saveDir)) {
        if (!mkdir($saveDir, 0775, true)) {
            throw new Exception('No se pudo crear directorio para guardar PDF.');
        }
    }
    if (file_put_contents($fullSavePath, $dompdf->output()) === false) {
        throw new Exception('No se pudo guardar el archivo PDF en el servidor.');
    }

    // 6) t_documento
    $sql_doc = "INSERT INTO t_documento (nombreArchivo, pathArchivo, fechaCreacion, tipoDocumento, t_usuario_idCreador) 
                VALUES (:nombre, :path, NOW(), 'MINUTA_APROBADA', :idCreador)";
    $stmt_doc = $pdo->prepare($sql_doc);
    $stmt_doc->execute([':nombre' => $filename, ':path' => $pdfWebPath, ':idCreador' => $idUsuarioLogueado]);
    $idDocumentoCreado = $pdo->lastInsertId();
    if (!$idDocumentoCreado) throw new Exception('No se pudo crear el registro en t_documento.');

    // 7) t_minuta: aprobar + vincular documento
    $sql_update_minuta = "UPDATE t_minuta SET 
                            estadoMinuta = 'APROBADA', 
                            fechaAprobacion = NOW(),
                            pathArchivo = :pathPDF, 
                            t_documento_idFijo = :idDoc 
                          WHERE idMinuta = :idMinuta AND estadoMinuta = 'PENDIENTE'";
    $stmt_update_minuta = $pdo->prepare($sql_update_minuta);
    $exito_update = $stmt_update_minuta->execute([
        ':pathPDF' => $pdfWebPath,
        ':idDoc'   => $idDocumentoCreado,
        ':idMinuta'=> $idMinuta
    ]);
    if (!$exito_update || $stmt_update_minuta->rowCount() == 0) {
        throw new Exception('No se pudo actualizar el estado final de la minuta.');
    }

    // 8) Commit
    $pdo->commit();

    echo json_encode([
        'status'  => 'success',
        'message' => 'Minuta aprobada y PDF generado correctamente.',
        'pdfPath' => $pdfWebPath
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error crítico en aprobar_minuta.php (Minuta ID: $idMinuta): " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Error al procesar la aprobación: ' . $e->getMessage()]);
} finally {
    $pdo = null;
    $db  = null;
}
