<?php
// views/pages/votacion_listado.php
// LÓGICA DE FILTROS

require_once __DIR__ . '/../../controllers/VotacionController.php';

// Conectar a la BD para la lista de comisiones
require_once __DIR__ . "/../../class/class.conectorDB.php";
$db = new conectorDB();
$pdo = $db->getDatabase();

/* ===== Capturar Filtros ===== */
$mes = $_GET['mes'] ?? date('m');
$anio = $_GET['anio'] ?? date('Y');
$comId = $_GET['comision_id'] ?? "";

/* Cargar comisiones para filtro */
$listaComisiones = [];
try {
  $st = $pdo->query("SELECT idComision, nombreComision FROM t_comision ORDER BY nombreComision ASC");
  $listaComisiones = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $listaComisiones = [];
}

// --- INICIO DE LA MODIFICACIÓN 1 ---
// Esta es tu nueva lógica para obtener el universo de votantes
$universoVotantes = 0; // Default
try {
  // Contamos todos los usuarios que DEBERÍAN votar (roles 1, 3, 7)
  $stUniverso = $pdo->query("SELECT COUNT(*) FROM t_usuario WHERE tipoUsuario_id IN (1, 3, 7)");
  $universoVotantes = (int)$stUniverso->fetchColumn();
} catch (Throwable $e) {
  error_log("Error al contar universo de votantes: " . $e->getMessage());
  $universoVotantes = 0; // Si hay error, la participación será 0
}
// --- FIN DE LA MODIFICACIÓN 1 ---


// 🚀 FIN: LÓGICA DE FILTROS

// Ahora llamamos al controlador con los filtros
$controller = new VotacionController();
$filtros = [
  'mes' => $mes,
  'anio' => $anio,
  'comision_id' => $comId
];
$response = $controller->listar($filtros);
$votaciones = $response['data'] ?? [];


/* =========================
   FILTRADO EN VISTA (Solo por Palabra Clave)
   ========================= */

// 1. Palabra Clave (para Nombre Reunión)
$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';

/* =========================
   FILTRADO EN VISTA
   ========================= */
// Empezamos con los datos YA FILTRADOS por el controlador (mes, año, comision)
$reunionesFiltradas = $votaciones;

// --- A. Filtro por Palabra Clave ---
if ($q !== '') {
  $needle = mb_strtolower($q, 'UTF-8');
  $reunionesFiltradas = array_filter($reunionesFiltradas, function ($r) use ($needle) {
    $nombre = mb_strtolower((string)($r['nombreVotacion'] ?? ''), 'UTF-8');
    return (strpos($nombre, $needle) !== false);
  });
}

$votacionesFiltradas = array_values($reunionesFiltradas);


/* =========================
   PAGINACIÓN
   ========================= */
$perPage = 10;
$total = count($votacionesFiltradas);
$pages = max(1, (int)ceil($total / $perPage));
$page = isset($_GET['p']) ? (int)$_GET['p'] : 1;
$page = max(1, min($page, $pages));
$offset = ($page - 1) * $perPage;

// Subconjunto a mostrar
$votacionesPage = array_slice($votacionesFiltradas, $offset, $perPage);

// Helper para paginación
function renderPagination($current, $pages)
{
  if ($pages <= 1) return;
  echo '<nav aria-label="PaginACIÓN"><ul class="pagination pagination-sm mb-0">';
  for ($i = 1; $i <= $pages; $i++) {
    $qsArr = $_GET;
    $qsArr['p'] = $i;
    $qs = http_build_query($qsArr);
    $active = ($i === $current) ? ' active' : '';
    echo '<li class="page-item' . $active . '"><a class="page-link" href="?' . $qs . '">' . $i . '</a></li>';
  }
  echo '</ul></nav>';
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <title>Listado de Votaciones</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="/corevota/public/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <style>
    /* ... Estilos CSS ... */
    .card-narrow {
      max-width: 1500px;
      margin: 1rem auto 2rem auto;
      border-top: 5px solid #1c88bf;
    }

    .filters-card h5 {
      font-size: 1.25rem;
      color: #495057;
      border-bottom: 1px dashed #dee2e6;
      padding-bottom: 10px;
      margin-bottom: 15px !important;
    }

    .table-responsive {
      margin-top: 20px;
    }

    .table th,
    .table td {
      vertical-align: middle;
    }

    .filters-card {
      border: 1px solid #e5e7eb;
      border-radius: .5rem;
      background: #f8fafc
    }

    .sticky-th thead th {
      position: sticky;
      top: 0;
      z-index: 1
    }
  </style>
</head>

<body>

  <nav aria-label="breadcrumb" class="mb-2">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="menu.php?pagina=home">Home</a></li>
      <li class="breadcrumb-item"><a href="menu.php?pagina=votaciones_dashboard">Gestión de Votaciones</a></li>
      <li class="breadcrumb-item active" aria-current="page">Listado de Votaciones</li>
    </ol>
  </nav>

  <h3 class="mb-3">Votaciones y resultados anteriores</h3>

  <div class="card card-narrow shadow-sm">
    <div class="card-body">
      <h5 class="mb-3">Filtrar Resultados</h5>

      <form id="filtrosForm" method="GET" class="row g-3">
        <input type="hidden" name="pagina" value="votacion_listado">
        <input type="hidden" name="p" id="pHidden" value="1">
        <div class="row g-3 align-items-end">

          <div class="col-md-2">
            <label class="form-label fw-bold">Mes</label>
            <select name="mes" class="form-select form-select-sm" id="mes_select">
              <?php for ($m = 1; $m <= 12; $m++): $val = str_pad((string)$m, 2, '0', STR_PAD_LEFT); ?>
                <option value="<?= $val ?>" <?= ($val === $mes ? 'selected' : '') ?>><?= $val ?></option>
              <?php endfor; ?>
            </select>
          </div>

          <div class="col-md-2">
            <label class="form-label fw-bold">Año</label>
            <select name="anio" class="form-select form-select-sm" id="anio_select">
              <?php $yNow = (int)date('Y');
              for ($y = $yNow; $y >= $yNow - 3; $y--): ?>
                <option value="<?= $y ?>" <?= ((string)$y === (string)$anio ? 'selected' : '') ?>><?= $y ?></option>
              <?php endfor; ?>
            </select>
          </div>

          <div class="col-md-4">
            <label class="form-label fw-bold">Comisión</label>
            <select name="comision_id" class="form-select form-select-sm" id="comision_id_select">
              <option value="">-- Todas --</option>
              <?php foreach ($listaComisiones as $c): ?>
                <option value="<?= (int)$c['idComision'] ?>" <?= ($comId == $c['idComision'] ? 'selected' : '') ?>>
                  <?= htmlspecialchars($c['nombreComision']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-2">
            <label class="form-label fw-bold">Palabra Clave</label>
            <input type="text" class="form-control form-control-sm" id="q" name="q" placeholder="Buscar..." value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>">
          </div>

          <div class="col-md-1">
            <button type="submit" class="btn btn-primary btn-sm w-100">Filtrar</button>
          </div>
          <div class="col-md-1">
            <a href="menu.php?pagina=votacion_listado" class="btn btn-outline-secondary btn-sm w-100">
              <i class="fas fa-times"></i> Limpiar
            </a>
          </div>
        </div>
      </form>
    </div>
  </div>

  <div class="card card-narrow shadow-sm">
    <div class="card-body">
      <?php if (empty($votacionesPage)): ?>
        <div class="alert alert-info">No se encontraron votaciones registradas para los filtros seleccionados.</div>
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
                <th class="text-center">Participación</th>
                <th class="text-center">Detalle</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($votacionesPage as $v): ?>
                <?php
                // --- Cálculo de Votos Totales ---
                $totalVotos = (int)($v['totalSi'] ?? 0) + (int)($v['totalNo'] ?? 0) + (int)($v['totalAbstencion'] ?? 0);
                ?>
                <tr>
                  <td><strong><?= $v['idVotacion'] ?></strong></td>
                  <td><?= htmlspecialchars($v['nombreVotacion']) ?></td>
                  <td><?= htmlspecialchars($v['nombreComision']) ?></td>
                  <td>
                    <?php
                    $fecha = $v['fechaCreacion'] ? date('d-m-Y H:i', strtotime($v['fechaCreacion'])) : 'N/A';
                    echo $fecha;
                    ?>
                  </td>

                  <td class="text-center">
                    <?php
                    // --- Lógica de Resultado ---
                    $si = (int)($v['totalSi'] ?? 0);
                    $no = (int)($v['totalNo'] ?? 0);
                    $abs = (int)($v['totalAbstencion'] ?? 0);

                    $statusText = '';
                    $statusClass = '';

                    if ($si > $no) {
                      $statusText = 'APROBADA';
                      $statusClass = 'bg-success';
                    } elseif ($no > $si) {
                      $statusText = 'RECHAZADA';
                      $statusClass = 'bg-danger';
                    } else {
                      $statusText = 'EMPATE';
                      $statusClass = 'bg-secondary';
                    }
                    ?>

                    <span class="badge <?= $statusClass ?> fw-bold" style="font-size: 0.9rem;"><?= $statusText ?></span>
                  </td>

                  <td style="min-width: 150px;">
                    <?php
                    // --- Lógica de Participación ---
                    $porcentajeParticipacion = 0;
                    if ($universoVotantes > 0) {
                      $porcentajeParticipacion = round(($totalVotos / $universoVotantes) * 100);
                    }
                    ?>

                    <small class="d-block mb-1 text-muted">Participación: <strong><?= $porcentajeParticipacion ?>%</strong></small>

                    <div class="progress" style="height: 10px;">
                      <div class="progress-bar bg-info" role="progressbar"
                        style="width: <?= $porcentajeParticipacion ?>%"
                        aria-valuenow="<?= $porcentajeParticipacion ?>"
                        aria-valuemin="0" aria-valuemax="100">
                      </div>
                    </div>

                    <small class="d-block text-end mt-1 text-muted">
                      <?= $totalVotos ?> de <?= $universoVotantes ?> votaron
                    </small>
                  </td>
                  <td class="text-center">
                    <button type="button" class="btn btn-sm btn-info"
                      onclick="mostrarDetalleVotacion('<?= $v['idVotacion'] ?>', '<?= $v['t_minuta_idMinuta'] ?? 0 ?>', '<?= htmlspecialchars($v['nombreVotacion'] ?? 'N/A', ENT_QUOTES) ?>')"
                      title="Ver Detalle de Votos y Nombres">
                      <i class="fas fa-eye"></i>
                    </button>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="d-flex justify-content-end">
          <?php renderPagination($page, $pages); ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="modal fade" id="modalDetalleVotacion" tabindex="-1" aria-labelledby="modalDetalleVotacionLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="modalDetalleVotacionLabel">Detalle de Votación: </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div id="detalleVotacionContenido">
            <p class="text-center text-muted"><i class="fas fa-spinner fa-spin me-2"></i>Cargando...</p>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
        </div>
      </div>
    </div>
  </div>
  <script src="/corevota/public/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <script>
    (function() {
      // Obtener los elementos del DOM
      const form = document.getElementById('filtrosForm');
      const inputQ = document.getElementById('q');
      const pHid = document.getElementById('pHidden');
      
      // AJUSTE 3: Capturamos los 3 selectores
      const comSelect = document.getElementById('comision_id_select');
      const mesSelect = document.getElementById('mes_select');
      const anioSelect = document.getElementById('anio_select');

      // Función para resetear la paginación a la página 1
      function toFirstPage() {
        if (pHid) pHid.value = '1';
      }

      // --- 1. Filtro automático para Comisión, Mes y Año ---
      // Agregamos el listener a los 3 campos
      [comSelect, mesSelect, anioSelect].forEach(selectElement => {
        if (selectElement && form) {
          selectElement.addEventListener('change', () => {
            toFirstPage();
            form.submit();
          });
        }
      });

      // --- 2. Filtro debounce para Palabra Clave ---
      if (inputQ && form) {
        let searchTimer = null;
        inputQ.addEventListener('input', () => {
          clearTimeout(searchTimer);
          searchTimer = setTimeout(() => {
            const val = (inputQ.value || '').trim();
            if (val.length >= 4 || val.length === 0) {
              toFirstPage();
              form.submit();
            }
          }, 400);
        });
      }

      // --- 3. Lógica para el Modal de Detalle ---
      window.mostrarDetalleVotacion = function(idVotacion, idMinuta, nombreVotacion) {
        const modal = new bootstrap.Modal(document.getElementById('modalDetalleVotacion'));
        const modalTitle = document.getElementById('modalDetalleVotacionLabel');
        const modalBody = document.getElementById('detalleVotacionContenido');

        // Configuración inicial
        modalTitle.textContent = `Detalle de Votación: ${nombreVotacion}`;
        modalBody.innerHTML = '<p class="text-center text-muted"><i class="fas fa-spinner fa-spin me-2"></i>Cargando...</p>';
        modal.show();

        // Llamada a la API
        fetch(`../../controllers/obtener_resultados_votacion.php?idVotacion=${encodeURIComponent(idVotacion)}&idMinuta=${encodeURIComponent(idMinuta)}`, {
            method: 'GET',
            credentials: 'same-origin'
          })
          .then(response => {
            if (!response.ok) throw new Error('Error al obtener datos del servidor.');
            return response.json();
          })
          .then(data => {
            if (data.status === 'success' && data.data && data.data.length > 0) {
              const votacion = data.data[0];
              modalBody.innerHTML = renderDetalleHTML(votacion);
            } else {
              modalBody.innerHTML = '<p class="alert alert-warning text-center">No se encontraron datos de detalle para esta votación.</p>';
            }
          })
          .catch(error => {
            modalBody.innerHTML = `<p class="alert alert-danger text-center">Error: ${error.message}</p>`;
            console.error('Error al cargar detalle de votación:', error);
          });
      };

      // 4. Función de Renderizado
      function renderDetalleHTML(v) {
        const getVoterList = (list) => list.length > 0 ?
          `<ul class="list-unstyled mb-0 small">${list.map(name => `<li><i class="fas fa-user-check fa-fw me-1 text-primary"></i>${name}</li>`).join('')}</ul>` :
          '<em class="text-muted small ps-2">Sin votos registrados</em>';

        return `
            <div class="row text-center mb-4">
                <div class="col-4">
                    <h3 class="text-success mb-0">${v.votosSi}</h3>
                    <p class="mb-0 small text-uppercase">A Favor</p>
                </div>
                <div class="col-4">
                    <h3 class="text-danger mb-0">${v.votosNo}</h3>
                    <p class="mb-0 small text-uppercase">En Contra</p>
                </div>
                <div class="col-4">
                    <h3 class="text-warning mb-0">${v.votosAbstencion}</h3>
                    <p class="mb-0 small text-uppercase">Abstención</p>
                </div>
            </div>
            <hr>
            <div class="row">
                <div class="col-4">
                    <h6 class="text-success border-bottom pb-1">Votaron SÍ (${v.votosSi})</h6>
                    ${getVoterList(v.votosSi_nombres || [])}
                </div>
                <div class="col-4">
                    <h6 class="text-danger border-bottom pb-1">Votaron NO (${v.votosNo})</h6>
                    ${getVoterList(v.votosNo_nombres || [])}
                </div>
                <div class="col-4">
                    <h6 class="text-warning border-bottom pb-1">Se Abstienen (${v.votosAbstencion})</h6>
                    ${getVoterList(v.votosAbstencion_nombres || [])}
                </div>
            </div>
            <hr class="mt-4">
            <p class="text-muted small"><strong>Total de Asistentes Requeridos:</strong> ${v.totalPresentes}</p>
            <p class="text-muted small"><strong>Total de Votos Emitidos:</strong> ${v.votosSi + v.votosNo + v.votosAbstencion}</p>
        `;
      }

    })();
  </script>
</body>

</html>