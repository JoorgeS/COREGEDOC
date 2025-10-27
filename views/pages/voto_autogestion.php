<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['idUsuario'])) {
  header("Location: /corevota/views/pages/login.php");
  exit;
}

require_once __DIR__ . '/../../controllers/VotacionController.php';
require_once __DIR__ . '/../../controllers/VotoController.php';

$votacionCtrl = new VotacionController();
$votoCtrl = new VotoController();

$idUsuario = $_SESSION['idUsuario'];
$mensaje = '';
$error = '';

// Registrar voto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['idVotacion'], $_POST['opcionVoto'])) {
  $response = $votoCtrl->registrarVoto($idUsuario, $_POST['idVotacion'], $_POST['opcionVoto']);
  if ($response['status'] === 'success') $mensaje = $response['message'];
  else $error = $response['message'];
}

// Cargar votaciones habilitadas
$votaciones = $votacionCtrl->listar()['data'];
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="container mt-5">
  <h3 class="fw-bold text-success mb-4"><i class="fa-solid fa-square-check me-2"></i>Registrar Votación</h3>

  <?php
  $habilitadas = array_filter($votaciones, fn($v) => $v['habilitada'] == 1);
  if (empty($habilitadas)): ?>
    <div class="alert alert-info shadow-sm">
      <i class="fa-solid fa-info-circle me-2"></i>
      No hay votaciones habilitadas en este momento.
    </div>
  <?php else: ?>
    <div class="row g-4">
      <?php foreach ($habilitadas as $v): ?>
        <div class="col-md-6">
          <div class="card shadow-sm border-0 rounded-4">
            <div class="card-body text-center">
              <h4 class="fw-bold mb-1"><?= htmlspecialchars($v['nombreVotacion']) ?></h4>
              <p class="mb-3 text-muted">
                <i class="fa-solid fa-landmark me-2 text-success"></i>
                Comisión: <strong><?= htmlspecialchars($v['nombreComision']) ?></strong>
              </p>
              <form method="post" class="form-voto" data-nombre="<?= htmlspecialchars($v['nombreVotacion']) ?>">
                <input type="hidden" name="idVotacion" value="<?= $v['idVotacion'] ?>">
                <input type="hidden" name="opcionVoto" value="">

                <div class="d-flex justify-content-center gap-4">
                  <button type="button" class="btn btn-success btn-lg voto-btn px-4 py-2 fw-semibold"
                          data-value="SI">SÍ</button>
                  <button type="button" class="btn btn-danger btn-lg voto-btn px-4 py-2 fw-semibold"
                          data-value="NO">NO</button>
                  <button type="button" class="btn btn-secondary btn-lg voto-btn px-4 py-2 fw-semibold"
                          data-value="ABSTENCIÓN">ABS</button>
                </div>
              </form>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<script>
// 🟢 Confirmación con SweetAlert
document.querySelectorAll('.voto-btn').forEach(btn => {
  btn.addEventListener('click', function () {
    const form = this.closest('.form-voto');
    const nombre = form.dataset.nombre;
    const opcion = this.dataset.value;
    const inputHidden = form.querySelector('input[name="opcionVoto"]');

    Swal.fire({
    title: `¿Confirmas tu voto "${opcion}"?`,
    text: `Votación: ${nombre}`,
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#198754',
    cancelButtonColor: '#6c757d',
    confirmButtonText: 'Sí, votar',
    cancelButtonText: 'Cancelar'
    }).then((result) => {
    if (result.isConfirmed) {
        const formData = new FormData();
        formData.append('idVotacion', form.querySelector('input[name="idVotacion"]').value);
        formData.append('opcionVoto', opcion);

        // Enviar el voto mediante fetch (sin recargar)
        fetch('voto_autogestion.php', {
        method: 'POST',
        body: formData
        })
        .then(res => res.text()) // no JSON porque este script también genera HTML
        .then(() => {
        Swal.fire({
            icon: 'success',
            title: '✅ Voto registrado correctamente',
            showConfirmButton: false,
            timer: 1500
        });

        // Redirige a la tabla después del voto
        setTimeout(() => {
            window.location.href = 'menu.php?pagina=tabla_votacion';
        }, 1500);
        })
        .catch(err => {
        Swal.fire({
            icon: 'error',
            title: 'Error al registrar voto',
            text: err,
            confirmButtonColor: '#198754'
        });
        });
    }
    });

  });
});

// 🟢 Mensajes post-envío
<?php if ($mensaje): ?>
Swal.fire({
  icon: 'success',
  title: '✅ <?= addslashes($mensaje) ?>',
  showConfirmButton: false,
  timer: 2000
});
<?php elseif ($error): ?>
Swal.fire({
  icon: 'error',
  title: '⚠️ <?= addslashes($error) ?>',
  confirmButtonColor: '#198754'
});
<?php endif; ?>
</script>

<style>
.voto-btn {
  width: 100px;
  border-radius: 10px;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.voto-btn:hover {
  transform: translateY(-3px);
  box-shadow: 0 4px 10px rgba(0,0,0,0.15);
}
.card {
  background-color: #fff;
  transition: transform 0.2s ease;
}
.card:hover {
  transform: translateY(-4px);
}
</style>
