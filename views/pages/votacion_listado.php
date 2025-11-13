<?php
// 🚀 INICIO: LÓGICA DE FILTROS (copiada de historial_votacion.php)
require_once __DIR__ . '/../../controllers/VotacionController.php';

// Conectar a la BD para la lista de comisiones
require_once __DIR__ . "/../../class/class.conectorDB.php";
$db  = new conectorDB();
$pdo = $db->getDatabase();

/* ===== Capturar Filtros ===== */
$mes   = $_GET['mes']  ?? date('m');
$anio  = $_GET['anio'] ?? date('Y');
$comId = $_GET['comision_id'] ?? "";

/* Cargar comisiones para filtro */
$listaComisiones = [];
try {
  $st = $pdo->query("SELECT idComision, nombreComision FROM t_comision ORDER BY nombreComision ASC");
  $listaComisiones = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $listaComisiones = [];
}
// 🚀 FIN: LÓGICA DE FILTROS

// Ahora llamamos al controlador con los filtros
$controller = new VotacionController();
$filtros = [
  'mes' => $mes,
  'anio' => $anio,
  'comision_id' => $comId
];
$response = $controller->listar($filtros); // <-- 🚀 MODIFICADO
$votaciones = $response['data'] ?? [];
?>

<style>
  /* Estilos para que el formulario se vea bien */
  .card-narrow {
    max-width: 980px;
    margin: 1rem auto 2rem auto;
  }
</style>

<div class="container mt-4">

  <nav aria-label="breadcrumb" class="mb-2">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="menu.php?pagina=home">Home</a></li>
      <li class="breadcrumb-item"><a href="menu.php?pagina=votaciones_dashboard">Gestión de Votaciones</a></li>
      <li class="breadcrumb-item active" aria-current="page">Listado de Votaciones</li>
    </ol>
  </nav>


  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0">Votaciones y resultados anteriores</h2>
  </div>

  <div class="card card-narrow shadow-sm">
    <div class="card-body">
      <h5 class="mb-3">Filtrar Resultados</h5>

      <form method="get" class="row g-3">
        <input type="hidden" name="pagina" value="votacion_listado">

        <div class="col-md-2">
          <label class="form-label fw-bold">Mes</label>
          <select name="mes" class="form-select form-select-sm">
            <?php for ($m = 1; $m <= 12; $m++): $val = str_pad((string)$m, 2, '0', STR_PAD_LEFT); ?>
              <option value="<?= $val ?>" <?= ($val === $mes ? 'selected' : '') ?>><?= $val ?></option>
            <?php endfor; ?>
          </select>
        </div>

        <div class="col-md-2">
          <label class="form-label fw-bold">Año</label>
          <select name="anio" class="form-select form-select-sm">
            <?php $yNow = (int)date('Y');
            for ($y = $yNow; $y >= $yNow - 3; $y--): ?>
              <option value="<?= $y ?>" <?= ((string)$y === (string)$anio ? 'selected' : '') ?>><?= $y ?></option>
            <?php endfor; ?>
          </select>
        </div>

        <div class="col-md-4">
          <label class="form-label fw-bold">Comisión</label>
          <select name="comision_id" class="form-select form-select-sm">
            <option value="">-- Todas --</option>
            <?php foreach ($listaComisiones as $c): ?>
              <option value="<?= (int)$c['idComision'] ?>" <?= ($comId == $c['idComision'] ? 'selected' : '') ?>>
                <?= htmlspecialchars($c['nombreComision']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-2 d-flex align-items-end">
          <button class="btn btn-primary btn-sm w-100">
            <i class="fas fa-filter"></i> Filtrar
          </button>
        </div>
        <div class="col-md-2 d-flex align-items-end">
          <a href="menu.php?pagina=votacion_listado" class="btn btn-outline-secondary btn-sm w-100">
            <i class="fas fa-times"></i> Limpiar
          </a>
        </div>
      </form>
    </div>
  </div>
  <div class="card card-narrow shadow-sm">
    <div class="card-body">
      <?php if (empty($votaciones)): ?>
        <div class="alert alert-info">No hay votaciones registradas para los filtros seleccionados.</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-bordered table-striped table-hover align-middle">
            <thead class="table-light">
              <tr>
                <th>ID</th>
                <th>Nombre de la Adenda</th>
                <th>Comisión</th>
                <th>Fecha de Creación</th>
                <th class="text-center">Resultados</th>
                <th class="text-center">Estado</th>
                <th class="text-center">Acción</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($votaciones as $v): ?>
                <tr>
                  <td><?= $v['idVotacion'] ?></td>
                  <td><?= htmlspecialchars($v['nombreVotacion']) ?></td>
                  <td><?= htmlspecialchars($v['nombreComision']) ?></td>
                  <td>
                    <?php
                    $fecha = $v['fechaCreacion'] ? date('d-m-Y H:i', strtotime($v['fechaCreacion'])) : 'N/A';
                    echo $fecha;
                    ?>
                  </td>
                  <td class="text-center" style="min-width: 150px; white-space: nowrap;">
                    <span class="badge bg-success" title="Sí">
                      <i class="fas fa-check"></i> SÍ: <?= (int)($v['totalSi'] ?? 0) ?>
                    </span>
                    <span class="badge bg-danger" title="No">
                      <i class="fas fa-times"></i> NO: <?= (int)($v['totalNo'] ?? 0) ?>
                    </span>
                    <span class="badge bg-warning text-dark" title="Abstención">
                      <i class="fas fa-pause-circle"></i> ABS: <?= (int)($v['totalAbstencion'] ?? 0) ?>
                    </span>
                  </td>
                  <td class="text-center">
                    <?php if ($v['habilitada']): ?>
                      <span class="badge bg-success">Habilitada</span>
                    <?php else: ?>
                      <span class="badge bg-secondary">Cerrada</span>
                    <?php endif; ?>
                  </td>
                  <td class="text-center">
                    <form method="post" action="menu.php?pagina=votacion_listado" style="display:inline;">
                      <input type="hidden" name="idVotacion" value="<?= $v['idVotacion'] ?>">
                      <input type="hidden" name="nuevoEstado" value="<?= $v['habilitada'] ? 0 : 1 ?>">

                      <input type="hidden" name="mes" value="<?= htmlspecialchars($mes) ?>">
                      <input type="hidden" name="anio" value="<?= htmlspecialchars($anio) ?>">
                      <input type="hidden" name="comision_id" value="<?= htmlspecialchars($comId) ?>">

                      <button type="submit" class="btn btn-sm <?= $v['habilitada'] ? 'btn-danger' : 'btn-success' ?>">
                        <?= $v['habilitada'] ? '<i class="fas fa-lock"></i> Cerrar' : '<i class="fas fa-lock-open"></i> Habilitar' ?>
                      </button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php
// Cambiar estado (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['idVotacion'], $_POST['nuevoEstado'])) {

  // 🚀 Mantenemos los filtros al recargar
  $mesPost = $_POST['mes'] ?? date('m');
  $anioPost = $_POST['anio'] ?? date('Y');
  $comIdPost = $_POST['comision_id'] ?? '';

  $controller->cambiarEstado($_POST['idVotacion'], $_POST['nuevoEstado']);

  // Redirigimos CON los filtros
  $queryString = http_build_query([
    'pagina' => 'votacion_listado',
    'mes' => $mesPost,
    'anio' => $anioPost,
    'comision_id' => $comIdPost
  ]);
  echo "<script>window.location.href = 'menu.php?{$queryString}';</script>";
  exit;
}
?>