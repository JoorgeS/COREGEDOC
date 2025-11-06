<?php
// views/pages/seguimiento_general.php

// 1. Incluir el modelo
require_once __DIR__ . '/../../models/minutaModel.php';

$minutasEnSeguimiento = [];
$comisiones = [];
$model = null;

try {
    // 2. Crear una instancia del modelo
    $model = new MinutaModel();

    // 3. Obtener la lista de comisiones para el filtro
    $comisiones = $model->getComisiones();

    // 4. Definir y obtener los valores de los filtros (desde la URL o por defecto)
    $filtroComision = $_GET['comisionId'] ?? null;

    // Por defecto: desde el día 1 del mes actual
    $filtroStartDate = $_GET['startDate'] ?? date('Y-m-01');
    // Por defecto: hasta hoy
    $filtroEndDate = $_GET['endDate'] ?? date('Y-m-d');

    // 5. Preparar el array de filtros para el modelo
    $filters = [
        'comisionId' => $filtroComision,
        'startDate'  => $filtroStartDate,
        'endDate'    => $filtroEndDate,
    ];

    // 6. Llamar a la función actualizada del modelo con los filtros
    $minutasEnSeguimiento = $model->getUltimoSeguimientoParaPendientes($filters);
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
                <i class="fas fa-tasks"></i> Seguimiento General de Minutas en Proceso
            </h6>
        </div>
        <div class="card-body">

            <div id="contenedor-timeline" class="mb-4 border rounded p-3" style="display: none; background-color: #f8f9fa;">
                </div>
            
            <form method="GET" class="mb-4 p-3 border rounded bg-light">
                <input type="hidden" name="pagina" value="seguimiento_general">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label for="comisionId" class="form-label">Filtrar por Comisión:</label>
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
                    <div class="col-md-3">
                        <label for="startDate" class="form-label">Creada Desde:</label>
                        <input type="date" class="form-control form-control-sm" id="startDate" name="startDate"
                            value="<?php echo htmlspecialchars($filtroStartDate); ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="endDate" class="form-label">Creada Hasta:</label>
                        <input type="date" class="form-control form-control-sm" id="endDate" name="endDate"
                            value="<?php echo htmlspecialchars($filtroEndDate); ?>">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary btn-sm w-100">
                            <i class="fas fa-filter"></i> Filtrar
                        </button>
                    </div>
                </div>
            </form>
            
            <div class="table-responsive">
                <table id="tablaSeguimiento" class="table table-bordered table-striped table-hover">
                    <thead class="thead-dark">
                        <tr>
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
                                <td colspan="7" class="text-center text-muted py-4">
                                    No se encontraron minutas en proceso con los filtros seleccionados.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($minutasEnSeguimiento as $minuta): ?>
                                <tr class="fila-clickeable" data-id="<?php echo $minuta['idMinuta']; ?>">
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

<script>
$(document).ready(function() {
    
    // 1. Definir el contenedor donde se mostrará la línea de tiempo
    var contenedor = $('#contenedor-timeline');

    // 2. Añadir el estilo de cursor "pointer" (delegado)
    $('#tablaSeguimiento').on('mouseover', '.fila-clickeable', function() {
        $(this).css('cursor', 'pointer');
    });

    // 3. Manejar el evento de clic en la tabla (delegado)
    $('#tablaSeguimiento').on('click', '.fila-clickeable', function() {
        
        var minutaId = $(this).data('id');
        console.log("Clic detectado en fila. ID Minuta: " + minutaId);

        if (minutaId) {
            // 4. Mostrar "Cargando..." en el DIV y hacerlo visible con animación
            contenedor.html('<div class="text-center p-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Cargando...</span></div></div>');
            contenedor.slideDown(); // Mostrar con animación

            // 5. Llamada AJAX al controlador
            $.ajax({
                url: 'controllers/obtener_preview_seguimiento.php', 
                type: 'GET',
                data: { id: minutaId },
                success: function(response) {
                    // 6. Cargar la respuesta en el DIV
                    // Añadimos un título y un botón de cerrar
                    var contenidoHtml = '<h5><i class="fas fa-history"></i> Seguimiento de Minuta N° ' + minutaId + '</h5>';
                    // Botón de cerrar
                    contenidoHtml += '<button id="cerrar-timeline" class="btn btn-sm btn-outline-secondary" style="position: absolute; top: 10px; right: 15px;">';
                    contenidoHtml += '<i class="fas fa-times"></i></button>';
                    
                    contenidoHtml += '<hr>';
                    contenidoHtml += response; // El HTML del controlador
                    
                    contenedor.html(contenidoHtml);
                },
                error: function(xhr, status, error) {
                    var errorMsg = '<p class="alert alert-danger"><strong>Error al cargar los datos.</strong></p>';
                    console.error("Error AJAX: " + status + " - " + error);
                    // Mostramos el error de PHP en el modal para saber qué pasó
                    contenedor.html(errorMsg + '<pre>' + xhr.responseText + '</pre>');
                }
            });
        }
    });

    // 7. Añadir evento para el nuevo botón de cerrar (delegado)
    contenedor.on('click', '#cerrar-timeline', function() {
        contenedor.slideUp(); // Ocultar el DIV con animación
    });
});
</script>