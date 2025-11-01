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
$idUsuarioLogueado = isset($_SESSION['idUsuario']) ? intval($_SESSION['idUsuario']) : null;
$nombreUsuarioLogueado = trim(($_SESSION['pNombre'] ?? '') . ' ' . ($_SESSION['aPaterno'] ?? 'N/A'));

if (!$idMinuta || !$idUsuarioLogueado || !is_numeric($idMinuta)) {
    echo json_encode(['status' => 'error', 'message' => 'Datos incompletos, sesión no válida o ID de minuta inválido.']);
    exit;
}

// -----------------------------------------------------------------------------
// FUNCIÓN ImageToDataUrl
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
    $idMinuta = htmlspecialchars($data['minuta_info']['idMinuta'] ?? 'N/A');
    $fecha = htmlspecialchars(date('d-m-Y', strtotime($data['minuta_info']['fechaMinuta'] ?? 'now')));
    $hora = htmlspecialchars(date('H:i', strtotime($data['minuta_info']['horaMinuta'] ?? 'now')));
    $secretario = htmlspecialchars($data['secretario_info']['nombreCompleto'] ?? 'N/A');

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


    // --- HTML ---
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
        '.firma-chip{font-size:9pt;color:#222; text-align:center; width:45%; margin: 10px 2.5%; border:1px dashed #aaa;padding:8px;border-radius:6px;' .
        'position: relative; min-height: 100px; overflow: hidden; display: inline-block; float:left; page-break-inside:avoid;} ' .
        '.votacion-block{page-break-inside:avoid; margin-bottom:15px; font-size:9pt;}' .
        '.votacion-tabla{width:100%;border-collapse:collapse;margin-top:5px;}' .
        '.votacion-tabla th, .votacion-tabla td{border:1px solid #ccc;padding:4px 6px;}' .
        '.votacion-tabla th{background-color:#f2f2f2;text-align:center;}' .
        '.votacion-detalle{columns:2;-webkit-columns:2;column-gap:20px;padding-left:20px;margin-top:5px;}' .
        '</style></head><body>';

    // (Contenido HTML del PDF - Sin cambios)
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
                $html .= '<div><strong>Objetivo:</strong> ' . $tema['objetivo'] . '</div>';
            }
            if (!empty(trim($tema['descAcuerdo'] ?? ''))) {
                $html .= '<div><strong>Acuerdo:</strong> ' . $tema['descAcuerdo'] . '</div>';
            }
            if (!empty(trim($tema['compromiso'] ?? ''))) {
                $html .= '<div><strong>Compromiso:</strong> ' . $tema['compromiso'] . '</div>';
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

    // --- BLOQUE DE FIRMAS MÚLTIPLES ---
    $html .= '<div class="signature-box-container">';

    $generarChipFirma = function ($nombre, $comision, $fechaHora, $firmaImgUri) {
        $chipHtml = '<div class="firma-chip">';
        if (!empty($firmaImgUri)) {
            $chipHtml .= '<img src="' . $firmaImgUri . '" alt="Firma" ' .
                'style="position: absolute; top: 10px; left: 50%; margin-left: -50px; width: 100px; height: auto; opacity: 0.2; z-index: 1;">';
        } else {
            $chipHtml .= '<span style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: 1; color: #a00; font-size: 8pt; opacity: 0.3;">[SELLO NO ENCONTRADO]</span>';
        }
        $chipHtml .= '<div style="position: relative; z-index: 2; font-size: 9pt; line-height: 1.3; display: inline-block; text-align: left;">' .
            '<strong style="font-size: 10pt;">' . htmlspecialchars($nombre) . '</strong><br/>' .
            'Presidente de Comisión<br/>' .
            htmlspecialchars($comision) . '<br/>' .
            'Consejo Regional<br/>' .
            htmlspecialchars($fechaHora) .
            '</div>';
        $chipHtml .= '</div>';
        return $chipHtml;
    };

    if (!empty($data['firmas_aprobadas']) && is_array($data['firmas_aprobadas'])) {
        foreach ($data['firmas_aprobadas'] as $firma) {
            $html .= $generarChipFirma(
                $firma['nombrePresidente'],
                $firma['nombreComision'],
                date('d-m-Y H:i:s', strtotime($firma['fechaAprobacion'])),
                $firmaImgUri
            );
        }
    }

    $html .= '</div>'; // cierre signature-box-container
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

    $data_pdf = [];

    // --- 1. CARGAR INFO MINUTA ---
    $sqlMinuta = "SELECT * FROM t_minuta WHERE idMinuta = :id";
    $stmtMinuta = $pdo->prepare($sqlMinuta);
    $stmtMinuta->execute([':id' => $idMinuta]);
    $data_pdf['minuta_info'] = $stmtMinuta->fetch(PDO::FETCH_ASSOC);

    if (!$data_pdf['minuta_info']) {
        throw new Exception('No se encontró la minuta.');
    }

    // --- 2. CARGAR INFO SECRETARIO ---
    // (Asumimos que 't_usuario_idSecretario' existe en t_minuta, si no, usamos el logueado)
    $idSecretario = $data_pdf['minuta_info']['t_usuario_idSecretario'] ?? $idUsuarioLogueado;
    $sqlSec = $pdo->prepare("SELECT CONCAT(pNombre, ' ', aPaterno) as nombreCompleto FROM t_usuario WHERE idUsuario = :idSec");
    $sqlSec->execute([':idSec' => $idSecretario]);
    $data_pdf['secretario_info'] = $sqlSec->fetch(PDO::FETCH_ASSOC);


    // --- 3. CARGAR COMISIONES Y PRESIDENTES ---
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

        return [
            'nombre' => $comData['nombreComision'],
            'presidente' => $nombrePresidente,
            'idPresidente' => $idPresidente
        ];
    };

    // 3a. Cargar Comisión Principal (de t_minuta)
    $idCom1 = $data_pdf['minuta_info']['t_comision_idComision'];
    $com1_data = $getDatosComision($idCom1);
    $data_pdf['comisiones_info']['com1'] = $com1_data;

    // 3b. Cargar Comisiones Mixtas (de t_reunion)
    $sqlMixta = $pdo->prepare("SELECT t_comision_idComision_mixta, t_comision_idComision_mixta2 FROM t_reunion WHERE t_minuta_idMinuta = :idMinuta");
    $sqlMixta->execute([':idMinuta' => $idMinuta]);
    $comisionesMixtas = $sqlMixta->fetch(PDO::FETCH_ASSOC);

    $idPres2 = null;
    $idPres3 = null;

    if ($comisionesMixtas) {
        if (!empty($comisionesMixtas['t_comision_idComision_mixta'])) {
            $com2_data = $getDatosComision($comisionesMixtas['t_comision_idComision_mixta']);
            $data_pdf['comisiones_info']['com2'] = $com2_data;
            $idPres2 = $com2_data['idPresidente'];
        }
        if (!empty($comisionesMixtas['t_comision_idComision_mixta2'])) {
            $com3_data = $getDatosComision($comisionesMixtas['t_comision_idComision_mixta2']);
            $data_pdf['comisiones_info']['com3'] = $com3_data;
            $idPres3 = $com3_data['idPresidente'];
        }
    }

    // --- 4. LISTA DE PRESIDENTES REQUERIDOS ---
    $idPres1 = $com1_data['idPresidente'];
    $presidentesRequeridos = array_map('intval', array_unique(array_filter([$idPres1, $idPres2, $idPres3])));
    $totalRequeridos = count($presidentesRequeridos);

    // Actualizar la minuta con el conteo correcto (siempre es bueno)
    $pdo->prepare("UPDATE t_minuta SET presidentesRequeridos = ? WHERE idMinuta = ?")->execute([$totalRequeridos, $idMinuta]);


    // --- 5. VERIFICAR PERMISO ---
    if (!in_array($idUsuarioLogueado, $presidentesRequeridos, true)) {
        throw new Exception('No tiene permisos para aprobar esta minuta. Solo los presidentes de las comisiones asociadas pueden hacerlo.');
    }

    // =========================================================================
    // 🔽 INICIO DE LA CORRECCIÓN 🔽
    // Esta sección reemplaza la lógica if/else (líneas 234-260)
    // =========================================================================

    // --- 6. REGISTRAR O RE-CONFIRMAR LA APROBACIÓN ACTUAL (LÓGICA SIMPLIFICADA) ---
    // Esta única consulta (INSERT...ON DUPLICATE KEY UPDATE) maneja todos los casos:
    // 1. Inserta una nueva firma con estado 'EN_ESPERA' si no existe.
    // 2. Actualiza una firma existente (ej. 'REQUIERE_REVISION') a 'EN_ESPERA'.

    $sqlUpsertFirma = "INSERT INTO t_aprobacion_minuta 
            (t_minuta_idMinuta, t_usuario_idPresidente, fechaAprobacion, estado_firma)
          VALUES 
            (:idMinuta, :idUsuario, NOW(), 'EN_ESPERA')
          ON DUPLICATE KEY UPDATE
            fechaAprobacion = NOW(),
            estado_firma = 'EN_ESPERA'";

    $stmtUpsert = $pdo->prepare($sqlUpsertFirma);
    $stmtUpsert->execute([
        ':idMinuta' => $idMinuta,
        ':idUsuario' => $idUsuarioLogueado
    ]);

    // =========================================================================
    // 🔼 FIN DE LA CORRECCIÓN 🔼
    // =========================================================================


    // --- 7. VERIFICAR SI YA SE COMPLETARON LAS APROBACIONES ---
    // Contamos solo las firmas que están activamente 'EN_ESPERA'
    // (Esta consulta ya era correcta y busca el estado que acabamos de guardar)
    $sqlCount = $pdo->prepare("SELECT COUNT(DISTINCT t_usuario_idPresidente)
                            FROM t_aprobacion_minuta
                            WHERE t_minuta_idMinuta = :idMinuta
                            AND estado_firma = 'EN_ESPERA'");
    $sqlCount->execute([':idMinuta' => $idMinuta]);
    $totalAprobaciones = (int)$sqlCount->fetchColumn();


    if ($totalAprobaciones < $totalRequeridos) {
        // --- AÚN FALTAN FIRMAS ---

        // Ahora que tu ENUM tiene 'PARCIAL', esta es la acción correcta.
        $sqlUpdParcial = "UPDATE t_minuta SET estadoMinuta = 'PARCIAL' WHERE idMinuta = :id";
        $pdo->prepare($sqlUpdParcial)->execute([':id' => $idMinuta]);

        $pdo->commit(); // Guardamos la firma Y el estado 'PARCIAL'.

        $faltan = $totalRequeridos - $totalAprobaciones;
        $mensaje = "Firma registrada. Faltan {$faltan} aprobación(es) más.";

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

    // --- 8. CARGAR DATOS PARA EL PDF (Asistentes, Temas, Votos) ---

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

    // 8c. VOTACIONES (Tu lógica original)
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

            if (!empty($votacionIDs)) { // Añadir chequeo por si $votacionIDs está vacío
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
            }
            $data_pdf['votaciones'] = array_values($votaciones_procesadas);
        }
    } catch (Exception $e) {
        error_log("Error al cargar votaciones para PDF: " . $e->getMessage());
        $data_pdf['votaciones'] = [];
    }

    // --- 9. CARGAR FIRMAS PARA EL PDF ---
    $sqlFirmasPDF = "SELECT 
            CONCAT(u.pNombre, ' ', u.aPaterno) as nombrePresidente, 
            c.nombreComision, 
            a.fechaAprobacion 
          FROM t_aprobacion_minuta a
          JOIN t_usuario u ON a.t_usuario_idPresidente = u.idUsuario
    -- ⬇️ CORRECCIÓN EN LA LÓGICA DE JOIN PARA OBTENER LA COMISIÓN CORRECTA ⬇️
          LEFT JOIN t_comision c ON c.t_usuario_idPresidente = u.idUsuario
          WHERE a.t_minuta_idMinuta = :idMinuta
          AND a.estado_firma = 'EN_ESPERA' -- ¡IMPORTANTE!
          GROUP BY u.idUsuario
          ORDER BY a.fechaAprobacion ASC";
    // row:368
    $stmtFirmas = $pdo->prepare($sqlFirmasPDF);
    $stmtFirmas->execute([':idMinuta' => $idMinuta]);
    $data_pdf['firmas_aprobadas'] = $stmtFirmas->fetchAll(PDO::FETCH_ASSOC);

    // --- CORRECCIÓN LÓGICA PARA NOMBRES DE COMISIÓN ---
    // El JOIN anterior era incorrecto para comisiones mixtas.
    // Usaremos los datos que ya cargamos en el paso 3.
    $mapaPresidentes = [];
    if (isset($data_pdf['comisiones_info']['com1'])) {
        $mapaPresidentes[$data_pdf['comisiones_info']['com1']['idPresidente']] = $data_pdf['comisiones_info']['com1']['nombre'];
    }
    if (isset($data_pdf['comisiones_info']['com2'])) {
        $mapaPresidentes[$data_pdf['comisiones_info']['com2']['idPresidente']] = $data_pdf['comisiones_info']['com2']['nombre'];
    }
    if (isset($data_pdf['comisiones_info']['com3'])) {
        $mapaPresidentes[$data_pdf['comisiones_info']['com3']['idPresidente']] = $data_pdf['comisiones_info']['com3']['nombre'];
    }

    $firmasCorregidas = [];
    $sqlFirmasRevisado = $pdo->prepare("SELECT 
                                        a.t_usuario_idPresidente,
                                        CONCAT(u.pNombre, ' ', u.aPaterno) as nombrePresidente, 
                                        a.fechaAprobacion 
                                      FROM t_aprobacion_minuta a
                                      JOIN t_usuario u ON a.t_usuario_idPresidente = u.idUsuario
                                      WHERE a.t_minuta_idMinuta = :idMinuta
                                      AND a.estado_firma = 'EN_ESPERA'
                                      GROUP BY u.idUsuario
                                      ORDER BY a.fechaAprobacion ASC");
    $sqlFirmasRevisado->execute([':idMinuta' => $idMinuta]);
    $firmas_temp = $sqlFirmasRevisado->fetchAll(PDO::FETCH_ASSOC);

    foreach ($firmas_temp as $firma) {
        $idPresi = $firma['t_usuario_idPresidente'];
        $firmasCorregidas[] = [
            'nombrePresidente' => $firma['nombrePresidente'],
            'fechaAprobacion' => $firma['fechaAprobacion'],
            'nombreComision' => $mapaPresidentes[$idPresi] ?? 'Comisión No Identificada' // Asignar nombre desde el mapa
        ];
    }
    $data_pdf['firmas_aprobadas'] = $firmasCorregidas;
    // --- FIN CORRECCIÓN LÓGICA COMISIÓN ---


    // --- 10. DEFINIR LOGOS Y SELLO DE FIRMA ---
    $logoGoreUri = ImageToDataUrl(ROOT_PATH . 'public/img/logo2.png');
    $logoCoreUri = ImageToDataUrl(ROOT_PATH . 'public/img/logoCore1.png');
    $firmaImgUri = ImageToDataUrl(ROOT_PATH . 'public/img/aprobacion.png');

    // --- 11. GENERAR HTML ---
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
