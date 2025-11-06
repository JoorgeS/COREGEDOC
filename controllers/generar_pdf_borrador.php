<?php
// /corevota/controllers/generar_pdf_borrador.php
// Este script genera una VISTA PREVIA de la minuta en PDF,
// (AJUSTADO) AHORA INCLUYE LOS SELLOS DE VALIDACIÓN DEL ST.

header('Content-Type: application/pdf');
error_reporting(0); // Suprimimos errores para que no rompan el PDF
ini_set('display_errors', 0);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. INCLUIR DEPENDENCIAS Y CONFIGURACIÓN
define('ROOT_PATH', dirname(__DIR__) . '/');
require_once ROOT_PATH . 'class/class.conectorDB.php';
require_once ROOT_PATH . 'vendor/autoload.php';
require_once ROOT_PATH . 'models/minutaModel.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// 2. OBTENER DATOS DE ENTRADA Y SESIÓN
$idMinuta = $_GET['id'] ?? null; // Obtenemos por GET
$idUsuarioLogueado = isset($_SESSION['idUsuario']) ? intval($_SESSION['idUsuario']) : null;

if (!$idMinuta || !$idUsuarioLogueado || !is_numeric($idMinuta)) {
    // Si falla, mostramos un PDF de error
    die("Error: ID de Minuta no válido o sesión expirada.");
}

// -----------------------------------------------------------------------------
// FUNCIÓN ImageToDataUrl (Copiada de aprobar_minuta.php)
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
// FUNCIÓN PARA GENERAR HTML (Copiada de aprobar_minuta.php)
// (INTEGRADO) MODIFICADA: Se añaden los sellos de validación del ST
// -----------------------------------------------------------------------------
function generateMinutaHtml($data, $logoGoreUri, $logoCoreUri, $sellos_st = [], $selloVerdeUri = '')
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


    // --- HTML (Estilos copiados) ---
    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Borrador Minuta ' . $idMinuta . '</title><style>' .
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
        // Estilos de Votación (copiados)
        '.votacion-block{page-break-inside:avoid; margin-bottom:15px; font-size:9pt;}' .
        '.votacion-tabla{width:100%;border-collapse:collapse;margin-top:5px;}' .
        '.votacion-tabla th, .votacion-tabla td{border:1px solid #ccc;padding:4px 6px;}' .
        '.votacion-tabla th{background-color:#f2f2f2;text-align:center;}' .
        '.votacion-detalle{columns:2;-webkit-columns:2;column-gap:20px;padding-left:20px;margin-top:5px;}' .

        // (INTEGRADO) Estilos para firmas y sellos
        '.signature-box-container{width:100%; margin-top:30px; padding-top: 15px; border-top: 1px solid #ccc; page-break-inside:avoid; text-align:center;}' .
        '.firma-chip{width: 220px; border: 1px solid #999; border-radius: 8px; padding: 10px; margin: 5px; display: inline-block; position: relative; overflow: hidden; background: #f9f9f9; font-size: 8pt; text-align: center; vertical-align: top; page-break-inside: avoid; }' .
        '.firma-chip p{ margin: 0; padding: 1px 0; line-height: 1.2; }' .
        '.firma-nombre{ font-weight: bold; }' .
        '.firma-cargo{ font-style: italic; color: #333; }' .
        '.firma-detalle{ font-size: 7pt; color: #555; margin-top: 5px; }' .
        '.firma-fecha{ font-size: 7pt; color: #555; border-top: 1px dashed #ccc; padding-top: 5px; margin-top: 5px; }' .
        '.sello-st-chip{ background-color: #e6ffed; border-color: #5cb85c; }' . // Fondo verde para sellos ST

        // Estilo para el pie de página de borrador
        'footer { position: fixed; bottom: -30px; left: 0px; right: 0px; height: 50px; text-align: center; color: #999; font-size: 9pt; }' .
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

        '<div class="titulo-minuta">BORRADOR DE MINUTA (Para Revisión)</div>' . // Título cambiado
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

    // --- BLOQUE DE VOTACIONES (Copiado de aprobar_minuta.php) ---
    if (!empty($data['votaciones']) && is_array($data['votaciones'])) {
        $html .= '<div class="seccion-titulo">Votaciones Realizadas:</div>';
        foreach ($data['votaciones'] as $votacion) {
            $votosSi = 0;
            $votosNo = 0;
            $votosAbs = 0;
            $listaVotos = ['SI' => [], 'NO' => [], 'ABSTENCION' => []];

            if (!empty($votacion['votos'])) {
                foreach ($votacion['votos'] as $voto) {
                    if ($voto['opcionVoto'] == 'SI') {
                        $votosSi++;
                        $listaVotos['SI'][] = htmlspecialchars($voto['nombreVotante']);
                    } elseif ($voto['opcionVoto'] == 'NO') {
                        $votosNo++;
                        $listaVotos['NO'][] = htmlspecialchars($voto['nombreVotante']);
                    } else {
                        $votosAbs++;
                        $listaVotos['ABSTENCION'][] = htmlspecialchars($voto['nombreVotante']);
                    }
                }
            }

            $html .= '<div class="votacion-block">' .
                '<h4>Votación: ' . htmlspecialchars($votacion['nombreVotacion']) . '</h4>' .
                '<table class="votacion-tabla">' .
                '<thead><tr><th>A Favor (' . $votosSi . ')</th><th>En Contra (' . $votosNo . ')</th><th>Abstención (' . $votosAbs . ')</th></tr></thead>' .
                '<tbody><tr>' .
                '<td style="vertical-align: top;">' . implode('<br>', $listaVotos['SI']) . '</td>' .
                '<td style="vertical-align: top;">' . implode('<br>', $listaVotos['NO']) . '</td>' .
                '<td style="vertical-align: top;">' . implode('<br>', $listaVotos['ABSTENCION']) . '</td>' .
                '</tr></tbody>' .
                '</table>' .
                '</div>';
        }
    }

    // Pie de página de borrador
    $html .= '<footer>Documento Borrador - Pendiente de Aprobación - Generado el ' . date('d-m-Y H:i') . '</footer>';

    // --- (INTEGRADO) BLOQUE DE FIRMAS/SELLOS (AL FINAL, ANTES DE </body>) ---
    $html .= '<div class="signature-box-container">';

    // (NUEVO) Función helper para renderizar CUALQUIER tipo de firma/sello
    $generarChipFirma = function ($nombre, $cargo, $detalle, $fechaHora, $imagenUri, $claseExtra = '') {
        $chipHtml = '<div class="firma-chip ' . $claseExtra . '">';

        // Imagen de fondo (sello)
        if (!empty($imagenUri)) {
            $chipHtml .= '<img src="' . $imagenUri . '" alt="Sello" ' .
                'style="position: absolute; top: 10px; left: 50%; margin-left: -50px; width: 100px; height: auto; opacity: 0.2; z-index: 1;">';
        }

        // (INTEGRADO) Contenido del chip (texto)
        $chipHtml .= '<div style="position: relative; z-index: 2;">'; // Contenedor para el texto
        $chipHtml .= '<p class="firma-nombre">' . htmlspecialchars($nombre) . '</p>';
        $chipHtml .= '<p class="firma-cargo">' . htmlspecialchars($cargo) . '</p>';
        $chipHtml .= '<p class="firma-detalle">' . htmlspecialchars($detalle) . '</p>';
        $chipHtml .= '<p class="firma-fecha">' . htmlspecialchars($fechaHora) . '</p>';
        $chipHtml .= '</div>'; // Cierre del contenedor de texto

        $chipHtml .= '</div>'; // Cierre de .firma-chip
        return $chipHtml;
    };

    // (NUEVO) Renderizar Sellos de Validación ST
    if (!empty($sellos_st) && is_array($sellos_st)) {
        foreach ($sellos_st as $sello) {
            $html .= $generarChipFirma(
                $sello['nombreSecretario'],
                'Secretario Técnico',
                'Validación de Feedback',
                date('d-m-Y H:i:s', strtotime($sello['fechaValidacion'])),
                $selloVerdeUri, // Sello verde
                'sello-st-chip' // Clase CSS para fondo verde
            );
        }
    }

    $html .= '</div>'; // cierre signature-box-container

    $html .= '</body></html>';
    return $html;
}


/* =============================================================================
 🔽 COMIENZA EL "MOTOR" PRINCIPAL DEL SCRIPT 🔽
=============================================================================
*/

try {
    // CORRECCIÓN: Usar conectorDB
    $db = new conectorDB();
    $pdo = $db->getDatabase();
    $minutaModel = new MinutaModel();

    $data_pdf = [];

    // --- 1. CARGAR INFO MINUTA ---
    $sqlMinuta = "SELECT * FROM t_minuta WHERE idMinuta = :id";
    $stmtMinuta = $pdo->prepare($sqlMinuta);
    $stmtMinuta->execute([':id' => $idMinuta]);
    $data_pdf['minuta_info'] = $stmtMinuta->fetch(PDO::FETCH_ASSOC);

    if (!$data_pdf['minuta_info']) {
        throw new Exception('No se encontró la minuta.');
    }

    // --- 2. CARGAR DATOS PARA EL PDF (Asistentes, Temas, Votos, Comisiones) ---
    // (Esta lógica es copiada de aprobar_minuta.php)

    // 2a. CARGAR INFO SECRETARIO
    $sqlSec = $pdo->prepare("SELECT CONCAT(pNombre, ' ', aPaterno) as nombreCompleto FROM t_usuario WHERE tipoUsuario_id IN (2, 6) LIMIT 1");
    $sqlSec->execute();
    $data_pdf['secretario_info'] = $sqlSec->fetch(PDO::FETCH_ASSOC) ?: ['nombreCompleto' => 'Secretario Técnico'];

    // 2b. CARGAR COMISIONES Y PRESIDENTES
    $getDatosComision = function ($idComision) use ($pdo) {
        if (empty($idComision)) return null;
        $sqlCom = $pdo->prepare("SELECT nombreComision, t_usuario_idPresidente FROM t_comision WHERE idComision = :id");
        $sqlCom->execute([':id' => $idComision]);
        $comData = $sqlCom->fetch(PDO::FETCH_ASSOC);
        if (!$comData) return ['nombre' => 'Comisión no encontrada', 'presidente' => 'N/A'];
        $nombrePresidente = 'Presidente no asignado';
        if (!empty($comData['t_usuario_idPresidente'])) {
            $sqlPres = $pdo->prepare("SELECT CONCAT(pNombre, ' ', aPaterno) as nombreCompleto FROM t_usuario WHERE idUsuario = :id");
            $sqlPres->execute([':id' => $comData['t_usuario_idPresidente']]);
            $nombrePresidente = $sqlPres->fetchColumn() ?: $nombrePresidente;
        }
        return ['nombre' => $comData['nombreComision'], 'presidente' => $nombrePresidente];
    };

    $idCom1 = $data_pdf['minuta_info']['t_comision_idComision'];
    $data_pdf['comisiones_info']['com1'] = $getDatosComision($idCom1);

    $sqlMixta = $pdo->prepare("SELECT t_comision_idComision_mixta, t_comision_idComision_mixta2 FROM t_reunion WHERE t_minuta_idMinuta = :idMinuta");
    $sqlMixta->execute([':idMinuta' => $idMinuta]);
    $comisionesMixtas = $sqlMixta->fetch(PDO::FETCH_ASSOC);

    if ($comisionesMixtas) {
        $data_pdf['comisiones_info']['com2'] = $getDatosComision($comisionesMixtas['t_comision_idComision_mixta']);
        $data_pdf['comisiones_info']['com3'] = $getDatosComision($comisionesMixtas['t_comision_idComision_mixta2']);
    }

    // 2c. ASISTENTES
    $sqlAsis = "SELECT CONCAT(u.pNombre, ' ', u.aPaterno) as nombreCompleto 
                 FROM t_asistencia a
                 JOIN t_usuario u ON a.t_usuario_idUsuario = u.idUsuario
                 WHERE a.t_minuta_idMinuta = :id
                 ORDER BY u.aPaterno, u.pNombre";
    $stmtAsis = $pdo->prepare($sqlAsis);
    $stmtAsis->execute([':id' => $idMinuta]);
    $data_pdf['asistentes'] = $stmtAsis->fetchAll(PDO::FETCH_ASSOC);

    // 2d. TEMAS Y ACUERDOS
    $sqlTemas = "SELECT t.idTema, t.nombreTema, t.objetivo, t.compromiso, t.observacion, a.descAcuerdo
                  FROM t_tema t 
                  LEFT JOIN t_acuerdo a ON a.t_tema_idTema = t.idTema
                  WHERE t.t_minuta_idMinuta = :id
                  ORDER BY t.idTema ASC";
    $stmtTemas = $pdo->prepare($sqlTemas);
    $stmtTemas->execute([':id' => $idMinuta]);
    $data_pdf['temas'] = $stmtTemas->fetchAll(PDO::FETCH_ASSOC);

    // =========================================================================
    // --- INICIO DE LA MODIFICACIÓN 1 (Añadir búsqueda de idReunion) ---
    // =========================================================================
    // 2d-bis. OBTENER IDREUNION (para buscar votaciones asociadas a la reunión)
    $sqlReunion = $pdo->prepare("SELECT idReunion FROM t_reunion WHERE t_minuta_idMinuta = :idMinuta LIMIT 1");
    $sqlReunion->execute([':idMinuta' => $idMinuta]);
    $reunion = $sqlReunion->fetch(PDO::FETCH_ASSOC);
    $idReunion = $reunion ? $reunion['idReunion'] : null;
    // =========================================================================
    // --- FIN DE LA MODIFICACIÓN 1 ---
    // =========================================================================


    // =========================================================================
    // --- INICIO DE LA MODIFICACIÓN 2 (Corregir consulta de votaciones) ---
    // =========================================================================
    // 2e. VOTACIONES
    // Buscamos votaciones ligadas a la minuta DIRECTAMENTE o a través de la REUNIÓN
    $sqlVotaciones = $pdo->prepare("SELECT * FROM t_votacion 
                                   WHERE t_minuta_idMinuta = :idMinuta 
                                   OR t_reunion_idReunion = :idReunion");
    $sqlVotaciones->execute([
        ':idMinuta' => $idMinuta,
        ':idReunion' => $idReunion // Usamos el $idReunion que obtuvimos
    ]);
    $data_pdf['votaciones'] = $sqlVotaciones->fetchAll(PDO::FETCH_ASSOC);
    // =========================================================================
    // --- FIN DE LA MODIFICACIÓN 2 ---
    // =========================================================================


    if (!empty($data_pdf['votaciones'])) {
        $sqlVotos = $pdo->prepare("
             SELECT v.t_votacion_idVotacion, v.opcionVoto, CONCAT(u.pNombre, ' ', u.aPaterno) as nombreVotante
             FROM t_voto v
             JOIN t_usuario u ON v.t_usuario_idUsuario = u.idUsuario
             WHERE v.t_votacion_idVotacion = :idVotacion
        ");
        foreach ($data_pdf['votaciones'] as $i => $votacion) {
            // CORRECCIÓN: El idVotacion está en la key 'idVotacion' no en 'idVotacion'
            // (Tu código original estaba correcto, pero lo ajusto para que coincida con el SELECT de t_voto)
            $sqlVotos->execute([':idVotacion' => $votacion['idVotacion']]);
            $data_pdf['votaciones'][$i]['votos'] = $sqlVotos->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    // 2f. (INTEGRADO) Cargar SELLOS del ST
    $sqlSellos = $pdo->prepare("SELECT 
                                     CONCAT(u.pNombre, ' ', u.aPaterno) as nombreSecretario, 
                                     v.fechaValidacion
                                     FROM t_validacion_st v
                                     JOIN t_usuario u ON v.t_usuario_idSecretario = u.idUsuario
                                     WHERE v.t_minuta_idMinuta = :idMinuta
                                     ORDER BY v.fechaValidacion ASC");
    $sqlSellos->execute([':idMinuta' => $idMinuta]);
    $data_pdf['sellos_st'] = $sqlSellos->fetchAll(PDO::FETCH_ASSOC);


    // --- 3. DEFINIR LOGOS ---
    $logoGoreUri = ImageToDataUrl(ROOT_PATH . 'public/img/logo2.png');
    $logoCoreUri = ImageToDataUrl(ROOT_PATH . 'public/img/logoCore1.png');
    // (INTEGRADO) Cargar el sello verde
    $selloVerdeUri = ImageToDataUrl(ROOT_PATH . 'public/img/aprobacion.png');

    // --- 4. GENERAR HTML ---
    // (INTEGRADO) Pasamos los nuevos datos a la función
    $html = generateMinutaHtml($data_pdf, $logoGoreUri, $logoCoreUri, $data_pdf['sellos_st'], $selloVerdeUri);

    // --- 5. INICIALIZAR DOMPDF Y RENDERIZAR ---
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    $options->set('chroot', ROOT_PATH); //
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('letter', 'portrait');
    $dompdf->render();
    // --- INICIO DE CÓDIGO AÑADIDO: GUARDAR BORRADOR ---

    // 1. Definir la ruta de guardado (usando tu constante ROOT_PATH)
    $directorioBorrador = ROOT_PATH . 'public/docs/minutas_borradores/';
    if (!is_dir($directorioBorrador)) {
        mkdir($directorioBorrador, 0777, true);
    }

    // 2. Definir nombre y ruta completa
    $nombreArchivoBorrador = 'Minuta_Borrador_N' . $idMinuta . '.pdf';
    $rutaCompletaBorrador = $directorioBorrador . $nombreArchivoBorrador;

    // 3. Definir la ruta que se guardará en la BD (relativa al root)
    $rutaParaDB_Borrador = 'public/docs/minutas_borradores/' . $nombreArchivoBorrador;

    // 4. Obtener el contenido del PDF
    $output = $dompdf->output();

    // 5. Guardar el archivo en el servidor
    file_put_contents($rutaCompletaBorrador, $output);

    // 6. Actualizar la base de datos (usando el modelo)
    $minutaModel->actualizarPathBorrador($idMinuta, $rutaParaDB_Borrador);

    // --- 6. MOSTRAR EL PDF AL NAVEGADOR ---
    // Limpiamos cualquier salida de buffer anterior
    ob_clean();
    // Mostramos el PDF en el navegador (no se descarga)
    $dompdf->stream("Minuta_Borrador_N" . $idMinuta . ".pdf", ["Attachment" => 0]);
    exit;
} catch (Exception $e) {
    error_log("Error fatal al generar PDF borrador: " . $e->getMessage());
    $dompdf = new Dompdf();
    $dompdf->loadHtml('<h1>Error al generar el PDF</h1><p>' . htmlspecialchars($e->getMessage()) . '</p>');
    $dompdf->setPaper('letter', 'portrait');
    $dompdf->render();
    $dompdf->stream("Error_Minuta_" . $idMinuta . ".pdf", ["Attachment" => 0]);
}
