<?php
// views/pages/minutas_listado_general.php

// 🔒 Asegúrate de que el ID del usuario logueado esté disponible aquí
// Esto DEBE estar al inicio del archivo, antes del HTML
$idUsuarioLogueado = $_SESSION['idUsuario'] ?? null;
?>

<div class="table-responsive shadow-sm">
    <table class="table table-striped table-bordered align-middle">
        <thead class="table-dark sticky-top">
            <tr>
                <th>ID</th>
                <th>Nombre del Tema</th>
                <th>Objetivo</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($minutas) || !is_array($minutas)): ?>
                <tr>
                    <td colspan="4" class="text-center text-muted">No hay minutas en este estado.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($minutas as $minuta): ?>
                    <tr>
                        <?php
                        // Asignación de variables para mayor legibilidad y seguridad
                        $minutaId = $minuta['idTema'] ?? $minuta['idMinuta'];
                        $estado = $minuta['estadoMinuta'] ?? 'PENDIENTE';
                        $presidenteAsignado = $minuta['t_usuario_idPresidente'] ?? null;
                        ?>

                        <td><?php echo htmlspecialchars($minutaId); ?></td>
                        <td><?php echo htmlspecialchars(substr($minuta['nombreTema'] ?? 'N/A', 0, 50)) . '...'; ?></td>
                        <td><?php echo htmlspecialchars(substr($minuta['objetivo'] ?? 'N/A', 0, 80)) . '...'; ?></td>

                        <td style="white-space: nowrap;">

                            <?php if ($estado === 'PENDIENTE'): ?>

                                <a href="menu.php?pagina=editar_minuta&id=<?php echo $minutaId; ?>"
                                    class="btn btn-sm btn-info text-white me-2">
                                    Editar
                                </a>

                                <?php if ($idUsuarioLogueado && (int)$idUsuarioLogueado === (int)$presidenteAsignado): ?>
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-primary"
                                        onclick="aprobarMinuta(<?php echo $minutaId; ?>)">
                                        🔒 Firmar y Aprobar
                                    </button>
                                <?php endif; ?>

                            <?php elseif ($estado === 'APROBADA'): ?>

                                <a href="<?php echo $minuta['pathArchivo'] ?? '#'; ?>"
                                    target="_blank"
                                    class="btn btn-sm btn-success">
                                    Ver PDF Fijo
                                </a>

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

<script>
    function aprobarMinuta(idMinuta) {
        if (!confirm("¿Está seguro de que desea FIRMAR y APROBAR esta minuta? ¡Esta acción es irreversible!")) {
            return;
        }

        // Apuntamos al controlador que hace la lógica
        fetch("/corevota/controllers/aprobar_minuta.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({
                    idMinuta: idMinuta
                })
            })
            .then(res => res.json())
            .then(response => {
                if (response.status === 'success') {
                    alert("✅ Minuta aprobada y sellada con éxito. Documento fijo creado.");
                    // Recargamos la página para que la minuta pase de "Pendientes" a "Aprobadas"
                    window.location.reload();
                } else {
                    alert(`⚠️ Error: ${response.message}`);
                }
            })
            .catch(err => {
                console.error("Fallo la conexión:", err);
                alert("Ocurrió un error de red al intentar aprobar la minuta.");
            });
    }
</script>