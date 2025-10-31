<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// CONEXIÓN A LA BBDD
require_once __DIR__ . "/../../class/class.conectorDB.php";
$db = new conectorDB();
$pdo = $db->getDatabase(); 

// Usuario logueado
$idUsuarioLogueado = $_SESSION['idUsuario'] ?? null;

if (!$idUsuarioLogueado) {
    echo "<p style='color:red;text-align:center;margin-top:2rem;'>Debe iniciar sesión para ver su historial de votación.</p>";
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

// === Query principal del historial de VOTACIÓN ===
$sqlHistorial = "
SELECT 
    v.idVotacion,
    v.nombreVotacion,  /* <-- CORRECCIÓN #1 (confirmado por image_f42688.png) */
    r.fechaInicioReunion,
    c.nombreComision,
    r.idReunion,
    
    (
        SELECT vto.opcionVoto
        FROM t_voto vto
        WHERE vto.t_votacion_idVotacion = v.idVotacion  /* <-- CORRECCIÓN #2 (confirmado por image_f47899.png) */
          AND vto.t_usuario_idUsuario = :idUsuario
        LIMIT 1
    ) AS miVoto
FROM t_votacion v
/* Unimos la votación con la reunión */
INNER JOIN t_reunion r ON v.t_reunion_idReunion = r.idReunion
/* Unimos la votación con la comisión */
INNER JOIN t_comision c ON v.idComision = c.idComision
WHERE r.fechaInicioReunion BETWEEN :inicio AND :fin
";

$params = [
    ':inicio'   => $inicioMes,
    ':fin'      => $finMes,
    ':idUsuario' => $idUsuarioLogueado
];

// Filtro opcional por comisión
if (!empty($comisionFiltro)) {
    // Usamos el idComision de la tabla t_votacion (v)
    $sqlHistorial .= " AND v.idComision = :comisionFiltro "; 
    $params[':comisionFiltro'] = $comisionFiltro;
}

$sqlHistorial .= "
ORDER BY r.fechaInicioReunion DESC, v.idVotacion DESC
";

// Esta era la línea que fallaba
$stHist = $pdo->prepare($sqlHistorial);
$stHist->execute($params);
$votaciones = $stHist->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Historial de Votación</title>
    <style>
        body.bg-light { background-color:#f5f5f5 !important; }
        
        /* Definimos los estilos para los votos */
        .badge-voto {
            font-size:.8rem;
            padding:.4rem .6rem;
            border-radius:.4rem;
            font-weight:600;
            color: #fff;
        }
        .badge-apruebo {
            background-color:#28a745; /* verde */
        }
        .badge-rechazo {
            background-color:#dc3545; /* rojo */
        }
        .badge-abstencion {
            background-color:#ffc107; /* amarillo */
            color: #212529; /* texto oscuro para amarillo */
        }
        .badge-no-voto {
            background-color:#6c757d; /* gris */
        }

        .filtros-card {
            max-width:900px;
            margin:1rem auto 2rem auto;
        }
        .tabla-card {
            max-width:900px;
            margin:0 auto 3rem auto;
        }
    </style>
</head>
<body class="bg-light">

<div class="card filtros-card">
    <div class="card-body">
        <h4 class="card-title mb-3">Historial de votación</h4>

        <form method="get" class="row g-3">
            <input type="hidden" name="pagina" value="historial_votacion">

            <div class="col-md-2">
                <label class="form-label fw-bold">Mes</label>
                <select name="mes" class="form-select">
                    <?php for ($m=1; $m<=12; $m++):
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
                    for ($y = $anioActual; $y >= $anioActual-3; $y--): ?>
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
                        <th>Nombre Votación</th> <th>Fecha</th>
                        <th>Hora</th>
                        <th>Mi Voto</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($votaciones)): ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">
                            Sin registros de votación en el periodo seleccionado
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($votaciones as $v): ?>
                        <?php
                        // Obtenemos el voto: 'APRUEBO', 'RECHAZO', 'ABSTENCION' o null
                        $miVoto = $v['miVoto'] ?? null;

                        // fecha y hora desde fechaInicioReunion
                        $fechaFmt = '';
                        $horaFmt  = '';
                        if (!empty($v['fechaInicioReunion'])) {
                            $ts = strtotime($v['fechaInicioReunion']);
                            if ($ts !== false) {
                                $fechaFmt = date('d-m-Y', $ts);
                                $horaFmt  = date('H:i',   $ts);
                            }
                        }
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($v['nombreComision'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($v['nombreVotacion'] ?? 'Sin nombre') ?></td>
                            <td><?= htmlspecialchars($fechaFmt) ?></td>
                            <td><?= htmlspecialchars($horaFmt) ?></td>
                            <td>
                                <?php if ($miVoto === 'APRUEBO'): ?>
                                    <span class="badge-voto badge-apruebo">Apruebo</span>
                                <?php elseif ($miVoto === 'RECHAZO'): ?>
                                    <span class="badge-voto badge-rechazo">Rechazo</span>
                                <?php elseif ($miVoto === 'ABSTENCION'): ?>
                                    <span class="badge-voto badge-abstencion">Abstención</span>
                                <?php else: ?>
                                    <span class="badge-voto badge-no-voto">No Votó</span>
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

</body>
</html>