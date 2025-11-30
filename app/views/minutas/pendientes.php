<div class="container-fluid">

    <!-- MIGA DE PAN -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php?action=home">Inicio</a></li>
            <li class="breadcrumb-item"><a href="index.php?action=minutas_dashboard">Minutas</a></li>
            <li class="breadcrumb-item active">Gestión de Pendientes</li>
        </ol>
    </nav>

    <!-- BARRA DE FILTROS AVANZADA (AUTO-SEARCH) -->
    <div class="card shadow-sm mb-4 border-0 bg-white">
        <div class="card-body py-4">
            <div class="d-flex justify-content-between align-items-end mb-2">
                <h6 class="text-muted text-uppercase fw-bold m-0" style="font-size: 0.8rem; letter-spacing: 1px;">
                    <i class="fas fa-filter me-1"></i> Filtros
                </h6>
                
            </div>
            
            <form id="formFiltros" class="row g-3 align-items-end">
                
                <!-- 1. Rango de Fechas -->
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-secondary">Fecha de Creación</label>
                    <div class="input-group input-group-sm">
                        <input type="date" id="filtroDesde" class="form-control">
                        <span class="input-group-text bg-light">-</span>
                        <input type="date" id="filtroHasta" class="form-control">
                    </div>
                </div>

                <!-- 2. Comisión (ComboBox) -->
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-secondary">Filtrar por Comisión</label>
                    <select id="filtroComision" class="form-select form-select-sm">
                        <option value="">Todas las Comisiones</option>
                        <?php if(!empty($data['comisiones'])): ?>
                            <?php foreach($data['comisiones'] as $c): ?>
                                <option value="<?= $c['idComision'] ?>"><?= htmlspecialchars($c['nombreComision']) ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <!-- 3. Buscador Inteligente -->
                <div class="col-md-5">
                    <label class="form-label small fw-bold text-secondary">Búsqueda Rápida</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" id="filtroKeyword" class="form-control" placeholder="Escriba para buscar (reunión, temas, objetivos)...">
                    </div>
                </div>

                <!-- Botón Limpiar -->
                <div class="col-md-1">
                    <button type="button" id="btnLimpiar" class="btn btn-outline-secondary btn-sm w-100" title="Limpiar filtros y recargar">
                        <i class="fas fa-eraser"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- TABLA DE RESULTADOS -->
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 table-striped">
                    <thead class="bg-light text-secondary">
                        <tr class="text-uppercase small fw-bold" style="font-size: 0.75rem;">
                            <th class="ps-3" width="5%">ID</th>
                            <th width="20%">Reunión</th>
                            <th width="20%">Comisión</th>
                            <!-- COLUMNA ELIMINADA: Temas Tratados -->
                            <th width="10%">Fecha</th>
                            <th width="5%" class="text-center">Documentos Adjuntos</th>
                            <th width="10%" class="text-center">Estado</th>
                            <th width="10%" class="text-end">Acciones</th>
                            <th width="5%" class="text-center border-start bg-light text-muted" title="Seguimiento">Seguimiento</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyResultados" class="small">
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <div class="spinner-border spinner-border-sm text-primary me-2"></div> Cargando datos...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <!-- Paginación (Contenedor) -->
            <div id="paginacionContainer" class="p-3 border-top bg-white">
                <!-- Aquí se inyectan los botones con JS -->
            </div>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- MODALES DEL SISTEMA -->
<!-- ========================================== -->

<!-- 1. Modal Archivos Adjuntos -->
<div class="modal fade" id="modalAdjuntos" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-folder-open me-2 text-warning"></i> Documentos Adjuntos</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div id="listaAdjuntos" class="list-group list-group-flush"></div>
                <div id="sinAdjuntosMsg" class="text-center text-muted py-4" style="display:none;">
                    <i class="far fa-file-excel fa-2x mb-2 opacity-25"></i><br>Sin archivos adjuntos.
                </div>
            </div>
            <div class="modal-footer py-1 bg-light">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- 2. Modal Ver Feedback (Correcciones) -->
<div class="modal fade" id="modalVerFeedback" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-danger shadow">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-exclamation-circle me-2"></i>Corrección Solicitada</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-white">
                
                <div class="d-flex align-items-start mb-3 p-2 bg-light rounded border">
                    <div class="me-3 text-danger mt-1"><i class="fas fa-user-tie fa-2x"></i></div>
                    <div>
                        <small class="text-uppercase text-muted fw-bold" style="font-size: 0.65rem;">Solicitado por:</small>
                        <h6 class="mb-0 fw-bold text-dark" id="fbNombre">Cargando...</h6>
                        <small class="text-muted" id="fbFecha">-</small>
                    </div>
                </div>
                
                <div class="p-3 border rounded bg-white">
                    <h6 class="text-uppercase text-danger small fw-bold mb-2">Detalle de la Observación:</h6>
                    <p class="card-text text-dark fst-italic" id="fbTexto">...</p>
                </div>

                <div class="mt-4 text-center">
                    <small class="text-muted d-block mb-2">
                        <i class="fas fa-info-circle"></i> Para resolver esto, debe editar la minuta y volver a enviarla a firma.
                    </small>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                <a href="#" id="btnIrAEditar" class="btn btn-danger fw-bold">
                    <i class="fas fa-tools me-1"></i> Ir a Corregir
                </a>
            </div>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- LÓGICA JAVASCRIPT -->
<!-- ========================================== -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        
        // --- 1. Configuración Inicial de Fechas ---
        const hoy = new Date();
        const primerDia = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
        const formatDate = d => d.toISOString().split('T')[0];
        
        const inputDesde = document.getElementById('filtroDesde');
        const inputHasta = document.getElementById('filtroHasta');
        const inputComision = document.getElementById('filtroComision');
        const inputKeyword = document.getElementById('filtroKeyword');
        
        if (!inputDesde.value) inputDesde.value = formatDate(primerDia);
        if (!inputHasta.value) inputHasta.value = formatDate(hoy);

        let debounceTimer;

        // --- 2. Función Principal: Cargar Tabla ---
        function cargarTabla(page = 1) {
            const tbody = document.getElementById('tbodyResultados');
            // Spinner sutil
            if(tbody.rows.length > 1 && !tbody.innerHTML.includes('Cargando')) {
                 tbody.style.opacity = '0.5';
            } else {
                 tbody.innerHTML = '<tr><td colspan="8" class="text-center py-5"><div class="spinner-border text-primary spinner-border-sm"></div> Buscando...</td></tr>';
            }

            // Recoger filtros
            const params = new URLSearchParams({
                action: 'api_filtrar_pendientes',
                desde: inputDesde.value,
                hasta: inputHasta.value,
                comisionId: inputComision.value,
                q: inputKeyword.value,
                page: page
            });

            fetch('index.php?' + params.toString())
                .then(r => r.json())
                .then(resp => {
                    tbody.style.opacity = '1';
                    if (resp.status === 'success') {
                        renderFilas(resp.data);
                        actualizarPaginacion(resp.total, resp.totalPages, page); 
                    } else {
                        tbody.innerHTML = `<tr><td colspan="8" class="text-center text-danger py-4">${resp.message}</td></tr>`;
                    }
                })
                .catch(err => {
                    console.error(err);
                    tbody.innerHTML = `<tr><td colspan="8" class="text-center text-danger py-4">Error de conexión.</td></tr>`;
                });
        }

        // --- 3. Renderizado de Filas ---
        function renderFilas(data) {
            const tbody = document.getElementById('tbodyResultados');
            let html = '';
            
            if(data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center py-5 text-muted fst-italic">No se encontraron resultados.</td></tr>';
                return;
            }

            data.forEach(m => {
                // Lógica de Estados Visuales
                let badge = '<span class="badge bg-secondary">BORRADOR</span>';
                let btnClass = 'btn-primary'; 
                let btnText = 'Gestionar';
                let feedbackBtn = '';
                let rowClass = '';

                if (m.estadoMinuta === 'REQUIERE_REVISION' || parseInt(m.tieneFeedback) > 0) {
                    badge = '<span class="badge bg-danger"><i class="fas fa-exclamation-triangle me-1"></i>CORRECCIÓN</span>';
                    btnClass = 'btn-danger'; 
                    btnText = 'Corregir';
                    rowClass = 'table-danger'; 
                    feedbackBtn = `<button class="btn btn-sm btn-warning text-dark me-1 shadow-sm" onclick="verFeedback(${m.idMinuta})" title="Leer Observaciones"><i class="fas fa-comment-dots"></i></button>`;
                } 
                else if (m.estadoMinuta === 'PENDIENTE') {
                    badge = '<span class="badge bg-warning text-dark">EN FIRMA</span>';
                    btnClass = 'btn-info text-white';
                    btnText = 'Ver Estado';
                }

                let fecha = m.fechaCreacion ? m.fechaCreacion.split('-').reverse().join('/') : '-';
                let reunion = m.nombreReunion || '<span class="text-muted fst-italic">Sin nombre</span>';
                
                // Ya NO usamos 'temas' aquí, así que lo quitamos del HTML

                let adjuntosHtml = parseInt(m.numAdjuntos) > 0 
                    ? `<button class="btn btn-xs btn-outline-secondary border-0" onclick="verAdjuntos(${m.idMinuta})"><i class="fas fa-paperclip text-primary"></i> ${m.numAdjuntos}</button>` 
                    : '<span class="text-muted small">-</span>';

                html += `
                <tr class="${rowClass}">
                    <td class="fw-bold text-muted">#${m.idMinuta}</td>
                    <td class="fw-bold text-primary text-truncate" style="max-width: 200px;" title="${m.nombreReunion}">${reunion}</td>
                    <td class="text-truncate" style="max-width: 200px;" title="${m.nombreComision}">${m.nombreComision}</td>
                    
                    <!-- Columna de Temas ELIMINADA -->
                    
                    <td><small>${fecha}</small></td>
                    
                    <td class="text-center">${adjuntosHtml}</td>
                    
                    <td class="text-center">${badge}</td>
                    
                    <td class="text-end">
                        <div class="btn-group btn-group-sm">
                            ${feedbackBtn}
                            <a href="index.php?action=minuta_gestionar&id=${m.idMinuta}" class="btn ${btnClass}" title="${btnText}"><i class="fas fa-edit me-1"></i> ${btnText}</a>
                        </div>
                    </td>
                    
                    <td class="text-center border-start bg-light">
                        <button class="btn btn-sm btn-link text-secondary p-0" onclick="verSeguimiento(${m.idMinuta})" title="Ver Historial"><i class="fas fa-history"></i></button>
                    </td>
                </tr>`;
            });
            tbody.innerHTML = html;
        }

        // --- 4. Paginación con Botones (ACTUALIZADO) ---
        function actualizarPaginacion(total, totalPages, currentPage) {
            const container = document.getElementById('paginacionContainer');
            if (!container) return;

            // Contenido básico
            let html = `<div class="d-flex justify-content-between align-items-center w-100">
                          <span class="text-muted small">Total: <strong>${total}</strong></span>`;

            // Si hay más de 1 página, mostramos los botones
            if (totalPages > 1) {
                html += `<nav aria-label="Navegación">
                            <ul class="pagination pagination-sm mb-0">`;

                // Botón Anterior
                let prevDisabled = (currentPage === 1) ? 'disabled' : '';
                html += `<li class="page-item ${prevDisabled}">
                            <button class="page-link" onclick="cambiarPagina(${currentPage - 1})" aria-label="Anterior">&laquo;</button>
                         </li>`;

                // Números de página (Ventana deslizante simple)
                let startPage = Math.max(1, currentPage - 2);
                let endPage = Math.min(totalPages, currentPage + 2);

                // Ajuste para mostrar siempre 5 botones si es posible
                if (endPage - startPage < 4) {
                    if (startPage === 1) endPage = Math.min(totalPages, startPage + 4);
                    else if (endPage === totalPages) startPage = Math.max(1, endPage - 4);
                }

                if (startPage > 1) {
                    html += `<li class="page-item"><button class="page-link" onclick="cambiarPagina(1)">1</button></li>`;
                    if (startPage > 2) html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
                }

                for (let i = startPage; i <= endPage; i++) {
                    let activeClass = (i === currentPage) ? 'active' : '';
                    html += `<li class="page-item ${activeClass}"><button class="page-link" onclick="cambiarPagina(${i})">${i}</button></li>`;
                }

                if (endPage < totalPages) {
                    if (endPage < totalPages - 1) html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
                    html += `<li class="page-item"><button class="page-link" onclick="cambiarPagina(${totalPages})">${totalPages}</button></li>`;
                }

                // Botón Siguiente
                let nextDisabled = (currentPage === totalPages) ? 'disabled' : '';
                html += `<li class="page-item ${nextDisabled}">
                            <button class="page-link" onclick="cambiarPagina(${currentPage + 1})" aria-label="Siguiente">&raquo;</button>
                         </li>`;

                html += `   </ul>
                         </nav>`;
            }

            html += `</div>`;
            container.innerHTML = html;
        }
        
        // Función global para el onclick de los botones de paginación
        window.cambiarPagina = function(page) {
            if (page > 0) cargarTabla(page);
        };


        // --- 5. Event Listeners ---
        inputDesde.addEventListener('change', () => cargarTabla(1));
        inputHasta.addEventListener('change', () => cargarTabla(1));
        inputComision.addEventListener('change', () => cargarTabla(1));
        inputKeyword.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                cargarTabla(1);
            }, 500);
        });

        document.getElementById('btnLimpiar').addEventListener('click', () => {
            inputDesde.value = formatDate(primerDia);
            inputHasta.value = formatDate(hoy);
            inputComision.value = '';
            inputKeyword.value = '';
            cargarTabla(1);
        });

        // Carga inicial
        cargarTabla(1);
    });

    // ==========================================
    // FUNCIONES GLOBALES
    // ==========================================

    function verAdjuntos(id) {
        const lista = document.getElementById('listaAdjuntos');
        const msg = document.getElementById('sinAdjuntosMsg');
        lista.innerHTML = '<div class="text-center py-3"><div class="spinner-border spinner-border-sm text-primary"></div></div>';
        msg.style.display = 'none';
        new bootstrap.Modal(document.getElementById('modalAdjuntos')).show();

        fetch(`index.php?action=api_ver_adjuntos_minuta&id=${id}`).then(r=>r.json()).then(resp=>{
            lista.innerHTML=''; 
            if(resp.data && resp.data.length>0){ 
                msg.style.display='none';
                resp.data.forEach(a=>{
                    let url = a.tipoAdjunto==='link'?a.pathAdjunto:`index.php?action=ver_archivo_adjunto&id=${a.idAdjunto}`;
                    let icon = 'fa-file text-secondary';
                    if(a.nombreArchivo && a.nombreArchivo.includes('.pdf')) icon = 'fa-file-pdf text-danger';
                    lista.innerHTML+=`<a href="${url}" target="_blank" class="list-group-item list-group-item-action"><i class="fas ${icon} me-2"></i> ${a.nombreArchivo||'Link'}</a>`;
                });
            }else{ msg.style.display='block'; }
        });
    }

    function verFeedback(id) {
        const btn = document.getElementById('btnIrAEditar');
        if(btn) btn.href = `index.php?action=minuta_gestionar&id=${id}`;
        const modal = new bootstrap.Modal(document.getElementById('modalVerFeedback'));
        document.getElementById('fbTexto').innerText = 'Cargando...';
        modal.show();
        fetch(`index.php?action=api_ver_feedback&id=${id}`).then(r=>r.json()).then(resp=>{
            if(resp.status==='success'){
                document.getElementById('fbNombre').innerText = resp.data.pNombre;
                document.getElementById('fbFecha').innerText = resp.data.fechaFeedback;
                document.getElementById('fbTexto').innerText = resp.data.textoFeedback;
            }
        });
    }

    function verSeguimiento(id) {
        window.location.href = `index.php?action=minuta_ver_historial&id=${id}`;
    }
</script>