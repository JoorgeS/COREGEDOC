<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="index.php?action=home">Inicio</a></li>
                <li class="breadcrumb-item active">Minutas Aprobadas</li>
            </ol>
        </nav>
    </div>

    <div class="card shadow-sm mb-4 border-0">
        <div class="card-body p-4">
            
            <div class="mb-3">
                <h6 class="fw-bold text-secondary text-uppercase mb-0" style="font-size: 0.85rem;">
                    <i class="fas fa-filter me-2"></i>Filtros
                </h6>
            </div>

            <form id="formFiltros" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-bold text-muted small mb-1">Fecha de Creación</label>
                    <div class="d-flex align-items-center">
                        <input type="date" id="filtroDesde" class="form-control form-control-sm" title="Desde">
                        <span class="mx-2 text-muted">-</span>
                        <input type="date" id="filtroHasta" class="form-control form-control-sm" title="Hasta">
                    </div>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-bold text-muted small mb-1">Filtrar por Comisión</label>
                    <select id="filtroComision" class="form-select form-select-sm">
                        <option value="">Todas las Comisiones</option>
                        <?php foreach ($data['comisiones'] as $c): ?>
                            <option value="<?= $c['idComision'] ?>"><?= htmlspecialchars($c['nombreComision']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-bold text-muted small mb-1">Búsqueda Rápida</label>
                    <div class="d-flex">
                        <div class="input-group input-group-sm me-2">
                            <span class="input-group-text bg-white text-muted border-end-0">
                                <i class="fas fa-search"></i>
                            </span>
                            <input type="text" id="filtroKeyword" class="form-control border-start-0 ps-0" placeholder="Escriba para buscar (reunión, temas, objetivos)...">
                        </div>
                        <button type="button" id="btnLimpiar" class="btn btn-sm btn-outline-secondary px-3" title="Limpiar Filtros">
                            <i class="fas fa-eraser"></i>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="tablaMinutas">
                    <thead class="table-light">
                        <tr>
                            <th width="5%">ID</th>
                            <th width="30%">Nombre Reunión</th>
                            <th width="25%">Comisión</th>
                            <th width="15%">Fecha</th>
                            <th width="10%" class="text-center">Adjuntos</th>
                            <th width="15%" class="text-center">PDF</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyResultados">
                        <tr>
                            <td colspan="6" class="text-center py-5">
                                <div class="spinner-border text-primary"></div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card-footer bg-white d-flex justify-content-between align-items-center py-2">
            <small class="text-muted" id="infoPaginacion">Cargando...</small>
            <nav>
                <ul class="pagination pagination-sm mb-0" id="paginacionContainer"></ul>
            </nav>
        </div>
    </div>
</div>

<div class="modal fade" id="modalAdjuntos" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-paperclip me-2"></i> Archivos Adjuntos</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="listaAdjuntos" class="list-group list-group-flush"></div>
                <div id="sinAdjuntosMsg" class="text-center text-muted py-3" style="display:none;">
                    No se encontraron archivos adjuntos.
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // 1. Configuración Inicial
        const hoy = new Date();
        const primerDia = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
        const formatDate = d => d.toISOString().split('T')[0];

        const inputDesde = document.getElementById('filtroDesde');
        const inputHasta = document.getElementById('filtroHasta');

        if (!inputDesde.value) inputDesde.value = formatDate(primerDia);
        if (!inputHasta.value) inputHasta.value = formatDate(hoy);

        const inputComision = document.getElementById('filtroComision');
        const inputKeyword = document.getElementById('filtroKeyword');
        let currentPage = 1;
        let timerDebounce;

        // 2. Cargar Datos
        function cargarMinutas(page = 1) {
            currentPage = page;
            const tbody = document.getElementById('tbodyResultados');
            tbody.style.opacity = '0.5';

            const params = new URLSearchParams({
                action: 'api_filtrar_aprobadas',
                desde: inputDesde.value,
                hasta: inputHasta.value,
                comision: inputComision.value,
                q: inputKeyword.value,
                page: page,
                // *** PARÁMETROS PARA ORDENAR POR ID DESCENDENTE ***
                orderBy: 'idMinuta',
                orderDir: 'DESC'
            });

            fetch('index.php?' + params.toString())
                .then(r => r.json())
                .then(resp => {
                    if (resp.status === 'error') {
                        tbody.innerHTML = `<tr><td colspan="7" class="text-center text-danger">${resp.message}</td></tr>`;
                    } else {
                        renderTabla(resp.data);
                        renderPaginacion(resp.total, resp.totalPages, page);
                    }
                    tbody.style.opacity = '1';
                })
                .catch(err => {
                    console.error(err);
                    tbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger">Error de conexión</td></tr>';
                    tbody.style.opacity = '1';
                });
        }

        // 3. Renderizar Tabla
        // 3. Renderizar Tabla (Corregida sin Temas)
        function renderTabla(datos) {
            const tbody = document.getElementById('tbodyResultados');
            tbody.innerHTML = '';

            if (!datos || datos.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">No se encontraron resultados.</td></tr>';
                return;
            }

            datos.forEach(m => {
                // --- SE ELIMINÓ TODA LA LÓGICA DE 'temasHtml' AQUÍ ---

                // PDF - Limpieza de ruta
                let btnPdf = '<span class="badge bg-light text-secondary border">No disponible</span>';
                if (m.pathArchivo) {
                    const urlPdf = cleanPath(m.pathArchivo);
                    btnPdf = `<a href="${urlPdf}" target="_blank" class="btn btn-sm btn-outline-danger" title="Ver PDF Firmado"><i class="fas fa-file-pdf"></i></a>`;
                }

                // Adjuntos (Icono)
                const numAdj = parseInt(m.numAdjuntos || 0);
                let btnAdj = '-';

                if (numAdj > 0) {
                    btnAdj = `
                <a href="javascript:void(0);" onclick="verAdjuntos(${m.idMinuta})" 
                   class="d-inline-flex align-items-center text-decoration-none text-nowrap" 
                   title="Ver ${numAdj} archivos adjuntos"
                   style="font-size: 0.8rem; line-height: 1;">
                    <i class="fas fa-paperclip text-primary me-1"></i>
                    <span class="fw-bold text-dark">${numAdj}</span>
                </a>
            `;
                }

                // Se eliminó la línea <td class="small">${temasHtml}</td> del template
                const tr = `
            <tr>
                <td class="fw-bold text-muted">${m.idMinuta}</td>
                <td class="fw-bold text-dark">${m.nombreReunion || 'Sin nombre'}</td>
                <td class="small text-secondary">${m.nombreComision || 'General'}</td>
                <td>${m.fecha ? m.fecha.substring(0,10) : 'Sin fecha'}</td>
                <td class="text-center">${btnAdj}</td>
                <td class="text-center">${btnPdf}</td>
            </tr>
        `;
                tbody.innerHTML += tr;
            });
        }
        // 4. Paginación
        function renderPaginacion(total, totalPages, current) {
            document.getElementById('infoPaginacion').innerText = `Total: ${total} registros`;
            const container = document.getElementById('paginacionContainer');
            container.innerHTML = '';

            if (totalPages <= 1) return;

            container.innerHTML += `
            <li class="page-item ${current === 1 ? 'disabled' : ''}">
                <button class="page-link" onclick="cambiarPagina(${current - 1})">&laquo;</button>
            </li>
        `;

            let startPage = Math.max(1, current - 2);
            let endPage = Math.min(totalPages, current + 2);

            for (let i = startPage; i <= endPage; i++) {
                container.innerHTML += `
                <li class="page-item ${i === current ? 'active' : ''}">
                    <button class="page-link" onclick="cambiarPagina(${i})">${i}</button>
                </li>
            `;
            }

            container.innerHTML += `
            <li class="page-item ${current === totalPages ? 'disabled' : ''}">
                <button class="page-link" onclick="cambiarPagina(${current + 1})">&raquo;</button>
            </li>
        `;
        }

        const debounceLoad = () => {
            clearTimeout(timerDebounce);
            timerDebounce = setTimeout(() => cargarMinutas(1), 400);
        };

        inputKeyword.addEventListener('input', debounceLoad);
        inputDesde.addEventListener('change', () => cargarMinutas(1));
        inputHasta.addEventListener('change', () => cargarMinutas(1));
        inputComision.addEventListener('change', () => cargarMinutas(1));

        document.getElementById('btnLimpiar').addEventListener('click', () => {
            inputDesde.value = formatDate(primerDia);
            inputHasta.value = formatDate(hoy);
            inputComision.value = "";
            inputKeyword.value = "";
            cargarMinutas(1);
        });

        window.cambiarPagina = (p) => cargarMinutas(p);
        cargarMinutas(1);
    });

    // --- FUNCIÓN DE LIMPIEZA DE RUTAS ---
    function cleanPath(path) {
        if (!path) return '#';
        if (path.startsWith('http') || path.startsWith('https')) {
            return path;
        }
        return path.replace(/^\/+/, '');
    }

    // --- FUNCIÓN MODAL ADJUNTOS (CORREGIDA) ---
    // --- FUNCIÓN MODAL ADJUNTOS (CORREGIDA) ---
    // --- FUNCIÓN MODAL ADJUNTOS (FILTRADA) ---
    function verAdjuntos(idMinuta) {
        const lista = document.getElementById('listaAdjuntos');
        const msg = document.getElementById('sinAdjuntosMsg');

        // Spinner de carga
        lista.innerHTML = '<div class="text-center py-3"><div class="spinner-border text-primary spinner-border-sm"></div></div>';

        const myModal = new bootstrap.Modal(document.getElementById('modalAdjuntos'));
        myModal.show();

        fetch(`index.php?action=api_ver_adjuntos_minuta&id=${idMinuta}`)
            .then(r => r.json())
            .then(resp => {
                lista.innerHTML = '';

                // 1. FILTRADO: Excluimos la asistencia antes de contar o mostrar
                const dataRaw = resp.data || [];
                
                const adjuntosValidos = dataRaw.filter(a => {
                    // Normalizamos a minúsculas para comparar
                    const nombre = (a.nombreArchivo || '').toLowerCase();
                    const path = (a.pathAdjunto || '').toLowerCase();
                    const tipo = (a.tipoAdjunto || '').toLowerCase(); // Si tu backend envía el tipo

                    // Si el nombre, ruta o tipo contienen "asistencia", lo descartamos (return false)
                    if (nombre.includes('asistencia')) return false;
                    if (path.includes('asistencia')) return false;
                    if (tipo === 'asistencia') return false;

                    return true; // Es un adjunto válido
                });

                // 2. LOGICA VISUAL: Usamos el array filtrado 'adjuntosValidos'
                if (adjuntosValidos.length > 0) {
                    msg.style.display = 'none';

                    adjuntosValidos.forEach(a => {
                        // Priorizar a.nombreArchivo o derivar de la ruta.
                        let nombreMostrar = a.nombreArchivo || a.pathAdjunto.split('/').pop();

                        // Configuración de URL e ícono por defecto
                        let urlVisor = `index.php?action=ver_archivo_adjunto&id=${a.idAdjunto}`;
                        let extension = nombreMostrar.split('.').pop().toLowerCase();
                        let icon = '<i class="fas fa-file text-secondary"></i>'; 

                        // Iconos según extensión
                        if (['pdf'].includes(extension)) icon = '<i class="fas fa-file-pdf text-danger"></i>';
                        else if (['doc', 'docx'].includes(extension)) icon = '<i class="fas fa-file-word text-primary"></i>';
                        else if (['xls', 'xlsx'].includes(extension)) icon = '<i class="fas fa-file-excel text-success"></i>';
                        else if (['jpg', 'jpeg', 'png', 'gif'].includes(extension)) icon = '<i class="fas fa-file-image text-info"></i>';

                        // Lógica para Links Externos
                        if (a.tipoAdjunto === 'link' || a.pathAdjunto.startsWith('http') || a.pathAdjunto.startsWith('www')) {
                            let urlExterna = a.pathAdjunto;
                            if (!urlExterna.match(/^https?:\/\//)) {
                                urlExterna = 'https://' + urlExterna;
                            }
                            urlVisor = urlExterna;
                            icon = '<i class="fas fa-link text-primary"></i>';
                        }

                        // Generar el HTML
                        const item = `
                        <a href="${urlVisor}" target="_blank" class="list-group-item list-group-item-action d-flex align-items-center">
                            <div class="me-3 fs-4">${icon}</div>
                            <div class="text-truncate w-100">
                                <div class="fw-bold text-dark" title="${nombreMostrar}">${nombreMostrar}</div>
                                <small class="text-muted d-block">
                                    <i class="fas fa-eye me-1"></i> Vista Previa
                                </small>
                            </div>
                        </a>
                    `;
                        lista.innerHTML += item;
                    });
                } else {
                    // Si después de filtrar no queda nada (o solo había asistencia)
                    msg.style.display = 'block';
                    msg.innerHTML = 'No se encontraron archivos adjuntos (Asistencia oculta).';
                }
            })
            .catch(e => {
                console.error(e);
                lista.innerHTML = '<div class="text-danger text-center p-3">Error al cargar archivos.</div>';
            }); 
    }
</script>