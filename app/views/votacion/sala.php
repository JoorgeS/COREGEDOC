<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    :root {
        --inst-azul: #0071bc;
        --inst-verde: #00a650;
        --inst-naranja: #f7931e;
        --inst-gris: #808080;
        --bg-light: #f8f9fa;
    }

    /* Estilos Generales */
    .nav-tabs-inst { border-bottom: 2px solid #e0e0e0; }
    .nav-tabs-inst .nav-link { color: var(--inst-gris); font-weight: 600; border: none; padding: 12px 20px; transition: all 0.3s; }
    .nav-tabs-inst .nav-link:hover { color: var(--inst-azul); background-color: #f8f9fa; }
    .nav-tabs-inst .nav-link.active { color: var(--inst-azul); border-bottom: 3px solid var(--inst-azul); background: transparent; }

    .card-status { border: none; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); background: white; }
    
    /* Filtros estilo imagen adjunta */
    .filter-label { font-size: 0.8rem; font-weight: 700; color: #6c757d; text-transform: uppercase; margin-bottom: 5px; }
    .filter-container { background-color: #fff; padding: 20px; border-radius: 8px; border: 1px solid #e0e0e0; margin-bottom: 20px; }
    
    /* Tabla */
    .table-custom thead th { background-color: #f8f9fa; color: #495057; font-weight: 600; border-bottom: 2px solid #dee2e6; font-size: 0.9rem; }
    .table-custom tbody td { font-size: 0.9rem; vertical-align: middle; }
    
    /* Badges de Resultado */
    .badge-aprobada { background-color: #d1e7dd; color: #0f5132; border: 1px solid #badbcc; }
    .badge-rechazada { background-color: #f8d7da; color: #842029; border: 1px solid #f5c2c7; }
    .badge-empate { background-color: #fff3cd; color: #664d03; border: 1px solid #ffecb5; }

    /* Animaciones */
    .scanning-pulse { width: 80px; height: 80px; background: rgba(0, 113, 188, 0.1); color: var(--inst-azul); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; animation: pulse 2s infinite; }
    @keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(0, 113, 188, 0.4); } 70% { box-shadow: 0 0 0 20px rgba(0, 113, 188, 0); } 100% { box-shadow: 0 0 0 0 rgba(0, 113, 188, 0); } }
</style>

<div class="container-fluid py-5 bg-light" style="min-height: 100vh;">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1" style="color: #000;">
                <i class="fas fa-vote-yea me-2 text-primary"></i> Sala de Votaciones
            </h2>
            <p class="text-muted mb-0">Gestión y visualización de procesos de votación.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="index.php?action=home" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                <i class="fas fa-arrow-left me-2"></i> Volver
            </a>
            <span class="badge bg-white text-secondary border px-3 py-2 rounded-pill shadow-sm">
                <i class="far fa-calendar-alt me-2 text-warning"></i> <?php echo date('d/m/Y'); ?>
            </span>
        </div>
    </div>

    <ul class="nav nav-tabs nav-tabs-inst mb-4" id="votacionTabs" role="tablist">
        <li class="nav-item">
            <button class="nav-link active" id="voto-tab" data-bs-toggle="tab" data-bs-target="#voto" type="button"><i class="fas fa-box-open me-2"></i> Votación en Curso</button>
        </li>

        <li class="nav-item">
            <button class="nav-link" id="resultados-tab" data-bs-toggle="tab" data-bs-target="#resultados" type="button"><i class="fas fa-table me-2"></i> Resultados Globales</button>
        </li>
    </ul>

    <div class="tab-content">

        <div class="tab-pane fade show active" id="voto">
            <div class="row justify-content-center">
                <div class="col-lg-6 col-md-8">
                    <div id="panel-espera" class="card card-status text-center p-5">
                        <div class="card-body">
                            <div class="scanning-pulse mb-3">
                                <i class="fa-solid fa-person-booth fa-2x" style="color: var(--inst-azul);"></i>
                            </div>
                            <h4 class="fw-bold mb-2 text-primary">Esperando Votación...</h4>
                            <p class="text-muted">La pantalla se actualizará automáticamente.</p>
                            <div class="mt-4 text-muted small">
                                <div class="spinner-border spinner-border-sm me-2"></div> Sincronizando...
                            </div>
                        </div>
                    </div>

                    <div id="panel-votacion" class="card card-status border-primary shadow-lg" style="display: none;">
                        <div class="card-header bg-primary text-white text-center py-3">
                            <h5 class="mb-0 text-uppercase ls-1">Votación Abierta</h5>
                        </div>
                        <div class="card-body text-center p-5">
                            <h6 class="text-muted text-uppercase small">Tema:</h6>
                            <h3 id="titulo-votacion" class="fw-bold mb-5 text-dark">...</h3>
                            <div class="d-grid gap-3">
                                <button class="btn btn-outline-success btn-lg py-3 fw-bold" onclick="confirmarVoto('SI')"><i class="fas fa-check me-2"></i> SÍ</button>
                                <button class="btn btn-outline-danger btn-lg py-3 fw-bold" onclick="confirmarVoto('NO')"><i class="fas fa-times me-2"></i> NO</button>
                                <button class="btn btn-outline-secondary btn-lg py-3 fw-bold" onclick="confirmarVoto('ABSTENCION')"><i class="fas fa-minus-circle me-2"></i> ABSTENCIÓN</button>
                            </div>
                            <input type="hidden" id="idVotacionActiva">
                        </div>
                    </div>

                    <div id="panel-ya-voto" class="card card-status text-center p-5" style="display: none;">
                        <div class="card-body">
                            <div id="icono-voto-previo" class="mb-3 display-4"></div>
                            <h4 class="card-title fw-bold text-muted">Voto Registrado</h4>
                            <hr>
                            <p class="mb-2">Usted ha votado:</p>
                            <h2 id="texto-voto-previo" class="fw-bold mb-3">...</h2>
                            <p id="mensaje-detalle-voto" class="text-secondary small mb-0"></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>



        <div class="tab-pane fade" id="resultados">
            
            <div class="filter-container shadow-sm">
                <div class="row align-items-center mb-2">
                    <div class="col-12">
                        <h6 class="text-secondary fw-bold"><i class="fas fa-filter me-2"></i>FILTROS</h6>
                        <hr class="mt-1 mb-3">
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-md-3">
                        <div class="filter-label">Fecha de Creación</div>
                        <div class="input-group input-group-sm">
                            <input type="date" id="globalDesde" class="form-control">
                            <span class="input-group-text bg-light">-</span>
                            <input type="date" id="globalHasta" class="form-control">
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="filter-label">Filtrar por Comisión</div>
                        <select id="globalComision" class="form-select form-select-sm">
                            <option value="">Todas las Comisiones</option>
                            <?php if(!empty($data['comisiones'])): ?>
                                <?php foreach ($data['comisiones'] as $c): ?>
                                    <option value="<?= $c['idComision'] ?>"><?= htmlspecialchars($c['nombreComision']) ?></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <div class="filter-label">Búsqueda Rápida</div>
                        <div class="d-flex gap-2">
                            <div class="input-group input-group-sm flex-grow-1">
                                <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                                <input type="text" id="globalSearch" class="form-control border-start-0" placeholder="Escriba para buscar (votación, reunión, objetivo)...">
                            </div>
                            <button class="btn btn-outline-secondary btn-sm" id="btnLimpiarGlobal" title="Limpiar Filtros">
                                <i class="fas fa-eraser"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card card-status">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-custom mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th style="width: 25%;">Nombre Votación</th>
                                    <th style="width: 20%;">Reunión</th>
                                    <th style="width: 15%;">Comisión</th>
                                    <th style="width: 10%;" class="text-center">Fecha</th>
                                    <th style="width: 5%;" class="text-center text-success">SI</th>
                                    <th style="width: 5%;" class="text-center text-danger">NO</th>
                                    <th style="width: 5%;" class="text-center text-secondary">ABS</th>
                                    <th style="width: 15%;" class="text-center">Resultado</th>
                                </tr>
                            </thead>
                            <tbody id="tbodyGlobal">
                                </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-white border-top-0 d-flex justify-content-between align-items-center py-3">
                    <small class="text-muted" id="infoPaginacionGlobal">Mostrando 0 registros</small>
                    <nav aria-label="Navegación">
                        <ul class="pagination pagination-sm mb-0" id="paginacionGlobalContainer">
                            </ul>
                    </nav>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
    // --- LÓGICA EXISTENTE (Votación en vivo, etc.) ---
    let pollingInterval;
    let yaVotoLocalmente = false;

    document.addEventListener("DOMContentLoaded", () => { 
        iniciarPolling(); 

        // Inicializar Historial Global (NUEVO)
        if(document.getElementById('globalSearch')) { initResultadosGlobales(); }
    });

    // ... (Mantener funciones de polling: verificarVotacion, mostrarVotacion, etc. sin cambios) ...
    // ... (Copiar tus funciones de polling aquí o mantenerlas si ya están en el archivo) ...
    
    function confirmarVoto(intencion) {
        // Lógica existente de SweetAlert y fetch a api_voto_emitir
        let colorBtn = '#6c757d';
        let textoPregunta = '';
        let valorParaBD = '';

        if (intencion === 'SI') {
            colorBtn = '#198754'; textoPregunta = 'SÍ'; valorParaBD = 'APRUEBO';
        } else if (intencion === 'NO') {
            colorBtn = '#dc3545'; textoPregunta = 'NO'; valorParaBD = 'RECHAZO';
        } else {
            colorBtn = '#ffc107'; textoPregunta = 'ABSTENCIÓN'; valorParaBD = 'ABSTENCION';
        }

        Swal.fire({
            title: '¿Confirmar Voto?',
            text: `Su voto será: ${textoPregunta}`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: colorBtn,
            confirmButtonText: `VOTAR ${textoPregunta}`,
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                enviarVotoAlServidor(valorParaBD, intencion);
            }
        });
    }

    function enviarVotoAlServidor(valorBD, valorVisual) {
        const idVotacion = document.getElementById('idVotacionActiva').value;
        const nombreVotacion = document.getElementById('titulo-votacion').textContent;
        const botones = document.querySelectorAll('#panel-votacion button');
        botones.forEach(b => b.disabled = true);

        fetch('index.php?action=api_voto_emitir', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ idVotacion: idVotacion, opcion: valorBD })
        })
        .then(r => r.json())
        .then(d => {
            if (d.status === 'success') {
                yaVotoLocalmente = true;
                Swal.fire({ title: '¡Voto Registrado!', icon: 'success', timer: 1500, showConfirmButton: false });
                renderizarVistaPrevia(valorVisual, nombreVotacion);
                // Si estoy en la pestaña de globales, actualizarla también
                if(document.getElementById('resultados-tab').classList.contains('active')){
                    cargarGlobales(1);
                }
            } else {
                Swal.fire('Error', d.message, 'error');
                botones.forEach(b => b.disabled = false);
            }
        })
        .catch(err => { console.error(err); });
    }

    function renderizarVistaPrevia(valorEntrada, nombreVotacion) {
        document.getElementById('panel-espera').style.display = 'none';
        document.getElementById('panel-votacion').style.display = 'none';
        const panel = document.getElementById('panel-ya-voto');
        const iconoDiv = document.getElementById('icono-voto-previo');
        const textoDiv = document.getElementById('texto-voto-previo');
        
        const op = valorEntrada ? valorEntrada.toString().toUpperCase().trim() : '';
        if (['SI', 'APRUEBO', 'SÍ'].includes(op)) {
            iconoDiv.innerHTML = '<i class="fas fa-check-circle text-success"></i>';
            textoDiv.className = 'fw-bold mb-3 text-success';
            textoDiv.textContent = 'SÍ';
        } else if (['NO', 'RECHAZO'].includes(op)) {
            iconoDiv.innerHTML = '<i class="fas fa-times-circle text-danger"></i>';
            textoDiv.className = 'fw-bold mb-3 text-danger';
            textoDiv.textContent = 'NO';
        } else {
            iconoDiv.innerHTML = '<i class="fas fa-minus-circle text-warning"></i>';
            textoDiv.className = 'fw-bold mb-3 text-warning';
            textoDiv.textContent = 'ABSTENCIÓN';
        }
        document.getElementById('mensaje-detalle-voto').textContent = nombreVotacion ? `Votación: "${nombreVotacion}"` : '';
        panel.style.display = 'block';
    }

    function iniciarPolling() { pollingInterval = setInterval(verificarVotacion, 2000); }
    function verificarVotacion() {
        if (!document.getElementById('voto').classList.contains('active')) return;
        fetch('index.php?action=api_voto_check')
            .then(r => r.ok ? r.json() : Promise.reject(r))
            .then(resp => {
                if (resp.status === 'active') {
                    const data = resp.data;
                    if (data.ya_voto === true) {
                        renderizarVistaPrevia(data.opcion_registrada, data.nombreVotacion);
                    } else {
                        mostrarVotacion(data);
                    }
                } else {
                    mostrarEspera();
                }
            })
            .catch(err => console.error(err));
    }
    
    function mostrarVotacion(data) {
        const elTitulo = document.getElementById('titulo-votacion');
        const elInput = document.getElementById('idVotacionActiva');
        const elPanel = document.getElementById('panel-votacion');
        if (!elTitulo || !elInput || !elPanel) return;
        if (elPanel.style.display === 'block' && elInput.value == data.idVotacion) return;
        
        document.getElementById('panel-espera').style.display = 'none';
        document.getElementById('panel-ya-voto').style.display = 'none';
        
        yaVotoLocalmente = false;
        elInput.value = data.idVotacion;
        elTitulo.textContent = data.nombreVotacion;
        const botones = elPanel.querySelectorAll('button');
        botones.forEach(btn => { btn.disabled = false; });
        elPanel.style.display = 'block';
    }

    function mostrarEspera() {
        if (document.getElementById('panel-espera').style.display === 'block') return;
        document.getElementById('panel-votacion').style.display = 'none';
        document.getElementById('panel-ya-voto').style.display = 'none';
        document.getElementById('panel-espera').style.display = 'block';
        const elInput = document.getElementById('idVotacionActiva');
        if (elInput) elInput.value = '';
    }

    // =========================================================
    // NUEVA LÓGICA: RESULTADOS GLOBALES (TABLA HISTÓRICA)
    // =========================================================

    function initResultadosGlobales() {
        const inputDesde = document.getElementById('globalDesde');
        const inputHasta = document.getElementById('globalHasta');
        const inputComision = document.getElementById('globalComision');
        const inputSearch = document.getElementById('globalSearch');
        const btnLimpiar = document.getElementById('btnLimpiarGlobal');

        // Fechas por defecto (Mes actual)
        const date = new Date();
        const firstDay = new Date(date.getFullYear(), date.getMonth(), 1).toISOString().split('T')[0];
        const lastDay = new Date(date.getFullYear(), date.getMonth() + 1, 0).toISOString().split('T')[0];
        
        if (!inputDesde.value) inputDesde.value = firstDay;
        if (!inputHasta.value) inputHasta.value = lastDay;

        // Eventos
        let debounce;
        inputSearch.addEventListener('input', () => {
            clearTimeout(debounce);
            debounce = setTimeout(() => cargarGlobales(1), 500);
        });

        inputDesde.addEventListener('change', () => cargarGlobales(1));
        inputHasta.addEventListener('change', () => cargarGlobales(1));
        inputComision.addEventListener('change', () => cargarGlobales(1));

        btnLimpiar.addEventListener('click', () => {
            inputDesde.value = '';
            inputHasta.value = '';
            inputComision.value = '';
            inputSearch.value = '';
            cargarGlobales(1);
        });

        // Cargar primera vez
        cargarGlobales(1);
    }

    function cargarGlobales(page) {
        const tbody = document.getElementById('tbodyGlobal');
        tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4"><div class="spinner-border text-primary spinner-border-sm"></div> Cargando datos...</td></tr>';

        const params = new URLSearchParams({
            action: 'api_historial_global', // Endpoint nuevo que debes crear o mapear
            page: page,
            limit: 10,
            desde: document.getElementById('globalDesde').value,
            hasta: document.getElementById('globalHasta').value,
            comision: document.getElementById('globalComision').value,
            q: document.getElementById('globalSearch').value
        });

        fetch('index.php?' + params.toString())
            .then(r => r.json())
            .then(data => {
                renderTablaGlobal(data.data);
                renderPaginacionGlobal(data.total, data.totalPages, page);
            })
            .catch(err => {
                console.error(err);
                tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger py-4">Error al cargar datos.</td></tr>';
            });
    }

    function renderTablaGlobal(registros) {
        const tbody = document.getElementById('tbodyGlobal');
        tbody.innerHTML = '';

        if (!registros || registros.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center py-5 text-muted"><i class="fas fa-search me-2"></i>No se encontraron votaciones con estos filtros.</td></tr>';
            return;
        }

        registros.forEach(r => {
            const fechaObj = new Date(r.fechaCreacion);
            const fechaStr = fechaObj.toLocaleDateString('es-CL');
            
            // Lógica de badge de resultado
            let badgeClass = 'badge-empate';
            let icon = 'fa-minus-circle';
            
            if (r.resultado_final === 'APROBADA') {
                badgeClass = 'badge-aprobada';
                icon = 'fa-check-circle';
            } else if (r.resultado_final === 'RECHAZADA') {
                badgeClass = 'badge-rechazada';
                icon = 'fa-times-circle';
            }

            const tr = `
                <tr>
                    <td class="fw-bold text-dark text-wrap" style="max-width: 200px;">${r.nombreVotacion}</td>
                    <td class="small text-muted text-wrap" style="max-width: 150px;">${r.nombreReunion || '-'}</td>
                    <td class="small text-wrap" style="max-width: 150px;">${r.nombreComision || 'Sin Comisión'}</td>
                    <td class="text-center small">${fechaStr}</td>
                    <td class="text-center fw-bold text-success">${r.votos_si}</td>
                    <td class="text-center fw-bold text-danger">${r.votos_no}</td>
                    <td class="text-center fw-bold text-secondary">${r.votos_abs}</td>
                    <td class="text-center">
                        <span class="badge ${badgeClass} px-2 py-1">
                            <i class="fas ${icon} me-1"></i> ${r.resultado_final}
                        </span>
                    </td>
                </tr>
            `;
            tbody.innerHTML += tr;
        });
    }

    function renderPaginacionGlobal(total, totalPages, current) {
        document.getElementById('infoPaginacionGlobal').innerText = `Total: ${total} votaciones`;
        const container = document.getElementById('paginacionGlobalContainer');
        container.innerHTML = '';

        if (totalPages <= 1) return;

        // Botón Anterior
        const prevDisabled = current === 1 ? 'disabled' : '';
        container.innerHTML += `
            <li class="page-item ${prevDisabled}">
                <button class="page-link" onclick="cargarGlobales(${current - 1})">&laquo;</button>
            </li>`;

        // Números de página (lógica simple de ventana)
        let startPage = Math.max(1, current - 2);
        let endPage = Math.min(totalPages, current + 2);

        for (let i = startPage; i <= endPage; i++) {
            const active = i === current ? 'active' : '';
            container.innerHTML += `
                <li class="page-item ${active}">
                    <button class="page-link" onclick="cargarGlobales(${i})">${i}</button>
                </li>`;
        }

        // Botón Siguiente
        const nextDisabled = current === totalPages ? 'disabled' : '';
        container.innerHTML += `
            <li class="page-item ${nextDisabled}">
                <button class="page-link" onclick="cargarGlobales(${current + 1})">&raquo;</button>
            </li>`;
    }
</script>