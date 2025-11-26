<?php
// /coregedoc/views/pages/tablaVotacion.php

if (session_status() === PHP_SESSION_NONE) {
      session_start();
}
if (!isset($_SESSION['idUsuario'])) {
      header("Location: /coregedoc/views/pages/login.php");
      exit;
}

require_once __DIR__ . '/../../class/class.conectorDB.php';

$db = new conectorDB();
$pdo = $db->getDatabase();

$idVotacion = $_GET['idVotacion'] ?? null;
$idUsuarioLogueado = $_SESSION['idUsuario'];
$esSecretarioTecnico = ($_SESSION['tipoUsuario_id'] == 2); // 2 es ST
$error = "";

if (!$idVotacion || !is_numeric($idVotacion)) {
      $error = "<div class='alert alert-warning text-center m-4'>No se seleccionó ninguna votación.</div>";
}

// 🔹 1. Obtener Info de Votación y (MUY IMPORTANTE) el idMinuta
$sqlInfo = "SELECT v.nombreVotacion, v.t_minuta_idMinuta, c.nombreComision
            FROM t_votacion v
            LEFT JOIN t_comision c ON v.idComision = c.idComision
            WHERE v.idVotacion = :id";
$stmtInfo = $pdo->prepare($sqlInfo);
$stmtInfo->execute([':id' => $idVotacion]);
$votacion = $stmtInfo->fetch(PDO::FETCH_ASSOC);

$idMinuta = $votacion['t_minuta_idMinuta'] ?? null;
$nombreVotacion = $votacion['nombreVotacion'] ?? 'Votación desconocida';
$nombreComision = $votacion['nombreComision'] ?? 'No definida';

if (!$idMinuta && !$error) {
      $error = "<div class='alert alert-danger text-center m-4'>Error: Esta votación no está vinculada a ninguna minuta. No se pueden mostrar resultados.</div>";
}

// 🔹 2. Obtener la lista de ASISTENTES (El "padrón" de votación)
// Esta es la lista base que SIEMPRE se mostrará en la tabla.
$asistentes = [];
if (!$error) {
      try {
            $sqlAsistentes = "SELECT u.idUsuario, u.pNombre, u.aPaterno
            FROM t_asistencia a
            JOIN t_usuario u ON a.t_usuario_idUsuario = u.idUsuario
            WHERE a.t_minuta_idMinuta = :idMinuta
            AND (u.tipoUsuario_id = 1 OR u.tipoUsuario_id = 3) -- Consejeros y Presidentes de Comisión
            ORDER BY u.aPaterno ASC, u.pNombre ASC";

            $stmtCons = $pdo->prepare($sqlAsistentes);
            $stmtCons->execute([':idMinuta' => $idMinuta]);
            $asistentes = $stmtCons->fetchAll(PDO::FETCH_ASSOC);

            if (empty($asistentes)) {
                  $error = "<div class='alert alert-info text-center m-4'>Aún no hay consejeros asistentes registrados para esta minuta.</div>";
            }
      } catch (Exception $e) {
            $error = "<div class='alert alert-danger text-center m-4'>Error al cargar la lista de asistentes.</div>";
            error_log("Error en tablaVotacion.php (Asistentes): " . $e->getMessage());
      }
}
?>

<div class="container mt-4">

      <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="fw-bold text-success" id="nombre-votacion">
                  <i class="fa-solid fa-chart-simple me-2"></i>
                  <?= htmlspecialchars($nombreVotacion) ?>
            </h3>
            <a href="menu.php?pagina=voto_autogestion" class="btn btn-outline-secondary">
                  <i class="fa-solid fa-arrow-left me-2"></i>Volver
            </a>
      </div>

      <p class="text-muted mb-4">
            <i class="fa-solid fa-landmark me-2 text-success"></i>
            Comisión: <strong><?= htmlspecialchars($nombreComision) ?></strong>
      </p>

      <?php if ($error): ?>
            <?= $error // Muestra el error si ocurrió alguno 
            ?>
      <?php else: ?>

            <div class="d-flex justify-content-center gap-4 mb-4 text-center fw-bold fs-5" id="resumen-votos">
                  <div class="text-success">SÍ: <span id="total-si">0</span></div>
                  <div class="text-danger">NO: <span id="total-no">0</span></div>
                  <div class="text-secondary">ABSTENCIÓN: <span id="total-abstencion">0</span></div>
                  <div class="text-dark">SIN VOTAR: <span id="total-sin-votar">...</span></div>
                  <div class="text-info">MI VOTO: <span id="mi-voto" class="badge bg-light text-dark">...</span></div>
            </div>

            <?php if ($esSecretarioTecnico): ?>
            <div class="row">
                  <?php
                  $mitad = ceil(count($asistentes) / 2);
                  $col1 = array_slice($asistentes, 0, $mitad);
                  $col2 = array_slice($asistentes, $mitad);
                  $columnas = [$col1, $col2];
                  ?>

                  <?php foreach ($columnas as $colIndex => $grupo): ?>
                        <div class="col-md-6">
                              <table class="table table-bordered text-center mb-4">
                                    <thead class="table-success">
                                          <tr>
                                                <th>#</th>
                                                <th>Consejero Asistente</th>
                                                <th>Voto</th>
                                          </tr>
                                    </thead>
                                    <tbody>
                                          <?php foreach ($grupo as $i => $asistente): ?>
                                                <tr>
                                                      <td><?= $i + 1 + ($colIndex * $mitad) ?></td>
                                                      <td class="text-start ps-4"><?= htmlspecialchars($asistente['pNombre'] . ' ' . $asistente['aPaterno']) ?></td>

                                                      <td id="voto-user-<?= $asistente['idUsuario'] ?>" data-votante-id="<?= $asistente['idUsuario'] ?>" class="text-muted">
                                                            Cargando...
                                                      </td>
                                                </tr>
                                          <?php endforeach; ?>
                                    </tbody>
                              </table>
                        </div>
                  <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="card-footer text-muted text-center bg-light">
                  <i class="fa-solid fa-clock-rotate-left me-1"></i>
                  Actualizado automáticamente. Última recarga: <span id="hora-actualizacion">--:--:--</span>
            </div>
      <?php endif; ?>
</div>

<style>
      .table th,
      .table td {
            vertical-align: middle;
            font-size: 0.95rem;
      }

      .text-muted {
            color: #999 !important;
      }
</style>

<script>
      document.addEventListener('DOMContentLoaded', function() {

            // IDs pasados desde PHP.
            const idMinuta = <?= json_encode($idMinuta); ?>;
            const idVotacionActual = <?= json_encode($idVotacion); ?>;
            // La variable esSecretarioTecnico de PHP no se usa directamente aquí,
            // se usa la que devuelve el API (resultadoAPI.esSecretarioTecnico)

            // Si no hay minuta, no podemos hacer nada.
            if (!idMinuta) return;

            // Referencias a todos los elementos que actualizaremos
            const elTotalSi = document.getElementById('total-si');
            const elTotalNo = document.getElementById('total-no');
            const elTotalAbs = document.getElementById('total-abstencion');
            const elTotalSinVotar = document.getElementById('total-sin-votar');
            const elMiVoto = document.getElementById('mi-voto');
            const elHoraActualizacion = document.getElementById('hora-actualizacion');

            // Obtenemos TODAS las celdas de voto en un objeto para acceso rápido
            const celdasVotos = {};
            document.querySelectorAll('[data-votante-id]').forEach(celda => {
                  celdasVotos[celda.dataset.votanteId] = celda;
            });

            // Función principal que llama al API
            async function actualizarResultados() {
                  try {
                        // 1. Llamamos al API (¡mucho más ligero que recargar todo el HTML!)
                        const response = await fetch(`/coregedoc/controllers/obtener_resultados_votacion.php?idMinuta=${idMinuta}`);
                        if (!response.ok) throw new Error('Error de red al consultar API');

                        const resultadoAPI = await response.json();
                        if (resultadoAPI.status !== 'success') throw new Error(resultadoAPI.message);

                        // 2. Buscamos la votación actual dentro de los datos (la API devuelve todas las de la minuta)
                        const votacion = resultadoAPI.data.find(v => v.idVotacion == idVotacionActual);

                        if (!votacion) {
                              console.warn("No se encontraron datos de votación para el ID " + idVotacionActual);
                              return;
                        }

                        // 3. Actualizamos los contadores
                        elTotalSi.textContent = votacion.totalSi;
                        elTotalNo.textContent = votacion.totalNo;
                        elTotalAbs.textContent = votacion.totalAbstencion;

                        // Usamos el "faltanVotar" que SÍ calcula bien el API (Asistentes - Votos)
                        elTotalSinVotar.textContent = votacion.faltanVotar;

                        // 4. Actualizamos "Mi Voto"
                        const miId = <?= json_encode($idUsuarioLogueado); ?>;
                        if (votacion.votoPersonal) {
                              elMiVoto.textContent = votacion.votoPersonal;
                              // Asignamos colores
                              if (votacion.votoPersonal === 'SI') elMiVoto.className = 'badge bg-success';
                              else if (votacion.votoPersonal === 'NO') elMiVoto.className = 'badge bg-danger';
                              else elMiVoto.className = 'badge bg-secondary';

                              // Actualizar la celda de mi voto en la tabla para todos los usuarios
                              if (celdasVotos[miId]) {
                                    celdasVotos[miId].textContent = votacion.votoPersonal;
                                    // Quitar 'badge' y usar 'text-...' para la celda
                                    celdasVotos[miId].className = elMiVoto.className.replace('badge ', 'fw-bold ').replace('bg-', 'text-');
                              }

                        } else {
                              elMiVoto.textContent = 'PENDIENTE';
                              elMiVoto.className = 'badge bg-warning text-dark';
                              // Resetear la celda de mi voto si no he votado
                              if (celdasVotos[miId]) {
                                    celdasVotos[miId].textContent = 'Sin Votar';
                                    celdasVotos[miId].className = 'text-muted';
                              }
                        }

                        // 5. Actualizamos la TABLA
                        // Primero, reseteamos todas las celdas a "Sin Votar" (excepto el voto personal, que se maneja arriba)
                        for (const idUsuario in celdasVotos) {
                              if (idUsuario != miId) {
                                    celdasVotos[idUsuario].textContent = 'Sin Votar';
                                    celdasVotos[idUsuario].className = 'text-muted';
                              }
                        }

                        // Segundo, si eres Secretario Técnico, llenamos la tabla con todos los votos
                        if (resultadoAPI.esSecretarioTecnico && votacion.votos) {
                              votacion.votos.forEach(voto => {
                                    const idVotante = voto.t_usuario_idUsuario; // (Asumiendo que el API lo envía)

                                    if (idVotante && celdasVotos[idVotante]) {
                                          const celda = celdasVotos[idVotante];
                                          celda.textContent = voto.opcionVoto;

                                          if (voto.opcionVoto === 'SI') celda.className = 'text-success fw-bold';
                                          else if (voto.opcionVoto === 'NO') celda.className = 'text-danger fw-bold';
                                          else if (voto.opcionVoto === 'ABSTENCION') celda.className = 'text-secondary fw-bold';
                                    }
                              });
                        }


                        // 6. Actualizamos la hora
                        elHoraActualizacion.textContent = new Date().toLocaleTimeString();

                  } catch (error) {
                        console.error('Error al actualizar dashboard:', error);
                        elTotalSinVotar.textContent = 'Error';
                  }
            }

            // Carga inicial inmediata
            actualizarResultados();

            // Actualización automática cada 3 segundos (3000 ms)
            setInterval(actualizarResultados, 3000);
      });
</script>