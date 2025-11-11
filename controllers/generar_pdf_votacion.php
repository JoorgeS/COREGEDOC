<?php
// controllers/generar_pdf_votacion.php
// Script para generar el PDF de resultados de una votación.

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../class/class.conectorDB.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$idVotacion = $_GET['idVotacion'] ?? 0;

if ($idVotacion <= 0) {
    die("ID de Votación inválido.");
}

$db = new conectorDB();
$pdo = $db->getDatabase();

try {
    // 1. OBTENER DATOS PRINCIPALES DE LA VOTACIÓN
    $sqlData = "
        SELECT 
            v.nombreVotacion, 
            c.nombreComision, 
            m.fechaMinuta, 
            r.nombreReunion, 
            m.idMinuta
        FROM 
            t_votacion v
        LEFT JOIN t_minuta m ON v.t_minuta_idMinuta = m.idMinuta
        
        -- 💡 CORRECCIÓN: Usar la FK de la reunión que apunta a la minuta (r.t_minuta_idMinuta)
        LEFT JOIN t_reunion r ON r.t_minuta_idMinuta = m.idMinuta 
        
        LEFT JOIN t_comision c ON v.idComision = c.idComision
        WHERE v.idVotacion = :idVotacion
    ";

    $stmtData = $pdo->prepare($sqlData);
    $stmtData->execute([':idVotacion' => $idVotacion]);
    $data = $stmtData->fetch(PDO::FETCH_ASSOC);

    if (!$data) {
        die("No se encontraron datos para la votación ID " . $idVotacion);
    }

    // 2. OBTENER EL DETALLE DE LOS VOTOS
    $sqlVotos = "
        SELECT 
            UPPER(tv.opcionVoto) as opcionVoto, 
            CONCAT(u.pNombre, ' ', u.aPaterno) as nombreCompleto
        FROM 
            t_voto tv
        LEFT JOIN t_usuario u ON tv.t_usuario_idUsuario = u.idUsuario
        WHERE tv.t_votacion_idVotacion = :idVotacion
        ORDER BY tv.opcionVoto DESC, u.aPaterno ASC
    ";

    $stmtVotos = $pdo->prepare($sqlVotos);
    $stmtVotos->execute([':idVotacion' => $idVotacion]);
    $votos = $stmtVotos->fetchAll(PDO::FETCH_ASSOC);

    // Organizar votos
    $votosOrganizados = ['SI' => [], 'NO' => [], 'ABSTENCION' => []];
    foreach ($votos as $voto) {
        $opcion = $voto['opcionVoto'];
        $votosOrganizados[$opcion][] = htmlspecialchars($voto['nombreCompleto']);
    }
    $votosTotales = count($votos);


    // 3. GENERAR HTML PARA EL PDF
    $html = '<!DOCTYPE html><html><head>
                <style>
                    body { font-family: sans-serif; margin: 0; padding: 0; }
                    .header { text-align: center; margin-bottom: 25px; padding-bottom: 10px; border-bottom: 3px solid #ccc; }
                    .header h1 { color: #0d6efd; font-size: 24px; margin: 5px 0; }
                    .info-table { width: 100%; border: none; font-size: 14px; margin-bottom: 20px; }
                    .info-table td { padding: 5px 0; }
                    .info-table .label { font-weight: bold; width: 25%; }
                    .votos-resumen { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                    .votos-resumen th, .votos-resumen td { border: 1px solid #ddd; padding: 10px; text-align: center; }
                    .votos-resumen th { background-color: #f8f9fa; font-weight: bold; }
                    .votos-detalle { margin-top: 25px; }
                    .columna-voto { width: 33%; float: left; padding: 5px; box-sizing: border-box; }
                    .columna-voto h4 { margin-top: 0; font-size: 16px; margin-bottom: 5px; }
                    .votos-list { list-style: disc; padding-left: 20px; margin: 0; }
                    .votos-list li { margin-bottom: 3px; font-size: 13px; }
                    .clearfix::after { content: ""; clear: both; display: table; }
                </style>
            </head><body>';

    $html .= '<div class="header">
                <img src="' . __DIR__ . '/../public/img/logoCore1.png" style="width: 80px; margin-bottom: 10px;"> 
                <h1>Reporte Oficial de Votación</h1>
            </div>';

    $html .= '<table class="info-table">
                <tr><td class="label">ID Minuta:</td><td>#' . htmlspecialchars($data['idMinuta'] ?? 'N/A') . '</td></tr>
                <tr><td class="label">Reunión:</td><td>' . htmlspecialchars($data['nombreReunion'] ?? 'N/A') . '</td></tr>
                <tr><td class="label">Comisión:</td><td>' . htmlspecialchars($data['nombreComision'] ?? 'N/A') . '</td></tr>
                <tr><td class="label">Fecha Reunión:</td><td>' . date('d-m-Y', strtotime($data['fechaMinuta'] ?? 'now')) . '</td></tr>
                <tr><td class="label"><strong>Votación:</strong></td><td><strong>' . htmlspecialchars($data['nombreVotacion']) . '</strong></td></tr>
            </table>';

    $html .= '<h3>Resumen de Resultados</h3>';
    $html .= '<table class="votos-resumen">
                <thead>
                    <tr>
                        <th style="color: green;">SÍ</th>
                        <th style="color: red;">NO</th>
                        <th style="color: gray;">ABSTENCIÓN</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>' . count($votosOrganizados['SI']) . '</strong></td>
                        <td><strong>' . count($votosOrganizados['NO']) . '</strong></td>
                        <td><strong>' . count($votosOrganizados['ABSTENCION']) . '</strong></td>
                    </tr>
                </tbody>
            </table>';

    $html .= '<p><strong>Total de Votos Emitidos:</strong> ' . $votosTotales . '</p>';

    $html .= '<h3>Detalle de Votos por Consejero</h3>';
    $html .= '<div class="votos-detalle clearfix">';

    // Votos a favor
    $html .= '<div class="columna-voto">
                <h4 style="color: green;">SÍ</h4>
                <ul class="votos-list"><li>' . (count($votosOrganizados['SI']) > 0 ? implode('</li><li>', $votosOrganizados['SI']) : 'Ninguno') . '</li></ul>
             </div>';

    // Votos en contra
    $html .= '<div class="columna-voto">
                <h4 style="color: red;">NO</h4>
                <ul class="votos-list"><li>' . (count($votosOrganizados['NO']) > 0 ? implode('</li><li>', $votosOrganizados['NO']) : 'Ninguno') . '</li></ul>
             </div>';

    // Abstenciones
    $html .= '<div class="columna-voto">
                <h4 style="color: gray;">ABSTENCIÓN</h4>
                <ul class="votos-list"><li>' . (count($votosOrganizados['ABSTENCION']) > 0 ? implode('</li><li>', $votosOrganizados['ABSTENCION']) : 'Ninguno') . '</li></ul>
             </div>';

    $html .= '</div>';
    $html .= '</body></html>';

    // 4. CONFIGURAR Y GENERAR DOMPDF
    $options = new Options();
    // Reemplaza 'DejaVuSans' si estás usando una fuente diferente o si tienes problemas de caracteres especiales (tildes, ñ)
    $options->set('defaultFont', 'DejaVuSans');
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    // 5. ENVIAR PDF AL NAVEGADOR
    $nombreArchivo = 'Votacion_' . $data['idMinuta'] . '_ID' . $idVotacion . '_' . date('Ymd_His') . '.pdf';
    $dompdf->stream($nombreArchivo, ['Attachment' => true]);
} catch (Exception $e) {
    error_log("Error al generar PDF de votación: " . $e->getMessage());
    die("Error al generar el PDF de resultados: " . htmlspecialchars($e->getMessage()));
}
