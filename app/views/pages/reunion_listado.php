<?php
// app/views/pages/reunion_listado.php
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-calendar-check me-2 text-primary"></i> Gestión de Reuniones</h2>

        <div>
            <a href="index.php?action=reuniones_dashboard" class="btn btn-outline-secondary me-2">
                <i class="fas fa-arrow-left"></i> Volver al Menú
            </a>
            <a href="index.php?action=reunion_form" class="btn btn-success">
                <i class="fas fa-plus"></i> Nueva Reunión
            </a>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Folio / N°</th>
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
                                // Detectar si ya tiene minuta (ID OFICIAL)
                                $idOficial = $r['t_minuta_idMinuta']; // Si es null, no ha iniciado
                                $tieneMinuta = !empty($idOficial);

                                $fechaInicio = strtotime($r['fechaInicioReunion']);
                                $esHoy = date('Y-m-d', $fechaInicio) === date('Y-m-d');
                                ?>
                                <tr>
                                    <td>
                                        <?php if ($tieneMinuta): ?>
                                            <span class="badge bg-dark fs-6" style="min-width: 40px;">
                                                #<?php echo $idOficial; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-light text-muted border border-secondary border-dashed">
                                                <i class="fas fa-hourglass-start"></i> Pendiente
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <strong><?php echo htmlspecialchars($r['nombreReunion']); ?></strong>
                                        <?php if ($esHoy): ?>
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
                                            <span class="badge bg-success"><i class="fas fa-check"></i> En curso / Finalizada</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark"><i class="fas fa-clock"></i> Programada</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group">
                                            <?php if (!$tieneMinuta): ?>
                                                <button type="button" class="btn btn-sm btn-primary"
                                                    onclick="iniciarReunion(<?php echo $r['idReunion']; ?>, '<?php echo htmlspecialchars($r['nombreReunion']); ?>')">
                                                    <i class="fas fa-play"></i> Asignar Folio e Iniciar
                                                </button>

                                                <a href="index.php?action=reunion_form&id=<?php echo $r['idReunion']; ?>"
                                                    class="btn btn-sm btn-outline-secondary" title="Editar">
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

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Obtenemos los parámetros de la URL
        const urlParams = new URLSearchParams(window.location.search);
        const msg = urlParams.get('msg');

        if (msg) {
            let title = '';
            let text = '';
            let icon = 'success';

            // Personalizamos el mensaje según la acción
            if (msg === 'guardado') {
                title = '¡Reunión Creada!';
                text = 'La reunión ha sido programada exitosamente.';
            } else if (msg === 'editado') {
                title = '¡Cambios Guardados!';
                text = 'La información de la reunión ha sido actualizada.';
            } else if (msg === 'eliminado') {
                title = '¡Eliminado!';
                text = 'La reunión ha sido eliminada del listado.';
                icon = 'warning'; // Un ícono diferente para eliminar
            }

            // Disparamos el SweetAlert
            if (title) {
                Swal.fire({
                    title: title,
                    text: text,
                    icon: icon,
                    confirmButtonText: 'Aceptar',
                    confirmButtonColor: '#0d6efd',
                    timer: 3000, // Se cierra solo a los 3 segundos
                    timerProgressBar: true
                }).then(() => {
                    // OPCIONAL: Limpiar la URL para que si recargas no salga el mensaje de nuevo
                    cleanUrl();
                });
            }
        }
    });

    function cleanUrl() {
        // Esta función quita el "?msg=..." de la barra de direcciones sin recargar
        const url = new URL(window.location);
        url.searchParams.delete('msg');
        window.history.replaceState({}, '', url);
    }
</script>