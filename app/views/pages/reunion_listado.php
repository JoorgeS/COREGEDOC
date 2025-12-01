<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-calendar-check me-2 text-primary"></i> Gestión de Reuniones</h2>
        <div>
            <a href="index.php?action=reuniones_dashboard" class="btn btn-outline-secondary me-2">
                <i class="fas fa-arrow-left"></i> Volver al Menú
            </a>
            <a href="index.php?action=reunion_form" class="btn btn-success">
                <i class="fas fa-plus"></i> Nueva Reunión
            </a>
        </div>
    </div>

    <div class="card shadow-sm mb-4 border-0">
        <div class="card-body p-3">
            <div class="mb-2">
                <i class="fas fa-filter text-secondary me-1"></i> <strong class="text-secondary" style="font-size: 0.9rem;">FILTROS</strong>
            </div>

            <form method="GET" action="index.php" id="formFiltros">
                <input type="hidden" name="action" value="reunion_listado">

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-bold text-muted small mb-1">Fecha de Creación</label>
                        <div class="input-group input-group-sm">
                            <input type="date" name="desde" id="filtroDesde" class="form-control"
                                value="<?php echo $_GET['desde'] ?? date('Y-m-01'); ?>">
                            <span class="input-group-text bg-white">-</span>
                            <input type="date" name="hasta" id="filtroHasta" class="form-control"
                                value="<?php echo $_GET['hasta'] ?? date('Y-m-t'); ?>">
                        </div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-bold text-muted small mb-1">Filtrar por Comisión</label>
                        <select name="idComision" id="filtroComision" class="form-select form-select-sm">
                            <option value="">Todas las Comisiones</option>
                            <?php if (isset($comisiones)): ?>
                                <?php foreach ($comisiones as $c): ?>
                                    <option value="<?php echo $c['idComision']; ?>"
                                        <?php echo (isset($_GET['idComision']) && $_GET['idComision'] == $c['idComision']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($c['nombreComision']); ?>
                                    </option>
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
                                <input type="text" name="q" id="txtBusqueda" class="form-control border-start-0"
                                    placeholder="Escriba para buscar..." autocomplete="off"
                                    value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>">
                            </div>
                            <button type="button" id="btnLimpiar" class="btn btn-sm btn-outline-secondary px-3" title="Limpiar Filtros">
                                <i class="fas fa-eraser"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th width="10%">N° Reunión</th>
                            <th width="25%">Nombre Reunión</th>
                            <th width="20%">Comisión</th>
                            <th width="15%">Fecha y Hora Inicio</th>
                            <th width="10%">Hora Término</th>
                            <th width="20%">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyResultados">
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <div class="spinner-border spinner-border-sm text-primary me-2"></div> Cargando datos...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <div id="paginacionContainer" class="p-3 border-top bg-white">
                </div>
        </div>
    </div>
</div>

<script>
    // --- Funciones de Botones (Iniciar/Eliminar) ---
    function iniciarReunion(idReunion, nombre) {
        Swal.fire({
            title: '¿Iniciar Reunión?',
            text: `Se generará la minuta para "${nombre}" y quedará vinculada.`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#0d6efd',
            confirmButtonText: 'Sí, Iniciar ahora',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `index.php?action=reunion_iniciar_minuta&idReunion=${idReunion}`;
            }
        });
    }

    function eliminarReunion(id) {
        Swal.fire({
            title: '¿Eliminar?',
            text: "La reunión desaparecerá del listado.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'Sí, eliminar'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `index.php?action=reunion_eliminar&id=${id}`;
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        
        // 1. Configuración Inicial
        const hoy = new Date();
        const primerDia = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
        const formatDate = d => d.toISOString().split('T')[0];

        const inputDesde = document.getElementById('filtroDesde');
        const inputHasta = document.getElementById('filtroHasta');
        const inputComision = document.getElementById('filtroComision');
        const searchInput = document.getElementById('txtBusqueda');
        const tableBody = document.querySelector('#tbodyResultados');
        
        if (!inputDesde.value) inputDesde.value = formatDate(primerDia);
        if (!inputHasta.value) inputHasta.value = formatDate(hoy);

        let debounceTimer;

        // 2. Cargar Tabla con Paginación
        function cargarTabla(page = 1) {
            // Spinner
            if (tableBody.rows.length > 1 && !tableBody.innerHTML.includes('Cargando')) {
                tableBody.style.opacity = '0.5';
            } else {
                tableBody.innerHTML = '<tr><td colspan="6" class="text-center py-5"><div class="spinner-border text-primary spinner-border-sm"></div> Cargando datos...</td></tr>';
            }

            // Parámetros incluyendo página y límite
            const params = new URLSearchParams({
                action: 'api_filtrar_reuniones', // Asegúrate que tu backend responda a este action o el que uses
                desde: inputDesde.value,
                hasta: inputHasta.value,
                idComision: inputComision.value,
                q: searchInput.value,
                page: page,
                limit: 10 // 10 filas por página
            });

            fetch('index.php?' + params.toString())
                .then(r => r.json())
                .then(resp => {
                    tableBody.style.opacity = '1';
                    
                    // Asumimos que el backend devuelve: { status: 'success', data: [...], total: 100, totalPages: 10 }
                    if (resp.data && Array.isArray(resp.data)) {
                        renderFilas(resp.data);
                        actualizarPaginacion(resp.total, resp.totalPages, page);
                    } else {
                        tableBody.innerHTML = `<tr><td colspan="6" class="text-center text-danger py-4">Error al cargar datos.</td></tr>`;
                    }
                })
                .catch(err => {
                    console.error('Error en búsqueda:', err);
                    tableBody.style.opacity = '1';
                    tableBody.innerHTML = `<tr><td colspan="6" class="text-center text-danger py-4">Error de conexión.</td></tr>`;
                });
        }

        // 3. Renderizar Filas
        function renderFilas(data) {
            if (data.length === 0) {
                tableBody.innerHTML = '<tr><td colspan="6" class="text-center py-5 text-muted">No se encontraron reuniones.</td></tr>';
                return;
            }

            let html = '';
            data.forEach(r => {
                // Lógica de visualización (tomada de tu código PHP original y adaptada a JS)
                let estadoBD = r.estadoMinuta;
                let idMinuta = r.t_minuta_idMinuta;
                let modoVisual = 'PROGRAMADA';
                
                if (idMinuta) {
                    if (estadoBD == 'APROBADA' || estadoBD == 3) modoVisual = 'APROBADA';
                    else if (estadoBD == 'EN_REVISION' || estadoBD == 2) modoVisual = 'PENDIENTE';
                    else modoVisual = 'INICIADA';
                }

                // Formateo de fechas
                let fechaInicio = new Date(r.fechaInicioReunion);
                let fechaStr = fechaInicio.toLocaleDateString('es-CL') + ' ' + fechaInicio.toLocaleTimeString('es-CL', {hour: '2-digit', minute:'2-digit'});
                
                let terminoHtml = '<span class="text-muted">-</span>';
                if(r.fechaTerminoReunion) {
                    let fechaTerm = new Date(r.fechaTerminoReunion);
                    terminoHtml = `<i class="far fa-clock text-muted me-1"></i>${fechaTerm.toLocaleTimeString('es-CL', {hour: '2-digit', minute:'2-digit'})}`;
                }

                // Botones Acciones
                let accionesHtml = '';
                if (modoVisual === 'PROGRAMADA') {
                    accionesHtml = `
                        <div class="d-flex gap-1">
                            <button class="btn btn-sm btn-primary d-flex align-items-center" onclick="iniciarReunion(${r.idReunion}, '${r.nombreReunion}')"><i class="fas fa-play me-1"></i> Iniciar</button>
                            <a href="index.php?action=reunion_form&id=${r.idReunion}" class="btn btn-sm btn-secondary" title="Editar"><i class="fas fa-pencil-alt"></i></a>
                            <button class="btn btn-sm btn-danger" onclick="eliminarReunion(${r.idReunion})" title="Eliminar"><i class="fas fa-trash"></i></button>
                        </div>`;
                } else if (modoVisual === 'INICIADA') {
                    accionesHtml = `<a href="index.php?action=minuta_gestionar&id=${idMinuta}" class="text-decoration-none fw-bold text-primary"><i class="fas fa-edit me-1"></i> Iniciada</a>`;
                } else if (modoVisual === 'PENDIENTE') {
                    accionesHtml = `<div class="text-warning text-dark fw-bold" style="font-size: 0.85rem;"><i class="fas fa-clock me-1"></i> Finalizada esperando aprobación</div>`;
                } else if (modoVisual === 'APROBADA') {
                    accionesHtml = `<div class="text-success fw-bold"><i class="fas fa-check-circle me-1"></i> Finalizada con minuta aprobada</div>`;
                }

                html += `
                <tr>
                    <td class="fw-bold text-secondary">#${r.idReunion}</td>
                    <td><div class="fw-bold">${r.nombreReunion}</div></td>
                    <td class="small">${r.nombreComision || ''}</td>
                    <td><i class="far fa-calendar-alt text-muted me-1"></i> ${fechaStr}</td>
                    <td>${terminoHtml}</td>
                    <td>${accionesHtml}</td>
                </tr>`;
            });
            tableBody.innerHTML = html;
        }

        // 4. Actualizar Botones de Paginación
        function actualizarPaginacion(total, totalPages, currentPage) {
            const container = document.getElementById('paginacionContainer');
            if (!container) return;

            let html = `<div class="d-flex justify-content-between align-items-center w-100">
                          <span class="text-muted small">Total: <strong>${total}</strong></span>`;

            if (totalPages > 1) {
                html += `<nav><ul class="pagination pagination-sm mb-0">`;

                // Anterior
                let prevDisabled = (currentPage == 1) ? 'disabled' : '';
                html += `<li class="page-item ${prevDisabled}"><button class="page-link" onclick="window.cambiarPagina(${currentPage - 1})">&laquo;</button></li>`;

                // Números (Ventana deslizante)
                let startPage = Math.max(1, currentPage - 2);
                let endPage = Math.min(totalPages, currentPage + 2);

                if (startPage > 1) {
                    html += `<li class="page-item"><button class="page-link" onclick="window.cambiarPagina(1)">1</button></li>`;
                    if (startPage > 2) html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
                }

                for (let i = startPage; i <= endPage; i++) {
                    let active = (i == currentPage) ? 'active' : '';
                    html += `<li class="page-item ${active}"><button class="page-link" onclick="window.cambiarPagina(${i})">${i}</button></li>`;
                }

                if (endPage < totalPages) {
                    if (endPage < totalPages - 1) html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
                    html += `<li class="page-item"><button class="page-link" onclick="window.cambiarPagina(${totalPages})">${totalPages}</button></li>`;
                }

                // Siguiente
                let nextDisabled = (currentPage == totalPages) ? 'disabled' : '';
                html += `<li class="page-item ${nextDisabled}"><button class="page-link" onclick="window.cambiarPagina(${currentPage + 1})">&raquo;</button></li>`;

                html += `</ul></nav>`;
            }
            html += `</div>`;
            container.innerHTML = html;
        }

        // Hacer la función accesible globalmente
        window.cambiarPagina = function(page) {
            if(page > 0) cargarTabla(page);
        };

        // --- Event Listeners (Resetean a página 1) ---
        if(searchInput) {
            searchInput.addEventListener('input', function() {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(() => cargarTabla(1), 300);
            });
        }
        
        // Para selects y fechas
        const filters = document.querySelectorAll('#filtroComision, #filtroDesde, #filtroHasta');
        filters.forEach(input => {
            input.addEventListener('change', () => cargarTabla(1));
        });

        const btnLimpiar = document.getElementById('btnLimpiar');
        if(btnLimpiar){
            btnLimpiar.addEventListener('click', (e) => {
                e.preventDefault(); // Evita submit del form
                inputDesde.value = formatDate(primerDia);
                inputHasta.value = formatDate(hoy);
                inputComision.value = "";
                searchInput.value = "";
                cargarTabla(1);
            });
        }

        // Carga inicial
        cargarTabla(1);

        // --- SweetAlert Mensajes PHP ---
        const urlParams = new URLSearchParams(window.location.search);
        const msg = urlParams.get('msg');
        if (msg) {
            let title = '', text = '', icon = 'success';
            if (msg === 'guardado') { title = '¡Reunión Creada!'; text = 'La reunión ha sido programada exitosamente.'; } 
            else if (msg === 'editado') { title = '¡Cambios Guardados!'; text = 'La información ha sido actualizada.'; } 
            else if (msg === 'eliminado') { title = '¡Eliminado!'; text = 'La reunión ha sido eliminada.'; icon = 'warning'; }

            if (title) {
                Swal.fire({
                    title: title, text: text, icon: icon,
                    confirmButtonText: 'Aceptar', confirmButtonColor: '#0d6efd',
                    timer: 3000, timerProgressBar: true
                }).then(() => {
                    const url = new URL(window.location);
                    url.searchParams.delete('msg');
                    window.history.replaceState({}, '', url);
                });
            }
        }
    });
</script>