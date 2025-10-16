<?php
// views/pages/minuta_detalle.php
// Asume que $tema contiene el array con idTema, nombreTema, objetivo, compromiso, observacion

// Valores de ejemplo para simular el encabezado:
$simulated_data = [
    'fecha' => date('Y-m-d'),
    'hora' => date('H:i'),
    'nSesion' => 'N/A',
    'presidente' => $_SESSION['pNombre'] ?? 'No Definido',
    'secretario' => $_SESSION['pNombre'] . ' ' . $_SESSION['aPaterno'] ?? 'Pedro Vergara',
    'comision' => 'Recursos Hídricos, Agricultura y Ganadería', // Valor de ejemplo
    'lugar' => 'Salón de Plenarios (Asumido)',
];
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Detalle de Minuta (Tema #<?php echo htmlspecialchars($tema['idTema']); ?>)</title>
    <link href="/corevota/public/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">

    <style>
        /* Estilos Generales y Contenedor */
        body {
            background-color: #f4f4f4;
        }

        .minuta-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 30px;
            background: #fff;
            border: 1px solid #ddd;
            font-family: Arial, sans-serif;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        /* --- Encabezado --- */
        .header-section {
            border-bottom: 2px solid #333;
            padding-bottom: 15px;
            margin-bottom: 25px;
            text-align: center;
        }

        .header-section img {
            max-height: 70px;
            margin-bottom: 10px;
        }

        .header-section p {
            font-size: 1rem;
            font-weight: bold;
            color: #555;
            margin: 0;
        }

        /* --- Tabla de Sesión (Corrección de bordes y padding) --- */
        .minuta-info {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
            font-size: 0.95rem;
        }

        .minuta-info th,
        .minuta-info td {
            border: 1px solid #c0c0c0;
            /* Borde más visible */
            padding: 8px 12px;
            text-align: left;
        }

        .minuta-info th {
            background-color: #e9ecef;
            /* Fondo gris claro para encabezados */
            width: 25%;
            font-weight: 600;
        }

        /* --- Títulos de Secciones --- */
        h2 {
            font-size: 1.4rem;
            font-weight: bold;
            margin-top: 30px;
            margin-bottom: 15px;
            color: #004d40;
            /* Verde Oscuro */
            border-bottom: 1px solid #004d40;
            padding-bottom: 5px;
        }

        h3 {
            font-size: 1.1rem;
            margin-top: 20px;
            color: #212529;
            /* Casi negro */
            font-weight: 700;
        }

        /* --- Contenido del Tema --- */
        .content-box {
            border: 1px solid #dee2e6;
            padding: 15px;
            margin-bottom: 25px;
            background: #fff;
            box-shadow: none;
        }

        .content-box p {
            white-space: pre-wrap;
            /* Mantiene saltos de línea */
            margin-bottom: 0;
            line-height: 1.5;
        }

        /* --- Estilo de la firma simulada --- */
        .signature-box {
            margin-top: 50px;
            text-align: center;
        }

        .signature-line {
            border-top: 1px solid #000;
            width: 50%;
            margin: 20px auto 5px auto;
        }

        /* Media Print */
        @media print {

            .btn-secondary,
            .btn-primary {
                display: none !important;
            }

            .minuta-container {
                box-shadow: none;
                border: none;
            }
        }
    </style>
</head>

<body>

    <div class="container mt-4">
        <div class="mb-3">
            <a href="MinutaController.php?action=list" class="btn btn-secondary">← Volver al Listado</a>
        </div>

        <div class="minuta-container">

            <div class="header-section">
                <img src="/corevota/public/img/logoCore1.png"
                    alt="Logo Gobierno Regional de Valparaíso"
                    style="max-height: 70px;">
                <img src="/corevota/public/img/logo2.png"
                    alt="Logo 2 Gobierno Regional de Valparaíso"
                    style="max-height: 70px;">
                <p>COMISIÓN: <?php echo htmlspecialchars($simulated_data['comision']); ?></p>
            </div>

            <table class="minuta-info">
                <thead>
                    <tr>
                        <th colspan="4" style="background-color: #343a40; color: white; text-align: center;">MINUTA DE REUNIÓN</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <th>Fecha</th>
                        <td><?php echo htmlspecialchars($simulated_data['fecha']); ?></td>
                        <th>Hora</th>
                        <td><?php echo htmlspecialchars($simulated_data['hora']); ?></td>
                    </tr>
                    <tr>
                        <th>Presidente</th>
                        <td><?php echo htmlspecialchars($simulated_data['presidente']); ?></td>
                        <th>Secretario Técnico</th>
                        <td><?php echo htmlspecialchars($simulated_data['secretario']); ?></td>
                    </tr>
                    <tr>
                        <th>N° Sesión</th>
                        <td><?php echo htmlspecialchars($simulated_data['nSesion']); ?></td>
                        <th>Lugar</th>
                        <td><?php echo htmlspecialchars($simulated_data['lugar']); ?></td>
                    </tr>
                </tbody>
            </table>

            <h2>ASISTENTES</h2>
            <div class="content-box">
                <p>No se registraron asistentes.</p>
            </div>

            <h2>DESARROLLO DE LA MINUTA</h2>

            <h3>TEMA TRATADO</h3>
            <div class="content-box">
                <p><?php echo nl2br(htmlspecialchars($tema['nombreTema'])); ?></p>
            </div>

            <h3>OBJETIVO</h3>
            <div class="content-box">
                <p><?php echo nl2br(htmlspecialchars($tema['objetivo'])); ?></p>
            </div>

            <h3>COMPROMISOS Y RESPONSABLES</h3>
            <div class="content-box">
                <p><?php echo nl2br(htmlspecialchars($tema['compromiso'])); ?></p>
            </div>

            <h3>OBSERVACIONES Y COMENTARIOS</h3>
            <div class="content-box">
                <p><?php echo nl2br(htmlspecialchars($tema['observacion'])); ?></p>
            </div>

            <hr style="margin-top: 30px; margin-bottom: 30px;">

            <h2>ACUERDOS</h2>
            <div class="content-box">
                <p>No hay acuerdos específicos registrados en esta sección del formulario.</p>
            </div>

            <h2>VARIOS</h2>
            <div class="content-box">
                <p>No hay puntos varios registrados en esta sección del formulario.</p>
            </div>

            <div class="signature-box">
                <div class="signature-line"></div>
                <p><?php echo htmlspecialchars($simulated_data['presidente']); ?></p>
                <p>Presidente</p>
                <p>Comisión <?php echo htmlspecialchars($simulated_data['comision']); ?></p>
            </div>

            <div class="text-center mt-5">
                <button class="btn btn-primary btn-lg" onclick="window.print()">🖨️ Imprimir / Guardar PDF</button>
            </div>
        </div>
    </div>

</body>

</html>