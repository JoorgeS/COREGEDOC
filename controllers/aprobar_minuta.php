<?php
// controllers/aprobar_minuta.php
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. INCLUIR DEPENDENCIAS Y CONFIGURACIÓN
// -----------------------------------------------------------------------------
// Ruta raíz del proyecto (subiendo un nivel desde controllers)
define('ROOT_PATH', dirname(__DIR__) . '/');

require_once ROOT_PATH . 'class/class.conectorDB.php';
require_once ROOT_PATH . 'vendor/autoload.php'; // Autoload de Dompdf

use Dompdf\Dompdf;
use Dompdf\Options;

// 2. OBTENER DATOS DE ENTRADA Y SESIÓN
// -----------------------------------------------------------------------------
$data = json_decode(file_get_contents('php://input'), true);
$idMinuta = $data['idMinuta'] ?? null;
$idPresidenteLogueado = $_SESSION['idUsuario'] ?? null;
$nombrePresidenteLogueado = trim(($_SESSION['pNombre'] ?? '') . ' ' . ($_SESSION['aPaterno'] ?? '')); // Nombre para el PDF

if (!$idMinuta || !$idPresidenteLogueado) {
    echo json_encode(['status' => 'error', 'message' => 'Datos incompletos o sesión no válida.']);
    exit;
}

// 3. FUNCIÓN PARA GENERAR HTML 
// -----------------------------------------------------------------------------
function generateMinutaHtml($data, $logo_uri)
{
    // Mapear variables a nombres más cortos para la plantilla
    $comision1 = htmlspecialchars($data['nombreComision1'] ?? 'N/A');
    $comision2 = htmlspecialchars($data['nombreComision2'] ?? '');
    
    // El HTML usa $comision2 para determinar si la sección mixta debe mostrarse.
    $comisionMixta = !empty($comision2); 

    $comisiones_display = $comision1;
    if ($comisionMixta) {
        $comisiones_display .= ' / ' . $comision2;
    }

    // Estilos CSS integrados
    $styles = '
        body { font-family: Helvetica, sans-serif; margin: 0; padding: 0; font-size: 10pt; line-height: 1.5; }
        .container { width: 90%; margin: 20px auto; }
        .header-box { border-bottom: 1px solid #ccc; padding-bottom: 15px; margin-bottom: 15px; display: block; overflow: hidden; }
        .logo { width: 60px; height: auto; float: left; margin-right: 15px; }
        .header-text { float: left; width: calc(100% - 75px); }
        .header-text p { margin: 0; font-size: 9pt; line-height: 1.2; color: #555; }
        .header-text .main-title { font-weight: bold; font-size: 11pt; color: #000; }
        .minuta-info { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 10pt; }
        .minuta-info th, .minuta-info td { border: 1px solid #ccc; padding: 6px 10px; text-align: left; }
        .minuta-info th { background-color: #eee; width: 30%; font-weight: bold; }
        h2 { font-size: 14pt; margin-top: 25px; margin-bottom: 10px; color: #004d40; }
        h3 { font-size: 11pt; margin-top: 15px; border-bottom: 2px solid #ddd; padding-bottom: 3px; color: #333; font-weight: bold; }
        .content p { margin: 5px 0 10px 0; }
        .asistencia-list ul { list-style-type: disc; padding-left: 20px; margin: 10px 0; columns: 2; }
        .asistencia-list li { margin-bottom: 3px; font-size: 10pt; }
        .signature-box { margin-top: 50px; text-align: center; }
        .signature-line { border-top: 1px solid #000; width: 50%; margin: 30px auto 5px auto; }
    ';

    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Minuta Aprobada CORE - ' . $comisiones_display . '</title>
        <style>' . $styles . '</style>
    </head>
    <body>
    <div class="container">
        
        <div class="header-box">
            <img src="' . htmlspecialchars($logo_uri) . '" class="logo" alt="Logo CORE">
            <div class="header-text">
                <p>GOBIERNO REGIONAL. REGIÓN DE VALPARAÍSO</p>
                <p class="main-title">CONSEJO REGIONAL</p>
                <p>COMISIÓN ' . strtoupper($comisiones_display) . '</p>
            </div>
        </div>

        <table class="minuta-info">
            <tr>
                <th>MINUTA DE REUNIÓN</th>
                <td colspan="3">APROBADA</td>
            </tr>
            <tr>
                <th>Fecha</th>
                <td>' . htmlspecialchars($data['fechaMinuta'] ?? 'N/A') . '</td>
                <th>Hora</th>
                <td>' . htmlspecialchars($data['horaMinuta'] ?? 'N/A') . '</td>
            </tr>
            <tr>
                <th>Presidente</th>
                <td>' . htmlspecialchars($data['nombrePresidente1'] ?? 'N/A') . '</td>
                <th>Secretario Técnico</th>
                <td>' . htmlspecialchars($data['nombreSecretario'] ?? 'N/A') . '</td>
            </tr>
            <tr>
                <th>N° Sesión</th>
                <td>' . htmlspecialchars($data['numeroSesion'] ?? 'N/A') . '</td>
                <th>Lugar</th>
                <td>' . htmlspecialchars($data['lugarReunion'] ?? 'Salón de Plenarios') . '</td>
            </tr>
            ' . (($comisionMixta) ? '
            <tr>
                <th>Comisión Mixta</th>
                <td>' . $comision2 . '</td>
                <th>Presidente Mixta</th>
                <td>' . htmlspecialchars($data['nombrePresidente2'] ?? 'N/A') . '</td>
            </tr>' : '') . '
        </table>

        <h2>ASISTENTES</h2>
        <div class="asistencia-list">
            <ul>';

    // LÓGICA DE ASISTENCIA
    if (!empty($data['asistentes']) && is_array($data['asistentes'])) {
        foreach ($data['asistentes'] as $asistente) {
            $html .= '<li>' . htmlspecialchars($asistente['nombreCompleto']) . '</li>';
        }
    } else {
        $html .= '<li>No se registraron asistentes.</li>';
    }

    $html .= '</ul>
        </div>';

    // --- Desarrollo de la Minuta (Temas) ---
    $html .= '<h2>DESARROLLO DE LA MINUTA</h2>';

    $temas = $data['temas'] ?? [];
    if (empty($temas) || (count($temas) == 1 && empty(strip_tags($temas[0]['nombreTema'] ?? '')))) {
        $html .= '<p>No hay temas registrados para el desarrollo de la minuta.</p>';
    } else {
        foreach ($temas as $index => $tema) {
            $num = $index + 1;
            
            if (empty(strip_tags($tema['nombreTema'] ?? ''))) continue;

            $html .= '
            <div class="tema-block">
                <h3>TEMA ' . $num . ': ' . strip_tags($tema['nombreTema'] ?? '') . '</h3>
                <div class="content">';

            if (!empty($tema['objetivo'])) {
                $html .= '<p><strong>OBJETIVO:</strong><br>' . ($tema['objetivo']) . '</p>';
            }
            if (!empty($tema['descAcuerdo'])) {
                $html .= '<p><strong>ACUERDOS ADOPTADOS:</strong><br>' . ($tema['descAcuerdo']) . '</p>';
            }
            if (!empty($tema['compromiso'])) {
                $html .= '<p><strong>COMPROMISOS Y RESPONSABLES:</strong><br>' . ($tema['compromiso']) . '</p>';
            }
            if (!empty($tema['observacion'])) {
                $html .= '<p><strong>OBSERVACIONES Y COMENTARIOS:</strong><br>' . ($tema['observacion']) . '</p>';
            }

            $html .= '</div>
            </div>';
        }
    }

    // --- SECCIONES ACUERDOS GENERALES Y VARIOS ELIMINADAS ---


    // --- FIRMA ---
    $html .= '
        <div class="signature-box">
            <div class="signature-line"></div>
            <p>' . htmlspecialchars($data['nombrePresidente1'] ?? 'N/A') . '</p>
            <p>Presidente</p>
            <p>Comisión ' . $comisiones_display . '</p>
        </div>
    </div>
    </body>
    </html>';

    return $html;
}


// 4. LÓGICA PRINCIPAL (DENTRO DE TRY...CATCH)
// -----------------------------------------------------------------------------
$db = null;
$pdfWebPath = null;
$idDocumentoCreado = null;

try {
    $db = new conectorDB();
    $pdo = $db->getDatabase();
    $pdo->beginTransaction();

    // 4.1. VERIFICAR PERMISOS (Presidente asignado)
    $sql_check = "SELECT t_usuario_idPresidente FROM t_minuta WHERE idMinuta = :idMinuta";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute([':idMinuta' => $idMinuta]);
    $minuta = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if (!$minuta || (int)$minuta['t_usuario_idPresidente'] !== (int)$idPresidenteLogueado) {
        throw new Exception('No tiene permisos para firmar esta minuta.');
    }

    // 4.2. OBTENER TODOS LOS DATOS PARA EL PDF
    $datosParaPDF = [];

    // Datos básicos de la minuta
    // Se seleccionan solo las columnas existentes en la tabla t_minuta
    $sql_minuta_data = "SELECT m.fechaMinuta, m.horaMinuta, 
                        m.t_comision_idComision, m.t_usuario_idPresidente
                        FROM t_minuta m
                        WHERE m.idMinuta = :idMinuta";
    $stmt_minuta_data = $pdo->prepare($sql_minuta_data);
    $stmt_minuta_data->execute([':idMinuta' => $idMinuta]);
    $minutaData = $stmt_minuta_data->fetch(PDO::FETCH_ASSOC);
    if (!$minutaData) throw new Exception('Minuta no encontrada.');
    $datosParaPDF = array_merge($datosParaPDF, $minutaData);

    // ASIGNACIONES SOLICITADAS Y CORRECTAS:
    $datosParaPDF['numeroSesion'] = $idMinuta; // Solicitado: N° Sesión = ID de Minuta
    $datosParaPDF['lugarReunion'] = 'Salón de Plenarios'; // Solicitado: Lugar fijo
    
    // Variables de sesión
    $datosParaPDF['nombreSecretario'] = $nombrePresidenteLogueado; 
    
    // Se inicializan las variables de comisión mixta (si no existen en la DB)
    $datosParaPDF['nombreComision2'] = '';
    $datosParaPDF['nombrePresidente2'] = '';

    // Comisión 1 (Principal)
    $sql_com1 = "SELECT nombreComision FROM t_comision WHERE idComision = :id";
    $stmt_com1 = $pdo->prepare($sql_com1);
    $stmt_com1->execute([':id' => $datosParaPDF['t_comision_idComision']]);
    $datosParaPDF['nombreComision1'] = $stmt_com1->fetchColumn();

    // Presidente 1 (Principal)
    $sql_pres1 = "SELECT CONCAT(pNombre, ' ', aPaterno) FROM t_usuario WHERE idUsuario = :id";
    $stmt_pres1 = $pdo->prepare($sql_pres1);
    $stmt_pres1->execute([':id' => $datosParaPDF['t_usuario_idPresidente']]);
    $datosParaPDF['nombrePresidente1'] = $stmt_pres1->fetchColumn();
    
    // Lista de Asistentes (Nombres)
    $sql_asist = "SELECT CONCAT(u.pNombre, ' ', u.aPaterno) as nombreCompleto
                  FROM t_asistencia a JOIN t_usuario u ON a.t_usuario_idUsuario = u.idUsuario
                  WHERE a.t_minuta_idMinuta = :idMinuta ORDER BY u.aPaterno, u.pNombre";
    $stmt_asist = $pdo->prepare($sql_asist);
    $stmt_asist->execute([':idMinuta' => $idMinuta]);
    $datosParaPDF['asistentes'] = $stmt_asist->fetchAll(PDO::FETCH_ASSOC);

    // Lista de Temas (con Acuerdos)
    $sql_temas = "SELECT t.nombreTema, t.objetivo, t.compromiso, t.observacion, a.descAcuerdo
                  FROM t_tema t LEFT JOIN t_acuerdo a ON a.t_tema_idTema = t.idTema
                  WHERE t.t_minuta_idMinuta = :idMinuta ORDER BY t.idTema ASC";
    $stmt_temas = $pdo->prepare($sql_temas);
    $stmt_temas->execute([':idMinuta' => $idMinuta]);
    $datosParaPDF['temas'] = $stmt_temas->fetchAll(PDO::FETCH_ASSOC);

    // 4.3. ACTUALIZAR ESTADO DE LA MINUTA
    $sql_update_status = "UPDATE t_minuta
                          SET estadoMinuta = 'APROBADA',
                              fechaAprobacion = NOW()
                          WHERE idMinuta = :idMinuta AND estadoMinuta = 'PENDIENTE'"; 
    $stmt_update_status = $pdo->prepare($sql_update_status);
    $exito_status = $stmt_update_status->execute([':idMinuta' => $idMinuta]);

    if (!$exito_status || $stmt_update_status->rowCount() == 0) {
        throw new Exception('No se pudo actualizar el estado de la minuta o ya estaba aprobada.');
    }

    // 4.4. GENERAR Y GUARDAR EL PDF
    $options = new Options();
    $options->set('defaultFont', 'Helvetica');
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    $options->set('chroot', ROOT_PATH); 
    $options->set('isPhpEnabled', true);

    $dompdf = new Dompdf($options);

    $logo_relative_path = 'public/img/logo2.png';

    // Generar HTML usando la función y los datos recolectados
    $htmlContent = generateMinutaHtml($datosParaPDF, $logo_relative_path); 
    $dompdf->loadHtml($htmlContent);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    // Definir dónde guardar y el nombre del archivo
    $saveDir = ROOT_PATH . 'public/docs/minutas_aprobadas/'; 
    $filename = "minuta_aprobada_" . $idMinuta . "_" . date('Ymd_His') . ".pdf";
    $fullSavePath = $saveDir . $filename;
    $pdfWebPath = "/corevota/public/docs/minutas_aprobadas/" . $filename; 

    // Asegurarse de que el directorio exista
    if (!is_dir($saveDir)) {
        if (!mkdir($saveDir, 0775, true)) { 
            throw new Exception('No se pudo crear el directorio para guardar PDFs: ' . $saveDir);
        }
    }

    // Guardar el PDF en el servidor
    if (file_put_contents($fullSavePath, $dompdf->output()) === false) {
        throw new Exception('No se pudo guardar el archivo PDF en: ' . $fullSavePath);
    }

    // 4.5. INSERTAR REGISTRO EN T_DOCUMENTO
    $sql_doc = "INSERT INTO t_documento (nombreArchivo, pathArchivo, fechaCreacion, tipoDocumento, t_usuario_idCreador)
                VALUES (:nombre, :path, NOW(), 'MINUTA_APROBADA', :idCreador)";
    $stmt_doc = $pdo->prepare($sql_doc);
    $stmt_doc->execute([
        ':nombre' => $filename,
        ':path' => $pdfWebPath, 
        ':idCreador' => $idPresidenteLogueado 
    ]);
    $idDocumentoCreado = $pdo->lastInsertId();
    if (!$idDocumentoCreado) throw new Exception('No se pudo crear el registro en t_documento.');


    // 4.6. ACTUALIZAR T_MINUTA con pathArchivo y t_documento_idFijo
    $sql_update_links = "UPDATE t_minuta
                         SET pathArchivo = :pathPDF,
                             t_documento_idFijo = :idDoc
                         WHERE idMinuta = :idMinuta";
    $stmt_update_links = $pdo->prepare($sql_update_links);
    $exito_links = $stmt_update_links->execute([
        ':pathPDF' => $pdfWebPath,
        ':idDoc'   => $idDocumentoCreado,
        ':idMinuta' => $idMinuta
    ]);

    if (!$exito_links) throw new Exception('No se pudo actualizar t_minuta con la ruta del PDF y el ID del documento.');

    // 4.7. COMMIT FINAL
    $pdo->commit();

    // Respuesta de éxito
    echo json_encode(['status' => 'success', 'message' => 'Minuta aprobada y PDF generado con éxito.', 'pdfPath' => $pdfWebPath]);
} catch (Exception $e) {
    // ROLLBACK si algo falló
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // Loggear el error detallado
    error_log("Error en aprobar_minuta.php: " . $e->getMessage());
    // Devolver un mensaje de error genérico al frontend
    echo json_encode(['status' => 'error', 'message' => 'Ocurrió un error al aprobar la minuta: ' . $e->getMessage()]); 
} finally {
    $pdo = null;
    $db = null;
}