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
// -----------------------------------------------------------------------------
// FUNCIÓN PARA GENERAR HTML (CORREGIDA)
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

  $comision1_nombre  = htmlspecialchars($com1['nombre']   ?? 'N/A');
  $presidente1_nombre = htmlspecialchars($com1['presidente'] ?? 'N/A');
  $comision2_nombre  = isset($com2['nombre'])  ? htmlspecialchars($com2['nombre'])  : null;
  $presidente2_nombre = isset($com2['presidente'])? htmlspecialchars($com2['presidente']): null;
  $comision3_nombre  = isset($com3['nombre'])  ? htmlspecialchars($com3['nombre'])  : null;
  $presidente3_nombre = isset($com3['presidente'])? htmlspecialchars($com3['presidente']): null;

  $esMixta = ($comision2_nombre || $comision3_nombre);

  // Título de comisiones en header
  $tituloComisionesHeader = $comision1_nombre;
  if ($comision2_nombre) $tituloComisionesHeader .= " / " . $comision2_nombre;
  if ($comision3_nombre) $tituloComisionesHeader .= " / " . $comision3_nombre;

  // Datos de firma (enviados desde PHP)
  $firmaNombre  = htmlspecialchars($data['firma']['nombre'] ?? 'N/A');
  $firmaFecha  = htmlspecialchars($data['firma']['fechaHora'] ?? '');
  $firmaRut   = htmlspecialchars($data['firma']['rut'] ?? '');
  $firmaCorreo  = htmlspecialchars($data['firma']['correo'] ?? '');
  $firmaCargo  = htmlspecialchars($data['firma']['cargo'] ?? '');
  $firmaUnidad  = htmlspecialchars($data['firma']['unidad'] ?? '');

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
    /* ... (después de .firma-chip) ... */
    '.votacion-block{page-break-inside:avoid; margin-bottom:15px; font-size:9pt;}' .
    '.votacion-tabla{width:100%;border-collapse:collapse;margin-top:5px;}' .
    '.votacion-tabla th, .votacion-tabla td{border:1px solid #ccc;padding:4px 6px;}' .
    '.votacion-tabla th{background-color:#f2f2f2;text-align:center;}' .
    '.votacion-detalle{columns:2;-webkit-columns:2;column-gap:20px;padding-left:20px;margin-top:5px;}' .
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
      if (!empty(trim($tema['objetivo'] ?? '')))   { $html .= '<div><strong>Objetivo:</strong> '   . $tema['objetivo']   . '</div>'; }
      if (!empty(trim($tema['descAcuerdo'] ?? ''))) { $html .= '<div><strong>Acuerdo:</strong> '   . $tema['descAcuerdo'] . '</div>'; }
      if (!empty(trim($tema['compromiso'] ?? '')))  { $html .= '<div><strong>Compromiso:</strong> '  . $tema['compromiso']  . '</div>'; }
      if (!empty(trim($tema['observacion'] ?? ''))) { $html .= '<div><strong>Observaciones:</strong> ' . $tema['observacion'] . '</div>'; }
      $html .= '</div>';
    }
  }
    // 🟨 CORRECCIÓN: Se eliminó una llave '}' extra que estaba aquí.
  if (!$temasExisten) {
    $html .= '<p style="font-size:10pt;">No hay detalles registrados para los temas.</p>';
  }

  // --- 🔽 BLOQUE DE VOTACIONES (YA LO TENÍAS) 🔽 ---
  if (!empty($data['votaciones']) && is_array($data['votaciones'])) {
    $html .= '<div class="seccion-titulo">Resultados de Votaciones:</div>';
    foreach ($data['votaciones'] as $votacion) {
      $totalSi = (int)($votacion['resumen']['SI'] ?? 0);
      $totalNo = (int)($votacion['resumen']['NO'] ?? 0);
      $totalAbs = (int)($votacion['resumen']['ABSTENCION'] ?? 0);
      $totalVotos = $totalSi + $totalNo + $totalAbs;

      $html .= '<div class="votacion-block">';
      $html .= '<h4>Votación: ' . htmlspecialchars($votacion['nombre']) . '</h4>';
      
      // Tabla de Resumen
      $html .= '<table class="votacion-tabla">';
      $html .= '<thead><tr><th>Apruebo (SÍ)</th><th>Rechazo (NO)</th><th>Abstención</th><th>Total Votos</th></tr></thead>';
      $html .= '<tbody><tr>';
      $html .= '<td style="text-align:center;">' . $totalSi . '</td>';
      $html .= '<td style="text-align:center;">' . $totalNo . '</td>';
      $html .= '<td style="text-align:center;">' . $totalAbs . '</td>';
      $html .= '<td style="text-align:center;">' . $totalVotos . '</td>';
      $html .= '</tr></tbody></table>';

      // Detalle (Opcional, si quieres mostrar quién votó qué)
      if (!empty($votacion['detalle'])) {
        $html .= '<p style="margin:8px 0 3px 0;font-weight:bold;">Detalle de votos:</p>';
        $html .= '<ul class="asistentes-lista votacion-detalle">'; // Reutiliza estilo de asistentes
        foreach ($votacion['detalle'] as $detalleVoto) {
          $html .= '<li>' . htmlspecialchars($detalleVoto['nombreCompleto']) . ' (<strong>' . htmlspecialchars($detalleVoto['opcionVoto']) . '</strong>)</li>';
               }
        $html .= '</ul>';
      }
      $html .= '</div>';
    }
  }
  // --- 🔼 FIN BLOQUE AÑADIDO 🔼 ---

    
      // Firma en texto (sin imágenes)
  $html .= '<div class="signature-box">
        <div class="signature-line"></div>
        <p>' . $presidente1_nombre . '</p>
        <p>Presidente</p>
        <p>Comisión ' . $comision1_nombre . '</p>
        <div class="firma-chip">
          <strong>Firmado electrónicamente por:</strong> ' . $firmaNombre . '<br/>' .
          ($firmaCargo ? ('<strong>Cargo:</strong> ' . $firmaCargo . '<br/>') : '') .
          ($firmaUnidad ? ('<strong>Unidad:</strong> ' . $firmaUnidad . '<br/>') : '') .
          ($firmaRut  ? ('<strong>RUT:</strong> '  . $firmaRut  . '<br/>') : '') .
          ($firmaCorreo ? ('<strong>Correo:</strong> ' . $firmaCorreo . '<br/>') : '') .
          '<strong>Fecha y hora de firma:</strong> ' . $firmaFecha . '
        </div>
       </div>';

  $html .= '</body></html>';
  return $html;
}