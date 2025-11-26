<?php
$minutas = $data['minutas'];
$comisiones = $data['comisiones'];
$f = $data['filtros_activos'];
?>

<div class="container-fluid mt-4">
    
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php?action=minutas_dashboard">Minutas</a></li>
            <li class="breadcrumb-item active">Seguimiento General</li>
        </ol>
    </nav>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 fw-bold text-info"><i class="fas fa-tasks me-2"></i> Monitor de Estado de Minutas</h6>
        </div>
        <div class="card-body">

            <form method="GET" action="index.php" class="mb-4 p-3 border rounded bg-light">
                <input type="hidden" name="action" value="seguimiento_general">
                
                <div class="row g-3 align-items-end">
                    <div class="col-md-2">
                        <label class="form-label small fw-bold">ID Minuta</label>
                        <input type="number" class="form-control form-control-sm" name="idMinuta" value="<?php echo htmlspecialchars($f['idMinuta']); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Palabra Clave</label>
                        <input type="text" class="form-control form-control-sm" name="keyword" placeholder="Tema..." value="<?php echo htmlspecialchars($f['keyword']); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Comisión</label>
                        <select class="form-select form-select-sm" name="comisionId">
                            <option value="">-- Todas --</option>
                            <?php foreach ($comisiones as $c): ?>
                                <option value="<?php echo $c['idComision']; ?>" <?php echo ($f['comisionId'] == $c['idComision']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($c['nombreComision']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary btn-sm w-100"><i class="fas fa-filter"></i> Filtrar</button>
                    </div>
                    <div class="col-md-2">
                        <a href="index.php?action=seguimiento_general" class="btn btn-outline-secondary btn-sm w-100">Limpiar</a>
                    </div>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle text-center">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Comisión</th>
                            <th>Estado</th>
                            <th>Última Acción</th>
                            <th>Fecha Acción</th>
                            <th>Responsable</th>
                            <th>Ver</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($minutas)): ?>
                            <tr><td colspan="7" class="py-4 text-muted">No se encontraron resultados.</td></tr>
                        <?php else: ?>
                            <?php foreach ($minutas as $m): ?>
                                <tr>
                                    <td class="fw-bold">#<?php echo $m['idMinuta']; ?></td>
                                    <td class="text-start"><?php echo htmlspecialchars($m['nombreComision']); ?></td>
                                    <td>
                                        <?php 
                                            $badge = 'secondary';
                                            if($m['estadoMinuta'] == 'APROBADA') $badge = 'success';
                                            if($m['estadoMinuta'] == 'PENDIENTE') $badge = 'warning text-dark';
                                            if($m['estadoMinuta'] == 'REQUIERE_REVISION') $badge = 'danger';
                                        ?>
                                        <span class="badge bg-<?php echo $badge; ?>"><?php echo $m['estadoMinuta']; ?></span>
                                    </td>
                                    <td class="text-start small text-muted"><?php echo htmlspecialchars($m['ultimo_detalle']); ?></td>
                                    <td class="small"><?php echo date('d/m H:i', strtotime($m['ultima_fecha'])); ?></td>
                                    <td class="small"><?php echo htmlspecialchars($m['ultimo_usuario']); ?></td>
                                    <td>
                                        <a href="index.php?action=minuta_ver_historial&id=<?php echo $m['idMinuta']; ?>" class="btn btn-sm btn-info text-white" title="Ver Historial Completo">
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