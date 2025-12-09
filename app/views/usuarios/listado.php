<div class="container-fluid mt-4">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="text-primary fw-bold"><i class="fas fa-users-cog me-2"></i> Gestión de Usuarios</h3>
        <a href="index.php?action=usuario_crear" class="btn btn-success shadow-sm">
            <i class="fas fa-user-plus me-2"></i> Nuevo Usuario
        </a>
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
                    <label class="form-label fw-bold text-muted small mb-1">Búsqueda Rápida</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white text-muted border-end-0"><i class="fas fa-search"></i></span>
                        <input type="text" id="filtroKeyword" class="form-control border-start-0 ps-0" 
                               placeholder="Nombre, apellido o correo...">
                    </div>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-bold text-muted small mb-1">Filtrar por Rol</label>
                    <select id="filtroRol" class="form-select">
                        <option value="">Todos los Roles</option>
                        <?php foreach($data['roles'] as $r): ?>
                            <option value="<?php echo $r['idTipoUsuario']; ?>">
                                <?php echo htmlspecialchars($r['descTipoUsuario']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-bold text-muted small mb-1">Filtrar por Partido</label>
                    <select id="filtroPartido" class="form-select">
                        <option value="">Todos los Partidos</option>
                        <?php foreach($data['partidos'] as $p): ?>
                            <option value="<?php echo $p['idPartido']; ?>">
                                <?php echo htmlspecialchars($p['nombrePartido']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2 d-flex align-items-end">
                    <button type="button" id="btnLimpiar" class="btn btn-outline-secondary w-100">
                        <i class="fas fa-eraser me-1"></i> 
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
                            <th width="30%">Nombre</th>
                            <th width="25%">Correo</th>
                            <th width="15%">Rol</th>
                            <th width="20%">Partido</th>
                            <th width="10%" class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyResultados">
                        <tr>
                            <td colspan="5" class="text-center py-5">
                                <div class="spinner-border text-primary"></div>
                                <p class="text-muted small mt-2">Cargando usuarios...</p>
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
// Todo el JS que maneja la lógica de AJAX y renderizado debe ir aquí.
// Lo adjunto de nuevo para asegurar que tienes la versión con paginación.
document.addEventListener('DOMContentLoaded', function() {
    
    // Referencias DOM
    const inputKeyword = document.getElementById('filtroKeyword');
    const selectRol = document.getElementById('filtroRol');
    const selectPartido = document.getElementById('filtroPartido');
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
            action: 'api_filtrar_usuarios',
            keyword: inputKeyword.value,
            rol: selectRol.value,
            partido: selectPartido.value,
            page: page
        });

        fetch('index.php?' + params.toString())
            .then(r => r.json())
            .then(resp => {
                if(resp.status === 'success') {
                    renderTable(resp.data);
                    renderPagination(resp.total, resp.totalPages, resp.page);
                    txtContador.innerText = `Total: ${resp.total} usuarios`;
                } else {
                    tbody.innerHTML = `<tr><td colspan="5" class="text-danger py-4 text-center">${resp.message || 'Error al cargar'}</td></tr>`;
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

    // --- RENDERIZAR TABLA ---
    function renderTable(data) {
        tbody.innerHTML = '';

        if (!data || data.length === 0) {
            tbody.innerHTML = `<tr><td colspan="5" class="text-muted py-5 text-center">No se encontraron usuarios.</td></tr>`;
            return;
        }

        data.forEach(u => {
            let badgeClass = 'bg-secondary';
            if(u.tipoUsuario_id == 6) badgeClass = 'bg-danger'; 
            if(u.tipoUsuario_id == 1) badgeClass = 'bg-primary'; 
            if(u.tipoUsuario_id == 2) badgeClass = 'bg-warning text-dark'; 

            const row = `
                <tr>
                    <td>
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle bg-light text-secondary d-flex align-items-center justify-content-center me-2 border" style="width: 35px; height: 35px;">
                                <i class="fas fa-user"></i>
                            </div>
                            <div>
                                <div class="fw-bold text-dark">${u.pNombre} ${u.aPaterno}</div>
                            </div>
                        </div>
                    </td>
                    <td>${u.correo}</td>
                    <td><span class="badge ${badgeClass}">${u.descTipoUsuario || 'N/A'}</span></td>
                    <td><small class="text-muted">${u.nombrePartido || '-'}</small></td>
                    <td class="text-end">
                        <a href="index.php?action=usuario_editar&id=${u.idUsuario}" class="btn btn-sm btn-outline-primary me-1" title="Editar">
                            <i class="fas fa-edit"></i>
                        </a>
                        <button onclick="borrarUsuario(${u.idUsuario})" class="btn btn-sm btn-outline-danger" title="Eliminar">
                            <i class="fas fa-trash-alt"></i>
                        </button>
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

    // --- EVENTOS ---
    window.cambiarPagina = (p) => { if(p > 0) cargarDatos(p); };
    
    inputKeyword.addEventListener('input', function() {
        clearTimeout(timerDebounce);
        timerDebounce = setTimeout(() => cargarDatos(1), 500);
    });

    selectRol.addEventListener('change', () => cargarDatos(1));
    selectPartido.addEventListener('change', () => cargarDatos(1));

    btnLimpiar.addEventListener('click', function() {
        inputKeyword.value = '';
        selectRol.value = '';
        selectPartido.value = '';
        cargarDatos(1);
    });

    // Carga inicial (LA CLAVE)
    cargarDatos(1);
});

// FUNCIÓN BORRAR (Global)
function borrarUsuario(id) {
    Swal.fire({
        title: '¿Eliminar Usuario?',
        text: "El usuario perderá acceso al sistema.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Sí, eliminar'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `index.php?action=usuario_eliminar&id=${id}`;
        }
    });
}
</script>