<?php
// views/pages/comisiones_listado.php
// Espera que $comisiones venga desde el controlador con los campos:
// nombreComision, vigencia, presidenteNombre

$filtroNombre   = $_GET['nombre']   ?? '';
$filtroVigencia = $_GET['vigencia'] ?? '';

// --- Parámetros de paginación ---
$perPage = isset($_GET['per_page']) ? max(1, (int)$_GET['per_page']) : 10;
$page    = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;

$comisionesFiltradas = $comisiones;

// FILTROS (lógica original intacta)
if (!empty($filtroNombre) || $filtroVigencia !== '') {
    $comisionesFiltradas = array_filter($comisionesFiltradas, function ($comision) use ($filtroNombre, $filtroVigencia) {
        $nombreCoincide = true;
        if (!empty($filtroNombre)) {
            $needle = mb_strtolower($filtroNombre);
            $campoComision   = mb_strtolower($comision['nombreComision'] ?? '');
            $campoPresidente = mb_strtolower($comision['presidenteNombre'] ?? '');
            $nombreCoincide = (strpos($campoComision, $needle) !== false) ||
                (strpos($campoPresidente, $needle) !== false);
        }
        $vigenciaCoincide = ($filtroVigencia === '') ||
            ((string)$comision['vigencia'] === (string)$filtroVigencia);

        return $nombreCoincide && $vigenciaCoincide;
    });
}

// --- Paginación ---
$total  = count($comisionesFiltradas);
$pages  = max(1, (int)ceil(($total ?: 1) / $perPage));
$offset = ($page - 1) * $perPage;
$comisionesPage = array_slice($comisionesFiltradas, $offset, $perPage);

// Helper de paginación
function renderPaginationComisiones($current, $pages)
{
    if ($pages <= 1) return;
    echo '<nav aria-label="Paginación"><ul class="pagination pagination-sm">';
    for ($i = 1; $i <= $pages; $i++) {
        $active = ($i === $current) ? ' active' : '';
        $qsArr  = $_GET;
        if (isset($_GET['tab'])) $qsArr['tab'] = $_GET['tab'];
        $qsArr['p'] = $i;
        $qs = http_build_query($qsArr);
        echo '<li class="page-item' . $active . '"><a class="page-link" href="?' . $qs . '">' . $i . '</a></li>';
    }
    echo '</ul></nav>';
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Listado de Comisiones</title>
    <link href="/coregedoc/public/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
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

        .table tbody tr td:nth-child(1) {
            width: 100%;
        }

        .sticky-top thead th {
            position: sticky;
            top: 0;
            z-index: 1;
        }
    </style>
</head>

<body>
    <div class="container-fluid mt-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="mb-0">Listado de Comisiones</h3>
        </div>

        <form method="GET" class="row g-3 mb-4" action="menu.php" id="formComisiones">
            <input type="hidden" name="pagina" value="comision_listado">
            <input type="hidden" name="p" id="pHidden" value="<?php echo (int)$page; ?>">

            <div class="col-md-6">
                <input
                    type="text"
                    name="nombre"
                    id="inputNombre"
                    class="form-control"
                    placeholder="Buscar por comisión o presidente..."
                    value="<?php echo htmlspecialchars($filtroNombre); ?>">
            </div>

            <div class="col-md-4">
                <select name="vigencia" id="selectVigencia" class="form-select">
                    <option value="">-- Todas --</option>
                    <option value="1" <?php echo $filtroVigencia === '1' ? 'selected' : ''; ?>>Activas</option>
                    <option value="0" <?php echo $filtroVigencia === '0' ? 'selected' : ''; ?>>Inactivas</option>
                </select>
            </div>

            <div class="col-md-2">
                <select name="per_page" id="perPage" class="form-select">
                    <?php foreach ([10, 25, 50] as $opt): ?>
                        <option value="<?php echo $opt; ?>" <?php echo ($perPage === $opt) ? 'selected' : ''; ?>>
                            <?php echo $opt; ?>/pág
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>

        <div class="table-responsive shadow-sm">
            <table class="table table-striped table-bordered align-middle">
                <thead class="table-dark sticky-top">
                    <tr>
                        <th>Nombre Comisión</th>
                        <th>Presidente</th>
                        <th>Vicepresidente</th>
                        <th>Vigencia</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($comisionesPage)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-4">No hay comisiones registradas.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($comisionesPage as $c): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($c['nombreComision']); ?></td>
                                <td><?php echo htmlspecialchars($c['presidenteNombre'] ?? 'No asignado'); ?></td>
                                <td><?php echo htmlspecialchars($c['vicepresidenteNombre'] ?? 'No asignado'); ?></td>
                                <td>
                                    <span class="badge <?php echo ($c['vigencia'] == 1 ? 'bg-success' : 'bg-danger'); ?>">
                                        <?php echo ($c['vigencia'] == 1 ? 'Activa' : 'Inactiva'); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="menu.php?pagina=comision_editar&id=<?php echo $c['idComision']; ?>" class="btn btn-sm btn-primary">
                                        Editar
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="mt-2">
            <?php renderPaginationComisiones($page, $pages); ?>
        </div>
    </div>

    <script>
        (function() {
            const form = document.getElementById('formComisiones');
            const nombre = document.getElementById('inputNombre');
            const vigSel = document.getElementById('selectVigencia');
            const perSel = document.getElementById('perPage');
            const pHid = document.getElementById('pHidden');

            function toFirstPage() {
                if (pHid) pHid.value = '1';
            }

            // Auto-submit al cambiar vigencia o resultados por página
            [vigSel, perSel].forEach(el => {
                if (!el) return;
                el.addEventListener('change', () => {
                    toFirstPage();
                    form.submit();
                });
            });

            // Búsqueda automática: ≥5 caracteres o vacío
            if (nombre) {
                let t = null;
                nombre.addEventListener('input', () => {
                    clearTimeout(t);
                    t = setTimeout(() => {
                        const val = (nombre.value || '').trim();
                        if (val.length >= 5 || val.length === 0) {
                            toFirstPage();
                            form.submit();
                        }
                    }, 400);
                });
            }
        })();
    </script>
</body>

</html>