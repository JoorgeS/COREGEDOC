<?php
// RUTA CRÍTICA: Desde views/pages/ subimos dos niveles (../../) a la raíz para encontrar Usuario.php
require_once(__DIR__ . '/../../Usuario.php');  

$usuarioObj = new Usuario();
$action = $_GET['action'] ?? 'create';
$idUsuario = (int)($_GET['id'] ?? 0);
$userData = [];
$titulo = "Registrar Nuevo Usuario";
$contrasenaPlaceholder = "Contraseña (obligatoria)";

// Lógica para cargar datos si es edición
if ($action === 'edit' && $idUsuario > 0) {
    $userData = $usuarioObj->obtenerUsuario($idUsuario);
    if (!$userData) {
        header('Location: usuarios_listado.php?status=error&msg='.urlencode('Usuario a editar no encontrado.'));
        exit;
    }
    // No pasamos la contraseña al formulario de edición por seguridad
    unset($userData['contrasena']); 
    
    $titulo = "Modificar Usuario: " . htmlspecialchars($userData['pNombre']) . ' ' . htmlspecialchars($userData['aPaterno']);
    $contrasenaPlaceholder = "Dejar en blanco para no cambiar";
}

// Obtener listas para los <select> (Foreign Keys)
$perfiles = $usuarioObj->obtenerPerfiles();
$tiposUsuario = $usuarioObj->obtenerTiposUsuario();
$partidos = $usuarioObj->obtenerPartidos();
$comunas = $usuarioObj->obtenerComunas();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?php echo $titulo; ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="/corevota/public/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-4">
        <div class="card shadow-sm p-4">
            <h2 class="mb-4"><?php echo $titulo; ?></h2>

            <form action="usuario_acciones.php" method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="action" value="<?php echo $action; ?>">
                <?php if ($idUsuario): ?>
                    <input type="hidden" name="idUsuario" id="idUsuario" value="<?php echo htmlspecialchars($idUsuario); ?>">
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
                               placeholder="<?php echo $contrasenaPlaceholder; ?>" 
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
                                <option value="<?php echo $perfil['idPerfil']; ?>" <?php echo $sel; ?>>
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
                                <option value="<?php echo $tipo['idTipoUsuario']; ?>" <?php echo $sel; ?>>
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
                            // Nota: Usamos 'null' para manejar campos que pueden ser opcionales (NULL en la DB)
                            $selectedPartido = $userData['partido_id'] ?? '';
                            foreach ($partidos as $partido): 
                                $sel = ((string)$selectedPartido === (string)$partido['idPartido']) ? 'selected' : '';
                            ?>
                                <option value="<?php echo $partido['idPartido']; ?>" <?php echo $sel; ?>>
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
                                <option value="<?php echo $comuna['idComuna']; ?>" <?php echo $sel; ?>>
                                    <?php echo htmlspecialchars($comuna['nombreComuna']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-12 mt-4">
                        <button type="submit" class="btn btn-primary">
                            <?php echo $action === 'create' ? 'Registrar Usuario' : 'Guardar Cambios'; ?>
                        </button>
                        <a href="usuarios_listado.php" class="btn btn-secondary ms-2">Cancelar</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <script src="/corevota/public/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
    // Script básico para validación de Bootstrap
    (function () {
      'use strict'
      var forms = document.querySelectorAll('.needs-validation')
      Array.prototype.slice.call(forms)
        .forEach(function (form) {
          form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
              event.preventDefault()
              event.stopPropagation()
            }
            form.classList.add('was-validated')
          }, false)
        })
    })()
    </script>
</body>
</html>