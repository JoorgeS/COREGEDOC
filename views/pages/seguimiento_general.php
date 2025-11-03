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

            <form method="GET" class="mb-4 p-3 border rounded bg-light">
                <input type="hidden" name="pagina" value="seguimiento_general">

                <div class="row g-3 align-items-end">

                    <div class="col-md-4">
                        <label for="comisionId" class="form-label">Filtrar por Comisión:</label>
                        <select class="form-select form-select-sm" id="comisionId" name="comisionId">
                            <option value="">-- Todas las Comisiones --</option>
                            <?php foreach ($comisiones as $comision): ?>
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
                <table class="table table-bordered table-striped table-hover">
                    <thead class="thead-dark">
                        <tr>
                            <th>ID Minuta</th>
                            <th>Comisión</th>
                            <th>Temas Principales</th>
                            <th>Fecha Creación</th>
                            <th>Última Acción Registrada</th>
                            <th>Fecha Última Acción</th>
                            <th>Realizado Por</th>
                            <th>Seguimiento</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($minutasEnSeguimiento)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    No se encontraron minutas en proceso con los filtros seleccionados.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($minutasEnSeguimiento as $minuta): ?>
                                <tr>
                                    <td class="text-center">
                                        <strong>#<?php echo htmlspecialchars($minuta['idMinuta']); ?></strong>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($minuta['nombreComision']); ?>
                                    </td>
                                    <td style="min-width: 200px;">
                                        <?php echo $minuta['nombreTemas']; // Ya viene con <br> del GROUP_CONCAT 
                                        ?>
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
                                    <td class="text-center">
                                        <a href="menu.php?pagina=seguimiento_minuta&id=<?php echo $minuta['idMinuta']; ?>"
                                            class="btn btn-info btn-sm"
                                            title="Ver línea de tiempo completa">
                                            <i class="fas fa-clipboard-list"></i>
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