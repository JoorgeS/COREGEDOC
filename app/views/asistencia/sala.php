<?php
// Obtener rol para validaciones de vista
$tipoUsuario = $data['usuario']['rol'] ?? 0;

$c_azul = '#0071bc';
$c_naranja = '#f7931e';
$c_verde = '#00a650';
$c_gris = '#808080';
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    :root {
        --inst-azul: #0071bc;
        --inst-verde: #00a650;
        --inst-naranja: #f7931e;
        --inst-gris: #808080;
        --inst-negro: #000000;
    }

    /* Pestañas Institucionales */
    .nav-tabs-inst {
        border-bottom: 2px solid #e0e0e0;
    }

    .nav-tabs-inst .nav-link {
        color: var(--inst-gris);
        font-weight: 600;
        border: none;
        padding: 12px 20px;
        transition: all 0.3s;
    }

    .nav-tabs-inst .nav-link:hover {
        color: var(--inst-azul);
        background-color: #f8f9fa;
    }

    .nav-tabs-inst .nav-link.active {
        color: var(--inst-azul);
        border-bottom: 3px solid var(--inst-azul);
        background: transparent;
    }

    /* Tarjetas de Estado */
    .card-status {
        border: 1px solid #e0e0e0;
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        overflow: hidden;
        transition: all 0.3s;
    }

    /* Animación Pulso */
    .scanning-pulse {
        width: 80px;
        height: 80px;
        background: rgba(0, 113, 188, 0.1);
        color: var(--inst-azul);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 20px;
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0% {
            box-shadow: 0 0 0 0 rgba(0, 113, 188, 0.4);
        }

        70% {
            box-shadow: 0 0 0 20px rgba(0, 113, 188, 0);
        }

        100% {
            box-shadow: 0 0 0 0 rgba(0, 113, 188, 0);
        }
    }

    /* Botón Acción Principal */
    .btn-action-main {
        background: var(--inst-naranja);
        border: none;
        color: white;
        font-weight: bold;
        text-transform: uppercase;
        padding: 15px 30px;
        border-radius: 50px;
        box-shadow: 0 4px 10px rgba(247, 147, 30, 0.3);
        transition: all 0.3s;
        width: 100%;
    }

    .btn-action-main:hover:not(:disabled) {
        background: #e87b00;
        transform: translateY(-2px);
        box-shadow: 0 6px 15px rgba(247, 147, 30, 0.4);
        color: white;
    }

    .btn-action-main:disabled {
        background: var(--inst-gris);
        cursor: not-allowed;
        opacity: 0.7;
        box-shadow: none;
    }

    /* Botón Volver Institucional */
    .btn-inst-volver {
        color: var(--inst-gris);
        border: 2px solid var(--inst-gris);
        background: transparent;
        border-radius: 50px;
        font-weight: 600;
        padding: 6px 20px;
        font-size: 0.85rem;
        text-decoration: none;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
    }

    .btn-inst-volver:hover {
        background: var(--inst-gris);
        color: white;
        transform: translateX(-3px);
    }

    /* Estados Visuales */
    .status-in-meeting {
        border-left: 6px solid var(--inst-verde);
    }

    .live-indicator {
        display: inline-block;
        width: 10px;
        height: 10px;
        background: red;
        border-radius: 50%;
        animation: blink 1s infinite;
        margin-right: 5px;
    }

    @keyframes blink {
        50% {
            opacity: 0;
        }
    }
</style>

<div class="container-fluid py-5">

    <div class="d-flex justify-content-between align-items-center mb-5">
        <div>
            <h2 class="fw-bold mb-1" style="color: var(--inst-negro);">
                <i class="fas fa-door-open me-2 text-inst-azul"></i> Sala de Reuniones
            </h2>
            <p class="text-muted mb-0">Registro de asistencia y control de sesiones.</p>
        </div>
        <div class="d-flex flex-column align-items-end gap-2">
            <a href="index.php?action=home" class="btn-inst-volver">
                <i class="fas fa-arrow-left me-2"></i> Volver
            </a>
            <span class="badge bg-white text-secondary border px-3 py-2 rounded-pill shadow-sm d-none d-md-block">
                <i class="far fa-calendar-alt me-2 text-inst-naranja"></i> <?php echo date('d/m/Y'); ?>
            </span>
        </div>
    </div>

    <ul class="nav nav-tabs nav-tabs-inst mb-4" id="asistenciaTabs" role="tablist">
        <li class="nav-item"><button class="nav-link active" id="marcar-tab" data-bs-toggle="tab" data-bs-target="#marcar"><i class="fas fa-fingerprint me-2"></i> Autoconsulta</button></li>
        <li class="nav-item"><button class="nav-link" id="historial-tab" data-bs-toggle="tab" data-bs-target="#historial"><i class="fas fa-history me-2"></i> Mi Historial</button></li>
        
        <?php if ($tipoUsuario != 1): ?>
            <li class="nav-item"><button class="nav-link" id="calendario-tab" data-bs-toggle="tab" data-bs-target="#calendario"><i class="far fa-calendar-alt me-2"></i> Calendario</button></li>
        <?php endif; ?>
    </ul>

    <div class="tab-content">

        <div class="tab-pane fade show active" id="marcar">
            <div class="row justify-content-center">
                <div class="col-lg-6 col-md-8">
                    <div id="panel-scan" class="card card-status text-center p-5 bg-white">
                        <div class="card-body">
                            <div class="scanning-pulse"><i class="fa-solid fa-chalkboard-user fa-2x"></i></div>
                            <h4 class="fw-bold mb-2 text-inst-azul">Esperando Inicio de Sesión...</h4>
                            <p class="text-muted">El sistema le notificará automáticamente cuando el Secretario Técnico habilite la reunión.</p>
                            <div class="mt-4 text-muted small">
                                <div class="spinner-border spinner-border-sm me-2"></div> Escaneando...
                            </div>
                        </div>
                    </div>

                    <div id="panel-confirmar" class="card card-status border-0 shadow-lg" style="display: none;">
                        <div class="card-header text-white text-center py-3" style="background-color: var(--inst-azul);">
                            <h5 class="mb-0 fw-bold"><i class="fas fa-check-circle me-2"></i> Sesión Habilitada</h5>
                        </div>
                        <div class="card-body text-center p-5">
                            <p class="text-uppercase text-muted fw-bold small ls-1 mb-2">Bienvenido a la sesión:</p>
                            <h2 id="nombre-reunion" class="fw-bold mb-4 text-inst-negro">...</h2>
                            <div id="alerta-tiempo-ok" class="alert alert-info border-0 bg-opacity-10 bg-info small mb-4">
                                <i class="fas fa-clock me-1"></i> El registro está habilitado (dentro de los 30 min).
                            </div>
                            <div id="alerta-tiempo-out" class="alert alert-danger border-0 bg-opacity-10 bg-danger small mb-4" style="display:none;">
                                <i class="fas fa-exclamation-triangle me-1"></i> <strong>Tiempo Expirado:</strong> Han pasado más de 30 minutos.
                            </div>
                            <div class="d-grid gap-2 col-10 mx-auto">
                                <button id="btn-marcar" class="btn btn-action-main" onclick="marcarPresente()">
                                    <i class="fas fa-user-check me-2"></i> Registrar mi Asistencia
                                </button>
                            </div>
                            <input type="hidden" id="idMinutaActiva"><input type="hidden" id="idReunionActiva">
                        </div>
                    </div>

                    <div id="panel-activo" class="card card-status status-in-meeting p-5 bg-white" style="display: none;">
                        <div class="card-body text-center">
                            <div class="mb-4 text-success"><i class="fas fa-id-badge fa-4x"></i></div>
                            <h2 class="fw-bold mb-2 text-dark">Te encuentras en sesión</h2>
                            <h4 id="nombre-reunion-activo" class="text-secondary mb-4">...</h4>
                            <div class="d-inline-block bg-light border rounded-pill px-4 py-2">
                                <span class="live-indicator"></span> <span class="fw-bold text-danger small">EN CURSO</span>
                            </div>
                            <p class="text-muted mt-4 small">Su asistencia ya fue registrada y confirmada.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="historial">
            <div class="card card-status border-0 shadow-sm">
                <div class="card-body p-4">

                    <div class="mb-4">
                        <div class="d-flex align-items-center mb-3">
                            <h6 class="fw-bold text-secondary text-uppercase mb-0 me-3">
                                <i class="fas fa-filter me-2 text-inst-naranja"></i>Filtros
                            </h6>
                            <div class="flex-grow-1 border-bottom"></div>
                        </div>

                        <form id="formFiltrosHistorial" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-bold text-muted small mb-1">Fecha de Reunión</label>
                                <div class="d-flex align-items-center gap-2">
                                    <input type="date" id="filtroDesde" class="form-control form-control-sm">
                                    <span class="text-muted">-</span>
                                    <input type="date" id="filtroHasta" class="form-control form-control-sm">
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-bold text-muted small mb-1">Filtrar por Comisión</label>
                                <select id="filtroComision" class="form-select form-select-sm">
                                    <option value="">Todas las Comisiones</option>
                                    <?php if (!empty($data['comisiones'])): ?>
                                        <?php foreach ($data['comisiones'] as $c): ?>
                                            <option value="<?= $c['idComision'] ?>"><?= htmlspecialchars($c['nombreComision']) ?></option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-bold text-muted small mb-1">Búsqueda Rápida</label>
                                <div class="d-flex">
                                    <div class="input-group input-group-sm me-2">
                                        <span class="input-group-text bg-white text-muted border-end-0">
                                            <i class="fas fa-search"></i>
                                        </span>
                                        <input type="text" id="filtroKeyword" class="form-control border-start-0 ps-0" placeholder="Nombre reunión, tema...">
                                    </div>
                                    <button type="button" id="btnLimpiarHistorial" class="btn btn-sm btn-outline-secondary px-3" title="Limpiar Filtros">
                                        <i class="fas fa-eraser"></i>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 small" id="tablaHistorial">
                            <thead class="table-light">
                                <tr>
                                    <th width="30%">Nombre Reunión</th>
                                    <th width="25%">Comisión</th>
                                    <th width="15%">Fecha Reunión</th>
                                    <th width="15%">Hora Registro</th>
                                    <th width="15%" class="text-center">Estado</th>
                                </tr>
                            </thead>
                            <tbody id="tbodyHistorial">
                                <tr>
                                    <td colspan="4" class="text-center py-5">
                                        <div class="spinner-border text-primary"></div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top">
                        <small class="text-muted" id="infoPaginacion">Cargando...</small>
                        <nav aria-label="Navegación">
                            <ul class="pagination pagination-sm mb-0 justify-content-end" id="paginacionContainer"></ul>
                        </nav>
                    </div>

                </div>
            </div>
        </div>

        <?php if ($tipoUsuario != 1): ?>
        <div class="tab-pane fade" id="calendario">
            <div class="card card-status border-0 shadow-sm">
                <div class="card-body p-0">
                    <iframe src="index.php?action=reunion_calendario&embedded=true" style="width: 100%; height: 650px; border: none;"></iframe>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
    </div>
</div>

<script>
    // --- LÓGICA DE ESCANEO Y MARCADO (Existente) ---
    let intervalAsistencia;
    let lastState = 'none';

    document.addEventListener("DOMContentLoaded", () => {
        iniciarScanner();

        // Inicializar filtros del historial
        if (document.getElementById('filtroDesde')) {
            initFiltrosHistorial();
        }
    });

    function iniciarScanner() {
        intervalAsistencia = setInterval(buscarReunion, 3000);
        buscarReunion();
    }

    function buscarReunion() {
        const activeTab = document.querySelector('#marcar-tab.active');
        if (!activeTab) return;
        fetch('index.php?action=api_asistencia_check')
            .then(r => r.json())
            .then(resp => {
                procesarEstado(resp);
            })
            .catch(err => console.error("Scanner waiting...", err));
    }

    function procesarEstado(resp) {
        const pScan = document.getElementById('panel-scan');
        const pConf = document.getElementById('panel-confirmar');
        const pActivo = document.getElementById('panel-activo');

        if (resp.status === 'none') {
            pScan.style.display = 'block';
            pConf.style.display = 'none';
            pActivo.style.display = 'none';
            lastState = 'none';
            return;
        }

        if (resp.status === 'active') {
            const data = resp.data;
            if (data.ya_marco) {
                pScan.style.display = 'none';
                pConf.style.display = 'none';
                pActivo.style.display = 'block';
                document.getElementById('nombre-reunion-activo').textContent = data.nombreReunion;
                lastState = 'joined';
                return;
            }

            pScan.style.display = 'none';
            pConf.style.display = 'block';
            pActivo.style.display = 'none';
            document.getElementById('idMinutaActiva').value = data.t_minuta_idMinuta;
            document.getElementById('idReunionActiva').value = data.idReunion;
            document.getElementById('nombre-reunion').textContent = data.nombreReunion;

            if (lastState === 'none') {
                Swal.fire({
                    icon: 'info',
                    title: '¡Sesión Habilitada!',
                    text: 'El Secretario Técnico ha iniciado: ' + data.nombreReunion,
                    confirmButtonColor: '#0071bc',
                    confirmButtonText: 'Entendido'
                });
                if (navigator.vibrate) navigator.vibrate([200, 100, 200]);
            }

            const minutos = parseInt(data.minutosTranscurridos);
            const btn = document.getElementById('btn-marcar');
            const alertOk = document.getElementById('alerta-tiempo-ok');
            const alertOut = document.getElementById('alerta-tiempo-out');

            if (minutos > 30) {
                btn.disabled = true;
                btn.classList.remove('btn-action-main');
                btn.classList.add('btn', 'btn-secondary');
                btn.innerHTML = '<i class="fas fa-lock me-2"></i> Registro Cerrado';
                alertOk.style.display = 'none';
                alertOut.style.display = 'block';
            } else {
                btn.disabled = false;
                if (btn.classList.contains('btn-secondary')) {
                    btn.classList.remove('btn', 'btn-secondary');
                    btn.classList.add('btn-action-main');
                    btn.innerHTML = '<i class="fas fa-user-check me-2"></i> Registrar mi Asistencia';
                }
                alertOk.innerHTML = `<i class="fas fa-clock me-1"></i> Puedes registrar tu asistencia hasta las: <strong>${data.horaLimite} hrs</strong>.`;
                alertOk.style.display = 'block';
                alertOut.style.display = 'none';
            }
            lastState = 'active';
        }
    }

    function marcarPresente() {
        const idMinuta = document.getElementById('idMinutaActiva').value;
        const idReunion = document.getElementById('idReunionActiva').value;
        const btn = document.getElementById('btn-marcar');

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Conectando...';

        fetch('index.php?action=api_asistencia_marcar', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    idMinuta,
                    idReunion
                })
            })
            .then(r => r.json())
            .then(d => {
                if (d.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Asistencia Registrada!',
                        text: 'Se ha confirmado su presencia.',
                        confirmButtonColor: '#00a650',
                        timer: 3000,
                        timerProgressBar: true
                    }).then(() => {
                        buscarReunion();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: d.message,
                        confirmButtonColor: '#d33'
                    });
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-user-check me-2"></i> Registrar mi Asistencia';
                }
            })
            .catch(e => {
                console.error(e);
                Swal.fire('Error', 'Problema de conexión.', 'error');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-user-check me-2"></i> Registrar mi Asistencia';
            });
    }

    // --- NUEVA LÓGICA DE HISTORIAL (FILTROS Y PAGINACIÓN) ---

    function initFiltrosHistorial() {
        const hoy = new Date();
        const primerDia = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
        const formatDate = d => d.toISOString().split('T')[0];

        const inputDesde = document.getElementById('filtroDesde');
        const inputHasta = document.getElementById('filtroHasta');
        const inputComision = document.getElementById('filtroComision');
        const inputKeyword = document.getElementById('filtroKeyword');

        // Setear fechas por defecto
        if (!inputDesde.value) inputDesde.value = formatDate(primerDia);
        if (!inputHasta.value) inputHasta.value = formatDate(hoy);

        let debounceTimer;

        // Event Listeners
        inputDesde.addEventListener('change', () => cargarHistorial(1));
        inputHasta.addEventListener('change', () => cargarHistorial(1));
        inputComision.addEventListener('change', () => cargarHistorial(1));

        inputKeyword.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => cargarHistorial(1), 500);
        });

        document.getElementById('btnLimpiarHistorial').addEventListener('click', () => {
            inputDesde.value = formatDate(primerDia);
            inputHasta.value = formatDate(hoy);
            inputComision.value = "";
            inputKeyword.value = "";
            cargarHistorial(1);
        });

        // Cargar primera página
        cargarHistorial(1);
    }

    function cargarHistorial(page) {
        const tbody = document.getElementById('tbodyHistorial');
        tbody.style.opacity = '0.5';

        const params = new URLSearchParams({
            action: 'api_historial_asistencia', 
            page: page,
            limit: 10,
            desde: document.getElementById('filtroDesde').value,
            hasta: document.getElementById('filtroHasta').value,
            comision: document.getElementById('filtroComision').value,
            q: document.getElementById('filtroKeyword').value
        });

        fetch('index.php?' + params.toString())
            .then(r => r.json())
            .then(data => {
                renderTablaHistorial(data.data);
                renderPaginacion(data.total, data.totalPages, page);
                tbody.style.opacity = '1';
            })
            .catch(err => {
                console.error(err);
                tbody.innerHTML = '<tr><td colspan="4" class="text-center text-danger">Error al cargar historial</td></tr>';
                tbody.style.opacity = '1';
            });
    }
function renderTablaHistorial(registros) {
        const tbody = document.getElementById('tbodyHistorial');
        tbody.innerHTML = '';

        if (!registros || registros.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">No se encontraron registros en este periodo.</td></tr>'; // Nota: cambié colspan a 5
            return;
        }

        registros.forEach(r => {
            // Formatear Fecha
            const fechaObj = new Date(r.fechaInicioReunion);
            const fechaStr = fechaObj.toLocaleDateString('es-CL');
            
            // Formatear Hora Registro
            const horaReg = r.fechaRegistroAsistencia ? r.fechaRegistroAsistencia.split(' ')[1].substring(0,5) : '--:--';

            // Estado
            const esPresente = r.estadoAsistencia === 'PRESENTE';
            const badgeClass = esPresente ? 'bg-success' : 'bg-secondary';
            const estadoTexto = esPresente ? 'Presente' : 'Ausente';

            // Determinar nombre comisión (si viene vacío, poner General o guión)
            const nombreComision = r.nombreComision || 'Sesión General';

            const tr = `
                <tr>
                    <td class="fw-bold text-dark text-truncate" style="max-width: 200px;" title="${r.nombreReunion}">
                        ${r.nombreReunion}
                    </td>
                    <td class="text-truncate" style="max-width: 180px;" title="${nombreComision}">
                        <span class="badge bg-light text-dark border fw-normal">
                            ${nombreComision}
                        </span>
                    </td>
                    <td>${fechaStr}</td>
                    <td><i class="far fa-clock me-1 text-muted"></i> ${horaReg}</td>
                    <td class="text-center">
                        <span class="badge ${badgeClass} border border-light shadow-sm px-3">
                            ${estadoTexto}
                        </span>
                    </td>
                </tr>
            `;
            tbody.innerHTML += tr;
        });
    }

    function renderPaginacion(total, totalPages, current) {
        document.getElementById('infoPaginacion').innerText = `Total: ${total} registros`;
        const container = document.getElementById('paginacionContainer');
        container.innerHTML = '';

        if (totalPages <= 1) return;

        // Botón Anterior
        container.innerHTML += `
            <li class="page-item ${current === 1 ? 'disabled' : ''}">
                <button class="page-link" onclick="cargarHistorial(${current - 1})">&laquo;</button>
            </li>
        `;

        // Números de página
        let startPage = Math.max(1, current - 2);
        let endPage = Math.min(totalPages, current + 2);

        for (let i = startPage; i <= endPage; i++) {
            container.innerHTML += `
                <li class="page-item ${i === current ? 'active' : ''}">
                    <button class="page-link" onclick="cargarHistorial(${i})">${i}</button>
                </li>
            `;
        }

        // Botón Siguiente
        container.innerHTML += `
            <li class="page-item ${current === totalPages ? 'disabled' : ''}">
                <button class="page-link" onclick="cargarHistorial(${current + 1})">&raquo;</button>
            </li>
        `;
    }
</script>