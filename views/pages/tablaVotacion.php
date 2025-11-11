<?php
// views/pages/voto_autogestion.php

if (session_status() === PHP_SESSION_NONE) {
      session_start();
}
if (!isset($_SESSION['idUsuario'])) {
      header("Location: /corevota/views/pages/login.php");
      exit;
}

require_once __DIR__ . '/../../class/class.conectorDB.php';
// Asegúrate de que VotacionController y VotoController son clases válidas y existen
require_once __DIR__ . '/../../controllers/VotacionController.php';
require_once __DIR__ . '/../../controllers/VotoController.php';

$db = new conectorDB();
$pdo = $db->getDatabase();

$idUsuario = $_SESSION['idUsuario'];
$votacionCtrl = new VotacionController();
$votoCtrl = new VotoController();

// --- 1. LÓGICA DE PROCESAMIENTO POST (VOTO DE AUTOGESTIÓN) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['idVotacion'], $_POST['opcionVoto'])) {
      header('Content-Type: application/json');
      $idVotacion = $_POST['idVotacion'];
      $opcionVoto = $_POST['opcionVoto'];
      $response = ['status' => 'error', 'message' => 'Error al registrar voto.'];

      try {
            // A. Verificar si ya votó (Backend check)
            $sqlCheck = "SELECT COUNT(*) FROM t_voto 
                     WHERE t_usuario_idUsuario = :idUsuario AND t_votacion_idVotacion = :idVotacion";
            $stmt = $pdo->prepare($sqlCheck);
            $stmt->execute([':idUsuario' => $idUsuario, ':idVotacion' => $idVotacion]);
            if ($stmt->fetchColumn() > 0) {
                  echo json_encode(['status' => 'duplicate', 'message' => 'Ya registraste tu voto.']);
                  exit;
            }

            // B. Obtener el idMinuta asociado a la votación para verificar asistencia
            $sqlMinuta = "SELECT t_minuta_idMinuta FROM t_votacion WHERE idVotacion = :idVotacion";
            $stmtMinuta = $pdo->prepare($sqlMinuta);
            $stmtMinuta->execute([':idVotacion' => $idVotacion]);
            $idMinuta = $stmtMinuta->fetchColumn();

            if (!$idMinuta) {
                  throw new Exception('Votación no asociada a ninguna minuta para verificar asistencia.');
            }

            // C. Verificar si el usuario está presente en la minuta (t_asistencia)
            $sqlAsistencia = "SELECT COUNT(*) FROM t_asistencia 
                          WHERE t_minuta_idMinuta = :idMinuta AND t_usuario_idUsuario = :idUsuario";
            $stmtAsistencia = $pdo->prepare($sqlAsistencia);
            $stmtAsistencia->execute([':idMinuta' => $idMinuta, ':idUsuario' => $idUsuario]);

            if ($stmtAsistencia->fetchColumn() == 0) {
                  echo json_encode([
                        'status' => 'unauthorized',
                        'message' => 'No puede votar. Debe registrar su asistencia a la reunión correspondiente.'
                  ]);
                  exit;
            }

            // D. Registrar voto (Llamada al método correcto para t_votacion)
            $response = $votoCtrl->registrarVotoVotacion(
                  (int)$idVotacion,
                  (int)$idUsuario,
                  (string)$opcionVoto,
                  null // Voto de autogestión, no hay Secretario registrando
            );
            echo json_encode($response);
            exit;
      } catch (Exception $e) {
            error_log("Error en voto_autogestion (POST): " . $e->getMessage());
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            exit;
      }
}

// --- 2. LÓGICA DE CARGA DE DATOS (VISTA) ---
$votaciones = $votacionCtrl->listar()['data'] ?? [];
$votacionesHabilitadas = array_filter($votaciones, fn($v) => (int)$v['habilitada'] === 1);
$votacionVigente = reset($votacionesHabilitadas); // Tomamos la primera votación habilitada

$votoPrevio = null;
$yaVoto = false;

if ($votacionVigente) {
      // 💡 REVISIÓN DE VOTO PREVIO: Consulta directa y robusta
      $sqlCheck = "SELECT opcionVoto FROM t_voto 
                 WHERE t_usuario_idUsuario = :idUsuario AND t_votacion_idVotacion = :idVotacion
                 LIMIT 1"; // Aseguramos que solo tome un valor
      $stmt = $pdo->prepare($sqlCheck);
      $stmt->execute([':idUsuario' => $idUsuario, ':idVotacion' => $votacionVigente['idVotacion']]);
      $votoPrevio = $stmt->fetchColumn();
      $yaVoto = !empty($votoPrevio);
}
?>

<div class="container mt-5">
      <h3 class="fw-bold text-primary mb-4">
            <i class="fa-solid fa-person-booth me-2"></i> Sala de Votaciones
      </h3>

      <div class="row g-4">

            <div class="col-12">
                  <div class="card shadow-lg border-0 rounded-4">
                        <div class="card-header bg-primary text-white fw-bold fs-5">
                              <i class="fas fa-bullhorn me-2"></i> Votación Abierta
                        </div>
                        <div class="card-body py-4" id="tarjetaVotacionVigente">
                              <?php if (empty($votacionVigente)): ?>
                                    <div class="alert alert-info text-center mb-0">
                                          No hay votaciones habilitadas en este momento.
                                    </div>
                              <?php else: ?>
                                    <h4 class="fw-bold mb-2 text-dark"><?= htmlspecialchars($votacionVigente['nombreVotacion']) ?></h4>
                                    <p class="mb-4 text-muted">Comisión: <strong><?= htmlspecialchars($votacionVigente['nombreComision'] ?? 'No definida') ?></strong></p>

                                    <?php if ($yaVoto): ?>
                                          <div class="alert alert-success fw-semibold py-3 text-center">
                                                <i class="fa-solid fa-check-circle me-2"></i>
                                                Ya emitiste tu voto:
                                                <span class="badge bg-success fs-6 ms-2"><?= strtoupper($votoPrevio) ?></span>

                                                <a href="menu.php?pagina=tablaVotacion&idVotacion=<?= $votacionVigente['idVotacion'] ?>" class="btn btn-sm btn-outline-success ms-3 fw-bold">
                                                      Revisar Resumen
                                                </a>
                                          </div>
                                    <?php else: ?>
                                          <form method="post" class="form-voto text-center" data-nombre="<?= htmlspecialchars($votacionVigente['nombreVotacion']) ?>">
                                                <input type="hidden" name="idVotacion" value="<?= $votacionVigente['idVotacion'] ?>">
                                                <input type="hidden" name="opcionVoto" value="">
                                                <h5 class="mb-4">¿Cuál es tu voto?</h5>
                                                <div class="d-flex justify-content-center gap-4">
                                                      <button type="button" class="btn btn-success btn-lg voto-btn px-4 py-2 fw-semibold" data-value="SI">SÍ</button>
                                                      <button type="button" class="btn btn-danger btn-lg voto-btn px-4 py-2 fw-semibold" data-value="NO">NO</button>
                                                      <button type="button" class="btn btn-secondary btn-lg voto-btn px-4 py-2 fw-semibold" data-value="ABSTENCION">ABS</button>
                                                </div>
                                          </form>
                                    <?php endif; ?>
                              <?php endif; ?>
                        </div>
                  </div>
            </div>

            <div class="col-md-6">
                  <div class="card shadow-sm h-100">
                        <div class="card-header bg-light fw-bold">
                              <i class="fas fa-chart-bar me-2 text-info"></i> 2. Resultados de Votaciones
                        </div>
                        <div class="card-body text-center d-flex flex-column justify-content-center align-items-center">
                              <p class="text-muted">Consulta el dashboard restringido con los resultados consolidados de todas las votaciones cerradas.</p>
                              <a href="menu.php?pagina=votacion_listado&filtro_estado=CERRADA" class="btn btn-info btn-lg mt-auto" style="min-width: 250px;">
                                    <i class="fas fa-lock me-2"></i> Ver Resultados Consolidados
                              </a>
                        </div>
                  </div>
            </div>

            <div class="col-md-6">
                  <div class="card shadow-sm h-100">
                        <div class="card-header bg-light fw-bold">
                              <i class="fas fa-history me-2 text-dark"></i> 3. Mi Historial
                        </div>
                        <div class="card-body text-center d-flex flex-column justify-content-center align-items-center">
                              <p class="text-muted">Revisa un listado de todas las votaciones en las que has participado y la opción que elegiste en cada una.</p>
                              <a href="menu.php?pagina=historial_votacion&idUsuario=<?= $idUsuario ?>" class="btn btn-outline-dark btn-lg mt-auto" style="min-width: 250px;">
                                    <i class="fas fa-user-check me-2"></i> Ver Mi Historial de Votos
                              </a>
                        </div>
                  </div>
            </div>

      </div>
</div>

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
            border-radius: 0.5rem;
      }

      .card-body.py-4 {
            padding-top: 1.5rem !important;
            padding-bottom: 1.5rem !important;
      }

      .card-header.bg-primary {
            background-color: #0d6efd !important;
      }
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
      document.querySelectorAll('.voto-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                  const form = this.closest('.form-voto');
                  const nombre = form.dataset.nombre;
                  const opcion = this.dataset.value;
                  const idVotacion = form.querySelector('input[name="idVotacion"]').value;

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
                              formData.append('idVotacion', idVotacion);
                              formData.append('opcionVoto', opcion);

                              fetch('voto_autogestion.php', {
                                          method: 'POST',
                                          body: formData
                                    })
                                    .then(response => {
                                          if (!response.ok) {
                                                return response.json().catch(() => ({
                                                      status: 'error',
                                                      message: `Error de red: HTTP ${response.status}`
                                                }));
                                          }
                                          return response.json();
                                    })
                                    .then(resp => {
                                          if (resp.status === 'success') {
                                                Swal.fire({
                                                      icon: 'success',
                                                      title: '✅ Voto registrado correctamente',
                                                      text: 'Redirigiendo a su resumen...',
                                                      showConfirmButton: false,
                                                      timer: 1600
                                                });

                                                // 🚀 REDIRECCIÓN AUTOMÁTICA A LA PÁGINA DE RESULTADOS
                                                setTimeout(() => {
                                                      window.location.href = `menu.php?pagina=tablaVotacion&idVotacion=${idVotacion}`;
                                                }, 1600);

                                          } else if (resp.status === 'unauthorized') {
                                                Swal.fire({
                                                      icon: 'error',
                                                      title: '❌ Voto no permitido',
                                                      text: resp.message || 'Debe registrar su asistencia para poder votar.',
                                                      confirmButtonColor: '#dc3545'
                                                });
                                          } else if (resp.status === 'duplicate') {
                                                Swal.fire({
                                                      icon: 'warning',
                                                      title: '⚠️ Ya registraste tu voto',
                                                      text: 'No puedes votar nuevamente en esta votación.',
                                                      confirmButtonColor: '#198754'
                                                });
                                          } else {
                                                Swal.fire({
                                                      icon: 'error',
                                                      title: 'Error al registrar voto',
                                                      text: resp.message || 'Inténtalo nuevamente.',
                                                      confirmButtonColor: '#198754'
                                                });
                                          }
                                    })
                                    .catch(error => {
                                          console.error("Error en la promesa fetch:", error);
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