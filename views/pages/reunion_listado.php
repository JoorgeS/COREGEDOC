<?php
// views/pages/reunion_listado.php
// La variable $reuniones es provista por ReunionController.php?action=list
if (!isset($reuniones) || !is_array($reuniones)) {
    $reuniones = []; 
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($now)) {
    $now = time();
}

/* =========================
   PROCESAMIENTO DE FILTROS
   ========================= */

$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$fechaInicio_val = isset($_GET['fecha_inicio']) && !empty($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : date('Y-m-01');
$fechaTermino_val = isset($_GET['fecha_termino']) && !empty($_GET['fecha_termino']) ? $_GET['fecha_termino'] : date('Y-m-d');


/* =========================
   LÓGICA DE FILTRADO
   ========================= */
$reunionesFiltradas = $reuniones;

// --- A. Filtro por Palabra Clave ---
if ($q !== '') {
    $needle = mb_strtolower($q, 'UTF-8');
    $reunionesFiltradas = array_filter($reunionesFiltradas, function ($r) use ($needle) {
        $nombre = mb_strtolower((string)($r['nombreReunion'] ?? ''), 'UTF-8');
        return (strpos($nombre, $needle) !== false);
    });
}

// --- B. Filtro por Rango de Fecha ---
if ($fechaInicio_val && $fechaTermino_val) {
    $inicioTimestamp = strtotime($fechaInicio_val . ' 00:00:00');
    $terminoTimestamp = strtotime($fechaTermino_val . ' 23:59:59');

    $reunionesFiltradas = array_filter($reunionesFiltradas, function ($r) use ($inicioTimestamp, $terminoTimestamp) {
        $reunionTimestamp = strtotime($r['fechaInicioReunion']);
        return ($reunionTimestamp >= $inicioTimestamp) && ($reunionTimestamp <= $terminoTimestamp);
    });
}


/* =========================
   PAGINACIÓN
   ========================= */
$perPage = 10; 
$total  = count($reunionesFiltradas);
$pages  = max(1, (int)ceil($total / $perPage));
$page   = isset($_GET['p']) ? (int)$_GET['p'] : 1;
$page   = max(1, min($page, $pages));
$offset = ($page - 1) * $perPage;
$reunionesPage = array_slice($reunionesFiltradas, $offset, $perPage);

function renderPagination($current, $pages)
{
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
    <title>Listado de Reuniones</title>
    <link href="/corevota/public/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        /* --- ESTILOS DEL NUEVO DISEÑO --- */
        body {
            background-color: #f4f6f9;
        }

        .card-narrow {
            max-width: 1500px;
            margin: 1.5rem auto;
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }

        .filters-card {
            background: #ffffff;
            border-left: 5px solid #0d6efd;
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

        .form-control-custom {
            border-radius: 6px;
            border: 1px solid #dee2e6;
            padding: 8px 12px;
            font-size: 0.95rem;
        }

        .form-control-custom:focus {
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
            border-top: 5px solid #198754;
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
    </style>
</head>

<body>
    
    <nav aria-label="breadcrumb" class="mb-4 ms-3 mt-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="menu.php?pagina=home" class="text-decoration-none">Home</a></li>
            <li class="breadcrumb-item"><a href="menu.php?pagina=reuniones_dashboard" class="text-decoration-none">Módulo de Reuniones</a></li>
            <li class="breadcrumb-item active" aria-current="page">Listado</li>
        </ol>
    </nav>

    <div class="container-fluid px-4">
        <h3 class="mb-4 text-dark fw-bold"><i class="fas fa-users-cog me-2 text-primary"></i>Gestión de Reuniones</h3>

        <div class="card card-narrow filters-card mb-4">
            <div class="card-body p-4">
                <h6 class="mb-3 text-primary fw-bold"><i class="fas fa-filter me-2"></i>Filtros de Búsqueda</h6>

                <form id="filtrosForm" method="GET" class="row g-3 align-items-end">
                    <input type="hidden" name="pagina" value="<?php echo htmlspecialchars($_GET['pagina'] ?? 'reunion_listado', ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="p" id="pHidden" value="1">

                    <div class="col-md-4">
                        <label for="q" class="form-label-custom"><i class="fas fa-search me-1"></i> Palabra Clave</label>
                        <input type="text" class="form-control form-control-custom" id="q" name="q" 
                               placeholder="Buscar por nombre de reunión..." value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>

                    <div class="col-md-3">
                        <label for="fecha_inicio" class="form-label-custom"><i class="far fa-calendar-alt me-1"></i> Desde</label>
                        <input type="date" class="form-control form-control-custom" id="fecha_inicio" name="fecha_inicio" 
                               value="<?php echo htmlspecialchars($fechaInicio_val); ?>">
                    </div>

                    <div class="col-md-3">
                        <label for="fecha_termino" class="form-label-custom"><i class="far fa-calendar-check me-1"></i> Hasta</label>
                        <input type="date" class="form-control form-control-custom" id="fecha_termino" name="fecha_termino" 
                               value="<?php echo htmlspecialchars($fechaTermino_val); ?>">
                    </div>

                    <div class="col-md-2">
                        <button type="button" id="btnClear" class="btn btn-clean w-100">
                            <i class="fas fa-eraser me-1 text-danger"></i> Limpiar
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card card-narrow table-card shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <?php if (empty($reunionesPage)) : ?>
                        <div class="p-5 text-center">
                            <div class="mb-3"><i class="fas fa-folder-open fa-3x text-muted"></i></div>
                            <h5 class="text-muted">No se encontraron reuniones.</h5>
                            <p class="text-muted small">Intenta ajustar los filtros de búsqueda.</p>
                        </div>
                    <?php else : ?>
                        <table class="table table-hover mb-0 align-middle sticky-th">
                            <thead>
                                <tr>
                                    <th class="ps-4">N° Reunión</th>
                                    <th>Nombre</th>
                                    <th>Comisión</th>
                                    <th>Fecha y hora</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reunionesPage as $reunion) : ?>
                                    <?php
                                    $idReunion  = $reunion['idReunion'];
                                    $idMinuta   = $reunion['t_minuta_idMinuta'];
                                    $estadoMinuta = $reunion['estadoMinuta'];
                                    $meetingStartTime = strtotime($reunion['fechaInicioReunion']);
                                    ?>
                                    <tr>
                                        <td class="ps-4 fw-bold text-secondary">#<?php echo htmlspecialchars($idMinuta ?? '-'); ?></td>

                                        <td class="fw-semibold"><?php echo htmlspecialchars($reunion['nombreReunion']); ?></td>

                                        <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($reunion['nombreComision']); ?></span></td>

                                        <td class="small text-muted"><i class="far fa-clock me-1"></i><?php echo htmlspecialchars(date('d-m-Y H:i', strtotime($reunion['fechaInicioReunion']))); ?></td>

                                        <td style="white-space: nowrap;">
                                            <?php
                                            if ($idMinuta === null) {
                                                // --- 1. ESTADO PROGRAMADA ---
                                                if ($now < $meetingStartTime) {
                                                    $horaInicioFormato = htmlspecialchars(date('H:i', $meetingStartTime));
                                                    $fechaInicioFormato = htmlspecialchars(date('d-m-Y', $meetingStartTime));
                                            ?>
                                                    <span class="text-muted" title="Programada para el <?php echo $fechaInicioFormato; ?> a las <?php echo $horaInicioFormato; ?>">
                                                        <i class="fas fa-clock me-1"></i> Reunión se habilitará a las: <?php echo $horaInicioFormato; ?>
                                                    </span>
                                                <?php
                                                } else {
                                                ?>
                                                    <a href="/corevota/controllers/ReunionController.php?action=iniciarMinuta&idReunion=<?php echo $idReunion; ?>" class="btn btn-sm btn-primary shadow-sm" title="Crear e iniciar la edición de la minuta">
                                                        <i class="fas fa-play me-1"></i> Iniciar Reunión
                                                    </a>
                                                <?php
                                                }
                                                // Botones de EDITAR y BORRAR (Restaurados)
                                                ?>
                                                <a href="menu.php?pagina=reunion_editar&id=<?php echo $idReunion; ?>" class="btn btn-outline-secondary btn-sm ms-1 rounded-circle" title="Editar Detalles">
                                                    <i class="fas fa-pencil-alt"></i>
                                                </a>
                                                <a href="/corevota/controllers/ReunionController.php?action=delete&id=<?php echo $idReunion; ?>" class="btn btn-outline-danger btn-sm ms-1 rounded-circle" title="Eliminar">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            <?php

                                            } elseif ($estadoMinuta === 'BORRADOR') {
                                                // --- 2. ESTADO BORRADOR (Iniciada) ---
                                            ?>
                                                <span title="La reunión está en progreso. La minuta se está editando.">
                                                    <i class="fas fa-edit me-1 text-primary"></i>Iniciada
                                                </span>
                                            <?php
                                            } elseif ($estadoMinuta === 'PENDIENTE') {
                                                // --- 3. ESTADO PENDIENTE (Borrador guardado) ---
                                            ?>
                                                <span title="La minuta fue guardada como borrador y está pendiente de aprobación por el Presidente.">
                                                    <i class="fas fa-clock me-1 text-warning"></i> Finalizada esperando aprobación de minuta
                                                </span>
                                            <?php
                                            } elseif ($estadoMinuta === 'APROBADA') {
                                                // --- 4. ESTADO APROBADA (Finalizada real) ---
                                            ?>
                                                <span title="Esta reunión concluyó y su minuta fue aprobada.">
                                                    <i class="fas fa-check-circle me-1 text-success"></i> Finalizada con minuta aprobada
                                                </span>
                                            <?php
                                            } elseif ($estadoMinuta === 'PARCIAL') {
                                                // --- 5. ESTADO PARCIAL ---
                                            ?>
                                                <span title="La minuta fue guardada como borrador y está pendiente de aprobación por más de un Presidente.">
                                                    <i class="fas fa-clock me-1 text-warning"></i> Finalizada esperando aprobación de minuta
                                                </span>
                                            <?php

                                            } else {
                                                // --- 6. ESTADO INVÁLIDO (Fallback) ---
                                            ?>
                                                <span class="text-danger" title="Estado de minuta desconocido: <?php echo htmlspecialchars($estadoMinuta); ?>">
                                                    <i class="fas fa-exclamation-circle me-1"></i> Estado Inválido
                                                </span>
                                            <?php
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <div class="card-footer bg-white d-flex justify-content-between align-items-center py-3">
                            <small class="text-muted">Mostrando <?php echo count($reunionesPage); ?> registros</small>
                            <?php renderPagination($page, $pages); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="/corevota/public/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const deleteLinks = document.querySelectorAll('a.btn-outline-danger[href*="ReunionController.php?action=delete"]');
            deleteLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const url = this.getAttribute('href');
                    Swal.fire({
                        title: '¿Eliminar reunión?',
                        text: "Esta acción no se puede deshacer.",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#dc3545',
                        cancelButtonColor: '#6c757d',
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

        (function() {
            const form = document.getElementById('filtrosForm');
            const inputQ = document.getElementById('q');
            const inputInicio = document.getElementById('fecha_inicio');
            const inputTermino = document.getElementById('fecha_termino');
            const btnClear = document.getElementById('btnClear');
            const pHid = document.getElementById('pHidden');

            function toFirstPage() { if (pHid) pHid.value = '1'; }
            function submitForm() { toFirstPage(); form.submit(); }

            if (inputInicio) inputInicio.addEventListener('change', submitForm);
            if (inputTermino) inputTermino.addEventListener('change', submitForm);

            if (btnClear) {
                btnClear.addEventListener('click', function() {
                    if (inputQ) inputQ.value = '';
                    if (inputInicio) inputInicio.value = '';
                    if (inputTermino) inputTermino.value = '';
                    submitForm();
                });
            }

            let searchTimer = null;
            if (inputQ && form) {
                inputQ.addEventListener('input', () => {
                    clearTimeout(searchTimer);
                    searchTimer = setTimeout(() => {
                        const val = (inputQ.value || '').trim();
                        if (val.length >= 4 || val.length === 0) {
                            submitForm();
                        }
                    }, 400);
                });
            }
        })();
    </script>
</body>
</html>