<?php
// views/pages/comision_form.php

// Asegúrate de que $title y $comision estén definidos por el controlador.
// $title lo define el controller en los case 'create' y 'edit'
// $comision es null en 'create' y un array en 'edit'
$is_edit = isset($comision) && !empty($comision['idComision']); // Más robusto
$form_action_value = $is_edit ? 'update' : 'store'; // Valor para el campo oculto 'action'
$controller_url = "/corevota/controllers/ComisionController.php"; // URL base del controlador

$comision_id = $is_edit ? ($comision['idComision'] ?? '') : '';
$comision_nombre = $is_edit ? ($comision['nombreComision'] ?? '') : '';
// Vigencia: 1 por defecto al crear, o el valor guardado al editar
$current_vigencia = $is_edit ? ($comision['vigencia'] ?? 0) : 1;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($title ?? 'Formulario Comisión'); // Título por defecto ?></title>
    <link href="/corevota/public/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .container { max-width: 600px; }
        .card { border: 1px solid #ddd; margin-top: 1rem; } /* Añadido margen superior */
    </style>
</head>
<body>

<div class="container mt-4">
    <h3 class="mb-4"><?php echo htmlspecialchars($title ?? 'Formulario Comisión'); ?></h3>

    <?php
    // Muestra mensajes de sesión si existen
    if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['success'])): /* Mover éxito aquí si quieres */ ?>
         <?php endif; ?>


    <div class="card shadow-sm">
        <div class="card-body">
            <form action="<?php echo $controller_url; ?>" method="POST">
                <input type="hidden" name="action" value="<?php echo $form_action_value; ?>">

                <?php if ($is_edit): ?>
                    <input type="hidden" name="idComision" value="<?php echo htmlspecialchars($comision_id); ?>">
                <?php endif; ?>

                <div class="mb-3">
                    <label for="nombreComision" class="form-label">Nombre de la Comisión</label>
                    <input type="text"
                           class="form-control"
                           id="nombreComision"
                           name="nombreComision"
                           value="<?php echo htmlspecialchars($comision_nombre); ?>"
                           required>
                </div>

                <div class="mb-4">
                    <label for="vigencia" class="form-label">Estado / Vigencia</label>
                    <select class="form-select" id="vigencia" name="vigencia" required>
                        <option value="1" <?php echo $current_vigencia === 1 ? 'selected' : ''; ?>>Activa</option>
                        <option value="0" <?php echo $current_vigencia === 0 ? 'selected' : ''; ?>>Inactiva</option>
                    </select>
                </div>

                <div class="d-flex justify-content-start gap-2">
                    <button type="submit" class="btn btn-primary">
                        <?php echo $is_edit ? 'Guardar Cambios' : 'Crear Comisión'; ?>
                    </button>
                    <a href="menu.php?pagina=comision_listado" class="btn btn-secondary">Cancelar</a>
                </div>
            </form>
            </div>
    </div>
</div>

</body>
</html>