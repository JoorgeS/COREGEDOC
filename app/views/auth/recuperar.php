<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Recuperar Contraseña | COREGEDOC</title>
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
            background: #fff;
        }

        .captcha-box {
            font-family: monospace;
            letter-spacing: 3px;
            background: #eee;
            padding: 8px;
            text-align: center;
            font-weight: bold;
            font-size: 1.2em;
            border-radius: 4px;
        }

        /* Asegura que el Swal esté por encima */
        .swal2-container {
            z-index: 9999 !important;
        }
    </style>
</head>

<body>

    <div class="background-overlay"></div>

    <div class="card card-auth p-4">
        <div class="text-center mb-4">
            <img src="public/img/logoCore1.png" alt="Logo" height="60">
            <h4 class="mt-3 fw-bold">Recuperar Acceso</h4>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?= $message_type ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <form method="POST" id="formRecuperar">
            <div class="mb-3">
                <label class="form-label fw-bold">Correo Electrónico</label>
                <input type="email" name="correo" class="form-control" required placeholder="nombre@ejemplo.cl">
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold">Código de Seguridad</label>
                <div class="d-flex gap-2">
                    <div class="captcha-box flex-grow-1"><?= $_SESSION['captcha_code'] ?? '?????' ?></div>
                    <input type="text" name="captcha" class="form-control" required placeholder="Ingrese código" style="width: 50%;">
                </div>
            </div>

            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-dark fw-bold">ENVIAR INSTRUCCIONES</button>
                <a href="index.php?action=login" class="btn btn-outline-secondary">Volver al Login</a>
            </div>
        </form>
    </div>

    <script>
        document.getElementById('formRecuperar').addEventListener('submit', function(e) {

            let btn = this.querySelector('button[type="submit"]');

            Swal.fire({
                title: 'Enviando correo...',
                text: 'Por favor espere un momento mientras procesamos su solicitud.',
                icon: 'info',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                backdrop: false, // <--- ESTO QUITA EL FONDO OSCURO
                didOpen: () => {
                    Swal.showLoading();
                }
            });
        });
    </script>
</body>

</html>
</body>

</html>