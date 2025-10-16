<?php
// 1. Verificar si se enviaron datos
if (!isset($_POST['pdf_data'])) {
    die("Error: No se recibieron datos para generar el PDF.");
}

// 2. Definir la ruta raíz del proyecto (corevota/)
// Subimos desde 'controllers' (../) para llegar a la raíz del proyecto.
// Es crucial que esta ruta NO TERMINE en una barra.
define('ROOT_PATH', __DIR__ . '/../');

// Incluir el autoload de Dompdf (Ruta corregida: Subimos un nivel a corevota/ y entramos a vendor)
require_once ROOT_PATH . 'vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// 3. Decodificar los datos recibidos
$data = json_decode($_POST['pdf_data'], true);

if (!$data) {
    die("Error: Datos recibidos inválidos.");
}

// 4. Configurar Dompdf
$options = new Options();
$options->set('defaultFont', 'Helvetica');
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true); // Vital para cualquier recurso externo o local
// 🔑 CONFIGURACIÓN CLAVE PARA IMÁGENES LOCALES: 
// Establecer el chroot al directorio raíz del proyecto (corevota/)
$options->set('chroot', ROOT_PATH); 
// Permitir Dompdf acceder a archivos 'file://'
$options->set('isPhpEnabled', true); 


$dompdf = new Dompdf($options);

// OBTENER RUTAS DEL LOGO (USANDO RUTA RELATIVA AL CHROOT)
// Dompdf ahora buscará la imagen dentro del ROOT_PATH ('corevota/').
// La ruta que le pasamos al HTML debe ser la ruta relativa desde ROOT_PATH.
$logo_relative_path = 'public/img/logo2.png';

// Para el HTML, usamos una URI relativa que Dompdf resolverá internamente:
// Esto funciona cuando isRemoteEnabled es true y chroot está configurado correctamente.
$logo_uri = $logo_relative_path;

// ---------------------------------------------------------------------------------------
// 5. Función para generar el HTML Ejecutivo de la Minuta (ESTILO EJEMPLO)
// ---------------------------------------------------------------------------------------

function generateMinutaHtml($data, $logo_uri) {
    // Generar la cadena de las comisiones (principal y mixta si existe)
    $comisiones = htmlspecialchars($data['comision1']);
    if ($data['comisionMixta'] && !empty($data['comision2'])) {
        $comisiones .= ' / ' . htmlspecialchars($data['comision2']);
    }

    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Minuta de Reunión CORE - ' . $comisiones . '</title>
        <style>
            body { 
                font-family: Helvetica, sans-serif; 
                margin: 0; 
                padding: 0; 
                font-size: 10pt; 
                line-height: 1.5;
            }
            .container { 
                width: 90%; 
                margin: 20px auto; 
            }
            /* === ESTILOS DE ENCABEZADO === */
            .header-box { 
                border-bottom: 1px solid #ccc; 
                padding-bottom: 15px; 
                margin-bottom: 15px; 
                display: block; 
                overflow: hidden; 
            }
            .logo { 
                width: 60px; 
                height: auto; 
                float: left;
                margin-right: 15px;
            }
            .header-text {
                float: left;
                width: calc(100% - 75px);
            }
            .header-text p {
                margin: 0;
                font-size: 9pt;
                line-height: 1.2;
                color: #555;
            }
            .header-text .main-title {
                font-weight: bold;
                font-size: 11pt;
                color: #000;
            }
            .comision-title {
                font-weight: bold;
                font-size: 12pt;
                margin: 10px 0 5px 0;
            }

            /* === ESTILOS DE MINUTA === */
            .minuta-info {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 20px;
                font-size: 10pt;
            }
            .minuta-info th, .minuta-info td {
                border: 1px solid #ccc;
                padding: 6px 10px;
                text-align: left;
            }
            .minuta-info th {
                background-color: #eee;
                width: 30%;
                font-weight: bold;
            }
            h2 { 
                font-size: 14pt; 
                margin-top: 25px; 
                margin-bottom: 10px;
                color: #004d40; 
            }
            h3 { 
                font-size: 11pt; 
                margin-top: 15px; 
                border-bottom: 2px solid #ddd; 
                padding-bottom: 3px; 
                color: #333;
                font-weight: bold;
            }
            .content p { 
                margin: 5px 0 10px 0; 
            }
            .asistencia-list ul {
                list-style-type: none;
                padding: 0;
                margin: 10px 0;
                columns: 2; 
            }
            .asistencia-list li {
                margin-bottom: 3px;
            }

            /* === ESTILOS DE FIRMA === */
            .signature-box {
                margin-top: 50px;
                text-align: center;
            }
            .signature-line {
                border-top: 1px solid #000;
                width: 50%;
                margin: 30px auto 5px auto;
            }
        </style>
    </head>
    <body>
    <div class="container">
        
        <div class="header-box">
            <img src="' . htmlspecialchars($logo_uri) . '" class="logo" alt="Logo CORE">
            <div class="header-text">
                <p>GOBIERNO REGIONAL. REGIÓN DE VALPARAÍSO</p>
                <p class="main-title">CONSEJO REGIONAL</p>
                <p>COMISIÓN ' . strtoupper($comisiones) . '</p>
            </div>
        </div>

        <table class="minuta-info">
            <tr>
                <th>MINUTA DE REUNIÓN</th>
                <td colspan="3"></td>
            </tr>
            <tr>
                <th>Fecha</th>
                <td>' . htmlspecialchars($data['fecha']) . '</td>
                <th>Hora</th>
                <td>' . htmlspecialchars($data['hora']) . '</td>
            </tr>
            <tr>
                <th>Presidente</th>
                <td>' . htmlspecialchars($data['presidente1']) . '</td>
                <th>Secretario Técnico</th>
                <td>' . htmlspecialchars($data['secretario']) . '</td>
            </tr>
            <tr>
                <th>N° Sesión</th>
                <td>' . htmlspecialchars($data['nSesion']) . '</td>
                <th>Lugar</th>
                <td>Salón de Plenarios</td>
            </tr>
            ' . ($data['comisionMixta'] && !empty($data['comision2']) ? '
            <tr>
                <th>Comisión Mixta</th>
                <td>' . htmlspecialchars($data['comision2']) . '</td>
                <th>Presidente Mixta</th>
                <td>' . htmlspecialchars($data['presidente2']) . '</td>
            </tr>' : '') . '
        </table>

        <h2>ASISTENTES</h2>
        <div class="asistencia-list">
            <ul>';
    if (!empty($data['asistencia'])) {
        foreach ($data['asistencia'] as $consejero) {
            $html .= '<li>' . htmlspecialchars($consejero) . '</li>';
        }
    } else {
        $html .= '<li>No se registraron asistentes.</li>';
    }
    $html .= '</ul>
        </div>';

    // --- Desarrollo de la Minuta (Temas) ---
    $html .= '<h2>DESARROLLO DE LA MINUTA</h2>';

    if (empty($data['temas']) || (count($data['temas']) == 1 && empty($data['temas'][0]['nombreTema']))) {
        $html .= '<p>No hay temas registrados para el desarrollo de la minuta.</p>';
    } else {
        foreach ($data['temas'] as $index => $tema) {
            $num = $index + 1;
            
            // Si el tema principal está vacío, lo saltamos
            if (empty(strip_tags($tema['nombreTema']))) continue;

            $html .= '
            <div class="tema-block">
                <h3>TEMA ' . $num . ': ' . strip_tags($tema['nombreTema']) . '</h3>
                <div class="content">';

            // Utilizamos el contenido HTML generado por el editor de texto (contenteditable)
            if (!empty($tema['objetivo'])) {
                $html .= '<p><strong>OBJETIVO:</strong><br>' . $tema['objetivo'] . '</p>';
            }
            if (!empty($tema['descAcuerdo'])) {
                $html .= '<p><strong>ACUERDOS ADOPTADOS:</strong><br>' . $tema['descAcuerdo'] . '</p>';
            }
            if (!empty($tema['compromiso'])) {
                $html .= '<p><strong>COMPROMISOS Y RESPONSABLES:</strong><br>' . $tema['compromiso'] . '</p>';
            }
            if (!empty($tema['observacion'])) {
                $html .= '<p><strong>OBSERVACIONES Y COMENTARIOS:</strong><br>' . $tema['observacion'] . '</p>';
            }

            $html .= '</div>
            </div>';
        }
    }


    // --- ACUERDOS (Sección final) ---
    $html .= '<h2>ACUERDOS</h2>';
    $html .= '<p>No hay acuerdos generales registrados en el formulario.</p>';


    // --- VARIOS (Sección final) ---
    $html .= '<h2>VARIOS</h2>';
    $html .= '<p>No hay puntos varios registrados en el formulario.</p>';


    // --- FIRMA ---
    $html .= '
        <div class="signature-box">
            <div class="signature-line"></div>
            <p>' . htmlspecialchars($data['presidente1']) . '</p>
            <p>Presidente</p>
            <p>Comisión ' . $comisiones . '</p>
        </div>
    </div>
    </body>
    </html>';

    return $html;
}

// 6. Cargar y renderizar el PDF
$htmlContent = generateMinutaHtml($data, $logo_uri);

$dompdf->loadHtml($htmlContent);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// 7. Forzar la descarga
$filename = "Minuta_CORE_" . date('Ymd_His') . ".pdf";
$dompdf->stream($filename, array("Attachment" => true)); 

exit;