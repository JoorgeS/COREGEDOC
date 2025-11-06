<?php
// 1. Incluir el controlador/modelo de Usuario
require_once(__DIR__ . '/../../Usuario.php');

$usuarioObj = new Usuario();
$usuarioActual = null;
$mensaje = '';
$tipoMensaje = '';

// 2. Obtener el ID del usuario de la sesión actual
$idUsuario = $_SESSION['idUsuario'] ?? 0;

// --- INICIO NUEVA LÓGICA (PROCESAR FOTO) ---
// 3. Lógica para manejar la subida de la foto de perfil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['foto_perfil'])) {
    $resultadoFoto = $usuarioObj->actualizarFotoPerfil($idUsuario, $_FILES['foto_perfil']);
    
    $mensaje = $resultadoFoto['message'];
    $tipoMensaje = $resultadoFoto['success'] ? 'success' : 'danger';
}
// --- FIN NUEVA LÓGICA ---

// 4. Lógica para manejar la actualización de la contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cambiar_contrasena'])) {
    
    $passwordActual = $_POST['password_actual'] ?? '';
    $nuevaPassword = $_POST['contrasena'] ?? '';
    $confirmarPassword = $_POST['confirmar_contrasena'] ?? '';

    // Validaciones (esto es lo que ya tenías)
    if (empty($passwordActual) || empty($nuevaPassword) || empty($confirmarPassword)) {
        $mensaje = "Debe completar todos los campos de contraseña (actual, nueva y confirmación).";
        $tipoMensaje = "warning";
    } 
    elseif (!$usuarioObj->validarContrasenaActual($idUsuario, $passwordActual)) {
        $mensaje = "La <strong>contraseña actual</strong> es incorrecta. No se pueden guardar los cambios.";
        $tipoMensaje = "danger";
    }
    elseif ($nuevaPassword !== $confirmarPassword) {
        $mensaje = "Las contraseñas nuevas no coinciden.";
        $tipoMensaje = "danger";
    } 
    else {
        $errors = [];
        if (strlen($nuevaPassword) < 8) $errors[] = 'Debe tener al menos 8 caracteres.';
        if (!preg_match('/[A-Z]/', $nuevaPassword)) $errors[] = 'Debe incluir al menos una letra mayúscula.';
        if (!preg_match('/[a-z]/', $nuevaPassword)) $errors[] = 'Debe incluir al menos una letra minúscula.';
        if (!preg_match('/[\W_]/', $nuevaPassword)) $errors[] = 'Debe incluir al menos un símbolo.';

        if (!empty($errors)) {
            $mensaje = 'La <strong>nueva contraseña</strong> no cumple con los requisitos:<br>- ' . implode('<br>- ', $errors);
            $tipoMensaje = "danger";
        } 
        else {
            try {
                $actualizado = $usuarioObj->actualizarContrasena($idUsuario, $nuevaPassword); 
                
                if ($actualizado) {
                    $mensaje = "¡Contraseña actualizada con éxito!";
                    $tipoMensaje = "success";
                } else {
                    $mensaje = "No se pudo actualizar la contraseña (quizás es la misma de antes).";
                    $tipoMensaje = "warning";
                }
            } catch (Exception $e) {
                $mensaje = "Error al actualizar: " . $e->getMessage();
                $tipoMensaje = "danger";
            }
        }
    }
}

// 5. Cargar (o recargar) los datos del usuario
if ($idUsuario > 0) {
    try {
        $usuarioActual = $usuarioObj->obtenerUsuario($idUsuario);
        $perfiles = $usuarioObj->obtenerPerfiles();
        $tiposUsuario = $usuarioObj->obtenerTiposUsuario();
        $partidos = $usuarioObj->obtenerPartidos();
        $comunas = $usuarioObj->obtenerComunas();
    } catch (Exception $e) {
        if (empty($mensaje)) { // Solo mostrar si no hay otro mensaje
            $mensaje = "Error al cargar los datos del perfil: " . $e->getMessage();
            $tipoMensaje = "danger";
        }
    }
} else {
    if (empty($mensaje)) {
        $mensaje = "No se pudo identificar al usuario. Por favor, inicie sesión de nuevo.";
        $tipoMensaje = "danger";
    }
}
?>

<style>
    .profile-pic-container {
        width: 150px;
        height: 150px;
        border-radius: 50%;
        overflow: hidden;
        margin: 0 auto 15px auto;
        border: 4px solid #eee;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        display: flex;
        justify-content: center;
        align-items: center;
        background-color: #f8f9fa;
    }
    .profile-pic-container img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .profile-pic-container .icon-placeholder {
        font-size: 8rem; /* 128px */
        color: #ccc;
    }
</style>

<div class="container-fluid">
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-user-circle"></i> Mi Perfil
            </h6>
        </div>
        <div class="card-body">

            <?php if (!empty($mensaje)): ?>
                <div class="alert alert-<?php echo $tipoMensaje; ?> alert-dismissible fade show" role="alert">
                    <?php echo $mensaje; // Permitir HTML como <br> ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if ($usuarioActual): // Solo mostrar si el usuario se cargó bien ?>
                
                <div class="row">
                    <div class="col-lg-4 text-center">
                        <div class="profile-pic-container">
                            <?php if (!empty($usuarioActual['foto_perfil'])): ?>
                                <img src="<?php echo htmlspecialchars($usuarioActual['foto_perfil']); ?>?v=<?php echo time(); ?>" alt="Foto de Perfil">
                            <?php else: ?>
                                <i class="fas fa-user-circle icon-placeholder"></i>
                            <?php endif; ?>
                        </div>
                        
                        <h5 class="mb-3"><?php echo htmlspecialchars($usuarioActual['pNombre'] . ' ' . $usuarioActual['aPaterno']); ?></h5>
                        
                        <form method="POST" action="" enctype="multipart/form-data">
                            <label for="foto_perfil" class="form-label">Cambiar foto de perfil</label>
                            <input class="form-control form-control-sm" type="file" id="foto_perfil" name="foto_perfil" accept="image/png, image/jpeg, image/jpg">
                            <small class="form-text text-muted d-block mb-2">Máximo 2MB. Formatos: JPG, PNG.</small>
                            <button type="submit" class="btn btn-secondary btn-sm">
                                <i class="fas fa-upload"></i> Subir Foto
                            </button>
                        </form>
                    </div>

                    <div class="col-lg-8 border-start-lg">
                        
                        <h5 class="mb-3">Mis Datos</h5>
                        <fieldset disabled> 
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="pNombre" class="form-label">Primer Nombre</label>
                                    <input type="text" class="form-control" id="pNombre" value="<?php echo htmlspecialchars($usuarioActual['pNombre'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="aPaterno" class="form-label">Apellido Paterno</label>
                                    <input type="text" class="form-control" id="aPaterno" value="<?php echo htmlspecialchars($usuarioActual['aPaterno'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="correo" class="form-label">Correo Electrónico</label>
                                    <input type="email" class="form-control" id="correo" value="<?php echo htmlspecialchars($usuarioActual['correo'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="perfil_id" class="form-label">Perfil/Rol</label>
                                    <select class="form-select" id="perfil_id">
                                        <?php 
                                        $selectedPerfil = $usuarioActual['perfil_id'] ?? '';
                                        foreach ($perfiles as $perfil): 
                                            $sel = ((string)$selectedPerfil === (string)$perfil['idPerfil']) ? 'selected' : '';
                                        ?>
                                            <option <?php echo $sel; ?>><?php echo htmlspecialchars($perfil['descPerfil']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                </div>
                        </fieldset> 

                        <hr class="my-4">

                        <h5 class="mb-3">Cambiar Contraseña</h5>
                        <form method="POST" action="">
                            <input type="hidden" name="cambiar_contrasena" value="1">
                            
                            <div class="row g-3">
                                <div class="col-md-12">
                                    <label for="password_actual" class="form-label">Contraseña Actual *</label>
                                    <div class="position-relative">
                                        <input type="password" class="form-control" id="password_actual" name="password_actual" required>
                                        <i class="fas fa-eye" id="togglePassActual" onclick="togglePassword('password_actual', 'togglePassActual')" 
                                           style="position: absolute; top: 11px; right: 12px; cursor: pointer; color: #666;"></i>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label for="contrasena" class="form-label">Nueva Contraseña *</label>
                                    <div class="position-relative">
                                        <input type="password" class="form-control" id="contrasena" name="contrasena" required
                                               pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*[\W_]).{8,}"
                                               title="Debe tener al menos 8 caracteres, una mayúscula, una minúscula y un símbolo."
                                               oninput="validarPasswordCliente()">
                                        <i class="fas fa-eye" id="togglePass1" onclick="togglePassword('contrasena', 'togglePass1')"
                                           style="position: absolute; top: 11px; right: 12px; cursor: pointer; color: #666;"></i>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label for="confirmar_contrasena" class="form-label">Confirmar Contraseña *</label>
                                    <div class="position-relative">
                                        <input type="password" class="form-control" id="confirmar_contrasena" name="confirmar_contrasena" required
                                               oninput="validarPasswordCliente()">
                                        <i class="fas fa-eye" id="togglePass2" onclick="togglePassword('confirmar_contrasena', 'togglePass2')"
                                           style="position: absolute; top: 11px; right: 12px; cursor: pointer; color: #666;"></i>
                                    </div>
                                </div>
                            </div>

                            <div class="password-rules mt-3 p-3" id="password-rules" style="background: #f8f9fa; border: 1px solid #ddd; border-radius: 4px; font-size: .9rem;">
                                <strong>La nueva contraseña debe cumplir:</strong>
                                <ul class="list-unstyled m-0 mt-2" style="padding-left: 18px; margin: 0;">
                                    <li id="rule-length" class="rule-fail" style="margin-bottom: 5px;">• Mínimo 8 caracteres</li>
                                    <li id="rule-upper"  class="rule-fail" style="margin-bottom: 5px;">• Al menos 1 mayúscula (A-Z)</li>
                                    <li id="rule-lower"  class="rule-fail" style="margin-bottom: 5px;">• Al menos 1 minúscula (a-z)</li>
                                    <li id="rule-symbol" class="rule-fail" style="margin-bottom: 5px;">• Al menos 1 símbolo (!@#...)</li>
                                    <li id="rule-match"  class="rule-fail" style="margin-bottom: 5px;">• Ambas contraseñas nuevas coinciden</li>
                                </ul>
                            </div>
                            
                            <div class="mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Guardar Contraseña
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

            <?php endif; ?> </div>
    </div>
</div>

<style>
    .rule-ok { color: #155724; font-weight: bold; }
    .rule-fail { color: #721c24; font-weight: normal; }
</style>

<script>
function validarPasswordCliente() {
    // Validar solo si los elementos existen (para evitar errores en otras páginas)
    if (document.getElementById('contrasena')) {
        const p1 = document.getElementById('contrasena').value;
        const p2 = document.getElementById('confirmar_contrasena').value;

        const cumpleLargo   = p1.length >= 8;
        const cumpleMayus   = /[A-Z]/.test(p1);
        const cumpleMinus   = /[a-z]/.test(p1);
        const cumpleSimbolo = /[\W_]/.test(p1);
        const coincide      = (p1 !== '' && p1 === p2);

        setRuleState('rule-length',  cumpleLargo);
        setRuleState('rule-upper',   cumpleMayus);
        setRuleState('rule-lower',   cumpleMinus);
        setRuleState('rule-symbol',  cumpleSimbolo);
        setRuleState('rule-match',   coincide);
    }
}

function setRuleState(id, ok) {
    const li = document.getElementById(id);
    if (!li) return;
    if (ok) {
        li.classList.remove('rule-fail');
        li.classList.add('rule-ok');
    } else {
        li.classList.remove('rule-ok');
        li.classList.add('rule-fail');
    }
}

function togglePassword(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    if (input.type === "password") {
        input.type = "text";
        icon.classList.remove("fa-eye");
        icon.classList.add("fa-eye-slash");
    } else {
        input.type = "password";
        icon.classList.remove("fa-eye-slash");
        icon.classList.add("fa-eye");
    }
}

document.addEventListener('DOMContentLoaded', function() {
    validarPasswordCliente();
});
</script>