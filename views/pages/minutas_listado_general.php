<?php
// views/pages/minutas_listado_general.php - Adaptada para t_tema

// Asegúrate de que $minutas esté definida por el controlador.
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Listado de Temas Guardados</title>
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
            vertical-align: middle;
        }
    </style>
</head>

<body>

    <div class="container-fluid mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="mb-0">Minutas de Reuniones Guardadas (Temas)</h3>
            <a href="/corevota/views/pages/crearMinuta.php" target="content-frame" class="btn btn-success">➕ Crear Minuta</a>
        </div>

        <?php
        if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['success'];
                                                unset($_SESSION['success']); ?></div>
        <?php elseif (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?php echo $_SESSION['error'];
                                            unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <div class="table-responsive shadow-sm">
            <table class="table table-striped table-bordered align-middle">
                <thead class="table-dark sticky-top">
                    <tr>
                        <th>ID</th>
                        <th>Nombre del Tema</th>
                        <th>Objetivo </th>
                        <!--<th>Compromisos (Extracto)</th>-->
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($minutas) || !is_array($minutas)): ?>
                    <?php else: ?>
                        <?php foreach ($minutas as $minuta): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($minuta['idTema']); ?></td>
                                <td><?php echo htmlspecialchars(substr($minuta['nombreTema'], 0, 50)) . '...'; ?></td>
                                <td><?php echo htmlspecialchars(substr($minuta['objetivo'], 0, 80)) . '...'; ?></td>
                                <!--<td><?php echo htmlspecialchars(substr($minuta['compromiso'], 0, 80)) . '...'; ?></td>-->
                                <td style="white-space: nowrap;">
                                    <a href="MinutaController.php?action=view&id=<?php echo $minuta['idTema']; ?>"
                                        class="btn btn-sm btn-info text-white me-2">
                                        Ver Minuta
                                    </a>
                                    <a href="MinutaController.php?action=edit&id=<?php echo $minuta['idTema']; ?>"
                                        class="btn btn-sm btn-warning">
                                        Editar
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