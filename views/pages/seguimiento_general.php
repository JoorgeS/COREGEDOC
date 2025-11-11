<?php
// views/pages/seguimiento_general.php

require_once __DIR__ . '/../../models/minutaModel.php';

$minutasEnSeguimiento = [];
$comisiones = [];
$model = null;

try {
    $model = new MinutaModel();
    $comisiones = $model->getComisiones();
    
    // REQ 1: La página no carga nada hasta que se activa el filtro
    // 💡 AJUSTE: Mantenemos el chequeo de 'filtro_activo' solo para saber si se usó la barra de filtros,
    // pero forzamos la carga de datos si no hay parámetros específicos.
    $filtroActivo = isset($_GET['filtro_activo']); 
    
    $filtroComision = $_GET['comisionId'] ?? null;
    $filtroIdMinuta = $_GET['idMinuta'] ?? null;
    $filtroKeyword = $_GET['keyword'] ?? null;
    $filtroStartDate = $_GET['startDate'] ?? '';
    $filtroEndDate = $_GET['endDate'] ?? '';

    // 🚀 NUEVO: Ejecutar la consulta en la carga inicial y siempre
    $filters = [
        'comisionId' => $filtroComision,
        'startDate'  => $filtroStartDate,
        'endDate'    => $filtroEndDate,
        'idMinuta'   => $filtroIdMinuta,
        'keyword'    => $filtroKeyword,
        // 💡 Nuevo parámetro para el modelo: indica que queremos todos los estados
        'all_status' => true 
    ];
    
    // Asumimos un nuevo método más genérico en MinutaModel para que devuelva TODAS las minutas
    $minutasEnSeguimiento = $model->getSeguimientoGeneral($filters);
    
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Error al conectar con el modelo: " . $e->getMessage() . "</div>";
    $minutasEnSeguimiento = [];
    $comisiones = [];
}
?>

<div class="container-fluid">
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-tasks"></i> Seguimiento General de Minutas (Todas)
            </h6>
        </div>
        <div class="card-body">

            <form method="GET" class="mb-4 p-3 border rounded bg-light" id="filtrosFormSeguimiento">
                <input type="hidden" name="pagina" value="seguimiento_general">
                <input type="hidden" name="filtro_activo" value="1">
                <div class="row g-3 align-items-end">
                    <div class="col-md-2">
                        <label for="idMinuta" class="form-label">ID Minuta:</label>
                        <input type="number" class="form-control form-control-sm" id="idMinuta" name="idMinuta" value="<?php echo htmlspecialchars($filtroIdMinuta ?? ''); ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="keyword" class="form-label">Palabra Clave (Tema/Obj):</label>
                        <input type="text" class="form-control form-control-sm" id="keyword" name="keyword" value="<?php echo htmlspecialchars($filtroKeyword ?? ''); ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="comisionId" class="form-label">Comisión:</label>
                        <select class="form-select form-select-sm" id="comisionId" name="comisionId">
                            <option value="">-- Todas las Comisiones --</option>
                            <?php foreach (($comisiones ?? []) as $comision): ?>
                                <option value="<?php echo $comision['idComision']; ?>"
                                    <?php echo ($filtroComision == $comision['idComision']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($comision['nombreComision']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="startDate" class="form-label">Creada Desde:</label>
                        <input type="date" class="form-control form-control-sm" id="startDate" name="startDate"
                            value="<?php echo htmlspecialchars($filtroStartDate); ?>">
                    </div>
                    <div class="col-md-2">
                        <label for="endDate" class="form-label">Creada Hasta:</label>
                        <input type="date" class="form-control form-control-sm" id="endDate" name="endDate"
                            value="<?php echo htmlspecialchars($filtroEndDate); ?>">
                    </div>
                    <div class="col-md-2 d-grid">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="fas fa-filter"></i> Buscar
                        </button>
                    </div>
                    <div class="col-md-2 d-grid">
                        <label class="form-label">&nbsp;</label>
                        <a href="menu.php?pagina=seguimiento_general" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-undo"></i> Limpiar
                        </a>
                    </div>
                </div>
            </form>

            <div class="table-responsive">
                <table id="tablaSeguimiento" class="table table-bordered table-striped table-hover">
                    <thead class="thead-dark">
                        <tr>
                            <th class="text-center">Acciones</th>
                            <th>ID Minuta</th>
                            <th>Comisión</th>
                            <th>Temas Principales</th>
                            <th>Fecha Creación</th>
                            <th>Última Acción Registrada</th>
                            <th>Fecha Última Acción</th>
                            <th>Realizado Por</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($minutasEnSeguimiento)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    <strong><i class="fas fa-info-circle"></i> No hay minutas registradas o no se encontraron resultados con los filtros.</strong>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($minutasEnSeguimiento as $minuta): ?>
                                <tr>
                                    <td class="text-center">
                                        <button type="button" 
                                                    class="btn btn-primary btn-sm btn-ver-seguimiento" 
                                                    title="Ver Seguimiento"
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#modalSeguimiento" 
                                                    data-id="<?php echo $minuta['idMinuta']; ?>">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </td>
                                    <td class="text-center">
                                        <strong>#<?php echo htmlspecialchars($minuta['idMinuta']); ?></strong>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($minuta['nombreComision']); ?>
                                    </td>
                                    <td style="min-width: 200px;">
                                        <?php echo $minuta['nombreTemas']; ?>
                                    </td>
                                    <td>
                                        <?php echo $minuta['fechaMinuta'] ? date('d-m-Y', strtotime($minuta['fechaMinuta'])) : 'N/A'; ?>
                                    </td>
                                    <td style="min-width: 250px;">
                                        <?php echo htmlspecialchars($minuta['ultimo_detalle']); ?>
                                    </td>
                                    <td>
                                        <?php echo $minuta['ultima_fecha'] ? date('d-m-Y H:i', strtotime($minuta['ultima_fecha'])) : 'N/A'; ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($minuta['ultimo_usuario']); ?>
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

<div class="modal fade" id="modalSeguimiento" tabindex="-1" aria-labelledby="modalSeguimientoLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalSeguimientoLabel">Seguimiento Minuta N°...</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="modalSeguimientoContenido">
                </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    const modalElement = document.getElementById('modalSeguimiento');
    
    if (modalElement) {
        const modalTitle = document.getElementById('modalSeguimientoLabel');
        const modalBody = document.getElementById('modalSeguimientoContenido');

        // 1. Escuchamos el evento que Bootstrap dispara ANTES de mostrar el modal
        modalElement.addEventListener('show.bs.modal', function(event) {
            
            // 2. Obtenemos el botón que fue clickeado
            const button = event.relatedTarget; 
            
            if (button) {
                // 3. Sacamos el ID del atributo 'data-id' del botón
                const minutaId = button.getAttribute('data-id');

                if (minutaId) {
                    // 4. Actualizamos el título y ponemos un "Cargando..."
                    modalTitle.textContent = 'Seguimiento Minuta N° ' + minutaId;
                    modalBody.innerHTML = '<div class="text-center p-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Cargando...</span></div></div>';

                    // 5. Usamos fetch() (JavaScript puro) para llamar al controlador
                    fetch('/corevota/controllers/obtener_preview_seguimiento.php?id=' + minutaId)
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('Error en la red: ' + response.statusText);
                            }
                            return response.text(); // La respuesta es el HTML
                        })
                        .then(html => {
                            // 6. Cargamos el HTML en el cuerpo del modal
                            modalBody.innerHTML = html;
                        })
                        .catch(error => {
                            // 7. Manejamos cualquier error
                            console.error("Error en fetch:", error);
                            modalBody.innerHTML = '<p class="alert alert-danger"><strong>Error al cargar los datos.</strong></p><pre>' + error.message + '</pre>';
                        });
                }
            }
        });
    }
});
</script>