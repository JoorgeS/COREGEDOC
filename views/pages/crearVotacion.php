<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['idUsuario'])) {
  header("Location: /corevota/views/pages/login.php");
  exit;
}

require_once __DIR__ . '/../../controllers/VotacionController.php';
$controller = new VotacionController();

$mensaje = '';
$error = '';

// Mostrar mensaje tras redirección
if (isset($_GET['success']) && $_GET['success'] == 1) {
  $mensaje = "Votación creada correctamente.";
}

// Guardar votación
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $response = $controller->storeVotacion($_POST);

  if ($response['status'] === 'success') {
  echo "<script>
          window.location.href = 'menu.php?pagina=crearVotacion&success=1';
        </script>";
  exit;

  } else {
    $error = $response['message'];
  }
}
?>

<div class="container mt-4">
  <h3 class="mb-4">Crear Nueva Votación</h3>

  <?php if ($mensaje): ?>
    <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
  <?php endif; ?>

  <?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <div class="card shadow-sm">
    <div class="card-body">
      <form method="post" action="menu.php?pagina=crearVotacion">

        <!-- Comisión -->
        <div class="mb-3">
          <label for="idComision" class="form-label fw-semibold">Comisión Principal *</label>
          <select class="form-select" id="idComision" name="t_comision_idComision" required>
            <option value="">Cargando comisiones...</option>
          </select>
        </div>

        <!-- Nombre de la votación -->
        <div class="mb-3">
          <label for="nombreVotacion" class="form-label fw-semibold">Nombre de la Votación *</label>
          <input type="text" class="form-control" id="nombreVotacion" name="nombreVotacion"
                 placeholder="Ej: Aprobación de proyecto regional" required>
        </div>

        <!-- Estado -->
        <div class="mb-3">
          <label class="form-label fw-semibold d-block">Estado de la votación</label>
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="habilitada" name="habilitada">
            <label class="form-check-label" for="habilitada">Habilitar votación</label>
          </div>
          <div class="form-text">Por defecto, la votación estará deshabilitada.</div>
        </div>

        <div class="d-flex gap-2 mt-4">
          <button type="submit" class="btn btn-primary">
            <i class="fa-solid fa-check me-2"></i>Guardar Votación
          </button>
          <a href="menu.php?pagina=votacion_listado" class="btn btn-secondary">
            <i class="fa-solid fa-xmark me-2"></i>Cancelar
          </a>
        </div>

      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", async () => {
  const selComision = document.getElementById("idComision");
  selComision.disabled = true;

  try {
    const r = await fetch("../../controllers/fetch_data.php?action=comisiones");
    const json = await r.json();

    if (json.status === "success" && Array.isArray(json.data)) {
      selComision.innerHTML = '<option value="">Seleccione una comisión...</option>' +
        json.data.map(c => `<option value="${c.idComision}">${c.nombreComision}</option>`).join('');
      selComision.disabled = false;
    } else {
      selComision.innerHTML = '<option value="">(No hay comisiones activas)</option>';
    }
  } catch (error) {
    console.error("Error cargando comisiones:", error);
    selComision.innerHTML = '<option value="">Error al cargar comisiones</option>';
  }
});
</script>
