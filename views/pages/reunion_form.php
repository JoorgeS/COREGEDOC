<?php
// views/pages/reunion_form.php
// Este formulario envía los datos al ReunionController
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Crear Reunión</title>
    <link href="/corevota/public/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .container {
            max-width: 700px;
        }
    </style>
</head>

<body>
    <div class="container mt-4">
        <h3 class="mb-4">Crear Nueva Reunión</h3>

        <div class="card shadow-sm">
            <div class="card-body">
                <form action="/corevota/controllers/ReunionController.php" method="POST">
                    <input type="hidden" name="action" value="store_reunion">

                    <div class="mb-3">
                        <label for="comisionSelect" class="form-label">Comisión <span class="text-danger">*</span></label>
                        <select class="form-select" id="comisionSelect" name="t_comision_idComision" required>
                            <option value="">Cargando comisiones...</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="nombreReunion" class="form-label">Nombre de la Reunión <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nombreReunion" name="nombreReunion" required>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="fechaInicioReunion" class="form-label">Fecha y Hora de Inicio <span class="text-danger">*</span></label>
                            <input type="datetime-local" class="form-control" id="fechaInicioReunion" name="fechaInicioReunion" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="fechaTerminoReunion" class="form-label">Fecha y Hora de Término <span class="text-danger">*</span></label>
                            <input type="datetime-local" class="form-control" id="fechaTerminoReunion" name="fechaTerminoReunion" required>
                        </div>
                    </div>

                    <div class="d-flex justify-content-start gap-2 mt-3">
                        <button type="submit" class="btn btn-primary">Guardar Reunión</button>
                        <a href="menu.php?pagina=reunion_listado" class="btn btn-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const comisionSelect = document.getElementById('comisionSelect');
            fetch("/corevota/controllers/fetch_data.php?action=comisiones")
                .then(res => res.json())
                .then(response => {
                    if (response.status === 'success' && Array.isArray(response.data)) {
                        comisionSelect.innerHTML = '<option value="" disabled selected>Seleccione una comisión...</option>';
                        response.data.forEach(comision => {
                            comisionSelect.innerHTML += `<option value="${comision.idComision}">${comision.nombreComision}</option>`;
                        });
                    } else {
                        comisionSelect.innerHTML = '<option value="">Error al cargar comisiones</option>';
                    }
                })
                .catch(error => {
                    comisionSelect.innerHTML = '<option value="">Error de conexión</option>';
                    console.error("Error fetch comisiones:", error);
                });
        });
    </script>
</body>

</html>