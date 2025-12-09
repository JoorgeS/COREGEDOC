<?php



namespace App\Services;



use Dompdf\Dompdf;

use Dompdf\Options;

use Endroid\QrCode\QrCode;

use Endroid\QrCode\Writer\PngWriter;

use Endroid\QrCode\Encoding\Encoding;

use Endroid\QrCode\ErrorCorrectionLevel;

use Endroid\QrCode\RoundBlockSizeMode;



class PdfService

{

    // --- GENERADORES DE IMÁGENES Y QR (Sin cambios) ---



    public function generarQrBase64($url)

    {

        try {

            $qrCode = new QrCode(

                data: $url,

                encoding: new Encoding('UTF-8'),

                errorCorrectionLevel: ErrorCorrectionLevel::Low,

                size: 400,

                margin: 0,

                roundBlockSizeMode: RoundBlockSizeMode::Margin

            );



            $writer = new PngWriter();

            $result = $writer->write($qrCode);



            return $result->getDataUri();
        } catch (\Exception $e) {

            error_log("Error generando QR: " . $e->getMessage());

            return '';
        }
    }



    public function imageToDataUrl($filename)

    {

        if (!file_exists($filename)) return '';

        $mime = mime_content_type($filename);

        $data = file_get_contents($filename);

        if ($data === false) return '';

        return 'data:' . $mime . ';base64,' . base64_encode($data);
    }



    // --- MÉTODOS PÚBLICOS ---



    public function generarPdfFinal($data, $rutaGuardado)

    {

        return $this->renderizarPdf($data, $rutaGuardado, false);
    }



    public function generarPdfBorrador($data, $rutaGuardado)

    {

        return $this->renderizarPdf($data, $rutaGuardado, true);
    }



    // --- RENDERIZADO ---



    private function renderizarPdf($data, $rutaGuardado, $esBorrador)

    {

        // 1. Imágenes

        $baseImg = __DIR__ . '/../../public/img/';



        $logoGore = $this->imageToDataUrl($baseImg . 'logo2.png');

        $logoCore = $this->imageToDataUrl($baseImg . 'logoCore1.png');

        $firmaImg = $this->imageToDataUrl($baseImg . 'firmadigital.png');



        // NUEVA IMAGEN: Aprobación (Sello Verde)

        // Nota: Asumo que la imagen se llamará 'aprobacion.png' y es un PNG

        $aprobacionImg = $this->imageToDataUrl($baseImg . 'aprobacion.png');



        // 2. QR

        $url = $data['urlValidacion'] ?? '#';

        $qrBase64 = $this->generarQrBase64($url);



        // 3. HTML (Pasamos la nueva imagen al template)

        $html = $this->getHtmlTemplate($data, $logoGore, $logoCore, $firmaImg, $aprobacionImg, $qrBase64, $esBorrador);



        // 4. Dompdf Config

        $options = new Options();

        $options->set('isHtml5ParserEnabled', true);

        $options->set('isRemoteEnabled', true);

        $options->set('defaultFont', 'Helvetica');



        $dompdf = new Dompdf($options);

        $dompdf->loadHtml($html);

        $dompdf->setPaper('letter', 'portrait');



        // Renderizamos primero para calcular las páginas

        $dompdf->render();



        // --- INYECCIÓN DE PIE DE PÁGINA (CANVAS) ---

        $canvas = $dompdf->getCanvas();

        $w = $canvas->get_width();

        $h = $canvas->get_height();



        $fontMetrics = $dompdf->getFontMetrics();

        $font = $fontMetrics->getFont("Helvetica", "normal");

        $size = 8;

        $color = array(0.33, 0.33, 0.33);



        $y = $h - 40;



        // Texto centrado (Fecha y Hora actual para el control de versión impresa)

        $textoCentro = "Documento generado por COREGEDOC el " . date('d/m/Y H:i');



        $anchoTexto = $fontMetrics->getTextWidth($textoCentro, $font, $size);

        $xCentro = ($w - $anchoTexto) / 2;

        $canvas->page_text($xCentro, $y, $textoCentro, $font, $size, $color);



        // Numeración

        $textPagina = "Página {PAGE_NUM} / {PAGE_COUNT}";

        $xPagina = $w - 80;

        $canvas->page_text($xPagina, $y, $textPagina, $font, $size, $color);



        $guardado = file_put_contents($rutaGuardado, $dompdf->output());



        return ($guardado !== false);
    }


    public function generarPdfReporteAsistencia($data, $rutaGuardado)
    {
        // 1. Recursos
        $baseImg = __DIR__ . '/../../public/img/';
        $logoGore = $this->imageToDataUrl($baseImg . 'logo2.png');
        $logoCore = $this->imageToDataUrl($baseImg . 'logoCore1.png');
        $firmaImg = $this->imageToDataUrl($baseImg . 'aprobacion.png');

        // 2. HTML (Ya no pasamos el QR)
        $html = $this->getReporteHtmlTemplate($data, $logoGore, $logoCore, $firmaImg);

        // 3. Render
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('letter', 'portrait');

        $dompdf->render();

        // 4. Pie de Página (Numeración)
        $canvas = $dompdf->getCanvas();
        $w = $canvas->get_width();
        $h = $canvas->get_height();
        $fontMetrics = $dompdf->getFontMetrics();
        $font = $fontMetrics->getFont("Helvetica", "normal");
        $size = 8;
        $color = array(0.4, 0.4, 0.4);

        $texto = "Página {PAGE_NUM} de {PAGE_COUNT}  |  Generado el " . date('d/m/Y H:i');
        $canvas->page_text($w - 220, $h - 30, $texto, $font, $size, $color);

        $guardado = file_put_contents($rutaGuardado, $dompdf->output());
        return ($guardado !== false);
    }
    private function getReporteHtmlTemplate($data, $logoGore, $logoCore, $firmaImg)
    {
        $reuniones = $data['registros'];

        $css = "
            @page { margin: 120px 40px 60px 40px; }
            body { font-family: Helvetica, Arial, sans-serif; color: #333; font-size: 10pt; line-height: 1.3; }
            
            /* Clases de utilidad */
            .text-center { text-align: center; }
            .text-right { text-align: right; }
            .text-left { text-align: left; }
            
            header { position: fixed; top: -100px; left: 0px; right: 0px; height: 90px; border-bottom: 2px solid #0071bc; padding-bottom: 5px; }
            .header-tbl { width: 100%; }
            .header-center { text-align: center; }
            .h-title { color: #0071bc; font-weight: bold; font-size: 13pt; text-transform: uppercase; margin: 0; }
            .h-sub { color: #666; font-size: 9pt; margin-top: 2px; }

            .meeting-container { margin-bottom: 30px; page-break-inside: avoid; border: 1px solid #ddd; border-radius: 4px; overflow: hidden; }
            .meeting-header { background-color: #f4f6f9; padding: 10px 15px; border-bottom: 1px solid #ddd; }
            
            .mt-date-row { color: #e87b00; font-weight: bold; font-size: 9pt; text-transform: uppercase; margin-bottom: 4px; }
            .mt-title { font-weight: bold; color: #0071bc; font-size: 11pt; margin-bottom: 2px; }
            .mt-comision { font-size: 9pt; color: #555; font-style: italic; }

            .asist-table { width: 100%; border-collapse: collapse; font-size: 9pt; }
            
            /* Encabezados con fondo gris suave para delimitar columnas */
            .asist-table th { background-color: #f8f9fa; border-bottom: 2px solid #ddd; padding: 6px 10px; color: #555; font-size: 8pt; text-transform: uppercase; font-weight: bold; }
            
            .asist-table td { border-bottom: 1px solid #eee; padding: 6px 10px; vertical-align: middle; }
            .row-alt { background-color: #fcfcfc; }

            /* BADGES DE ESTADO (Ancho fijo para alinear mejor) */
            .tag-origen { 
                font-size: 7pt; 
                font-weight: bold; 
                padding: 3px 6px; 
                border-radius: 3px; 
                text-transform: uppercase;
                display: inline-block;
                width: 110px; /* Ancho fijo para que se vean todos iguales */
                text-align: center;
            }
            .tag-app { color: #198754; border: 1px solid #198754; background-color: #f0fff4; }
            .tag-manual { color: #6c757d; border: 1px solid #6c757d; background-color: #f8f9fa; }

            .tag-atrasado { color: #b58900; font-weight: bold; font-size: 7pt; margin-left: 5px; border: 1px solid #b58900; padding: 1px 3px; border-radius: 3px; }
            .time-tag { font-weight: bold; color: #0071bc; font-size: 9pt; font-family: monospace; } /* Monospace para alinear números */

            .footer-val { margin-top: 40px; border-top: 1px dashed #ccc; padding-top: 15px; }
        ";

        ob_start();
?>
        <!DOCTYPE html>
        <html lang="es">

        <head>
            <meta charset="UTF-8">
            <style>
                <?= $css ?>
            </style>
        </head>

        <body>
            <header>
                <table class="header-tbl">
                    <tr>
                        <td width="70"><img src="<?= $logoGore ?>" width="50"></td>
                        <td class="header-center">
                            <div class="h-title"><?= $data['titulo'] ?></div>
                            <div class="h-sub"><?= $data['rango'] ?></div>
                        </td>
                        <td width="90" align="right"><img src="<?= $logoCore ?>" width="80"></td>
                    </tr>
                </table>
            </header>

            <?php if (empty($reuniones)): ?>
                <div style="text-align:center; padding:50px; color:#777;">Sin registros.</div>
            <?php else: ?>

                <?php foreach ($reuniones as $reu):
                    $presentes = array_filter($reu['asistentes'], function ($a) {
                        $st = strtoupper($a['estado']);
                        return ($st === 'PRESENTE' || $st === 'ATRASADO');
                    });
                    $total = count($presentes);
                ?>
                    <?php if ($total > 0): ?>
                        <div class="meeting-container">
                            <div class="meeting-header">
                                <div style="float:right; text-align:right;">
                                    <span style="font-weight:bold; color:#333; font-size:9pt;">INICIO: <?= $reu['hora_inicio'] ?> HRS.</span>
                                    <div style="font-size:8pt; color:#666; margin-top:2px;">Asistentes: <strong><?= $total ?></strong></div>
                                </div>
                                <div class="mt-date-row"><?= $reu['fecha_texto'] ?></div>
                                <div class="mt-title"><?= htmlspecialchars($reu['titulo']) ?></div>
                                <div class="mt-comision">COMISIÓN: <?= htmlspecialchars($reu['comision']) ?></div>
                            </div>

                            <table class="asist-table">
                                <thead>
                                    <tr>
                                        <th width="50%" class="text-left">Consejero Regional</th>
                                        <th width="30%" class="text-center">Tipo Registro</th>
                                        <th width="20%" class="text-center">Hora</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $i = 0;
                                    foreach ($presentes as $asist):
                                        $i++;
                                        $bgClass = ($i % 2 == 0) ? 'row-alt' : '';

                                        $origenRaw = $asist['origen'];
                                        $esApp = (stripos($origenRaw, 'Autogestión') !== false || stripos($origenRaw, 'App') !== false);

                                        $textoLabel = $esApp ? 'AUTOGESTIÓN' : 'SECRETARIO TÉC.';
                                        $claseLabel = $esApp ? 'tag-app' : 'tag-manual';
                                    ?>
                                        <tr class="<?= $bgClass ?>">
                                            <td class="text-left">
                                                <strong><?= htmlspecialchars($asist['nombre']) ?></strong>
                                                <?php if ($asist['atrasado']): ?>
                                                    <span class="tag-atrasado">ATRASADO</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <span class="tag-origen <?= $claseLabel ?>">
                                                    <?= $textoLabel ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <span class="time-tag"><?= $asist['hora'] ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>

            <?php endif; ?>

            <div class="footer-val">
                <div align="center">
                    <div style="border:1px solid #28a745; border-radius:5px; padding:10px; width:300px; background-color:#f9fff9; margin: 0 auto;">
                        <div style="color:#28a745; font-weight:bold; font-size:9pt; text-transform:uppercase;">Documento Oficial de Asistencia</div>
                        <img src="<?= $firmaImg ?>" width="50" style="margin:5px 0; opacity:0.8;">
                        <div style="font-size:10pt; font-weight:bold;"><?= htmlspecialchars($data['generado_por']) ?></div>
                        <div style="font-size:8pt; color:#555;">COREGEDOC</div>
                        <div style="font-size:7pt; color:#999; margin-top:3px;">
                            Fecha de emisión: <?= date('d/m/Y H:i') ?>
                        </div>
                    </div>
                </div>
            </div>
        </body>

        </html>
    <?php
        return ob_get_clean();
    }
    private function getHtmlTemplate($data, $logoGore, $logoCore, $firmaImg, $aprobacionImg, $qrBase64, $esBorrador)
    {
        $m = $data['minuta_info'];
        $firmas = $data['firmas_aprobadas'] ?? [];
        $temas = $data['temas'] ?? [];
        $asistencia = $data['asistencia'] ?? [];
        $votaciones = $data['votaciones'] ?? [];

        // DATOS PARA VALIDACIÓN DIGITAL (BORRADOR)
        $nombreSecretario = $data['secretario'] ?? 'Secretario Técnico';
        
        $fechaBase = $m['fechaMinuta'] ?? date('Y-m-d');
        $horaFin   = $m['horaTerminoReal'] ?? date('H:i');
        $fechaEnvio = date('d/m/Y H:i', strtotime("$fechaBase $horaFin"));

        $idMinuta = $m['idMinuta'] ?? '---';
        $nombreReunion = isset($m['nombreReunion']) ? mb_strtoupper($m['nombreReunion'], 'UTF-8') : '---';

        $fechaRaw = strtotime($m['fechaMinuta']);
        $meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        $fechaTexto = date('d', $fechaRaw) . ' de ' . $meses[date('n', $fechaRaw) - 1] . ' de ' . date('Y', $fechaRaw);

        $formatear = function ($list) {
            if (empty($list)) return '---';
            $list = array_map(function ($n) {
                return mb_strtoupper(trim($n), 'UTF-8');
            }, $list);
            $list = array_values(array_unique($list));
            if (count($list) === 1) return $list[0];
            $ultimo = array_pop($list);
            return implode(', ', $list) . ' Y ' . $ultimo;
        };

        // Procesar nombres de comisiones para el encabezado
        $nombresCom = [];
        if (!empty($data['comisiones_info']) && is_array($data['comisiones_info'])) {
            $nombresCom = array_column($data['comisiones_info'], 'nombre');
        } elseif (!empty($m['nombreComision'])) {
            $nombresCom = explode('/', $m['nombreComision']);
        }

        $labelComision = (count($nombresCom) > 1) ? "COMISIONES: " : "COMISIÓN: ";
        $comisionesStr = $formatear($nombresCom);

        // Procesar nombres de presidentes para el encabezado
        $nombresPres = [];
        if (!empty($data['presidentes_info']) && is_array($data['presidentes_info'])) {
            $nombresPres = array_column($data['presidentes_info'], 'nombre');
        } elseif (!empty($m['nombrePresidentes'])) {
            $nombresPres = explode('/', $m['nombrePresidentes']);
        }

        $labelPresidente = (count($nombresPres) > 1) ? "PRESIDENTES:" : "PRESIDENTE:";
        $presidentesStr = $formatear($nombresPres);

        $hash = $m['hashValidacion'] ?? '---';
        $tituloDocumento = $esBorrador ? "MINUTA DE REUNIÓN (BORRADOR)" : "MINUTA DE REUNIÓN N° $idMinuta";

        $css = "
            @page { margin: 160px 50px 50px 50px; }
            body { font-family: Helvetica, Arial, sans-serif; font-size: 10pt; color: #333; line-height: 1.4; }
            .watermark { position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-45deg); font-size: 80pt; font-weight: bold; color: rgba(95, 93, 93, 0.08); z-index: 9999; text-transform: uppercase; text-align: center; width: 100%; pointer-events: none; }
            
            header { position: fixed; top: -140px; left: 0px; right: 0px; height: 130px; text-align: center; }

            .header-table { width: 100%; border-collapse: collapse; }
            .header-center { text-align: center; color: #000; vertical-align: top; padding-top: 5px; }
            .h-line-1 { font-size: 10pt; font-weight: bold; text-transform: uppercase; margin: 0; }
            .h-line-2 { font-size: 10pt; font-weight: bold; text-transform: uppercase; margin: 2px 0 10px 0; }
            .h-line-dynamic { font-size: 9pt; color: #000; margin-bottom: 4px; line-height: 1.2; text-transform: uppercase; }
            hr.header-sep { border: 0; border-top: 2px solid #0071bc; margin: 5px 0 0 0; }

            .doc-title { text-align: center; font-size: 14pt; font-weight: bold; color: #000; text-transform: uppercase; margin-bottom: 15px; background-color: #f0f0f0; padding: 5px; border: 1px solid #ccc; margin-top: 10px; }
            
            .tabla-sesion-box { border: 1px solid #ddd; background-color: #f9f9f9; padding: 10px; margin-bottom: 20px; border-radius: 4px; page-break-inside: avoid; }
            .tabla-sesion-title { font-weight: bold; font-size: 10pt; text-decoration: underline; margin-bottom: 8px; color: #000; }
            .tabla-item { font-size: 9.5pt; margin-bottom: 3px; }

            .info-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 9pt; table-layout: fixed; }
            .info-table td { padding: 4px; border-bottom: 1px solid #eee; vertical-align: top; }
            .lbl { font-weight: bold; color: #000; width: 130px; }
            .val { color: #333; word-wrap: break-word; }
            
            h3.sec-title { font-size: 11pt; font-weight: bold; margin-top: 20px; margin-bottom: 10px; color: #0071bc; border-bottom: 1px solid #0071bc; padding-bottom: 3px; text-transform: uppercase; }
            
            .asist-table { width: 100%; border-collapse: collapse; font-size: 9pt; }
            .asist-table td { width: 33%; padding: 3px; }
            
            .tema-block { margin-bottom: 20px; page-break-inside: avoid; border: 1px solid #e0e0e0; border-radius: 4px; overflow: hidden; }
            .tema-header { background-color: #f5f5f5; padding: 5px 10px; font-weight: bold; border-bottom: 1px solid #ddd; font-size: 10pt; }
            .tema-body { padding: 10px; }
            .tema-field { margin-bottom: 8px; }
            .tema-lbl { font-weight: bold; font-size: 9pt; color: #555; display: block; margin-bottom: 2px; }
            .tema-val { display: block; text-align: justify; font-size: 9.5pt; }
            
            .votacion-box { border: 1px solid #ccc; padding: 10px; margin-bottom: 15px; page-break-inside: avoid; background-color: #fff; }
            .vot-title { font-weight: bold; font-size: 10pt; margin-bottom: 2px; border-bottom: 1px dashed #ccc; padding-bottom: 5px; }
            .vot-comision { font-size: 8pt; color: #0071bc; font-weight: bold; margin-bottom: 8px; text-transform: uppercase; }
            .vot-res { font-weight: bold; float: right; text-transform: uppercase; }
            .res-aprobado { color: green; } .res-rechazado { color: red; }
            
            .vot-table { width: 100%; border-collapse: collapse; font-size: 8.5pt; margin-top: 10px; border: 1px solid #eee; }
            .vot-table th { background-color: #f9f9f9; text-align: left; padding: 5px; border-bottom: 1px solid #ddd; color: #555; }
            .vot-table td { padding: 5px; border-bottom: 1px solid #eee; }
            .voto-si { color: green; font-weight: bold; } .voto-no { color: red; font-weight: bold; } .voto-abs { color: orange; font-weight: bold; }
            
            .signature-wrapper { margin-top: 50px; margin-bottom: 30px; text-align: center; page-break-inside: avoid; }
            .signature-box { display: inline-block; width: 280px; border: 1px dashed #999; padding: 20px 10px; margin: 0 20px; position: relative; background-color: transparent; vertical-align: middle; text-align: center; page-break-inside: avoid; }
            .sig-seal { position: absolute; left: 90px; top: 15px; width: 100px; opacity: 0.15; z-index: -1; }
            .sig-content { position: relative; z-index: 1; }
            .sig-name { font-weight: bold; font-size: 11pt; color: #000; margin-bottom: 4px; }
            .sig-role { font-size: 10pt; color: #555; margin-bottom: 4px; }
            .sig-meta { font-size: 8pt; color: #888; line-height: 1.2; }
            
            .validation-box { display: inline-block; width: 350px; border: 2px solid #28a745; background-color: transparent; padding: 15px; text-align: center; border-radius: 8px; }
            .val-title { color: #28a745; font-weight: bold; font-size: 11pt; margin-bottom: 5px; text-transform: uppercase; }
            .val-img { width: 60px; margin-bottom: 10px; }
            .val-text { font-size: 10pt; color: #333; margin-bottom: 4px; }
            .val-date { font-size: 9pt; color: #666; font-style: italic; }

            .footer-container { margin-top: 30px; page-break-inside: avoid; width: 100%; text-align: center; }
            .footer-validation-box { background-color: #f4f4f4; border: 1px solid #dcdcdc; border-radius: 6px; padding: 10px; width: 100%; box-sizing: border-box; }
            .footer-content-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
            .qr-cell { width: 90px; vertical-align: middle; text-align: center; padding-right: 15px; border-right: 1px solid #ccc; }
            .info-cell { padding-left: 15px; text-align: left; vertical-align: middle; font-size: 8pt; color: #555; line-height: 1.3; word-wrap: break-word; }
            .link-validacion { color: #0071bc; text-decoration: none; font-family: monospace; font-size: 7pt; display: block; width: 100%; margin-top: 3px; word-break: break-all; overflow-wrap: break-word; }
            .footer-borrador { text-align: center; color: #999; font-size: 8pt; margin-top: 30px; border-top: 1px dashed #ccc; padding-top: 10px; }
        ";

        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <style><?= $css ?></style>
        </head>
        <body>
            <header>
                <table class="header-table">
                    <tr>
                        <td width="100" align="left"><img src="<?= $logoGore ?>" width="70" style="display:block;"></td>
                        <td class="header-center">
                            <div class="h-line-1">GOBIERNO REGIONAL - REGIÓN DE VALPARAÍSO</div>
                            <div class="h-line-2">CONSEJO REGIONAL</div>
                            <div class="h-line-dynamic"><?= $labelComision ?> <?= htmlspecialchars($comisionesStr) ?></div>
                            <div class="h-line-dynamic"><?= $labelPresidente ?> <?= htmlspecialchars($presidentesStr) ?></div>
                        </td>
                        <td width="130" align="right"><img src="<?= $logoCore ?>" width="120" style="display:block;"></td>
                    </tr>
                </table>
                <hr class="header-sep">
            </header>

            <?php if ($esBorrador): ?><div class="watermark">BORRADOR</div><?php endif; ?>

            <div class="doc-title"><?= $tituloDocumento ?></div>

            <table class="info-table">
                <tr>
                    <td class="lbl">NOMBRE REUNIÓN:</td>
                    <td class="val"><strong><?= htmlspecialchars($nombreReunion) ?></strong></td>
                </tr>
                <tr>
                    <td class="lbl">FECHA:</td>
                    <td class="val"><?= $fechaTexto ?></td>
                </tr>
                <tr>
                    <td class="lbl">HORA INICIO:</td>
                    <td class="val"><?= isset($m['horaInicioReal']) ? date('H:i', strtotime($m['horaInicioReal'])) : '---' ?> hrs.</td>
                </tr>
                <tr>
                    <td class="lbl">HORA TÉRMINO:</td>
                    <td class="val"><?= isset($m['horaTerminoReal']) ? date('H:i', strtotime($m['horaTerminoReal'])) : 'En curso' ?> hrs.</td>
                </tr>
                <tr>
                    <td class="lbl">LUGAR:</td>
                    <td class="val">Sala de plenos</td>
                </tr>
            </table>

            <div class="tabla-sesion-box">
                <div class="tabla-sesion-title">Tabla de la sesión</div>
                <?php if (!empty($temas)): ?>
                    <?php foreach ($temas as $index => $t): ?>
                        <div class="tabla-item"><?= ($index + 1) ?>. <?= htmlspecialchars($t['nombreTema']) ?></div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="font-style:italic; color:#777;">Sin temas registrados.</div>
                <?php endif; ?>
            </div>

            <h3 class="sec-title">1. Asistencia de Consejeros</h3>
            <?php
            $presentes = array_filter($asistencia, function ($a) {
                return isset($a['estaPresente']) && $a['estaPresente'] == 1;
            });

            if (!empty($presentes)): ?>
                <table class="asist-table">
                    <tr>
                        <?php $col = 0;
                        foreach ($presentes as $p): if ($col >= 3) {
                                echo "</tr><tr>";
                                $col = 0;
                            } ?>
                            <td>• <?= htmlspecialchars($p['pNombre'] . ' ' . $p['aPaterno']) ?></td>
                        <?php $col++;
                        endforeach;
                        while ($col < 3) {
                            echo "<td></td>";
                            $col++;
                        } ?>
                    </tr>
                </table>
                <div style="font-size: 8pt; color: #777; margin-top: 5px;">Total Asistentes: <?= count($presentes) ?> Consejeros.</div>
            <?php else: ?>
                <p style="font-style: italic; color: #555;">No se registró asistencia.</p>
            <?php endif; ?>

            <h3 class="sec-title">2. Desarrollo de la Reunión</h3>
            <?php if (!empty($temas)): foreach ($temas as $index => $t): ?>
                    <div class="tema-block">
                        <div class="tema-header"><?= ($index + 1) ?>. <?= htmlspecialchars($t['nombreTema']) ?></div>
                        <div class="tema-body">
                            <?php if (!empty($t['objetivo'])): ?><div class="tema-field"><span class="tema-lbl">Objetivo:</span><span class="tema-val"><?= nl2br(htmlspecialchars($t['objetivo'])) ?></span></div><?php endif; ?>
                            <?php if (!empty($t['acuerdos'])): ?><div class="tema-field"><span class="tema-lbl">Acuerdos Adoptados:</span><span class="tema-val"><?= nl2br(htmlspecialchars($t['acuerdos'])) ?></span></div><?php endif; ?>
                            <?php if (!empty($t['compromiso'])): ?><div class="tema-field"><span class="tema-lbl">Compromisos:</span><span class="tema-val"><?= nl2br(htmlspecialchars($t['compromiso'])) ?></span></div><?php endif; ?>
                            <?php if (!empty($t['observacion'])): ?><div class="tema-field"><span class="tema-lbl">Observaciones:</span><span class="tema-val"><?= nl2br(htmlspecialchars($t['observacion'])) ?></span></div><?php endif; ?>
                        </div>
                    </div>
                <?php endforeach;
            else: ?><p>No se registraron temas.</p><?php endif; ?>

            <h3 class="sec-title">3. Registro de Votaciones</h3>
            <?php if (!empty($votaciones)): foreach ($votaciones as $v):
                    $si = 0; $no = 0; $abs = 0;
                    $detalles = $v['detalle_asistentes'] ?? [];
                    $lista = [];
                    foreach ($detalles as $d) {
                        $op = strtoupper($d['voto']);
                        if ($op == 'SI' || $op == 'APRUEBO') $si++;
                        elseif ($op == 'NO' || $op == 'RECHAZO') $no++;
                        elseif ($op == 'ABSTENCION' || $op == 'ABS') $abs++;
                        $lista[] = ['n' => $d['nombre'], 'o' => $op, 'c' => ($op == 'SI' ? 'voto-si' : ($op == 'NO' ? 'voto-no' : 'voto-abs'))];
                    }
                    $res = strtoupper($v['resultado'] ?? 'SIN RESULTADO');
                    $colRes = ($res == 'APROBADO') ? 'res-aprobado' : 'res-rechazado';
            ?>
                    <div class="votacion-box">
                        <div class="vot-title">ADENDA: <?= htmlspecialchars($v['nombreVotacion']) ?> <span class="vot-res <?= $colRes ?>"><?= $res ?></span></div>
                        <div class="vot-comision">COMISIÓN: <?= htmlspecialchars($v['nombreComision'] ?? 'GENERAL') ?></div>
                        <div style="font-size: 9pt; margin-bottom: 8px; background-color: #f9f9f9; padding: 5px;"><strong>Resumen:</strong> SI: <?= $si ?> | NO: <?= $no ?> | ABS: <?= $abs ?></div>
                        <table class="vot-table">
                            <thead>
                                <tr>
                                    <th width="60%">Consejero</th>
                                    <th width="40%">Voto</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($lista as $l): ?><tr>
                                        <td><?= htmlspecialchars($l['n']) ?></td>
                                        <td class="<?= $l['c'] ?>"><?= htmlspecialchars($l['o']) ?></td>
                                    </tr><?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endforeach;
            else: ?><p>No se realizaron votaciones.</p><?php endif; ?>

            <div class="signature-wrapper">
                <?php if ($esBorrador): ?>
                    <div class="validation-box">
                        <img src="<?= $aprobacionImg ?>" class="val-img">
                        <div class="val-title">VALIDADO DIGITALMENTE</div>
                        <div class="val-text"><strong><?= htmlspecialchars($nombreSecretario) ?></strong></div>
                        <div class="val-date">Fecha: <?= $fechaEnvio ?></div>
                    </div>
                <?php else: ?>
                    <?php if (!empty($firmas)): 
                        foreach ($firmas as $f): 
                            // ----------------------------------------------------
                            // CORRECCIÓN AQUÍ: NOMBRE INDIVIDUAL DE LA COMISIÓN
                            // ----------------------------------------------------
                            // Asumimos que $f trae 'nombreComision'. Si no, usamos fallback
                            $nombreComisionIndividual = $f['nombreComision'] ?? 'Comisión'; 
                    ?>
                        <div class="signature-box">
                            <img src="<?= $firmaImg ?>" class="sig-seal">
                            <div class="sig-content">
                                <div class="sig-name"><?= htmlspecialchars(($f['pNombre'] ?? '') . ' ' . ($f['aPaterno'] ?? '')) ?></div>
                                
                                <div class="sig-role" style="font-size: 9pt; font-weight: bold; margin-bottom: 2px;">
                                    <?= htmlspecialchars(mb_strtoupper($nombreComisionIndividual, 'UTF-8')) ?>
                                </div>
                                
                                <div class="sig-role">Presidente</div>
                                <div class="sig-meta">Firmado digitalmente<br><?= date('d/m/Y H:i', strtotime($f['fechaAprobacion'])) ?></div>
                            </div>
                        </div>
                        <?php endforeach;
                    else: ?>
                        <div style="margin-top:30px; font-style:italic; color:#777;">Documento pendiente de firmas.</div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <div class="footer-container">
                <?php if (!$esBorrador): ?>
                    <div class="footer-validation-box">
                        <table class="footer-content-table">
                            <tr>
                                <td class="qr-cell"><img src="<?= $qrBase64 ?>" width="80"></td>
                                <td class="info-cell">
                                    <strong>VALIDACIÓN DE AUTENTICIDAD</strong><br>
                                    Este documento es una copia fiel firmada electrónicamente.<br>
                                    Valide en:
                                    <a href="<?= $data['urlValidacion'] ?>" class="link-validacion" target="_blank"><?= $data['urlValidacion'] ?></a>
                                </td>
                            </tr>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
}

?>