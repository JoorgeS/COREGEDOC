<?php
require_once __DIR__ . '/../../controllers/VotacionController.php';
$controller = new VotacionController();
$response = $controller->listar();
$votaciones = $response['data'] ?? [];
?>

<div class="container mt-4">
  <h3 class="mb-4">Listado de Votaciones</h3>

  <div class="card shadow-sm">
    <div class="card-body">
      <?php if (empty($votaciones)): ?>
        <div class="alert alert-info">No hay votaciones registradas.</div>
      <?php else: ?>
        <table class="table table-bordered table-striped">
          <thead class="table-light">
            <tr>
              <th>ID</th>
              <th>Nombre de la Adenda</th>
              <th>Comisión</th>
              <th>Fecha de Creación</th>
              <th>Estado</th>
              <th>Acción</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($votaciones as $v): ?>
              <tr>
                <td><?= $v['idVotacion'] ?></td>
                <td><?= htmlspecialchars($v['nombreVotacion']) ?></td>
                <td><?= htmlspecialchars($v['nombreComision']) ?></td>
                <td><?= $v['fechaCreacion'] ?></td>
                <td>
                  <?php if ($v['habilitada']): ?>
                    <span class="badge bg-success">Habilitada</span>
                  <?php else: ?>
                    <span class="badge bg-secondary">Deshabilitada</span>
                  <?php endif; ?>
                </td>
                <td>
                  <form method="post" action="menu.php?pagina=votacion_listado" style="display:inline;">
                    <input type="hidden" name="idVotacion" value="<?= $v['idVotacion'] ?>">
                    <input type="hidden" name="nuevoEstado" value="<?= $v['habilitada'] ? 0 : 1 ?>">
                    <button type="submit" class="btn btn-sm <?= $v['habilitada'] ? 'btn-danger' : 'btn-success' ?>">
                      <?= $v['habilitada'] ? 'Deshabilitar' : 'Habilitar' ?>
                    </button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php
// Cambiar estado (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['idVotacion'], $_POST['nuevoEstado'])) {
  $controller->cambiarEstado($_POST['idVotacion'], $_POST['nuevoEstado']);
  echo "<script>window.location.href = 'menu.php?pagina=votacion_listado';</script>";
  exit;
}
?>
