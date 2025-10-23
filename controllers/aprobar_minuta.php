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
// FUNCIÓN ImageToDataUrl (Sin cambios, necesaria para logos)
// -----------------------------------------------------------------------------
function ImageToDataUrl(String $filename): String {
    // Verificar si el archivo existe
    if (!file_exists($filename)) {
        error_log("ImageToDataUrl Error: File not found at " . $filename);
        return ''; // Retornar vacío si no se encuentra
    }
    $mime = mime_content_type($filename);
    if ($mime === false || strpos($mime, 'image/') !== 0) {
        error_log("ImageToDataUrl Error: Illegal MIME type for " . $filename . " (Type: " . $mime . ")");
        return '';
    }
    $raw_data = file_get_contents($filename);
    if ($raw_data === false || empty($raw_data)) {
        error_log("ImageToDataUrl Error: File not readable or empty at " . $filename);
        return '';
    }
    return "data:{$mime};base64," . base64_encode($raw_data);
}

// -----------------------------------------------------------------------------
// FUNCIÓN PARA GENERAR HTML (¡MODIFICADA!)
// -----------------------------------------------------------------------------
function generateMinutaHtml($data, $logoGoreUri, $logoCoreUri) {
    
    // --- Preparar datos del encabezado ---
    $idMinuta = htmlspecialchars($data['minuta_info']['idMinuta'] ?? 'N/A');
    $fecha = htmlspecialchars(date('d-m-Y', strtotime($data['minuta_info']['fechaMinuta'] ?? 'now')));
    $hora = htmlspecialchars(date('H:i', strtotime($data['minuta_info']['horaMinuta'] ?? 'now')));
    $secretario = htmlspecialchars($data['secretario_info']['nombreCompleto'] ?? 'N/A');
    
    // Nombres de comisiones y presidentes (ya vienen formateados desde PHP)
    $comision1_nombre = htmlspecialchars($data['comisiones_info']['com1']['nombre'] ?? 'N/A');
    $presidente1_nombre = htmlspecialchars($data['comisiones_info']['com1']['presidente'] ?? 'N/A');
    $comision2_nombre = htmlspecialchars($data['comisiones_info']['com2']['nombre'] ?? null);
    $presidente2_nombre = htmlspecialchars($data['comisiones_info']['com2']['presidente'] ?? null);
    $comision3_nombre = htmlspecialchars($data['comisiones_info']['com3']['nombre'] ?? null);
    $presidente3_nombre = htmlspecialchars($data['comisiones_info']['com3']['presidente'] ?? null);
    
    $esMixta = $comision2_nombre || $comision3_nombre; // Determinar si es mixta para el formato

    // Construir título de comisiones para header GORE
    $tituloComisionesHeader = $comision1_nombre;
    if($comision2_nombre) $tituloComisionesHeader .= " / " . $comision2_nombre;
    if($comision3_nombre) $tituloComisionesHeader .= " / " . $comision3_nombre;

    // --- Inicio HTML ---
    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Minuta Aprobada '.$idMinuta.'</title><style>' .
        // Estilos (simplificados, ajusta según necesidad)
        'body{font-family:Helvetica,sans-serif;font-size:10pt;line-height:1.4;}' .
        '.header{margin-bottom:20px;overflow:hidden; border-bottom: 1px solid #ccc; padding-bottom: 10px;}' .
        '.logo-left{float:left;width:70px;height:auto; margin-top: 5px;}' .
        '.logo-right{float:right;width:100px;height:auto;}' .
        '.header-center{text-align:center;margin:0 110px 0 80px;}' .
        '.header-center p{margin:0;padding:0;font-size:9pt;font-weight:bold;}' .
        '.header-center .consejo{font-size:10pt;}' .
        '.titulo-minuta{text-align:center;font-weight:bold;font-size:12pt;margin:20px 0 15px 0;text-decoration:underline;}' .
        '.info-tabla{width:100%;border-collapse:collapse;margin-bottom:20px;font-size:9pt;}' .
        '.info-tabla td{padding:4px 8px;border:1px solid #ccc; vertical-align: top;}' .
        '.info-tabla .label{font-weight:bold;width:150px;background-color:#f2f2f2;}' . // Ancho fijo para etiquetas
        '.seccion-titulo{font-weight:bold;font-size:11pt;margin:25px 0 8px 0;text-decoration:underline; page-break-after: avoid;}' .
        '.asistentes-lista ul{list-style:disc; padding-left: 20px; margin:5px 0; columns:2;-webkit-columns:2; column-gap: 30px;}' .
        '.asistentes-lista li{margin-bottom:3px;font-size:9pt;line-height:1.2; page-break-inside: avoid;}' .
        '.desarrollo-tema{margin-bottom:15px;font-size:10pt;text-align:justify; page-break-inside: avoid;}' .
        '.desarrollo-tema h4{font-size:10pt;font-weight:bold;margin:10px 0 3px 0; background-color:#f2f2f2; padding: 3px 5px; border: 1px solid #ddd;}' .
        '.desarrollo-tema div{margin:0 0 8px 5px; padding-left: 5px; border-left: 2px solid #eee;}' .
        '.desarrollo-tema strong { display: block; margin-bottom: 2px; font-size: 9pt; color: #555;}' .
        '.signature-box{margin-top:50px;text-align:center; page-break-inside: avoid;}' .
        '.signature-line{border-top:1px solid #000;width:50%;margin:30px auto 5px auto;}' .
        '</style></head><body>' .
        
        // --- Encabezado GORE / CORE ---
        '<div class="header">' .
        ($logoGoreUri ? '<img src="' . htmlspecialchars($logoGoreUri) . '" class="logo-left" alt="Logo GORE">' : '') .
        ($logoCoreUri ? '<img src="' . htmlspecialchars($logoCoreUri) . '" class="logo-right" alt="Logo CORE">' : '') .
        '<div class="header-center">' .
        '<p>GOBIERNO REGIONAL. REGIÓN DE VALPARAÍSO</p>' .
        '<p class="consejo">CONSEJO REGIONAL</p>' .
        '<p>COMISIÓN(ES): ' . strtoupper($tituloComisionesHeader) . '</p>' . // Mostrar todas las comisiones
        //'<p>PRESIDENTE: ' . strtoupper($presidente1_nombre) . '</p>' . // Opcional: Mostrar solo el principal aquí?
        '</div>' .
        '</div>' .
        
        // --- Título y Tabla de Información ---
        '<div class="titulo-minuta">MINUTA REUNIÓN</div>' .
        '<table class="info-tabla">' .
        '<tr><td class="label">N° Sesión:</td><td>' . $idMinuta . '</td><td class="label">Secretario T.:</td><td>' . $secretario . '</td></tr>' .
        '<tr><td class="label">Fecha:</td><td>' . $fecha . '</td><td class="label">Hora:</td><td>' . $hora . '</td></tr>';

    // --- Mostrar Comisiones y Presidentes ---
    if (!$esMixta) { // Caso Única Comisión
        $html .= '<tr><td class="label">Comisión:</td><td>' . $comision1_nombre . '</td><td class="label">Presidente:</td><td>' . $presidente1_nombre . '</td></tr>';
    } else { // Caso Mixta/Conjunta
        $html .= '<tr><td class="label">1° Comisión:</td><td>' . $comision1_nombre . '</td><td class="label">1° Presidente:</td><td>' . $presidente1_nombre . '</td></tr>';
        if ($comision2_nombre) {
             $html .= '<tr><td class="label">2° Comisión:</td><td>' . $comision2_nombre . '</td><td class="label">2° Presidente:</td><td>' . $presidente2_nombre . '</td></tr>';
        }
        if ($comision3_nombre) {
             $html .= '<tr><td class="label">3° Comisión:</td><td>' . $comision3_nombre . '</td><td class="label">3° Presidente:</td><td>' . $presidente3_nombre . '</td></tr>';
        }
    }
    $html .= '</table>';

    // --- Asistentes ---
    $html .= '<div class="seccion-titulo">Asistentes:</div><div class="asistentes-lista"><ul>';
    if (!empty($data['asistentes']) && is_array($data['asistentes'])) {
        foreach ($data['asistentes'] as $asistente) {
            $html .= '<li>' . htmlspecialchars($asistente['nombreCompleto']) . '</li>';
        }
    } else {
        $html .= '<li>No se registraron asistentes.</li>';
    }
    $html .= '</ul></div>';
    
    // --- Tabla de la Sesión (Temas Resumidos) ---
     $html .= '<div class="seccion-titulo">Tabla de la sesión:</div><div><ol style="font-size: 9pt; padding-left: 20px;">';
     $temasExisten = false;
    if (!empty($data['temas']) && is_array($data['temas'])) {
        foreach ($data['temas'] as $tema) {
            $nombreTemaLimpio = trim(strip_tags($tema['nombreTema'] ?? ''));
            if (!empty($nombreTemaLimpio)) {
                 $html .= '<li>' . $nombreTemaLimpio . '</li>';
                 $temasExisten = true;
            }
        }
    } 
    if (!$temasExisten) {
         $html .= '<li>No se definieron temas específicos.</li>';
    }
    $html .= '</ol></div>';


    // --- Desarrollo por Tema ---
    $html .= '<div class="seccion-titulo">Desarrollo / Acuerdos / Compromisos:</div>';
     $temasExisten = false;
    if (!empty($data['temas']) && is_array($data['temas'])) {
        foreach ($data['temas'] as $index => $tema) {
             $nombreTemaLimpio = trim(strip_tags($tema['nombreTema'] ?? ''));
             if (empty($nombreTemaLimpio)) continue; // Saltar temas sin nombre
             $temasExisten = true;

             $html .= '<div class="desarrollo-tema"><h4>TEMA ' . ($index + 1) . ': ' . $nombreTemaLimpio . '</h4>';
            
             // Mostrar secciones solo si tienen contenido
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
            
             $html .= '</div>'; // Cierre desarrollo-tema
        }
    } 
    if (!$temasExisten){
        $html .= '<p style="font-size:10pt;">No hay detalles registrados para los temas.</p>';
    }

    // --- Firma (Solo del presidente principal) ---
    $html .= '<div class="signature-box"><div class="signature-line"></div><p>' . $presidente1_nombre . '</p><p>Presidente</p><p>Comisión ' . $comision1_nombre . '</p></div>'; // Firma del presidente principal
    
    $html .= '</body></html>';
    return $html;
}

// -----------------------------------------------------------------------------
// FUNCIÓN PARA OBTENER DATOS COMPLETOS (¡MODIFICADA!)
// -----------------------------------------------------------------------------
function getMinutaCompletaParaPDF($pdo, $idMinuta) {
    $datos = [
        'minuta_info' => null,
        'comisiones_info' => ['com1' => null, 'com2' => null, 'com3' => null],
        'secretario_info' => null,
        'asistentes' => [],
        'temas' => []
    ];

    // 1. Datos básicos de Minuta y Reunión (incluyendo IDs de comisiones)
    $sql_base = "SELECT 
                    m.idMinuta, m.fechaMinuta, m.horaMinuta, 
                    r.t_comision_idComision, /* Principal */
                    r.t_comision_idComision_mixta, /* Segunda */
                    r.t_comision_idComision_mixta2 /* Tercera */
                 FROM t_minuta m
                 LEFT JOIN t_reunion r ON m.idMinuta = r.t_minuta_idMinuta
                 WHERE m.idMinuta = :idMinuta";
    $stmt_base = $pdo->prepare($sql_base);
    $stmt_base->execute([':idMinuta' => $idMinuta]);
    $datos['minuta_info'] = $stmt_base->fetch(PDO::FETCH_ASSOC);
    
    if (!$datos['minuta_info']) {
        throw new Exception("Minuta base no encontrada para ID: $idMinuta");
    }

    // 2. Obtener nombres de Comisiones y Presidentes
    $ids_comisiones_a_buscar = array_filter([
        $datos['minuta_info']['t_comision_idComision'],
        $datos['minuta_info']['t_comision_idComision_mixta'],
        $datos['minuta_info']['t_comision_idComision_mixta2']
    ]);

    if (!empty($ids_comisiones_a_buscar)) {
        $placeholders = implode(',', array_fill(0, count($ids_comisiones_a_buscar), '?'));
        $sql_com_pres = "SELECT 
                            c.idComision, c.nombreComision, c.t_usuario_idPresidente,
                            u.pNombre, u.aPaterno
                         FROM t_comision c
                         LEFT JOIN t_usuario u ON c.t_usuario_idPresidente = u.idUsuario
                         WHERE c.idComision IN ($placeholders)";
        $stmt_com_pres = $pdo->prepare($sql_com_pres);
        $stmt_com_pres->execute($ids_comisiones_a_buscar);
        $comisiones_con_presidente = $stmt_com_pres->fetchAll(PDO::FETCH_ASSOC | PDO::FETCH_UNIQUE); // Indexar por idComision
        
        // Asignar a $datos['comisiones_info']
        $map = [
            'com1' => $datos['minuta_info']['t_comision_idComision'],
            'com2' => $datos['minuta_info']['t_comision_idComision_mixta'],
            'com3' => $datos['minuta_info']['t_comision_idComision_mixta2']
        ];
        foreach($map as $key => $idCom) {
            if ($idCom && isset($comisiones_con_presidente[$idCom])) {
                 $com_data = $comisiones_con_presidente[$idCom];
                 $datos['comisiones_info'][$key] = [
                     'nombre' => $com_data['nombreComision'],
                     'presidente' => trim(($com_data['pNombre'] ?? '') . ' ' . ($com_data['aPaterno'] ?? '')) ?: 'No Asignado'
                 ];
            }
        }
    }

    // 3. Obtener Secretario Técnico (Primer usuario tipo 2 o 6)
    $sql_sec = "SELECT CONCAT(pNombre, ' ', aPaterno) as nombreCompleto FROM t_usuario 
                WHERE tipoUsuario_id IN (2, 6) LIMIT 1"; // Tipos 2=Secretario, 6=Admin
    $stmt_sec = $pdo->query($sql_sec);
    $datos['secretario_info'] = $stmt_sec->fetch(PDO::FETCH_ASSOC);

    // 4. Obtener Asistentes (Consejeros tipo 1)
    $sql_asist = "SELECT CONCAT(u.pNombre, ' ', u.aPaterno) as nombreCompleto 
                  FROM t_asistencia a JOIN t_usuario u ON a.t_usuario_idUsuario = u.idUsuario 
                  WHERE a.t_minuta_idMinuta = :idMinuta AND u.tipoUsuario_id = 1
                  ORDER BY u.aPaterno, u.pNombre";
    $stmt_asist = $pdo->prepare($sql_asist);
    $stmt_asist->execute([':idMinuta' => $idMinuta]);
    $datos['asistentes'] = $stmt_asist->fetchAll(PDO::FETCH_ASSOC);

    // 5. Obtener Temas y Acuerdos
    $sql_temas = "SELECT t.nombreTema, t.objetivo, t.compromiso, t.observacion, a.descAcuerdo 
                  FROM t_tema t LEFT JOIN t_acuerdo a ON a.t_tema_idTema = t.idTema 
                  WHERE t.t_minuta_idMinuta = :idMinuta ORDER BY t.idTema ASC";
    $stmt_temas = $pdo->prepare($sql_temas);
    $stmt_temas->execute([':idMinuta' => $idMinuta]);
    $datos['temas'] = $stmt_temas->fetchAll(PDO::FETCH_ASSOC);

    return $datos;
}


// -----------------------------------------------------------------------------
// LÓGICA PRINCIPAL DEL SCRIPT
// -----------------------------------------------------------------------------
$db = null;
$pdfWebPath = null;
$idDocumentoCreado = null;

try {
    $db = new conectorDB();
    $pdo = $db->getDatabase();
    $pdo->beginTransaction();

    // 1. VERIFICAR PERMISOS (Usuario logueado debe ser el presidente guardado en t_minuta)
    $sql_check = "SELECT t_usuario_idPresidente FROM t_minuta 
                  WHERE idMinuta = :idMinuta AND estadoMinuta = 'PENDIENTE'"; // Solo aprobar pendientes
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute([':idMinuta' => $idMinuta]);
    $minuta = $stmt_check->fetch(PDO::FETCH_ASSOC);
    
    if (!$minuta) {
         throw new Exception('Minuta no encontrada o ya está aprobada.');
    }
    if ((int)$minuta['t_usuario_idPresidente'] !== (int)$idUsuarioLogueado) {
        throw new Exception('No tiene permisos para firmar esta minuta (solo el presidente asignado).');
    }

    // 2. OBTENER DATOS COMPLETOS PARA EL PDF (Usando la nueva función)
    $datosParaPDF = getMinutaCompletaParaPDF($pdo, $idMinuta);

    // 3. CONVERTIR LOGOS A DATA URI (Asegúrate que las rutas sean correctas)
    $logoGoreFilesystemPath = ROOT_PATH . 'public/img/logo2.png'; 
    $logoCoreFilesystemPath = ROOT_PATH . 'public/img/logoCore1.png'; 
    $logoGoreDataUri = '';
    $logoCoreDataUri = '';
    try {
        $logoGoreDataUri = ImageToDataUrl($logoGoreFilesystemPath);
        $logoCoreDataUri = ImageToDataUrl($logoCoreFilesystemPath);
    } catch (Exception $e) {
        error_log("Error convirtiendo logos a Data URI para Minuta $idMinuta: " . $e->getMessage());
        // Continuar sin logos si fallan
    }

    // 4. GENERAR HTML
    $htmlContent = generateMinutaHtml($datosParaPDF, $logoGoreDataUri, $logoCoreDataUri);

    // 5. GENERAR Y GUARDAR EL PDF FÍSICO
    $options = new Options();
    $options->set('defaultFont', 'Helvetica');
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', false); // No necesario con Data URIs
    // $options->set('chroot', ROOT_PATH); // No necesario con Data URIs
    $options->set('isPhpEnabled', true); // Por si acaso
    $dompdf = new Dompdf($options);

    $dompdf->loadHtml($htmlContent);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    $saveDir = ROOT_PATH . 'public/docs/minutas_aprobadas/';
    $filename = "minuta_aprobada_" . $idMinuta . "_" . date('Ymd_His') . ".pdf";
    $fullSavePath = $saveDir . $filename;
    $pdfWebPath = "/corevota/public/docs/minutas_aprobadas/" . $filename;

    if (!is_dir($saveDir)) {
        if (!mkdir($saveDir, 0775, true)) {
            throw new Exception('No se pudo crear directorio para guardar PDF.');
        }
    }
    if (file_put_contents($fullSavePath, $dompdf->output()) === false) {
        throw new Exception('No se pudo guardar el archivo PDF en el servidor.');
    }

    // 6. INSERTAR EN T_DOCUMENTO
    $sql_doc = "INSERT INTO t_documento (nombreArchivo, pathArchivo, fechaCreacion, tipoDocumento, t_usuario_idCreador) 
                VALUES (:nombre, :path, NOW(), 'MINUTA_APROBADA', :idCreador)";
    $stmt_doc = $pdo->prepare($sql_doc);
    $stmt_doc->execute([':nombre' => $filename, ':path' => $pdfWebPath, ':idCreador' => $idUsuarioLogueado]);
    $idDocumentoCreado = $pdo->lastInsertId();
    if (!$idDocumentoCreado) throw new Exception('No se pudo crear el registro en t_documento.');

    // 7. ACTUALIZAR T_MINUTA (Marcar como aprobada y vincular documento)
    $sql_update_minuta = "UPDATE t_minuta SET 
                            estadoMinuta = 'APROBADA', 
                            fechaAprobacion = NOW(),
                            pathArchivo = :pathPDF, 
                            t_documento_idFijo = :idDoc 
                          WHERE idMinuta = :idMinuta AND estadoMinuta = 'PENDIENTE'"; // Doble check
    $stmt_update_minuta = $pdo->prepare($sql_update_minuta);
    $exito_update = $stmt_update_minuta->execute([
        ':pathPDF' => $pdfWebPath, 
        ':idDoc' => $idDocumentoCreado, 
        ':idMinuta' => $idMinuta
    ]);
    if (!$exito_update || $stmt_update_minuta->rowCount() == 0) {
       throw new Exception('No se pudo actualizar el estado final de la minuta.');
    }

    // 8. COMMIT FINAL
    $pdo->commit();

    echo json_encode(['status' => 'success', 'message' => 'Minuta aprobada y PDF generado correctamente.', 'pdfPath' => $pdfWebPath]);

} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error crítico en aprobar_minuta.php (Minuta ID: $idMinuta): " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Error al procesar la aprobación: ' . $e->getMessage()]); // Mostrar error detallado por ahora
} finally {
    // Cerrar conexión si es necesario (depende de tu clase BaseConexion)
    $pdo = null; 
    $db = null;
}
?>