<?php

/**
 * Archivo: public/validar.php
 */

// Ajuste de ruta: Salimos de 'public' para buscar 'app'
require_once(__DIR__ . '/../app/config/Database.php');

use App\Config\Database;

$hash = $_GET['hash'] ?? '';
$doc = null;

if ($hash) {
    try {
        $db = new Database();
        $conn = $db->getConnection();

        // 1. PRIMER INTENTO: Buscar en Minutas
        $sqlMinuta = "SELECT 
                        'Minuta de Reunión' as tipoDoc,
                        idMinuta,
                        fechaMinuta as fecha,
                        estadoMinuta as estado,
                        pathArchivo as path
                      FROM t_minuta 
                      WHERE hashValidacion = :h";

        $stmt = $conn->prepare($sqlMinuta);
        $stmt->execute([':h' => $hash]);
        $doc = $stmt->fetch(PDO::FETCH_ASSOC);

        // 2. SEGUNDO INTENTO: Si no es minuta, buscar en Adjuntos (Asistencia)
        if (!$doc) {
            $sqlAdjunto = "SELECT 
                            'Certificado de Asistencia' as tipoDoc,
                            t_minuta_idMinuta as idMinuta,
                            'Fecha Actual' as fecha, /* Texto placeholder ya que no hay fecha en BD */
                            'VALIDADO DIGITALMENTE' as estado,
                            pathAdjunto as path
                           FROM t_adjunto 
                           WHERE hash_validacion = :h AND tipoAdjunto = 'asistencia'";

            $stmt2 = $conn->prepare($sqlAdjunto);
            $stmt2->execute([':h' => $hash]);
            $resultadoAdjunto = $stmt2->fetch(PDO::FETCH_ASSOC);

            if ($resultadoAdjunto) {
                // Si encontramos el adjunto, formateamos la fecha al momento actual de validación
                // ya que la tabla no guarda la fecha de subida.
                $resultadoAdjunto['fecha'] = date('Y-m-d H:i:s');
                $doc = $resultadoAdjunto;
            }
        }
    } catch (Exception $e) {
        error_log("Error en validación: " . $e->getMessage());
    }
}

// --- AQUÍ COMIENZA EL HTML (Mismo diseño que tenías) ---
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Validación Digital - CORE Valparaíso</title>

    <link href="vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        body {
            background-color: #f4f6f9;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding-top: 40px;
        }

        .card-validation {
            max-width: 500px;
            margin: 0 auto;
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .card-header-logos {
            background: white;
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid #eee;
        }

        .card-header-logos img {
            height: 55px;
            margin: 0 10px;
        }

        .status-box {
            padding: 30px 20px;
            text-align: center;
            color: white;
        }

        .bg-valid {
            background: linear-gradient(135deg, #28a745 0%, #218838 100%);
        }

        .bg-invalid {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        }

        .icon-status {
            font-size: 4rem;
            margin-bottom: 10px;
            opacity: 0.9;
        }

        .hash-display {
            font-family: monospace;
            background: #f8f9fa;
            padding: 8px;
            border-radius: 4px;
            font-size: 0.85rem;
            color: #666;
            word-break: break-all;
            border: 1px dashed #ccc;
        }

        .table-details th {
            width: 40%;
            background-color: #f8f9fa;
            font-weight: 600;
            color: #555;
        }

        .btn-download {
            border-radius: 50px;
            padding: 10px 25px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>

<body>

    <div class="container pb-5">
        <div class="card card-validation">

            <div class="card-header-logos">
                <img src="/coregedoc/public/img/logo2.png" alt="Gore Valparaíso">
                <img src="/coregedoc/public/img/logoCore1.png" alt="Consejo Regional">
            </div>

            <?php if ($doc): ?>
                <div class="status-box bg-valid">
                    <i class="fas fa-check-circle icon-status"></i>
                    <h2 class="fw-bold mb-0">Documento Válido</h2>
                    <p class="mb-0 opacity-75">Verificado en nuestros registros</p>
                </div>

                <div class="card-body p-4">
                    <h5 class="text-secondary mb-3"><i class="fas fa-file-alt me-2"></i>Detalle del Documento</h5>

                    <table class="table table-bordered table-details mb-4">
                        <tr>
                            <th>Tipo</th>
                            <td class="text-success fw-bold"><?= htmlspecialchars($doc['tipoDoc']) ?></td>
                        </tr>
                        <tr>
                            <th>Referencia</th>
                            <td>Minuta N° <?= $doc['idMinuta'] ?></td>
                        </tr>
                        <tr>
                            <th>Fecha Registro</th>
                            <td>
                                <?php
                                // Manejo seguro de fecha
                                $fechaStr = $doc['fecha'];
                                echo ($fechaStr === 'Fecha Actual')
                                    ? date('d/m/Y H:i') . ' (Validación)'
                                    : date('d/m/Y H:i', strtotime($fechaStr));
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Estado</th>
                            <td><span class="badge bg-success"><?= $doc['estado'] ?></span></td>
                        </tr>
                    </table>

                    <div class="text-center">
                        <a href="/coregedoc/<?= htmlspecialchars($doc['path']) ?>" class="btn btn-success btn-download w-100" target="_blank" download>
                            <i class="fas fa-cloud-download-alt me-2"></i> Descargar Original
                        </a>
                    </div>

                    <div class="mt-4">
                        <label class="small text-muted fw-bold">Firma Digital (Hash SHA-256):</label>
                        <div class="hash-display">
                            <?= htmlspecialchars($hash) ?>
                        </div>
                    </div>
                </div>

            <?php else: ?>
                <div class="status-box bg-invalid">
                    <i class="fas fa-times-circle icon-status"></i>
                    <h2 class="fw-bold mb-0">No Encontrado</h2>
                    <p class="mb-0 opacity-75">El código no es válido o expiró</p>
                </div>

                <div class="card-body p-4 text-center">
                    <p class="text-muted mb-4">
                        El documento que intenta validar no existe en la base de datos o ha sido eliminado.
                    </p>
                    <div class="alert alert-danger border-0 bg-danger bg-opacity-10">
                        <strong>Hash consultado:</strong><br>
                        <span class="small font-monospace text-break"><?= htmlspecialchars($hash) ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <div class="card-footer bg-light text-center py-3">
                <small class="text-muted">&copy; <?= date('Y') ?> COREGEDOC</small>
            </div>
        </div>
    </div>

</body>

</html>