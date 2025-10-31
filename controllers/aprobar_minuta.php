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
require_once ROOT_PATH . 'models/FirmaModel.php';
require_once ROOT_PATH . 'vendor/autoload.php'; // Dompdf

use Dompdf\Dompdf;
use Dompdf\Options;

// 2. OBTENER DATOS DE ENTRADA Y SESIÓN
// (Se mueven dentro del 'try' principal para más seguridad)
$input_data = json_decode(file_get_contents('php://input'), true);
$idMinuta = $input_data['idMinuta'] ?? null;
$idUsuarioLogueado = $_SESSION['idUsuario'] ?? null;
$nombreUsuarioLogueado = trim(($_SESSION['pNombre'] ?? '') . ' ' . ($_SESSION['aPaterno'] ?? 'N/A'));

if (!$idMinuta || !$idUsuarioLogueado || !is_numeric($idMinuta)) {
    echo json_encode(['status' => 'error', 'message' => 'Datos incompletos, sesión no válida o ID de minuta inválido.']);
    exit;
}

// -----------------------------------------------------------------------------
// FUNCIÓN ImageToDataUrl (se mantiene por si usas logos; si no existen, no rompe)
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
// FUNCIÓN PARA GENERAR HTML (CORREGIDA)
// -----------------------------------------------------------------------------
function generateMinutaHtml($data, $logoGoreUri, $logoCoreUri)
{
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
    $presidente2_nombre = isset($com2['presidente']) ? htmlspecialchars($com2['presidente']) : null;
    $comision3_nombre  = isset($com3['nombre'])  ? htmlspecialchars($com3['nombre'])  : null;
    $presidente3_nombre = isset($com3['presidente']) ? htmlspecialchars($com3['presidente']) : null;

    $esMixta = ($comision2_nombre || $comision3_nombre);

    // Título de comisiones en header
    $tituloComisionesHeader = $comision1_nombre;
    if ($comision2_nombre) $tituloComisionesHeader .= " / " . $comision2_nombre;
    if ($comision3_nombre) $tituloComisionesHeader .= " / " . $comision3_nombre;

    // Datos de firma (enviados desde PHP)
    $firmaNombre  = htmlspecialchars($data['firma']['nombre'] ?? 'N/A');
    $firmaFecha  = htmlspecialchars($data['firma']['fechaHora'] ?? '');
    //$firmaRut   = htmlspecialchars($data['firma']['rut'] ?? '');
    $firmaCorreo  = htmlspecialchars($data['firma']['correo'] ?? '');
    $firmaCargo  = htmlspecialchars($data['firma']['cargo'] ?? '');
    $firmaUnidad  = htmlspecialchars($data['firma']['unidad'] ?? '');

    // --- HTML ---
    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Minuta Aprobada ' . $idMinuta . '</title><style>' .
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
        ($logoGoreUri ? '<img src="' . htmlspecialchars($logoGoreUri) . '" class="logo-left" alt="Logo GORE">' : '') .
        ($logoCoreUri ? '<img src="' . htmlspecialchars($logoCoreUri) . '" class="logo-right" alt="Logo CORE">' : '') .
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


    // -----------------------------------------------------------------------------
    // BLOQUE DE FIRMA (versión estable con verificación + estilo alineado)
    // -----------------------------------------------------------------------------
    $firmaImg = '';
    try {
        $filename = ($data['firma']['idTipoUsuario'] ?? 1) == 1
            ? 'firmadigital.png'
            : 'aprobacion.png';

        $imgPath = realpath(__DIR__ . '/../../corevota/public/img/' . $filename);
        error_log("🧭 RUTA CALCULADA: " . $imgPath);
        error_log("👤 Tipo de usuario en firma: " . ($data['firma']['idTipoUsuario'] ?? 'NO DEFINIDO'));

        if ($imgPath && is_file($imgPath)) {
            $mime = mime_content_type($imgPath);
            $firmaImg = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($imgPath));
            error_log("✅ Firma encontrada y cargada correctamente");
        } else {
            error_log("⚠️ No se encontró imagen de firma en: " . $imgPath);
        }
    } catch (Throwable $e) {
        error_log("❌ Error al generar firmaImg: " . $e->getMessage());
    }


    $html .= '<div class="signature-box">';
    $html .= '<div class="signature-line"></div>' .
            '<p>' . htmlspecialchars($presidente1_nombre) . '</p>' .
            '<p>Presidente</p>' .
            '<p>Comisión ' . htmlspecialchars($comision1_nombre) . '</p>';

    // Bloque con flexbox: imagen + texto alineados
    $html .= '<div class="firma-chip" style="display:flex;align-items:center;gap:12px;">';

    if (!empty($firmaImg)) {
        $html .= '<img src="' . $firmaImg . '" alt="Firma" style="width:80px;height:auto;opacity:0.95;">';
    } else {
        $html .= '<span style="color:#a00;font-size:8pt;">[Firma digital no encontrada]</span>';
    }

    $html .= '<div style="font-size:9pt;line-height:1.4;">' .
                '<strong>Firmado electrónicamente por:</strong> ' . htmlspecialchars($presidente1_nombre) . '<br/>' .
                ($firmaCargo  ? '<strong>Cargo:</strong> '  . htmlspecialchars($firmaCargo)  . '<br/>' : '') .
                ($firmaUnidad ? '<strong>Unidad:</strong> ' . htmlspecialchars($firmaUnidad) . '<br/>' : '') .
                ($firmaCorreo ? '<strong>Correo:</strong> ' . htmlspecialchars($firmaCorreo) . '<br/>' : '') .
                '<strong>Fecha y hora de firma:</strong> ' . htmlspecialchars($firmaFecha) .
            '</div>';

    $html .= '</div>'; // cierre firma-chip
    $html .= '</div>'; // cierre signature-box

    $html .= '</body></html>';
    return $html;
}


/* =============================================================================
   🔽 COMIENZA EL "MOTOR" PRINCIPAL DEL SCRIPT 🔽
   (Este es el código que faltaba y que ejecuta toda la lógica)
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

    // --- 2. CARGAR INFO SECRETARIO Y FIRMA ---
    // El usuario logueado ES el presidente que está firmando
    $idSecretario = $data_pdf['minuta_info']['t_usuario_idSecretario'] ?? $idUsuarioLogueado;

    // 2a. Datos del Secretario que escribió la minuta
    $sqlSec = $pdo->prepare("SELECT CONCAT(pNombre, ' ', aPaterno) as nombreCompleto FROM t_usuario WHERE idUsuario = :idSec");
    $sqlSec->execute([':idSec' => $idSecretario]);
    $data_pdf['secretario_info'] = $sqlSec->fetch(PDO::FETCH_ASSOC);

    // 2b. Datos de FIRMA (El presidente que está logueado y aprobando)
    $sqlFirma = $pdo->prepare("SELECT CONCAT(pNombre, ' ', aPaterno) as nombreCompleto, 'Presidente de Comisión' as cargo, 'Consejo Regional' as unidad FROM t_usuario WHERE idUsuario = :idUser");
    $sqlFirma->execute([':idUser' => $idUsuarioLogueado]);
    $data_pdf['firma'] = $sqlFirma->fetch(PDO::FETCH_ASSOC);
    $data_pdf['firma']['fechaHora'] = date('d-m-Y H:i:s'); // Firma ahora mismo

    // --- 3. CARGAR COMISIONES Y PRESIDENTES (CORREGIDO PARA MIXTAS) ---
    $idCom1 = $data_pdf['minuta_info']['t_comision_idComision'];
    $idPres1 = $data_pdf['minuta_info']['t_usuario_idPresidente'];

    // Función auxiliar para no repetir código
    $getDatosComision = function ($idComision) use ($pdo) {
        if (empty($idComision)) return null;

        $sqlCom = $pdo->prepare("SELECT nombreComision, t_usuario_idPresidente FROM t_comision WHERE idComision = :id");
        $sqlCom->execute([':id' => $idComision]);
        $comData = $sqlCom->fetch(PDO::FETCH_ASSOC);

        if (!$comData) return ['nombre' => 'Comisión no encontrada', 'presidente' => 'N/A'];

        $idPresidente = $comData['t_usuario_idPresidente'];
        $nombrePresidente = 'Presidente no asignado';

        if (!empty($idPresidente)) {
            $sqlPres = $pdo->prepare("SELECT CONCAT(pNombre, ' ', aPaterno) as nombreCompleto FROM t_usuario WHERE idUsuario = :id");
            $sqlPres->execute([':id' => $idPresidente]);
            $nombrePresidente = $sqlPres->fetchColumn() ?: $nombrePresidente;
        }

        return [
            'nombre' => $comData['nombreComision'],
            'presidente' => $nombrePresidente
        ];
    };

    // 3a. Cargar Comisión Principal (Presidente de la minuta)
    $com1_data = $getDatosComision($idCom1);
    // Sobreescribimos el presidente por el que quedó guardado en la minuta (por si cambió)
    if (!empty($idPres1)) {
        $sqlPres1 = $pdo->prepare("SELECT CONCAT(pNombre, ' ', aPaterno) as nombreCompleto FROM t_usuario WHERE idUsuario = :id");
        $sqlPres1->execute([':id' => $idPres1]);
        $com1_data['presidente'] = $sqlPres1->fetchColumn() ?: $com1_data['presidente'];
    }
    $data_pdf['comisiones_info']['com1'] = $com1_data;


    // 3b. Cargar Comisiones Mixtas (Presidentes oficiales de cada comisión)
    $sqlMixta = $pdo->prepare("SELECT t_comision_idComision_mixta, t_comision_idComision_mixta2 FROM t_reunion WHERE t_minuta_idMinuta = :idMinuta");
    $sqlMixta->execute([':idMinuta' => $idMinuta]);
    $comisionesMixtas = $sqlMixta->fetch(PDO::FETCH_ASSOC);

    if ($comisionesMixtas) {
        // Cargar Comisión 2 (Mixta)
        if (!empty($comisionesMixtas['t_comision_idComision_mixta'])) {
            $data_pdf['comisiones_info']['com2'] = $getDatosComision($comisionesMixtas['t_comision_idComision_mixta']);
        }
        // Cargar Comisión 3 (Mixta)
        if (!empty($comisionesMixtas['t_comision_idComision_mixta2'])) {
            $data_pdf['comisiones_info']['com3'] = $getDatosComision($comisionesMixtas['t_comision_idComision_mixta2']);
        }
    }

    // --- 4. CARGAR ASISTENTES ---
    $sqlAsis = "SELECT CONCAT(u.pNombre, ' ', u.aPaterno) as nombreCompleto 
                FROM t_asistencia a
                JOIN t_usuario u ON a.t_usuario_idUsuario = u.idUsuario
                WHERE a.t_minuta_idMinuta = :id
                ORDER BY u.aPaterno, u.pNombre";
    $stmtAsis = $pdo->prepare($sqlAsis);
    $stmtAsis->execute([':id' => $idMinuta]);
    $data_pdf['asistentes'] = $stmtAsis->fetchAll(PDO::FETCH_ASSOC);

    // --- 5. CARGAR TEMAS Y ACUERDOS ---
    $sqlTemas = "SELECT t.idTema, t.nombreTema, t.objetivo, t.compromiso, t.observacion, a.descAcuerdo
                 FROM t_tema t 
                 LEFT JOIN t_acuerdo a ON a.t_tema_idTema = t.idTema
                 WHERE t.t_minuta_idMinuta = :id
                 ORDER BY t.idTema ASC";
    $stmtTemas = $pdo->prepare($sqlTemas);
    $stmtTemas->execute([':id' => $idMinuta]);
    $data_pdf['temas'] = $stmtTemas->fetchAll(PDO::FETCH_ASSOC);

    // --- 6. CARGAR VOTACIONES (El bloque que te di, integrado aquí) ---
    $data_pdf['votaciones'] = []; // Inicializar
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
        $data_pdf['votaciones'] = []; // Asegurarse que sea un array vacío si falla
    }
    // --- (FIN BLOQUE VOTACIONES) ---

    // --- 7. DEFINIR LOGOS ---
    $logoGoreUri = ImageToDataUrl(ROOT_PATH . 'public/img/logo2.png');
    $logoCoreUri = ImageToDataUrl(ROOT_PATH . 'public/img/logoCore1.png');

    // --- 8. GENERAR HTML ---
    $html = generateMinutaHtml($data_pdf, $logoGoreUri, $logoCoreUri);

    // --- 9. INICIALIZAR DOMPDF Y RENDERIZAR ---
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true); // Para las imágenes
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('letter', 'portrait');
    $dompdf->render();

    // --- 10. GUARDAR PDF EN SERVIDOR ---

    // 🔽 CAMBIO 1: Ruta física correcta en tu servidor (con 'docs')
    $pathDirectorio = ROOT_PATH . 'public/docs/minutas_aprobadas/';
    if (!file_exists($pathDirectorio)) {
        mkdir($pathDirectorio, 0775, true);
    }
    $nombreArchivo = "Minuta_Aprobada_N" . $idMinuta . "_" . date('Ymd_His') . ".pdf";
    $pathCompleto = $pathDirectorio . $nombreArchivo;

    // 🔽 CAMBIO 2: Ruta web correcta (URL) para la Base de Datos
    // Esta ruta DEBE empezar con /corevota/ para que funcione desde cualquier página
    $pathParaBD = '/corevota/public/docs/minutas_aprobadas/' . $nombreArchivo; // Ruta relativa para la BD

    file_put_contents($pathCompleto, $dompdf->output());

    // --- 11. ACTUALIZAR MINUTA EN BD (CORREGIDO) ---
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


    // -----------------------------------------------------------------------------
    // 11b. REGISTRAR FIRMA ELECTRÓNICA DIRECTAMENTE EN t_firma
    // -----------------------------------------------------------------------------
    $idTipoUsuario = $_SESSION['tipoUsuario_id'] ?? 1;

    // Obtener comisión asociada
    $stmtCom = $pdo->prepare("SELECT t_comision_idComision FROM t_minuta WHERE idMinuta = ?");
    $stmtCom->execute([$idMinuta]);
    $idComision = $stmtCom->fetchColumn();

    if ($idComision) {
        $sql_firma = "INSERT INTO t_firma (descFirma, idTipoUsuario, fechaGuardado, idUsuario, idComision)
                    VALUES (:desc, :tipo, CURTIME(), :usuario, :comision)";
        $stmt_firma = $pdo->prepare($sql_firma);
        $stmt_firma->execute([
            ':desc'     => 'Firma electrónica registrada al aprobar minuta ' . $idMinuta,
            ':tipo'     => $idTipoUsuario,
            ':usuario'  => $idUsuarioLogueado,
            ':comision' => $idComision
        ]);
    }

    // --- 12. COMMIT Y RESPUESTA EXITOSA ---
    $pdo->commit();
    echo json_encode(['status' => 'success', 'message' => 'Minuta aprobada y PDF generado.', 'pdf_path' => $pathParaBD]);
} catch (Exception $e) {
    // --- 13. ROLLBACK Y RESPUESTA DE ERROR ---
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error fatal al aprobar minuta: " . $e->getMessage() . " \nEn archivo: " . $e->getFile() . " \nEn línea: " . $e->getLine());
    http_response_code(500); // Enviar un código de error real
    echo json_encode(['status' => 'error', 'message' => 'Error al procesar la aprobación: ' . $e->getMessage()]);
}
