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
   PROCESAMIENTO DE FILTROS
   ========================= */

// 1. Palabra Clave (para Nombre Reunión)
$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';

// 2. Rango de Fecha (con valores por defecto)
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
        // Búsqueda solo en Nombre Reunión, como se solicitó
        $nombre = mb_strtolower((string)($r['nombreReunion'] ?? ''), 'UTF-8');
        return (strpos($nombre, $needle) !== false);
    });
}

// --- B. Filtro por Rango de Fecha (sobre la fecha de INICIO de la reunión) ---
// (Solo filtra si ambas fechas están presentes)
if ($fechaInicio_val && $fechaTermino_val) {
    $inicioTimestamp = strtotime($fechaInicio_val . ' 00:00:00');
    $terminoTimestamp = strtotime($fechaTermino_val . ' 23:59:59');

    $reunionesFiltradas = array_filter($reunionesFiltradas, function ($r) use ($inicioTimestamp, $terminoTimestamp) {
        $reunionTimestamp = strtotime($r['fechaInicioReunion']);
        // Comprobar que la fecha de la reunión esté DENTRO del rango
        return ($reunionTimestamp >= $inicioTimestamp) && ($reunionTimestamp <= $terminoTimestamp);
    });
}


/* =========================
   PAGINACIÓN
   ========================= */
$perPage = 10; // Se elimina el dropdown, se fija en 10 (o el valor que prefieras)

$total  = count($reunionesFiltradas);
$pages  = max(1, (int)ceil($total / $perPage));
$page   = isset($_GET['p']) ? (int)$_GET['p'] : 1;
$page   = max(1, min($page, $pages));
$offset = ($page - 1) * $perPage;

// Subconjunto a mostrar
$reunionesPage = array_slice($reunionesFiltradas, $offset, $perPage);

// Helper para paginación
function renderPagination($current, $pages)
{
    if ($pages <= 1) return;
    // Preservar querystring existente (incluyendo filtros)
    echo '<nav aria-label="Paginación"><ul class="pagination pagination-sm mb-0">';
    for ($i = 1; $i <= $pages; $i++) {
        $qsArr = $_GET; // Mantiene 'pagina', 'q', 'fecha_inicio', 'fecha_termino'
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
    <div class="container-fluid mt-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="mb-0">Reuniones Registradas</h3>
        </div>

        <form id="filtrosForm" method="GET" class="mb-3 p-3 filters-card">
            <input type="hidden" name="pagina" value="<?php echo htmlspecialchars($_GET['pagina'] ?? 'reunion_listado', ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="p" id="pHidden" value="1">
            <div class="row g-3 align-items-end">

                <div class="col-md-5">
                    <label for="q" class="form-label">Palabra Clave (Nombre Reunión)</label>
                    <input type="text" class="form-control form-control-sm" id="q" name="q" placeholder="Buscar por nombre..." value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>">
                </div>

                <div class="col-md-3">
                    <label for="fecha_inicio" class="form-label">Desde</label>
                    <input type="date" class="form-control form-control-sm" id="fecha_inicio" name="fecha_inicio" value="<?php echo htmlspecialchars($fechaInicio_val); ?>">
                </div>

                <div class="col-md-3">
                    <label for="fecha_termino" class="form-label">Hasta</label>
                    <input type="date" class="form-control form-control-sm" id="fecha_termino" name="fecha_termino" value="<?php echo htmlspecialchars($fechaTermino_val); ?>">
                </div>

                <div class="col-md-1">
                    <button type="submit" class="btn btn-sm btn-primary w-100">Filtrar</button>
                </div>
            </div>
        </form>

        <div class="card shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <?php if (empty($reunionesPage)) : ?>
                        <div class="alert alert-info">No se encontraron reuniones con esos criterios.</div>
                    <?php else : ?>
                        <table class="table table-striped table-hover sticky-th">
                            <thead class="table-dark">
                                <tr>
                                    <th>N° Reunión</th>
                                    <th>Nombre</th>
                                    <th>Comisión</th>
                                    <th>Fecha y hora</th>
                                    <th>Estado</th>

                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reunionesPage as $reunion) : ?>
                                    <?php
                                    // Variables para esta fila
                                    $idReunion  = $reunion['idReunion'];
                                    $idMinuta   = $reunion['t_minuta_idMinuta']; // puede ser NULL
                                    $estadoMinuta = $reunion['estadoMinuta'];     // NULL, 'BORRADOR', 'PENDIENTE', 'APROBADA'
                                    $meetingStartTime = strtotime($reunion['fechaInicioReunion']);
                                    ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($idMinuta); ?></strong></td>

                                        <td><?php echo htmlspecialchars($reunion['nombreReunion']); ?></td>

                                        <td><?php echo htmlspecialchars($reunion['nombreComision']); ?></td>

                                        <td><?php echo htmlspecialchars(date('d-m-Y H:i', strtotime($reunion['fechaInicioReunion']))); ?></td>

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
                                                    <a href="/corevota/controllers/ReunionController.php?action=iniciarMinuta&idReunion=<?php echo $idReunion; ?>" class="btn btn-sm btn-primary" title="Crear e iniciar la edición de la minuta">
                                                        <i class="fas fa-play me-1"></i> Iniciar Reunión
                                                    </a>
                                                <?php
                                                }
                                                // Botones de EDITAR y BORRAR
                                                ?>
                                                <a href="menu.php?pagina=reunion_editar&id=<?php echo $idReunion; ?>" class="btn btn-secondary btn-sm ms-1" title="Editar Detalles de la Reunión (horario, nombre, etc.)">
                                                    <i class="fas fa-pencil-alt"></i>
                                                </a>
                                                <a href="/corevota/controllers/ReunionController.php?action=delete&id=<?php echo $idReunion; ?>" class="btn btn-sm btn-danger ms-1" title="Deshabilitar Reunión">
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
                                                // --- 4. ESTADO APROBADA (Finalizada real) ---
                                            ?>
                                                <span title="La minuta fue guardada como borrador y está pendiente de aprobación por más de un Presidente.">
                                                    <i class="fas fa-clock me-1 text-warning"></i></i> Finalizada esperando aprobación de minuta
                                                </span>
                                            <?php


                                            } else {
                                                // --- 5. ESTADO INVÁLIDO (Fallback) ---
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

                        <div class="d-flex justify-content-end">
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
        (function() {
            // Obtener los elementos del DOM
            const form = document.getElementById('filtrosForm');
            const inputQ = document.getElementById('q');
            const pHid = document.getElementById('pHidden');

            // Función para resetear la paginación a la página 1
            function toFirstPage() {
                if (pHid) pHid.value = '1';
            }

            // Variable para guardar el temporizador (para debounce)
            let searchTimer = null;

            // Escuchar el evento 'input' (cada vez que el usuario teclea)
            if (inputQ && form) {
                inputQ.addEventListener('input', () => {
                    // Limpiar el temporizador anterior
                    clearTimeout(searchTimer);

                    // Iniciar un nuevo temporizador
                    searchTimer = setTimeout(() => {
                        const val = (inputQ.value || '').trim();

                        // Si el texto tiene 4 o más caracteres, O si está vacío, filtrar.
                        if (val.length >= 4 || val.length === 0) {
                            toFirstPage(); // Volver a la página 1
                            form.submit(); // Enviar el formulario
                        }
                    }, 400); // Espera 400ms después de la última tecla antes de buscar
                });
            }
        })();
    </script>




</body>

</html>