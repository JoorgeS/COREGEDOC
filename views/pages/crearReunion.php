<?php
// 1. LÓGICA PHP INICIAL PARA CARGAR DATOS DE EDICIÓN
// La ruta es '../../' porque este archivo está en views/pages/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$idSecretarioLogueado = $_SESSION['idUsuario'] ?? 0;
// --- FIN NUEVO ---

// La ruta es '../../' porque este archivo está en views/pages/

$controllerPath = __DIR__ . '/../../controllers/ReunionController.php';

$reunionData = null;
$action = $_GET['action'] ?? 'create';
$reunionId = $_GET['id'] ?? null;
$managerExists = false;

if (file_exists($controllerPath)) {
    require_once $controllerPath;
    if (class_exists('ReunionManager')) {
        $managerExists = true;
    }
}

// Ejecutar la consulta de EDICIÓN solo si el Manager existe
if ($managerExists && $action === 'edit' && $reunionId) {
    try {
        $manager = new ReunionManager();
        $result = $manager->getReunionById($reunionId);

        if ($result['status'] === 'success') {
            $reunionData = $result['data'];
        }
    } catch (Throwable $e) {
        error_log("Error al cargar datos de edición: " . $e->getMessage());
        $reunionData = null;
    }
}
?>

<input type="hidden" id="reunionId" value="<?php echo htmlspecialchars($reunionId); ?>">

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title><?php echo ($reunionData ? 'Editar Reunión' : 'Crear Nueva Reunión'); ?></title>
    <link href="/corevota/public/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>

    <div class="container mt-5">
        <h3 class="mb-4"><?php echo ($reunionData ? 'Editar Reunión ID: ' . htmlspecialchars($reunionId) : 'Crear Nueva Reunión'); ?></h3>

        <form id="formCrearReunion" class="p-4 border rounded bg-white">
            <div class="mb-3">
                <label for="comisionId" class="form-label">Comisión *</label>
                <select class="form-select" id="comisionId" required></select>
            </div>

            <div class="mb-3">
                <label for="nombreReunion" class="form-label">Nombre de la Reunión *</label>
                <input type="text" class="form-control" id="nombreReunion" required>
            </div>

            <div class="mb-3">
                <label for="numeroReunion" class="form-label">Número de Reunión *</label>
                <input type="text" class="form-control" id="numeroReunion" required>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="fechaInicio" class="form-label">Fecha y Hora de Inicio *</label>
                    <input type="datetime-local" class="form-control" id="fechaInicio" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="fechaTermino" class="form-label">Fecha y Hora de Término *</label>
                    <input type="datetime-local" class="form-control" id="fechaTermino" required>
                </div>
            </div>

            <button type="button" class="btn btn-success mt-3" onclick="guardarReunion()">
                💾 Guardar Reunión
            </button>
        </form>

        <div id="mensaje" class="mt-3"></div>
    </div>

    <script src="/corevota/public/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        // --- FUNCIÓN DE CARGA DE COMISIONES (ASÍNCRONA) ---
        async function cargarComisiones(selectId) {
            try {
                const ID_SECRETARIO_LOGUEADO = <?php echo json_encode($idSecretarioLogueado); ?>;
                const response = await fetch("/corevota/controllers/fetch_data.php?action=comisiones");
                const data = await response.json();
                const select = document.getElementById(selectId);
                select.innerHTML = '<option selected disabled value="">Seleccione...</option>';
                data.forEach(c => {
                    select.innerHTML += `<option value="${c.idComision}">${c.nombreComision}</option>`;
                });
            } catch (err) {
                console.error("Error cargando comisiones:", err);
            }
        }

        // --- LÓGICA DE CARGA DE PÁGINA Y RELLENO DE EDICIÓN ---
        document.addEventListener("DOMContentLoaded", async function() {
            await cargarComisiones("comisionId");

            // Lógica para rellenar el formulario al EDITAR
            <?php if ($reunionData): ?>
                const data = <?php echo json_encode($reunionData); ?>;

                document.getElementById('nombreReunion').value = data.nombreReunion;
                document.getElementById('numeroReunion').value = data.numeroReunion;
                document.getElementById('comisionId').value = data.t_comision_idComision;

                // Formato DATETIME-LOCAL (YYYY-MM-DDTHH:mm)
                document.getElementById('fechaInicio').value = data.fechaInicioReunion.replace(' ', 'T').substring(0, 16);
                document.getElementById('fechaTermino').value = data.fechaTerminoReunion.replace(' ', 'T').substring(0, 16);

                // Cambiar la función del botón a ACTUALIZAR
                const btnGuardar = document.querySelector('button[onclick="guardarReunion()"]');
                btnGuardar.innerText = 'Guardar Cambios';
                btnGuardar.setAttribute('onclick', 'actualizarReunion()');

            <?php endif; ?>
        });

        // --- FUNCIÓN DE CREACIÓN (guardarReunion) ---
        function guardarReunion() {
            const nombreReunion = document.getElementById('nombreReunion').value.trim();
            //const numeroReunion = document.getElementById('numeroReunion').value.trim();
            const comisionId = document.getElementById('comisionId').value;
            const fechaInicio = document.getElementById('fechaInicio').value;
            const fechaTermino = document.getElementById('fechaTermino').value;

            if (!comisionId || !nombreReunion || !numeroReunion || !fechaInicio || !fechaTermino) {
                document.getElementById('mensaje').innerHTML = '<div class="alert alert-danger">Todos los campos con * son obligatorios.</div>';
                return;
            }

            const datosReunion = {
                nombreReunion: nombreReunion,
                numeroReunion: numeroReunion,
                t_comision_idComision: comisionId,
                fechaInicioReunion: fechaInicio.replace('T', ' '),
                fechaTerminoReunion: fechaTermino.replace('T', ' '),
                vigente: 1,
                idSecretario: ID_SECRETARIO_LOGUEADO
            };

            fetch("/corevota/controllers/ReunionController.php?action=create", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json"
                    },
                    body: JSON.stringify(datosReunion)
                })
                .then(res => res.json())
                .then(resp => {
                    if (resp.status === "success") {
                        document.getElementById('mensaje').innerHTML = '<div class="alert alert-success">✅ Reunión guardada con ID: ' + resp.idReunion + '</div>';
                        document.getElementById('formCrearReunion').reset();
                    } else {
                        document.getElementById('mensaje').innerHTML = '<div class="alert alert-danger">⚠️ Error al guardar: ' + resp.message + '</div>';
                        console.error("Error del servidor:", resp.error);
                    }
                })
                .catch(err => {
                    document.getElementById('mensaje').innerHTML = '<div class="alert alert-danger">Error de conexión con el servidor.</div>';
                    console.error("Error de fetch:", err);
                });
        }

        // Añadir esta función en el bloque <script> de tu vista o layout principal

        function confirmarEliminacion(idReunion) {
            if (confirm("¿Estás seguro de que deseas eliminar la Reunión ID " + idReunion + "? Esta acción es irreversible.")) {
                eliminarReunion(idReunion);
            }
        }

        function eliminarReunion(idReunion) {
            fetch(`/corevota/controllers/ReunionController.php?action=delete&id=${idReunion}`, {
                    method: 'POST', // Usamos POST para la acción de eliminación
                    headers: {
                        'Content-Type': 'application/json'
                    }
                })
                .then(res => res.json())
                .then(resp => {
                    const mensajeDiv = document.getElementById('mensajeListado') || document.querySelector('.container');

                    if (resp.status === 'success') {
                        alert('✅ Reunión eliminada con éxito.');
                        // Recargar la página para actualizar la lista:
                        window.location.reload();
                    } else {
                        alert('⚠️ Error al eliminar: ' + (resp.message || 'Error desconocido.'));
                        console.error(resp.error);
                    }
                })
                .catch(err => {
                    alert('Error de conexión al intentar eliminar la reunión.');
                    console.error(err);
                });
        }


        // --- FUNCIÓN DE ACTUALIZACIÓN (ActualizarReunion) ---
        function actualizarReunion() {
            const reunionId = document.getElementById('reunionId').value;

            if (!reunionId) {
                document.getElementById('mensaje').innerHTML = '<div class="alert alert-danger">Error: ID de reunión no encontrado para actualizar.</div>';
                return;
            }

            const nombreReunion = document.getElementById('nombreReunion').value.trim();
           // const numeroReunion = document.getElementById('numeroReunion').value.trim();
            const comisionId = document.getElementById('comisionId').value;
            const fechaInicio = document.getElementById('fechaInicio').value;
            const fechaTermino = document.getElementById('fechaTermino').value;

            if (!comisionId || !nombreReunion || !numeroReunion || !fechaInicio || !fechaTermino) {
                document.getElementById('mensaje').innerHTML = '<div class="alert alert-danger">Todos los campos con * son obligatorios.</div>';
                return;
            }

            const datosReunion = {
                id: reunionId,
                nombreReunion: nombreReunion,
                numeroReunion: numeroReunion,
                t_comision_idComision: comisionId,
                fechaInicioReunion: fechaInicio.replace('T', ' '),
                fechaTerminoReunion: fechaTermino.replace('T', ' '),
                vigente: 1
            };

            fetch("/corevota/controllers/ReunionController.php?action=update", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json"
                    },
                    body: JSON.stringify(datosReunion)
                })
                .then(res => res.json())
                .then(resp => {
                    if (resp.status === "success") {
                        document.getElementById('mensaje').innerHTML = '<div class="alert alert-success">✅ Reunión ID ' + reunionId + ' actualizada con éxito.</div>';
                    } else {
                        document.getElementById('mensaje').innerHTML = '<div class="alert alert-danger">⚠️ Error al actualizar: ' + resp.message + '</div>';
                        console.error("Error del servidor:", resp.error);
                    }
                })
                .catch(err => {
                    document.getElementById('mensaje').innerHTML = '<div class="alert alert-danger">Error de conexión con el servidor.</div>';
                    console.error("Error de fetch:", err);
                });
        }
    </script>
</body>

</html>