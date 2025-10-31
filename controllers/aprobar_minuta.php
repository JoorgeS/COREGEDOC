<?php
// /corevota/controllers/aprobar_minuta.php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. INCLUIR DEPENDENCIAS Y CONFIGURACIÓN
define('ROOT_PATH', dirname(__DIR__) . '/');
require_once ROOT_PATH . 'class/class.conectorDB.php';
require_once ROOT_PATH . 'models/FirmaModel.php';
require_once ROOT_PATH . 'vendor/autoload.php'; // Dompdf

use Dompdf\Dompdf;
use Dompdf\Options;

// 2. OBTENER DATOS DE ENTRADA Y SESIÓN
$input_data = json_decode(file_get_contents('php://input'), true);
$idMinuta = $input_data['idMinuta'] ?? null;
// ⭐ CORRECCIÓN: Forzar el ID de usuario a entero
$idUsuarioLogueado = isset($_SESSION['idUsuario']) ? intval($_SESSION['idUsuario']) : null;
$nombreUsuarioLogueado = trim(($_SESSION['pNombre'] ?? '') . ' ' . ($_SESSION['aPaterno'] ?? 'N/A'));

if (!$idMinuta || !$idUsuarioLogueado || !is_numeric($idMinuta)) {
    echo json_encode(['status' => 'error', 'message' => 'Datos incompletos, sesión no válida o ID de minuta inválido.']);
    exit;
}

// -----------------------------------------------------------------------------
// FUNCIÓN ImageToDataUrl (Se mantiene sin cambios)
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
// FUNCIÓN PARA GENERAR HTML (AJUSTADA PARA MÚLTIPLES FIRMAS)
// -----------------------------------------------------------------------------
function generateMinutaHtml($data, $logoGoreUri, $logoCoreUri, $firmaImgUri)
{
    // --- Preparar datos del encabezado ---
    // (Esta sección se mantiene igual que tu original)
    $idMinuta = htmlspecialchars($data['minuta_info']['idMinuta'] ?? 'N/A');
    $fecha = htmlspecialchars(date('d-m-Y', strtotime($data['minuta_info']['fechaMinuta'] ?? 'now')));
    $hora = htmlspecialchars(date('H:i', strtotime($data['minuta_info']['horaMinuta'] ?? 'now')));
    $secretario = htmlspecialchars($data['secretario_info']['nombreCompleto'] ?? 'N/A');

    $com1 = $data['comisiones_info']['com1'] ?? null;
    $com2 = $data['comisiones_info']['com2'] ?? null;
    $com3 = $data['comisiones_info']['com3'] ?? null;

    $comision1_nombre  = htmlspecialchars($com1['nombre']   ?? 'N/A');
    $presidente1_nombre = htmlspecialchars($com1['presidente'] ?? 'N/A');
    $comision2_nombre  = isset($com2['nombre'])   ? htmlspecialchars($com2['nombre'])   : null;
    $presidente2_nombre = isset($com2['presidente']) ? htmlspecialchars($com2['presidente']) : null;
    $comision3_nombre  = isset($com3['nombre'])   ? htmlspecialchars($com3['nombre'])   : null;
    $presidente3_nombre = isset($com3['presidente']) ? htmlspecialchars($com3['presidente']) : null;

    $esMixta = ($comision2_nombre || $comision3_nombre);

    $tituloComisionesHeader = $comision1_nombre;
    if ($comision2_nombre) $tituloComisionesHeader .= " / " . $comision2_nombre;
    if ($comision3_nombre) $tituloComisionesHeader .= " / " . $comision3_nombre;


    // --- HTML ---
    // (El CSS se mantiene igual que tu original, lo omito por brevedad)
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
        '.signature-box-container{margin-top:40px;text-align:center;page-break-inside:avoid; clear:both;}' . // <-- Contenedor
        '.firma-chip{font-size:9pt;color:#222; text-align:center; width:45%; margin: 10px 2.5%; border:1px dashed #aaa;padding:8px;border-radius:6px;' .
        'position: relative; min-height: 100px; overflow: hidden; display: inline-block; float:left; page-break-inside:avoid;} ' . // <-- Ajustado
        '.votacion-block{page-break-inside:avoid; margin-bottom:15px; font-size:9pt;}' .
        '.votacion-tabla{width:100%;border-collapse:collapse;margin-top:5px;}' .
        '.votacion-tabla th, .votacion-tabla td{border:1px solid #ccc;padding:4px 6px;}' .
        '.votacion-tabla th{background-color:#f2f2f2;text-align:center;}' .
        '.votacion-detalle{columns:2;-webkit-columns:2;column-gap:20px;padding-left:20px;margin-top:5px;}' .
        '</style></head><body>';

    // -----------------------------------------------------------------
    // 🔽 CÓDIGO HTML DEL CONTENIDO (Omitido por brevedad, es igual) 🔽
    // -----------------------------------------------------------------
    // (Tu código de header-table, info-tabla, asistentes, tabla de sesión, desarrollo, votaciones va aquí... sin cambios)
    // ... (código HTML idéntico al tuyo desde línea 133 hasta 291) ...
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
    if (!$temasExisten) {
        $html .= '<li>No se definieron temas específicos.</li>';
    }
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
            if (!empty(trim($tema['objetivo'] ?? ''))) {
                $html .= '<div><strong>Objetivo:</strong> '   . $tema['objetivo']   . '</div>';
            }
            if (!empty(trim($tema['descAcuerdo'] ?? ''))) {
                $html .= '<div><strong>Acuerdo:</strong> '   . $tema['descAcuerdo'] . '</div>';
            }
            if (!empty(trim($tema['compromiso'] ?? ''))) {
                $html .= '<div><strong>Compromiso:</strong> '  . $tema['compromiso']  . '</div>';
            }
            if (!empty(trim($tema['observacion'] ?? ''))) {
                $html .= '<div><strong>Observaciones:</strong> ' . $tema['observacion'] . '</div>';
            }
            $html .= '</div>';
        }
    }
    if (!$temasExisten) {
        $html .= '<p style="font-size:10pt;">No hay detalles registrados para los temas.</p>';
    }

    // --- BLOQUE DE VOTACIONES ---
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

            // Detalle (Opcional)
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
    // -----------------------------------------------------------------
    // 🔼 FIN DEL CÓDIGO HTML DEL CONTENIDO 🔼
    // -----------------------------------------------------------------


    // -----------------------------------------------------------------------------
    // ⭐ NUEVO BLOQUE DE FIRMAS MÚLTIPLES ⭐
    // -----------------------------------------------------------------------------
    $html .= '<div class="signature-box-container">';

    // --- Función interna para no repetir el "chip" de firma ---
    $generarChipFirma = function ($nombre, $comision, $fechaHora, $firmaImgUri) {
        $chipHtml = '<div class="firma-chip">';

        // Sello de agua
        if (!empty($firmaImgUri)) {
            $chipHtml .= '<img src="' . $firmaImgUri . '" alt="Firma" ' .
                'style="position: absolute; top: 10px; left: 50%; margin-left: -50px; width: 100px; height: auto; opacity: 0.2; z-index: 1;">';
        } else {
            $chipHtml .= '<span style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: 1; color: #a00; font-size: 8pt; opacity: 0.3;">[SELLO NO ENCONTRADO]</span>';
        }

        // Texto de la firma
        $chipHtml .= '<div style="position: relative; z-index: 2; font-size: 9pt; line-height: 1.3; display: inline-block; text-align: left;">' .
            '<strong style="font-size: 10pt;">' . htmlspecialchars($nombre) . '</strong><br/>' .
            'Presidente de Comisión<br/>' .
            htmlspecialchars($comision) . '<br/>' .
            'Consejo Regional<br/>' .
            htmlspecialchars($fechaHora) . // Usamos la fecha de aprobación real
            '</div>'; // Cierre div de texto

        $chipHtml .= '</div>'; // Cierre firma-chip
        return $chipHtml;
    };
    // --- Fin función interna ---

    // Iteramos sobre las firmas reales obtenidas de la BD
    if (!empty($data['firmas_aprobadas']) && is_array($data['firmas_aprobadas'])) {
        foreach ($data['firmas_aprobadas'] as $firma) {
            $html .= $generarChipFirma(
                $firma['nombrePresidente'],
                $firma['nombreComision'],
                date('d-m-Y H:i:s', strtotime($firma['fechaAprobacion'])),
                $firmaImgUri // Usamos el mismo sello para todos
            );
        }
    }

    $html .= '</div>'; // cierre signature-box-container
    // -----------------------------------------------------------------------------
    // ⭐ FIN NUEVO BLOQUE DE FIRMAS ⭐
    // -----------------------------------------------------------------------------


    $html .= '</body></html>';
    return $html;
}


/* =============================================================================
    🔽 COMIENZA EL "MOTOR" PRINCIPAL DEL SCRIPT (MODIFICADO) 🔽
=============================================================================
*/

try {
    $db = new conectorDB();
    $pdo = $db->getDatabase();
    $pdo->beginTransaction();

    $data_pdf = []; // Usamos un array limpio para el PDF

    // --- 1. CARGAR INFO MINUTA ---
    $sqlMinuta = "SELECT * FROM t_minuta WHERE idMinuta = :id";
    $stmtMinuta = $pdo->prepare($sqlMinuta);
    $stmtMinuta->execute([':id' => $idMinuta]);
    $data_pdf['minuta_info'] = $stmtMinuta->fetch(PDO::FETCH_ASSOC);

    if (!$data_pdf['minuta_info']) {
        throw new Exception('No se encontró la minuta.');
    }

    // --- 2. CARGAR INFO SECRETARIO ---
    // (Esto se mantiene, es solo para el encabezado del PDF)
    $idSecretario = $data_pdf['minuta_info']['t_usuario_idSecretario'] ?? $idUsuarioLogueado;
    $sqlSec = $pdo->prepare("SELECT CONCAT(pNombre, ' ', aPaterno) as nombreCompleto FROM t_usuario WHERE idUsuario = :idSec");
    $sqlSec->execute([':idSec' => $idSecretario]);
    $data_pdf['secretario_info'] = $sqlSec->fetch(PDO::FETCH_ASSOC);


    // --- 3. CARGAR COMISIONES Y PRESIDENTES (CORREGIDO PARA OBTENER IDs) ---

    // ⭐ CAMBIO AQUÍ: La función ahora debe devolver también el ID del presidente
    $getDatosComision = function ($idComision) use ($pdo) {
        if (empty($idComision)) return null;

        $sqlCom = $pdo->prepare("SELECT nombreComision, t_usuario_idPresidente FROM t_comision WHERE idComision = :id");
        $sqlCom->execute([':id' => $idComision]);
        $comData = $sqlCom->fetch(PDO::FETCH_ASSOC);

        if (!$comData) return ['nombre' => 'Comisión no encontrada', 'presidente' => 'N/A', 'idPresidente' => null];

        $idPresidente = $comData['t_usuario_idPresidente']; // <-- Obtenemos el ID oficial
        $nombrePresidente = 'Presidente no asignado';

        if (!empty($idPresidente)) {
            $sqlPres = $pdo->prepare("SELECT CONCAT(pNombre, ' ', aPaterno) as nombreCompleto FROM t_usuario WHERE idUsuario = :id");
            $sqlPres->execute([':id' => $idPresidente]);
            $nombrePresidente = $sqlPres->fetchColumn() ?: $nombrePresidente;
        }

        return [
            'nombre' => $comData['nombreComision'],
            'presidente' => $nombrePresidente,
            'idPresidente' => $idPresidente // <-- ⭐ Devolvemos el ID
        ];
    };

    // 3a. Cargar Comisión Principal
    $idCom1 = $data_pdf['minuta_info']['t_comision_idComision'];
    $idPres1_minuta = $data_pdf['minuta_info']['t_usuario_idPresidente']; // Presidente guardado en la minuta

    $com1_data = $getDatosComision($idCom1);


    $data_pdf['comisiones_info']['com1'] = $com1_data;


    // 3b. Cargar Comisiones Mixtas
    $sqlMixta = $pdo->prepare("SELECT t_comision_idComision_mixta, t_comision_idComision_mixta2 FROM t_reunion WHERE t_minuta_idMinuta = :idMinuta");
    $sqlMixta->execute([':idMinuta' => $idMinuta]);
    $comisionesMixtas = $sqlMixta->fetch(PDO::FETCH_ASSOC);

    $idPres2 = null;
    $idPres3 = null;

    if ($comisionesMixtas) {
        if (!empty($comisionesMixtas['t_comision_idComision_mixta'])) {
            $com2_data = $getDatosComision($comisionesMixtas['t_comision_idComision_mixta']);
            $data_pdf['comisiones_info']['com2'] = $com2_data;
            $idPres2 = $com2_data['idPresidente']; // <-- ⭐ Guardamos ID Pres 2
        }
        if (!empty($comisionesMixtas['t_comision_idComision_mixta2'])) {
            $com3_data = $getDatosComision($comisionesMixtas['t_comision_idComision_mixta2']);
            $data_pdf['comisiones_info']['com3'] = $com3_data;
            $idPres3 = $com3_data['idPresidente']; // <-- ⭐ Guardamos ID Pres 3
        }
    }

    // --- 4. LISTA DE PRESIDENTES REQUERIDOS ---
    // --- 4. LISTA DE PRESIDENTES REQUERIDOS ---
    $idPres1 = $com1_data['idPresidente'];

    // ⭐ CORRECCIÓN: Forzar todos los IDs a enteros para una comparación segura
    $presidentesRequeridos = array_map('intval', array_unique(array_filter([$idPres1, $idPres2, $idPres3])));

    $totalRequeridos = count($presidentesRequeridos);

    // (Opcional) Actualizar la minuta con el conteo correcto
    $pdo->prepare("UPDATE t_minuta SET presidentesRequeridos = ? WHERE idMinuta = ?")->execute([$totalRequeridos, $idMinuta]);


    // --- 5. VERIFICAR SI EL USUARIO LOGUEADO ES UN PRESIDENTE REQUERIDO ---
    // Usamos una comparación estricta (true) ahora que ambos son enteros
    if (!in_array($idUsuarioLogueado, $presidentesRequeridos, true)) {
        throw new Exception('No tiene permisos para aprobar esta minuta. Solo los presidentes de las comisiones asociadas pueden hacerlo.');
    }

    // --- 6. REGISTRAR LA APROBACIÓN ACTUAL ---
    // Usamos INSERT IGNORE para evitar errores si ya firmó
    $sqlInsertFirma = "INSERT IGNORE INTO t_aprobacion_minuta (t_minuta_idMinuta, t_usuario_idPresidente, fechaAprobacion)
                       VALUES (:idMinuta, :idUsuario, NOW())";
    $stmtInsert = $pdo->prepare($sqlInsertFirma);
    $stmtInsert->execute([
        ':idMinuta' => $idMinuta,
        ':idUsuario' => $idUsuarioLogueado
    ]);

    // --- 7. VERIFICAR SI YA SE COMPLETARON LAS APROBACIONES ---
    $sqlCount = $pdo->prepare("SELECT COUNT(DISTINCT t_usuario_idPresidente) FROM t_aprobacion_minuta WHERE t_minuta_idMinuta = :idMinuta");
    $sqlCount->execute([':idMinuta' => $idMinuta]);
    $totalAprobaciones = (int)$sqlCount->fetchColumn();

    if ($totalAprobaciones < $totalRequeridos) {
        // --- AÚN FALTAN FIRMAS ---
        // Actualizamos estado a PARCIAL y salimos
        $sqlUpdParcial = "UPDATE t_minuta SET estadoMinuta = 'PARCIAL' WHERE idMinuta = :id";
        $pdo->prepare($sqlUpdParcial)->execute([':id' => $idMinuta]);

        $pdo->commit();
        echo json_encode([
            'status' => 'success_partial',
            'message' => "Firma registrada. Faltan " . ($totalRequeridos - $totalAprobaciones) . " aprobación(es) más.",
            'aprobadas' => $totalAprobaciones,
            'requeridas' => $totalRequeridos
        ]);
        exit;
    }

    // =========================================================================
    // SI LLEGAMOS AQUÍ, ES LA ÚLTIMA FIRMA. PROCEDEMOS A GENERAR EL PDF FINAL.
    // =========================================================================

    // --- 8. CARGAR DATOS PARA EL PDF (Asistentes, Temas, Votos) ---
    // (Este bloque se mantiene igual que tu original)

    // 8a. ASISTENTES
    $sqlAsis = "SELECT CONCAT(u.pNombre, ' ', u.aPaterno) as nombreCompleto 
                FROM t_asistencia a
                JOIN t_usuario u ON a.t_usuario_idUsuario = u.idUsuario
                WHERE a.t_minuta_idMinuta = :id
                ORDER BY u.aPaterno, u.pNombre";
    $stmtAsis = $pdo->prepare($sqlAsis);
    $stmtAsis->execute([':id' => $idMinuta]);
    $data_pdf['asistentes'] = $stmtAsis->fetchAll(PDO::FETCH_ASSOC);

    // 8b. TEMAS Y ACUERDOS
    $sqlTemas = "SELECT t.idTema, t.nombreTema, t.objetivo, t.compromiso, t.observacion, a.descAcuerdo
                 FROM t_tema t 
                 LEFT JOIN t_acuerdo a ON a.t_tema_idTema = t.idTema
                 WHERE t.t_minuta_idMinuta = :id
                 ORDER BY t.idTema ASC";
    $stmtTemas = $pdo->prepare($sqlTemas);
    $stmtTemas->execute([':id' => $idMinuta]);
    $data_pdf['temas'] = $stmtTemas->fetchAll(PDO::FETCH_ASSOC);

    // 8c. VOTACIONES
    $data_pdf['votaciones'] = [];
    try {
        $sqlVotaciones = "SELECT idVotacion, nombreVotacion FROM t_votacion WHERE t_minuta_idMinuta = :idMinuta";
        $stmtVotaciones = $pdo->prepare($sqlVotaciones);
        $stmtVotaciones->execute([':idMinuta' => $idMinuta]);
        $votaciones = $stmtVotaciones->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($votaciones)) {
            $votacionIDs = array_column($votaciones, 'idVotacion');
            $votaciones_procesadas = [];
            foreach ($votaciones as $v) {
                $votaciones_procesadas[$v['idVotacion']] = [
                    'nombre' => htmlspecialchars($v['nombreVotacion']),
                    'resumen' => ['SI' => 0, 'NO' => 0, 'ABSTENCION' => 0],
                    'detalle' => []
                ];
            }
            $placeholders = implode(',', array_fill(0, count($votacionIDs), '?'));
            $sqlVotos = "SELECT v.t_votacion_idVotacion, v.opcionVoto, CONCAT(u.pNombre, ' ', u.aPaterno) as nombreCompleto
                         FROM t_voto v
                         JOIN t_usuario u ON v.t_usuario_idUsuario = u.idUsuario
                         WHERE v.t_votacion_idVotacion IN ($placeholders)";
            $stmtVotos = $pdo->prepare($sqlVotos);
            $stmtVotos->execute($votacionIDs);
            $todos_los_votos = $stmtVotos->fetchAll(PDO::FETCH_ASSOC);

            foreach ($todos_los_votos as $voto) {
                $idVotacionActual = $voto['t_votacion_idVotacion'];
                $opcion = $voto['opcionVoto'];
                if (isset($votaciones_procesadas[$idVotacionActual]['resumen'][$opcion])) {
                    $votaciones_procesadas[$idVotacionActual]['resumen'][$opcion]++;
                }
                $votaciones_procesadas[$idVotacionActual]['detalle'][] = [
                    'nombreCompleto' => htmlspecialchars($voto['nombreCompleto']),
                    'opcionVoto' => htmlspecialchars($opcion)
                ];
            }
            $data_pdf['votaciones'] = array_values($votaciones_procesadas);
        }
    } catch (Exception $e) {
        error_log("Error al cargar votaciones para PDF: " . $e->getMessage());
        $data_pdf['votaciones'] = [];
    }

    // --- 9. ⭐ NUEVO: CARGAR FIRMAS PARA EL PDF ---
    $sqlFirmasPDF = "SELECT 
                        CONCAT(u.pNombre, ' ', u.aPaterno) as nombrePresidente, 
                        c.nombreComision, 
                        a.fechaAprobacion 
                     FROM t_aprobacion_minuta a
                     JOIN t_usuario u ON a.t_usuario_idPresidente = u.idUsuario
                     -- Unir con t_comision para saber qué comisión preside
                     LEFT JOIN t_comision c ON c.t_usuario_idPresidente = u.idUsuario
                     WHERE a.t_minuta_idMinuta = :idMinuta
                     GROUP BY u.idUsuario -- Agrupar por si un presidente preside varias comisiones de la minuta
                     ORDER BY a.fechaAprobacion ASC";
    $stmtFirmas = $pdo->prepare($sqlFirmasPDF);
    $stmtFirmas->execute([':idMinuta' => $idMinuta]);
    $data_pdf['firmas_aprobadas'] = $stmtFirmas->fetchAll(PDO::FETCH_ASSOC);


    // --- 10. DEFINIR LOGOS Y SELLO DE FIRMA ---
    $logoGoreUri = ImageToDataUrl(ROOT_PATH . 'public/img/logo2.png');
    $logoCoreUri = ImageToDataUrl(ROOT_PATH . 'public/img/logoCore1.png');

    // Usamos el sello 'aprobacion.png' genérico, ya que 'firmadigital.png' parece ser para Secretarios.
    $firmaImgUri = ImageToDataUrl(ROOT_PATH . 'public/img/aprobacion.png');

    // --- 11. GENERAR HTML (Ahora con el bloque de firmas múltiples) ---
    $html = generateMinutaHtml($data_pdf, $logoGoreUri, $logoCoreUri, $firmaImgUri);

    // --- 12. INICIALIZAR DOMPDF Y RENDERIZAR ---
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('letter', 'portrait');
    $dompdf->render();

    // --- 13. GUARDAR PDF EN SERVIDOR ---
    $pathDirectorio = ROOT_PATH . 'public/docs/minutas_aprobadas/';
    if (!file_exists($pathDirectorio)) {
        mkdir($pathDirectorio, 0775, true);
    }
    $nombreArchivo = "Minuta_Aprobada_N" . $idMinuta . "_" . date('Ymd_His') . ".pdf";
    $pathCompleto = $pathDirectorio . $nombreArchivo;

    $pathParaBD = '/corevota/public/docs/minutas_aprobadas/' . $nombreArchivo;

    file_put_contents($pathCompleto, $dompdf->output());

    // --- 14. ACTUALIZAR MINUTA EN BD (Estado final) ---
    $sqlUpd = "UPDATE t_minuta SET 
                    estadoMinuta = 'APROBADA', 
                    pathArchivo = :pathArchivo, 
                    fechaAprobacion = NOW() 
                WHERE idMinuta = :id";
    $stmtUpd = $pdo->prepare($sqlUpd);
    $stmtUpd->execute([
        ':pathArchivo' => $pathParaBD,
        ':id' => $idMinuta
    ]);

    // --- 15. COMMIT Y RESPUESTA EXITOSA FINAL ---
    $pdo->commit();
    echo json_encode(['status' => 'success_final', 'message' => 'Minuta aprobada y PDF generado con todas las firmas.', 'pdf_path' => $pathParaBD]);
} catch (Exception $e) {
    // --- 16. ROLLBACK Y RESPUESTA DE ERROR ---
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error fatal al aprobar minuta: " . $e->getMessage() . " \nEn archivo: " . $e->getFile() . " \nEn línea: " . $e->getLine());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error al procesar la aprobación: ' . $e->getMessage()]);
}
