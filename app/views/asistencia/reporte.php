<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2 text-primary fw-bold"><i class="bi bi-file-earmark-bar-graph me-2"></i>Reportes de Asistencia</h1>
    
    <button class="btn btn-danger" onclick="generarPDF()">
        <i class="fas fa-file-pdf me-2"></i>Descargar PDF
    </button>
</div>

<div class="card shadow-sm mb-4 border-0">
    <div class="card-body bg-light">
        <form id="filtroForm" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-bold small text-muted">Mes / Rango Inicio</label>
                <input type="date" class="form-control" id="fechaDesde" value="<?php echo date('Y-m-01'); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold small text-muted">Rango Fin</label>
                <input type="date" class="form-control" id="fechaHasta" value="<?php echo date('Y-m-t'); ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold small text-muted">Comisión</label>
                <select class="form-select" id="filtroComision">
                    <option value="">-- Todas las Comisiones --</option>
                    <?php foreach ($data['comisiones'] as $c): ?>
                        <option value="<?php echo $c['idComision']; ?>"><?php echo htmlspecialchars($c['nombreComision']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="button" class="btn btn-primary w-100" onclick="cargarTabla(1)">
                    <i class="fas fa-filter me-1"></i> Filtrar
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light text-secondary small text-uppercase">
                    <tr>
                        <th class="ps-4">Fecha</th>
                        <th>Consejero</th>
                        <th>Comisión / Reunión</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody id="tablaResultados">
                    <tr><td colspan="4" class="text-center p-4">Cargando datos...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer bg-white py-3">
        <nav>
            <ul class="pagination justify-content-center mb-0" id="paginacion"></ul>
        </nav>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        cargarTabla(1);
    });

    function cargarTabla(page) {
        const desde = document.getElementById('fechaDesde').value;
        const hasta = document.getElementById('fechaHasta').value;
        const comision = document.getElementById('filtroComision').value;

        const tbody = document.getElementById('tablaResultados');
        tbody.innerHTML = '<tr><td colspan="4" class="text-center p-4 text-muted"><i class="fas fa-spinner fa-spin me-2"></i>Cargando...</td></tr>';

        fetch(`index.php?action=api_reporte_asistencia&page=${page}&desde=${desde}&hasta=${hasta}&comision=${comision}`)
            .then(res => res.json())
            .then(res => {
                if (res.status === 'success') {
                    renderizarTabla(res.data);
                    renderizarPaginacion(res.current_page, res.pages);
                } else {
                    tbody.innerHTML = `<tr><td colspan="4" class="text-center text-danger p-4">${res.message}</td></tr>`;
                }
            })
            .catch(err => {
                console.error(err);
                tbody.innerHTML = '<tr><td colspan="4" class="text-center text-danger p-4">Error de conexión</td></tr>';
            });
    }

function renderizarTabla(reuniones) {
        const tbody = document.getElementById('tablaResultados');
        
        if (reuniones.length === 0) {
            tbody.innerHTML = '<tr><td colspan="3" class="text-center p-5 text-muted">No se encontraron reuniones en este periodo.</td></tr>';
            return;
        }

        let html = '';

        reuniones.forEach(reunion => {
            // 1. Filtrado Seguro de Presentes
            // Convertimos a mayúsculas y quitamos espacios para comparar
            const presentes = reunion.asistentes.filter(a => {
                const estado = (a.estado || '').toUpperCase().trim();
                return estado === 'PRESENTE' || estado === 'ATRASADO';
            });

            // 2. Cabecera de la Reunión (Siempre visible)
            html += `
                <tr class="bg-light border-top border-3 border-primary">
                    <td colspan="3" class="p-3">
                        <div class="row align-items-center">
                            <div class="col-md-9">
                                <h6 class="text-primary fw-bold text-uppercase mb-1">
                                    <i class="far fa-calendar-alt me-2"></i>${reunion.fecha_texto} - ${reunion.hora_inicio} hrs.
                                </h6>
                                <div class="fs-5 fw-bold text-dark">${reunion.titulo}</div>
                                <div class="text-muted small fst-italic">
                                    <i class="fas fa-sitemap me-1"></i> ${reunion.comision}
                                </div>
                            </div>
                            <div class="col-md-3 text-end">
                                <span class="badge ${presentes.length > 0 ? 'bg-success' : 'bg-secondary'} fs-6">
                                    <i class="fas fa-users me-1"></i> ${presentes.length} Asistentes
                                </span>
                            </div>
                        </div>
                    </td>
                </tr>
            `;

            // 3. Listado de Asistentes (Si hay)
            if (presentes.length > 0) {
                html += `
                    <tr class="bg-white fade-in">
                        <td colspan="3" class="p-0">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover mb-0">
                                    <thead class="bg-white text-secondary border-bottom">
                                        <tr>
                                            <th class="ps-5 py-2 w-50">Consejero Regional</th>
                                            <th class="py-2 w-30">Tipo de Registro</th>
                                            <th class="py-2 w-20">Hora</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                `;

                presentes.forEach(asistente => {
                    let icon = 'fa-user-check';
                    let colorClass = 'text-muted';
                    let estiloNombre = 'fw-bold text-dark';

                    // Destacar si es autogestión
                    if (asistente.origen && asistente.origen.includes('Autogestión')) {
                        icon = 'fa-mobile-alt';
                        colorClass = 'text-success fw-bold';
                    }

                    // Destacar si está atrasado
                    let etiquetaHora = `<span class="badge bg-light text-dark border font-monospace">${asistente.hora_marca}</span>`;
                    if (asistente.estado === 'ATRASADO') {
                        estiloNombre = 'fw-bold text-warning-emphasis';
                        etiquetaHora += ` <span class="badge bg-warning text-dark ms-1" style="font-size:0.65rem">ATRASADO</span>`;
                    }

                    html += `
                        <tr>
                            <td class="ps-5 py-2 ${estiloNombre}">
                                ${asistente.nombre}
                            </td>
                            <td class="py-2 small ${colorClass}">
                                <i class="fas ${icon} me-1"></i> ${asistente.origen}
                            </td>
                            <td class="py-2">
                                ${etiquetaHora}
                            </td>
                        </tr>
                    `;
                });

                html += `   </tbody>
                          </table>
                        </div>
                        <div class="mb-4"></div> </td>
                </tr>`;
            } else {
                // Mensaje si nadie fue (pero la reunión existe)
                html += `
                    <tr>
                        <td colspan="3" class="text-center py-3 text-muted fst-italic bg-white border-bottom">
                            <i class="fas fa-info-circle me-1"></i> No se registraron asistentes presentes para esta reunión.
                        </td>
                    </tr>
                `;
            }
        });

        tbody.innerHTML = html;
        // Limpiamos paginación numérica si existía
        const pagDiv = document.getElementById('paginacion');
        if(pagDiv) pagDiv.innerHTML = '';
    }
    function renderizarPaginacion(current, total) {
        const pag = document.getElementById('paginacion');
        let html = '';
        
        // Prev
        html += `<li class="page-item ${current == 1 ? 'disabled' : ''}">
                    <button class="page-link" onclick="cargarTabla(${current - 1})">Anterior</button>
                 </li>`;
        
        // Números (Simple)
        for (let i = 1; i <= total; i++) {
            if (i == 1 || i == total || (i >= current - 1 && i <= current + 1)) {
                html += `<li class="page-item ${current == i ? 'active' : ''}">
                            <button class="page-link" onclick="cargarTabla(${i})">${i}</button>
                         </li>`;
            } else if (i == current - 2 || i == current + 2) {
                html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            }
        }

        // Next
        html += `<li class="page-item ${current == total ? 'disabled' : ''}">
                    <button class="page-link" onclick="cargarTabla(${current + 1})">Siguiente</button>
                 </li>`;

        pag.innerHTML = html;
    }

    function generarPDF() {
        const desde = document.getElementById('fechaDesde').value;
        const hasta = document.getElementById('fechaHasta').value;
        const comision = document.getElementById('filtroComision').value;
        
        window.open(`index.php?action=generar_reporte_pdf&desde=${desde}&hasta=${hasta}&comision=${comision}`, '_blank');
    }
</script>