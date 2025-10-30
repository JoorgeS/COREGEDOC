<?php
// views/pages/reunion_listado.php
// La variable $reuniones es provista por ReunionController.php?action=list
if (!isset($reuniones)) {
    $reuniones = []; // Asegura que la variable exista si se accede directamente
}

// Los mensajes de sesión ahora se manejan en menu.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
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
    </style>
</head>

<body>
    <div class="container-fluid mt-4">
        <h3 class="mb-4">Listado de Reuniones</h3>

        <a href="menu.php?pagina=reunion_crear" class="btn btn-primary mb-4">
            <i class="fas fa-plus me-1"></i> Crear Nueva Reunión
        </a>

        <?php // Los mensajes de éxito/error se muestran en menu.php ?>

        <div class="card shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <?php if (empty($reuniones)): ?>
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
                                <?php foreach ($reuniones as $reunion): ?>
                                    <?php
                                    // Variables para esta fila
                                    $idReunion = $reunion['idReunion']; // ID de la reunión
                                    $idMinuta = $reunion['t_minuta_idMinuta']; // ID de la minuta (puede ser NULL)
                                    $estadoMinuta = $reunion['estadoMinuta']; // Estado (NULL, 'PENDIENTE', 'APROBADA')

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
                                        $estadoTexto = 'Programada'; // Estado si aún no tiene minuta
                                        $badge_class = 'bg-info text-dark';
                                    }
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
                                            // $now viene del controlador (ReunionController.php)
                                            $meetingStartTime = strtotime($reunion['fechaInicioReunion']);

                                            // --- Lógica Principal de Acciones ---

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
                                                // *** CASO 2: Minuta iniciada pero pendiente *** -> Botón Amarillo "Continuar Edición"
                                            ?>
                                                <a href="menu.php?pagina=editar_minuta&id=<?php echo $idMinuta; ?>" class="btn btn-sm btn-warning" title="Continuar editando la minuta">
                                                    <i class="fas fa-edit me-1"></i> Continuar Edición
                                                </a>
                                            <?php
                                            } elseif ($estadoMinuta === 'APROBADA') {
                                                // *** CASO 3: Minuta aprobada *** -> Mensaje "Reunión Finalizada"
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

                                            // --- INICIO DE LA CORRECCIÓN ---
                                            // Botones Adicionales (Editar y Eliminar Reunión)
                                            // Mostrar solo si la minuta AÚN NO se ha creado (reunión no iniciada)
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
                                            // --- FIN DE LA CORRECCIÓN ---
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="/corevota/public/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>

</html>