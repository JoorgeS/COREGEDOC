<?php
// views/pages/comision_form.php

// Asegúrate de que $title y $comision estén definidos por el controlador.
$action_url = isset($comision['idComision']) ? 'ComisionController.php?action=update' : 'ComisionController.php?action=store';
$is_edit = isset($comision['idComision']);
$current_vigencia = $is_edit ? $comision['vigencia'] : 1;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($title); ?></title>
    <link href="/corevota/public/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .container { max-width: 600px; }
        .card { border: 1px solid #ddd; }
    </style>
</head>
<body>

<div class="container mt-4">
    <h3 class="mb-4"><?php echo htmlspecialchars($title); ?></h3>

    <?php 
    // Muestra mensajes de sesión (éxito o error)
    if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php elseif (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">
            <form action="<?php echo $action_url; ?>" method="POST">
                
                <?php if ($is_edit): ?>
                    <input type="hidden" name="idComision" value="<?php echo htmlspecialchars($comision['idComision']); ?>">
                <?php endif; ?>

                <div class="mb-3">
                    <label for="nombreComision" class="form-label">Nombre de la Comisión</label>
                    <input type="text" 
                           class="form-control" 
                           id="nombreComision" 
                           name="nombreComision" 
                           value="<?php echo $is_edit ? htmlspecialchars($comision['nombreComision']) : ''; ?>"
                           required>
                </div>

                <div class="mb-4">
                    <label for="vigencia" class="form-label">Estado / Vigencia</label>
                    <select class="form-select" id="vigencia" name="vigencia" required>
                        <option value="1" <?php echo $current_vigencia == 1 ? 'selected' : ''; ?>>Activa</option>
                        <option value="0" <?php echo $current_vigencia == 0 ? 'selected' : ''; ?>>Inactiva</option>
                    </select>
                </div>

                <div class="d-flex justify-content-start gap-2">
                    <button type="submit" class="btn btn-primary">
                        <?php echo $is_edit ? 'Guardar Cambios' : 'Crear Comisión'; ?>
                    </button>
                    <a href="ComisionController.php?action=list" class="btn btn-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>

</body>
</html>