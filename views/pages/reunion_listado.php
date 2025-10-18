<?php
// reunion_listado.php
// La variable $reuniones es provista por ReunionController.php?action=list
if (!isset($reuniones)) {
    $reuniones = []; // Asegura que la variable exista si se accede directamente
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Listado de Reuniones</title>
    <link href="/corevota/public/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        /* Estilos CSS para asegurar el margen de la tabla */
        .table-responsive {
            margin-top: 20px;
        }
    </style>
</head>
<body>

<div class="container mt-4">
    <h3 class="mb-4">Listado de Reuniones Registradas</h3>
    
    <a href="/corevota/views/pages/crearReunion.php" class="btn btn-primary mb-4">
        ➕ Crear Nueva Reunión
    </a>
    
    <div class="table-responsive">
        <?php if (empty($reuniones)): ?>
            <div class="alert alert-info">No hay reuniones vigentes registradas.</div>
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
    </div>
</div>

<script src="/corevota/public/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

<script>
    function confirmarEliminacion(idReunion) {
        if (confirm("¿Estás seguro de que deseas eliminar (lógicamente) la Reunión ID " + idReunion + "? Esta acción es irreversible.")) {
            eliminarReunion(idReunion);
        }
    }

    function eliminarReunion(idReunion) {
        fetch(`/corevota/controllers/ReunionController.php?action=delete&id=${idReunion}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        })
        .then(res => res.json())
        .then(resp => {
            if (resp.status === 'success') {
                alert('✅ Reunión eliminada (lógicamente) con éxito. La lista se actualizará.');
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