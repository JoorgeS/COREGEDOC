<?php
// views/pages/minutas_listado_general.php
// Variables esperadas del Controlador:
// $minutas (array), $estadoActual (string), $currentStartDate (string), $currentEndDate (string), $currentThemeName (string)

$idUsuarioLogueado = $_SESSION['idUsuario'] ?? null;

// Determinar el título y la página del formulario
$estadoActual = $estadoActual ?? 'PENDIENTE'; // Valor por defecto
$pageTitle = ($estadoActual === 'APROBADA') ? 'Minutas Aprobadas' : 'Minutas Pendientes';
$paginaForm = ($estadoActual === 'APROBADA') ? 'minutas_aprobadas' : 'minutas_pendientes';

// Usar fechas de la URL si existen, si no, usar mes actual
$currentStartDate = $_GET['startDate'] ?? date('Y-m-01'); // Primer día del mes actual
$currentEndDate = $_GET['endDate'] ?? date('Y-m-d');     // Día actual

// Mantener filtro de tema si existe
$currentThemeName = $_GET['themeName'] ?? '';
?>

<div class="container-fluid mt-4">
    <h3 class="mb-3"><?php echo $pageTitle; ?></h3>

    <form method="GET" class="mb-4 p-3 border rounded bg-light">
        <input type="hidden" name="pagina" value="<?php echo $paginaForm; ?>">
        <input type="hidden" name="estado" value="<?php echo $estadoActual; ?>">

        <div class="row g-3 align-items-end">
            <div class="col-md-3">
                <label for="startDate" class="form-label">Fecha Creación Desde:</label>
                <input type="date" class="form-control form-control-sm" id="startDate" name="startDate" value="<?php echo htmlspecialchars($currentStartDate ?? ''); ?>">
            </div>
            <div class="col-md-3">
                <label for="endDate" class="form-label">Fecha Creación Hasta:</label>
                <input type="date" class="form-control form-control-sm" id="endDate" name="endDate" value="<?php echo htmlspecialchars($currentEndDate ?? ''); ?>">
            </div>
            <div class="col-md-4">
                <label for="themeName" class="form-label">Nombre del Tema:</label>
                <input type="text" class="form-control form-control-sm" id="themeName" name="themeName" placeholder="Buscar por tema..." value="<?php echo htmlspecialchars($currentThemeName ?? ''); ?>">
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
                    <th scope="col">ID</th>
                    <th scope="col">Nombre(s) del Tema</th>
                    <th scope="col">Objetivo(s)</th>
                    <th scope="col">Fecha Creación</th>
                    <th scope="col" class="text-center">Adjuntos</th> <th scope="col">Acciones</th>
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
                            $fechaCreacion = $minuta['fechaMinuta'] ?? 'N/A';
                            // Asegurarse de que totalAdjuntos exista (si no viene del controlador, será 0)
                            $totalAdjuntos = $minuta['totalAdjuntos'] ?? 0; 
                            ?>

                            <td><?php echo htmlspecialchars($minutaId); ?></td>
                            <td><?php echo $minuta['nombreTemas'] ?? 'N/A'; // Usar la columna del GROUP_CONCAT ?></td>
                            <td><?php echo $minuta['objetivos'] ?? 'N/A'; // Usar la columna del GROUP_CONCAT ?></td>
                            <td><?php echo htmlspecialchars($fechaCreacion); ?></td>

                            <td class="text-center">
                                <?php if ($totalAdjuntos > 0): ?>
                                    <button type="button" class="btn btn-info btn-sm" 
                                            title="Ver adjuntos"
                                            onclick="verAdjuntos(<?php echo $minutaId; ?>)">
                                        <i class="fas fa-paperclip"></i> (<?php echo $totalAdjuntos; ?>)
                                    </button>
                                <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td style="white-space: nowrap;">
                                <?php if ($estado === 'PENDIENTE'): ?>
                                    <a href="menu.php?pagina=editar_minuta&id=<?php echo $minutaId; ?>" class="btn btn-sm btn-info text-white me-2">Editar</a>
                                    <?php if ($idUsuarioLogueado && (int)$idUsuarioLogueado === (int)$presidenteAsignado): ?>
                                        <button type="button" class="btn btn-sm btn-primary" onclick="aprobarMinuta(<?php echo $minutaId; ?>)">🔒 Firmar y Aprobar</button>
                                    <?php endif; ?>
                                <?php elseif ($estado === 'APROBADA'): ?>
                                    <a href="<?php echo htmlspecialchars($minuta['pathArchivo'] ?? '#'); ?>" target="_blank" class="btn btn-sm btn-success <?php echo empty($minuta['pathArchivo']) ? 'disabled' : ''; ?>">Visualizar PDF</a>
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

<div class="modal fade" id="modalAdjuntos" tabindex="-1" aria-labelledby="modalAdjuntosLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalAdjuntosLabel">Documentos Adjuntos</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <ul id="listaDeAdjuntos" class="list-group list-group-flush">
          <li class="list-group-item text-muted">Cargando...</li>
        </ul>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>
<script>
    /**
     * Función para aprobar minuta (ya existía)
     */
    function aprobarMinuta(idMinuta) {
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
                    window.location.reload();
                } else {
                    alert(`⚠️ Error: ${response.message}`);
                }
            })
            .catch(err => alert("Error de red al aprobar:\n" + err.message));
    }

    // --- INICIO: NUEVO CÓDIGO JAVASCRIPT PARA MODAL ---
    
    // Crear una instancia del modal de Bootstrap una sola vez
    const modalAdjuntosElement = document.getElementById('modalAdjuntos');
    const modalAdjuntos = modalAdjuntosElement ? new bootstrap.Modal(modalAdjuntosElement) : null;
    const listaUl = document.getElementById('listaDeAdjuntos');

    /**
     * Función para mostrar el modal y cargar los adjuntos
     */
    async function verAdjuntos(idMinuta) {
        if (!idMinuta || !modalAdjuntos || !listaUl) return; // Verificar que todo exista

        // 1. Mostrar el modal y poner estado de "Cargando"
        listaUl.innerHTML = '<li class="list-group-item text-muted"><i class="fas fa-spinner fa-spin me-2"></i>Cargando...</li>';
        modalAdjuntos.show();

        try {
            // 2. Usar 'fetch_data.php' para obtener los adjuntos
            const response = await fetch(`/corevota/controllers/fetch_data.php?action=adjuntos_por_minuta&idMinuta=${idMinuta}`);
            
            if (!response.ok) {
                 // Intentar leer el texto del error si no es OK
                 const errorText = await response.text();
                 throw new Error(`Error de red (${response.status}): ${errorText || 'No se pudo cargar'}`);
            }
            
            const data = await response.json();

            // 3. Mostrar los adjuntos en la lista
            if (data.status === 'success' && data.data && data.data.length > 0) {
                listaUl.innerHTML = ''; // Limpiar "Cargando..."
                
                data.data.forEach(adj => {
                    const li = document.createElement('li');
                    li.className = 'list-group-item';

                    const link = document.createElement('a');
                    
                    // Definir el link (si es 'file' o 'link')
                    const isFile = adj.tipoAdjunto === 'file';
                    link.href = isFile ? `/corevota/public/${adj.pathAdjunto}` : adj.pathAdjunto;
                    link.target = '_blank';
                    link.title = adj.pathAdjunto; // Tooltip con la ruta completa

                    // Definir el ícono y el nombre
                    const iconClass = isFile ? 'fas fa-paperclip' : 'fas fa-link';
                    // Obtener solo el nombre del archivo si es 'file'
                    const fileName = isFile ? adj.pathAdjunto.split('/').pop() : 'Enlace Externo'; 
                    
                    link.innerHTML = `<i class="${iconClass} me-2"></i> ${fileName}`;

                    li.appendChild(link);
                    listaUl.appendChild(li);
                });
            } else {
                // Si la respuesta es exitosa pero no hay datos
                listaUl.innerHTML = '<li class="list-group-item text-info"><i class="fas fa-info-circle me-2"></i>No se encontraron adjuntos para esta minuta.</li>';
            }

        } catch (error) {
            console.error('Error en verAdjuntos:', error);
            // Mostrar error en el modal
            listaUl.innerHTML = `<li class="list-group-item text-danger"><i class="fas fa-exclamation-triangle me-2"></i>Error al cargar: ${error.message}</li>`;
        }
    }
    // --- FIN: NUEVO CÓDIGO JAVASCRIPT ---
</script>