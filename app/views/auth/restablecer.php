<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Nueva Contraseña | COREGEDOC</title>
    <link href="public/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="public/css/login_style.css">

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
            overflow: hidden;
        }

        .card-auth {
            width: 100%;
            max-width: 400px;
            border: none;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            position: relative;
            z-index: 2;
            /* Tu tarjeta está en nivel 2 */
            background: #fff;
        }

        /* AJUSTE IMPORTANTE: Forzar que el popup esté muy por encima de todo */
        .swal2-container {
            z-index: 9999 !important;
        }

        /* Opcional: Ajuste fino para que el popup se vea más limpio */
        .swal2-popup {
            font-family: inherit !important;
            /* Heredar fuente de tu sitio */
            border-radius: 10px !important;
        }
    </style>
</head>

<body>

    <div class="background-overlay"></div>

    <div class="card card-auth p-4">
        <div class="text-center mb-4">
            <img src="public/img/logoCore1.png" alt="Logo" height="60">
            <h4 class="mt-3 fw-bold">Nueva Contraseña</h4>
        </div>

        <?php if (!$tokenValido): ?>
            <div class="alert alert-danger text-center">
                <strong class="d-block mb-2">¡Enlace Expirado!</strong>
                El enlace de recuperación ya no es válido o ha caducado.
            </div>
            <div class="d-grid">
                <a href="index.php?action=recuperar_password" class="btn btn-dark">Solicitar nuevo enlace</a>
            </div>
        <?php else: ?>

            <?php if ($message && $message_type === 'success'): ?>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        Swal.fire({
                            title: '¡Contraseña Actualizada!',
                            text: 'Redirigiendo al login',
                            icon: 'success',

                            // CONFIGURACIÓN AUTOMÁTICA
                            timer: 2500, // 2500 milisegundos = 2.5 segundos
                            timerProgressBar: true, // Muestra una barrita de tiempo
                            showConfirmButton: false, // Oculta el botón "Aceptar"

                            allowOutsideClick: false,
                            allowEscapeKey: false,
                            backdrop: `rgba(0,0,0,0.4)`
                        }).then((result) => {
                            // Cuando el tiempo termina (dismiss === timer), redirige
                            window.location.href = 'index.php?action=login';
                        });
                    });
                </script>
            <?php endif; ?>

            <?php if ($message && $message_type !== 'success'): ?>
                <div class="alert alert-<?= $message_type ?>"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <?php if ($message_type !== 'success'): ?>
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Nueva Contraseña</label>
                        <input type="password" name="contrasena" class="form-control" required minlength="8" placeholder="Mínimo 8 caracteres">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Confirmar Contraseña</label>
                        <input type="password" name="confirmar_contrasena" class="form-control" required minlength="8" placeholder="Repita la contraseña">
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-dark fw-bold">GUARDAR CAMBIOS</button>
                    </div>
                </form>
            <?php endif; ?>

        <?php endif; ?>
    </div>
</body>

</html>