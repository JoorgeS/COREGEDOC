<?php

// 1. Intentar cargar el autoloader de Composer si existe
$autoloadPath = __DIR__ . '/../../vendor/autoload.php';
if (file_exists($autoloadPath) && !class_exists('Dompdf\Dompdf')) {
    require_once $autoloadPath;
}

use Dompdf\Dompdf;
use Dompdf\Options;

// Función segura para convertir imágenes a base64
function ImageToDataUrl(String $filename): String
{
    if (!file_exists($filename)) return '';
    $mime = @mime_content_type($filename);
    if ($mime === false || strpos($mime, 'image/') !== 0) return '';
    $raw_data = @file_get_contents($filename);
    return "data:{$mime};base64," . base64_encode($raw_data);
}

function generarPdfAsistencia($idMinuta, $rutaGuardado, $pdo, $idSecretario, $rootPath, $qrBase64 = null, $hash = null, $urlValidacion = null)
{
    try {
        // ==========================================
        // 1. OBTENER DATOS DE LA BASE DE DATOS
        // ==========================================
        
        // Datos de la Minuta, Reunión, Comisión, Presidente y Secretario
        $stmt = $pdo->prepare("
            SELECT m.idMinuta, 
                   m.fechaMinuta,
                   m.fechaAprobacion, 
                   r.fechaInicioReunion, 
                   r.horaInicioReal,
                   r.fechaTerminoReunion,
                   r.horaTerminoReal,
                   r.nombreReunion,
                   m.nombreComision,
                   m.nombrePresidentes,
                   CONCAT(s.pNombre, ' ', s.aPaterno, ' ', s.aMaterno) as nombreSecretario
            FROM t_minuta m
            LEFT JOIN t_reunion r ON m.idMinuta = r.t_minuta_idMinuta
            LEFT JOIN t_usuario s ON s.idUsuario = :idSecretario
            WHERE m.idMinuta = :idMinuta
        ");
        $stmt->execute([':idMinuta' => $idMinuta, ':idSecretario' => $idSecretario]);
        $minuta = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$minuta) return false;

        // Lista de Asistentes
        $sqlAsis = "
            SELECT 
                CONCAT(u.pNombre, ' ', u.aPaterno, ' ', u.aMaterno) as nombreCompleto,
                a.idAsistencia, 
                a.fechaRegistroAsistencia,
                a.origenAsistencia,
                a.estadoAsistencia
            FROM t_usuario u
            LEFT JOIN t_asistencia a ON a.t_usuario_idUsuario = u.idUsuario AND a.t_minuta_idMinuta = :idMinuta
            WHERE u.tipoUsuario_id IN (1, 3, 7) AND u.estado = 1 
            ORDER BY u.aPaterno ASC
        ";
        $stmtAsis = $pdo->prepare($sqlAsis);
        $stmtAsis->execute([':idMinuta' => $idMinuta]);
        $asistentes = $stmtAsis->fetchAll(PDO::FETCH_ASSOC);

        // ==========================================
        // 2. PREPARAR VARIABLES Y RUTAS
        // ==========================================
        
        // --- LÓGICA DE FECHAS Y HORAS ---
        $fechaRaw = strtotime($minuta['fechaMinuta']); // Usamos fechaMinuta para consistencia
        $meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        $fechaTexto = date('d', $fechaRaw) . ' de ' . $meses[date('n', $fechaRaw) - 1] . ' de ' . date('Y', $fechaRaw);

        // Horas reales (o planificadas si fallan)
        $horaInicio = $minuta['horaInicioReal'] ? date('H:i', strtotime($minuta['horaInicioReal'])) : ($minuta['fechaInicioReunion'] ? date('H:i', strtotime($minuta['fechaInicioReunion'])) : '--:--');
        $horaTermino = $minuta['horaTerminoReal'] ? date('H:i', strtotime($minuta['horaTerminoReal'])) : 'En curso';

        // --- LÓGICA DE ENCABEZADO (FORMATO LISTA) ---
        $formatear = function ($cadena) {
            if (empty($cadena)) return '---';
            // Asumimos que pueden venir separados por '/' o ser un solo string
            $list = explode('/', $cadena); 
            $list = array_map(function ($n) { return mb_strtoupper(trim($n), 'UTF-8'); }, $list);
            $list = array_values(array_unique($list)); // Eliminar duplicados y reindexar
            
            if (count($list) === 1) return $list[0];
            $ultimo = array_pop($list);
            return implode(', ', $list) . ' Y ' . $ultimo;
        };

        $comisionesStr = $formatear($minuta['nombreComision'] ?? '');
        $presidentesStr = $formatear($minuta['nombrePresidentes'] ?? '');
        
        // Labels plural/singular
        $labelComision = (strpos($comisionesStr, ' Y ') !== false) ? "COMISIONES: " : "COMISIÓN: ";
        $labelPresidente = (strpos($presidentesStr, ' Y ') !== false) ? "PRESIDENTES:" : "PRESIDENTE:";

        // Nombre Reunión
        $nombreReunion = mb_strtoupper($minuta['nombreReunion'] ?? '---', 'UTF-8');
        
        // Título del Documento con ID
        $tituloDocumento = "CERTIFICADO DE ASISTENCIA PARA MINUTA N°" . ($minuta['idMinuta'] ?? '---');
        
        // Datos para validación digital
        $nombreSecretario = mb_strtoupper($minuta['nombreSecretario'] ?? 'SECRETARIO TÉCNICO', 'UTF-8');
        $fechaValidacion = date('d/m/Y H:i'); // Fecha de emisión del certificado

        // Rutas absolutas para imágenes
        $logoGore = ImageToDataUrl($rootPath . '/public/img/logo2.png');
        $logoCore = ImageToDataUrl($rootPath . '/public/img/logoCore1.png');
        $aprobacionImg = ImageToDataUrl($rootPath . '/public/img/aprobacion.png'); // Imagen para el sello verde

        // ==========================================
        // 3. CONSTRUIR EL HTML
        // ==========================================
        
        $css = "
            @page { margin: 160px 50px 50px 50px; }
            body { font-family: Helvetica, Arial, sans-serif; font-size: 10pt; color: #333; line-height: 1.4; }
            
            /* HEADER FIJO */
            header { position: fixed; top: -140px; left: 0px; right: 0px; height: 130px; text-align: center; }
            .header-table { width: 100%; border-collapse: collapse; }
            .header-center { text-align: center; color: #000; vertical-align: top; padding-top: 5px; }
            .h-line-1 { font-size: 10pt; font-weight: bold; text-transform: uppercase; margin: 0; }
            .h-line-2 { font-size: 10pt; font-weight: bold; text-transform: uppercase; margin: 2px 0 10px 0; }
            .h-line-dynamic { font-size: 9pt; color: #000; margin-bottom: 4px; line-height: 1.2; text-transform: uppercase; }
            hr.header-sep { border: 0; border-top: 2px solid #0071bc; margin: 5px 0 0 0; }

            .doc-title { text-align: center; font-size: 14pt; font-weight: bold; color: #000; text-transform: uppercase; margin-bottom: 15px; background-color: #f0f0f0; padding: 5px; border: 1px solid #ccc; margin-top: 10px; }

            /* INFO TABLE */
            .info-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 9pt; table-layout: fixed; }
            .info-table td { padding: 4px; border-bottom: 1px solid #eee; vertical-align: top; }
            .lbl { font-weight: bold; color: #000; width: 130px; }
            .val { color: #333; word-wrap: break-word; }

            /* ASISTENCIA TABLE */
            .tabla-asistencia { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 9.5pt; }
            .tabla-asistencia th { background-color: #f9f9f9; padding: 8px; border: 1px solid #ddd; text-align: left; color: #555; }
            .tabla-asistencia td { padding: 6px; border: 1px solid #eee; vertical-align: middle; }
            
            /* COLORES */
            .presente { color: #00a650; font-weight: bold; text-transform: uppercase; }
            .ausente { color: #999; font-weight: bold; text-transform: uppercase; }
            .manual { color: #666; font-size: 7pt; font-style: italic; display:block; }

            /* VALIDACION DIGITAL BOX (VERDE) */
            .validation-wrapper { text-align: center; margin-top: 40px; margin-bottom: 20px; page-break-inside: avoid; }
            .validation-box { 
                display: inline-block; 
                width: 350px; 
                border: 2px solid #28a745; /* Borde Verde */
                background-color: transparent; 
                padding: 15px; 
                text-align: center; 
                border-radius: 8px;
            }
            .val-title { color: #28a745; font-weight: bold; font-size: 11pt; margin-bottom: 5px; text-transform: uppercase; }
            .val-img { width: 60px; margin-bottom: 10px; }
            .val-text { font-size: 10pt; color: #333; margin-bottom: 4px; }
            .val-date { font-size: 9pt; color: #666; font-style: italic; }

            /* FOOTER QR */
            .footer-container { margin-top: 30px; page-break-inside: avoid; width: 100%; text-align: center; }
            .footer-validation-box { background-color: #f4f4f4; border: 1px solid #dcdcdc; border-radius: 6px; padding: 10px; width: 100%; box-sizing: border-box; }
            .footer-content-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
            .qr-cell { width: 90px; vertical-align: middle; text-align: center; padding-right: 15px; border-right: 1px solid #ccc; }
            .info-cell { padding-left: 15px; text-align: left; vertical-align: middle; font-size: 8pt; color: #555; line-height: 1.3; }
            
            .link-validacion { 
                color: #0071bc; 
                text-decoration: none; 
                font-family: monospace; 
                font-size: 7pt; 
                display: block; 
                width: 100%; 
                margin-top: 3px; 
                word-break: break-all; 
                overflow-wrap: break-word; 
            }
        ";

        $html = '
        <html>
        <head>
            <meta charset="UTF-8">
            <style>' . $css . '</style>
        </head>
        <body>
            <header>
                <table class="header-table">
                    <tr>
                        <td width="100" align="left"><img src="' . $logoGore . '" width="70" style="display:block;"></td>
                        <td class="header-center">
                            <div class="h-line-1">GOBIERNO REGIONAL - REGIÓN DE VALPARAÍSO</div>
                            <div class="h-line-2">CONSEJO REGIONAL</div>
                            <div class="h-line-dynamic">' . $labelComision . ' ' . htmlspecialchars($comisionesStr) . '</div>
                            <div class="h-line-dynamic">' . $labelPresidente . ' ' . htmlspecialchars($presidentesStr) . '</div>
                        </td>
                        <td width="130" align="right"><img src="' . $logoCore . '" width="120" style="display:block;"></td>
                    </tr>
                </table>
                <hr class="header-sep">
            </header>

            <div class="doc-title">' . $tituloDocumento . '</div>

            <table class="info-table">
                <tr>
                    <td class="lbl">NOMBRE REUNIÓN:</td>
                    <td class="val"><strong>' . htmlspecialchars($nombreReunion) . '</strong></td>
                </tr>
                <tr>
                    <td class="lbl">FECHA:</td>
                    <td class="val">' . $fechaTexto . '</td>
                </tr>
                <tr>
                    <td class="lbl">HORA INICIO:</td>
                    <td class="val">' . $horaInicio . ' hrs.</td>
                </tr>
                <tr>
                    <td class="lbl">HORA TÉRMINO:</td>
                    <td class="val">' . $horaTermino . ' hrs.</td>
                </tr>
                <tr>
                    <td class="lbl">LUGAR:</td>
                    <td class="val">Sala de plenos</td>
                </tr>
            </table>

            <h3>Detalle de Asistencia</h3>
            <table class="tabla-asistencia">
                <thead>
                    <tr>
                        <th>Consejero Regional</th>
                        <th width="120" style="text-align:center;">Estado</th>
                        <th width="100" style="text-align:center;">Hora Registro</th>
                    </tr>
                </thead>
                <tbody>';

        $totalPresentes = 0;
        foreach ($asistentes as $p) {
            $estado = '<span class="ausente">AUSENTE</span>';
            $hora = '-';
            
            if ($p['idAsistencia'] && $p['estadoAsistencia'] === 'PRESENTE') {
                $totalPresentes++;
                $estado = '<span class="presente">PRESENTE</span>';
                if($p['origenAsistencia'] === 'SECRETARIO') {
                    $estado .= '<span class="manual">(Manual)</span>';
                }
                
                if (!empty($p['fechaRegistroAsistencia'])) {
                    $hora = date('H:i', strtotime($p['fechaRegistroAsistencia']));
                }
            }

            $html .= '<tr>
                        <td>' . htmlspecialchars($p['nombreCompleto']) . '</td>
                        <td align="center">' . $estado . '</td>
                        <td align="center">' . $hora . '</td>
                      </tr>';
        }

        $html .= '</tbody></table>
            <div style="text-align:right; margin-top:10px; font-size:9pt; color:#555;">Total Asistentes: <strong>' . $totalPresentes . '</strong></div>

            <!-- VALIDACIÓN DIGITAL CON SECRETARIO -->
            <div class="validation-wrapper">
                <div class="validation-box">
                    <img src="' . $aprobacionImg . '" class="val-img">
                    <div class="val-title">VALIDADO DIGITALMENTE</div>
                    <div class="val-text"><strong>' . htmlspecialchars($nombreSecretario) . '</strong></div>
                    <div class="val-date">Certificado emitido el: ' . $fechaValidacion . '</div>
                </div>
            </div>

            <!-- FOOTER CON QR -->
            <div class="footer-container">
                <div class="footer-validation-box">
                    <table class="footer-content-table">
                        <tr>
                            <td class="qr-cell">';
                            
        if ($qrBase64) {
            $html .= '<img src="' . $qrBase64 . '" width="80">';
        }

        $url = $urlValidacion ?? '#';

        $html .= '          </td>
                            <td class="info-cell">
                                <strong>VALIDACIÓN DE AUTENTICIDAD</strong><br>
                                Este documento certifica la asistencia oficial.<br>
                                Valide en: 
                                <a href="' . $url . '" class="link-validacion" target="_blank">' . $url . '</a>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

        </body>
        </html>';

        // ==========================================
        // 4. GENERAR ARCHIVO Y CANVAS (Paginación)
        // ==========================================
        
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Helvetica');
        
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();

        // --- INYECCIÓN DE PIE DE PÁGINA (Paginación y Texto Fijo) ---
        $canvas = $dompdf->getCanvas();
        $w = $canvas->get_width();
        $h = $canvas->get_height();

        $fontMetrics = $dompdf->getFontMetrics();
        $font = $fontMetrics->getFont("Helvetica", "normal");
        $size = 8;
        $color = array(0.33, 0.33, 0.33);

        $y = $h - 40; // Altura del footer

        // Texto centrado: "Documento generado por COREGEDOC"
        // Le agregamos la fecha actual para referencia si quieres, si no, solo el texto
        $textoCentro = "Documento generado por COREGEDOC"; 
        
        $anchoTexto = $fontMetrics->getTextWidth($textoCentro, $font, $size);
        $xCentro = ($w - $anchoTexto) / 2;
        $canvas->page_text($xCentro, $y, $textoCentro, $font, $size, $color);

        // Numeración: "Página X / Y"
        $textPagina = "Página {PAGE_NUM} / {PAGE_COUNT}";
        $xPagina = $w - 80; // A la derecha
        $canvas->page_text($xPagina, $y, $textPagina, $font, $size, $color);

        // --- GUARDADO ---
        $output = $dompdf->output();
        $guardado = file_put_contents($rutaGuardado, $output);

        return ($guardado !== false);

    } catch (Exception $e) {
        file_put_contents($rutaGuardado, "Error: " . $e->getMessage());
        return false; 
    }
}
?>