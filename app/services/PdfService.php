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
        $m = $data['minuta_info'];
        $firmas = $data['firmas_aprobadas'] ?? [];
        $temas = $data['temas'] ?? [];
        $asistencia = $data['asistencia'] ?? [];
        $votaciones = $data['votaciones'] ?? [];
        $presidentesStr = $data['presidentes_str'] ?? ''; // Recibimos la cadena

        $idMinuta = $m['idMinuta'] ?? '---';

        $fechaRaw = strtotime($m['fechaMinuta']);
        $meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        $fechaTexto = date('d', $fechaRaw) . ' de ' . $meses[date('n', $fechaRaw) - 1] . ' de ' . date('Y', $fechaRaw);

        $tituloComision = 'Comisión';
        if (isset($data['comisiones_info'])) {
            $nombres = array_column($data['comisiones_info'], 'nombre');
            $tituloComision = implode(' / ', $nombres);
        }

        $hash = $m['hashValidacion'] ?? '---';
        $tituloDocumento = $esBorrador ? "MINUTA DE REUNIÓN (BORRADOR)" : "MINUTA DE REUNIÓN N° $idMinuta";

        $css = "
            @page { margin: 40px 50px; }
            body { font-family: Helvetica, Arial, sans-serif; font-size: 10pt; color: #333; line-height: 1.4; }
            .watermark { position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-45deg); font-size: 80pt; font-weight: bold; color: rgba(95, 93, 93, 0.08); z-index: 9999; text-transform: uppercase; text-align: center; width: 100%; pointer-events: none; }
            
            /* Encabezado */
            .header-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
            .header-center { text-align: center; color: #000; vertical-align: middle; }
            .h-line-1 { font-size: 11pt; font-weight: bold; text-transform: uppercase; margin: 0; }
            .h-line-2 { font-size: 10pt; margin: 2px 0; }
            .h-line-3 { font-size: 9pt; color: #555; }
            hr.header-sep { border: 0; border-top: 2px solid #0071bc; margin: 5px 0 20px 0; }
            .doc-title { text-align: center; font-size: 14pt; font-weight: bold; color: #000; text-transform: uppercase; margin-bottom: 15px; background-color: #f0f0f0; padding: 5px; border: 1px solid #ccc; }
            
            /* Info */
            .info-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 9pt; }
            .info-table td { padding: 4px; border-bottom: 1px solid #eee; }
            .lbl { font-weight: bold; color: #000; width: 130px; }
            .val { color: #333; }
            h3.sec-title { font-size: 11pt; font-weight: bold; margin-top: 20px; margin-bottom: 10px; color: #0071bc; border-bottom: 1px solid #0071bc; padding-bottom: 3px; text-transform: uppercase; }
            
            /* Contenido */
            .asist-table { width: 100%; border-collapse: collapse; font-size: 9pt; }
            .asist-table td { width: 33%; padding: 3px; }
            .tema-block { margin-bottom: 20px; page-break-inside: avoid; border: 1px solid #e0e0e0; border-radius: 4px; overflow: hidden; }
            .tema-header { background-color: #f5f5f5; padding: 5px 10px; font-weight: bold; border-bottom: 1px solid #ddd; font-size: 10pt; }
            .tema-body { padding: 10px; }
            .tema-field { margin-bottom: 8px; }
            .tema-lbl { font-weight: bold; font-size: 9pt; color: #555; display: block; margin-bottom: 2px; }
            .tema-val { display: block; text-align: justify; font-size: 9.5pt; }
            
            /* Votaciones */
            .votacion-box { border: 1px solid #ccc; padding: 10px; margin-bottom: 15px; page-break-inside: avoid; background-color: #fff; }
            .vot-title { font-weight: bold; font-size: 10pt; margin-bottom: 2px; border-bottom: 1px dashed #ccc; padding-bottom: 5px; }
            .vot-comision { font-size: 8pt; color: #0071bc; font-weight: bold; margin-bottom: 8px; text-transform: uppercase; }
            .vot-res { font-weight: bold; float: right; text-transform: uppercase; }
            .res-aprobado { color: green; } .res-rechazado { color: red; }
            .vot-table { width: 100%; border-collapse: collapse; font-size: 8.5pt; margin-top: 10px; border: 1px solid #eee; }
            .vot-table th { background-color: #f9f9f9; text-align: left; padding: 5px; border-bottom: 1px solid #ddd; color: #555; }
            .vot-table td { padding: 5px; border-bottom: 1px solid #eee; }
            .voto-si { color: green; font-weight: bold; } .voto-no { color: red; font-weight: bold; } .voto-abs { color: orange; font-weight: bold; }
            
            /* FIRMAS (CORRECCIÓN DE CORTE) */
            .signature-wrapper { margin-top: 50px; margin-bottom: 30px; text-align: center; page-break-inside: avoid; }
            .signature-box { display: inline-block; width: 280px; border: 1px dashed #999; padding: 20px 10px; margin: 0 20px; position: relative; background-color: transparent; vertical-align: middle; text-align: center; page-break-inside: avoid; }
            .sig-seal { position: absolute; left: 90px; top: 15px; width: 100px; opacity: 0.15; z-index: -1; }
            .sig-content { position: relative; z-index: 1; }
            .sig-name { font-weight: bold; font-size: 11pt; color: #000; margin-bottom: 4px; }
            .sig-role { font-size: 10pt; color: #555; margin-bottom: 4px; }
            .sig-meta { font-size: 8pt; color: #888; line-height: 1.2; }
            
            /* FOOTER (CORRECCIÓN DE CENTRADO) */
            .footer-container { text-align: center; border-top: 1px solid #ccc; padding-top: 15px; margin-top: 30px; page-break-inside: avoid; }
            .footer-content-table { width: 80%; margin: 0 auto; border-collapse: collapse; }
            .qr-cell { width: 100px; text-align: right; padding-right: 15px; vertical-align: middle; }
            .info-cell { text-align: left; vertical-align: middle; font-size: 8pt; color: #555; line-height: 1.3; }
            .link-validacion { color: #0071bc; text-decoration: none; font-family: monospace; font-size: 7pt; word-wrap: break-word; word-break: break-all; white-space: pre-wrap; display: block; width: 100%; margin-top: 3px; }
            .hash-tag { font-family: monospace; background: #eee; padding: 2px; font-size: 7pt; }
            .footer-borrador { text-align: center; color: #999; font-size: 8pt; margin-top: 30px; border-top: 1px dashed #ccc; padding-top: 10px; }
        ";

        ob_start();
?>
        <!DOCTYPE html>
        <html lang="es">
        <head><meta charset="UTF-8"><style><?= $css ?></style></head>
        <body>

            <?php if ($esBorrador): ?><div class="watermark">BORRADOR</div><?php endif; ?>

            <table class="header-table">
                <tr>
                    <td width="100" align="left"><img src="<?= $logoGore ?>" width="70" style="display:block;"></td>
                    <td class="header-center">
                        <div class="h-line-1">GOBIERNO REGIONAL VALPARAÍSO</div>
                        <div class="h-line-2">CONSEJO REGIONAL</div>
                        <div class="h-line-3">Sistema de Gestión Documental</div>
                    </td>
                    <td width="130" align="right"><img src="<?= $logoCore ?>" width="120" style="display:block;"></td>
                </tr>
            </table>
            <hr class="header-sep">

            <div class="doc-title"><?= $tituloDocumento ?></div>

            <table class="info-table">
                <tr>
                    <td class="lbl">COMISIÓN(ES):</td>
                    <td class="val"><strong><?= htmlspecialchars($tituloComision) ?></strong></td>
                </tr>
                <tr>
                    <td class="lbl">PRESIDENTE(S):</td>
                    <td class="val"><?= htmlspecialchars($presidentesStr) ?></td>
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
            $presentes = array_filter($asistencia, function ($a) { return isset($a['estaPresente']) && $a['estaPresente'] == 1; });
            if (!empty($presentes)): ?>
                <table class="asist-table">
                    <tr>
                        <?php $col = 0; foreach ($presentes as $p): if ($col >= 3) { echo "</tr><tr>"; $col = 0; } ?>
                            <td>• <?= htmlspecialchars($p['pNombre'] . ' ' . $p['aPaterno']) ?></td>
                        <?php $col++; endforeach; while ($col < 3) { echo "<td></td>"; $col++; } ?>
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
            <?php endforeach; else: ?><p>No se registraron temas.</p><?php endif; ?>

            <h3 class="sec-title">3. Registro de Votaciones</h3>
            <?php if (!empty($votaciones)): foreach ($votaciones as $v): 
                $si=0; $no=0; $abs=0; $detalles=$v['detalle_asistentes']??[]; $lista=[];
                foreach($detalles as $d){ 
                    $op=strtoupper($d['voto']); 
                    if($op=='SI'||$op=='APRUEBO')$si++; elseif($op=='NO'||$op=='RECHAZO')$no++; elseif($op=='ABSTENCION'||$op=='ABS')$abs++; 
                    $lista[]=['n'=>$d['nombre'],'o'=>$op,'c'=>($op=='SI'?'voto-si':($op=='NO'?'voto-no':'voto-abs'))];
                }
                $res = strtoupper($v['resultado']??'SIN RESULTADO');
                $colRes = ($res=='APROBADO')?'res-aprobado':'res-rechazado';
            ?>
                <div class="votacion-box">
                    <div class="vot-title">Moción: <?= htmlspecialchars($v['nombreVotacion']) ?> <span class="vot-res <?= $colRes ?>"><?= $res ?></span></div>
                    <div class="vot-comision">COMISIÓN: <?= htmlspecialchars($v['nombreComision']??'GENERAL') ?></div>
                    <div style="font-size: 9pt; margin-bottom: 8px; background-color: #f9f9f9; padding: 5px;"><strong>Resumen:</strong> SI: <?=$si?> | NO: <?=$no?> | ABS: <?=$abs?></div>
                    <table class="vot-table"><thead><tr><th width="60%">Consejero</th><th width="40%">Voto</th></tr></thead><tbody>
                    <?php foreach($lista as $l): ?><tr><td><?=htmlspecialchars($l['n'])?></td><td class="<?=$l['c']?>"><?=htmlspecialchars($l['o'])?></td></tr><?php endforeach; ?>
                    </tbody></table>
                </div>
            <?php endforeach; else: ?><p>No se realizaron votaciones.</p><?php endif; ?>

            <?php if (!$esBorrador): ?>
                <div class="signature-wrapper">
                    <?php if (!empty($firmas)): foreach ($firmas as $f): ?>
                        <div class="signature-box">
                            <img src="<?= $firmaImg ?>" class="sig-seal">
                            <div class="sig-content">
                                <div class="sig-name"><?= htmlspecialchars(($f['pNombre'] ?? '') . ' ' . ($f['aPaterno'] ?? '')) ?></div>
                                <div class="sig-role" style="font-size: 9pt; font-weight: bold; margin-bottom: 2px;">
                                    <?= htmlspecialchars($f['nombreComision'] ?? $tituloComision) ?>
                                </div>
                                <div class="sig-role">Presidente</div>
                                <div class="sig-meta">Firmado digitalmente<br><?= date('d/m/Y H:i', strtotime($f['fechaAprobacion'])) ?></div>
                            </div>
                        </div>
                    <?php endforeach; else: ?><div style="margin-top:30px; font-style:italic; color:#777;">Documento pendiente de firmas.</div><?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="footer-container">
                <?php if ($esBorrador): ?>
                    <div class="footer-borrador">Documento preliminar generado el <?= date('d/m/Y H:i') ?>.</div>
                <?php else: ?>
                    <table class="footer-content-table">
                        <tr>
                            <td class="qr-cell"><img src="<?= $qrBase64 ?>" width="80"></td>
                            <td class="info-cell">
                                <strong>VALIDACIÓN DE AUTENTICIDAD</strong><br>
                                Este documento es una copia fiel firmada electrónicamente.<br>
                                Valide en: <span class="link-validacion"><?= $data['urlValidacion'] ?></span>
                                Hash: <span class="hash-tag"><?= $hash ?></span>
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
