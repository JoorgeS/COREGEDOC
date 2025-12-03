<?php
// Configuración de Fechas por Defecto
$fechaHoy = date('Y-m-d');
$fechaInicioMes = date('Y-m-01');
$comisiones = $data['comisiones'] ?? [];
?>

<div class="container-fluid mt-4">
    
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php?action=minutas_dashboard">Minutas</a></li>
            <li class="breadcrumb-item active">Seguimiento General</li>
        </ol>
    </nav>

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
                        <input type="date" id="filtroDesde" class="form-control form-control-sm" value="<?php echo $fechaInicioMes; ?>">
                        <span class="mx-2 text-muted">-</span>
                        <input type="date" id="filtroHasta" class="form-control form-control-sm" value="<?php echo $fechaHoy; ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold text-muted small mb-1">Filtrar por Comisión</label>
                    <select id="filtroComision" class="form-select form-select-sm">
                        <option value="">Todas las Comisiones</option>
                        <?php foreach ($comisiones as $c): ?>
                            <option value="<?= $c['idComision'] ?>"><?= htmlspecialchars($c['nombreComision']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold text-muted small mb-1">Búsqueda Rápida</label>
                    <div class="d-flex">
                        <div class="input-group input-group-sm me-2">
                            <span class="input-group-text bg-white text-muted border-end-0"><i class="fas fa-search"></i></span>
                            <input type="text" id="filtroKeyword" class="form-control border-start-0 ps-0" placeholder="Escriba para buscar...">
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
        <div class="card-header bg-white py-3 border-0">
             <h6 class="m-0 fw-bold text-info"><i class="fas fa-tasks me-2"></i> Monitor de Estado</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 text-center">
                    <thead class="table-light">
                        <tr>
                            <th width="5%">ID</th>
                            <th width="20%" class="text-start">Comisión</th>
                            <th width="10%">Estado</th>
                            <th width="25%" class="text-start">Última Acción</th>
                            <th width="10%">Fecha Acción</th>
                            <th width="15%">Responsable</th>
                            <th width="5%">Ver</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyResultados">
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <div class="spinner-border text-primary"></div>
                                <p class="text-muted small mt-2">Cargando datos...</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="card-footer bg-white py-2 d-flex justify-content-between align-items-center">
             <small class="text-muted fw-bold" id="txtContador">Cargando...</small>
             
             <nav aria-label="Page navigation">
                 <ul class="pagination pagination-sm mb-0" id="paginationContainer">
                     </ul>
             </nav>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // Referencias al DOM
    const inputDesde = document.getElementById('filtroDesde');
    const inputHasta = document.getElementById('filtroHasta');
    const inputComision = document.getElementById('filtroComision');
    const inputKeyword = document.getElementById('filtroKeyword');
    const btnLimpiar = document.getElementById('btnLimpiar');
    const tbody = document.getElementById('tbodyResultados');
    const txtContador = document.getElementById('txtContador');
    const paginationContainer = document.getElementById('paginationContainer');
    
    // Variables de Estado
    let timerDebounce;
    let currentPage = 1;

    // --- FUNCIÓN PRINCIPAL DE CARGA ---
    // Acepta un parámetro 'page' (por defecto 1)
    function cargarDatos(page = 1) {
        currentPage = page; // Actualizamos estado global
        tbody.style.opacity = '0.5';

        const params = new URLSearchParams({
            action: 'api_filtrar_seguimiento',
            desde: inputDesde.value,
            hasta: inputHasta.value,
            comision: inputComision.value,
            keyword: inputKeyword.value,
            orderBy: 'idMinuta',
            orderDir: 'DESC',
            page: page // <--- Enviamos la página actual
        });

        fetch('index.php?' + params.toString())
            .then(response => response.json())
            .then(resp => {
                if(resp.status === 'success') {
                    renderTable(resp.data);
                    renderPagination(resp.total, resp.totalPages, resp.page); // Renderizamos botones
                    txtContador.innerText = `Total: ${resp.total} registros`;
                } else {
                    tbody.innerHTML = `<tr><td colspan="7" class="text-danger py-4">${resp.message || 'Error al cargar'}</td></tr>`;
                    txtContador.innerText = '0 registros';
                    paginationContainer.innerHTML = '';
                }
                tbody.style.opacity = '1';
            })
            .catch(err => {
                console.error(err);
                tbody.innerHTML = `<tr><td colspan="7" class="text-danger py-4">Error de conexión.</td></tr>`;
                tbody.style.opacity = '1';
            });
    }

    // --- RENDERIZADOR TABLA ---
    function renderTable(data) {
        tbody.innerHTML = '';

        if (!data || data.length === 0) {
            tbody.innerHTML = `<tr><td colspan="7" class="text-muted py-5">No se encontraron resultados.</td></tr>`;
            return;
        }

        data.forEach(m => {
            let badgeClass = 'bg-secondary';
            if(m.estadoMinuta === 'APROBADA') badgeClass = 'bg-success';
            else if(m.estadoMinuta === 'PENDIENTE') badgeClass = 'bg-warning text-dark';
            else if(m.estadoMinuta === 'REQUIERE_REVISION') badgeClass = 'bg-danger';

            let fechaFmt = m.ultima_fecha;
            try {
                const d = new Date(m.ultima_fecha);
                fechaFmt = d.toLocaleDateString('es-CL', {day:'2-digit', month:'2-digit'}) + ' ' + 
                           d.toLocaleTimeString('es-CL', {hour:'2-digit', minute:'2-digit'});
            } catch(e){}

            const row = `
                <tr>
                    <td class="fw-bold">#${m.idMinuta}</td>
                    <td class="text-start">${m.nombreComision || 'General'}</td>
                    <td><span class="badge ${badgeClass}">${m.estadoMinuta}</span></td>
                    <td class="text-start small text-muted">${m.ultimo_detalle || '-'}</td>
                    <td class="small">${fechaFmt}</td>
                    <td class="small">${m.ultimo_usuario || '-'}</td>
                    <td>
                        <a href="index.php?action=minuta_ver_historial&id=${m.idMinuta}" 
                           class="btn btn-sm btn-info text-white" title="Ver Historial">
                            <i class="fas fa-history"></i>
                        </a>
                    </td>
                </tr>
            `;
            tbody.innerHTML += row;
        });
    }

    // --- RENDERIZADOR PAGINACIÓN (BOTONES) ---
    function renderPagination(totalRecords, totalPages, currentPage) {
        paginationContainer.innerHTML = '';

        if (totalPages <= 1) return; // Si hay 1 o 0 páginas, no mostramos botones

        // Convertir a enteros por seguridad
        currentPage = parseInt(currentPage);
        totalPages = parseInt(totalPages);

        // Botón "Anterior"
        let prevDisabled = (currentPage === 1) ? 'disabled' : '';
        paginationContainer.innerHTML += `
            <li class="page-item ${prevDisabled}">
                <button class="page-link" onclick="cambiarPagina(${currentPage - 1})">&laquo;</button>
            </li>
        `;

        // Lógica para mostrar rango de páginas (Ej: 1 2 3 ... 10)
        let startPage = Math.max(1, currentPage - 2);
        let endPage = Math.min(totalPages, currentPage + 2);

        // Siempre mostrar página 1 si estamos lejos
        if (startPage > 1) {
            paginationContainer.innerHTML += `
                <li class="page-item"><button class="page-link" onclick="cambiarPagina(1)">1</button></li>
            `;
            if (startPage > 2) {
                paginationContainer.innerHTML += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            }
        }

        // Páginas centrales
        for (let i = startPage; i <= endPage; i++) {
            let active = (i === currentPage) ? 'active' : '';
            paginationContainer.innerHTML += `
                <li class="page-item ${active}">
                    <button class="page-link" onclick="cambiarPagina(${i})">${i}</button>
                </li>
            `;
        }

        // Siempre mostrar última página si estamos lejos
        if (endPage < totalPages) {
            if (endPage < totalPages - 1) {
                paginationContainer.innerHTML += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            }
            paginationContainer.innerHTML += `
                <li class="page-item"><button class="page-link" onclick="cambiarPagina(${totalPages})">${totalPages}</button></li>
            `;
        }

        // Botón "Siguiente"
        let nextDisabled = (currentPage === totalPages) ? 'disabled' : '';
        paginationContainer.innerHTML += `
            <li class="page-item ${nextDisabled}">
                <button class="page-link" onclick="cambiarPagina(${currentPage + 1})">&raquo;</button>
            </li>
        `;
    }

    // --- EVENTOS ---

    // Función global para que el onclick del HTML funcione
    window.cambiarPagina = function(p) {
        if(p < 1) return;
        cargarDatos(p);
    };

    // Al filtrar, siempre volvemos a la página 1
    const resetAndLoad = () => cargarDatos(1);

    inputDesde.addEventListener('change', resetAndLoad);
    inputHasta.addEventListener('change', resetAndLoad);
    inputComision.addEventListener('change', resetAndLoad);

    inputKeyword.addEventListener('input', function() {
        clearTimeout(timerDebounce);
        timerDebounce = setTimeout(resetAndLoad, 500);
    });

    btnLimpiar.addEventListener('click', function() {
        inputDesde.value = '<?php echo $fechaInicioMes; ?>';
        inputHasta.value = '<?php echo $fechaHoy; ?>';
        inputComision.value = '';
        inputKeyword.value = '';
        resetAndLoad();
    });

    // Carga inicial
    cargarDatos(1);

});
</script>