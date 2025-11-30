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
    /**
     * Genera un código QR en formato Base64 data URI
     */
    public function generarQrBase64($url)
    {
        try {
            // Configuración optimizada para lectura en pantallas y papel
            $qrCode = new QrCode(
                data: $url,
                encoding: new Encoding('UTF-8'),
                errorCorrectionLevel: ErrorCorrectionLevel::Low, // Nivel bajo para puntos más grandes y claros
                size: 400, // Tamaño grande para que al reducirse se vea nítido
                margin: 0, 
                roundBlockSizeMode: RoundBlockSizeMode::Margin
            );

            $writer = new PngWriter();
            $result = $writer->write($qrCode);

            return $result->getDataUri();

        } catch (\Exception $e) {
            error_log("Error generando QR local: " . $e->getMessage());
            return ''; 
        }
    }

    /**
     * Convierte imagen local a Base64 para incrustar en PDF
     */
    public function imageToDataUrl($filename)
    {
        if (!file_exists($filename)) {
            return '';
        }
        
        $mime = mime_content_type($filename);
        $data = file_get_contents($filename);
        
        if ($data === false) {
            return '';
        }

        return 'data:' . $mime . ';base64,' . base64_encode($data);
    }

    public function generarPdfFinal($data, $rutaGuardado)
    {
        // 1. Preparar imágenes
        // Ajusta esta ruta si tus imágenes están en otro lado
        $baseImg = __DIR__ . '/../../public/img/'; 
        
        $logoGore = $this->imageToDataUrl($baseImg . 'logo2.png');
        $logoCore = $this->imageToDataUrl($baseImg . 'logoCore1.png');
        // Si tienes una imagen para la firma digital (sello), descomenta la siguiente línea:
        $firmaImg = $this->imageToDataUrl($baseImg . 'firmadigital.png'); 
        // $firmaImg = ''; // Usa esto si no quieres imagen de fondo en la firma
        
        // 2. Generar QR
        $qrBase64 = $this->generarQrBase64($data['urlValidacion']);

        // 3. Renderizar HTML
        $html = $this->getHtmlTemplate($data, $logoGore, $logoCore, $firmaImg, $qrBase64);

        // 4. Configurar Dompdf
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Helvetica'); 
        
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();

        // 5. Guardar archivo
        $guardado = file_put_contents($rutaGuardado, $dompdf->output());
        
        return ($guardado !== false);
    }

    /**
     * Plantilla HTML con el diseño final (Gris, Dashed, QR izquierda)
     */
    private function getHtmlTemplate($data, $logoGore, $logoCore, $firmaImg, $qrBase64)
    {
        $m = $data['minuta_info'];
        $firmas = $data['firmas_aprobadas'] ?? [];
        $temas = $data['temas'] ?? [];
        
        // Variables visuales
        $idMinuta = $m['idMinuta'] ?? '---';
        $fecha = isset($m['fechaMinuta']) ? date('d/m/Y', strtotime($m['fechaMinuta'])) : date('d/m/Y');
        $estado = strtoupper($m['estadoMinuta'] ?? 'APROBADA');
        $nombreComision = htmlspecialchars($data['comisiones_info']['com1']['nombre'] ?? 'Comisión');
        $hash = $m['hashValidacion'] ?? '---';

        // CSS
        $css = "
            @page { margin: 40px 50px; }
            body { font-family: Helvetica, Arial, sans-serif; font-size: 10pt; color: #333; line-height: 1.3; }
            .header-table { width: 100%; border-collapse: collapse; margin-bottom: 5px; }
            .header-center { text-align: center; color: #000; vertical-align: top; padding-top: 5px; }
            .h-line-1 { font-size: 12pt; font-weight: bold; text-transform: uppercase; margin: 0; }
            .h-line-2 { font-size: 10pt; margin: 2px 0; }
            .h-line-3 { font-size: 9pt; color: #555; }
            hr.header-sep { border: 0; border-top: 1px solid #ccc; margin: 10px 0 25px 0; }
            .doc-title { text-align: center; font-size: 16pt; font-weight: bold; color: #333; text-transform: uppercase; margin-bottom: 25px; }
            .info-box { background-color: #fcfcfc; border: 1px solid #e0e0e0; padding: 12px 15px; margin-bottom: 25px; }
            .info-table { width: 100%; border-collapse: collapse; }
            .lbl { font-weight: bold; color: #333; }
            .val { color: #555; }
            .info-table td { padding: 3px 0; }
            h3.sec-title { font-size: 11pt; font-weight: bold; margin-bottom: 10px; color: #000; }
            ul.temas-list { margin-top: 0; padding-left: 20px; }
            ul.temas-list li { margin-bottom: 5px; color: #444; }
            .signature-wrapper { margin-top: 50px; margin-bottom: 50px; text-align: center; }
            .signature-box { display: inline-block; width: 300px; border: 1px dashed #aaa; padding: 20px 10px; position: relative; background-color: #fff; }
            .sig-content { position: relative; z-index: 2; }
            .sig-name { font-weight: bold; font-size: 10pt; margin-bottom: 2px; }
            .sig-role { font-size: 9pt; color: #555; margin-bottom: 2px; }
            .sig-meta { font-size: 8pt; color: #777; }
            .watermark-img { position: absolute; top: 35px; left: 50%; margin-left: -40px; width: 80px; opacity: 0.1; z-index: 1; }
            .footer-sep { border: 0; border-top: 1px solid #eee; margin: 20px 0 20px 0; }
            .footer-table { width: 100%; border-collapse: collapse; }
            .qr-col { width: 100px; vertical-align: top; }
            .info-col { vertical-align: top; font-size: 9pt; color: #555; line-height: 1.4; }
            .link-blue { color: #0066cc; text-decoration: none; word-break: break-all; }
            .hash-tag { background-color: #eee; padding: 2px 6px; font-family: monospace; font-size: 8.5pt; color: #666; }
        ";

        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head><meta charset="UTF-8"><style><?= $css ?></style></head>
        <body>
            <table class="header-table">
                <tr>
                    <td width="80" align="left"><img src="<?= $logoGore ?>" width="60"></td>
                    <td class="header-center">
                        <div class="h-line-1">GOBIERNO REGIONAL VALPARAÍSO</div>
                        <div class="h-line-2">CONSEJO REGIONAL</div>
                        <div class="h-line-3">Sistema de Gestión Documental</div>
                    </td>
                    <td width="80" align="right"><img src="<?= $logoCore ?>" width="70"></td>
                </tr>
            </table>
            <hr class="header-sep">

            <div class="doc-title">MINUTA DE REUNIÓN N° <?= $idMinuta ?></div>

            <div class="info-box">
                <table class="info-table">
                    <tr>
                        <td width="50%"><span class="lbl">Fecha:</span> <span class="val"><?= $fecha ?></span></td>
                        <td width="50%" align="center"><span class="lbl">Estado:</span> <span class="val"><?= $estado ?></span></td>
                    </tr>
                    <tr>
                        <td colspan="2"><span class="lbl">Comisión:</span> <span class="val"><?= $nombreComision ?></span></td>
                    </tr>
                </table>
            </div>

            <h3 class="sec-title">1. Temas Tratados</h3>
            <ul class="temas-list">
                <?php if (!empty($temas)): ?>
                    <?php foreach ($temas as $t): ?>
                        <li>
                            <strong><?= htmlspecialchars($t['nombreTema']) ?></strong>
                            <?php if(!empty($t['compromiso'])): ?> - <?= htmlspecialchars($t['compromiso']) ?><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li>No hay temas registrados.</li>
                <?php endif; ?>
            </ul>

            <div class="signature-wrapper">
                <?php if (!empty($firmas)): ?>
                    <?php foreach ($firmas as $f): ?>
                        <div class="signature-box">
                            <?php if($firmaImg): ?><img src="<?= $firmaImg ?>" class="watermark-img"><?php endif; ?>
                            <div class="sig-content">
                                <div class="sig-name">
                                    <?= htmlspecialchars($f['pNombre'] . ' ' . $f['aPaterno']) ?>
                                </div>
                                <div class="sig-role">Presidente Comisión</div>
                                <div class="sig-meta">
                                    Firmado digitalmente<br>
                                    <?= date('d/m/Y H:i', strtotime($f['fechaAprobacion'])) ?>
                                </div>
                            </div>
                        </div>
                        <br>
                    <?php endforeach; ?>
                <?php else: ?>
                     <div class="signature-box" style="border-style: dotted; color: #ccc;">
                        <div class="sig-content">Sin firmas registradas</div>
                    </div>
                <?php endif; ?>
            </div>

            <div style="page-break-inside: avoid;">
                <hr class="footer-sep">
                <table class="footer-table">
                    <tr>
                        <td class="qr-col"><img src="<?= $qrBase64 ?>" width="85"></td>
                        <td class="info-col">
                            <strong>Verificación de Autenticidad</strong><br>
                            Este documento ha sido firmado electrónicamente.<br>
                            Puede validar su originalidad escaneando el código QR o visitando:<br>
                            <a href="<?= $data['urlValidacion'] ?>" class="link-blue"><?= $data['urlValidacion'] ?></a>
                            <br><br>
                            Código Hash: <span class="hash-tag"><?= $hash ?></span>
                        </td>
                    </tr>
                </table>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
}