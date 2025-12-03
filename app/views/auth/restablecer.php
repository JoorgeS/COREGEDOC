<?php
    // Inicializamos variables para evitar errores de PHP si no vienen definidas
    $tokenValido = $tokenValido ?? false;
    $message = $message ?? '';
    $message_type = $message_type ?? '';
?>
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
            overflow: hidden; /* Mantiene el scroll oculto */
        }
        .card-auth {
            width: 100%;
            max-width: 400px;
            border: none;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            position: relative;
            z-index: 2;
            background: #fff;
            min-height: 420px; 
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        /* Ajuste para que el popup no altere estilos globales */
        .swal2-container { z-index: 9999 !important; }
        .swal2-popup { font-family: inherit !important; border-radius: 10px !important; }
        .requirements-text {
            font-size: 0.75rem;
            color: #6c757d;
            margin-top: 5px;
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
                            text: 'Redirigiendo al login...',
                            icon: 'success',
                            timer: 2500,
                            timerProgressBar: true,
                            showConfirmButton: false,
                            allowOutsideClick: false,
                            allowEscapeKey: false,
                            // CONFIGURACIÓN PARA QUE SEA ESTÁTICO Y TRANSPARENTE:
                            backdrop: 'rgba(0,0,0,0)', // Fondo totalmente transparente
                            heightAuto: false,         // IMPIDE que SweetAlert modifique la altura del body (evita el salto)
                            scrollbarPadding: false    // IMPIDE que añada padding extra a la derecha
                        }).then((result) => {
                            window.location.href = 'index.php?action=login';
                        });
                    });
                </script>
            <?php endif; ?>

            <?php if ($message && $message_type !== 'success'): ?>
                <div class="alert alert-<?= $message_type ?>"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <form method="POST" id="formRestablecer" novalidate>
                <div class="mb-3">
                    <label class="form-label fw-bold">Nueva Contraseña</label>
                    <input type="password" name="contrasena" id="contrasena" class="form-control" required placeholder="Ingrese nueva contraseña">
                    <div class="requirements-text">
                        8-16 caracteres, mayúscula, minúscula, número y símbolo.
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Confirmar Contraseña</label>
                    <input type="password" name="confirmar_contrasena" id="confirmar_contrasena" class="form-control" required placeholder="Repita la contraseña">
                </div>
                <div class="d-grid">
                    <button type="submit" class="btn btn-dark fw-bold">GUARDAR CAMBIOS</button>
                </div>
            </form>

            <script>
                document.getElementById('formRestablecer').addEventListener('submit', function(e) {
                    e.preventDefault(); 

                    const pass = document.getElementById('contrasena').value;
                    const confirm = document.getElementById('confirmar_contrasena').value;

                    // 1. Validar coincidencia
                    if (pass !== confirm) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Las contraseñas no coinciden.',
                            backdrop: 'rgba(0,0,0,0)',
                            heightAuto: false,      // FIX
                            scrollbarPadding: false // FIX
                        });
                        return;
                    }

                    // 2. Validar requisitos (Regex)
                    const regex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,16}$/;

                    if (!regex.test(pass)) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Contraseña Insegura',
                            html: '<ul style="text-align:left; font-size:0.9rem; margin-bottom:0;">' +
                                  '<li>Entre 8 y 16 caracteres</li>' +
                                  '<li>Al menos una mayúscula</li>' +
                                  '<li>Al menos una minúscula</li>' +
                                  '<li>Al menos un número</li>' +
                                  '<li>Al menos un símbolo (!@#$%, etc)</li>' +
                                  '</ul>',
                            backdrop: 'rgba(0,0,0,0)',
                            heightAuto: false,      // FIX
                            scrollbarPadding: false // FIX
                        });
                        return;
                    }

                    // 3. Mostrar carga y enviar
                    Swal.fire({
                        title: 'Actualizando...',
                        text: 'Guardando su nueva contraseña.',
                        allowOutsideClick: false,
                        showConfirmButton: false,
                        // Mismas propiedades para evitar el oscurecimiento y salto visual
                        backdrop: 'rgba(0,0,0,0)', 
                        heightAuto: false,
                        scrollbarPadding: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    this.submit();
                });
            </script>

        <?php endif; ?>
    </div>
</body>
</html>