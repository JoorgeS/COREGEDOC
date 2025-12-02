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
    // --- GENERADORES DE IMÁGENES Y QR ---

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
        // Ajustamos para usar __DIR__ y evitar problemas con DOCUMENT_ROOT en algunos servidores
        $baseImg = __DIR__ . '/../../public/img/';

        $logoGore = $this->imageToDataUrl($baseImg . 'logo2.png');
        $logoCore = $this->imageToDataUrl($baseImg . 'logoCore1.png');
        $firmaImg = $this->imageToDataUrl($baseImg . 'firmadigital.png');

        // 2. QR
        $url = $data['urlValidacion'] ?? '#';
        $qrBase64 = $this->generarQrBase64($url);

        // 3. HTML
        $html = $this->getHtmlTemplate($data, $logoGore, $logoCore, $firmaImg, $qrBase64, $esBorrador);

        // 4. Dompdf
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Helvetica');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();

        $guardado = file_put_contents($rutaGuardado, $dompdf->output());

        return ($guardado !== false);
    }

    private function getHtmlTemplate($data, $logoGore, $logoCore, $firmaImg, $qrBase64, $esBorrador)
    {
        // Extracción de datos
        $m = $data['minuta_info'];
        $firmas = $data['firmas_aprobadas'] ?? [];
        $temas = $data['temas'] ?? [];
        $asistencia = $data['asistencia'] ?? [];
        $votaciones = $data['votaciones'] ?? [];

        $idMinuta = $m['idMinuta'] ?? '---';

        // Fecha en español
        $fechaRaw = strtotime($m['fechaMinuta']);
        $meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        $fechaTexto = date('d', $fechaRaw) . ' de ' . $meses[date('n', $fechaRaw) - 1] . ' de ' . date('Y', $fechaRaw);

        $estado = strtoupper($m['estadoMinuta'] ?? 'APROBADA');

        $nombreComision = 'Comisión';
        if (isset($data['comisiones_info'])) {
            $nombres = array_column($data['comisiones_info'], 'nombre');
            $nombreComision = implode(' / ', $nombres);
        }

        $hash = $m['hashValidacion'] ?? '---';
        $tituloDocumento = $esBorrador ? "MINUTA DE REUNIÓN (BORRADOR)" : "MINUTA DE REUNIÓN N° $idMinuta";

        // CSS
        $css = "
            @page { margin: 40px 50px; }
            body { font-family: Helvetica, Arial, sans-serif; font-size: 10pt; color: #333; line-height: 1.4; }
            
            /* Marca de Agua */
            .watermark {
                position: fixed; 
                top: 50%; 
                left: 50%; 
                transform: translate(-50%, -50%) rotate(-45deg);
                font-size: 80pt; 
                font-weight: bold; 
                color: rgba(95, 93, 93, 0.08); 
                z-index: 9999; 
                text-transform: uppercase; 
                text-align: center; 
                width: 100%;
                pointer-events: none;
            }

            /* Encabezado */
            .header-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
            .header-center { text-align: center; color: #000; vertical-align: middle; }
            .h-line-1 { font-size: 11pt; font-weight: bold; text-transform: uppercase; margin: 0; }
            .h-line-2 { font-size: 10pt; margin: 2px 0; }
            .h-line-3 { font-size: 9pt; color: #555; }
            hr.header-sep { border: 0; border-top: 2px solid #0071bc; margin: 5px 0 20px 0; }
            
            /* Título */
            .doc-title { text-align: center; font-size: 14pt; font-weight: bold; color: #000; text-transform: uppercase; margin-bottom: 15px; background-color: #f0f0f0; padding: 5px; border: 1px solid #ccc; }
            
            /* Tablas Info */
            .info-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 9pt; }
            .info-table td { padding: 4px; border-bottom: 1px solid #eee; }
            .lbl { font-weight: bold; color: #000; width: 120px; }
            .val { color: #333; }
            
            /* Secciones */
            h3.sec-title { font-size: 11pt; font-weight: bold; margin-top: 20px; margin-bottom: 10px; color: #0071bc; border-bottom: 1px solid #0071bc; padding-bottom: 3px; text-transform: uppercase; }
            
            /* Asistencia */
            .asist-table { width: 100%; border-collapse: collapse; font-size: 9pt; }
            .asist-table td { width: 33%; padding: 3px; }
            
            /* Temas */
            .tema-block { margin-bottom: 20px; page-break-inside: avoid; border: 1px solid #e0e0e0; border-radius: 4px; overflow: hidden; }
            .tema-header { background-color: #f5f5f5; padding: 5px 10px; font-weight: bold; border-bottom: 1px solid #ddd; font-size: 10pt; }
            .tema-body { padding: 10px; }
            .tema-field { margin-bottom: 8px; }
            .tema-lbl { font-weight: bold; font-size: 9pt; color: #555; display: block; margin-bottom: 2px; }
            .tema-val { display: block; text-align: justify; font-size: 9.5pt; }
            
            /* --- VOTACIONES --- */
            .votacion-box { border: 1px solid #ccc; padding: 10px; margin-bottom: 15px; page-break-inside: avoid; background-color: #fff; }
            .vot-title { font-weight: bold; font-size: 10pt; margin-bottom: 2px; border-bottom: 1px dashed #ccc; padding-bottom: 5px; }
            .vot-comision { font-size: 8pt; color: #0071bc; font-weight: bold; margin-bottom: 8px; text-transform: uppercase; }
            
            .vot-res { font-weight: bold; float: right; text-transform: uppercase; }
            .res-aprobado { color: green; }
            .res-rechazado { color: red; }
            
            .vot-table { width: 100%; border-collapse: collapse; font-size: 8.5pt; margin-top: 10px; border: 1px solid #eee; }
            .vot-table th { background-color: #f9f9f9; text-align: left; padding: 5px; border-bottom: 1px solid #ddd; color: #555; }
            .vot-table td { padding: 5px; border-bottom: 1px solid #eee; }
            .voto-si { color: green; font-weight: bold; }
            .voto-no { color: red; font-weight: bold; }
            .voto-abs { color: orange; font-weight: bold; }
            
            /* --- FIRMAS --- */
            .signature-wrapper { margin-top: 50px; margin-bottom: 30px; text-align: center; }
            .signature-box {
                display: inline-block;
                width: 280px;
                border: 1px dashed #999; 
                padding: 20px 10px;
                margin: 0 20px;
                position: relative; 
                background-color: transparent; 
                vertical-align: middle;
                text-align: center;
            }
            .sig-seal {
                position: absolute;
                left: 90px; 
                top: 15px; 
                width: 100px; 
                opacity: 0.15; 
                z-index: -1; 
            }
            .sig-content { position: relative; z-index: 1; }
            .sig-name { font-weight: bold; font-size: 11pt; color: #000; margin-bottom: 4px; }
            .sig-role { font-size: 10pt; color: #555; margin-bottom: 4px; }
            .sig-meta { font-size: 8pt; color: #888; line-height: 1.2; }
            
            /* Footer */
            .footer-sep { border: 0; border-top: 1px solid #ccc; margin: 20px 0; }
            .footer-table { width: 100%; border-top: 1px solid #ccc; padding-top: 10px; margin-top: 20px; }
            .qr-col { width: 80px; vertical-align: top; padding-right: 15px; }
            .info-col { 
                vertical-align: top; 
                font-size: 8pt; 
                color: #555; 
                line-height: 1.3; 
                text-align: justify;
            }
            .link-validacion {
                color: #0071bc;
                text-decoration: none;
                font-family: monospace; 
                font-size: 7pt; 
                word-break: break-all;
                overflow-wrap: break-word;
                display: block; 
                margin-top: 3px;
            }
            .hash-tag { font-family: monospace; background: #eee; padding: 2px; }
            .footer-borrador { text-align: center; color: #999; font-size: 8pt; margin-top: 30px; border-top: 1px dashed #ccc; padding-top: 10px; }
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

            <?php if ($esBorrador): ?>
                <div class="watermark">BORRADOR</div>
            <?php endif; ?>

            <table class="header-table">
                <tr>
                    <td width="100" align="left" style="vertical-align: middle;">
                        <img src="<?= $logoGore ?>" width="70" style="display:block;">
                    </td>
                    <td class="header-center">
                        <div class="h-line-1">GOBIERNO REGIONAL VALPARAÍSO</div>
                        <div class="h-line-2">CONSEJO REGIONAL</div>
                        <div class="h-line-3">Sistema de Gestión Documental</div>
                    </td>
                    <td width="130" align="right" style="vertical-align: middle;">
                        <img src="<?= $logoCore ?>" width="120" style="display:block;">
                    </td>
                </tr>
            </table>
            <hr class="header-sep">

            <div class="doc-title"><?= $tituloDocumento ?></div>

            <table class="info-table">
                <tr>
                    <td class="lbl">COMISIÓN:</td>
                    <td class="val"><strong><?= htmlspecialchars($nombreComision) ?></strong></td>
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

            </table>

            <h3 class="sec-title">1. Asistencia de Consejeros</h3>
            <?php
            $presentes = array_filter($asistencia, function ($a) {
                return isset($a['estaPresente']) && $a['estaPresente'] == 1;
            });

            if (!empty($presentes)):
            ?>
                <table class="asist-table">
                    <tr>
                        <?php
                        $col = 0;
                        foreach ($presentes as $p):
                            if ($col >= 3) {
                                echo "</tr><tr>";
                                $col = 0;
                            }
                        ?>
                            <td>• <?= htmlspecialchars($p['pNombre'] . ' ' . $p['aPaterno']) ?></td>
                        <?php
                            $col++;
                        endforeach;
                        while ($col < 3) {
                            echo "<td></td>";
                            $col++;
                        }
                        ?>
                    </tr>
                </table>
                <div style="font-size: 8pt; color: #777; margin-top: 5px;">
                    Total Asistentes: <?= count($presentes) ?> Consejeros.
                </div>
            <?php else: ?>
                <p style="font-style: italic; color: #555;">No se registró asistencia.</p>
            <?php endif; ?>


            <h3 class="sec-title">2. Desarrollo de la Reunión</h3>
            <?php if (!empty($temas)): ?>
                <?php foreach ($temas as $index => $t): ?>
                    <div class="tema-block">
                        <div class="tema-header">
                            <?= ($index + 1) ?>. <?= htmlspecialchars($t['nombreTema']) ?>
                        </div>
                        <div class="tema-body">
                            <?php if (!empty($t['objetivo'])): ?>
                                <div class="tema-field"><span class="tema-lbl">Objetivo:</span><span class="tema-val"><?= nl2br(htmlspecialchars($t['objetivo'])) ?></span></div>
                            <?php endif; ?>
                            <?php if (!empty($t['acuerdos'])): ?>
                                <div class="tema-field"><span class="tema-lbl">Acuerdos Adoptados:</span><span class="tema-val"><?= nl2br(htmlspecialchars($t['acuerdos'])) ?></span></div>
                            <?php endif; ?>
                            <?php if (!empty($t['compromiso'])): ?>
                                <div class="tema-field"><span class="tema-lbl">Compromisos y Responsables:</span><span class="tema-val"><?= nl2br(htmlspecialchars($t['compromiso'])) ?></span></div>
                            <?php endif; ?>
                            <?php if (!empty($t['observacion'])): ?>
                                <div class="tema-field"><span class="tema-lbl">Observaciones / Comentarios:</span><span class="tema-val"><?= nl2br(htmlspecialchars($t['observacion'])) ?></span></div>
                            <?php endif; ?>
                            <?php if (empty($t['objetivo']) && empty($t['acuerdos']) && empty($t['compromiso']) && empty($t['observacion'])): ?>
                                <span class="tema-val" style="font-style:italic;">Se abordó el tema sin registro de detalles adicionales.</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No se registraron temas.</p>
            <?php endif; ?>


            <h3 class="sec-title">3. Registro de Votaciones</h3>
            <?php if (!empty($votaciones)): ?>
                <?php foreach ($votaciones as $v):
                    $si = 0;
                    $no = 0;
                    $abs = 0;
                    $listaVotosProcesados = [];

                    $detalles = $v['detalle_asistentes'] ?? $v['detalles'] ?? [];

                    foreach ($detalles as $dv) {
                        $opcionRaw = $dv['opcionVoto'] ?? $dv['voto'] ?? 'PENDIENTE';
                        $opcion = strtoupper($opcionRaw);

                        if ($opcion == 'SI' || $opcion == 'APRUEBO') {
                            $si++;
                            $claseCss = 'voto-si';
                        } elseif ($opcion == 'NO' || $opcion == 'RECHAZO') {
                            $no++;
                            $claseCss = 'voto-no';
                        } elseif ($opcion == 'ABSTENCION' || $opcion == 'ABS') {
                            $abs++;
                            $claseCss = 'voto-abs';
                        } else {
                            $claseCss = '';
                        }

                        $nombreConsejero = 'Consejero';
                        if (!empty($dv['nombre'])) {
                            $nombreConsejero = $dv['nombre'];
                        } elseif (!empty($dv['pNombre'])) {
                            $nombreConsejero = $dv['pNombre'] . ' ' . ($dv['aPaterno'] ?? '');
                        }

                        if ($opcion != 'PENDIENTE') {
                            $listaVotosProcesados[] = [
                                'nombre' => $nombreConsejero,
                                'opcion' => $opcion,
                                'css' => $claseCss
                            ];
                        }
                    }

                    $txtResultado = ($si > $no) ? 'APROBADO' : 'RECHAZADO';
                    if ($si == 0 && $no == 0 && $abs == 0) $txtResultado = 'SIN VOTOS';

                    $resultadoFinal = strtoupper($v['resultado'] ?? $txtResultado);
                    $colorRes = ($resultadoFinal == 'APROBADO' || $resultadoFinal == 'SI') ? 'res-aprobado' : 'res-rechazado';
                ?>
                    <div class="votacion-box">
                        <div class="vot-title">
                            Moción: <?= htmlspecialchars($v['nombreVotacion']) ?>
                            <span class="vot-res <?= $colorRes ?>"><?= $resultadoFinal ?></span>
                        </div>

                        <div class="vot-comision">
                            COMISIÓN: <?= htmlspecialchars($v['nombreComision'] ?? 'GENERAL') ?>
                        </div>

                        <div style="font-size: 9pt; margin-bottom: 8px; background-color: #f9f9f9; padding: 5px; border-radius: 3px;">
                            <strong>Resumen:</strong>
                            SI: <?= $si ?> &nbsp;|&nbsp;
                            NO: <?= $no ?> &nbsp;|&nbsp;
                            ABS: <?= $abs ?>
                        </div>

                        <?php if (!empty($listaVotosProcesados)): ?>
                            <table class="vot-table">
                                <thead>
                                    <tr>
                                        <th width="60%">Consejero</th>
                                        <th width="40%">Voto</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($listaVotosProcesados as $voto): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($voto['nombre']) ?></td>
                                            <td class="<?= $voto['css'] ?>"><?= htmlspecialchars($voto['opcion']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div style="font-size: 8pt; color: #999; font-style: italic; padding: 5px;">
                                No hay votos registrados (Todos pendientes o sin datos).
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No se realizaron votaciones en esta sesión.</p>
            <?php endif; ?>


            <?php if (!$esBorrador): ?>
                <div class="signature-wrapper">
                    <?php if (!empty($firmas)): ?>
                        <?php foreach ($firmas as $f): ?>
                            <div class="signature-box">
                                <img src="<?= $firmaImg ?>" class="sig-seal">
                                <div class="sig-content">
                                    <div class="sig-name">
                                        <!-- CORRECCIÓN: Usamos pNombre y aPaterno que vienen del Controller -->
                                        <?= htmlspecialchars(($f['pNombre'] ?? '') . ' ' . ($f['aPaterno'] ?? '')) ?> 
                                    </div>
                                    <div class="sig-role" style="font-size: 9pt; font-weight: bold; margin-bottom: 2px;">
                                        <!-- CORRECCIÓN: Fallback al nombre de comisión del documento si no hay específica -->
                                        <?= htmlspecialchars($f['nombreComision'] ?? $nombreComision) ?>
                                    </div>
                                    <div class="sig-role">Presidente</div>
                                    <div class="sig-meta">
                                        Firmado digitalmente<br>
                                        <?= date('d/m/Y H:i', strtotime($f['fechaAprobacion'])) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="margin-top:30px; font-style:italic; color:#777;">Documento pendiente de firmas electrónicas.</div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div style="page-break-inside: avoid;">
                <?php if ($esBorrador): ?>
                    <div class="footer-borrador">
                        Documento preliminar generado el <?= date('d/m/Y H:i') ?> - Válido solo para revisión interna.
                    </div>
                <?php else: ?>
                    <hr class="footer-sep">
                    <table class="footer-table">
                        <tr>
                            <td class="qr-col"><img src="<?= $qrBase64 ?>" width="80"></td>
                            <td class="info-col">
                                <strong>VALIDACIÓN DE AUTENTICIDAD</strong><br>
                                Este documento es una copia fiel del original firmado electrónicamente en el Sistema COREGEDOC.<br>
                                Valide su originalidad escaneando el código QR o ingresando a:<br>
                                <span class="link-validacion"><?= $data['urlValidacion'] ?></span>
                                <br><br>
                                Hash Seguridad: <span class="hash-tag"><?= $hash ?></span>
                            </td>
                        </tr>
                    </table>
                <?php endif; ?>
            </div>

        </body>

        </html>
<?php
        return ob_get_clean();
    }
}