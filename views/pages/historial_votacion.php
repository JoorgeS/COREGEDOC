<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['idUsuario'])) {
    echo "<p style='color:red;text-align:center;margin-top:2rem;'>Debe iniciar sesión para ver su historial de votación.</p>";
    exit;
}

require_once __DIR__ . "/../../class/class.conectorDB.php";
$db  = new conectorDB();
$pdo = $db->getDatabase();

$idUsuarioLogueado = (int)($_SESSION['idUsuario'] ?? 0);
if ($idUsuarioLogueado <= 0) {
    echo "<p style='color:red;text-align:center;margin-top:2rem;'>Sesión inválida.</p>";
    exit;
}

/** Helpers de introspección de esquema */
function columnExists(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare("SHOW COLUMNS FROM {$table} LIKE :col");
    $stmt->execute([':col' => $column]);
    return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
}
function tableExists(PDO $pdo, string $table): bool {
    try {
        $pdo->query("SELECT 1 FROM {$table} LIMIT 1");
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

/* ===== Filtros ===== */
$mes   = $_GET['mes']  ?? date('m');
$anio  = $_GET['anio'] ?? date('Y');
$comId = $_GET['comision_id'] ?? "";

$mesInt  = (int)$mes;
$anioInt = (int)$anio;

$inicioMes = sprintf('%04d-%02d-01 00:00:00', $anioInt, $mesInt);
$finMes    = date('Y-m-t 23:59:59', strtotime($inicioMes));

/* Cargar comisiones para filtro */
$listaComisiones = [];
try {
    $st = $pdo->query("SELECT idComision, nombreComision FROM t_comision ORDER BY nombreComision ASC");
    $listaComisiones = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $listaComisiones = [];
}

/* ===== Detección de esquema ===== */
$hasTablaReunion = tableExists($pdo, 't_reunion');
$hasLinkReunion  = columnExists($pdo, 't_votacion', 't_reunion_idReunion');
$hasFechaCreacion= columnExists($pdo, 't_votacion', 'fechaCreacion');

/* ===== Armar consulta principal según esquema ===== */
$params = [
    ':idUsuario' => $idUsuarioLogueado,
];

$whereRango = '';
$orderBy    = 'v.idVotacion DESC';
$fechaCampo = null;

if ($hasTablaReunion && $hasLinkReunion) {
    // Caso 1: hay relación con reuniones => filtrar por fecha de la reunión
    $sql = "
        SELECT 
            v.idVotacion,
            v.nombreVotacion,
            r.fechaInicioReunion AS fechaRef,
            c.nombreComision,
            r.idReunion,
            (
                SELECT vto.opcionVoto
                FROM t_voto vto
                WHERE vto.t_votacion_idVotacion = v.idVotacion
                  AND vto.t_usuario_idUsuario   = :idUsuario
                LIMIT 1
            ) AS miVoto
        FROM t_votacion v
        INNER JOIN t_reunion  r ON r.idReunion = v.t_reunion_idReunion
        INNER JOIN t_comision c ON c.idComision = v.idComision
        WHERE 1=1
    ";
    $whereRango = " AND r.fechaInicioReunion BETWEEN :inicio AND :fin";
    $params[':inicio'] = $inicioMes;
    $params[':fin']    = $finMes;
    $orderBy    = " r.fechaInicioReunion DESC, v.idVotacion DESC ";
    $fechaCampo = 'fechaRef';
} elseif ($hasFechaCreacion) {
    // Caso 2: no hay reuniones, pero sí fechaCreacion en votación
    $sql = "
        SELECT 
            v.idVotacion,
            v.nombreVotacion,
            v.fechaCreacion AS fechaRef,
            c.nombreComision,
            NULL AS idReunion,
            (
                SELECT vto.opcionVoto
                FROM t_voto vto
                WHERE vto.t_votacion_idVotacion = v.idVotacion
                  AND vto.t_usuario_idUsuario   = :idUsuario
                LIMIT 1
            ) AS miVoto
        FROM t_votacion v
        INNER JOIN t_comision c ON c.idComision = v.idComision
        WHERE 1=1
    ";
    $whereRango = " AND v.fechaCreacion BETWEEN :inicio AND :fin";
    $params[':inicio'] = $inicioMes;
    $params[':fin']    = $finMes;
    $orderBy    = " v.fechaCreacion DESC, v.idVotacion DESC ";
    $fechaCampo = 'fechaRef';
} else {
    // Caso 3: sin fecha para filtrar; mostrar todo por id
    $sql = "
        SELECT 
            v.idVotacion,
            v.nombreVotacion,
            NULL AS fechaRef,
            c.nombreComision,
            NULL AS idReunion,
            (
                SELECT vto.opcionVoto
                FROM t_voto vto
                WHERE vto.t_votacion_idVotacion = v.idVotacion
                  AND vto.t_usuario_idUsuario   = :idUsuario
                LIMIT 1
            ) AS miVoto
        FROM t_votacion v
        INNER JOIN t_comision c ON c.idComision = v.idComision
        WHERE 1=1
    ";
    $whereRango = ""; // no se puede filtrar por fecha
    $orderBy    = " v.idVotacion DESC ";
    $fechaCampo = 'fechaRef';
}

/* Filtro por comisión */
if (!empty($comId)) {
    $sql .= " AND v.idComision = :fCom ";
    $params[':fCom'] = (int)$comId;
}

/* Rango si aplica */
$sql .= $whereRango;

/* Orden */
$sql .= " ORDER BY {$orderBy} ";

$registros = [];
try {
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $registros = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $registros = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Historial de Votación</title>
    <link href="/corevota/public/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body.bg-light { background-color:#f5f5f5 !important; }
        .badge-voto { font-size:.8rem; padding:.35rem .55rem; border-radius:.4rem; font-weight:600; color:#fff; }
        .badge-apruebo { background:#28a745; }
        .badge-rechazo { background:#dc3545; }
        .badge-abstencion { background:#ffc107; color:#212529; }
        .badge-no-voto { background:#6c757d; }
        .card-narrow { max-width: 980px; margin: 1rem auto 2rem auto; }
        .table thead th { position: sticky; top: 0; background: #f8f9fa; z-index: 1; }
    </style>
</head>
<body class="bg-light">

<div class="card card-narrow">
  <div class="card-body">
    <h4 class="mb-3">Historial de votación</h4>

    <form method="get" class="row g-3">
      <input type="hidden" name="pagina" value="historial_votacion">

      <div class="col-md-2">
        <label class="form-label fw-bold">Mes</label>
        <select name="mes" class="form-select form-select-sm">
          <?php for ($m=1; $m<=12; $m++): $val=str_pad((string)$m,2,'0',STR_PAD_LEFT); ?>
            <option value="<?= $val ?>" <?= ($val===$mes ? 'selected':'') ?>><?= $val ?></option>
          <?php endfor; ?>
        </select>
      </div>

      <div class="col-md-2">
        <label class="form-label fw-bold">Año</label>
        <select name="anio" class="form-select form-select-sm">
          <?php $yNow=(int)date('Y'); for($y=$yNow;$y>=$yNow-3;$y--): ?>
            <option value="<?= $y ?>" <?= ((string)$y === (string)$anio ? 'selected':'') ?>><?= $y ?></option>
          <?php endfor; ?>
        </select>
      </div>

      <div class="col-md-4">
        <label class="form-label fw-bold">Comisión</label>
        <select name="comision_id" class="form-select form-select-sm">
          <option value="">-- Todas --</option>
          <?php foreach ($listaComisiones as $c): ?>
            <option value="<?= (int)$c['idComision'] ?>" <?= ($comId==$c['idComision']?'selected':'') ?>>
              <?= htmlspecialchars($c['nombreComision']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-2 d-flex align-items-end">
        <button class="btn btn-primary btn-sm w-100">Filtrar</button>
      </div>
    </form>
  </div>
</div>

<div class="card card-narrow">
  <div class="card-body">
    <h6 class="mb-3 text-muted">
      <?php if ($hasTablaReunion && $hasLinkReunion): ?>
        Filtrando por <strong>fecha de reunión</strong>.
      <?php elseif ($hasFechaCreacion): ?>
        Filtrando por <strong>fecha de creación de la votación</strong>.
      <?php else: ?>
        Sin campo de fecha disponible; mostrando todas las votaciones.
      <?php endif; ?>
    </h6>

    <div class="table-responsive" style="max-height:65vh;">
      <table class="table table-sm align-middle">
        <thead class="table-light">
          <tr>
            <th>Comisión</th>
            <th>Votación</th>
            <th>Fecha</th>
            <th>Hora</th>
            <th>Mi Voto</th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($registros)): ?>
          <tr>
            <td colspan="5" class="text-center text-muted py-4">Sin registros para el período seleccionado.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($registros as $r):
            $ts = $r['fechaRef'] ? strtotime($r['fechaRef']) : false;
            $fecha = $ts ? date('d-m-Y', $ts) : '-';
            $hora  = $ts ? date('H:i',   $ts) : '-';
            $mi    = $r['miVoto'] ?? null;
          ?>
            <tr>
              <td><?= htmlspecialchars($r['nombreComision'] ?? 'N/D') ?></td>
              <td><?= htmlspecialchars($r['nombreVotacion'] ?? 'Sin nombre') ?></td>
              <td><?= htmlspecialchars($fecha) ?></td>
              <td><?= htmlspecialchars($hora) ?></td>
              <td>
                
                <?php if ($mi === 'SI'): // <-- CAMBIADO DE 'APRUEBO' ?>
                    <span class="badge-voto badge-apruebo">Sí</span>
                
                <?php elseif ($mi === 'NO'): // <-- CAMBIADO DE 'RECHAZO' ?>
                    <span class="badge-voto badge-rechazo">No</span>
                
                <?php elseif ($mi === 'ABSTENCION'): // <-- Este estaba correcto ?>
                    <span class="badge-voto badge-abstencion">Abstención</span>
                
                <?php else: // Muestra "No Votó" para NULL o cualquier otro valor ?>
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