<?php
// views/pages/minuta_form.php
// Asume que $tema y $title están definidos por el controlador.
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($title); ?></title>
    <link href="/corevota/public/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .container { max-width: 900px; }
        textarea { min-height: 120px; }
    </style>
</head>
<body>

<div class="container mt-4">
    <h3 class="mb-4"><?php echo htmlspecialchars($title); ?></h3>

    <?php 
    if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php elseif (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">
            <form action="MinutaController.php?action=update" method="POST">
                
                <input type="hidden" name="idTema" value="<?php echo htmlspecialchars($tema['idTema']); ?>">
                
                <div class="mb-3">
                    <label for="nombreTema" class="form-label fw-bold">Nombre del Tema</label>
                    <textarea class="form-control" id="nombreTema" name="nombreTema" required><?php echo htmlspecialchars($tema['nombreTema']); ?></textarea>
                </div>

                <div class="mb-3">
                    <label for="objetivo" class="form-label fw-bold">Objetivo</label>
                    <textarea class="form-control" id="objetivo" name="objetivo" required><?php echo htmlspecialchars($tema['objetivo']); ?></textarea>
                </div>

                <div class="mb-3">
                    <label for="compromiso" class="form-label fw-bold">Compromisos</label>
                    <textarea class="form-control" id="compromiso" name="compromiso"><?php echo htmlspecialchars($tema['compromiso']); ?></textarea>
                </div>

                <div class="mb-3">
                    <label for="observacion" class="form-label fw-bold">Observaciones</label>
                    <textarea class="form-control" id="observacion" name="observacion"><?php echo htmlspecialchars($tema['observacion']); ?></textarea>
                </div>

                <div class="d-flex justify-content-between mt-4">
                    <button type="submit" class="btn btn-success">💾 Guardar Cambios</button>
                    <a href="MinutaController.php?action=list" class="btn btn-secondary">Cancelar y Volver</a>
                </div>
            </form>
        </div>
    </div>
</div>

</body>
</html>