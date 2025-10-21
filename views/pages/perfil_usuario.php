<?php
// views/pages/perfil_usuario.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pNombre = $_SESSION['pNombre'] ?? 'Usuario';
$aPaterno = $_SESSION['aPaterno'] ?? 'Invitado';
$correo = $_SESSION['correo'] ?? 'No disponible';
$idUsuario = $_SESSION['idUsuario'] ?? 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mi Perfil</title>
    <link href="/corevota/public/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-4">
    <h3 class="mb-4">Mi Perfil (<?php echo htmlspecialchars($pNombre . ' ' . $aPaterno); ?>)</h3>

    <div class="row">
        <div class="col-md-6 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <i class="fa-solid fa-address-card me-2"></i> Información de Contacto
                </div>
                <div class="card-body">
                    <p><strong>Usuario ID:</strong> <?php echo $idUsuario; ?></p>
                    <p><strong>Correo:</strong> <?php echo htmlspecialchars($correo); ?></p>
                    <a href="#" class="btn btn-sm btn-outline-primary mt-2 disabled">
                        Editar Información
                    </a>
                </div>
            </div>
        </div>

        <div class="col-md-6 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-danger text-white">
                    <i class="fa-solid fa-lock me-2"></i> Seguridad
                </div>
                <div class="card-body">
                    <p>Actualiza tu contraseña para mantener tu cuenta segura.</p>
                    <button class="btn btn-sm btn-danger mt-2" data-bs-toggle="modal" data-bs-target="#modalCambiarContrasena">
                        Cambiar Contraseña
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalCambiarContrasena" tabindex="-1" aria-labelledby="modalCambiarContrasenaLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalCambiarContrasenaLabel">Cambiar Contraseña</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form>
          <div class="mb-3">
            <label for="passActual" class="form-label">Contraseña Actual</label>
            <input type="password" class="form-control" id="passActual" required>
          </div>
          <div class="mb-3">
            <label for="passNueva" class="form-label">Nueva Contraseña</label>
            <input type="password" class="form-control" id="passNueva" required>
          </div>
          <div class="mb-3">
            <label for="passConfirmar" class="form-label">Confirmar Nueva Contraseña</label>
            <input type="password" class="form-control" id="passConfirmar" required>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-danger">Guardar Nueva Contraseña</button>
      </div>
    </div>
  </div>
</div>

</body>
</html>