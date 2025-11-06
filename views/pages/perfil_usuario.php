<?php
// 1. Incluir el controlador/modelo de Usuario
require_once(__DIR__ . '/../../Usuario.php');

$usuarioObj = new Usuario();
$usuarioActual = null;
$mensaje = '';
$tipoMensaje = '';

// 2. Obtener el ID del usuario de la sesión actual
$idUsuario = $_SESSION['idUsuario'] ?? 0;

if ($idUsuario > 0) {
  try {
    // 3. Buscar toda la información del usuario actual
    $usuarioActual = $usuarioObj->obtenerUsuario($idUsuario);

    // 4. Cargar las listas para mostrar los nombres (de los selects)
    $perfiles = $usuarioObj->obtenerPerfiles();
    $tiposUsuario = $usuarioObj->obtenerTiposUsuario();
    $partidos = $usuarioObj->obtenerPartidos();
    $comunas = $usuarioObj->obtenerComunas();
  } catch (Exception $e) {
    $mensaje = "Error al cargar los datos del perfil: " . $e->getMessage();
    $tipoMensaje = "danger";
  }
} else {
  $mensaje = "No se pudo identificar al usuario. Por favor, inicie sesión de nuevo.";
  $tipoMensaje = "danger";
}

// 5. Lógica para manejar la actualización de la contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cambiar_contrasena'])) {

  // Obtenemos los TRES campos
  $passwordActual = $_POST['password_actual'] ?? '';
  $nuevaPassword = $_POST['contrasena'] ?? '';
  $confirmarPassword = $_POST['confirmar_contrasena'] ?? '';

  // --- Validación 1: Campos vacíos ---
  if (empty($passwordActual) || empty($nuevaPassword) || empty($confirmarPassword)) {
    $mensaje = "Debe completar todos los campos de contraseña (actual, nueva y confirmación).";
    $tipoMensaje = "warning";
  }
  // --- Validación 2: Contraseña actual (Usando la nueva función) ---
  elseif (!$usuarioObj->validarContrasenaActual($idUsuario, $passwordActual)) {
    $mensaje = "La <strong>contraseña actual</strong> es incorrecta. No se pueden guardar los cambios.";
    $tipoMensaje = "danger";
  }
  // --- Validación 3: Coincidencia de la nueva ---
  elseif ($nuevaPassword !== $confirmarPassword) {
    $mensaje = "Las contraseñas nuevas no coinciden.";
    $tipoMensaje = "danger";
  }
  // --- Validación 4: Reglas de la nueva contraseña (copiadas de restablecer_contrasena.php) ---
  else {
    $errors = [];
    if (strlen($nuevaPassword) < 8) {
      $errors[] = 'Debe tener al menos 8 caracteres.';
    }
    if (!preg_match('/[A-Z]/', $nuevaPassword)) {
      $errors[] = 'Debe incluir al menos una letra mayúscula.';
    }
    if (!preg_match('/[a-z]/', $nuevaPassword)) {
      $errors[] = 'Debe incluir al menos una letra minúscula.';
    }
    if (!preg_match('/[\W_]/', $nuevaPassword)) { // \W es "no-palabra", _ es underscore. Esto es un símbolo.
      $errors[] = 'Debe incluir al menos un símbolo o carácter especial.';
    }

    if (!empty($errors)) {
      $mensaje = 'La <strong>nueva contraseña</strong> no cumple con los requisitos:<br>- ' . implode('<br>- ', $errors);
      $tipoMensaje = "danger";
    }
    // --- ¡ÉXITO! ---
    else {
      try {
        // Esta es la función que ya existe y funciona
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
?>

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
          <?php echo $mensaje; // Usamos $mensaje directamente para permitir el <br> 
          ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      <?php endif; ?>

      <?php if ($usuarioActual): // Solo mostrar si el usuario se cargó bien 
      ?>

        <h5 class="mb-3">Mis Datos</h5>
        <fieldset disabled>
          <div class="row g-3">
            <div class="col-md-3">
              <label for="pNombre" class="form-label">Primer Nombre</label>
              <input type="text" class="form-control" id="pNombre"
                value="<?php echo htmlspecialchars($usuarioActual['pNombre'] ?? ''); ?>">
            </div>
            <div class="col-md-3">
              <label for="sNombre" class="form-label">Segundo Nombre</label>
              <input type="text" class="form-control" id="sNombre"
                value="<?php echo htmlspecialchars($usuarioActual['sNombre'] ?? ''); ?>">
            </div>
            <div class="col-md-3">
              <label for="aPaterno" class="form-label">Apellido Paterno</label>
              <input type="text" class="form-control" id="aPaterno"
                value="<?php echo htmlspecialchars($usuarioActual['aPaterno'] ?? ''); ?>">
            </div>
            <div class="col-md-3">
              <label for="aMaterno" class="form-label">Apellido Materno</label>
              <input type="text" class="form-control" id="aMaterno"
                value="<?php echo htmlspecialchars($usuarioActual['aMaterno'] ?? ''); ?>">
            </div>

            <hr class="my-3">

            <div class="col-md-6">
              <label for="correo" class="form-label">Correo Electrónico</label>
              <input type="email" class="form-control" id="correo"
                value="<?php echo htmlspecialchars($usuarioActual['correo'] ?? ''); ?>">
            </div>

            <div class="col-md-3">
              <label for="perfil_id" class="form-label">Perfil/Rol</label>
              <select class="form-select" id="perfil_id">
                <option value="">Seleccione Perfil</option>
                <?php
                $selectedPerfil = $usuarioActual['perfil_id'] ?? '';
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
              <label for="tipoUsuario_id" class="form-label">Tipo de Usuario</label>
              <select class="form-select" id="tipoUsuario_id">
                <option value="">Seleccione Tipo</option>
                <?php
                $selectedTipo = $usuarioActual['tipoUsuario_id'] ?? '';
                foreach ($tiposUsuario as $tipo):
                  $sel = ((string)$selectedTipo === (string)$tipo['idTipoUsuario']) ? 'selected' : '';
                ?>
                  <option value="<?php echo htmlspecialchars($tipo['idTipoUsuario']); ?>" <?php echo $sel; ?>>
                    <?php echo htmlspecialchars($tipo['descTipoUsuario']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-6">
              <label for="partido_id" class="form-label">Partido</label>
              <select class="form-select" id="partido_id">
                <option value="">Seleccione Partido</option>
                <?php
                $selectedPartido = $usuarioActual['partido_id'] ?? '';
                foreach ($partidos as $partido):
                  $sel = ((string)$selectedPartido === (string)$partido['idPartido']) ? 'selected' : '';
                ?>
                  <option value="<?php echo htmlspecialchars($partido['idPartido']); ?>" <?php echo $sel; ?>>
                    <?php echo htmlspecialchars($partido['nombrePartido']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-6">
              <label for="comuna_id" class="form-label">Comuna</label>
              <select class="form-select" id="comuna_id">
                <option value="">Seleccione Comuna</option>
                <?php
                $selectedComuna = $usuarioActual['comuna_id'] ?? '';
                foreach ($comunas as $comuna):
                  $sel = ((string)$selectedComuna === (string)$comuna['idComuna']) ? 'selected' : '';
                ?>
                  <option value="<?php echo htmlspecialchars($comuna['idComuna']); ?>" <?php echo $sel; ?>>
                    <?php echo htmlspecialchars($comuna['nombreComuna']); ?>
                  </option>
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
              <li id="rule-upper" class="rule-fail" style="margin-bottom: 5px;">• Al menos 1 mayúscula (A-Z)</li>
              <li id="rule-lower" class="rule-fail" style="margin-bottom: 5px;">• Al menos 1 minúscula (a-z)</li>
              <li id="rule-symbol" class="rule-fail" style="margin-bottom: 5px;">• Al menos 1 símbolo (!@#...)</li>
              <li id="rule-match" class="rule-fail" style="margin-bottom: 5px;">• Ambas contraseñas nuevas coinciden</li>
            </ul>
          </div>

          <div class="mt-4">
            <button type="submit" class="btn btn-primary">
              <i class="fas fa-save"></i> Guardar Contraseña
            </button>
          </div>
        </form>

      <?php endif; ?>
    </div>
  </div>
</div>

<style>
  .rule-ok {
    color: #155724;
    font-weight: bold;
  }

  .rule-fail {
    color: #721c24;
    font-weight: normal;
  }
</style>

<script>
  function validarPasswordCliente() {
    const p1 = document.getElementById('contrasena').value;
    const p2 = document.getElementById('confirmar_contrasena').value;

    const cumpleLargo = p1.length >= 8;
    const cumpleMayus = /[A-Z]/.test(p1);
    const cumpleMinus = /[a-z]/.test(p1);
    const cumpleSimbolo = /[\W_]/.test(p1); // \W es "no-palabra" (símbolo) y _
    const coincide = (p1 !== '' && p1 === p2);

    setRuleState('rule-length', cumpleLargo);
    setRuleState('rule-upper', cumpleMayus);
    setRuleState('rule-lower', cumpleMinus);
    setRuleState('rule-symbol', cumpleSimbolo);
    setRuleState('rule-match', coincide);
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

  // Ejecutar la validación al cargar la página por si hay datos autocompletados
  document.addEventListener('DOMContentLoaded', function() {
    // Ponemos la validación inicial en un try/catch por si la página
    // se carga en un contexto donde los elementos no existen.
    try {
      validarPasswordCliente();
    } catch (e) {}
  });
</script>