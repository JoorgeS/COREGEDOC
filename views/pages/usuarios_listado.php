<?php
// RUTA CRÍTICA: Desde views/pages/ subimos dos niveles (../../) a la raíz para encontrar Usuario.php
require_once(__DIR__ . '/../../Usuario.php');

$usuarioObj = new Usuario();

// Capturamos el texto de búsqueda que viene por GET (input name="nombre")
$nombreFiltro = $_GET['nombre'] ?? '';

// Si se ingresó algo en el buscador, usamos búsqueda filtrada
if (!empty($nombreFiltro)) {
    $usuarios = $usuarioObj->listarUsuariosPorNombre($nombreFiltro);
} else {
    // si no hay filtro, traemos todo (ordenados por nombre A→Z)
    $usuarios = $usuarioObj->listarUsuarios();
}

// Obtener mensajes de estado (?status=success&msg=Usuario+creado)
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
        .tabla-scroll {
            position: relative;
            max-height: 70vh;
            overflow-y: auto;
            overflow-x: auto;
        }
        thead th {
            position: sticky;
            top: 0;
            z-index: 3;
            background-color: #f8f9fa;
        }
        td.text-nowrap { white-space: nowrap; }
        .hint { font-size: .85rem; color: #6c757d; }
        .search-loading { font-size: .85rem; color: #0d6efd; display:none; }
    </style>
</head>
<body>
    <div class="card p-4 shadow-sm">

        <!-- Título y botón crear -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="mb-0">Listado de Usuarios</h2>
            <a href="usuario_formulario.php?action=create" class="btn btn-success btn-sm">
                Registrar Nuevo Usuario
            </a>
        </div>

        <!-- Filtro de búsqueda -->
        <form id="form-busqueda" method="GET" action="usuarios_listado.php" class="mb-3 p-3 border rounded bg-light" role="search">
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
                        autocomplete="off"
                        aria-describedby="hint-busqueda estado-busqueda"
                    >
                    <div id="hint-busqueda" class="hint mt-1">
                        Consejo: al escribir <strong>6+ caracteres</strong> se filtra automáticamente (no necesitas presionar “Buscar”).
                    </div>
                    <div id="estado-busqueda" class="search-loading mt-1" aria-live="polite">Buscando…</div>
                </div>

                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100" id="btn-buscar">Buscar</button>
                </div>

                <?php if (!empty($nombreFiltro)): ?>
                <div class="col-md-2">
                    <a href="usuarios_listado.php" class="btn btn-secondary btn-sm w-100" id="btn-limpiar">Limpiar</a>
                </div>
                <?php endif; ?>
            </div>
        </form>

        <!-- Mensajes de feedback (éxito / error) -->
        <?php if ($status && $msg): ?>
            <div class="alert alert-<?php echo ($status === 'success' ? 'success' : 'danger'); ?>" role="alert">
                <?php echo htmlspecialchars($msg); ?>
            </div>
        <?php endif; ?>

        <!-- Tabla de usuarios -->
        <div class="tabla-scroll">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <!-- SIN COLUMNA ID -->
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
                                   class="btn btn-sm btn-primary me-1"
                                   title="Editar">
                                   Editar
                                </a>
                                <form action="usuario_acciones.php" method="POST" class="d-inline"
                                      onsubmit="return confirm('¿Está seguro de que desea eliminar a este usuario?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="idUsuario" value="<?php echo (int)($user['idUsuario'] ?? 0); ?>">
                                    <button type="submit" class="btn btn-sm btn-danger" title="Eliminar">
                                        Eliminar
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center">No hay usuarios registrados.</td>
                        </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="/corevota/public/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

    <!-- Auto-búsqueda: 6+ caracteres, con debounce -->
    <script>
    (function () {
        const input = document.getElementById('nombre');
        const form  = document.getElementById('form-busqueda');
        const state = document.getElementById('estado-busqueda');

        // Debounce genérico
        function debounce(fn, delay) {
            let t = null;
            return function(...args) {
                clearTimeout(t);
                t = setTimeout(() => fn.apply(this, args), delay);
            };
        }

        // Lanzar submit si hay 6+ caracteres; si queda vacío, recargar sin filtro
        const autoSearch = debounce(function () {
            const val = (input.value || '').trim();

            // Mostrar / ocultar “Buscando…”
            if (val.length >= 6 || val.length === 0) {
                state.style.display = 'inline';
            } else {
                state.style.display = 'none';
            }

            if (val.length >= 6) {
                form.requestSubmit(); // respeta method/action del form
            } else if (val.length === 0) {
                // Si el usuario limpió el input, volvemos a la lista completa
                // Preservamos la URL base del formulario
                if (window.location.search) {
                    window.location.href = form.getAttribute('action') || 'usuarios_listado.php';
                }
            }
        }, 300);

        // Eventos de entrada (teclado, pegar, autocompletar)
        input.addEventListener('input', autoSearch);
        input.addEventListener('change', autoSearch);
        input.addEventListener('compositionend', autoSearch); // soporte IME

        // Oculta el estado tras el submit definitivo
        form.addEventListener('submit', function() {
            state.style.display = 'none';
        });
    })();
    </script>
</body>
</html>
