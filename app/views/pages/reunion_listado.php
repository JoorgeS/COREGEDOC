<?php
// app/views/pages/reunion_listado.php
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-calendar-check me-2 text-primary"></i> Gestión de Reuniones</h2>
        <a href="index.php?action=reunion_form" class="btn btn-success">
            <i class="fas fa-plus"></i> Nueva Reunión
        </a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Reunión</th>
                            <th>Comisión</th>
                            <th>Fecha Inicio</th>
                            <th>Estado</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reuniones)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="fas fa-calendar-times fa-3x mb-3"></i><br>
                                    No hay reuniones programadas vigentes.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($reuniones as $r): ?>
                                <?php 
                                    // Detectar si ya tiene minuta sincronizada
                                    $tieneMinuta = !empty($r['t_minuta_idMinuta']);
                                    $fechaInicio = strtotime($r['fechaInicioReunion']);
                                    $esHoy = date('Y-m-d', $fechaInicio) === date('Y-m-d');
                                ?>
                                <tr>
                                    <td>#<?php echo $r['idReunion']; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($r['nombreReunion']); ?></strong>
                                        <?php if($esHoy): ?>
                                            <span class="badge bg-info text-dark ms-2">HOY</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($r['nombreComision']); ?></td>
                                    <td>
                                        <i class="far fa-clock text-muted"></i> 
                                        <?php echo date('d/m/Y H:i', $fechaInicio); ?>
                                    </td>
                                    <td>
                                        <?php if ($tieneMinuta): ?>
                                            <span class="badge bg-success"><i class="fas fa-check"></i> Iniciada</span>
                                            <div style="font-size: 0.75rem;" class="text-muted">
                                                Minuta #<?php echo $r['t_minuta_idMinuta']; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark"><i class="fas fa-clock"></i> Programada</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group">
                                            <?php if (!$tieneMinuta): ?>
                                                <button type="button" class="btn btn-sm btn-primary" 
                                                        onclick="iniciarReunion(<?php echo $r['idReunion']; ?>, '<?php echo htmlspecialchars($r['nombreReunion']); ?>')">
                                                    <i class="fas fa-play"></i> Iniciar
                                                </button>
                                                
                                                <a href="index.php?action=reunion_editar&id=<?php echo $r['idReunion']; ?>" 
                                                   class="btn btn-sm btn-outline-secondary" title="Editar datos">
                                                    <i class="fas fa-edit"></i>
                                                </a>

                                                <button type="button" class="btn btn-sm btn-outline-danger" 
                                                        onclick="eliminarReunion(<?php echo $r['idReunion']; ?>)">
                                                    <i class="fas fa-trash"></i>
                                                </button>

                                            <?php else: ?>
                                                <a href="index.php?action=minuta_gestionar&id=<?php echo $r['t_minuta_idMinuta']; ?>" 
                                                   class="btn btn-sm btn-success">
                                                    <i class="fas fa-file-signature"></i> Gestionar Minuta
                                                </a>
                                            <?php endif; ?>
                                        </div>
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

<script>
    function iniciarReunion(idReunion, nombre) {
        Swal.fire({
            title: '¿Iniciar Reunión?',
            text: `Se generará la minuta para "${nombre}" y quedará vinculada.`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#0d6efd',
            confirmButtonText: 'Sí, Iniciar ahora',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                // Llamada al controlador para ejecutar la sincronización
                window.location.href = `index.php?action=reunion_iniciar_minuta&idReunion=${idReunion}`;
            }
        });
    }

    function eliminarReunion(id) {
        Swal.fire({
            title: '¿Eliminar?',
            text: "La reunión desaparecerá del listado.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'Sí, eliminar'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `index.php?action=reunion_eliminar&id=${id}`;
            }
        });
    }
</script>