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
// =====================================================================
// views/pages/usuario_form.php
// Mantiene el menú lateral en "Crear" y "Editar" usuarios
// =====================================================================
require_once(__DIR__ . '/../../Usuario.php');

// ---------------------------------------------------------------------------
// 🔍 DETECCIÓN DE MODO EMBEBIDO (cuando se carga dentro de menu.php)
// ---------------------------------------------------------------------------
$pagina = $_GET['pagina'] ?? '';
$EMBED = false;

if (in_array($pagina, ['usuario_crear', 'usuario_editar'])) {
    $EMBED = true;
    $_GET['embed'] = '1';
    $_GET['action'] = ($pagina === 'usuario_crear') ? 'create' : 'edit';
}

// Si entra directo (por error o enlace viejo), redirigimos al modo correcto
if (!$EMBED && !isset($_GET['embed'])) {
    $action = $_GET['action'] ?? 'create';
    $id     = (int)($_GET['id'] ?? 0);
    if ($action === 'edit' && $id > 0) {
        header('Location: menu.php?pagina=usuario_editar&id=' . $id);
        exit;
    } else {
        header('Location: menu.php?pagina=usuario_crear');
        exit;
    }
}
// ---------------------------------------------------------------------------

// ---------------------------------------------------------------------------
// 🔧 LÓGICA PRINCIPAL DEL FORMULARIO
// ---------------------------------------------------------------------------
$usuarioObj = new Usuario();
$action = $_GET['action'] ?? 'create';
$idUsuario = (int)($_GET['id'] ?? 0);
$userData = [];
$titulo = "Registrar Nuevo Usuario";
$contrasenaPlaceholder = "Contraseña (obligatoria)";

// Si es edición, obtener datos del usuario
if ($action === 'edit' && $idUsuario > 0) {
    $userData = $usuarioObj->obtenerUsuario($idUsuario);
    if (!$userData) {
        $dest = $EMBED ? 'menu.php?pagina=usuarios_listado' : 'usuarios_listado.php';
        header('Location: ' . $dest . '&status=error&msg=' . urlencode('Usuario a editar no encontrado.'));
        exit;
    }
    unset($userData['contrasena']);
    $pn = htmlspecialchars($userData['pNombre'] ?? '');
    $ap = htmlspecialchars($userData['aPaterno'] ?? '');
    $titulo = "Modificar Usuario: {$pn} {$ap}";
    $contrasenaPlaceholder = "Dejar en blanco para no cambiar";
}

// Listas para selects
$perfiles = $usuarioObj->obtenerPerfiles();
$tiposUsuario = $usuarioObj->obtenerTiposUsuario();
$partidos = $usuarioObj->obtenerPartidos();
$comunas = $usuarioObj->obtenerComunas();

// ---------------------------------------------------------------------------
// 🔹 CABECERA HTML (solo si no está embebido en menu.php)
// ---------------------------------------------------------------------------
if (!$EMBED): ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($titulo); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="/corevota/public/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body.bg-light { background-color: #f8f9fa !important; }
    </style>
</head>
<body class="bg-light">
<div class="container py-4">
<?php endif; ?>

<!-- Breadcrumb y título -->
<?php if ($EMBED): ?>
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb mb-1">
            <li class="breadcrumb-item"><a href="menu.php?pagina=usuarios_dashboard">Usuarios</a></li>
            <li class="breadcrumb-item"><a href="menu.php?pagina=usuarios_listado">Listado</a></li>
            <li class="breadcrumb-item active" aria-current="page">
                <?php echo ($action === 'edit') ? 'Editar Usuario' : 'Nuevo Usuario'; ?>
            </li>
        </ol>
    </nav>
    <h2 class="mb-3"><?php echo htmlspecialchars($titulo); ?></h2>
<?php else: ?>
    <h2 class="mb-4"><?php echo htmlspecialchars($titulo); ?></h2>
<?php endif; ?>

<div class="card shadow-sm p-4">
    <form action="usuario_acciones.php" method="POST" class="needs-validation" novalidate autocomplete="off">
        <input type="hidden" name="action" value="<?php echo htmlspecialchars($action); ?>">
        <?php if ($idUsuario): ?>
            <input type="hidden" name="idUsuario" value="<?php echo htmlspecialchars((string)$idUsuario); ?>">
        <?php endif; ?>

        <div class="row g-3">
            <div class="col-md-3">
                <label for="pNombre" class="form-label">Primer Nombre *</label>
                <input type="text" class="form-control" id="pNombre" name="pNombre"
                       value="<?php echo htmlspecialchars($userData['pNombre'] ?? ''); ?>" required>
            </div>
            <div class="col-md-3">
                <label for="sNombre" class="form-label">Segundo Nombre</label>
                <input type="text" class="form-control" id="sNombre" name="sNombre"
                       value="<?php echo htmlspecialchars($userData['sNombre'] ?? ''); ?>">
            </div>
            <div class="col-md-3">
                <label for="aPaterno" class="form-label">Apellido Paterno *</label>
                <input type="text" class="form-control" id="aPaterno" name="aPaterno"
                       value="<?php echo htmlspecialchars($userData['aPaterno'] ?? ''); ?>" required>
            </div>
            <div class="col-md-3">
                <label for="aMaterno" class="form-label">Apellido Materno</label>
                <input type="text" class="form-control" id="aMaterno" name="aMaterno"
                       value="<?php echo htmlspecialchars($userData['aMaterno'] ?? ''); ?>">
            </div>

            <hr class="my-3">

            <div class="col-md-6">
                <label for="correo" class="form-label">Correo Electrónico *</label>
                <input type="email" class="form-control" id="correo" name="correo"
                       value="<?php echo htmlspecialchars($userData['correo'] ?? ''); ?>" required>
            </div>

            <div class="col-md-6">
                <label for="contrasena" class="form-label">Contraseña</label>
                <input type="password" class="form-control" id="contrasena" name="contrasena"
                       placeholder="<?php echo htmlspecialchars($contrasenaPlaceholder); ?>"
                       <?php echo ($action === 'create' ? 'required' : ''); ?>>
                <?php if ($action === 'edit'): ?>
                    <div class="form-text">Dejar en blanco para no cambiar la contraseña.</div>
                <?php endif; ?>
            </div>

            <div class="col-md-3">
                <label for="perfil_id" class="form-label">Perfil/Rol *</label>
                <select class="form-select" id="perfil_id" name="perfil_id" required>
                    <option value="">Seleccione Perfil</option>
                    <?php 
                    $selectedPerfil = $userData['perfil_id'] ?? '';
                    foreach ($perfiles as $perfil): 
                        $sel = ((string)$selectedPerfil === (string)$perfil['idPerfil']) ? 'selected' : '';
                    ?>
                        <option value="<?php echo htmlspecialchars($perfil['idPerfil']); ?>" <?php echo $sel; ?>>
                            <?php echo htmlspecialchars($perfil['descPerfil']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <label for="tipoUsuario_id" class="form-label">Tipo de Usuario *</label>
                <select class="form-select" id="tipoUsuario_id" name="tipoUsuario_id" required>
                    <option value="">Seleccione Tipo</option>
                    <?php 
                    $selectedTipo = $userData['tipoUsuario_id'] ?? '';
                    foreach ($tiposUsuario as $tipo): 
                        $sel = ((string)$selectedTipo === (string)$tipo['idTipoUsuario']) ? 'selected' : '';
                    ?>
                        <option value="<?php echo htmlspecialchars($tipo['idTipoUsuario']); ?>" <?php echo $sel; ?>>
                            <?php echo htmlspecialchars($tipo['descTipoUsuario']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <label for="partido_id" class="form-label">Partido</label>
                <select class="form-select" id="partido_id" name="partido_id">
                    <option value="">Seleccione Partido</option>
                    <?php 
                    $selectedPartido = $userData['partido_id'] ?? '';
                    foreach ($partidos as $partido): 
                        $sel = ((string)$selectedPartido === (string)$partido['idPartido']) ? 'selected' : '';
                    ?>
                        <option value="<?php echo htmlspecialchars($partido['idPartido']); ?>" <?php echo $sel; ?>>
                            <?php echo htmlspecialchars($partido['nombrePartido']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <label for="comuna_id" class="form-label">Comuna</label>
                <select class="form-select" id="comuna_id" name="comuna_id">
                    <option value="">Seleccione Comuna</option>
                    <?php 
                    $selectedComuna = $userData['comuna_id'] ?? '';
                    foreach ($comunas as $comuna): 
                        $sel = ((string)$selectedComuna === (string)$comuna['idComuna']) ? 'selected' : '';
                    ?>
                        <option value="<?php echo htmlspecialchars($comuna['idComuna']); ?>" <?php echo $sel; ?>>
                            <?php echo htmlspecialchars($comuna['nombreComuna']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-12 mt-4">
                <button type="submit" class="btn btn-primary">
                    <?php echo ($action === 'create') ? 'Registrar Usuario' : 'Guardar Cambios'; ?>
                </button>
                <?php
                $urlCancelar = $EMBED ? 'menu.php?pagina=usuarios_listado' : 'usuarios_listado.php';
                ?>
                <a href="<?php echo $urlCancelar; ?>" class="btn btn-secondary ms-2">Cancelar</a>
            </div>
        </div>
    </form>
</div>

<?php if (!$EMBED): ?>
</div>
<script src="/corevota/public/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<?php endif; ?>

<script>
// ✅ Marca el menú lateral "Usuarios" como activo
document.addEventListener('DOMContentLoaded', () => {
    const item = document.querySelector('a[href*="usuarios_dashboard"], a[href*="usuarios_listado"]');
    if (item) item.classList.add('active', 'fw-bold', 'text-primary');
});
</script>
