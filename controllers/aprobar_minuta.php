<?php
// /corevota/controllers/aprobar_minuta.php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0); // Errores a log, no a la pantalla

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. INCLUIR DEPENDENCIAS Y CONFIGURACIÓN
define('ROOT_PATH', dirname(__DIR__) . '/');
require_once ROOT_PATH . 'class/class.conectorDB.php';

require_once ROOT_PATH . 'models/minutaModel.php';
require_once ROOT_PATH . 'vendor/autoload.php'; // Dompdf

use Dompdf\Dompdf;
use Dompdf\Options;
// (Se eliminan 'use' de chillerlan/QRCode, ya que no se usan)


// 2. OBTENER DATOS DE ENTRADA Y SESIÓN
$input_data = json_decode(file_get_contents('php://input'), true);
$idMinuta = $input_data['idMinuta'] ?? null;
$idUsuarioLogueado = isset($_SESSION['idUsuario']) ? intval($_SESSION['idUsuario']) : null;
$nombreUsuarioLogueado = trim(($_SESSION['pNombre'] ?? '') . ' ' . ($_SESSION['aPaterno'] ?? 'N/A'));

if (!$idMinuta || !$idUsuarioLogueado || !is_numeric($idMinuta)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Datos incompletos, sesión no válida o ID de minuta inválido.']);
    exit;
}

// -----------------------------------------------------------------------------
// FUNCIÓN ImageToDataUrl (Sin cambios)
// -----------------------------------------------------------------------------
function ImageToDataUrl(String $filename): String
{
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
// FUNCIÓN PARA GENERAR HTML (Sin cambios)
// -----------------------------------------------------------------------------
function generateMinutaHtml($data, $logoGoreUri, $logoCoreUri, $firmaImgUri, $selloVerdeUri)
{
    // --- Preparar datos del encabezado ---
    $idMinuta = htmlspecialchars($data['minuta_info']['idMinuta'] ?? 'N/A');
    $fecha = htmlspecialchars(date('d-m-Y', strtotime($data['minuta_info']['fechaMinuta'] ?? 'now')));
    $hora = htmlspecialchars(date('H:i', strtotime($data['minuta_info']['horaMinuta'] ?? 'now')));

    // --- (CORRECCIÓN IMPORTANTE) ---
    // El secretario debe ser el que CREÓ la minuta, no el que aprueba.
    $secretario = htmlspecialchars($data['secretario_info']['nombreCompleto'] ?? 'N/A');
    // --- FIN CORRECCIÓN ---

    $com1 = $data['comisiones_info']['com1'] ?? null;
    $com2 = $data['comisiones_info']['com2'] ?? null;
    $com3 = $data['comisiones_info']['com3'] ?? null;

    $comision1_nombre = htmlspecialchars($com1['nombre'] ?? 'N/A');
    $presidente1_nombre = htmlspecialchars($com1['presidente'] ?? 'N/A');
    $comision2_nombre = isset($com2['nombre']) ? htmlspecialchars($com2['nombre']) : null;
    $presidente2_nombre = isset($com2['presidente']) ? htmlspecialchars($com2['presidente']) : null;
    $comision3_nombre = isset($com3['nombre']) ? htmlspecialchars($com3['nombre']) : null;
    $presidente3_nombre = isset($com3['presidente']) ? htmlspecialchars($com3['presidente']) : null;

    $esMixta = ($comision2_nombre || $comision3_nombre);

    $tituloComisionesHeader = $comision1_nombre;
    if ($comision2_nombre) $tituloComisionesHeader .= " / " . $comision2_nombre;
    if ($comision3_nombre) $tituloComisionesHeader .= " / " . $comision3_nombre;


    // --- HTML (Estilos actualizados) ---
    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Minuta Aprobada ' . $idMinuta . '</title><style>' .
        'body{font-family:Helvetica,sans-serif;font-size:10pt;line-height:1.4;}' .
        '.header-table{width:100%; border-bottom:1px solid #ccc; padding-bottom:10px; margin-bottom:20px; border-collapse: collapse;}' .
        '.header-table .logo-left-cell{width:110px; text-align:left; vertical-align:top;}' .
        '.header-table .logo-left-cell img{height: 80px; width: auto;}' .
        '.header-table .header-center-cell{text-align:center; vertical-align:top;}' .
        '.header-table .header-center-cell p{margin:0;padding:0;font-size:9pt;font-weight:bold;}' .
        '.header-table .header-center-cell .consejo{font-size:10pt;}' .
        '.header-table .logo-right-cell{width:110px; text-align:right; vertical-align:top;}' .
        '.header-table .logo-right-cell img{width:100px; height:auto;}' .
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
        '.signature-box-container{margin-top:40px;text-align:center;page-break-inside:avoid; clear:both;}' .
        '.firma-chip{font-size:9pt;color:#222; text-align:center; width:90%; margin: 10px auto; border:1px dashed #aaa;padding:8px;border-radius:6px;' .
        'position: relative; min-height: 100px; overflow: hidden; display: block; page-break-inside:avoid;} ' .
        '.sello-st-chip{border-style: solid; border-color: #008a00; background-color: #f0fff0;}' .
        '.votacion-block{page-break-inside:avoid; margin-bottom:15px; font-size:9pt;}' .
        '.votacion-tabla{width:100%;border-collapse:collapse;margin-top:5px;}' .
        '.votacion-tabla th, .votacion-tabla td{border:1px solid #ccc;padding:4px 6px;}' .
        '.votacion-tabla th{background-color:#f2f2f2;text-align:center;}' .
        /* MODIFICADO: Ajustado para que funcione dentro de la nueva tabla */
        '.votacion-detalle{padding-left:5px; margin-top:0px; font-size: 8pt; line-height: 1.2;}' .
        '.votacion-detalle b { font-size: 9pt; }' .
        '.comision-header { 
      background-color: #f0f0f0; 
      font-weight: bold; 
      padding: 6px 8px; 
      font-size: 10pt; 
      border-bottom: 1px solid #ccc;
      border-top: 1px solid #ccc;
      color: #333;
    }' .
        '.votacion-nombre-indentada { 
      padding-left: 20px !important; /* Añadimos indentación */
    }' .

        '</style></head><body>';

    // (Contenido HTML del PDF - Encabezado y Asistentes sin cambios)
    $html .= '<table class="header-table"><tr>' .
        '<td class="logo-left-cell">' . ($logoGoreUri ? '<img src="' . htmlspecialchars($logoGoreUri) . '" alt="Logo GORE">' : '') . '</td>' .
        '<td class="header-center-cell">' .
        '<p>GOBIERNO REGIONAL. REGIÓN DE VALPARAÍSO</p>' .
        '<p class="consejo">CONSEJO REGIONAL</p>' .
        '<p>COMISIÓN(ES): ' . strtoupper($tituloComisionesHeader) . '</p>' .
        '</td>' .
        '<td class="logo-right-cell">' . ($logoCoreUri ? '<img src="' . htmlspecialchars($logoCoreUri) . '" alt="Logo CORE">' : '') . '</td>' .
        '</tr></table>' .

        '<div class="titulo-minuta">MINUTA REUNIÓN</div>' .
        '<table class="info-tabla">' .
        '<tr><td class="label">N° Minuta:</td><td>' . $idMinuta . '</td><td class="label">Secretario Técnico:</td><td>' . $secretario . '</td></tr>' .
        '<tr><td class="label">Fecha:</td><td>' . $fecha . '</td><td class="label">Hora:</td><td>' . $hora . '</td></tr>';

    if (!$esMixta) {
        $html .= '<tr><td class="label">Comisión:</td><td>' . $comision1_nombre . '</td><td class="label">Presidente:</td><td>' . $presidente1_nombre . '</td></tr>';
    } else {
        $html .= '<tr><td class="label">1° Comisión:</td><td>' . $comision1_nombre . '</td><td class="label">1° Presidente:</td><td>' . $presidente1_nombre . '</td></tr>';
        if ($comision2_nombre) {
            $html .= '<tr><td class="label">2° Comisión:</td><td>' . $comision2_nombre . '</td><td class="label">2° Presidente:</td><td>' . $presidente2_nombre . '</td></tr>';
        }
        if ($comision3_nombre) {
            $html .= '<tr><td class="label">3° Comisión:</td><td>' . $comision3_nombre . '</td><td class="label">3° Presidente:</td><td>' . $presidente3_nombre . '</td></tr>';
        }
    }
    $html .= '</table>';

    // Asistentes (Sin cambios)
    $html .= '<div class="seccion-titulo">Asistentes:</div><div class="asistentes-lista"><ul>';
    if (!empty($data['asistentes']) && is_array($data['asistentes'])) {
        foreach ($data['asistentes'] as $asistente) {
            $html .= '<li>' . htmlspecialchars($asistente['nombreCompleto']) . '</li>';
        }
    } else {
        $html .= '<li>No se registraron asistentes.</li>';
    }
    $html .= '</ul></div>';

    // Tabla de la sesión (temas título) (Sin cambios)
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
    if (!$temasExisten) {
        $html .= '<li>No se definieron temas específicos.</li>';
    }
    $html .= '</ol></div>';

    // Desarrollo / acuerdos / compromisos (Sin cambios)
    $html .= '<div class="seccion-titulo">Desarrollo / Acuerdos / Compromisos:</div>';
    $temasExisten = false;
    if (!empty($data['temas']) && is_array($data['temas'])) {
        foreach ($data['temas'] as $index => $tema) {
            $nombreTemaLimpio = trim(strip_tags($tema['nombreTema'] ?? ''));
            if ($nombreTemaLimpio === '') continue;
            $temasExisten = true;
            $html .= '<div class="desarrollo-tema"><h4>TEMA ' . ($index + 1) . ': ' . $nombreTemaLimpio . '</h4>';
            if (!empty(trim($tema['objetivo'] ?? ''))) $html .= '<div><strong>Objetivo:</strong> ' . $tema['objetivo'] . '</div>';
            if (!empty(trim($tema['descAcuerdo'] ?? ''))) $html .= '<div><strong>Acuerdo:</strong> ' . $tema['descAcuerdo'] . '</div>';
            if (!empty(trim($tema['compromiso'] ?? ''))) $html .= '<div><strong>Compromiso:</strong> ' . $tema['compromiso'] . '</div>';
            if (!empty(trim($tema['observacion'] ?? ''))) $html .= '<div><strong>Observaciones:</strong> ' . $tema['observacion'] . '</div>';
            $html .= '</div>';
        }
    }
    if (!$temasExisten) {
        $html .= '<p style="font-size:10pt;">No hay detalles registrados para los temas.</p>';
    }

    // --- BLOQUE DE VOTACIONES (Sin cambios, ya estaba correcto) ---
    // --- BLOQUE DE VOTACIONES (MODIFICADO para agrupar por comisión) ---
    if (!empty($data['votaciones']) && is_array($data['votaciones'])) {
        $html .= '<div class="seccion-titulo">Votaciones Realizadas:</div>';

        $comisionActual = null; // Variable de seguimiento

        // Usamos una tabla principal para todo el bloque de votaciones
        $html .= '<table class="votacion-tabla" style="width: 100%;">'; // Tabla principal
        $html .= '<thead><tr>
          <th>Comisión / Votación</th>
          <th style="width:80px; text-align:center;">Resultado</th>
          <th style="width:250px;">Detalle de Votos</th>
         </tr></thead>';
        $html .= '<tbody>';

        foreach ($data['votaciones'] as $votacion) {

            // --- Lógica de Agrupación ---
            // Si la comisión no está seteada (es NULL), la asignamos a un grupo "General"
            $nombreComision = $votacion['nombreComision'] ?? 'Votaciones Generales';

            if ($nombreComision !== $comisionActual) {
                $html .= '<tr><td class="comision-header" colspan="3">' . htmlspecialchars(strtoupper($nombreComision)) . '</td></tr>';
                $comisionActual = $nombreComision;
            }

            // --- Lógica de conteo de votos (la movemos aquí) ---
            $votosSi = 0;
            $votosNo = 0;
            $votosAbs = 0;
            $listaVotosSI = [];
            $listaVotosNO = [];
            $listaVotosABS = [];

            if (!empty($votacion['votos'])) {
                foreach ($votacion['votos'] as $voto) {
                    $nombreVotanteSafe = htmlspecialchars($voto['nombreVotante']);
                    if ($voto['opcionVoto'] == 'SI') {
                        $votosSi++;
                        $listaVotosSI[] = $nombreVotanteSafe;
                    } elseif ($voto['opcionVoto'] == 'NO') {
                        $votosNo++;
                        $listaVotosNO[] = $nombreVotanteSafe;
                    } else { // ABSTENCION
                        $votosAbs++;
                        $listaVotosABS[] = $nombreVotanteSafe;
                    }
                }
            }

            // --- Determinar Resultado ---
            $totalVotos = $votosSi + $votosNo + $votosAbs;
            $resultadoStr = 'Sin Votos';
            if ($totalVotos > 0) {
                if ($votosSi > $votosNo) $resultadoStr = 'Aprobado';
                elseif ($votosNo > $votosSi) $resultadoStr = 'Rechazado';
                else $resultadoStr = 'Empate';
            }

            // --- Fila de la Votación ---
            $html .= '<tr>';
            // Aplicamos la indentación al nombre de la votación
            $html .= '<td class="votacion-nombre-indentada" style="vertical-align: top;">' . htmlspecialchars($votacion['nombreVotacion']) . '</td>';
            $html .= '<td style="text-align:center; vertical-align: top;">' . $resultadoStr . '</td>';

            // Columna de detalle (con listas separadas)
            $html .= '<td class="votacion-detalle" style="vertical-align: top;">';
            $html .= '<b>SÍ (' . $votosSi . '):</b> ' . (empty($listaVotosSI) ? '<i>-</i>' : implode(', ', $listaVotosSI)) . '<br>';
            $html .= '<b>NO (' . $votosNo . '):</b> ' . (empty($listaVotosNO) ? '<i>-</i>' : implode(', ', $listaVotosNO)) . '<br>';
            $html .= '<b>ABS (' . $votosAbs . '):</b> ' . (empty($listaVotosABS) ? '<i>-</i>' : implode(', ', $listaVotosABS));
            $html .= '</td>';

            $html .= '</tr>';
        }

        $html .= '</tbody></table>';
    }
    // --- FIN BLOQUE DE VOTACIONES ---


    // --- BLOQUE DE FIRMAS MÚLTIPLES (Sin cambios) ---
    $html .= '<div class="signature-box-container">';

    $generarChipFirma = function ($nombre, $cargo, $detalle, $fechaHora, $imagenUri, $claseExtra = '') {
        $chipHtml = '<div class="firma-chip ' . $claseExtra . '">';
        if (!empty($imagenUri)) {
            $chipHtml .= '<img src="' . $imagenUri . '" alt="Firma" ' .
                'style="position: absolute; top: 10px; left: 50%; margin-left: -50px; width: 100px; height: auto; opacity: 0.2; z-index: 1;">';
        } else {
            $chipHtml .= '<span style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: 1; color: #a00; font-size: 8pt; opacity: 0.3;">[SELLO NO ENCONTRADO]</span>';
        }
        $chipHtml .= '<div style="position: relative; z-index: 2; font-size: 9pt; line-height: 1.3; display: inline-block; text-align: left;">' .
            '<strong style="font-size: 10pt;">' . htmlspecialchars($nombre) . '</strong><br/>' .
            htmlspecialchars($cargo) . '<br/>' .
            htmlspecialchars($detalle) . '<br/>' .
            'Consejo Regional<br/>' .
            htmlspecialchars($fechaHora) .
            '</div>';
        $chipHtml .= '</div>';
        return $chipHtml;
    };

    // Renderizar Firmas de Presidentes
    if (!empty($data['firmas_aprobadas']) && is_array($data['firmas_aprobadas'])) {
        foreach ($data['firmas_aprobadas'] as $firma) {
            $html .= $generarChipFirma(
                $firma['nombrePresidente'],
                'Presidente de Comisión',
                $firma['nombreComision'],
                date('d-m-Y H:i:s', strtotime($firma['fechaAprobacion'])),
                $firmaImgUri, // Sello genérico de presidente
                ''
            );
        }
    }

    $html .= '</div>'; // cierre signature-box-container

    // =========================================================================
    // --- INICIO: BLOQUE DE QR Y HASH DE VALIDACIÓN (Sin cambios) ---
    // =========================================================================
    if (!empty($data['qrBase64'])) { // <-- Comprobamos 'qrBase64'
        $html .= '
        <div style="margin-top:40px; margin-right:30px; page-break-inside:avoid; text-align:right;">
            <table style="width: auto; margin-left: auto; margin-right: 0px; border-collapse: collapse;">
                <tr>
                    <td style="width: 90px; padding-right: 10px; vertical-align: top;">
                        <img src="' . $data['qrBase64'] . '" 
                             alt="QR de verificación" 
                             style="width:85px; height:auto; opacity:0.9; border:1px solid #ccc; padding:4px; background:#fff;">
                    </td>
                    <td style="width: auto; vertical-align: top; text-align: left; font-size: 8pt; line-height: 1.3;">
                        <p style="margin:0; padding:0; font-size:8pt; font-weight:bold;">Este documento tiene firma electrónica y su original puede ser validado en:</p>
                        <p style="margin:2px 0 0 0; padding:0; font-size:7pt; color:#333;">' .
            htmlspecialchars($data['urlValidacion'] ?? '') .
            '</p>
                        <p style="margin:5px 0 0 0; padding:0; font-size:8pt;">
                            Código de Verificación: <span style="font-family:monospace; font-weight:bold;">' .
            htmlspecialchars($data['minuta_info']['hashValidacion'] ?? '---') . '</span>
                        </p>
                    </td>
                </tr>
            </table>
        </div>';
    }
    // =========================================================================
    // --- FIN: BLOQUE DE QR Y HASH DE VALIDACIÓN ---
    // =========================================================================

    $html .= '</body></html>';
    return $html;
}


/* =============================================================================
 🔽 COMIENZA EL "MOTOR" PRINCIPAL DEL SCRIPT (MODIFICADO) 🔽
=============================================================================
*/

// ... (justo antes de la línea 285 "try {")

// --- Directorio para QRs temporales ---
// (Esta sección ya no es necesaria si usamos la API, pero no molesta)
$tempQrDir = ROOT_PATH . 'public/docs/temp_qrs/';
if (!file_exists($tempQrDir)) {
    @mkdir($tempQrDir, 0775, true);
}

/* =============================================================================
 🔽 COMIENZA EL "MOTOR" PRINCIPAL DEL SCRIPT (MODIFICADO) 🔽
=============================================================================
*/


try {
    $db = new conectorDB();
    $pdo = $db->getDatabase();
    $pdo->beginTransaction();

    $data_pdf = [];

    // --- 1. CARGAR INFO MINUTA ---
    $sqlMinuta = "SELECT * FROM t_minuta WHERE idMinuta = :id";
    $stmtMinuta = $pdo->prepare($sqlMinuta);
    $stmtMinuta->execute([':id' => $idMinuta]);
    $data_pdf['minuta_info'] = $stmtMinuta->fetch(PDO::FETCH_ASSOC);

    if (!$data_pdf['minuta_info']) {
        throw new Exception('No se encontró la minuta.');
    }

    $totalRequeridos = max(1, (int)($data_pdf['minuta_info']['presidentesRequeridos'] ?? 1));

    // --- 2. REGISTRAR LA FIRMA (LÓGICA CORREGIDA) ---
    $sqlAprobar = "UPDATE t_aprobacion_minuta 
                    SET estado_firma = 'FIRMADO', fechaAprobacion = NOW()
                    WHERE t_minuta_idMinuta = :idMinuta
                    AND t_usuario_idPresidente = :idUsuario
                    AND estado_firma = 'EN_ESPERA'";

    $stmtAprobar = $pdo->prepare($sqlAprobar);
    $stmtAprobar->execute([
        ':idMinuta' => $idMinuta,
        ':idUsuario' => $idUsuarioLogueado
    ]);

    if ($stmtAprobar->rowCount() == 0) {
        throw new Exception('No tiene permisos para firmar esta minuta, ya la ha firmado o la minuta está en revisión.');
    }

    // --- 3. (OPCIONAL) REGISTRAR EN t_firma ---
    $descripcionFirma = substr('Firma electrónica minuta ' . $idMinuta, 0, 45);

    $sqlFirma = "INSERT INTO t_firma (descFirma, idTipoUsuario, fechaGuardado, idUsuario, idComision)
                 VALUES (:desc, 3, NOW(), :idUsuario, 0)";
    $stmtFirma = $pdo->prepare($sqlFirma);
    $stmtFirma->execute([
        ':desc' => $descripcionFirma,
        ':idUsuario' => $idUsuarioLogueado
    ]);


    // --- 4. VERIFICAR SI YA SE COMPLETARON LAS APROBACIONES ---
    $sqlCount = $pdo->prepare("SELECT COUNT(DISTINCT t_usuario_idPresidente)
                                  FROM t_aprobacion_minuta
                                  WHERE t_minuta_idMinuta = :idMinuta
                                  AND estado_firma = 'FIRMADO'");
    $sqlCount->execute([':idMinuta' => $idMinuta]);
    $totalAprobaciones = (int)$sqlCount->fetchColumn();


    if ($totalAprobaciones < $totalRequeridos) {
        // --- AÚN FALTAN FIRMAS (PARCIAL) ---
        $sqlUpdParcial = "UPDATE t_minuta SET estadoMinuta = 'PARCIAL' WHERE idMinuta = :id";
        $pdo->prepare($sqlUpdParcial)->execute([':id' => $idMinuta]);
        $pdo->commit();

        try {
            // Re-instanciamos MinutaModel para que cree su propia conexión (como está en tu archivo)
            $minutaModel = new MinutaModel();
            $faltanLog = $totalRequeridos - $totalAprobaciones;
            $minutaModel->logAccion(
                $idMinuta,
                $idUsuarioLogueado,
                'FIRMADA_PARCIAL',
                "Presidente ($nombreUsuarioLogueado) ha firmado. Faltan $faltanLog firma(s)."
            );
        } catch (Exception $logException) {
            error_log("ADVERTENCIA idMinuta {$idMinuta}: No se pudo registrar el log de 'FIRMADA_PARCIAL': " . $logException->getMessage());
        }



        $faltan = $totalRequeridos - $totalAprobaciones;
        $mensaje = "Firma registrada. Falta(n) {$faltan} firma(s) más.";
        echo json_encode([
            'status' => 'success_partial',
            'message' => $mensaje,
            'aprobadas' => $totalAprobaciones,
            'requeridas' => $totalRequeridos
        ]);
        exit;
    }

    // =========================================================================
    // SI LLEGAMOS AQUÍ, ES LA ÚLTIMA FIRMA. PROCEDEMOS A GENERAR EL PDF FINAL.
    // =========================================================================

    // --- 5. CARGAR DATOS PARA EL PDF (Asistentes, Temas, Votos, Comisiones) ---

    // 5a. CARGAR INFO SECRETARIO (¡CORREGIDO!)
    // Buscamos al secretario que CREÓ la minuta, no al que está logueado
    $sqlSec = $pdo->prepare("SELECT CONCAT(u.pNombre, ' ', u.aPaterno) as nombreCompleto 
                                 FROM t_usuario u 
                                 JOIN t_minuta m ON u.idUsuario = m.t_usuario_idSecretario
                                 WHERE m.idMinuta = :idMinuta AND u.tipoUsuario_id IN (2, 6)"); // 2=ST, 6=Admin
    $sqlSec->execute([':idMinuta' => $idMinuta]);
    $data_pdf['secretario_info'] = $sqlSec->fetch(PDO::FETCH_ASSOC);
    if (!$data_pdf['secretario_info']) {
        $data_pdf['secretario_info'] = ['nombreCompleto' => 'Secretario Técnico (Asignado)']; // Fallback
    }

    // =========================================================================
    // --- INICIO DE LA MODIFICACIÓN 1 ---
    // =========================================================================
    // 5a-bis. OBTENER IDREUNION (para buscar votaciones asociadas a la reunión)
    $sqlReunion = $pdo->prepare("SELECT idReunion FROM t_reunion WHERE t_minuta_idMinuta = :idMinuta LIMIT 1");
    $sqlReunion->execute([':idMinuta' => $idMinuta]);
    $reunion = $sqlReunion->fetch(PDO::FETCH_ASSOC);
    $idReunion = $reunion ? $reunion['idReunion'] : null;
    // =========================================================================
    // --- FIN DE LA MODIFICACIÓN 1 ---
    // =========================================================================


    // 5b. CARGAR COMISIONES Y PRESIDENTES (Lógica CORREGIDA)
    $getDatosComision = function ($idComision) use ($pdo) {
        if (empty($idComision)) return null;
        $sqlCom = $pdo->prepare("SELECT nombreComision, t_usuario_idPresidente FROM t_comision WHERE idComision = :id");
        $sqlCom->execute([':id' => $idComision]);
        $comData = $sqlCom->fetch(PDO::FETCH_ASSOC);
        if (!$comData) return ['nombre' => 'Comisión no encontrada', 'presidente' => 'N/A', 'idPresidente' => null];
        $idPresidente = $comData['t_usuario_idPresidente'];
        $nombrePresidente = 'Presidente no asignado';
        if (!empty($idPresidente)) {
            $sqlPres = $pdo->prepare("SELECT CONCAT(pNombre, ' ', aPaterno) as nombreCompleto FROM t_usuario WHERE idUsuario = :id");
            $sqlPres->execute([':id' => $idPresidente]);
            $nombrePresidente = $sqlPres->fetchColumn() ?: $nombrePresidente;
        }
        return ['nombre' => $comData['nombreComision'], 'presidente' => $nombrePresidente, 'idPresidente' => $idPresidente];
    };

    $idCom1 = $data_pdf['minuta_info']['t_comision_idComision'];
    $data_pdf['comisiones_info']['com1'] = $getDatosComision($idCom1);

    $sqlMixta = $pdo->prepare("SELECT t_comision_idComision_mixta, t_comision_idComision_mixta2 FROM t_reunion WHERE t_minuta_idMinuta = :idMinuta");
    $sqlMixta->execute([':idMinuta' => $idMinuta]);
    $comisionesMixtas = $sqlMixta->fetch(PDO::FETCH_ASSOC);

    if ($comisionesMixtas) {
        if (!empty($comisionesMixtas['t_comision_idComision_mixta'])) {
            $data_pdf['comisiones_info']['com2'] = $getDatosComision($comisionesMixtas['t_comision_idComision_mixta']);
        }
        if (!empty($comisionesMixtas['t_comision_idComision_mixta2'])) {
            $data_pdf['comisiones_info']['com3'] = $getDatosComision($comisionesMixtas['t_comision_idComision_mixta2']);
        }
    }

    // (Mapa de ID de Presidente -> Nombre de Comisión)
    $mapaPresidentes = [];
    if (isset($data_pdf['comisiones_info']['com1'])) $mapaPresidentes[$data_pdf['comisiones_info']['com1']['idPresidente']] = $data_pdf['comisiones_info']['com1']['nombre'];
    if (isset($data_pdf['comisiones_info']['com2'])) $mapaPresidentes[$data_pdf['comisiones_info']['com2']['idPresidente']] = $data_pdf['comisiones_info']['com2']['nombre'];
    if (isset($data_pdf['comisiones_info']['com3'])) $mapaPresidentes[$data_pdf['comisiones_info']['com3']['idPresidente']] = $data_pdf['comisiones_info']['com3']['nombre'];


    // 5c. ASISTENTES
    $sqlAsis = "SELECT CONCAT(u.pNombre, ' ', u.aPaterno) as nombreCompleto 
                 FROM t_asistencia a
                 JOIN t_usuario u ON a.t_usuario_idUsuario = u.idUsuario
                 WHERE a.t_minuta_idMinuta = :id
                 ORDER BY u.aPaterno, u.pNombre";
    $stmtAsis = $pdo->prepare($sqlAsis);
    $stmtAsis->execute([':id' => $idMinuta]);
    $data_pdf['asistentes'] = $stmtAsis->fetchAll(PDO::FETCH_ASSOC);

    // 5d. TEMAS Y ACUERDOS
    $sqlTemas = "SELECT t.idTema, t.nombreTema, t.objetivo, t.compromiso, t.observacion, a.descAcuerdo
                  FROM t_tema t 
                  LEFT JOIN t_acuerdo a ON a.t_tema_idTema = t.idTema
                  WHERE t.t_minuta_idMinuta = :id
                  ORDER BY t.idTema ASC";
    $stmtTemas = $pdo->prepare($sqlTemas);
    $stmtTemas->execute([':id' => $idMinuta]);
    $data_pdf['temas'] = $stmtTemas->fetchAll(PDO::FETCH_ASSOC);


    // =========================================================================
    // --- INICIO DE LA MODIFICACIÓN 2 ---
    // =========================================================================
    // --- 5e. VOTACIONES (¡¡CORREGIDO!!) ---
    // Buscamos votaciones ligadas a la minuta DIRECTAMENTE o a través de la REUNIÓN
    $sqlVotaciones = $pdo->prepare("SELECT * FROM t_votacion 
                                   WHERE t_minuta_idMinuta = :idMinuta 
                                   OR t_reunion_idReunion = :idReunion");
    $sqlVotaciones->execute([
        ':idMinuta' => $idMinuta,
        ':idReunion' => $idReunion // Usamos el $idReunion que obtuvimos en 5a-bis
    ]);
    $data_pdf['votaciones'] = $sqlVotaciones->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($data_pdf['votaciones'])) {
        // Esta consulta anidada para buscar los votos de CADA votación está CORRECTA
        $sqlVotos = $pdo->prepare("
             SELECT v.t_votacion_idVotacion, v.opcionVoto, CONCAT(u.pNombre, ' ', u.aPaterno) as nombreVotante
             FROM t_voto v
             JOIN t_usuario u ON v.t_usuario_idUsuario = u.idUsuario
             WHERE v.t_votacion_idVotacion = :idVotacion
        ");
        foreach ($data_pdf['votaciones'] as $i => $votacion) {
            $sqlVotos->execute([':idVotacion' => $votacion['idVotacion']]);
            $data_pdf['votaciones'][$i]['votos'] = $sqlVotos->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    // --- FIN CORRECCIÓN VOTACIONES ---
    // =========================================================================
    // --- FIN DE LA MODIFICACIÓN 2 ---
    // =========================================================================


    // --- 6. CARGAR FIRMAS Y SELLOS PARA EL PDF (Sin cambios) ---
    $sqlFirmasRevisado = $pdo->prepare("SELECT 
                                             a.t_usuario_idPresidente,
                                             CONCAT(u.pNombre, ' ', u.aPaterno) as nombrePresidente, 
                                             a.fechaAprobacion 
                                           FROM t_aprobacion_minuta a
                                           JOIN t_usuario u ON a.t_usuario_idPresidente = u.idUsuario
                                           WHERE a.t_minuta_idMinuta = :idMinuta
                                           AND a.estado_firma = 'FIRMADO'
                                           GROUP BY u.idUsuario
                                           ORDER BY a.fechaAprobacion ASC");
    $sqlFirmasRevisado->execute([':idMinuta' => $idMinuta]);
    $firmas_temp = $sqlFirmasRevisado->fetchAll(PDO::FETCH_ASSOC);

    $firmasCorregidas = [];
    foreach ($firmas_temp as $firma) {
        $idPresi = $firma['t_usuario_idPresidente'];
        $firmasCorregidas[] = [
            'nombrePresidente' => $firma['nombrePresidente'],
            'fechaAprobacion' => $firma['fechaAprobacion'],
            'nombreComision' => $mapaPresidentes[$idPresi] ?? 'Comisión No Identificada'
        ];
    }
    $data_pdf['firmas_aprobadas'] = $firmasCorregidas;


    // --- 7. DEFINIR LOGOS Y SELLO DE FIRMA ---
    // (Asegúrate que estas imágenes existan en tu servidor)
    $logoGoreUri = ImageToDataUrl(ROOT_PATH . 'public/img/logo2.png');
    $logoCoreUri = ImageToDataUrl(ROOT_PATH . 'public/img/logoCore1.png');
    $firmaImgUri = ImageToDataUrl(ROOT_PATH . 'public/img/firmadigital.png');
    $selloVerdeUri = ImageToDataUrl(ROOT_PATH . 'public/img/aprobacion.png'); // (Nombre corregido)

    // -- GENERACION DEL QR EN EL PDF -- 
    $hashValidacion = hash('sha256', $idMinuta . '-' . time() . '-' . rand());

    // URL de verificación (DINÁMICA)
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost'; // Usa el host del servidor
    $urlValidacion = $protocol . '://' . $host . "/corevota/public/validar.php?hash={$hashValidacion}";


    // --- INICIO: SOLUCIÓN (Usar API Externa, evita problemas de GD/SVG) ---

    // 1. Definir la URL de la API externa
    $qrApiUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=90x90&data=' . urlencode($urlValidacion);

    // --- INICIO DE LA CORRECCIÓN (file_get_contents para URL) ---
    // No usamos ImageToDataUrl para URLs, ya que esa función usa file_exists()
    // que no funciona con enlaces web. Usamos file_get_contents directamente.

    $qrBase64 = ''; // Inicializar

    // Usamos @ para suprimir warnings si 'allow_url_fopen' está desactivado o falla la conexión
    $rawQrData = @file_get_contents($qrApiUrl);

    if ($rawQrData === false || empty($rawQrData)) {
        // Log por si la API falla o allow_url_fopen está apagado
        error_log("Error al generar QR: No se pudo obtener la imagen desde la API en " . $qrApiUrl);
        $qrBase64 = ''; // Dejar vacío para que no rompa el PDF
    } else {
        // Si tuvimos éxito, convertimos a Base64. Asumimos que es PNG (la API devuelve PNG).
        $qrBase64 = "data:image/png;base64," . base64_encode($rawQrData);
    }
    // --- FIN DE LA CORRECCIÓN ---
    // --- FIN: SOLUCIÓN API ---


    // Pasar datos al arreglo que usa generateMinutaHtml()
    $data_pdf['qrBase64'] = $qrBase64;
    $data_pdf['urlValidacion'] = $urlValidacion;
    $data_pdf['minuta_info']['hashValidacion'] = $hashValidacion;


    // --- 8. GENERAR HTML ---
    $html = generateMinutaHtml($data_pdf, $logoGoreUri, $logoCoreUri, $firmaImgUri, $selloVerdeUri);

    // --- 9. INICIALIZAR DOMPDF Y RENDERIZAR ---
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true); // Necesario para que Dompdf cargue el QR (data:image)
    $options->set('chroot', ROOT_PATH); // Seguridad, aunque las imágenes locales se cargan con ImageToDataUrl
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('letter', 'portrait');
    $dompdf->render();

    // --- 10. GUARDAR PDF EN SERVIDOR ---
    $pathDirectorio = ROOT_PATH . 'public/docs/minutas_aprobadas/';
    if (!file_exists($pathDirectorio)) {
        mkdir($pathDirectorio, 0775, true);
    }
    $nombreArchivo = "Minuta_Aprobada_N" . $idMinuta . "_" . date('Ymd_His') . ".pdf";
    $pathCompleto = $pathDirectorio . $nombreArchivo;
    $pathParaBD = 'public/docs/minutas_aprobadas/' . $nombreArchivo;

    file_put_contents($pathCompleto, $dompdf->output());

    // --- 11. ACTUALIZAR MINUTA CON PATH FINAL Y HASH ---
    $sqlUpd =  "UPDATE t_minuta SET 
                     estadoMinuta = 'APROBADA',
                     pathArchivo = :pathArchivo,
                     fechaAprobacion = NOW(),
                     hashValidacion = :hash
                 WHERE   idMinuta = :id";
    $stmtUpd = $pdo->prepare($sqlUpd);
    $stmtUpd->execute([
        ':pathArchivo' => $pathParaBD,
        ':hash' => $hashValidacion,
        ':id' => $idMinuta
    ]);

    // --- 12. COMMIT Y RESPUESTA EXITOSA FINAL ---
    $pdo->commit(); // Hacemos commit DE LA TRANSACCIÓN DE LA FIRMA Y PDF

    // --- 13. REGISTRO DE LOGS (Fuera de la transacción) ---
    try {
        $minutaModel = new MinutaModel(); // Crea su propia conexión
        // Log 1: Aprobación final
        $minutaModel->logAccion(
            $idMinuta,
            $idUsuarioLogueado,
            'APROBADA_FINAL',
            "Presidente ($nombreUsuarioLogueado) ha dado la firma final. Minuta Aprobada."
        );
        // Log 2: PDF Generado (acción del sistema, por eso 'null' en usuario)
        $minutaModel->logAccion(
            $idMinuta,
            null,
            'PDF_GENERADO',
            "PDF final generado y guardado en: " . $pathParaBD
        );
    } catch (Exception $logException) {
        error_log("ADVERTENCIA idMinuta {$idMinuta}: No se pudo registrar el log de 'APROBADA_FINAL': " . $logException->getMessage());
    }


    echo json_encode(['status' => 'success_final', 'message' => 'Minuta aprobada y generada con todas las firmas.', 'pdf_path' => $pathParaBD]);
} catch (Exception $e) {
    // --- 14. ROLLBACK Y RESPUESTA DE ERROR ---
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error fatal al aprobar minuta: " . $e->getMessage() . " \nEn archivo: " . $e->getFile() . " \nEn línea: " . $e->getLine());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error al procesar la aprobación: ' . $e->getMessage()]);
}
