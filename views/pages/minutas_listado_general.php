<?php
// views/pages/minutas_listado_general.php
$idUsuarioLogueado = $_SESSION['idUsuario'] ?? null;

// Recuperar el estado actual y los valores de filtro pasados por el controlador
$estadoActual = $estado_filtro ?? $_GET['estado'] ?? 'PENDIENTE'; // Asegura tener el estado
$currentStartDate = $filtro_startDate ?? '';
$currentEndDate = $filtro_endDate ?? '';
$currentThemeName = $filtro_themeName ?? '';

// Determinar el título de la página según el estado
$pageTitle = ($estadoActual === 'APROBADA') ? 'Minutas Aprobadas' : 'Minutas Pendientes';
// Determinar la 'pagina' correcta para el formulario
$paginaForm = ($estadoActual === 'APROBADA') ? 'minutas_aprobadas' : 'minutas_pendientes';
?>

<div class="container-fluid mt-4">
    <h3 class="mb-3"><?php echo $pageTitle; ?></h3>

    <form method="GET" class="mb-4 p-3 border rounded bg-light">
        <input type="hidden" name="pagina" value="<?php echo $paginaForm; ?>">
        <input type="hidden" name="estado" value="<?php echo $estadoActual; ?>">

        <div class="row g-3 align-items-end">
            <div class="col-md-3">
                <label for="startDate" class="form-label">Fecha Creación Desde:</label>
                <input type="date" class="form-control form-control-sm" id="startDate" name="startDate" value="<?php echo htmlspecialchars($currentStartDate); ?>">
            </div>
            <div class="col-md-3">
                <label for="endDate" class="form-label">Fecha Creación Hasta:</label>
                <input type="date" class="form-control form-control-sm" id="endDate" name="endDate" value="<?php echo htmlspecialchars($currentEndDate); ?>">
            </div>
            <div class="col-md-4">
                <label for="themeName" class="form-label">Nombre del Tema:</label>
                <input type="text" class="form-control form-control-sm" id="themeName" name="themeName" placeholder="Buscar por tema..." value="<?php echo htmlspecialchars($currentThemeName); ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary btn-sm w-100">Filtrar</button>
            </div>
        </div>
    </form>
    <div class="table-responsive shadow-sm">
        <table class="table table-striped table-bordered align-middle">
            <thead class="table-dark sticky-top">
                <tr>
                    <th>ID</th>
                    <th>Nombre(s) del Tema</th>
                    <th>Objetivo(s)</th>
                    <th>Asistentes</th>
                    <th>Fecha Creación</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($minutas) || !is_array($minutas)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">No hay minutas que coincidan con los filtros aplicados en este estado.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($minutas as $minuta): ?>
                        <tr>
                            <?php
                            $minutaId = $minuta['idMinuta'];
                            $estado = $minuta['estadoMinuta'] ?? 'PENDIENTE';
                            $presidenteAsignado = $minuta['t_usuario_idPresidente'] ?? null;
                            $asistentes = $minuta['asistentes'] ?? 'N/A';
                            $fechaCreacion = $minuta['fechaMinuta'] ?? 'N/A'; // Obtener fecha
                            ?>

                            <td><?php echo htmlspecialchars($minutaId); ?></td>
                            <td><?php echo htmlspecialchars(substr($minuta['nombreTema'] ?? 'N/A', 0, 50)) . '...'; ?></td>
                            <td><?php echo htmlspecialchars(substr($minuta['objetivo'] ?? 'N/A', 0, 80)) . '...'; ?></td>
                            <td>
                                <?php // Mostrar asistentes con tooltip
                                $maxChars = 40;
                                if (strlen($asistentes) > $maxChars) {
                                    echo '<span title="' . htmlspecialchars($asistentes) . '">' . htmlspecialchars(substr($asistentes, 0, $maxChars)) . '...</span>';
                                } else {
                                    echo htmlspecialchars($asistentes);
                                }
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars($fechaCreacion); ?></td>

                            <td style="white-space: nowrap;">
                                <?php if ($estado === 'PENDIENTE'): ?>
                                    <a href="menu.php?pagina=editar_minuta&id=<?php echo $minutaId; ?>" class="btn btn-sm btn-info text-white me-2">Editar</a>
                                    <?php if ($idUsuarioLogueado && (int)$idUsuarioLogueado === (int)$presidenteAsignado): ?>
                                        <button type="button" class="btn btn-sm btn-primary" onclick="aprobarMinuta(<?php echo $minutaId; ?>)">🔒 Firmar y Aprobar</button>
                                    <?php endif; ?>
                                <?php elseif ($estado === 'APROBADA'): ?>
                                    <a href="<?php echo htmlspecialchars($minuta['pathArchivo'] ?? '#'); ?>" target="_blank" class="btn btn-sm btn-success <?php echo empty($minuta['pathArchivo']) ? 'disabled' : ''; ?>">Ver PDF Fijo</a>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<script>
    function aprobarMinuta(idMinuta) {
        /* ... (tu función aprobarMinuta sin cambios) ... */
        if (!confirm("¿Está seguro de FIRMAR y APROBAR esta minuta? ¡Irreversible!")) {
            return;
        }
        fetch("/corevota/controllers/aprobar_minuta.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({
                    idMinuta: idMinuta
                })
            })
            .then(res => res.ok ? res.json() : res.text().then(text => Promise.reject(new Error(text))))
            .then(response => {
                if (response.status === 'success') {
                    alert("✅ Minuta aprobada.");
                    window.location.reload(); // Recarga la vista actual (pendientes o aprobadas)
                } else {
                    alert(`⚠️ Error: ${response.message}`);
                }
            })
            .catch(err => alert("Error de red al aprobar:\n" + err.message));
    }
</script>