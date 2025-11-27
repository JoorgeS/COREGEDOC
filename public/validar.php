<?php
// /corevota/public/validar.php
require_once __DIR__ . '/../class/class.conectorDB.php';

// 1. LIMPIEZA CRÍTICA: Quitamos espacios en blanco que rompen la búsqueda
$hash = isset($_GET['hash']) ? trim($_GET['hash']) : '';

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Validación de Documento - Consejo Regional de Valparaíso</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f5f6fa; font-family: 'Segoe UI', sans-serif; }
        .card { margin-top: 80px; border-radius: 12px; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08); }
        .header { text-align: center; margin-bottom: 20px; }
        .header img { height: 70px; }
        .valid { color: #007e00; font-weight: bold; }
        .invalid { color: #d00; font-weight: bold; }
        .footer { margin-top: 25px; text-align: center; font-size: 0.85rem; color: #777; }
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
                    
                    $encontrado = false;
                    $docInfo = []; 

                    // =========================================================
                    // PASO 1: BÚSQUEDA SIMPLE EN ADJUNTOS (ASISTENCIA)
                    // =========================================================
                    // Buscamos SOLO en t_adjunto primero. Sin JOINs que puedan fallar.
                    // Nota: Usamos 'hash_validacion' (con guion bajo)
                    $sqlAdj = "SELECT idAdjunto, t_minuta_idMinuta, pathAdjunto, tipoAdjunto 
                               FROM t_adjunto 
                               WHERE hash_validacion = :hash LIMIT 1";
                    
                    $stmtAdj = $pdo->prepare($sqlAdj);
                    $stmtAdj->execute([':hash' => $hash]);
                    $resAdj = $stmtAdj->fetch(PDO::FETCH_ASSOC);

                    if ($resAdj) {
                        $encontrado = true;
                        $idMinuta = $resAdj['t_minuta_idMinuta'];
                        
                        // Ahora recuperamos los datos de la reunión "manualmente" para evitar errores
                        // si la reunión no existe o está mal enlazada.
                        $nombreReunion = 'Reunión no especificada';
                        $fechaMinuta = 'Fecha no disponible';
                        $estadoMinuta = 'Desconocido';

                        if ($idMinuta) {
                            $sqlDet = "SELECT m.estadoMinuta, m.fechaMinuta, r.nombreReunion
                                       FROM t_minuta m
                                       LEFT JOIN t_reunion r ON m.idMinuta = r.t_minuta_idMinuta
                                       WHERE m.idMinuta = :id LIMIT 1";
                            $stmtDet = $pdo->prepare($sqlDet);
                            $stmtDet->execute([':id' => $idMinuta]);
                            $detalles = $stmtDet->fetch(PDO::FETCH_ASSOC);
                            
                            if ($detalles) {
                                $nombreReunion = $detalles['nombreReunion'] ?: 'Sin nombre de reunión';
                                $fechaMinuta = $detalles['fechaMinuta'] ? date('d/m/Y', strtotime($detalles['fechaMinuta'])) : 'N/A';
                                $estadoMinuta = $detalles['estadoMinuta'];
                            }
                        }

                        $tituloDoc = ($resAdj['tipoAdjunto'] === 'asistencia') ? 'Lista de Asistencia' : 'Documento Adjunto';

                        $docInfo = [
                            'titulo' => $tituloDoc,
                            'id' => $idMinuta,
                            'reunion' => $nombreReunion,
                            'fecha' => $fechaMinuta,
                            'estado' => $estadoMinuta,
                            'path' => $resAdj['pathAdjunto'],
                            'extra_html' => ''
                        ];
                    }

                    // =========================================================
                    // PASO 2: BÚSQUEDA SIMPLE EN MINUTAS (SI NO FUE ADJUNTO)
                    // =========================================================
                    if (!$encontrado) {
                        // Nota: Usamos 'hashValidacion' (sin guion bajo, CamelCase)
                        $sqlMin = "SELECT idMinuta, pathArchivo, fechaAprobacion, estadoMinuta, fechaMinuta
                                   FROM t_minuta
                                   WHERE hashValidacion = :hash LIMIT 1";
                        $stmtMin = $pdo->prepare($sqlMin);
                        $stmtMin->execute([':hash' => $hash]);
                        $resMin = $stmtMin->fetch(PDO::FETCH_ASSOC);

                        if ($resMin) {
                            $encontrado = true;
                            
                            // Buscar nombre reunión aparte
                            $sqlReuName = "SELECT nombreReunion FROM t_reunion WHERE t_minuta_idMinuta = :id LIMIT 1";
                            $stmtRN = $pdo->prepare($sqlReuName);
                            $stmtRN->execute([':id' => $resMin['idMinuta']]);
                            $nombreReunion = $stmtRN->fetchColumn() ?: 'Ver documento original';
                            
                            $fechaApro = $resMin['fechaAprobacion'] ? date('d/m/Y', strtotime($resMin['fechaAprobacion'])) : 'Pendiente';
                            $fechaMin = $resMin['fechaMinuta'] ? date('d/m/Y', strtotime($resMin['fechaMinuta'])) : 'N/A';

                            $docInfo = [
                                'titulo' => 'Minuta / Acta Oficial',
                                'id' => $resMin['idMinuta'],
                                'reunion' => $nombreReunion,
                                'fecha' => $fechaMin,
                                'estado' => $resMin['estadoMinuta'],
                                'path' => $resMin['pathArchivo'],
                                'extra_html' => '<p><strong>Fecha Aprobación:</strong> ' . $fechaApro . '</p>'
                            ];
                        }
                    }

                    // =========================================================
                    // MOSTRAR RESULTADOS
                    // =========================================================
                    if ($encontrado && !empty($docInfo)) {
                        echo '<div class="text-center">';
                        echo '<p class="valid">✅ Documento verificado correctamente</p>';
                        echo '<p>El documento (<strong>' . htmlspecialchars($docInfo['titulo']) . '</strong>) corresponde a la <strong>Minuta #' . htmlspecialchars($docInfo['id']) . '</strong>.</p>';
                        echo '<p><strong>Reunión:</strong> ' . htmlspecialchars($docInfo['reunion']) . '</p>';
                        echo '<p><strong>Fecha Reunión:</strong> ' . htmlspecialchars($docInfo['fecha']) . '</p>';
                        echo '<p><strong>Estado:</strong> ' . htmlspecialchars($docInfo['estado']) . '</p>';
                        
                        echo $docInfo['extra_html'];
                        
                        echo '<a href="/corevota/' . htmlspecialchars($docInfo['path']) . '" target="_blank" class="btn btn-success mt-3">📄 Ver Documento Original</a>';
                        echo '</div>';
                    } else {
                        // DEBUG: Si sigue fallando, descomenta la línea de abajo para ver qué hash está recibiendo el servidor realmente
                        // echo '<p class="text-danger small">Debug: Recibí el hash [' . htmlspecialchars($hash) . ']</p>';

                        echo '<div class="text-center">';
                        echo '<p class="invalid">❌ Código no válido o documento no encontrado</p>';
                        echo '<p>El código ingresado no corresponde a ningún documento emitido por el Consejo Regional.</p>';
                        echo '<p style="font-size: 0.8rem; color: #777;">Código buscado: ' . htmlspecialchars($hash) . '</p>';
                        echo '</div>';
                    }

                } catch (Throwable $e) {
                    echo '<div class="alert alert-danger text-center">Error del Sistema: ' . htmlspecialchars($e->getMessage()) . '</div>';
                }
            }
            ?>

            <div class="footer">
                © <?php echo date('Y'); ?> Consejo Regional de Valparaíso · Sistema COREGEDOC
            </div>
        </div>
    </div>
</body>
</html>