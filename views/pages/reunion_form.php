<?php
// views/pages/reunion_form.php

// El controlador (action=edit desde menu.php) puede inyectar $reunion_data
// Si $reunion_data existe, estamos en modo de edición.
$isEditMode = isset($reunion_data) && !empty($reunion_data);

// Establecer valores por defecto
// Usamos htmlspecialchars para prevenir XSS en campos de texto
$idReunion_val = $isEditMode ? $reunion_data['idReunion'] : '';
$nombre_val = $isEditMode ? htmlspecialchars($reunion_data['nombreReunion']) : '';
$comision1_val = $isEditMode ? $reunion_data['t_comision_idComision'] : '';
$comision2_val = $isEditMode ? $reunion_data['t_comision_idComision_mixta'] : '';
$comision3_val = $isEditMode ? $reunion_data['t_comision_idComision_mixta2'] : '';

// Los inputs datetime-local necesitan el formato 'Y-m-d\TH:i'
$inicio_val = $isEditMode ? date('Y-m-d\TH:i', strtotime($reunion_data['fechaInicioReunion'])) : '';
$termino_val = $isEditMode ? date('Y-m-d\TH:i', strtotime($reunion_data['fechaTerminoReunion'])) : '';

$esMixta_val = $isEditMode && !empty($comision2_val);

// Determinar la acción del formulario y los textos
$form_action = $isEditMode ? 'update_reunion' : 'store_reunion';
$page_title = $isEditMode ? 'Editar Reunión' : 'Crear Nueva Reunión';
$button_text = $isEditMode ? '<i class="fas fa-save me-1"></i> Actualizar Reunión' : '<i class="fas fa-save me-1"></i> Guardar Reunión';
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title><?php echo $page_title; ?></title>
    <link href="/corevota/public/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        .container {
            max-width: 700px;
        }

        .hidden-block {
            display: none;
            transition: opacity 0.3s ease-in-out;
            opacity: 0;
        }

        .hidden-block.show {
            display: block;
            opacity: 1;
        }

        /* Clase modificada para un bloque más visual */
        .pretty-block {
            background: linear-gradient(to bottom right, #ffffff 0%, #fafbff 100%);
            border: 1px solid #e1e5ef;
            border-radius: 10px;
            padding: 1rem 1rem .75rem;
            margin-bottom: 1rem;
        }

        .section-header-inline {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: .75rem;
        }

        .section-title {
            font-size: 1rem; /* Aumentado ligeramente para más peso */
            font-weight: 700; /* Más audaz */
            color: #1f2d3d;
            display: flex;
            align-items: center;
            gap: .5rem;
            margin-bottom: .5rem;
        }

        .section-title i {
            color: #0d6efd;
        }

        .section-desc {
            font-size: .8rem;
            color: #6b727d;
            line-height: 1.3;
            margin-top: .25rem;
        }

        .asterisk {
            color: #dc3545;
            font-weight: 600;
        }

        .time-row {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .time-col {
            flex: 1 1 300px;
            min-width: 260px;
        }

        .input-group .btn-plus {
            font-size: .8rem;
            font-weight: 500;
            white-space: nowrap;
        }

        .hint {
            font-size: .75rem;
            color: #6b727d;
            margin-top: .4rem;
            line-height: 1.3;
        }

        .invalid-horario, .invalid-feedback-custom {
            color: #dc3545;
            font-size: .8rem;
            font-weight: 500;
            display: none;
            margin-top: .4rem;
        }

        .is-invalid {
            border-color: #dc3545 !important;
            box-shadow: 0 0 0 .2rem rgba(220, 53, 69, .1);
        }
    </style>
</head>

<body>
    <div class="container mt-4">
        <h3 class="mb-4"><?php echo $page_title; ?></h3>

        <div class="card shadow-sm">
            <div class="card-body">
                <form action="/corevota/controllers/ReunionController.php" method="POST" id="reunionForm">
                    <input type="hidden" name="action" value="<?php echo $form_action; ?>">

                    <?php if ($isEditMode): ?>
                        <input type="hidden" name="idReunion" value="<?php echo $idReunion_val; ?>">
                    <?php endif; ?>

                    <div class="pretty-block mb-4">
                        <div class="section-title mb-3">
                            <i class="fa-solid fa-people-group"></i>
                            <span>1. Comisiones Responsables</span>
                        </div>

                        <div class="mb-3">
                            <label for="comisionSelect1" class="form-label">
                                Comisión Principal <span class="asterisk">*</span>
                            </label>
                            <select class="form-select form-select-md rounded-3" id="comisionSelect1" name="t_comision_idComision" required onchange="toggleComisionMixtaBlock()">
                                <option value="">Cargando comisiones...</option>
                            </select>
                            <div class="hint mt-1">
                                Seleccione la comisión responsable de la sesión.
                            </div>
                            <div id="errorComision1" class="invalid-feedback-custom"></div>
                        </div>

                        <div id="opcionMixtaBlock" class="hidden-block pt-2">
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="comisionMixtaCheck" name="comisionMixta" value="1"
                                    onchange="toggleComisionMixta()" <?php if ($esMixta_val) echo 'checked'; ?>>
                                <label class="form-check-label fw-semibold" for="comisionMixtaCheck">
                                    ¿Es una reunión de Comisión Mixta/Conjunta?
                                </label>
                            </div>
                        </div>

                        <div id="bloqueMixta" class="hidden-block border rounded p-3 mt-3 bg-white">
                            <p class="fw-bold mb-3">Comisiones Adicionales</p>
                            
                            <div class="mb-3">
                                <label for="comisionSelect2" class="form-label">
                                    Segunda Comisión <span class="asterisk">*</span>
                                </label>
                                <select class="form-select" id="comisionSelect2" name="t_comision_idComision_mixta" onchange="toggleTerceraComision(); validateComisiones();">
                                    <option value="">Seleccione...</option>
                                </select>
                                <div class="hint">La segunda comisión participante. Debe ser distinta a la Principal.</div>
                                <div id="errorComision2" class="invalid-feedback-custom"></div>
                            </div>

                            <div id="bloqueTercera" class="hidden-block mb-1">
                                <label for="comisionSelect3" class="form-label">Tercera Comisión (Opcional)</label>
                                <select class="form-select" id="comisionSelect3" name="t_comision_idComision_mixta2" onchange="validateComisiones()">
                                    <option value="">Seleccione si aplica...</option>
                                </select>
                                <div class="hint">Seleccione si hay una tercera comisión participante.</div>
                                <div id="errorComision3" class="invalid-feedback-custom"></div>
                            </div>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label for="nombreReunion" class="form-label">
                            2. Nombre de la Reunión <span class="asterisk">*</span>
                        </label>
                        <input type="text" class="form-control" id="nombreReunion" name="nombreReunion" required
                            value="<?php echo $nombre_val; ?>">
                        <div class="hint">Título descriptivo para identificar rápidamente la sesión.</div>
                    </div>
                    <div class="pretty-block">
                        <div class="section-header-inline">
                            <div>
                                <div class="section-title">
                                    <i class="fa-solid fa-clock"></i>
                                    <span>3. Duración y Horario</span>
                                </div>
                                <div class="section-desc">
                                    <?php if (!$isEditMode): ?>
                                        Se propone la hora actual como inicio y **+1 hora** como término. Puedes ajustar.
                                    <?php else: ?>
                                        Ajuste la fecha y hora de la reunión.
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="time-row">
                            <div class="time-col">
                                <label for="fechaInicioReunion" class="form-label">
                                    Fecha y Hora de Inicio <span class="asterisk">*</span>
                                </label>
                                <input type="datetime-local" class="form-control" id="fechaInicioReunion" name="fechaInicioReunion" required
                                    value="<?php echo $inicio_val; ?>">
                                <div class="hint">Momento en que comienza la sesión.</div>
                            </div>

                            <div class="time-col">
                                <label for="fechaTerminoReunion" class="form-label">
                                    Fecha y Hora de Término <span class="asterisk">*</span>
                                </label>
                                <div class="input-group">
                                    <input type="datetime-local" class="form-control" id="fechaTerminoReunion" name="fechaTerminoReunion" required
                                        value="<?php echo $termino_val; ?>">
                                    <button class="btn btn-outline-primary btn-plus" type="button" id="btnCopiarMasHora">
                                        +1 hr
                                    </button>
                                </div>
                                <div class="hint">Hora estimada de término. Usa **+1 hr** para autocompletar.</div>
                                <div id="errorHorario" class="invalid-horario">
                                    La hora de término debe ser posterior al inicio.
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-start gap-2 mt-4">
                        <button type="submit" class="btn btn-primary">
                            <?php echo $button_text; ?>
                        </button>
                        <a href="menu.php?pagina=reunion_listado" class="btn btn-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        let comisionesData = [];
        const isEditMode = <?php echo json_encode($isEditMode); ?>;
        const editData = <?php echo json_encode($isEditMode ? $reunion_data : new stdClass()); ?>;

        // Referencias a los selects
        const com1 = document.getElementById('comisionSelect1');
        const com2 = document.getElementById('comisionSelect2');
        const com3 = document.getElementById('comisionSelect3');

        // Referencias a los bloques
        const opcionMixtaBlock = document.getElementById('opcionMixtaBlock');
        const comisionMixtaCheck = document.getElementById('comisionMixtaCheck');
        const bloqueMixta = document.getElementById('bloqueMixta');
        const bloqueTercera = document.getElementById('bloqueTercera');
        
        // Referencias a errores
        const errorComision1 = document.getElementById('errorComision1');
        const errorComision2 = document.getElementById('errorComision2');
        const errorComision3 = document.getElementById('errorComision3');


        document.addEventListener("DOMContentLoaded", () => {
            fetchComisiones();
            // Solo establece defaults si NO estamos editando
            if (!isEditMode) {
                setDefaultsFromNow();
            }
            setupDateTimeLogic();
            // Llama a la lógica inicial para edición o creación
            toggleComisionMixtaBlock(); 
            if (isEditMode) {
                toggleComisionMixta();
                toggleTerceraComision();
            }
        });

        function fetchComisiones() {
            fetch("/corevota/controllers/fetch_data.php?action=comisiones")
                .then(res => res.ok ? res.json() : Promise.reject(res))
                .then(response => {
                    if (response.status === 'success' && Array.isArray(response.data)) {
                        comisionesData = response.data;
                        populateSelect(com1, comisionesData, "Seleccione la comisión principal...");
                        populateSelect(com2, comisionesData, "Seleccione la segunda comisión...");
                        populateSelect(com3, comisionesData, "Seleccione si aplica...");

                        // --- Lógica de Edición ---
                        if (isEditMode) {
                            com1.value = editData.t_comision_idComision || "";
                            com2.value = editData.t_comision_idComision_mixta || "";
                            com3.value = editData.t_comision_idComision_mixta2 || "";
                            validateComisiones(); // Valida al cargar en edición
                        }
                    } else handleFetchError([com1, com2, com3]);
                })
                .catch(err => handleFetchError([com1, com2, com3]));
        }

        function handleFetchError(selects) {
            selects.forEach(s => {
                if (s) s.innerHTML = '<option value="">Error al cargar</option>';
            });
        }

        function populateSelect(selectElement, data, placeholder) {
            selectElement.innerHTML = `<option value="">${placeholder}</option>`;
            data.forEach(comision => {
                selectElement.innerHTML += `
            <option value="${comision.idComision}">
                ${comision.nombreComision}
            </option>`;
            });
        }
        
        // Función UX: Muestra el Checkbox de Mixta solo después de elegir Comisión 1
        function toggleComisionMixtaBlock() {
            if (com1.value) {
                opcionMixtaBlock.style.display = 'block';
                setTimeout(() => opcionMixtaBlock.classList.add('show'), 10);
            } else {
                opcionMixtaBlock.classList.remove('show');
                bloqueMixta.classList.remove('show');
                bloqueTercera.classList.remove('show');
                comisionMixtaCheck.checked = false; // Desmarcar si no hay C1
                setTimeout(() => {
                    opcionMixtaBlock.style.display = 'none';
                    bloqueMixta.style.display = 'none';
                    bloqueTercera.style.display = 'none';
                }, 300);
            }
            validateComisiones(); // Re-validar al cambiar C1
        }

        function toggleComisionMixta() {
            if (comisionMixtaCheck.checked) {
                bloqueMixta.style.display = 'block';
                setTimeout(() => bloqueMixta.classList.add('show'), 10);
                com2.required = true;
            } else {
                bloqueMixta.classList.remove('show');
                bloqueTercera.classList.remove('show'); // Ocultar tercera también
                setTimeout(() => {
                    bloqueMixta.style.display = 'none';
                    bloqueTercera.style.display = 'none';
                }, 300);
                com2.required = false;
                com3.required = false;

                // Limpiar valores solo si no estamos editando (o si se desmarca)
                if (!isEditMode || !comisionMixtaCheck.checked) {
                    com2.value = "";
                    com3.value = "";
                }
            }
            validateComisiones(); // Re-validar al activar/desactivar mixta
        }

        function toggleTerceraComision() {
            if (com2.value) {
                bloqueTercera.style.display = 'block';
                setTimeout(() => bloqueTercera.classList.add('show'), 10);
            } else {
                bloqueTercera.classList.remove('show');
                setTimeout(() => bloqueTercera.style.display = 'none', 300);
                com3.required = false;
                
                if (!isEditMode || !com2.value) {
                    com3.value = "";
                }
            }
        }
        
        // Función de VALIDACIÓN MEJORADA para Comisiones
        function validateComisiones() {
            let isValid = true;
            const val1 = com1.value;
            const val2 = com2.value;
            const val3 = com3.value;
            
            // Limpiar errores previos
            [com1, com2, com3].forEach(c => c.classList.remove('is-invalid'));
            [errorComision1, errorComision2, errorComision3].forEach(e => {
                e.style.display = 'none'; 
                e.textContent = '';
            });

            if (val2 && val2 === val1) {
                showComisionError(com2, errorComision2, 'La Segunda Comisión no puede ser la misma que la Comisión Principal.');
                isValid = false;
            }

            if (val3) {
                if (val3 === val1) {
                    showComisionError(com3, errorComision3, 'La Tercera Comisión no puede ser la misma que la Comisión Principal.');
                    isValid = false;
                }
                if (val3 === val2) {
                    showComisionError(com3, errorComision3, 'La Tercera Comisión debe ser distinta a la Segunda Comisión.');
                    isValid = false;
                }
            }
            
            return isValid;
        }
        
        function showComisionError(input, errorElement, message) {
            input.classList.add('is-invalid');
            errorElement.textContent = message;
            errorElement.style.display = 'block';
        }


        // ====== LÓGICA FECHA/HORA (Mantenida y mejorada la validación) ======
        function formatLocalForInput(dateObj) {
            const pad = (n) => n.toString().padStart(2, '0');
            return `${dateObj.getFullYear()}-${pad(dateObj.getMonth() + 1)}-${pad(dateObj.getDate())}T${pad(dateObj.getHours())}:${pad(dateObj.getMinutes())}`;
        }

        function setDefaultsFromNow() {
            const inicio = document.getElementById('fechaInicioReunion');
            const fin = document.getElementById('fechaTerminoReunion');
            const now = new Date();
            // Redondea al minuto más cercano para evitar segundos
            now.setSeconds(0);
            now.setMilliseconds(0);
            
            const plus1h = new Date(now.getTime() + 60 * 60 * 1000);

            if (!inicio.value) inicio.value = formatLocalForInput(now);
            if (!fin.value) fin.value = formatLocalForInput(plus1h);
        }

        function setupDateTimeLogic() {
            const inicio = document.getElementById('fechaInicioReunion');
            const fin = document.getElementById('fechaTerminoReunion');
            const btn = document.getElementById('btnCopiarMasHora');
            const error = document.getElementById('errorHorario');
            const form = document.getElementById('reunionForm');

            function showErr() {
                error.style.display = 'block';
                fin.classList.add('is-invalid');
            }

            function hideErr() {
                error.style.display = 'none';
                fin.classList.remove('is-invalid');
            }

            function validarHorario() {
                if (!inicio.value || !fin.value) return hideErr();
                const ini = new Date(inicio.value);
                const end = new Date(fin.value);
                if (end <= ini) showErr();
                else hideErr();
                
                return end > ini;
            }

            btn.addEventListener('click', () => {
                if (!inicio.value) {
                    alert('Por favor, defina primero la Fecha y Hora de Inicio.');
                    return;
                }
                const ini = new Date(inicio.value);
                fin.value = formatLocalForInput(new Date(ini.getTime() + 60 * 60 * 1000));
                validarHorario();
            });

            [inicio, fin].forEach(e => e.addEventListener('input', validarHorario));

            form.addEventListener('submit', e => {
                const isHorarioValid = validarHorario();
                const isComisionesValid = validateComisiones();
                
                if (!isHorarioValid || !isComisionesValid) {
                    e.preventDefault();
                    // Opcional: Desplazarse al primer error
                    if (!isComisionesValid) com1.focus();
                    else if (!isHorarioValid) inicio.focus();
                    alert('Por favor, corrija los errores en el formulario antes de guardar.');
                }
            });
        }
    </script>
</body>

</html>