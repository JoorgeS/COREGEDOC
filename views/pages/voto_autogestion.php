<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
if (!isset($_SESSION['idUsuario'])) {
  header("Location: /corevota/views/pages/login.php");
  exit;
}

require_once __DIR__ . '/../../class/class.conectorDB.php';
require_once __DIR__ . '/../../controllers/VotacionController.php';
require_once __DIR__ . '/../../controllers/VotoController.php'; // ruta correcta

$db = new conectorDB();
$pdo = $db->getDatabase();
$votacionCtrl = new VotacionController();
$votoCtrl = new VotoController();

$idUsuario = $_SESSION['idUsuario'];

// --- REGISTRO DE VOTO (vía fetch POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['idVotacion'], $_POST['opcionVoto'])) {
  header('Content-Type: application/json');
  $idVotacion = $_POST['idVotacion'];
  $opcionVoto = $_POST['opcionVoto'];

  // 1️⃣ Verificar si ya votó
  $sqlCheck = "SELECT COUNT(*) FROM t_voto 
             WHERE t_usuario_idUsuario = :idUsuario AND t_votacion_idVotacion = :idVotacion";
  $stmt = $pdo->prepare($sqlCheck);
  $stmt->execute([':idUsuario' => $idUsuario, ':idVotacion' => $idVotacion]);
  if ($stmt->fetchColumn() > 0) {
    echo json_encode(['status' => 'duplicate', 'message' => 'Ya registraste tu voto.']);
    exit;
  }

  // 2️⃣ Registrar voto
  $response = $votoCtrl->registrarVoto((int)$idVotacion, (int)$idUsuario, (string)$opcionVoto);
  echo json_encode($response);
  exit;
}

$votaciones = $votacionCtrl->listar()['data'] ?? [];
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="container mt-5">
  <h3 class="fw-bold text-success mb-4">
    <i class="fa-solid fa-square-check me-2"></i>Registrar Votación
  </h3>

  <?php
  $habilitadas = array_filter($votaciones, fn($v) => (int)$v['habilitada'] === 1);
  if (empty($habilitadas)): ?>
    <div class="alert alert-info shadow-sm">
      <i class="fa-solid fa-info-circle me-2"></i>
      No hay votaciones habilitadas en este momento.
    </div>
  <?php else: ?>
    <div class="row g-4">
      <?php foreach ($habilitadas as $v):
        // verificar si el usuario ya votó esta votación
        $sqlCheck = "SELECT opcionVoto FROM t_voto 
                     WHERE t_usuario_idUsuario = :idUsuario AND t_votacion_idVotacion = :idVotacion";
        $stmt = $pdo->prepare($sqlCheck);
        $stmt->execute([':idUsuario' => $idUsuario, ':idVotacion' => $v['idVotacion']]);
        $votoPrevio = $stmt->fetchColumn();
        $yaVoto = !empty($votoPrevio);
      ?>
        <div class="col-md-6">
          <div class="card shadow-sm border-0 rounded-4">
            <div class="card-body text-center">
              <h4 class="fw-bold mb-1"><?= htmlspecialchars($v['nombreVotacion']) ?></h4>
              <p class="mb-3 text-muted">
                <i class="fa-solid fa-landmark me-2 text-success"></i>
                Comisión: <strong><?= htmlspecialchars($v['nombreComision'] ?? 'No definida') ?></strong>
              </p>

              <?php if ($yaVoto): ?>
                <div class="alert alert-success fw-semibold py-2">
                  <i class="fa-solid fa-check-circle me-2"></i>
                  Ya emitiste tu voto: <strong><?= strtoupper($votoPrevio) ?></strong>
                </div>
              <?php else: ?>
                <form method="post" class="form-voto" data-nombre="<?= htmlspecialchars($v['nombreVotacion']) ?>">
                  <input type="hidden" name="idVotacion" value="<?= $v['idVotacion'] ?>">
                  <input type="hidden" name="opcionVoto" value="">
                  <div class="d-flex justify-content-center gap-4">
                    <button type="button" class="btn btn-success btn-lg voto-btn px-4 py-2 fw-semibold" data-value="SI">SÍ</button>
                    <button type="button" class="btn btn-danger btn-lg voto-btn px-4 py-2 fw-semibold" data-value="NO">NO</button>
                    <button type="button" class="btn btn-secondary btn-lg voto-btn px-4 py-2 fw-semibold" data-value="ABSTENCION">ABS</button>
                  </div>
                </form>
              <?php endif; ?>
              <div class="mt-3">
                <!-- Agrego clase para que el JS pueda tomar el destino real -->
                <a href="menu.php?pagina=tabla_votacion&idVotacion=<?= $v['idVotacion'] ?>"
                   class="btn btn-outline-success btn-sm fw-semibold js-resumen-url">
                  <i class="fa-solid fa-chart-simple me-1"></i> Ver resultados
                </a>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<script>
  // Enviar voto con validación visual
  document.querySelectorAll('.voto-btn').forEach(btn => {
    btn.addEventListener('click', function() {
      const form = this.closest('.form-voto');
      const nombre = form.dataset.nombre;
      const opcion = this.dataset.value;

      Swal.fire({
        title: `¿Confirmas tu voto "${opcion}"?`,
        text: `Votación: ${nombre}`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#198754',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, votar',
        cancelButtonText: 'Cancelar'
      }).then(result => {
        if (result.isConfirmed) {
          const formData = new FormData();
          formData.append('idVotacion', form.querySelector('input[name="idVotacion"]').value);
          formData.append('opcionVoto', opcion);

          fetch('voto_autogestion.php', {
              method: 'POST',
              body: formData
            })
            .then(response => response.json())
            .then(resp => {
              if (resp.status === 'duplicate') {
                Swal.fire({
                  icon: 'warning',
                  title: '⚠️ Ya registraste tu voto',
                  text: 'No puedes votar nuevamente en esta votación.',
                  confirmButtonColor: '#198754'
                });
              } else if (resp.status === 'success') {
                Swal.fire({
                  icon: 'success',
                  title: '✅ Voto registrado correctamente',
                  text: 'Redirigiendo al resumen...',
                  showConfirmButton: false,
                  timer: 1600
                });
                setTimeout(() => {
                  const id = form.querySelector('input[name="idVotacion"]').value;

                  // 1) Intentar usar el mismo destino del botón "Ver resultados"
                  const contenedor = form.closest('.card-body') || document;
                  const linkResumen = contenedor.querySelector('.js-resumen-url');
                  const hrefResumen = linkResumen ? linkResumen.getAttribute('href') : null;

                  // 2) Fallback confiable al router que ya funciona
                  const destino = hrefResumen || `menu.php?pagina=tabla_votacion&idVotacion=${id}`;

                  window.location.href = destino;
                }, 1600);
              } else {
                Swal.fire({
                  icon: 'error',
                  title: 'Error al registrar voto',
                  text: resp.message || 'Inténtalo nuevamente.',
                  confirmButtonColor: '#198754'
                });
              }
            })
            .catch(() => {
              Swal.fire({
                icon: 'error',
                title: 'Error de conexión',
                text: 'No se pudo comunicar con el servidor.',
                confirmButtonColor: '#198754'
              });
            });
        }
      });
    });
  });
</script>

<style>
  .voto-btn {
    width: 100px;
    border-radius: 10px;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
  }

  .voto-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
  }

  .card {
    background-color: #fff;
    transition: transform 0.2s ease;
  }

  .card:hover {
    transform: translateY(-4px);
  }

  .alert-success {
    background-color: #e6f7ec;
    border: 1px solid #198754;
    color: #155d2d;
  }

  .btn-outline-success {
    border-width: 2px;
    border-color: #198754;
    color: #198754;
  }

  .btn-outline-success:hover {
    background-color: #198754;
    color: #fff;
  }
</style>
