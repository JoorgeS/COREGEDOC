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
   PAGINACIÓN (no rompe lógica)
   ========================= */
$perPage = isset($_GET['per_page']) ? max(1, (int)$_GET['per_page']) : 10;  // 10 por defecto
$total   = count($reuniones);
$pages   = max(1, (int)ceil($total / $perPage));
$page    = isset($_GET['p']) ? (int)$_GET['p'] : 1;
$page    = max(1, min($page, $pages));
$offset  = ($page - 1) * $perPage;

// Subconjunto a mostrar
$reunionesPage = array_slice($reuniones, $offset, $perPage);

// Helper para paginación
function renderPagination($current, $pages) {
    if ($pages <= 1) return;
    // Preservar querystring existente
    echo '<nav aria-label="Paginación"><ul class="pagination pagination-sm mt-3">';
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
    </style>
</head>
<body>
    <div class="container-fluid mt-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="mb-0">Listado de Reuniones</h3>
            <a href="menu.php?pagina=reunion_crear" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i> Crear Nueva Reunión
            </a>
        </div>

        <?php // Los mensajes de éxito/error se muestran en menu.php ?>

        <div class="card shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <?php if (empty($reunionesPage)): ?>
                        <div class="alert alert-info">No hay reuniones vigentes registradas.</div>
                    <?php else: ?>
                        <table class="table table-striped table-hover">
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
                                                // *** CASO 1: Aún no se ha iniciado la minuta ***
                                                if ($now < $meetingStartTime) {
                                                    // 1a: Reunión futura -> Mensaje informativo
                                                    $horaInicioFormato = htmlspecialchars(date('H:i', $meetingStartTime));
                                                    $fechaInicioFormato = htmlspecialchars(date('d-m-Y', $meetingStartTime));
                                                    ?>
                                                    <span class="text-muted" title="Programada para el <?php echo $fechaInicioFormato; ?> a las <?php echo $horaInicioFormato; ?>">
                                                        <i class="fas fa-clock me-1"></i> Iniciar se habilita a las <?php echo $horaInicioFormato; ?>
                                                    </span>
                                                    <?php
                                                } else {
                                                    // 1b: Hora de inicio ya pasó -> Botón Azul "Iniciar Reunión"
                                                    ?>
                                                    <a href="/corevota/controllers/ReunionController.php?action=iniciarMinuta&idReunion=<?php echo $idReunion; ?>" class="btn btn-sm btn-primary" title="Crear e iniciar la edición de la minuta">
                                                        <i class="fas fa-play me-1"></i> Iniciar Reunión
                                                    </a>
                                                    <?php
                                                }
                                            } elseif ($estadoMinuta === 'PENDIENTE') {
                                                // *** CASO 2: Minuta iniciada pero pendiente ***
                                                ?>
                                                <a href="menu.php?pagina=editar_minuta&id=<?php echo $idMinuta; ?>" class="btn btn-sm btn-warning" title="Continuar editando la minuta">
                                                    <i class="fas fa-edit me-1"></i> Continuar Edición
                                                </a>
                                                <?php
                                            } elseif ($estadoMinuta === 'APROBADA') {
                                                // *** CASO 3: Minuta aprobada ***
                                                ?>
                                                <span class="text-success">
                                                    <i class="fas fa-check-circle me-1"></i> Reunión Finalizada
                                                </span>
                                                <?php
                                            } else {
                                                // Estado desconocido
                                                ?>
                                                <span class="text-danger" title="Estado de minuta desconocido: <?php echo htmlspecialchars($estadoMinuta); ?>">
                                                    <i class="fas fa-exclamation-circle me-1"></i> Estado Inválido
                                                </span>
                                                <?php
                                            }

                        /* Botones Editar / Eliminar visibles sólo si aún no hay minuta creada */
                                            if ($idMinuta === null) {
                                                ?>
                                                <a href="menu.php?pagina=reunion_editar&id=<?php echo $idReunion; ?>" class="btn btn-secondary btn-sm ms-1" title="Editar Detalles de la Reunión (horario, nombre, etc.)">
                                                    <i class="fas fa-pencil-alt"></i>
                                                </a>
                                                <a href="/corevota/controllers/ReunionController.php?action=delete&id=<?php echo $idReunion; ?>"
                                                   class="btn btn-sm btn-danger ms-1"
                                                   title="Deshabilitar Reunión"
                                                   onclick="return confirm('¿Está seguro de que desea deshabilitar esta reunión? Esta acción la quitará del listado activo.');">
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

                        <!-- Paginación -->
                        <?php renderPagination($page, $pages); ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="/corevota/public/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
