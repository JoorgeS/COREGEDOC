<?php
// views/pages/reunion_listado.php
// La variable $reuniones es provista por ReunionController.php?action=list
if (!isset($reuniones) || !is_array($reuniones)) {
    $reuniones = []; // Asegura que la variable exista si se accede directamente
}

// Los mensajes de sesión ahora se manejan en menu.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Asegurar $now si no viene desde el controlador
if (!isset($now)) {
    $now = time();
}

/* =========================
   FILTROS EN VISTA (no rompe lógica)
   ========================= */
$q = isset($_GET['q']) ? trim((string)$_GET['q']) : ''; // búsqueda por comisión o nombre reunión

/* =========================
   PAGINACIÓN (no rompe lógica)
   ========================= */
$perPage = isset($_GET['per_page']) ? max(1, (int)$_GET['per_page']) : 10;  // 10 por defecto

// --- Aplicar filtro antes de paginar ---
$reunionesFiltradas = $reuniones;
if ($q !== '') {
    $needle = mb_strtolower($q, 'UTF-8');
    $reunionesFiltradas = array_filter($reunionesFiltradas, function ($r) use ($needle) {
        $comision = mb_strtolower((string)($r['nombreComision'] ?? ''), 'UTF-8');
        $nombre   = mb_strtolower((string)($r['nombreReunion'] ?? ''), 'UTF-8');
        return (strpos($comision, $needle) !== false) || (strpos($nombre, $needle) !== false);
    });
}

$total   = count($reunionesFiltradas);
$pages   = max(1, (int)ceil($total / $perPage));
$page    = isset($_GET['p']) ? (int)$_GET['p'] : 1;
$page    = max(1, min($page, $pages));
$offset  = ($page - 1) * $perPage;

// Subconjunto a mostrar
$reunionesPage = array_slice($reunionesFiltradas, $offset, $perPage);

// Helper para paginación
function renderPagination($current, $pages) {
    if ($pages <= 1) return;
    // Preservar querystring existente
    echo '<nav aria-label="Paginación"><ul class="pagination pagination-sm mb-0">';
    for ($i = 1; $i <= $pages; $i++) {
        $qsArr = $_GET;
        $qsArr['p'] = $i;
        $qs = http_build_query($qsArr);
        $active = ($i === $current) ? ' active' : '';
        echo '<li class="page-item'.$active.'"><a class="page-link" href="?'.$qs.'">'.$i.'</a></li>';
    }
    echo '</ul></nav>';
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Listado de Reuniones</title>
    <link href="/corevota/public/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        .table-responsive { margin-top: 20px; }
        .table th, .table td { vertical-align: middle; }
        .filters-card{border:1px solid #e5e7eb;border-radius:.5rem;background:#f8fafc}
        .sticky-th thead th{position:sticky;top:0;z-index:1}
    </style>
</head>
<body>
    <div class="container-fluid mt-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="mb-0">Reuniones Registradas</h3>
            <!-- Se eliminó el selector de la esquina superior derecha -->
        </div>

        <!-- Filtros (debajo del título) -->
        <form id="filtrosForm" method="GET" class="mb-3 p-3 filters-card">
            <input type="hidden" name="pagina" value="<?php echo htmlspecialchars($_GET['pagina'] ?? 'reunion_listado', ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="p" id="pHidden" value="<?php echo (int)$page; ?>">
            <div class="row g-3 align-items-end">
                <div class="col-md-8">
                    <label for="q" class="form-label">Buscar</label>
                    <input
                        type="text"
                        class="form-control form-control-sm"
                        id="q"
                        name="q"
                        placeholder="Buscar por comisión o nombre de reunión..."
                        value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="col-md-4">
                    <label for="per_page" class="form-label">Resultados</label>
                    <select name="per_page" id="per_page" class="form-select form-select-sm">
                        <?php foreach ([10,25,50] as $opt): ?>
                            <option value="<?php echo $opt; ?>" <?php echo ($perPage===$opt)?'selected':''; ?>>
                                <?php echo $opt; ?>/pág
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </form>

        <div class="card shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <?php if (empty($reunionesPage)): ?>
                        <div class="alert alert-info">No hay reuniones vigentes registradas.</div>
                    <?php else: ?>
                        <table class="table table-striped table-hover sticky-th">
                            <thead class="table-dark">
                                <tr>
                                    <th>N° Minuta</th>
                                    <th>Comisión</th>
                                    <th>Nombre Reunión</th>
                                    <th>Inicio</th>
                                    <th>Término</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reunionesPage as $reunion): ?>
                                    <?php
                                    // Variables para esta fila
                                    $idReunion   = $reunion['idReunion'];
                                    $idMinuta    = $reunion['t_minuta_idMinuta']; // puede ser NULL
                                    $estadoMinuta = $reunion['estadoMinuta'];     // NULL, 'PENDIENTE', 'APROBADA'

                                    // Determinar el texto y color del badge de estado
                                    $estadoTexto = 'No Iniciada';
                                    $badge_class = 'bg-secondary';
                                    if ($estadoMinuta === 'PENDIENTE') {
                                        $estadoTexto = 'Pendiente';
                                        $badge_class = 'bg-warning text-dark';
                                    } elseif ($estadoMinuta === 'APROBADA') {
                                        $estadoTexto = 'Aprobada';
                                        $badge_class = 'bg-success';
                                    } elseif ($idMinuta === null) {
                                        $estadoTexto = 'Programada';
                                        $badge_class = 'bg-info text-dark';
                                    }

                                    $meetingStartTime = strtotime($reunion['fechaInicioReunion']);
                                    ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($idMinuta); ?></strong></td>
                                        <td><?php echo htmlspecialchars($reunion['nombreComision']); ?></td>
                                        <td><?php echo htmlspecialchars($reunion['nombreReunion']); ?></td>
                                        <td><?php echo htmlspecialchars(date('d-m-Y H:i', strtotime($reunion['fechaInicioReunion']))); ?></td>
                                        <td><?php echo htmlspecialchars(date('d-m-Y H:i', strtotime($reunion['fechaTerminoReunion']))); ?></td>
                                        <td>
                                            <span class="badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($estadoTexto); ?></span>
                                        </td>
                                        <td style="white-space: nowrap;">
                                            <?php
                                            // --- Lógica Principal de Acciones (se mantiene) ---
                                            if ($idMinuta === null) {
                                                if ($now < $meetingStartTime) {
                                                    $horaInicioFormato = htmlspecialchars(date('H:i', $meetingStartTime));
                                                    $fechaInicioFormato = htmlspecialchars(date('d-m-Y', $meetingStartTime));
                                                    ?>
                                                    <span class="text-muted" title="Programada para el <?php echo $fechaInicioFormato; ?> a las <?php echo $horaInicioFormato; ?>">
                                                        <i class="fas fa-clock me-1"></i> Iniciar se habilita a las <?php echo $horaInicioFormato; ?>
                                                    </span>
                                                    <?php
                                                } else {
                                                    ?>
                                                    <a href="/corevota/controllers/ReunionController.php?action=iniciarMinuta&idReunion=<?php echo $idReunion; ?>" class="btn btn-sm btn-primary" title="Crear e iniciar la edición de la minuta">
                                                        <i class="fas fa-play me-1"></i> Iniciar Reunión
                                                    </a>
                                                    <?php
                                                }
                                            } elseif ($estadoMinuta === 'PENDIENTE') {
                                                ?>
                                                <a href="menu.php?pagina=editar_minuta&id=<?php echo $idMinuta; ?>" class="btn btn-sm btn-warning" title="Continuar editando la minuta">
                                                    <i class="fas fa-edit me-1"></i> Continuar Edición
                                                </a>
                                                <?php
                                            } elseif ($estadoMinuta === 'APROBADA') {
                                                ?>
                                                <span class="text-success">
                                                    <i class="fas fa-check-circle me-1"></i> Reunión Finalizada
                                                </span>
                                                <?php
                                            } else {
                                                ?>
                                                <span class="text-danger" title="Estado de minuta desconocido: <?php echo htmlspecialchars($estadoMinuta); ?>">
                                                    <i class="fas fa-exclamation-circle me-1"></i> Estado Inválido
                                                </span>
                                                <?php
                                            }

                                            if ($idMinuta === null) {
                                                ?>
                                                <a href="menu.php?pagina=reunion_editar&id=<?php echo $idReunion; ?>" class="btn btn-secondary btn-sm ms-1" title="Editar Detalles de la Reunión (horario, nombre, etc.)">
                                                    <i class="fas fa-pencil-alt"></i>
                                                </a>
                                                <a href="/corevota/controllers/ReunionController.php?action=delete&id=<?php echo $idReunion; ?>"
                                                   class="btn btn-sm btn-danger ms-1"
                                                   title="Deshabilitar Reunión">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                                <?php
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <!-- Paginación inferior -->
                        <div class="d-flex justify-content-end">
                            <?php renderPagination($page, $pages); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="/corevota/public/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <!-- ✅ SweetAlert2 (Popup moderno) -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- ✅ Script de confirmación personalizado -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const deleteLinks = document.querySelectorAll('a.btn-danger[href*="ReunionController.php?action=delete"]');
        deleteLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const url = this.getAttribute('href');
                Swal.fire({
                    title: '¿Estás seguro?',
                    text: "Esta acción eliminará la reunión del listado activo.",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Sí, eliminar',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = url;
                    }
                });
            });
        });
    });
    </script>

    <script>
    (function(){
      const form   = document.getElementById('filtrosForm');
      const inputQ = document.getElementById('q');
      const perSel = document.getElementById('per_page');
      const pHid   = document.getElementById('pHidden');

      function toFirstPage(){ if (pHid) pHid.value = '1'; }

      if(perSel){
        perSel.addEventListener('change', ()=>{
          toFirstPage();
          if (form) form.submit();
        });
      }

      if(inputQ && form){
        let t=null;
        inputQ.addEventListener('input', ()=>{
          clearTimeout(t);
          t = setTimeout(()=>{
            const val = (inputQ.value || '').trim();
            if(val.length >= 5 || val.length === 0){
              toFirstPage();
              form.submit();
            }
          }, 400);
        });
      }
    })();
    </script>
</body>
</html>
