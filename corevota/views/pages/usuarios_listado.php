<?php
// RUTA CRÍTICA: Desde views/pages/ subimos dos niveles (../../) a la raíz para encontrar Usuario.php
require_once(__DIR__ . '/../../Usuario.php'); 

$usuarioObj = new Usuario();
$usuarios = $usuarioObj->listarUsuarios();

// Obtener mensajes de estado
$status = $_GET['status'] ?? '';
$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Listado de Usuarios</title>
    <link href="/corevota/public/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    </head>
<body>
    <div class="card p-4 shadow-sm">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">Listado de Usuarios</h2>
            <a href="usuario_formulario.php?action=create" class="btn btn-success">
                Registrar Nuevo Usuario
            </a>
        </div>

        <?php if ($status && $msg): // Muestra mensajes de éxito o error ?>
            <div class="alert alert-<?php echo ($status === 'success' ? 'success' : 'danger'); ?>" role="alert">
                <?php echo htmlspecialchars($msg); ?>
            </div>
        <?php endif; ?>

        <div class="table-responsive" style="max-height: 70vh; overflow-y: auto;">
            <table class="table table-hover table-sm">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre Completo</th>
                        <th>Correo</th>
                        <th>Perfil</th>
                        <th>Tipo Usuario</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($usuarios): ?>
                        <?php foreach ($usuarios as $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['idUsuario']); ?></td>
                            <td><?php echo htmlspecialchars($user['pNombre'] . ' ' . $user['aPaterno']); ?></td>
                            <td><?php echo htmlspecialchars($user['correo']); ?></td>
                            <td><?php echo htmlspecialchars($user['perfil_desc']); ?></td>
                            <td><?php echo htmlspecialchars($user['tipoUsuario_desc']); ?></td>
                            <td>
                                <a href="usuario_formulario.php?action=edit&id=<?php echo $user['idUsuario']; ?>" class="btn btn-sm btn-primary me-2" title="Editar">
                                    Editar
                                </a>
                                <form action="usuario_acciones.php" method="POST" class="d-inline" onsubmit="return confirm('¿Está seguro de que desea eliminar a este usuario?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="idUsuario" value="<?php echo $user['idUsuario']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger" title="Eliminar">
                                        Eliminar
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center">No hay usuarios registrados.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <script src="/corevota/public/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>