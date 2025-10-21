<?php
// views/pages/comisiones_listado.php
// Asegúrate de que $comisiones esté definida por el controlador.
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Listado de Comisiones</title>
    <link href="/corevota/public/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .container-fluid {
            padding: 20px;
        }

        .table-responsive {
            max-height: 70vh;
            overflow-y: auto;
        }

        .table th,
        .table td {
            white-space: nowrap;
        }

        .table tbody tr td:nth-child(2) {
            width: 100%;
        }

        /* Fuerza el ancho del nombre */
    </style>
</head>

<body>

    <div class="container-fluid mt-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="mb-0">Listado de Comisiones</h3>
            <a href="menu.php?pagina=comision_crear" class="btn btn-success">Registrar Nueva Comisión</a>
        </div>

        <div class="table-responsive shadow-sm">
            <table class="table table-striped table-bordered align-middle">
                <thead class="table-dark sticky-top">
                    <tr>
                        <th>ID</th>
                        <th>Nombre de la Comisión</th>
                        <th>Vigencia</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($comisiones) || !is_array($comisiones)): ?>
                        <tr>
                            <td colspan="4" class="text-center py-4">No hay comisiones registradas.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($comisiones as $comision): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($comision['idComision']); ?></td>
                                <td><?php echo htmlspecialchars($comision['nombreComision']); ?></td>
                                <td>
                                    <span class="badge <?php echo $comision['vigencia'] == 1 ? 'bg-success' : 'bg-danger'; ?>">
                                        <?php echo $comision['vigencia'] == 1 ? 'Activa' : 'Inactiva'; ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="menu.php?pagina=comision_editar&id=<?php echo $comision['idComision']; ?>" class="btn btn-sm btn-primary me-2">Editar</a>
                                    <a href="/corevota/controllers/ComisionController.php?action=delete&id=<?php echo $comision['idComision']; ?>"
                                        class="btn btn-sm btn-danger"
                                        onclick="return confirm('¿Está seguro de deshabilitar esta comisión?');">
                                        Deshabilitar
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>

</html>