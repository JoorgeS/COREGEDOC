<div class="container-fluid">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php?action=home">Inicio</a></li>
            <li class="breadcrumb-item"><a href="index.php?action=minutas_dashboard">Minutas</a></li>
            <li class="breadcrumb-item active">Aprobadas</li>
        </ol>
    </nav>

    <div class="card shadow-sm">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0 text-success"><i class="fas fa-check-circle me-2"></i> Minutas Aprobadas</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Fecha Aprobación</th>
                            <th>Comisión</th>
                            <th>Documento</th>
                            <th class="text-end">Opciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($data['minutas'])): ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">No hay minutas aprobadas aún.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($data['minutas'] as $m): ?>
                                <tr>
                                    <td>#<?php echo $m['idMinuta']; ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($m['fechaMinuta'])); ?></td> <td><?php echo htmlspecialchars($m['nombreComision']); ?></td>
                                    <td>
                                        <?php if (!empty($m['pathArchivo'])): ?>
                                            <a href="<?php echo $m['pathArchivo']; ?>" target="_blank" class="text-danger text-decoration-none">
                                                <i class="fas fa-file-pdf fa-lg me-1"></i> PDF Firmado
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">No disponible</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <a href="index.php?action=minuta_ver_historial&id=<?php echo $m['idMinuta']; ?>" class="btn btn-sm btn-outline-secondary">
                                            <i class="fas fa-history"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>