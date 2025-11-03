<?php
/**
 * --------------------------------------
 * GUARDIA DE ACCESO (ADMIN ONLY)
 * --------------------------------------
 * Las variables $tipoUsuario y ROL_ADMINISTRADOR son definidas 
 * automáticamente por el archivo menu.php que incluye este script.
 */
if (!isset($tipoUsuario) || $tipoUsuario != ROL_ADMINISTRADOR) {
    
    // Oculta el contenido y redirige al inicio
    echo "<div class='alert alert-danger m-3'>Acceso Denegado: No tiene permisos para ver esta página.</div>";
    echo '<script>setTimeout(function() { window.location.href = "menu.php?pagina=home"; }, 2000);</script>';
    
    // Detiene la ejecución del resto de la página de admin
    exit;
}
require_once(__DIR__ . '/../../Usuario.php');

$usuarioObj   = new Usuario();
$nombreFiltro = $_GET['nombre'] ?? '';
$esAjax       = isset($_GET['ajax']) && $_GET['ajax'] === '1';

// --- Paginación: page y pageSize ---
$page     = max(1, (int)($_GET['page'] ?? 1));
$pageSize = (int)($_GET['pageSize'] ?? 10);
$validPageSizes = [10, 20, 50];
if (!in_array($pageSize, $validPageSizes, true)) { $pageSize = 10; }

// Carga de datos completos (el modelo entrega todo; aquí paginamos en memoria)
if ($nombreFiltro !== '') {
    $usuarios = $usuarioObj->listarUsuariosPorNombre($nombreFiltro);
} else {
    $usuarios = $usuarioObj->listarUsuarios();
}

// Orden opcional A→Z por nombre completo (seguro si el modelo no los ordena)
usort($usuarios, function($a, $b) {
    $na = trim(($a['pNombre'] ?? '').' '.($a['sNombre'] ?? '').' '.($a['aPaterno'] ?? '').' '.($a['aMaterno'] ?? ''));
    $nb = trim(($b['pNombre'] ?? '').' '.($b['sNombre'] ?? '').' '.($b['aPaterno'] ?? '').' '.($b['aMaterno'] ?? ''));
    return strcasecmp($na, $nb);
});

// Totales y slice
$totalReg   = count($usuarios);
$totalPages = max(1, (int)ceil($totalReg / $pageSize));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $pageSize;
$usuariosPagina = array_slice($usuarios, $offset, $pageSize);

/** Construye query preservando filtros y pageSize (sin page). */
function buildBaseQuery(array $extra = []): string {
    $params = [
        'nombre'   => $_GET['nombre'] ?? '',
        'pageSize' => $_GET['pageSize'] ?? 10,
    ];
    $params = array_merge($params, $extra);
    // quitamos ajax para enlaces normales; JS lo añadirá si corresponde
    unset($params['ajax']);
    return http_build_query($params);
}

// Render: tabla
function renderTablaUsuarios(array $usuarios): void { ?>
    <table class="table table-hover table-sm mb-0">
        <thead class="table-light">
        <tr>
            <th>Nombre Completo</th>
            <th>Correo</th>
            <th>Perfil</th>
            <th>Tipo Usuario</th>
            <th class="text-nowrap">Acciones</th>
        </tr>
        </thead>
        <tbody>
        <?php if (!empty($usuarios)): ?>
            <?php foreach ($usuarios as $user): ?>
                <tr>
                    <td>
                        <?php
                        $nombreCompleto = trim(
                            ($user['pNombre']  ?? '') . ' ' .
                            ($user['sNombre']  ?? '') . ' ' .
                            ($user['aPaterno'] ?? '') . ' ' .
                            ($user['aMaterno'] ?? '')
                        );
                        echo htmlspecialchars(preg_replace('/\s+/', ' ', $nombreCompleto));
                        ?>
                    </td>
                    <td><?php echo htmlspecialchars($user['correo'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($user['perfil_desc'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($user['tipoUsuario_desc'] ?? ''); ?></td>
                    <td class="text-nowrap">
                        <a href="menu.php?pagina=usuario_editar&id=<?php echo (int)($user['idUsuario'] ?? 0); ?>"
                           class="btn btn-sm btn-primary me-1" title="Editar">Editar</a>
                        <form action="usuario_acciones.php" method="POST" class="d-inline"
                              onsubmit="return confirm('¿Está seguro de que desea eliminar a este usuario?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="idUsuario" value="<?php echo (int)($user['idUsuario'] ?? 0); ?>">
                            <button type="submit" class="btn btn-sm btn-danger" title="Eliminar">Eliminar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="5" class="text-center text-muted">No hay usuarios registrados.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
<?php }

// Render: selector de tamaño de página + paginación
function renderPaginacion(int $page, int $totalPages, int $totalReg, int $pageSize): void {
    $base = buildBaseQuery();
    ?>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 border-top py-2">
        <div class="small text-muted">
            Mostrando página <?php echo $page; ?> de <?php echo $totalPages; ?>
            (<?php echo $totalReg; ?> registros)
        </div>

        <div class="d-flex align-items-center gap-2">
            <label for="pageSize" class="form-label m-0 small">Filas:</label>
            <select id="pageSize" class="form-select form-select-sm" style="width:auto">
                <?php foreach ([10,20,50] as $size): ?>
                    <option value="<?php echo $size; ?>" <?php echo ($size===$pageSize?'selected':''); ?>>
                        <?php echo $size; ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <?php
                    $makeLink = function(int $p) use ($base) {
                        $q = $base . '&page=' . $p;
                        return 'usuarios_listado.php?' . $q;
                    };
                    $disabled = function(bool $cond) { return $cond ? ' disabled' : ''; };

                    // First / Prev
                    ?>
                    <li class="page-item<?php echo $disabled($page<=1); ?>">
                        <a class="page-link" href="<?php echo $makeLink(1); ?>" data-page="1" aria-label="Primera">&laquo;</a>
                    </li>
                    <li class="page-item<?php echo $disabled($page<=1); ?>">
                        <a class="page-link" href="<?php echo $makeLink(max(1,$page-1)); ?>" data-page="<?php echo max(1,$page-1); ?>" aria-label="Anterior">&lsaquo;</a>
                    </li>
                    <?php
                    // Rango de páginas (ventana)
                    $window = 2;
                    $start = max(1, $page - $window);
                    $end   = min($totalPages, $page + $window);
                    if ($start > 1) {
                        echo '<li class="page-item"><a class="page-link" href="'.$makeLink(1).'" data-page="1">1</a></li>';
                        if ($start > 2) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                    }
                    for ($p=$start; $p<=$end; $p++) {
                        $active = ($p===$page) ? ' active' : '';
                        echo '<li class="page-item'.$active.'"><a class="page-link" href="'.$makeLink($p).'" data-page="'.$p.'">'.$p.'</a></li>';
                    }
                    if ($end < $totalPages) {
                        if ($end < $totalPages-1) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                        echo '<li class="page-item"><a class="page-link" href="'.$makeLink($totalPages).'" data-page="'.$totalPages.'">'.$totalPages.'</a></li>';
                    }
                    ?>
                    <li class="page-item<?php echo $disabled($page>=$totalPages); ?>">
                        <a class="page-link" href="<?php echo $makeLink(min($totalPages,$page+1)); ?>" data-page="<?php echo min($totalPages,$page+1); ?>" aria-label="Siguiente">&rsaquo;</a>
                    </li>
                    <li class="page-item<?php echo $disabled($page>=$totalPages); ?>">
                        <a class="page-link" href="<?php echo $makeLink($totalPages); ?>" data-page="<?php echo $totalPages; ?>" aria-label="Última">&raquo;</a>
                    </li>
                </ul>
            </nav>
        </div>
    </div>
<?php }

// Render: bloque listado (tabla + paginación)
function renderListado(array $usuariosPagina, int $page, int $totalPages, int $totalReg, int $pageSize): void { ?>
    <div id="listado-contenedor">
        <div class="tabla-scroll">
            <?php renderTablaUsuarios($usuariosPagina); ?>
        </div>
        <?php renderPaginacion($page, $totalPages, $totalReg, $pageSize); ?>
    </div>
<?php }

// Si es AJAX, devolvemos SOLO el bloque de listado y terminamos
if ($esAjax) {
    renderListado($usuariosPagina, $page, $totalPages, $totalReg, $pageSize);
    exit;
}

$status = $_GET['status'] ?? '';
$msg    = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Listado de Usuarios</title>
    <link href="/corevota/public/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        .tabla-scroll { position: relative; max-height: 70vh; overflow-y: auto; overflow-x: auto; }
        thead th { position: sticky; top: 0; z-index: 3; background-color: #f8f9fa; }
        td.text-nowrap { white-space: nowrap; }
        .search-loading { font-size: .85rem; color: #0d6efd; display: none; }
    </style>
</head>
<body>
<div class="card p-4 shadow-sm">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0">Usuarios Registrados</h2>
        <a href="menu.php?pagina=usuario_crear" class="btn btn-success">
            <i class="fas fa-user-plus me-1"></i> Registrar Nuevo Usuario
        </a>
    </div>

    <!-- Buscador -->
    <div class="mb-3 p-3 border rounded bg-light" role="search">
        <div class="row g-3 align-items-end">
            <div class="col-md-6">
                <label for="nombre" class="form-label mb-1">Buscar por nombre / apellido / correo:</label>
                <input
                    type="text"
                    class="form-control form-control-sm"
                    id="nombre"
                    name="nombre"
                    placeholder="Ej: Juan, Pérez, jperez@gobiernovalparaiso.cl"
                    value="<?php echo htmlspecialchars($nombreFiltro); ?>"
                    autocomplete="off">
                <div id="estado-busqueda" class="search-loading mt-1" aria-live="polite">Buscando...</div>
            </div>
        </div>
    </div>

    <?php if ($status && $msg): ?>
        <div class="alert alert-<?php echo ($status === 'success' ? 'success' : 'danger'); ?>" role="alert">
            <?php echo htmlspecialchars($msg); ?>
        </div>
    <?php endif; ?>

    <!-- Contenedor dinámico (tabla + paginación) -->
    <div id="listado-ajax">
        <?php renderListado($usuariosPagina, $page, $totalPages, $totalReg, $pageSize); ?>
    </div>
</div>

<script src="/corevota/public/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    const input  = document.getElementById('nombre');
    const estado = document.getElementById('estado-busqueda');
    const cont   = document.getElementById('listado-ajax');

    // Lee valor actual de pageSize del DOM (si existe)
    function currentPageSize() {
        const sel = cont.querySelector('#pageSize');
        return sel ? parseInt(sel.value, 10) : 10;
    }

    function buildAjaxUrl(params) {
        const p = new URLSearchParams(params);
        p.set('ajax','1');
        return 'usuarios_listado.php?' + p.toString();
    }

    // Delegación para paginación (clicks)
    cont.addEventListener('click', function (e) {
        const a = e.target.closest('.pagination .page-link');
        if (!a) return;
        e.preventDefault();

        const page = a.getAttribute('data-page');
        const nombre = (input.value || '').trim();
        const pageSize = currentPageSize();

        cargarListado({ nombre, page, pageSize });
    });

    // Cambio de tamaño de página
    cont.addEventListener('change', function (e) {
        if (e.target && e.target.id === 'pageSize') {
            const pageSize = parseInt(e.target.value, 10) || 10;
            const nombre = (input.value || '').trim();
            cargarListado({ nombre, page: 1, pageSize });
        }
    });

    function debounce(fn, delay) {
        let t;
        return (...args) => { clearTimeout(t); t = setTimeout(() => fn.apply(this, args), delay); };
    }

    const actualizarBusqueda = debounce(() => {
        const val = (input.value || '').trim();
        if (val.length >= 5 || val.length === 0) {
            cargarListado({ nombre: val, page: 1, pageSize: currentPageSize() });
        }
    }, 300);

    input.addEventListener('input', actualizarBusqueda);
    input.addEventListener('change', actualizarBusqueda);

    function cargarListado({ nombre, page, pageSize }) {
        estado.style.display = 'inline';
        const url = buildAjaxUrl({ nombre, page, pageSize });
        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }})
            .then(r => r.text())
            .then(html => { cont.innerHTML = html; estado.style.display = 'none'; })
            .catch(() => { estado.style.display = 'none'; });
    }
})();
</script>
</body>
</html>
