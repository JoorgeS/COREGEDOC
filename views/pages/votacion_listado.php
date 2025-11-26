<?php
// views/pages/votacion_listado.php

require_once __DIR__ . '/../../controllers/VotacionController.php';
require_once __DIR__ . "/../../class/class.conectorDB.php";

$db = new conectorDB();
$pdo = $db->getDatabase();

/* ===== Capturar Filtros ===== */
$mes = $_GET['mes'] ?? date('m');
$anio = $_GET['anio'] ?? date('Y');
$comId = $_GET['comision_id'] ?? "";

/* Cargar comisiones */
$listaComisiones = [];
try {
  $st = $pdo->query("SELECT idComision, nombreComision FROM t_comision ORDER BY nombreComision ASC");
  $listaComisiones = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $listaComisiones = [];
}

/* Calcular Universo de Votantes */
$universoVotantes = 0;
try {
  $stUniverso = $pdo->query("SELECT COUNT(*) FROM t_usuario WHERE tipoUsuario_id IN (1, 3, 7)");
  $universoVotantes = (int)$stUniverso->fetchColumn();
} catch (Throwable $e) {
  $universoVotantes = 0;
}

// Controlador y Datos
$controller = new VotacionController();
$filtros = ['mes' => $mes, 'anio' => $anio, 'comision_id' => $comId];
$response = $controller->listar($filtros);
$votaciones = $response['data'] ?? [];

// Filtro en Vista (Palabra Clave)
$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$reunionesFiltradas = $votaciones;

if ($q !== '') {
  $needle = mb_strtolower($q, 'UTF-8');
  $reunionesFiltradas = array_filter($reunionesFiltradas, function ($r) use ($needle) {
    $nombre = mb_strtolower((string)($r['nombreVotacion'] ?? ''), 'UTF-8');
    return (strpos($nombre, $needle) !== false);
  });
}

$votacionesFiltradas = array_values($reunionesFiltradas);

/* Paginación */
$perPage = 10;
$total = count($votacionesFiltradas);
$pages = max(1, (int)ceil($total / $perPage));
$page = isset($_GET['p']) ? (int)$_GET['p'] : 1;
$page = max(1, min($page, $pages));
$offset = ($page - 1) * $perPage;
$votacionesPage = array_slice($votacionesFiltradas, $offset, $perPage);

function renderPagination($current, $pages) {
  if ($pages <= 1) return;
  echo '<nav aria-label="Paginación"><ul class="pagination pagination-sm mb-0 justify-content-end">';
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
  <link href="/coregedoc/public/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <style>
    /* --- MEJORAS VISUALES --- */
    body {
        background-color: #f4f6f9; /* Fondo gris muy suave */
    }

    .card-narrow {
      max-width: 1500px;
      margin: 1.5rem auto;
      border: none;
      border-radius: 12px; /* Bordes más redondeados */
      box-shadow: 0 4px 6px rgba(0,0,0,0.05); /* Sombra suave */
    }

    .filters-card {
        background: #ffffff;
        border-left: 5px solid #0d6efd; /* Acento de color a la izquierda */
        transition: all 0.3s ease;
    }
    
    .filters-card:hover {
        box-shadow: 0 8px 15px rgba(0,0,0,0.08);
    }

    .form-label-custom {
        font-size: 0.85rem;
        font-weight: 700;
        text-transform: uppercase;
        color: #6c757d;
        letter-spacing: 0.5px;
        margin-bottom: 5px;
    }

    .form-select-custom, .form-control-custom {
        border-radius: 6px;
        border: 1px solid #dee2e6;
        padding: 8px 12px;
        font-size: 0.95rem;
    }

    .form-select-custom:focus, .form-control-custom:focus {
        border-color: #86b7fe;
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.15);
    }

    .btn-clean {
        border-radius: 6px;
        font-weight: 500;
        padding: 8px 15px;
        background-color: #f8f9fa;
        border: 1px solid #dee2e6;
        color: #495057;
        transition: all 0.2s;
    }

    .btn-clean:hover {
        background-color: #e2e6ea;
        color: #212529;
        border-color: #adb5bd;
    }

    .table-card {
        border-top: 5px solid #198754; /* Verde para resultados */
    }

    .table thead th {
      background-color: #343a40;
      color: white;
      font-weight: 500;
      border: none;
      vertical-align: middle;
    }
    
    .sticky-th thead th {
        position: sticky;
        top: 0;
        z-index: 10;
    }

    /* Badge de estado */
    .badge-status {
        font-size: 0.85rem;
        padding: 6px 12px;
        border-radius: 20px;
        font-weight: 600;
    }
  </style>
</head>

<body>

  <nav aria-label="breadcrumb" class="mb-4 ms-3 mt-3">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="menu.php?pagina=home" class="text-decoration-none">Home</a></li>
      <li class="breadcrumb-item"><a href="menu.php?pagina=votaciones_dashboard" class="text-decoration-none">Gestión de Votaciones</a></li>
      <li class="breadcrumb-item active" aria-current="page">Listado</li>
    </ol>
  </nav>

  <div class="container-fluid px-4">
      <h3 class="mb-4 text-dark fw-bold"><i class="fas fa-list-alt me-2 text-primary"></i>Votaciones y Resultados</h3>

      <div class="card card-narrow filters-card mb-4">
        <div class="card-body p-4">
          <h6 class="mb-3 text-primary fw-bold"><i class="fas fa-filter me-2"></i>Filtros de Búsqueda</h6>

          <form id="filtrosForm" method="GET" class="row g-3 align-items-end">
            <input type="hidden" name="pagina" value="votacion_listado">
            <input type="hidden" name="p" id="pHidden" value="1">

            <div class="col-md-2">
              <label class="form-label-custom"><i class="far fa-calendar-alt me-1"></i> Mes</label>
              <select name="mes" class="form-select form-select-custom" id="mes_select">
                <?php for ($m = 1; $m <= 12; $m++): $val = str_pad((string)$m, 2, '0', STR_PAD_LEFT); ?>
                  <option value="<?= $val ?>" <?= ($val === $mes ? 'selected' : '') ?>><?= $val ?></option>
                <?php endfor; ?>
              </select>
            </div>

            <div class="col-md-2">
              <label class="form-label-custom"><i class="far fa-calendar me-1"></i> Año</label>
              <select name="anio" class="form-select form-select-custom" id="anio_select">
                <?php $yNow = (int)date('Y');
                for ($y = $yNow; $y >= $yNow - 3; $y--): ?>
                  <option value="<?= $y ?>" <?= ((string)$y === (string)$anio ? 'selected' : '') ?>><?= $y ?></option>
                <?php endfor; ?>
              </select>
            </div>

            <div class="col-md-3">
              <label class="form-label-custom"><i class="fas fa-users me-1"></i> Comisión</label>
              <select name="comision_id" class="form-select form-select-custom" id="comision_id_select">
                <option value="">-- Todas las Comisiones --</option>
                <?php foreach ($listaComisiones as $c): ?>
                  <option value="<?= (int)$c['idComision'] ?>" <?= ($comId == $c['idComision'] ? 'selected' : '') ?>>
                    <?= htmlspecialchars($c['nombreComision']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-3">
              <label class="form-label-custom"><i class="fas fa-search me-1"></i> Palabra Clave</label>
              <input type="text" class="form-control form-control-custom" id="q" name="q" 
                     placeholder="Buscar por nombre..." value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>">
            </div>

            <div class="col-md-2">
                <a href="menu.php?pagina=votacion_listado" class="btn btn-clean w-100">
                  <i class="fas fa-eraser me-1 text-danger"></i> Limpiar
                </a>
            </div>

          </form>
        </div>
      </div>

      <div class="card card-narrow table-card shadow-sm">
        <div class="card-body p-0"> <?php if (empty($votacionesPage)): ?>
            <div class="p-5 text-center">
                <div class="mb-3"><i class="fas fa-folder-open fa-3x text-muted"></i></div>
                <h5 class="text-muted">No se encontraron votaciones.</h5>
                <p class="text-muted small">Intenta ajustar los filtros de búsqueda.</p>
            </div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-hover mb-0 align-middle sticky-th">
                <thead>
                  <tr>
                    <th class="ps-4">ID</th>
                    <th>Nombre de la Adenda</th>
                    <th>Comisión</th>
                    <th>Fecha</th>
                    <th class="text-center">Resultado</th>
                    <th class="text-center">Participación</th>
                    <th class="text-center pe-4">Acciones</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($votacionesPage as $v): ?>
                    <?php
                    $totalVotos = (int)($v['totalSi'] ?? 0) + (int)($v['totalNo'] ?? 0) + (int)($v['totalAbstencion'] ?? 0);
                    $fecha = $v['fechaCreacion'] ? date('d-m-Y H:i', strtotime($v['fechaCreacion'])) : 'N/A';
                    
                    // Estado
                    $si = (int)($v['totalSi'] ?? 0);
                    $no = (int)($v['totalNo'] ?? 0);
                    if ($si > $no) { $stTxt = 'APROBADA'; $stCls = 'bg-success'; }
                    elseif ($no > $si) { $stTxt = 'RECHAZADA'; $stCls = 'bg-danger'; }
                    else { $stTxt = 'EMPATE'; $stCls = 'bg-secondary'; }

                    // Participación
                    $porcentaje = ($universoVotantes > 0) ? round(($totalVotos / $universoVotantes) * 100) : 0;
                    ?>
                    <tr>
                      <td class="ps-4 fw-bold text-secondary">#<?= $v['idVotacion'] ?></td>
                      <td class="fw-semibold"><?= htmlspecialchars($v['nombreVotacion']) ?></td>
                      <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($v['nombreComision']) ?></span></td>
                      <td class="small text-muted"><i class="far fa-clock me-1"></i><?= $fecha ?></td>

                      <td class="text-center">
                        <span class="badge badge-status <?= $stCls ?>"><?= $stTxt ?></span>
                      </td>

                      <td style="min-width: 160px;">
                        <div class="d-flex justify-content-between small mb-1">
                            <span><?= $porcentaje ?>%</span>
                            <span class="text-muted"><?= $totalVotos ?>/<?= $universoVotantes ?></span>
                        </div>
                        <div class="progress" style="height: 6px;">
                          <div class="progress-bar bg-info" role="progressbar" style="width: <?= $porcentaje ?>%"></div>
                        </div>
                      </td>

                      <td class="text-center pe-4">
                        <button type="button" class="btn btn-outline-primary btn-sm rounded-circle"
                          onclick="mostrarDetalleVotacion('<?= $v['idVotacion'] ?>', '<?= $v['t_minuta_idMinuta'] ?? 0 ?>', '<?= htmlspecialchars($v['nombreVotacion'] ?? 'N/A', ENT_QUOTES) ?>')"
                          title="Ver Detalle">
                          <i class="fas fa-eye"></i>
                        </button>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            
            <div class="card-footer bg-white d-flex justify-content-between align-items-center py-3">
                <small class="text-muted">Mostrando <?= count($votacionesPage) ?> registros</small>
                <?php renderPagination($page, $pages); ?>
            </div>

          <?php endif; ?>
        </div>
      </div>
  </div>

  <div class="modal fade" id="modalDetalleVotacion" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content border-0 shadow">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title" id="modalDetalleVotacionLabel"><i class="fas fa-chart-pie me-2"></i>Detalle de Votación</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-4">
          <div id="detalleVotacionContenido">
            <div class="d-flex justify-content-center py-5">
                <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Cargando...</span></div>
            </div>
          </div>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Cerrar</button>
        </div>
      </div>
    </div>
  </div>

  <script src="/coregedoc/public/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <script>
    (function() {
      const form = document.getElementById('filtrosForm');
      const inputQ = document.getElementById('q');
      const pHid = document.getElementById('pHidden');
      
      const comSelect = document.getElementById('comision_id_select');
      const mesSelect = document.getElementById('mes_select');
      const anioSelect = document.getElementById('anio_select');

      function toFirstPage() { if (pHid) pHid.value = '1'; }

      // Filtros Automáticos
      [comSelect, mesSelect, anioSelect].forEach(selectElement => {
        if (selectElement && form) {
          selectElement.addEventListener('change', () => { toFirstPage(); form.submit(); });
        }
      });

      // Buscador con Debounce
      if (inputQ && form) {
        let searchTimer = null;
        inputQ.addEventListener('input', () => {
          clearTimeout(searchTimer);
          searchTimer = setTimeout(() => {
            const val = (inputQ.value || '').trim();
            if (val.length >= 4 || val.length === 0) { toFirstPage(); form.submit(); }
          }, 400);
        });
      }

      // Lógica Modal
      window.mostrarDetalleVotacion = function(idVotacion, idMinuta, nombreVotacion) {
        const modal = new bootstrap.Modal(document.getElementById('modalDetalleVotacion'));
        const modalTitle = document.getElementById('modalDetalleVotacionLabel');
        const modalBody = document.getElementById('detalleVotacionContenido');

        modalTitle.innerHTML = `<i class="fas fa-chart-pie me-2"></i> ${nombreVotacion}`;
        modalBody.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div><p class="mt-2 text-muted">Cargando resultados...</p></div>';
        modal.show();

        fetch(`../../controllers/obtener_resultados_votacion.php?idVotacion=${encodeURIComponent(idVotacion)}&idMinuta=${encodeURIComponent(idMinuta)}`, {
            method: 'GET',
            credentials: 'same-origin'
          })
          .then(r => r.ok ? r.json() : Promise.reject('Error servidor'))
          .then(data => {
            if (data.status === 'success' && data.data && data.data.length > 0) {
              modalBody.innerHTML = renderDetalleHTML(data.data[0]);
            } else {
              modalBody.innerHTML = '<div class="alert alert-warning">No hay datos disponibles.</div>';
            }
          })
          .catch(e => modalBody.innerHTML = `<div class="alert alert-danger">Error: ${e}</div>`);
      };

      function renderDetalleHTML(v) {
        const listHTML = (list) => list.length ? `<ul class="list-unstyled small mb-0">${list.map(n=>`<li><i class="fas fa-user-check text-primary me-2"></i>${n}</li>`).join('')}</ul>` : '<em class="text-muted small">Sin votos</em>';
        
        return `
            <div class="row text-center mb-4 g-3">
                <div class="col-4"><div class="p-3 border rounded bg-success-subtle"><h3 class="text-success mb-0">${v.votosSi}</h3><small class="text-uppercase fw-bold text-success">A Favor</small></div></div>
                <div class="col-4"><div class="p-3 border rounded bg-danger-subtle"><h3 class="text-danger mb-0">${v.votosNo}</h3><small class="text-uppercase fw-bold text-danger">En Contra</small></div></div>
                <div class="col-4"><div class="p-3 border rounded bg-warning-subtle"><h3 class="text-warning mb-0">${v.votosAbstencion}</h3><small class="text-uppercase fw-bold text-warning">Abstención</small></div></div>
            </div>
            <div class="row g-3">
                <div class="col-md-4"><div class="card h-100 border-success"><div class="card-header bg-success text-white py-1 small">Votaron SÍ</div><div class="card-body p-2" style="max-height:200px;overflow-y:auto;">${listHTML(v.votosSi_nombres || [])}</div></div></div>
                <div class="col-md-4"><div class="card h-100 border-danger"><div class="card-header bg-danger text-white py-1 small">Votaron NO</div><div class="card-body p-2" style="max-height:200px;overflow-y:auto;">${listHTML(v.votosNo_nombres || [])}</div></div></div>
                <div class="col-md-4"><div class="card h-100 border-warning"><div class="card-header bg-warning text-dark py-1 small">Abstención</div><div class="card-body p-2" style="max-height:200px;overflow-y:auto;">${listHTML(v.votosAbstencion_nombres || [])}</div></div></div>
            </div>
            <div class="mt-4 pt-3 border-top d-flex justify-content-between text-muted small">
                <span><strong>Total Asistentes:</strong> ${v.totalPresentes}</span>
                <span><strong>Votos Emitidos:</strong> ${v.votosSi + v.votosNo + v.votosAbstencion}</span>
            </div>
        `;
      }
    })();
  </script>
</body>
</html>