<div class="container-fluid mt-4">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="text-primary fw-bold"><i class="fas fa-sitemap me-2"></i> Gestión de Comisiones</h3>
        <a href="index.php?action=comision_crear" class="btn btn-success shadow-sm">
            <i class="fas fa-plus-circle me-2"></i> Nueva Comisión
        </a>
    </div>

    <div class="card shadow-sm mb-4 border-0">
        <div class="card-body p-4"> <div class="mb-3">
                <h6 class="fw-bold text-secondary text-uppercase mb-0" style="font-size: 0.85rem;">
                    <i class="fas fa-filter me-2"></i>Filtros
                </h6>
            </div>

            <form id="formFiltros" class="row g-3 align-items-end">
                <div class="col-md-10">
                    <label class="form-label fw-bold text-muted small mb-1">Búsqueda Rápida</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white text-muted border-end-0"><i class="fas fa-search"></i></span>
                        <input type="text" id="filtroKeyword" class="form-control border-start-0 ps-0" 
                               placeholder="Buscar por comisión, presidente o vicepresidente...">
                    </div>
                </div>
                <div class="col-md-2">
                    <button type="button" id="btnLimpiar" class="btn btn-outline-secondary w-100" title="Limpiar Filtro">
                        <i class="fas fa-eraser me-1"></i> Limpiar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th width="35%">Nombre Comisión</th>
                            <th width="20%">Presidente</th>
                            <th width="20%">Vicepresidente</th>
                            <th width="10%" class="text-center">Estado</th> <th width="15%" class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyResultados">
                        <tr>
                            <td colspan="5" class="text-center py-5">
                                <div class="spinner-border text-primary"></div>
                                <p class="text-muted small mt-2">Cargando comisiones...</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card-footer bg-white py-2 d-flex justify-content-between align-items-center">
             <small class="text-muted fw-bold" id="txtContador">Cargando...</small>
             <nav aria-label="Page navigation">
                 <ul class="pagination pagination-sm mb-0" id="paginationContainer"></ul>
             </nav>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // Referencias
    const inputKeyword = document.getElementById('filtroKeyword');
    const btnLimpiar = document.getElementById('btnLimpiar');
    const tbody = document.getElementById('tbodyResultados');
    const txtContador = document.getElementById('txtContador');
    const paginationContainer = document.getElementById('paginationContainer');
    
    let timerDebounce;
    let currentPage = 1;

    // --- FUNCIÓN CARGAR DATOS ---
    function cargarDatos(page = 1) {
        currentPage = page;
        tbody.style.opacity = '0.5';

        const params = new URLSearchParams({
            action: 'api_filtrar_comisiones',
            keyword: inputKeyword.value,
            page: page
        });

        fetch('index.php?' + params.toString())
            .then(r => r.json())
            .then(resp => {
                if(resp.status === 'success') {
                    renderTable(resp.data);
                    renderPagination(resp.total, resp.totalPages, resp.page);
                    txtContador.innerText = `Total: ${resp.total} comisiones`;
                } else {
                    tbody.innerHTML = `<tr><td colspan="5" class="text-danger py-4 text-center">${resp.message || 'Error'}</td></tr>`;
                    txtContador.innerText = '0';
                    paginationContainer.innerHTML = '';
                }
                tbody.style.opacity = '1';
            })
            .catch(e => {
                console.error(e);
                tbody.innerHTML = `<tr><td colspan="5" class="text-danger py-4 text-center">Error de conexión</td></tr>`;
                tbody.style.opacity = '1';
            });
    }

    // --- RENDERIZAR TABLA (MODIFICADO VISUALMENTE) ---
    function renderTable(data) {
        tbody.innerHTML = '';

        if (!data || data.length === 0) {
            tbody.innerHTML = `<tr><td colspan="5" class="text-muted py-5 text-center">No se encontraron comisiones.</td></tr>`;
            return;
        }

        data.forEach(c => {
            // 1. MODIFICACIÓN PRESIDENTE: Ícono limpio en vez de círculo
            let htmlPres = '<span class="text-muted small">-</span>';
            if(c.nombrePresidente) {
                htmlPres = `
                    <div class="d-flex align-items-center">
                        <i class="fas fa-user-tie text-primary fs-5 me-2"></i>
                        <span class="fw-bold text-dark small">${c.nombrePresidente}</span>
                    </div>`;
            }

            // 2. MODIFICACIÓN VICEPRESIDENTE: Ícono limpio
            let htmlVice = '<span class="text-muted small">-</span>';
            if(c.nombreVicepresidente) {
                htmlVice = `
                    <div class="d-flex align-items-center">
                        <i class="fas fa-user text-secondary fs-5 me-2"></i>
                        <span class="text-secondary small">${c.nombreVicepresidente}</span>
                    </div>`;
            }

            // 3. MODIFICACIÓN ESTADO: Fondo sólido y letras blancas
            let estadoBadge = c.vigencia == 1 
                ? '<span class="badge bg-success text-white px-3 py-2">Activa</span>' 
                : '<span class="badge bg-secondary text-white px-3 py-2">Inactiva</span>';

            // Botón Acción (Toggle)
            let btnAction = c.vigencia == 1
                ? `<button onclick="cambiarEstado(${c.idComision}, 1, '${c.nombreComision}')" class="btn btn-sm btn-outline-danger" title="Deshabilitar"><i class="fas fa-ban"></i></button>`
                : `<button onclick="cambiarEstado(${c.idComision}, 0, '${c.nombreComision}')" class="btn btn-sm btn-outline-success" title="Habilitar"><i class="fas fa-check"></i></button>`;

            const row = `
                <tr>
                    <td class="fw-bold text-dark">${c.nombreComision}</td>
                    <td>${htmlPres}</td>
                    <td>${htmlVice}</td>
                    <td class="text-center align-middle">${estadoBadge}</td>
                    <td class="text-end align-middle">
                        <a href="index.php?action=comision_editar&id=${c.idComision}" class="btn btn-sm btn-outline-primary me-1" title="Editar">
                            <i class="fas fa-edit"></i>
                        </a>
                        ${btnAction}
                    </td>
                </tr>
            `;
            tbody.innerHTML += row;
        });
    }

    // --- RENDERIZAR PAGINACIÓN ---
    function renderPagination(total, totalPages, current) {
        paginationContainer.innerHTML = '';
        if (totalPages <= 1) return;

        current = parseInt(current);
        
        let prevDisabled = (current === 1) ? 'disabled' : '';
        paginationContainer.innerHTML += `
            <li class="page-item ${prevDisabled}">
                <button class="page-link" onclick="window.cambiarPagina(${current - 1})">&laquo;</button>
            </li>`;

        let start = Math.max(1, current - 2);
        let end = Math.min(totalPages, current + 2);

        if(start > 1) {
             paginationContainer.innerHTML += `<li class="page-item"><button class="page-link" onclick="window.cambiarPagina(1)">1</button></li>`;
             if(start > 2) paginationContainer.innerHTML += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        }

        for (let i = start; i <= end; i++) {
            let active = (i === current) ? 'active' : '';
            paginationContainer.innerHTML += `
                <li class="page-item ${active}">
                    <button class="page-link" onclick="window.cambiarPagina(${i})">${i}</button>
                </li>`;
        }

        if(end < totalPages) {
             if(end < totalPages - 1) paginationContainer.innerHTML += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
             paginationContainer.innerHTML += `<li class="page-item"><button class="page-link" onclick="window.cambiarPagina(${totalPages})">${totalPages}</button></li>`;
        }

        let nextDisabled = (current === totalPages) ? 'disabled' : '';
        paginationContainer.innerHTML += `
            <li class="page-item ${nextDisabled}">
                <button class="page-link" onclick="window.cambiarPagina(${current + 1})">&raquo;</button>
            </li>`;
    }

    // Globales
    window.cambiarPagina = (p) => { if(p > 0) cargarDatos(p); };
    window.recargarTabla = () => cargarDatos(currentPage);

    inputKeyword.addEventListener('input', function() {
        clearTimeout(timerDebounce);
        timerDebounce = setTimeout(() => cargarDatos(1), 500);
    });

    btnLimpiar.addEventListener('click', function() {
        inputKeyword.value = '';
        cargarDatos(1);
    });

    cargarDatos(1);
});

// FUNCIÓN CAMBIAR ESTADO
function cambiarEstado(id, estadoActual, nombre) {
    const accion = estadoActual == 1 ? 'Deshabilitar' : 'Habilitar';
    const color = estadoActual == 1 ? '#d33' : '#198754';
    const msg = estadoActual == 1 
        ? "La comisión no aparecerá en nuevas reuniones." 
        : "La comisión volverá a estar disponible.";

    Swal.fire({
        title: `¿${accion} Comisión?`,
        text: `${nombre}: ${msg}`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: color,
        confirmButtonText: `Sí, ${accion}`,
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('index.php?action=api_comision_estado', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ id: id, estadoActual: estadoActual })
            })
            .then(r => r.json())
            .then(resp => {
                if(resp.status === 'success') {
                    Swal.fire({
                        title: 'Actualizado',
                        text: resp.message,
                        icon: 'success',
                        timer: 1500,
                        showConfirmButton: false
                    });
                    window.recargarTabla();
                } else {
                    Swal.fire('Error', resp.message, 'error');
                }
            })
            .catch(err => Swal.fire('Error', 'Fallo de conexión', 'error'));
        }
    });
}
</script>