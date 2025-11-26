<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="text-primary fw-bold"><i class="fas fa-sitemap me-2"></i> Gestión de Comisiones</h3>
        <a href="index.php?action=comision_crear" class="btn btn-success">
            <i class="fas fa-plus-circle me-2"></i> Nueva Comisión
        </a>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Nombre Comisión</th>
                            <th>Presidente</th>
                            <th>Vicepresidente</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data['comisiones'] as $c): ?>
                        <tr>
                            <td class="fw-bold text-dark">
                                <?php echo htmlspecialchars($c['nombreComision']); ?>
                            </td>
                            <td>
                                <?php if($c['nombrePresidente']): ?>
                                    <div class="d-flex align-items-center">
                                        <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center me-2" style="width: 30px; height: 30px;"><i class="fas fa-user-tie fa-xs"></i></div>
                                        <small><?php echo htmlspecialchars($c['nombrePresidente']); ?></small>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted small">Vacante</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($c['nombreVicepresidente']): ?>
                                    <span class="small text-secondary"><i class="fas fa-user me-1"></i> <?php echo htmlspecialchars($c['nombreVicepresidente']); ?></span>
                                <?php else: ?>
                                    <span class="text-muted small">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <a href="index.php?action=comision_editar&id=<?php echo $c['idComision']; ?>" class="btn btn-sm btn-outline-primary me-1" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="javascript:void(0);" onclick="eliminarComision(<?php echo $c['idComision']; ?>)" class="btn btn-sm btn-outline-danger" title="Eliminar">
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
function eliminarComision(id) {
    Swal.fire({
        title: '¿Eliminar Comisión?',
        text: "La comisión quedará inactiva (no se podrá usar en nuevas reuniones).",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Sí, eliminar'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `index.php?action=comision_eliminar&id=${id}`;
        }
    });
}
</script>