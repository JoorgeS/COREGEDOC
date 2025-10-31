<?php
// views/pages/usuarios_listado.php
// RUTA CRÍTICA: Desde views/pages/ subimos dos niveles (../../) a la raíz para encontrar Usuario.php
require_once(__DIR__ . '/../../Usuario.php');

$usuarioObj   = new Usuario();
$nombreFiltro = $_GET['nombre'] ?? '';
$esAjax       = isset($_GET['ajax']) && $_GET['ajax'] === '1';

// Carga de datos (misma lógica de siempre)
if ($nombreFiltro !== '') {
    $usuarios = $usuarioObj->listarUsuariosPorNombre($nombreFiltro);
} else {
    $usuarios = $usuarioObj->listarUsuarios();
}

// Función para renderizar SOLO la tabla (la usa la página y el modo AJAX)
function renderTablaUsuarios(array $usuarios): void {
    ?>
    <table class="table table-hover table-sm mb-0">
        <thead class="table-light">
        <tr>
            <th>Nombre Completo</th>
            <th>Correo</th>
            <th>Perfil</th>
            <th>Tipo Usuario</th>
            <th>Acciones</th>
        </tr>
        </thead>
        <tbody>
        <?php if (!empty($usuarios)): ?>
            <?php foreach ($usuarios as $user): ?>
                <tr>
                    <td>
                        <?php
                        $nombreCompleto = trim(
                            ($user['pNombre'] ?? '') . ' ' .
                            ($user['sNombre'] ?? '') . ' ' .
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
                        <a href="usuario_formulario.php?action=edit&id=<?php echo (int)($user['idUsuario'] ?? 0); ?>"
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
    <?php
}

// Si es AJAX, devolvemos SOLO la tabla y terminamos
if ($esAjax) {
    renderTablaUsuarios($usuarios);
    exit;
}

// --- Render de la página completa (para menu.php u otras plantillas) ---
$status = $_GET['status'] ?? '';
$msg    = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Listado de Usuarios</title>
    <link href="/corevota/public/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
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
        <h2 class="mb-0">Listado de Usuarios</h2>
        <a href="usuario_formulario.php?action=create" class="btn btn-success btn-sm">
            Registrar Nuevo Usuario
        </a>
    </div>

    <!-- Buscador (sin botón Buscar) -->
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
            <div class="col-md-2">
                <button id="btn-limpiar" class="btn btn-secondary btn-sm w-100">Limpiar</button>
            </div>
        </div>
    </div>

    <?php if ($status && $msg): ?>
        <div class="alert alert-<?php echo ($status === 'success' ? 'success' : 'danger'); ?>" role="alert">
            <?php echo htmlspecialchars($msg); ?>
        </div>
    <?php endif; ?>

    <div class="tabla-scroll" id="tabla-usuarios">
        <?php renderTablaUsuarios($usuarios); ?>
    </div>
</div>

<script src="/corevota/public/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    const input = document.getElementById('nombre');
    const limpiarBtn = document.getElementById('btn-limpiar');
    const tabla = document.getElementById('tabla-usuarios');
    const estado = document.getElementById('estado-busqueda');

    function debounce(fn, delay) {
        let t;
        return (...args) => { clearTimeout(t); t = setTimeout(() => fn.apply(this, args), delay); };
    }

    const actualizarTabla = debounce(() => {
        const val = (input.value || '').trim();
        // Ejecuta con 5+ caracteres o al limpiar
        if (val.length >= 5 || val.length === 0) {
            estado.style.display = 'inline';
            const url = 'usuarios_listado.php?ajax=1&nombre=' + encodeURIComponent(val);
            fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }})
                .then(r => r.text())
                .then(html => { tabla.innerHTML = html; estado.style.display = 'none'; })
                .catch(() => { estado.style.display = 'none'; });
        }
    }, 300);

    input.addEventListener('input', actualizarTabla);
    input.addEventListener('change', actualizarTabla);

    limpiarBtn.addEventListener('click', function (e) {
        e.preventDefault();
        input.value = '';
        actualizarTabla();
    });
})();
</script>
</body>
</html>
