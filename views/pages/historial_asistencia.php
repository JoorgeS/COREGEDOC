<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/../../class/class.conectorDB.php";
$db = new conectorDB();
$pdo = $db->getDatabase();

// Usuario logueado
$idUsuarioLogueado = $_SESSION['idUsuario'] ?? null;

if (!$idUsuarioLogueado) {
    echo "<p style='color:red;text-align:center;margin-top:2rem;'>Debe iniciar sesión para ver su historial de asistencia.</p>";
    exit;
}

// ===============================================
// --- INICIO: NUEVO HELPER DE PAGINACIÓN ---
// ===============================================
/**
 * Renderiza los controles de paginación
 * @param int $current La página actual
 * @param int $pages El total de páginas
 */
function renderPaginationHistorial($current, $pages)
{
    if ($pages <= 1) return;
    echo '<nav aria-label="Paginación"><ul class="pagination pagination-sm mb-0">';

    // Flecha "Anterior"
    $prevDisabled = ($current <= 1) ? 'disabled' : '';
    $qsArr = $_GET;
    $qsArr['p'] = $current - 1;
    echo "<li class=\"page-item {$prevDisabled}\"><a class=\"page-link\" href=\"?" . http_build_query($qsArr) . "\">&laquo;</a></li>";

    // Números de Página
    for ($i = 1; $i <= $pages; $i++) {
        $active = ($i === $current) ? ' active' : '';
        $qsArr = $_GET;
        $qsArr['p'] = $i;
        $qs = http_build_query($qsArr);
        echo '<li class="page-item' . $active . '"><a class="page-link" href="?' . $qs . '">' . $i . '</a></li>';
    }

    // Flecha "Siguiente"
    $nextDisabled = ($current >= $pages) ? 'disabled' : '';
    $qsArr = $_GET;
    $qsArr['p'] = $current + 1;
    echo "<li class=\"page-item {$nextDisabled}\"><a class=\"page-link\" href=\"?" . http_build_query($qsArr) . "\">&raquo;</a></li>";

    echo '</ul></nav>';
}
// ===============================================
// --- FIN: NUEVO HELPER DE PAGINACIÓN ---
// ===============================================


// === Filtros GET ===
$mesSeleccionado  = $_GET['mes']  ?? date('m'); // "01".."12"
$anioSeleccionado = $_GET['anio'] ?? date('Y'); // "2025"
$comisionFiltro   = $_GET['comision_id'] ?? "";
$page             = $_GET['p'] ?? 1; // <-- Capturamos la página actual

// Normalizamos
$mesInt  = (int)$mesSeleccionado;
$anioInt = (int)$anioSeleccionado;

// Rango del mes seleccionado
$inicioMes = sprintf('%04d-%02d-01 00:00:00', $anioInt, $mesInt);
$finMes    = date('Y-m-t 23:59:59', strtotime($inicioMes));

// === Cargar comisiones para el filtro desplegable ===
$sqlComisiones = "
    SELECT idComision, nombreComision
    FROM t_comision
    ORDER BY nombreComision ASC
";
$stCom = $pdo->prepare($sqlComisiones);
$stCom->execute();
$listaComisiones = $stCom->fetchAll(PDO::FETCH_ASSOC);

// === Query principal del historial ===
$sqlHistorial = "
SELECT 
    r.idReunion,
    r.nombreReunion,
    r.fechaInicioReunion,
    c.nombreComision,
    r.t_minuta_idMinuta AS idMinutaAsociada,

    (
        SELECT COUNT(*)
        FROM t_asistencia a
        WHERE a.t_minuta_idMinuta = r.t_minuta_idMinuta
          AND a.t_usuario_idUsuario = :idUsuario
    ) AS asistenciasUsuario
FROM t_reunion r
INNER JOIN t_comision c ON c.idComision = r.t_comision_idComision
WHERE r.fechaInicioReunion BETWEEN :inicio AND :fin
AND r.fechaInicioReunion <= NOW()
";

$params = [
    ':inicio'     => $inicioMes,
    ':fin'        => $finMes,
    ':idUsuario'  => $idUsuarioLogueado
];

// Filtro opcional por comisión
if (!empty($comisionFiltro)) {
    $sqlHistorial .= " AND r.t_comision_idComision = :comisionFiltro ";
    $params[':comisionFiltro'] = $comisionFiltro;
}

$sqlHistorial .= "
ORDER BY r.fechaInicioReunion DESC
";

$stHist = $pdo->prepare($sqlHistorial);
$stHist->execute($params);
$reuniones = $stHist->fetchAll(PDO::FETCH_ASSOC);


// ===============================================
// --- INICIO: CÁLCULOS PARA EL GRÁFICO DE LÍNEAS ---
// ===============================================

// 1. Agrupar reuniones por día
$reunionesPorDia = [];
$reunionesAsc = array_reverse($reuniones);

foreach ($reunionesAsc as $r) {
    $dia = date('j', strtotime($r['fechaInicioReunion']));

    if (!isset($reunionesPorDia[$dia])) {
        $reunionesPorDia[$dia] = ['asistio' => 0, 'total' => 0];
    }

    $reunionesPorDia[$dia]['total']++;
    if (($r['asistenciasUsuario'] ?? 0) > 0) {
        $reunionesPorDia[$dia]['asistio']++;
    }
}

// 2. Calcular porcentaje acumulado por día
$diasDelMes = cal_days_in_month(CAL_GREGORIAN, $mesInt, $anioInt);
$chartLabels = [];
$chartDataPoints = [];
$totalAsistidasAcumulado = 0;
$totalReunionesAcumulado = 0;

for ($dia = 1; $dia <= $diasDelMes; $dia++) {
    $chartLabels[] = sprintf('%02d-%s', $dia, $mesSeleccionado);

    if (isset($reunionesPorDia[$dia])) {
        $totalAsistidasAcumulado += $reunionesPorDia[$dia]['asistio'];
        $totalReunionesAcumulado += $reunionesPorDia[$dia]['total'];
    }

    $porcentajeAcumulado = 0;
    if ($totalReunionesAcumulado > 0) {
        $porcentajeAcumulado = round(($totalAsistidasAcumulado / $totalReunionesAcumulado) * 100);
    }

    $chartDataPoints[] = $porcentajeAcumulado;
}

// 5. Preparar datos finales para JavaScript
$chartData = [
    'labels' => $chartLabels,
    'data'   => $chartDataPoints,
    'totalReuniones' => $totalReunionesAcumulado
];

// ===============================================
// --- FIN: CÁLCULOS PARA EL GRÁFICO DE LÍNEAS ---
// ===============================================


// ===============================================
// --- INICIO: CÁLCULO DE PAGINACIÓN ---
// ===============================================
$totalReuniones = count($reuniones);
$perPage = 15; // Mostrar 15 por página
$totalPages = max(1, (int)ceil($totalReuniones / $perPage));
$page = max(1, min($page, $totalPages)); // Asegurar que la pág sea válida
$offset = ($page - 1) * $perPage;

// Tomamos solo la porción para la página actual
$reunionesParaTabla = array_slice($reuniones, $offset, $perPage);
// ===============================================
// --- FIN: CÁLCULO DE PAGINACIÓN ---
// ===============================================

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Historial de Asistencia</title>
    <link href="/corevota/public/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome para el ícono de limpiar -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <!-- Chart.js (para el gráfico) -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        body.bg-light {
            background-color: #f5f5f5 !important;
        }

        .badge-asistio {
            background-color: #28a745;
            color: #fff;
            font-size: .8rem;
            padding: .4rem .6rem;
            border-radius: .4rem;
            font-weight: 600;
        }

        .badge-ausente {
            background-color: #6c757d;
            color: #fff;
            font-size: .8rem;
            padding: .4rem .6rem;
            border-radius: .4rem;
            font-weight: 600;
        }

        .filtros-card,
        .grafico-card,
        .tabla-card {
            max-width: 900px;
            margin: 1rem auto 2rem auto;
        }

        .tabla-card {
            margin: 0 auto 3rem auto;
        }

        #chartContainer {
            position: relative;
            height: 300px;
            width: 100%;
        }
    </style>
</head>

<body class="bg-light">

    <div class="card filtros-card">
        <div class="card-body">
            <h4 class="card-title mb-3">Historial de asistencia</h4>

            <form method="get" class="row g-3" id="filtroAsistenciaForm">
                <input type="hidden" name="pagina" value="historial_asistencia">
                <!-- AÑADIDO: Input 'p' (página) para el reseteo de filtros -->
                <input type="hidden" name="p" id="pHidden" value="1">

                <div class="col-md-2">
                    <label class="form-label fw-bold">Mes</label>
                    <select name="mes" class="form-select">
                        <?php for ($m = 1; $m <= 12; $m++):
                            $val = str_pad($m, 2, '0', STR_PAD_LEFT); ?>
                            <option value="<?= $val ?>" <?= ($val === $mesSeleccionado ? 'selected' : '') ?>>
                                <?= $val ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label fw-bold">Año</label>
                    <select name="anio" class="form-select">
                        <?php
                        $anioActual = date('Y');
                        for ($y = $anioActual; $y >= $anioActual - 3; $y--): ?>
                            <option value="<?= $y ?>" <?= ((string)$y === (string)$anioSeleccionado ? 'selected' : '') ?>>
                                <?= $y ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="col-md-5">
                    <label class="form-label fw-bold">Comisión</label>
                    <select name="comision_id" class="form-select">
                        <option value="">-- Todas --</option>
                        <?php foreach ($listaComisiones as $com): ?>
                            <option value="<?= htmlspecialchars($com['idComision']) ?>"
                                <?= ($comisionFiltro == $com['idComision'] ? 'selected' : '') ?>>
                                <?= htmlspecialchars($com['nombreComision']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-primary w-100">Filtrar</button>
                </div>

                <div class="col-md-1 d-flex align-items-end">
                    <a href="menu.php?pagina=historial_asistencia" class="btn btn-outline-secondary w-100" title="Limpiar filtros">
                        <i class="fas fa-times"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="card grafico-card">
        <div class="card-body">
            <h5 class="card-title">Comportamiento de Asistencia (<?php echo "{$mesSeleccionado}-{$anioSeleccionado}"; ?>)</h5>

            <?php if ($chartData['totalReuniones'] === 0): ?>
                <p class="text-muted text-center py-4">No hay datos de reuniones para mostrar un gráfico.</p>
            <?php else: ?>
                <div id="chartContainer">
                    <canvas id="asistenciaChart"></canvas>
                </div>
            <?php endif; ?>
        </div>
    </div>


    <div class="card tabla-card">
        <div class="card-body">
            <h5 class="card-title">Detalle de Reuniones</h5>

            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Comisión</th>
                            <th>Reunión</th>
                            <th>Fecha</th>
                            <th>Hora</th>
                            <th>Asistencia</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reunionesParaTabla)): // Modificado 
                        ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">
                                    Sin registros en el periodo seleccionado
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($reunionesParaTabla as $r): // Modificado 
                            ?>
                                <?php
                                $asistio = ($r['asistenciasUsuario'] ?? 0) > 0;
                                $fechaFmt = '';
                                $horaFmt  = '';
                                if (!empty($r['fechaInicioReunion'])) {
                                    $ts = strtotime($r['fechaInicioReunion']);
                                    if ($ts !== false) {
                                        $fechaFmt = date('d-m-Y', $ts);
                                        $horaFmt  = date('H:i',   $ts);
                                    }
                                }
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($r['nombreComision'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($r['nombreReunion'] ?? 'Sin nombre') ?></td>
                                    <td><?= htmlspecialchars($fechaFmt) ?></td>
                                    <td><?= htmlspecialchars($horaFmt) ?></td>
                                    <td>
                                        <?php if ($asistio): ?>
                                            <span class="badge-asistio">Asistió</span>
                                        <?php else: ?>
                                            <span class="badge-ausente">Ausente</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- =============================================== -->
            <!-- --- INICIO: SECCIÓN DE PAGINACIÓN --- -->
            <!-- =============================================== -->
            <?php if ($totalReuniones > $perPage): // Solo mostrar si hay más de 1 pág 
            ?>
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <span class="text-muted small">
                        Página <?php echo $page; ?> de <?php echo $totalPages; ?> (Total: <?php echo $totalReuniones; ?> reuniones)
                    </span>
                    <?php renderPaginationHistorial($page, $totalPages); ?>
                </div>
            <?php endif; ?>
            <!-- =============================================== -->
            <!-- --- FIN: SECCIÓN DE PAGINACIÓN --- -->
            <!-- =============================================== -->

        </div>
    </div>

    <!-- === SweetAlert2 === -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <?php if (isset($_GET['asistencia']) && $_GET['asistencia'] === 'ok'): ?>
        <script>
            Swal.fire({
                icon: 'success',
                title: '¡Asistencia registrada!',
                text: 'Tu asistencia fue registrada correctamente.',
                confirmButtonColor: '#3085d6',
                confirmButtonText: 'Aceptar'
            });
        </script>
    <?php endif; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // 1. Encontrar el formulario por su ID
            const form = document.getElementById('filtroAsistenciaForm');
            // AÑADIDO: Input 'p' (página)
            const pHidden = document.getElementById('pHidden');

            // AÑADIDO: Función para resetear la pág. a 1
            function toFirstPage() {
                if (pHidden) pHidden.value = '1';
            }

            if (form) {
                // 2. Encontrar TODOS los <select> dentro de ese formulario
                const selects = form.querySelectorAll('select');

                // 3. La función que enviará el formulario
                const autoSubmitForm = function() {
                    toFirstPage(); // <-- AÑADIDO: Resetea a pág 1
                    form.submit();
                };

                // 4. Asignar el "detector de cambios" a CADA select
                selects.forEach(function(select) {
                    select.addEventListener('change', autoSubmitForm);
                });
            }


            // ===============================================
            // --- INICIO: SCRIPT PARA EL GRÁFICO DE LÍNEAS ---
            // ===============================================

            // 1. Obtener los datos desde PHP
            const chartData = <?php echo json_encode($chartData); ?>;

            // 2. Verificar si hay datos para el gráfico
            if (chartData.totalReuniones > 0) {
                // 3. Obtener el lienzo (canvas)
                const ctx = document.getElementById('asistenciaChart');

                if (ctx) {
                    // 4. Crear el gráfico
                    new Chart(ctx, {
                        type: 'line', // <-- CAMBIADO A LÍNEA
                        data: {
                            labels: chartData.labels, // Eje X (Días: "01-11", "02-11"...)
                            datasets: [{
                                label: 'Asistencia Acumulada',
                                data: chartData.data, // Eje Y (Porcentaje: 0, 50, 75...)
                                backgroundColor: 'rgba(40, 167, 69, 0.1)', // Relleno verde suave
                                borderColor: '#28a745', // Línea verde sólida
                                borderWidth: 2,
                                fill: true, // Rellenar área bajo la línea
                                tension: 0.1 // Ligeramente curvada
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false, // Se adapta al alto del div
                            plugins: {
                                legend: {
                                    display: false // Ocultamos la leyenda (solo hay 1 dato)
                                },
                                title: {
                                    display: false // El título ya está en el HTML
                                },
                                tooltip: {
                                    callbacks: {
                                        // Formatear el texto del tooltip
                                        label: function(context) {
                                            let label = context.dataset.label || '';
                                            let value = context.parsed.y;
                                            return `${label}: ${value}%`;
                                        }
                                    }
                                }
                            },
                            scales: {
                                y: { // Eje Y (Porcentaje)
                                    beginAtZero: true,
                                    max: 100, // Forzar el eje Y a 100%
                                    ticks: {
                                        // Añadir el símbolo "%" a las etiquetas
                                        callback: function(value) {
                                            return value + '%';
                                        }
                                    }
                                },
                                x: { // Eje X (Días)
                                    title: {
                                        display: true,
                                        text: 'Días del Mes (<?php echo "{$mesSeleccionado}-{$anioSeleccionado}"; ?>)'
                                    }
                                }
                            }
                        }
                    });
                }
            }
            // ===============================================
            // --- FIN: SCRIPT PARA EL GRÁFICO ---
            // ===============================================

        });
    </script>
</body>

</html>