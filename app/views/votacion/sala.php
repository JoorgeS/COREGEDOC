<?php
$c_azul = '#0071bc';
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
    .nav-tabs-inst { border-bottom: 2px solid #e0e0e0; }
    .nav-tabs-inst .nav-link { color: var(--inst-gris); font-weight: 600; border: none; padding: 12px 20px; transition: all 0.3s; }
    .nav-tabs-inst .nav-link:hover { color: var(--inst-azul); background-color: #f8f9fa; }
    .nav-tabs-inst .nav-link.active { color: var(--inst-azul); border-bottom: 3px solid var(--inst-azul); background: transparent; }

    /* Tarjetas de Estado */
    .card-status { border: 1px solid #e0e0e0; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); overflow: hidden; transition: all 0.3s; }
    
    /* Animación Pulso */
    .scanning-pulse { width: 80px; height: 80px; background: rgba(0, 113, 188, 0.1); color: var(--inst-azul); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; animation: pulse 2s infinite; }
    @keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(0, 113, 188, 0.4); } 70% { box-shadow: 0 0 0 20px rgba(0, 113, 188, 0); } 100% { box-shadow: 0 0 0 0 rgba(0, 113, 188, 0); } }

    /* Botón Volver Institucional */
    .btn-inst-volver { color: var(--inst-gris); border: 2px solid var(--inst-gris); background: transparent; border-radius: 50px; font-weight: 600; padding: 6px 20px; font-size: 0.85rem; text-decoration: none; transition: all 0.3s; display: inline-flex; align-items: center; }
    .btn-inst-volver:hover { background: var(--inst-gris); color: white; transform: translateX(-3px); }
    
    /* Utilidades extra para votación */
    .hover-fill:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
</style>

<div class="container-fluid py-5">
    
    <div class="d-flex justify-content-between align-items-center mb-5">
        <div>
            <h2 class="fw-bold mb-1" style="color: var(--inst-negro);">
                <i class="fas fa-vote-yea me-2 text-inst-azul"></i> Sala de Votaciones
            </h2>
            <p class="text-muted mb-0">Panel de votación electrónica y resultados.</p>
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

    <ul class="nav nav-tabs nav-tabs-inst mb-4" id="votacionTabs" role="tablist">
        <li class="nav-item">
            <button class="nav-link active" id="voto-tab" data-bs-toggle="tab" data-bs-target="#voto" type="button"><i class="fas fa-box-open me-2"></i> Votación en Curso</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" id="misvotos-tab" data-bs-toggle="tab" data-bs-target="#misvotos" type="button"><i class="fas fa-list-ol me-2"></i> Mis Votos</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" id="resultados-tab" data-bs-toggle="tab" data-bs-target="#resultados" type="button"><i class="fas fa-chart-pie me-2"></i> Resultados Globales</button>
        </li>
    </ul>

    <div class="tab-content">

        <div class="tab-pane fade show active" id="voto">
            <div class="row justify-content-center">
                <div class="col-lg-6 col-md-8">

                    <div id="panel-espera" class="card card-status text-center p-5 bg-white">
                        <div class="card-body">
                            <div class="scanning-pulse mb-3">
                                <i class="fa-solid fa-person-booth fa-2x" style="color: var(--inst-azul);"></i>
                            </div>
                            <h4 class="fw-bold mb-2 text-inst-azul">Esperando Votación...</h4>
                            <p class="text-muted">La pantalla se actualizará automáticamente cuando se abra una votación.</p>
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
                                <button class="btn btn-outline-success btn-lg py-3 fw-bold hover-fill" onclick="confirmarVoto('SI')">
                                    <i class="fas fa-check fa-lg me-2"></i> SÍ
                                </button>
                                <button class="btn btn-outline-danger btn-lg py-3 fw-bold hover-fill" onclick="confirmarVoto('NO')">
                                    <i class="fas fa-times fa-lg me-2"></i> NO
                                </button>
                                <button class="btn btn-outline-secondary btn-lg py-3 fw-bold hover-fill" onclick="confirmarVoto('ABSTENCION')">
                                    <i class="fas fa-minus-circle fa-lg me-2"></i> ABSTENCIÓN
                                </button>
                            </div>
                            <input type="hidden" id="idVotacionActiva">
                        </div>
                    </div>

                    <div id="panel-ya-voto" class="card card-status text-center p-5 bg-white shadow-sm" style="display: none;">
                        <div class="card-body">
                            <div id="icono-voto-previo" class="mb-3 display-4"></div>
                            <h4 class="card-title text-uppercase fw-bold text-muted">Voto Registrado</h4>
                            <hr>
                            <p class="mb-2 fs-5">Usted ha votado:</p>
                            <h2 id="texto-voto-previo" class="fw-bold mb-3">...</h2>
                            <p id="mensaje-detalle-voto" class="text-secondary fw-bold mb-4"></p>
                            <div class="alert alert-light border small text-muted">
                                <i class="fas fa-lock me-1"></i> Su voto ha sido guardado. Espere al cierre de la votación.
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="misvotos">
            <div class="card card-status border-0 shadow-sm">
                <div class="card-body p-4">
                    
                    <div class="mb-4">
                        <div class="d-flex align-items-center mb-3">
                            <h6 class="fw-bold text-secondary text-uppercase mb-0 me-3">
                                <i class="fas fa-filter me-2 text-inst-naranja"></i>Filtros
                            </h6>
                            <div class="flex-grow-1 border-bottom"></div>
                        </div>

                        <form id="formFiltrosVotos" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-bold text-muted small mb-1">Fecha del Voto</label>
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
                                    <?php if(!empty($data['comisiones'])): ?>
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
                                        <input type="text" id="filtroKeyword" class="form-control border-start-0 ps-0" placeholder="Nombre votación...">
                                    </div>
                                    <button type="button" id="btnLimpiarFiltros" class="btn btn-sm btn-outline-secondary px-3" title="Limpiar Filtros">
                                        <i class="fas fa-eraser"></i>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="tablaVotos">
                            <thead class="table-light">
                                <tr>
                                    <th width="40%">Votación</th>
                                    <th width="20%">Mi Voto</th>
                                    <th width="25%">Fecha</th>
                                    <th width="15%">Estado</th>
                                </tr>
                            </thead>
                            <tbody id="tbodyVotos">
                                <?php if (empty($data['historial_personal'])): ?>
                                    <tr><td colspan="4" class="text-center py-4 text-muted">No has votado aún.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($data['historial_personal'] as $v): ?>
                                        <?php
                                        // Lógica de visualización PHP inicial
                                        $votoVis = $v['opcionVoto'];
                                        $claseVis = 'secondary';
                                        
                                        if (in_array($v['opcionVoto'], ['SI', 'APRUEBO'])) {
                                            $votoVis = 'SÍ';
                                            $claseVis = 'success';
                                        } elseif (in_array($v['opcionVoto'], ['NO', 'RECHAZO'])) {
                                            $votoVis = 'NO';
                                            $claseVis = 'danger';
                                        } else {
                                            $votoVis = 'ABSTENCIÓN';
                                            $claseVis = 'warning text-dark';
                                        }
                                        ?>
                                        <tr>
                                            <td class="fw-bold text-dark text-truncate" style="max-width: 250px;" title="<?= htmlspecialchars($v['nombreVotacion']) ?>">
                                                <?= htmlspecialchars($v['nombreVotacion']) ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= $claseVis ?> border border-light shadow-sm px-3">
                                                    <?= $votoVis ?>
                                                </span>
                                            </td>
                                            <td><?= date('d/m/Y H:i', strtotime($v['fechaVoto'])) ?></td>
                                            <td><span class="badge bg-light text-dark border"><?= $v['estado'] ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top">
                        <small class="text-muted" id="infoPaginacion">Mostrando resultados recientes</small>
                        <nav aria-label="Navegación">
                            <ul class="pagination pagination-sm mb-0 justify-content-end" id="paginacionContainer"></ul>
                        </nav>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="resultados">
            <div class="row g-3">
                <?php if (empty($data['resultados_generales'])): ?>
                    <div class="col-12 text-center py-5 text-muted">No hay resultados históricos disponibles.</div>
                <?php else: ?>
                    <?php foreach ($data['resultados_generales'] as $res): ?>
                        <div class="col-md-6 col-xl-4">
                            <div class="card card-status h-100 shadow-sm">
                                <div class="card-header bg-white fw-bold text-truncate" title="<?php echo htmlspecialchars($res['nombreVotacion']); ?>">
                                    <?php echo htmlspecialchars($res['nombreVotacion']); ?>
                                </div>
                                <div class="card-body">
                                    <div class="d-flex justify-content-between text-center mb-2">
                                        <div class="text-success"><strong><?php echo $res['si']; ?></strong><br><small>SÍ</small></div>
                                        <div class="text-danger"><strong><?php echo $res['no']; ?></strong><br><small>NO</small></div>
                                        <div class="text-secondary"><strong><?php echo $res['abs']; ?></strong><br><small>ABSTENCIÓN</small></div>
                                    </div>
                                    <?php
                                    $total = $res['si'] + $res['no'] + $res['abs'];
                                    $pSi = $total > 0 ? ($res['si'] / $total) * 100 : 0;
                                    $pNo = $total > 0 ? ($res['no'] / $total) * 100 : 0;
                                    $pAbs = $total > 0 ? ($res['abs'] / $total) * 100 : 0;
                                    ?>
                                    <div class="progress" style="height: 10px;">
                                        <div class="progress-bar bg-success" style="width: <?php echo $pSi; ?>%"></div>
                                        <div class="progress-bar bg-danger" style="width: <?php echo $pNo; ?>%"></div>
                                        <div class="progress-bar bg-secondary" style="width: <?php echo $pAbs; ?>%"></div>
                                    </div>
                                </div>
                                <div class="card-footer bg-light text-muted small">
                                    <?php echo date('d/m/Y', strtotime($res['fechaCreacion'])); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<script>
    // --- 1. LÓGICA DE VOTACIÓN EN TIEMPO REAL ---
    let pollingInterval;
    let yaVotoLocalmente = false;

    document.addEventListener("DOMContentLoaded", () => { 
        iniciarPolling(); 
        // Inicializar filtros del historial
        if(document.getElementById('filtroDesde')) {
            initFiltrosVotos();
        }
    });

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

        yaVotoLocalmente = false;
        elInput.value = data.idVotacion;
        elTitulo.textContent = data.nombreVotacion;

        const botones = elPanel.querySelectorAll('button');
        botones.forEach(btn => { btn.disabled = false; });

        ocultarTodosPaneles();
        elPanel.style.display = 'block';
        if (navigator.vibrate) navigator.vibrate([200, 100, 200]);
    }

    function mostrarEspera() {
        if (document.getElementById('panel-espera').style.display === 'block') return;
        ocultarTodosPaneles();
        document.getElementById('panel-espera').style.display = 'block';
        const elInput = document.getElementById('idVotacionActiva');
        if (elInput) elInput.value = '';
        document.querySelectorAll('#panel-votacion button').forEach(b => b.disabled = false);
    }

    function ocultarTodosPaneles() {
        document.getElementById('panel-espera').style.display = 'none';
        document.getElementById('panel-votacion').style.display = 'none';
        document.getElementById('panel-ya-voto').style.display = 'none';
    }

    function confirmarVoto(intencion) {
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
                cargarHistorial(1); // Recargar historial automáticamente
            } else {
                Swal.fire('Error', d.message, 'error');
                botones.forEach(b => b.disabled = false);
            }
        })
        .catch(err => {
            console.error(err);
            Swal.fire('Error', 'Error de conexión', 'error');
            botones.forEach(b => b.disabled = false);
        });
    }

    function renderizarVistaPrevia(valorEntrada, nombreVotacion) {
        ocultarTodosPaneles();
        const panel = document.getElementById('panel-ya-voto');
        const iconoDiv = document.getElementById('icono-voto-previo');
        const textoDiv = document.getElementById('texto-voto-previo');
        const mensajeDiv = document.getElementById('mensaje-detalle-voto');

        const op = valorEntrada ? valorEntrada.toString().toUpperCase().trim() : '';
        // Normalizamos con las variantes posibles
        const variantesSI = ['SI', 'APRUEBO', 'SÍ'];
        const variantesNO = ['NO', 'RECHAZO'];

        if (variantesSI.includes(op)) {
            iconoDiv.innerHTML = '<i class="fas fa-check-circle text-success"></i>';
            textoDiv.className = 'fw-bold mb-3 text-success';
            textoDiv.textContent = 'SÍ';
        } else if (variantesNO.includes(op)) {
            iconoDiv.innerHTML = '<i class="fas fa-times-circle text-danger"></i>';
            textoDiv.className = 'fw-bold mb-3 text-danger';
            textoDiv.textContent = 'NO';
        } else {
            iconoDiv.innerHTML = '<i class="fas fa-minus-circle text-warning"></i>';
            textoDiv.className = 'fw-bold mb-3 text-warning';
            textoDiv.textContent = 'ABSTENCIÓN';
        }

        mensajeDiv.textContent = nombreVotacion ? `para "${nombreVotacion}"` : '';
        panel.style.display = 'block';
    }

    // --- 2. LÓGICA DE HISTORIAL (FILTROS Y PAGINACIÓN) ---
    
    function initFiltrosVotos() {
        const hoy = new Date();
        const primerDia = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
        const formatDate = d => d.toISOString().split('T')[0];

        const inputDesde = document.getElementById('filtroDesde');
        const inputHasta = document.getElementById('filtroHasta');
        const inputComision = document.getElementById('filtroComision');
        const inputKeyword = document.getElementById('filtroKeyword');
        
        // Fechas por defecto solo si están vacías
        if (!inputDesde.value) inputDesde.value = formatDate(primerDia);
        if (!inputHasta.value) inputHasta.value = formatDate(hoy);

        let debounceTimer;
        
        // Listeners
        inputDesde.addEventListener('change', () => cargarHistorial(1));
        inputHasta.addEventListener('change', () => cargarHistorial(1));
        inputComision.addEventListener('change', () => cargarHistorial(1));
        
        inputKeyword.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => cargarHistorial(1), 500);
        });

        // BOTÓN LIMPIAR: DEJA FECHAS VACÍAS PARA BÚSQUEDA GLOBAL
        document.getElementById('btnLimpiarFiltros').addEventListener('click', () => {
            inputDesde.value = ''; 
            inputHasta.value = '';
            inputComision.value = "";
            inputKeyword.value = "";
            cargarHistorial(1);
        });

        cargarHistorial(1);
    }

    function cargarHistorial(page) {
        const tbody = document.getElementById('tbodyVotos');
        tbody.style.opacity = '0.5';

        const params = new URLSearchParams({
            action: 'api_historial_votos',
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
        const tbody = document.getElementById('tbodyVotos');
        tbody.innerHTML = '';

        if (!registros || registros.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted">No se encontraron votos.</td></tr>';
            return;
        }

        registros.forEach(r => {
            // 1. Formatear Fecha
            const fechaObj = new Date(r.fechaVoto);
            const fechaStr = fechaObj.toLocaleDateString('es-CL') + ' ' + fechaObj.toLocaleTimeString('es-CL', {hour: '2-digit', minute:'2-digit'});
            
            // 2. Lógica visual para "Mi Voto"
            let badgeClass = 'bg-secondary';
            let opcionTexto = r.opcionVoto;
            
            if(['SI', 'APRUEBO'].includes(r.opcionVoto)) {
                badgeClass = 'bg-success';
                opcionTexto = 'SÍ';
            } else if(['NO', 'RECHAZO'].includes(r.opcionVoto)) {
                badgeClass = 'bg-danger';
                opcionTexto = 'NO';
            } else {
                badgeClass = 'bg-warning text-dark';
                opcionTexto = 'ABSTENCIÓN';
            }

            // 3. Lógica visual para "Estado de la Votación" (CORRECCIÓN AQUÍ)
            // Usamos r.resultado_final que viene de tu Modelo
            let resultadoTexto = r.resultado_final || 'PENDIENTE';
            let estadoClass = 'bg-secondary';

            if (resultadoTexto === 'APROBADA') {
                estadoClass = 'bg-success'; // Verde
            } else if (resultadoTexto === 'RECHAZADA') {
                estadoClass = 'bg-danger'; // Rojo
            } else if (resultadoTexto === 'EMPATE') {
                estadoClass = 'bg-warning text-dark'; // Amarillo
            } else if (resultadoTexto === 'SIN DATOS') {
                estadoClass = 'bg-light text-muted border'; // Gris claro
                resultadoTexto = 'Sin Quórum'; // Texto más amigable
            }

            const tr = `
                <tr>
                    <td class="fw-bold text-dark text-truncate" style="max-width: 250px;" title="${r.nombreVotacion}">
                        ${r.nombreVotacion}
                        <div class="small text-muted fw-normal">${r.nombreReunion || 'Sin Reunión'}</div>
                    </td>
                    <td>
                        <span class="badge ${badgeClass} border border-light shadow-sm px-3">
                            ${opcionTexto}
                        </span>
                    </td>
                    <td>${fechaStr}</td>
                    <td>
                        <span class="badge ${estadoClass} border border-light shadow-sm px-2">
                            ${resultadoTexto}
                        </span>
                    </td>
                </tr>
            `;
            tbody.innerHTML += tr;
        });
    }

    function renderPaginacion(total, totalPages, current) {
        document.getElementById('infoPaginacion').innerText = `Total: ${total} votos`;
        const container = document.getElementById('paginacionContainer');
        container.innerHTML = '';

        if (totalPages <= 1) return;

        container.innerHTML += `
            <li class="page-item ${current === 1 ? 'disabled' : ''}">
                <button class="page-link" onclick="cargarHistorial(${current - 1})">&laquo;</button>
            </li>
        `;

        let startPage = Math.max(1, current - 2);
        let endPage = Math.min(totalPages, current + 2);

        for (let i = startPage; i <= endPage; i++) {
            container.innerHTML += `
                <li class="page-item ${i === current ? 'active' : ''}">
                    <button class="page-link" onclick="cargarHistorial(${i})">${i}</button>
                </li>
            `;
        }

        container.innerHTML += `
            <li class="page-item ${current === totalPages ? 'disabled' : ''}">
                <button class="page-link" onclick="cargarHistorial(${current + 1})">&raquo;</button>
            </li>
        `;
    }
</script>