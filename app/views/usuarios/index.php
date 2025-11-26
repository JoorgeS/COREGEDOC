<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="text-primary fw-bold"><i class="fas fa-users-cog me-2"></i> Gestión de Usuarios</h3>
        <a href="index.php?action=usuario_crear" class="btn btn-success">
            <i class="fas fa-user-plus me-2"></i> Nuevo Usuario
        </a>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Nombre Completo</th>
                            <th>Correo / Usuario</th>
                            <th>Rol</th>
                            <th>Partido / Provincia</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data['usuarios'] as $u): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center me-3" style="width: 35px; height: 35px;">
                                        <i class="fas fa-user"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold"><?php echo htmlspecialchars($u['pNombre'] . ' ' . $u['aPaterno']); ?></div>
                                        <div class="small text-muted"><?php echo htmlspecialchars($u['sNombre'] . ' ' . $u['aMaterno']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($u['correo']); ?></td>
                            <td>
                                <?php 
                                    $badgeColor = 'secondary';
                                    if($u['tipoUsuario_id'] == ROL_ADMINISTRADOR) $badgeColor = 'danger';
                                    if($u['tipoUsuario_id'] == ROL_SECRETARIO_TECNICO) $badgeColor = 'warning text-dark';
                                    if($u['tipoUsuario_id'] == ROL_CONSEJERO) $badgeColor = 'primary';
                                ?>
                                <span class="badge bg-<?php echo $badgeColor; ?>"><?php echo htmlspecialchars($u['descTipoUsuario']); ?></span>
                            </td>
                            <td class="small">
                                <div><i class="fas fa-flag me-1 text-muted"></i> <?php echo htmlspecialchars($u['nombrePartido'] ?? 'N/A'); ?></div>
                                <div><i class="fas fa-map-marker-alt me-1 text-muted"></i> <?php echo htmlspecialchars($u['nombreProvincia'] ?? 'N/A'); ?></div>
                            </td>
                            <td class="text-end">
                                <a href="index.php?action=usuario_editar&id=<?php echo $u['idUsuario']; ?>" class="btn btn-sm btn-outline-primary me-1" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="javascript:void(0);" onclick="confirmarEliminarUsuario(<?php echo $u['idUsuario']; ?>)" class="btn btn-sm btn-outline-danger" title="Eliminar">
                                    <i class="fas fa-trash-alt"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function confirmarEliminarUsuario(id) {
    Swal.fire({
        title: '¿Eliminar Usuario?',
        text: "El usuario perderá acceso al sistema (Borrado lógico).",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Sí, eliminar'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `index.php?action=usuario_eliminar&id=${id}`;
        }
    });
}
</script>