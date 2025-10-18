<?php
// Este archivo es views/pages/reunion_listado.php

// La variable $reuniones fue pasada por el controlador
if (!isset($reuniones)) {
    $reuniones = []; // Evita errores si no se pasó la variable
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Listado de Reuniones</title>
    <link href="/corevota/public/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>

    <div class="container mt-4">
        <h3 class="mb-4">Listado de Reuniones Registradas</h3>

        <?php if (empty($reuniones)): ?>
            <div class="alert alert-info">No hay reuniones registradas.</div>
        <?php else: ?>
            <table class="table table-striped table-bordered">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Comisión</th>
                        <th>Reunión</th>
                        <th>N°</th>
                        <th>Inicio</th>
                        <th>Término</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reuniones as $reunion): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($reunion['idReunion']); ?></td>
                            <td><?php echo htmlspecialchars($reunion['nombreComision']); ?></td>
                            <td><?php echo htmlspecialchars($reunion['nombreReunion']); ?></td>
                            <td><?php echo htmlspecialchars($reunion['numeroReunion']); ?></td>
                            <td><?php echo htmlspecialchars($reunion['fechaInicioReunion']); ?></td>
                            <td><?php echo htmlspecialchars($reunion['fechaTerminoReunion']); ?></td>
                            <td>
                                <a href="/corevota/views/pages/crearReunion.php?action=edit&id=<?php echo $reunion['idReunion']; ?>" class="btn btn-sm btn-warning">Editar</a>

                                <button class="btn btn-sm btn-danger"
                                    onclick="confirmarEliminacion(<?php echo $reunion['idReunion']; ?>)">
                                    Eliminar
                                </button>
                            </td>

                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <a href="/corevota/views/pages/crearReunion.php" class="btn btn-primary mt-3">
            + Crear Nueva Reunión
        </a>
    </div>



<script>
function confirmarEliminacion(idReunion) {
    if (confirm("¿Estás seguro de que deseas eliminar la Reunión ID " + idReunion + "? Esta acción es irreversible.")) {
        eliminarReunion(idReunion);
    }
}

function eliminarReunion(idReunion) {
    fetch(`/corevota/controllers/ReunionController.php?action=delete&id=${idReunion}`, {
        method: 'POST', // Usamos POST para la acción de eliminación
        headers: {
            'Content-Type': 'application/json'
        }
    })
    .then(res => res.json())
    .then(resp => {
        if (resp.status === 'success') {
            alert('✅ Reunión eliminada (lógicamente) con éxito.');
            // Recargar la página para actualizar la lista:
            window.location.reload(); 
        } else {
            alert('⚠️ Error al eliminar: ' + (resp.message || 'Error desconocido.'));
            console.error(resp.error);
        }
    })
    .catch(err => {
        alert('Error de conexión al intentar eliminar la reunión.');
        console.error(err);
    });
}
</script>

</body>

</html>