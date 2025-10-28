<?php
// views/pages/comisiones_listado.php
// Asegúrate de que $comisiones esté definida por el controlador.

// =====================
// BLOQUE NUEVO: filtros
// =====================
$filtroNombre = $_GET['nombre'] ?? '';
$filtroVigencia = $_GET['vigencia'] ?? '';

$comisionesFiltradas = $comisiones;

if (!empty($filtroNombre) || $filtroVigencia !== '') {
    $comisionesFiltradas = array_filter($comisionesFiltradas, function ($comision) use ($filtroNombre, $filtroVigencia) {

        // Coincidencia parcial de nombre (case-insensitive)
        $coincideNombre = empty($filtroNombre) ||
            stripos($comision['nombreComision'], $filtroNombre) !== false;

        // Coincidencia por vigencia exacta si viene seleccionada
        $coincideVigencia = ($filtroVigencia === '') ||
            ($comision['vigencia'] == $filtroVigencia);

        return $coincideNombre && $coincideVigencia;
    });
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Listado de Comisiones</title>
    <link href="/corevota/public/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .container-fluid {
            padding: 20px;
        }

        .table-responsive {
            max-height: 70vh;
            overflow-y: auto;
        }

        .table th,
        .table td {
            white-space: nowrap;
        }

        .table tbody tr td:nth-child(2) {
            width: 100%;
        }

        /* Fuerza el ancho del nombre */
    </style>
</head>

<body>

    <div class="container-fluid mt-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="mb-0">Listado de Comisiones</h3>
            <a href="menu.php?pagina=comision_crear" class="btn btn-success">Registrar Nueva Comisión</a>
        </div>

        <!-- ========================= -->
        <!-- FORMULARIO DE FILTRO      -->
        <!-- ========================= -->
        <form method="GET" class="row g-3 mb-4" action="menu.php">
            <!-- IMPORTANTE:
                 Esto define qué vista debe renderizar tu router -->
            <input type="hidden" name="pagina" value="comision_listado">

            <div class="col-md-5">
                <input
                    type="text"
                    name="nombre"
                    class="form-control"
                    placeholder="Buscar por nombre de comisión..."
                    value="<?php echo htmlspecialchars($filtroNombre); ?>">
            </div>

            <div class="col-md-3">
                <select name="vigencia" class="form-select">
                    <option value="">-- Todas --</option>
                    <option value="1" <?php echo $filtroVigencia === '1' ? 'selected' : ''; ?>>Activas</option>
                    <option value="0" <?php echo $filtroVigencia === '0' ? 'selected' : ''; ?>>Inactivas</option>
                </select>
            </div>

            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Filtrar</button>
            </div>

            <div class="col-md-2">
                <!-- "Limpiar" vuelve al router con la misma vista sin filtros -->
                <a href="menu.php?pagina=comision_listado" class="btn btn-secondary w-100">Limpiar</a>
            </div>
        </form>
        <!-- ========================= -->
        <!-- FIN FORMULARIO DE FILTRO  -->
        <!-- ========================= -->

        <div class="table-responsive shadow-sm">
            <table class="table table-striped table-bordered align-middle">
                <thead class="table-dark sticky-top">
                    <tr>
                        <th>Nombre de la Comisión</th>
                        <th>Vigencia</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($comisionesFiltradas) || !is_array($comisionesFiltradas)): ?>
                        <tr>
                            <td colspan="4" class="text-center py-4">No hay comisiones registradas.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($comisionesFiltradas as $comision): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($comision['nombreComision']); ?></td>
                                <td>
                                    <span class="badge <?php echo $comision['vigencia'] == 1 ? 'bg-success' : 'bg-danger'; ?>">
                                        <?php echo $comision['vigencia'] == 1 ? 'Activa' : 'Inactiva'; ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="menu.php?pagina=comision_editar&id=<?php echo $comision['idComision']; ?>" class="btn btn-sm btn-primary me-2">Editar</a>
                                    <a href="/corevota/controllers/ComisionController.php?action=delete&id=<?php echo $comision['idComision']; ?>"
                                        class="btn btn-sm btn-danger"
                                        onclick="return confirm('¿Está seguro de deshabilitar esta comisión?');">
                                        Deshabilitar
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>

</html>