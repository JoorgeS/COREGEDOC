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

        .hidden-block { display: none; transition: opacity 0.3s ease-in-out; opacity: 0; }
        .hidden-block.show { display: block; opacity: 1; }

        /* Bloque bonito */
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
            font-size: .9rem;
            font-weight: 600;
            color: #1f2d3d;
            display: flex;
            align-items: center;
            gap: .5rem;
        }
        .section-title i { color: #0d6efd; }

        .section-desc {
            font-size: .8rem;
            color: #6b727d;
            line-height: 1.3;
            margin-top: .25rem;
        }

        .asterisk { color: #dc3545; font-weight: 600; }

        .time-row { display: flex; flex-wrap: wrap; gap: 1rem; }
        .time-col { flex: 1 1 300px; min-width: 260px; }

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

        .invalid-horario {
            color: #dc3545;
            font-size: .8rem;
            font-weight: 500;
            display: none;
            margin-top: .4rem;
        }

        .is-invalid {
            border-color: #dc3545 !important;
            box-shadow: 0 0 0 .2rem rgba(220,53,69,.1);
        }
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
                        <label for="comisionSelect1" class="form-label">
                            Comisión Principal <span class="text-danger">*</span>
                        </label>
                        <select class="form-select form-select-md rounded-3" id="comisionSelect1" name="t_comision_idComision" required>
                            <option value="">Cargando comisiones...</option>
                        </select>
                        <div class="hint text-muted small mt-1">
                            Esta será la comisión responsable de la sesión.
                        </div>
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
                            <label for="comisionSelect2" class="form-label">
                                Segunda Comisión <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="comisionSelect2" name="t_comision_idComision_mixta" onchange="toggleTerceraComision()">
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
                        <label for="nombreReunion" class="form-label">
                            Nombre de la Reunión <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" id="nombreReunion" name="nombreReunion" required>
                    </div>

                    <!-- BLOQUE MEJORADO FECHA/HORA -->
                    <div class="pretty-block">
                        <div class="section-header-inline">
                            <div>
                                <div class="section-title">
                                    <i class="fa-solid fa-clock"></i>
                                    <span>Duración de la Reunión</span>
                                </div>
                                <div class="section-desc">
                                    Se propone la hora actual como inicio y +1 hora como término. Puedes ajustar.
                                </div>
                            </div>
                        </div>

                        <div class="time-row">
                            <!-- Inicio -->
                            <div class="time-col">
                                <label for="fechaInicioReunion" class="form-label">
                                    Fecha y Hora de Inicio <span class="asterisk">*</span>
                                </label>
                                <input type="datetime-local" class="form-control" id="fechaInicioReunion" name="fechaInicioReunion" required>
                                <div class="hint">Momento en que comienza la sesión.</div>
                            </div>

                            <!-- Término -->
                            <div class="time-col">
                                <label for="fechaTerminoReunion" class="form-label">
                                    Fecha y Hora de Término <span class="asterisk">*</span>
                                </label>
                                <div class="input-group">
                                    <input type="datetime-local" class="form-control" id="fechaTerminoReunion" name="fechaTerminoReunion" required>
                                    <button class="btn btn-outline-secondary btn-plus" type="button" id="btnCopiarMasHora">
                                        +1 hr
                                    </button>
                                </div>
                                <div class="hint">Hora estimada de término. Usa <b>+1 hr</b> para autocompletar.</div>
                                <div id="errorHorario" class="invalid-horario">
                                    La hora de término debe ser posterior al inicio.
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- FIN BLOQUE MEJORADO -->

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
let comisionesData = [];

document.addEventListener("DOMContentLoaded", () => {
    fetchComisiones();
    setDefaultsFromNow();
    setupDateTimeLogic();
});

function fetchComisiones() {
    const com1 = document.getElementById('comisionSelect1');
    const com2 = document.getElementById('comisionSelect2');
    const com3 = document.getElementById('comisionSelect3');

    fetch("/corevota/controllers/fetch_data.php?action=comisiones")
        .then(res => res.ok ? res.json() : Promise.reject(res))
        .then(response => {
            if (response.status === 'success' && Array.isArray(response.data)) {
                comisionesData = response.data;
                populateSelect(com1, comisionesData, "Seleccione la comisión principal...");
                populateSelect(com2, comisionesData, "Seleccione la segunda comisión...");
                populateSelect(com3, comisionesData, "Seleccione si aplica...");
            } else handleFetchError([com1, com2, com3]);
        })
        .catch(err => handleFetchError([com1, com2, com3]));
}

function handleFetchError(selects) {
    selects.forEach(s => { if (s) s.innerHTML = '<option value="">Error al cargar</option>'; });
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

function toggleComisionMixta() {
    const check = document.getElementById('comisionMixtaCheck');
    const bloqueMixta = document.getElementById('bloqueMixta');
    const select2 = document.getElementById('comisionSelect2');
    const bloqueTercera = document.getElementById('bloqueTercera');
    const select3 = document.getElementById('comisionSelect3');

    if (check.checked) {
        bloqueMixta.style.display = 'block';
        setTimeout(() => bloqueMixta.classList.add('show'), 10);
        select2.required = true;
        bloqueTercera.classList.remove('show');
        bloqueTercera.style.display = 'none';
        select3.required = false;
    } else {
        bloqueMixta.classList.remove('show');
        setTimeout(() => {
            bloqueMixta.style.display = 'none';
            bloqueTercera.style.display = 'none';
        }, 300);
        select2.required = false;
        select3.required = false;
        select2.value = "";
        select3.value = "";
    }
}

function toggleTerceraComision() {
    const select2 = document.getElementById('comisionSelect2');
    const bloqueTercera = document.getElementById('bloqueTercera');
    const select3 = document.getElementById('comisionSelect3');

    if (select2.value) {
        bloqueTercera.style.display = 'block';
        setTimeout(() => bloqueTercera.classList.add('show'), 10);
    } else {
        bloqueTercera.classList.remove('show');
        setTimeout(() => bloqueTercera.style.display = 'none', 300);
        select3.value = "";
    }
}

// ====== NUEVA LÓGICA FECHA/HORA ======
function formatLocalForInput(dateObj) {
    const pad = (n) => n.toString().padStart(2, '0');
    return `${dateObj.getFullYear()}-${pad(dateObj.getMonth() + 1)}-${pad(dateObj.getDate())}T${pad(dateObj.getHours())}:${pad(dateObj.getMinutes())}`;
}

function setDefaultsFromNow() {
    const inicio = document.getElementById('fechaInicioReunion');
    const fin = document.getElementById('fechaTerminoReunion');
    const now = new Date();
    const plus1h = new Date(now.getTime() + 60 * 60 * 1000);
    inicio.value = formatLocalForInput(now);
    fin.value = formatLocalForInput(plus1h);
}

function setupDateTimeLogic() {
    const inicio = document.getElementById('fechaInicioReunion');
    const fin = document.getElementById('fechaTerminoReunion');
    const btn = document.getElementById('btnCopiarMasHora');
    const error = document.getElementById('errorHorario');
    const form = document.getElementById('reunionForm');

    function showErr() { error.style.display = 'block'; fin.classList.add('is-invalid'); }
    function hideErr() { error.style.display = 'none'; fin.classList.remove('is-invalid'); }

    function validar() {
        if (!inicio.value || !fin.value) return hideErr();
        const ini = new Date(inicio.value);
        const end = new Date(fin.value);
        if (end <= ini) showErr(); else hideErr();
    }

    btn.addEventListener('click', () => {
        if (!inicio.value) return;
        const ini = new Date(inicio.value);
        fin.value = formatLocalForInput(new Date(ini.getTime() + 60 * 60 * 1000));
        validar();
    });

    [inicio, fin].forEach(e => e.addEventListener('input', validar));

    form.addEventListener('submit', e => {
        const ini = new Date(inicio.value);
        const end = new Date(fin.value);
        if (end <= ini) {
            e.preventDefault();
            showErr();
        }
    });
}
</script>
</body>
</html>
