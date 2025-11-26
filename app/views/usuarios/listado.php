<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="text-primary fw-bold"><i class="fas fa-users-cog me-2"></i> Gestión de Usuarios</h3>
        <a href="index.php?action=usuario_crear" class="btn btn-success"><i class="fas fa-user-plus me-2"></i> Nuevo Usuario</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Nombre</th>
                        <th>Correo</th>
                        <th>Rol</th>
                        <th>Partido</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($data['usuarios'] as $u): ?>
                    <tr>
                        <td>
                            <div class="fw-bold"><?php echo htmlspecialchars($u['pNombre'] . ' ' . $u['aPaterno']); ?></div>
                        </td>
                        <td><?php echo htmlspecialchars($u['correo']); ?></td>
                        <td>
                            <?php 
                                $color = 'secondary';
                                if($u['tipoUsuario_id'] == ROL_ADMINISTRADOR) $color = 'danger'; // Admin
                                if($u['tipoUsuario_id'] == ROL_CONSEJERO) $color = 'primary'; // Consejero
                                if($u['tipoUsuario_id'] == ROL_SECRETARIO_TECNICO) $color = 'warning text-dark'; // Secretario
                            ?>
                            <span class="badge bg-<?php echo $color; ?>"><?php echo htmlspecialchars($u['descTipoUsuario'] ?? 'N/A'); ?></span>
                        </td>
                        <td><?php echo htmlspecialchars($u['nombrePartido'] ?? '-'); ?></td>
                        <td class="text-end">
                            <a href="index.php?action=usuario_editar&id=<?php echo $u['idUsuario']; ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a>
                            <a href="javascript:void(0);" onclick="borrarUsuario(<?php echo $u['idUsuario']; ?>)" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function borrarUsuario(id) {
    if(confirm('¿Estás seguro de eliminar este usuario?')) {
        window.location.href = `index.php?action=usuario_eliminar&id=${id}`;
    }
}
</script>