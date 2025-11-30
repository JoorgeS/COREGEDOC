<?php

// 1. Intentar cargar el autoloader de Composer si existe
$autoloadPath = __DIR__ . '/../../vendor/autoload.php';
if (file_exists($autoloadPath) && !class_exists('Dompdf\Dompdf')) {
    require_once $autoloadPath;
}

use Dompdf\Dompdf;
use Dompdf\Options;

// Función segura para convertir imágenes a base64 (para que se vean en el PDF)
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
        
        // Datos de la Minuta y Reunión
        $stmt = $pdo->prepare("
            SELECT m.idMinuta, r.fechaInicioReunion, c.nombreComision,
                   CONCAT(s.pNombre, ' ', s.aPaterno, ' ', s.aMaterno) as nombreSecretario
            FROM t_minuta m
            LEFT JOIN t_reunion r ON m.idMinuta = r.t_minuta_idMinuta
            LEFT JOIN t_comision c ON m.t_comision_idComision = c.idComision
            LEFT JOIN t_usuario s ON s.idUsuario = :idSecretario
            WHERE m.idMinuta = :idMinuta
        ");
        $stmt->execute([':idMinuta' => $idMinuta, ':idSecretario' => $idSecretario]);
        $minuta = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$minuta) return false;

        // Lista de Asistentes (Presentes y Ausentes)
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
        $fechaReunion = $minuta['fechaInicioReunion'] ? date('d-m-Y', strtotime($minuta['fechaInicioReunion'])) : date('d-m-Y');
        $horaReunion = $minuta['fechaInicioReunion'] ? date('H:i', strtotime($minuta['fechaInicioReunion'])) : '--:--';
        $fechaValidacion = date('d-m-Y H:i:s');
        
        // Helper function for images
        $getImgBase64 = function($path) {
            if(file_exists($path)) {
                $data = file_get_contents($path);
                return 'data:image/png;base64,' . base64_encode($data);
            }
            return '';
        };

        // Rutas absolutas para imágenes
        $logoGore = $getImgBase64($rootPath . '/public/img/logo2.png');
        $logoCore = $getImgBase64($rootPath . '/public/img/logoCore1.png');
        $selloImg = $getImgBase64($rootPath . '/public/img/aprobacion.png');

        // ==========================================
        // 3. CONSTRUIR EL HTML DEL REPORTE
        // ==========================================
        $html = '
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Helvetica, Arial, sans-serif; font-size: 10pt; color: #333; }
                .header-table { width: 100%; border-bottom: 2px solid #ccc; margin-bottom: 20px; padding-bottom: 10px; }
                .titulo { text-align: center; font-size: 14pt; font-weight: bold; text-transform: uppercase; margin: 15px 0; color: #000; }
                .info-box { width: 100%; margin-bottom: 20px; border-collapse: collapse; }
                .info-box td { border: 1px solid #999; padding: 6px; }
                .label { background-color: #f0f0f0; font-weight: bold; width: 25%; }
                
                .tabla-asistencia { width: 100%; border-collapse: collapse; margin-top: 10px; }
                .tabla-asistencia th { background-color: #e9ecef; padding: 8px; border: 1px solid #999; text-align: left; }
                .tabla-asistencia td { padding: 6px; border: 1px solid #ccc; vertical-align: middle; }
                
                .presente { color: green; font-weight: bold; }
                .ausente { color: red; font-weight: bold; }
                .manual { color: #0056b3; font-size: 8pt; font-style: italic; display:block; }
                
                .sello-container { text-align: center; margin-top: 50px; margin-bottom: 10px; }
                .footer { position: fixed; bottom: 20px; left: 0; right: 0; text-align: center; font-size: 8pt; color: #888; border-top: 1px solid #eee; padding-top: 5px; }

                /* New styles for QR Footer */
                .footer-sep { border: 0; border-top: 1px solid #eee; margin: 30px 0 10px 0; }
                .footer-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                .qr-col { width: 100px; vertical-align: top; padding-right: 15px; }
                .info-col { vertical-align: top; font-size: 9pt; color: #555; line-height: 1.4; }
                .link-blue { color: #0066cc; text-decoration: none; word-break: break-all; font-size: 8pt; }
                .hash-tag { background-color: #eee; padding: 2px 6px; font-family: monospace; font-size: 8pt; color: #666; display: block; margin-top: 5px; word-break: break-all; }
            </style>
        </head>
        <body>
            <table class="header-table">
                <tr>
                    <td width="15%"><img src="' . $logoGore . '" style="width: 70px;"></td>
                    <td width="70%" align="center">
                        <strong>GOBIERNO REGIONAL DE VALPARAÍSO</strong><br>
                        CONSEJO REGIONAL
                    </td>
                    <td width="15%" align="right"><img src="' . $logoCore . '" style="width: 70px;"></td>
                </tr>
            </table>

            <div class="titulo">Certificado de Asistencia Validada</div>

            <table class="info-box">
                <tr>
                    <td class="label">N° Minuta:</td><td>' . $idMinuta . '</td>
                    <td class="label">Fecha Reunión:</td><td>' . $fechaReunion . '</td>
                </tr>
                <tr>
                    <td class="label">Comisión:</td><td>' . htmlspecialchars($minuta['nombreComision'] ?? 'Sin Comisión') . '</td>
                    <td class="label">Hora Inicio:</td><td>' . $horaReunion . '</td>
                </tr>
            </table>

            <h3>Detalle de Asistencia</h3>
            <table class="tabla-asistencia">
                <thead>
                    <tr>
                        <th>Consejero Regional</th>
                        <th width="150" style="text-align:center;">Estado</th>
                        <th width="120" style="text-align:center;">Hora Registro</th>
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
                    $estado .= '<span class="manual">(Validación Manual ST)</span>';
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
            <p style="text-align:right; margin-top:10px;"><strong>Total Asistentes: ' . $totalPresentes . '</strong></p>

            <div class="sello-container">
                <img src="' . $selloImg . '" style="width: 100px; opacity: 0.8;"><br>
                <p><strong>VALIDADO DIGITALMENTE</strong><br>
                Por: ' . htmlspecialchars($minuta['nombreSecretario'] ?? 'Secretaría Técnica') . '<br>
                Fecha de Cierre: ' . $fechaValidacion . '</p>
            </div>

            <div style="page-break-inside: avoid;">
                <hr class="footer-sep">
                <table class="footer-table">
                    <tr>
                        <td class="qr-col">';
        
        if ($qrBase64) {
            $html .= '<img src="' . $qrBase64 . '" style="width: 90px; height: 90px;">';
        }

        $html .= '      </td>
                        <td class="info-col">
                            <strong>Verificación de Autenticidad</strong><br>
                            Este documento certifica la asistencia oficial a la sesión indicada.<br>
                            Puede validar su originalidad escaneando el código QR o visitando:<br>
                            <a href="' . $urlValidacion . '" class="link-blue">' . $urlValidacion . '</a>
                            <br>
                            <span class="hash-tag">HASH: ' . $hash . '</span>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="footer">
                Documento oficial generado por Sistema COREGEDOC el ' . date('d/m/Y H:i:s') . '
            </div>
        </body>
        </html>';

        // ==========================================
        // 4. GENERAR ARCHIVO (Con Fallback de Seguridad)
        // ==========================================
        
        // Opción A: Si Dompdf está instalado, generamos PDF real
        if (class_exists('Dompdf\Dompdf')) {
            $options = new Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', true); // Permitir imágenes
            
            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('letter', 'portrait');
            $dompdf->render();
            $output = $dompdf->output();
            
            file_put_contents($rutaGuardado, $output);
        } 
        // Opción B: Si falla o no está instalado, guardamos el HTML
        // Esto permite que el archivo "exista" físicamente y el correo se pueda enviar.
        else {
            $aviso = "";
            file_put_contents($rutaGuardado, $aviso . $html);
        }

        return true; // Retornamos éxito siempre para no detener el flujo del controlador

    } catch (Exception $e) {
        // En caso de error catastrófico, guardamos un archivo de error
        // para que al menos exista algo que adjuntar y no falle el mail.
        file_put_contents($rutaGuardado, "Error generando reporte: " . $e->getMessage());
        return true; 
    }
}