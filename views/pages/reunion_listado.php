<?php
// views/pages/reunion_listado.php
// La variable $reuniones es provista por ReunionController.php?action=list
if (!isset($reuniones)) {
    $reuniones = []; // Asegura que la variable exista si se accede directamente
}

// Mensajes de éxito o error (opcional pero útil)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$success_msg = $_SESSION['success'] ?? null;
$error_msg = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);
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
        <h3 class="mb-4">Listado de Reuniones Registradas</h3>

        <a href="menu.php?pagina=reunion_crear" class="btn btn-primary mb-4">
            <i class="fas fa-plus me-1"></i> Crear Nueva Reunión
        </a>

        <?php if ($success_msg): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success_msg); ?></div>
        <?php endif; ?>
        <?php if ($error_msg): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error_msg); ?></div>
        <?php endif; ?>

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
                                    // Determinamos el estado basado en la minuta vinculada
                                    $estado = $reunion['estadoMinuta'] ?? 'Error';
                                    $idMinuta = $reunion['t_minuta_idMinuta'];

                                    $badge_class = 'bg-secondary';
                                    if ($estado === 'PENDIENTE') $badge_class = 'bg-warning text-dark';
                                    if ($estado === 'APROBADA') $badge_class = 'bg-success';
                                    ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($idMinuta); ?></strong></td>
                                        <td><?php echo htmlspecialchars($reunion['nombreComision']); ?></td>
                                        <td><?php echo htmlspecialchars($reunion['nombreReunion']); ?></td>
                                        <td><?php echo htmlspecialchars(date('d-m-Y H:i', strtotime($reunion['fechaInicioReunion']))); ?></td>
                                        <td><?php echo htmlspecialchars(date('d-m-Y H:i', strtotime($reunion['fechaTerminoReunion']))); ?></td>
                                        <td>
                                            <span class="badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($estado); ?></span>
                                        </td>

                                        <td style="white-space: nowrap;">
                                            <?php if ($estado === 'APROBADA'): ?>
                                                <a href="menu.php?pagina=minutas_aprobadas" class="btn btn-sm btn-success">
                                                    <i class="fas fa-check-circle me-1"></i> Ver Aprobada
                                                </a>

                                            <?php elseif ($estado === 'PENDIENTE'): ?>
                                                <a href="menu.php?pagina=editar_minuta&id=<?php echo $idMinuta; ?>" class="btn btn-sm btn-warning">
                                                    <i class="fas fa-edit me-1"></i> Continuar Minuta
                                                </a>
                                                <a href="/corevota/controllers/ReunionController.php?action=delete&id=<?php echo $reunion['idReunion']; ?>"
                                                    class="btn btn-sm btn-danger"
                                                    onclick="return confirm('¿Está seguro de que desea deshabilitar esta reunión?');">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-danger">Inválido</span>
                                            <?php endif; ?>
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