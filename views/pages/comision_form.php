<?php
// views/pages/comision_form.php

$is_edit = isset($comision) && !empty($comision['idComision']);
$form_action_value = $is_edit ? 'update' : 'store';
$controller_url = "/corevota/controllers/ComisionController.php"; // Asegúrate que esta ruta sea correcta

$comision_id = $is_edit ? ($comision['idComision'] ?? '') : '';
$comision_nombre = $is_edit ? ($comision['nombreComision'] ?? '') : '';
$current_vigencia = $is_edit ? ($comision['vigencia'] ?? 0) : 1; // 1 (Activa) por defecto al crear
// ❗️ NUEVO: Obtener ID del presidente actual si estamos editando
$current_presidente_id = $is_edit ? ($comision['t_usuario_idPresidente'] ?? null) : null;

// Asegurar que $title esté definido (lo pasa el controlador)
$title = $title ?? ($is_edit ? 'Editar Comisión' : 'Crear Comisión');
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($title); ?></title>
    <link href="/corevota/public/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .container {
            max-width: 600px;
        }

        .card {
            border: 1px solid #ddd;
            margin-top: 1rem;
        }
    </style>
</head>

<body>

    <div class="container mt-4">
        <h3 class="mb-4"><?php echo htmlspecialchars($title); ?></h3>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?php echo $_SESSION['error'];
                                            unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <div class="card shadow-sm">
            <div class="card-body">
                <form action="<?php echo $controller_url; ?>" method="POST">
                    <input type="hidden" name="action" value="<?php echo $form_action_value; ?>">
                    <?php if ($is_edit): ?>
                        <input type="hidden" name="idComision" value="<?php echo htmlspecialchars($comision_id); ?>">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label for="nombreComision" class="form-label">Nombre de la Comisión <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nombreComision" name="nombreComision" value="<?php echo htmlspecialchars($comision_nombre); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="presidenteComision" class="form-label">Presidente de Comisión</label>
                        <select class="form-select" id="presidenteComision" name="t_usuario_idPresidente">
                            <option value="">Cargando presidentes...</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label for="vigencia" class="form-label">Estado / Vigencia <span class="text-danger">*</span></label>
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

    <script>
        // Pasamos el ID del presidente actual (si existe) a JavaScript
        const currentPresidenteId = <?php echo json_encode($current_presidente_id); ?>;

        document.addEventListener("DOMContentLoaded", () => {
            const presidenteSelect = document.getElementById('presidenteComision');

            // Usamos el mismo endpoint que ya tienes para cargar presidentes/consejeros
            fetch("/corevota/controllers/fetch_data.php?action=presidentes")
                .then(res => res.ok ? res.json() : Promise.reject('Error al cargar datos'))
                .then(response => {
                    if (response.status === 'success' && Array.isArray(response.data)) {
                        presidenteSelect.innerHTML = '<option value="">Seleccione un presidente...</option>'; // Opción por defecto
                        response.data.forEach(user => {
                            // Comparar como string por seguridad
                            const isSelected = (currentPresidenteId != null && String(user.idUsuario) === String(currentPresidenteId)) ? 'selected' : '';
                            presidenteSelect.innerHTML += `<option value="${user.idUsuario}" ${isSelected}>${user.nombreCompleto}</option>`;
                        });
                        // Forzar valor si estamos editando (más robusto)
                        if (currentPresidenteId) {
                            presidenteSelect.value = currentPresidenteId;
                        }
                    } else {
                        presidenteSelect.innerHTML = '<option value="">Error al cargar presidentes</option>';
                        console.error("Respuesta inválida para presidentes:", response);
                    }
                })
                .catch(error => {
                    presidenteSelect.innerHTML = '<option value="">Error de conexión</option>';
                    console.error("Error fetch presidentes:", error);
                });
        });
    </script>

</body>

</html>