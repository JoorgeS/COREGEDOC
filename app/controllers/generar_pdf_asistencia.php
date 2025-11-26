<?php
// app/controllers/generar_pdf_asistencia.php

use Dompdf\Dompdf;
use Dompdf\Options;

// Función auxiliar para incrustar imágenes (NECESARIA para logos y sellos)
function ImageToDataUrl(String $filename): String
{
    if (!file_exists($filename)) return '';
    $mime = @mime_content_type($filename);
    if ($mime === false || strpos($mime, 'image/') !== 0) return '';
    $raw_data = @file_get_contents($filename);
    return "data:{$mime};base64," . base64_encode($raw_data);
}


function generarPdfAsistencia($idMinuta, $rutaGuardado, $pdo, $idSecretario, $rootPath)
{

    // 1. Obtener datos

    // a. Datos Minuta, Reunión y Secretario (MODIFICADO)
    $stmt = $pdo->prepare("
        SELECT m.idMinuta, r.fechaInicioReunion, c.nombreComision,
               CONCAT(s.pNombre, ' ', s.aPaterno, ' ', s.aMaterno) as nombreSecretario
        FROM t_minuta m
        JOIN t_reunion r ON m.idMinuta = r.t_minuta_idMinuta
        JOIN t_comision c ON m.t_comision_idComision = c.idComision
        JOIN t_usuario s ON s.idUsuario = :idSecretario
        WHERE m.idMinuta = :idMinuta
    ");
    $stmt->execute([':idMinuta' => $idMinuta, ':idSecretario' => $idSecretario]);
    $minuta = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$minuta) return false;

    // b. Lista COMPLETA de Asistentes (MODIFICADO: LEFT JOIN para incluir AUSENTES)
    $sqlAsis = "
        SELECT 
            CONCAT(u.pNombre, ' ', u.aPaterno, ' ', u.aMaterno) as nombreCompleto,
            a.idAsistencia, 
            a.fechaRegistroAsistencia -- Hora y Fecha de autogestión
        FROM t_usuario u
        LEFT JOIN t_asistencia a ON a.t_usuario_idUsuario = u.idUsuario AND a.t_minuta_idMinuta = :idMinuta
        -- Asegúrate de que los tipos de usuario (1, 3, 7) coincidan con el rol de Consejero/Miembro
        WHERE u.tipoUsuario_id IN (1, 3, 7) AND u.estado = 1 
        ORDER BY u.aPaterno ASC
    ";

    $stmtAsis = $pdo->prepare($sqlAsis);
    $stmtAsis->execute([':idMinuta' => $idMinuta]);
    $asistentes = $stmtAsis->fetchAll(PDO::FETCH_ASSOC);

    $fechaReunion = date('d-m-Y', strtotime($minuta['fechaInicioReunion']));
    $horaReunion = date('H:i', strtotime($minuta['fechaInicioReunion']));
    $fechaValidacion = date('d-m-Y H:i:s');

    // c. Preparar URLs de imágenes (Asume rutas correctas desde $rootPath)
    $logoGore = ImageToDataUrl($rootPath . 'public/img/logo2.png');
    $logoCore = ImageToDataUrl($rootPath . 'public/img/logoCore1.png');
    $selloImg = ImageToDataUrl($rootPath . 'public/img/aprobacion.png');

    // 2. Construir HTML (MODIFICADO COMPLETAMENTE)
    $html = '
    <html>
    <head>
        <style>
            body { font-family: Helvetica, sans-serif; font-size: 10pt; color: #333; }
            .header-table { width: 100%; border-bottom: 2px solid #ccc; margin-bottom: 20px; padding-bottom: 10px; }
            .titulo { text-align: center; font-size: 14pt; font-weight: bold; text-transform: uppercase; margin: 10px 0; }
            .info-box { width: 100%; margin-bottom: 20px; border-collapse: collapse; }
            .info-box td { border: 1px solid #999; padding: 6px; }
            .label { background-color: #f0f0f0; font-weight: bold; width: 25%; }
            .tabla-asistencia { width: 100%; border-collapse: collapse; margin-top: 10px; }
            .tabla-asistencia th { background-color: #e9ecef; padding: 8px; border: 1px solid #999; text-align: left; }
            .tabla-asistencia td { padding: 6px; border: 1px solid #ccc; vertical-align: middle; }
            
            .presente { color: green; font-weight: bold; text-align: center; }
            .ausente { color: red; font-weight: bold; text-align: center; }
            .fecha-registro { font-size: 8pt; color: #666; text-align: center; display: block; margin-top: 2px; }

            .sello-container { text-align: center; margin-top: 40px; }
            .sello-img { width: 120px; opacity: 0.8; }
            .firma-txt { font-weight: bold; margin-top: 5px; font-size: 9pt; }
            .footer { position: fixed; bottom: 20px; left: 0; right: 0; text-align: center; font-size: 8pt; color: #888; border-top: 1px solid #eee; padding-top: 5px; }
            .linea-firma { border-top: 1px solid #333; width: 60%; margin: 5px auto 0; }
        </style>
    </head>
    <body>
        <table class="header-table">
            <tr>
                <td width="15%"><img src="' . $logoGore . '" style="width: 80px;"></td>
                <td width="70%" align="center">
                    <strong>GOBIERNO REGIONAL DE VALPARAÍSO</strong><br>
                    CONSEJO REGIONAL
                </td>
                <td width="15%" align="right"><img src="' . $logoCore . '" style="width: 80px;"></td>
            </tr>
        </table>

        <div class="titulo">Certificado de Asistencia Validada</div>

        <table class="info-box">
            <tr>
                <td class="label">N° Minuta:</td><td>' . $minuta['idMinuta'] . '</td>
                <td class="label">Fecha Reunión:</td><td>' . $fechaReunion . '</td>
            </tr>
            <tr>
                <td class="label">Comisión:</td><td>' . htmlspecialchars($minuta['nombreComision']) . '</td>
                <td class="label">Hora Inicio:</td><td>' . $horaReunion . '</td>
            </tr>
        </table>

        <h3>Detalle de Asistencia</h3>
        <table class="tabla-asistencia">
            <thead>
                <tr>
                    <th>Nombre Consejero(a)</th>
                    <th width="160" style="text-align:center;">Estado</th>
                </tr>
            </thead>
            <tbody>';

    $totalPresentes = 0;
    foreach ($asistentes as $p) {
        if ($p['idAsistencia']) {
            $totalPresentes++;

            // Formatear la fecha y hora de registro de autogestión
            $fechaRegStr = '';
            if (!empty($p['fechaRegistroAsistencia'])) {
                $fechaRegStr = date('d-m-Y H:i', strtotime($p['fechaRegistroAsistencia']));
            }

            $stHtml = '<div class="presente">PRESENTE</div>';
            if ($fechaRegStr) {
                // Se añade la hora y fecha de autogestión
                $stHtml .= '<span class="fecha-registro">(' . $fechaRegStr . ')</span>';
            }
        } else {
            $stHtml = '<div class="ausente">AUSENTE</div>';
        }

        $html .= '<tr>
                    <td>' . htmlspecialchars($p['nombreCompleto']) . '</td>
                    <td>' . $stHtml . '</td>
                  </tr>';
    }

    $html .= '</tbody></table>
        <p style="text-align:right; margin-top:10px;"><strong>Total Asistentes: ' . $totalPresentes . '</strong></p>

        <div class="sello-container" style="margin-top: 50px;">
            <img src="' . $selloImg . '" class="sello-img" style="width: 100px;"><br>
            
                        <p style="margin-top: 15px; font-size: 11pt; font-weight: bold; color: #333;">
                VALIDADO por ' . htmlspecialchars($minuta['nombreSecretario']) . '
            </p>
            <p style="font-size: 10pt; color: #555; margin-top: -8px;">
                Secretario(a) Técnico(a)
            </p>
            <p style="font-size: 9pt; color: #666; margin-top: 5px;">
                Fecha y Hora: ' . $fechaValidacion . '
            </p>
        </div>

        <div class="footer">
            Documento oficial generado el ' . date('d-m-Y H:i:s') . '
        </div>
    </body>
    </html>';

    // 3. Renderizar PDF
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true); // Necesario para cargar imágenes DataURL
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('letter', 'portrait');
    $dompdf->render();

    // 4. Guardar archivo
    $output = $dompdf->output();
    file_put_contents($rutaGuardado, $output);

    return true;
}
