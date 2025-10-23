<?php
// views/pages/reunion_form.php
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Crear Reunión</title>
    <link href="/corevota/public/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        .container { max-width: 700px; }
        /* Style to smoothly show/hide blocks */
        .hidden-block { display: none; transition: opacity 0.3s ease-in-out; opacity: 0; }
        .hidden-block.show { display: block; opacity: 1; } /* Class to fade in */
    </style>
</head>
<body>
    <div class="container mt-4">
        <h3 class="mb-4">Crear Nueva Reunión</h3>

        <div class="card shadow-sm">
            <div class="card-body">
                <form action="/corevota/controllers/ReunionController.php" method="POST" id="reunionForm">
                    <input type="hidden" name="action" value="store_reunion">

                    <div class="mb-3">
                        <label for="comisionSelect1" class="form-label">Comisión Principal <span class="text-danger">*</span></label>
                        <select class="form-select" id="comisionSelect1" name="t_comision_idComision" required>
                            <option value="">Cargando comisiones...</option>
                        </select>
                    </div>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="comisionMixtaCheck" name="comisionMixta" value="1" onchange="toggleComisionMixta()">
                        <label class="form-check-label fw-semibold" for="comisionMixtaCheck">
                            ¿Es Comisión Mixta/Conjunta?
                        </label>
                    </div>

                    <div id="bloqueMixta" class="hidden-block border rounded p-3 mb-3 bg-light"> 
                        <p class="fw-bold mb-2">Comisiones Adicionales:</p>
                
                        <div class="mb-3">
                            <label for="comisionSelect2" class="form-label">Segunda Comisión <span class="text-danger">*</span></label>
                            <select class="form-select" id="comisionSelect2" name="t_comision_idComision_mixta" onchange="toggleTerceraComision()"> {/* Added onchange */}
                                <option value="">Seleccione...</option>
                            </select>
                            <small class="text-muted">Seleccione la segunda comisión participante.</small>
                        </div>
                       
                        <div id="bloqueTercera" class="hidden-block mb-2"> 
                            <label for="comisionSelect3" class="form-label">Tercera Comisión (Opcional)</label>
                            <select class="form-select" id="comisionSelect3" name="t_comision_idComision_mixta2">
                                <option value="">Seleccione si aplica...</option>
                            </select>
                             <small class="text-muted">Seleccione si hay una tercera comisión.</small>
                        </div>
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
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Guardar Reunión
                        </button>
                        <a href="menu.php?pagina=reunion_listado" class="btn btn-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        let comisionesData = []; // Store fetched commissions

        // Fetch commissions on page load
        document.addEventListener("DOMContentLoaded", () => {
            fetchComisiones();
        });

        function fetchComisiones() {
            const comisionSelect1 = document.getElementById('comisionSelect1');
            const comisionSelect2 = document.getElementById('comisionSelect2');
            const comisionSelect3 = document.getElementById('comisionSelect3');

            fetch("/corevota/controllers/fetch_data.php?action=comisiones")
                .then(res => res.ok ? res.json() : Promise.reject(res))
                .then(response => {
                    if (response.status === 'success' && Array.isArray(response.data)) {
                        comisionesData = response.data;
                        populateSelect(comisionSelect1, comisionesData, "Seleccione la comisión principal...");
                        populateSelect(comisionSelect2, comisionesData, "Seleccione la segunda comisión...");
                        populateSelect(comisionSelect3, comisionesData, "Seleccione si aplica...");
                    } else {
                        handleFetchError([comisionSelect1, comisionSelect2, comisionSelect3]);
                    }
                })
                .catch(error => {
                    handleFetchError([comisionSelect1, comisionSelect2, comisionSelect3]);
                    console.error("Error fetch comisiones:", error);
                });
        }

        // Helper function to show error in selects
        function handleFetchError(selects) {
            selects.forEach(select => {
                if(select) select.innerHTML = '<option value="">Error al cargar</option>';
            });
        }

        // Helper function to populate select options
        function populateSelect(selectElement, data, placeholder) {
            selectElement.innerHTML = `<option value="">${placeholder}</option>`;
            data.forEach(comision => {
                selectElement.innerHTML += `<option value="${comision.idComision}">${comision.nombreComision}</option>`;
            });
        }

        // Function to show/hide the additional commission block
        function toggleComisionMixta() {
            const check = document.getElementById('comisionMixtaCheck');
            const bloqueMixta = document.getElementById('bloqueMixta');
            const select2 = document.getElementById('comisionSelect2');
            const bloqueTercera = document.getElementById('bloqueTercera'); // Get the third block
            const select3 = document.getElementById('comisionSelect3');

            if (check.checked) {
                bloqueMixta.style.display = 'block'; // Show the main block first
                setTimeout(() => bloqueMixta.classList.add('show'), 10);
                select2.required = true; // Second commission is required
                // DO NOT show third block yet
                bloqueTercera.classList.remove('show');
                bloqueTercera.style.display = 'none';
                select3.required = false;
            } else {
                bloqueMixta.classList.remove('show');
                setTimeout(() => { // Hide everything after fade out
                    bloqueMixta.style.display = 'none';
                    bloqueTercera.style.display = 'none';
                    bloqueTercera.classList.remove('show');
                }, 300);
                select2.required = false;
                select3.required = false;
                select2.value = "";
                select3.value = "";
            }
        }

        // NEW Function to show/hide the THIRD commission dropdown based on the SECOND
        function toggleTerceraComision() {
            const select2 = document.getElementById('comisionSelect2');
            const bloqueTercera = document.getElementById('bloqueTercera');
            const select3 = document.getElementById('comisionSelect3');

            if (select2.value) { // If a value is selected in the second dropdown
                bloqueTercera.style.display = 'block'; // Show the third block
                setTimeout(() => bloqueTercera.classList.add('show'), 10);
                select3.required = false; // Still optional
            } else { // If the second dropdown is cleared
                bloqueTercera.classList.remove('show');
                setTimeout(() => bloqueTercera.style.display = 'none', 300);
                select3.required = false;
                select3.value = ""; // Reset third selection
            }
        }
    </script>
</body>
</html>