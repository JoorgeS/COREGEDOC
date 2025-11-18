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

// === Filtros GET ===
$mesSeleccionado  = $_GET['mes']  ?? date('m'); // "01".."12"
$anioSeleccionado = $_GET['anio'] ?? date('Y'); // "2025"
$comisionFiltro   = $_GET['comision_id'] ?? "";

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
//
// Lógica:
// - t_reunion tiene la reunión (nombre, fechaInicioReunion, t_comision_idComision, t_minuta_idMinuta)
// - t_comision tiene el nombre de la comisión
// - t_asistencia marca la asistencia del usuario a la minuta
//
// asistenciasUsuario = cuántos registros de asistencia existen para ESTA reunión y ESTE usuario.
// Si >0 => asistió, si =0 => ausente.
//

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

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Historial de Asistencia</title>
    <link href="/corevota/public/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
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

        .filtros-card {
            max-width: 900px;
            margin: 1rem auto 2rem auto;
        }

        .tabla-card {
            max-width: 900px;
            margin: 0 auto 3rem auto;
        }
    </style>
</head>

<body class="bg-light">

    <div class="card filtros-card">
        <div class="card-body">
            <h4 class="card-title mb-3">Historial de asistencia</h4>

            <form method="get" class="row g-3" id="filtroAsistenciaForm">
                <input type="hidden" name="pagina" value="historial_asistencia">

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

                <div class="col-md-4">
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
            </form>
        </div>
    </div>

    <div class="card tabla-card">
        <div class="card-body">
            <h5 class="card-title">Resultados</h5>

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
                        <?php if (empty($reuniones)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">
                                    Sin registros en el periodo seleccionado
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($reuniones as $r): ?>
                                <?php
                                // asistió = true si existe al menos 1 registro en t_asistencia
                                $asistio = ($r['asistenciasUsuario'] ?? 0) > 0;

                                // fecha y hora desde fechaInicioReunion
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

        </div>
    </div>

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

            if (form) {
                // 2. Encontrar TODOS los <select> dentro de ese formulario
                const selects = form.querySelectorAll('select');

                // 3. La función que enviará el formulario
                const autoSubmitForm = function() {
                    form.submit();
                };

                // 4. Asignar el "detector de cambios" a CADA select
                selects.forEach(function(select) {
                    select.addEventListener('change', autoSubmitForm);
                });
            }
        });
    </script>
</body>

</html>