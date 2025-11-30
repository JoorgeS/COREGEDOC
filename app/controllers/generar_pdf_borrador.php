<?php

// 1. Cargar Dompdf si no está cargado
$autoloadPath = __DIR__ . '/../../vendor/autoload.php';
if (file_exists($autoloadPath) && !class_exists('Dompdf\Dompdf')) {
    require_once $autoloadPath;
}

use Dompdf\Dompdf;
use Dompdf\Options;

// Función auxiliar para imágenes
if (!function_exists('ImageToDataUrl')) {
    function ImageToDataUrl(String $filename): String {
        if (!file_exists($filename)) return '';
        $mime = @mime_content_type($filename);
        if ($mime === false || strpos($mime, 'image/') !== 0) return '';
        $raw_data = @file_get_contents($filename);
        return "data:{$mime};base64," . base64_encode($raw_data);
    }
}

function generarPdfBorrador($idMinuta, $pdo, $rootPath)
{
    // ==========================================
    // 1. OBTENER DATOS COMPLETOS
    // ==========================================

    // A. Datos Generales (Minuta, Reunión, Comisiones, Personas)
    $sqlMinuta = "SELECT 
                    m.idMinuta, 
                    m.fechaMinuta, 
                    m.horaMinuta, 
                    r.idReunion,
                    r.nombreReunion,
                    -- Comisiones
                    c.nombreComision as com1,
                    c2.nombreComision as com2,
                    c3.nombreComision as com3,
                    -- Personas
                    CONCAT(u_sec.pNombre, ' ', u_sec.aPaterno) as nombreSecretario,
                    CONCAT(u_pres.pNombre, ' ', u_pres.aPaterno) as nombrePresidente
                  FROM t_minuta m
                  LEFT JOIN t_reunion r ON m.idMinuta = r.t_minuta_idMinuta
                  LEFT JOIN t_comision c ON m.t_comision_idComision = c.idComision
                  LEFT JOIN t_comision c2 ON r.t_comision_idComision_mixta = c2.idComision
                  LEFT JOIN t_comision c3 ON r.t_comision_idComision_mixta2 = c3.idComision
                  LEFT JOIN t_usuario u_sec ON m.t_usuario_idSecretario = u_sec.idUsuario
                  LEFT JOIN t_usuario u_pres ON m.t_usuario_idPresidente = u_pres.idUsuario
                  WHERE m.idMinuta = :id";
                  
    $stmt = $pdo->prepare($sqlMinuta);
    $stmt->execute([':id' => $idMinuta]);
    $minuta = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$minuta) die("Error: Minuta no encontrada.");

    // Procesar Comisiones (Mixtas)
    $nombresComisiones = [$minuta['com1']];
    if($minuta['com2']) $nombresComisiones[] = $minuta['com2'];
    if($minuta['com3']) $nombresComisiones[] = $minuta['com3'];
    $tituloComisiones = implode(' / ', array_filter($nombresComisiones));

    // B. Temas (Todos los campos)
    $stmtT = $pdo->prepare("SELECT * FROM t_tema WHERE t_minuta_idMinuta = :id ORDER BY idTema ASC");
    $stmtT->execute([':id' => $idMinuta]);
    $temas = $stmtT->fetchAll(PDO::FETCH_ASSOC);

    // C. Asistencia
    $stmtA = $pdo->prepare("SELECT CONCAT(u.pNombre, ' ', u.aPaterno) as nombreCompleto 
                            FROM t_asistencia a 
                            JOIN t_usuario u ON a.t_usuario_idUsuario = u.idUsuario 
                            WHERE a.t_minuta_idMinuta = :id 
                            ORDER BY u.aPaterno ASC");
    $stmtA->execute([':id' => $idMinuta]);
    $asistentes = $stmtA->fetchAll(PDO::FETCH_ASSOC);

    // D. Votaciones y Detalle (¡NUEVO!)
    $votaciones = [];
    $idReunion = $minuta['idReunion'];
    
    // Buscamos votaciones por ID Minuta o ID Reunión
    $sqlVotos = "SELECT * FROM t_votacion 
                 WHERE t_minuta_idMinuta = :idMinuta 
                 OR (t_reunion_idReunion IS NOT NULL AND t_reunion_idReunion = :idReunion)";
    $stmtV = $pdo->prepare($sqlVotos);
    $stmtV->execute([':idMinuta' => $idMinuta, ':idReunion' => $idReunion]);
    $listaVotaciones = $stmtV->fetchAll(PDO::FETCH_ASSOC);

    // Para cada votación, traemos el detalle
    foreach($listaVotaciones as $v) {
        $sqlDetalle = "SELECT u.pNombre, u.aPaterno, vo.opcionVoto 
                       FROM t_voto vo
                       JOIN t_usuario u ON vo.idUsuario = u.idUsuario
                       WHERE vo.idVotacion = :idVoto
                       ORDER BY u.aPaterno ASC";
        $stmtD = $pdo->prepare($sqlDetalle);
        $stmtD->execute([':idVoto' => $v['idVotacion']]);
        $v['detalle_votos'] = $stmtD->fetchAll(PDO::FETCH_ASSOC);
        
        // Conteo rápido
        $v['si'] = 0; $v['no'] = 0; $v['abs'] = 0;
        foreach($v['detalle_votos'] as $dv) {
            if($dv['opcionVoto'] == 'SI') $v['si']++;
            if($dv['opcionVoto'] == 'NO') $v['no']++;
            if($dv['opcionVoto'] == 'ABSTENCION') $v['abs']++;
        }
        
        $votaciones[] = $v;
    }


    // ==========================================
    // 2. PREPARAR IMÁGENES
    // ==========================================
    $logoGore = ImageToDataUrl($rootPath . '/public/img/logo2.png');
    $logoCore = ImageToDataUrl($rootPath . '/public/img/logoCore1.png');

    // ==========================================
    // 3. CONSTRUIR HTML
    // ==========================================
    $html = '
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Helvetica, Arial, sans-serif; font-size: 10pt; color: #333; }
            
            /* Marca de agua */
            .watermark {
                position: fixed; top: 50%; left: 50%; width: 100%; text-align: center;
                transform: translate(-50%, -50%) rotate(-45deg);
                font-size: 90pt; font-weight: bold; color: rgba(200, 200, 200, 0.15);
                z-index: 9999; text-transform: uppercase;
            }
            
            /* Encabezado */
            .header-table { width: 100%; border-bottom: 2px solid #444; margin-bottom: 20px; padding-bottom: 10px; }
            .header-center { text-align: center; font-weight: bold; font-size: 10pt; line-height: 1.3; }
            
            /* Tabla de Info Principal */
            .info-tabla { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 9.5pt; }
            .info-tabla td { border: 1px solid #999; padding: 6px 8px; vertical-align: top; }
            .label { background-color: #f2f2f2; font-weight: bold; width: 18%; color: #444; }
            
            /* Títulos de Sección */
            .seccion-titulo { 
                font-weight: bold; font-size: 11pt; margin-top: 20px; margin-bottom: 10px; 
                text-transform: uppercase; border-bottom: 1px solid #ccc; padding-bottom: 3px; color: #000;
            }
            
            /* Temas */
            .tema-box { margin-bottom: 15px; padding: 10px; background-color: #fdfdfd; border: 1px solid #e0e0e0; page-break-inside: avoid; }
            .tema-titulo { font-weight: bold; font-size: 10.5pt; margin-bottom: 6px; display: block; color: #222; }
            .tema-item { margin-bottom: 4px; font-size: 9.5pt; }
            .tema-label { font-weight: bold; color: #555; }
            
            /* Asistencia */
            .asistentes-list ul { margin: 5px 0; padding-left: 20px; column-count: 2; column-gap: 20px; }
            .asistentes-list li { margin-bottom: 3px; }

            /* Votaciones */
            .votacion-box { border: 1px solid #999; margin-bottom: 15px; page-break-inside: avoid; }
            .votacion-header { background-color: #eee; padding: 5px 10px; font-weight: bold; border-bottom: 1px solid #999; }
            .votacion-resumen { padding: 5px 10px; font-size: 9pt; background-color: #f9f9f9; border-bottom: 1px solid #eee; }
            .votacion-detalle { padding: 8px 10px; font-size: 9pt; }
            .voto-si { color: green; }
            .voto-no { color: red; }
            .voto-abs { color: orange; }
            
            .footer { position: fixed; bottom: 0; left: 0; right: 0; text-align: center; font-size: 8pt; color: #999; border-top: 1px solid #eee; padding-top: 5px; }
        </style>
    </head>
    <body>
        <div class="watermark">BORRADOR</div>

        <!-- Encabezado Logos -->
        <table class="header-table">
            <tr>
                <td width="15%"><img src="' . $logoGore . '" style="width: 70px;"></td>
                <td class="header-center">
                    GOBIERNO REGIONAL DE VALPARAÍSO<br>
                    CONSEJO REGIONAL<br>
                    <span style="font-size: 9pt; font-weight: normal;">' . strtoupper($tituloComisiones) . '</span>
                </td>
                <td width="15%" align="right"><img src="' . $logoCore . '" style="width: 70px;"></td>
            </tr>
        </table>

        <h3 style="text-align:center; text-decoration:underline; margin-bottom:20px;">MINUTA DE REUNIÓN (BORRADOR)</h3>

        <!-- Info Minuta -->
        <table class="info-tabla">
            <tr>
                <td class="label">N° Minuta:</td>
                <td width="30%">' . $minuta['idMinuta'] . '</td>
                <td class="label">Fecha:</td>
                <td>' . date('d/m/Y', strtotime($minuta['fechaMinuta'])) . '</td>
            </tr>
            <tr>
                <td class="label">Hora:</td>
                <td>' . date('H:i', strtotime($minuta['horaMinuta'])) . ' hrs</td>
                <td class="label">Reunión:</td>
                <td>' . htmlspecialchars($minuta['nombreReunion']) . '</td>
            </tr>
            <tr>
                <td class="label">Comisión:</td>
                <td colspan="3">' . htmlspecialchars($tituloComisiones) . '</td>
            </tr>
            <tr>
                <td class="label">Presidente:</td>
                <td>' . htmlspecialchars($minuta['nombrePresidente'] ?: 'No asignado') . '</td>
                <td class="label">Secretario:</td>
                <td>' . htmlspecialchars($minuta['nombreSecretario'] ?: 'No asignado') . '</td>
            </tr>
        </table>

        <!-- Asistencia -->
        <div class="seccion-titulo">ASISTENCIA</div>
        <div class="asistentes-list">
            <ul>';
    if (empty($asistentes)) {
        $html .= '<li>No se ha registrado asistencia.</li>';
    } else {
        foreach ($asistentes as $as) {
            $html .= '<li>' . htmlspecialchars($as['nombreCompleto']) . '</li>';
        }
    }
    $html .= '</ul></div>';

    // Temas Tratados
    $html .= '<div class="seccion-titulo">TEMAS TRATADOS Y ACUERDOS</div>';
    if (empty($temas)) {
        $html .= '<p>No hay temas registrados.</p>';
    } else {
        foreach ($temas as $i => $t) {
            $html .= '<div class="tema-box">';
            $html .= '<span class="tema-titulo">' . ($i + 1) . '. ' . htmlspecialchars($t['nombreTema']) . '</span>';
            
            if (!empty(trim($t['objetivo']))) 
                $html .= '<div class="tema-item"><span class="tema-label">Objetivo:</span> ' . nl2br(htmlspecialchars($t['objetivo'])) . '</div>';
            
            if (!empty(trim($t['acuerdos']))) 
                $html .= '<div class="tema-item"><span class="tema-label">Acuerdos:</span> ' . nl2br(htmlspecialchars($t['acuerdos'])) . '</div>';
            
            if (!empty(trim($t['compromiso']))) 
                $html .= '<div class="tema-item"><span class="tema-label">Compromisos/Responsables:</span> ' . nl2br(htmlspecialchars($t['compromiso'])) . '</div>';
            
            if (!empty(trim($t['observacion']))) 
                $html .= '<div class="tema-item"><span class="tema-label">Observaciones:</span> ' . nl2br(htmlspecialchars($t['observacion'])) . '</div>';
            
            $html .= '</div>';
        }
    }

    // Votaciones
    if (!empty($votaciones)) {
        $html .= '<div class="seccion-titulo">VOTACIONES REALIZADAS</div>';
        foreach ($votaciones as $v) {
            $resultado = 'SIN RESULTADO';
            if ($v['si'] > $v['no']) $resultado = 'APROBADO';
            elseif ($v['no'] > $v['si']) $resultado = 'RECHAZADO';
            elseif ($v['si'] == $v['no'] && $v['si'] > 0) $resultado = 'EMPATE';

            $html .= '<div class="votacion-box">';
            $html .= '<div class="votacion-header">' . htmlspecialchars($v['nombreVotacion']) . ' <span style="float:right;">Resultado: ' . $resultado . '</span></div>';
            $html .= '<div class="votacion-resumen">Resumen: <b>SI:</b> ' . $v['si'] . ' | <b>NO:</b> ' . $v['no'] . ' | <b>ABST:</b> ' . $v['abs'] . '</div>';
            
            $html .= '<div class="votacion-detalle">';
            $votosTexto = [];
            foreach ($v['detalle_votos'] as $dv) {
                $opcion = $dv['opcionVoto'];
                $clase = ($opcion == 'SI') ? 'voto-si' : (($opcion == 'NO') ? 'voto-no' : 'voto-abs');
                $votosTexto[] = htmlspecialchars($dv['pNombre'] . ' ' . $dv['aPaterno']) . " (<span class='$clase'>$opcion</span>)";
            }
            $html .= implode(', ', $votosTexto);
            $html .= '</div></div>';
        }
    } else {
        $html .= '<div class="seccion-titulo">VOTACIONES</div>';
        $html .= '<p>No se registraron votaciones en esta sesión.</p>';
    }

    $html .= '
    <div class="footer">
        Documento preliminar generado el ' . date('d/m/Y H:i') . ' - Válido solo para revisión interna.
    </div>
    </body>
    </html>';

    // ==========================================
    // 4. RENDERIZAR PDF
    // ==========================================
    $options = new Options();
    $options->set('isRemoteEnabled', true);
    $options->set('isHtml5ParserEnabled', true);
    
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('letter', 'portrait');
    $dompdf->render();

    // 5. Salida Inline
    $dompdf->stream("Borrador_Minuta_$idMinuta.pdf", ["Attachment" => 0]);
}