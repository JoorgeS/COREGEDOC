<div class="container-fluid">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php?action=home">Inicio</a></li>
            <li class="breadcrumb-item"><a href="index.php?action=minutas_dashboard">Minutas</a></li>
            <li class="breadcrumb-item active">Pendientes</li>
        </ol>
    </nav>

    <div class="card shadow-sm">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 text-warning"><i class="fas fa-clock me-2"></i> Minutas Pendientes</h5>
            <a href="index.php?action=crear_minuta" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Nueva Minuta</a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Fecha</th>
                            <th>Comisión</th>
                            <th>Temas</th>
                            <th>Estado</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($data['minutas'])): ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">No hay minutas pendientes.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($data['minutas'] as $m): ?>
                                <tr>
                                    <td>#<?php echo $m['idMinuta']; ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($m['fechaMinuta'])); ?></td>
                                    <td>
                                        <span class="fw-bold"><?php echo htmlspecialchars($m['nombreComision']); ?></span><br>
                                        <small class="text-muted">Pres: <?php echo htmlspecialchars($m['presidenteNombre'] . ' ' . $m['presidenteApellido']); ?></small>
                                    </td>
                                    <td><small><?php echo $m['nombreTemas']; ?></small></td>
                                    <td>
                                        <?php 
                                            $badge = 'secondary';
                                            if($m['estadoMinuta'] == 'REQUIERE_REVISION') $badge = 'danger';
                                            if($m['estadoMinuta'] == 'BORRADOR') $badge = 'info text-dark';
                                        ?>
                                        <span class="badge bg-<?php echo $badge; ?>"><?php echo $m['estadoMinuta']; ?></span>
                                    </td>
                                    <td class="text-end">
                                        <a href="index.php?action=minuta_gestionar&id=<?php echo $m['idMinuta']; ?>" class="btn btn-sm btn-outline-primary" title="Gestionar">
                                            <i class="fas fa-search"></i>
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