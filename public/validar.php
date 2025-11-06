<?php
// /corevota/public/validar.php
require_once __DIR__ . '/../class/class.conectorDB.php';

$hash = $_GET['hash'] ?? '';

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Validación de Documento - Consejo Regional de Valparaíso</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f5f6fa;
            font-family: 'Segoe UI', sans-serif;
        }
        .card {
            margin-top: 80px;
            border-radius: 12px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.08);
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .header img {
            height: 70px;
        }
        .valid {
            color: #007e00;
            font-weight: bold;
        }
        .invalid {
            color: #d00;
            font-weight: bold;
        }
        .footer {
            margin-top: 25px;
            text-align: center;
            font-size: 0.85rem;
            color: #777;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="card p-4 mx-auto" style="max-width: 600px;">
        <div class="header">
            <img src="/corevota/public/img/logo2.png" alt="Logo GORE" class="me-2">
            <img src="/corevota/public/img/logoCore1.png" alt="Logo CORE">
            <h4 class="mt-3">Consejo Regional de Valparaíso</h4>
            <h6>Validación de Autenticidad de Documentos</h6>
        </div>
        <hr>

        <?php
        if (empty($hash)) {
            echo '<div class="alert alert-warning text-center">⚠️ No se proporcionó ningún código de validación.</div>';
        } else {
            try {
                $db = new conectorDB();
                $pdo = $db->getDatabase();

                $sql = "SELECT idMinuta, pathArchivo, fechaAprobacion, estadoMinuta
                        FROM t_minuta
                        WHERE hashValidacion = :hash
                        LIMIT 1";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':hash' => $hash]);
                $minuta = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($minuta) {
                    echo '<div class="text-center">';
                    echo '<p class="valid">✅ Documento verificado correctamente</p>';
                    echo '<p>El documento corresponde a la minuta <strong>#' . htmlspecialchars($minuta['idMinuta']) . '</strong> emitida por el Consejo Regional de Valparaíso.</p>';
                    echo '<p><strong>Fecha de aprobación:</strong> ' . htmlspecialchars($minuta['fechaAprobacion']) . '</p>';
                    echo '<p><strong>Estado:</strong> ' . htmlspecialchars($minuta['estadoMinuta']) . '</p>';
                    echo '<a href="/corevota/' . htmlspecialchars($minuta['pathArchivo']) . '" target="_blank" class="btn btn-success mt-3">📄 Ver documento original</a>';
                    echo '</div>';
                } else {
                    echo '<div class="text-center">';
                    echo '<p class="invalid">❌ Código no válido o documento no encontrado</p>';
                    echo '<p>El código ingresado no corresponde a ningún documento emitido por el Consejo Regional.</p>';
                    echo '</div>';
                }
            } catch (Throwable $e) {
                echo '<div class="alert alert-danger text-center">Error al validar: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        }
        ?>

        <div class="footer">
            © <?php echo date('Y'); ?> Consejo Regional de Valparaíso · Sistema CoreVota
        </div>
    </div>
</div>
</body>
</html>
