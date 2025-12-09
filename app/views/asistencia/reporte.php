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
                <label class="form-label d-none d-md-block">&nbsp;</label>
                <button type="button" class="btn btn-outline-secondary w-100" onclick="limpiarFiltros()" title="Limpiar Filtros">
                    <i class="fas fa-eraser me-2"></i>
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
                    <tr>
                        <td colspan="4" class="text-center p-4">Cargando datos...</td>
                    </tr>
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

        // Filtros automáticos
        ['fechaDesde', 'fechaHasta', 'filtroComision'].forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                el.addEventListener('change', () => cargarTabla(1));
            }
        });
    });

    // Variables por defecto (Esto va al inicio del script)
    const defaultDesde = '<?php echo date('Y-m-01'); ?>';
    const defaultHasta = '<?php echo date('Y-m-t'); ?>';

    // Función de la Goma
    function limpiarFiltros() {
        document.getElementById('fechaDesde').value = defaultDesde;
        document.getElementById('fechaHasta').value = defaultHasta;
        document.getElementById('filtroComision').value = ""; // Resetea el combo
        cargarTabla(1); // Recarga la tabla
    }

    function cargarTabla(page) {
        const desde = document.getElementById('fechaDesde').value;
        const hasta = document.getElementById('fechaHasta').value;
        
        const comision = document.getElementById('filtroComision').value;

        const tbody = document.getElementById('tablaResultados');
        tbody.innerHTML = '<tr><td colspan="3" class="text-center p-5 text-muted"><div class="spinner-border text-primary mb-2"></div><br>Cargando datos...</td></tr>';

        fetch(`index.php?action=api_reporte_asistencia&page=${page}&desde=${desde}&hasta=${hasta}&comision=${comision}`)
            .then(res => res.json())
            .then(res => {
                if (res.status === 'success') {
                    renderizarTabla(res.data);
                    renderizarPaginacion(res.current_page, res.pages);
                } else {
                    tbody.innerHTML = `<tr><td colspan="3" class="text-center text-danger p-4">${res.message}</td></tr>`;
                }
            })
            .catch(err => {
                console.error(err);
                tbody.innerHTML = '<tr><td colspan="3" class="text-center text-danger p-4">Error de conexión.</td></tr>';
            });
    }

    function renderizarTabla(reuniones) {
        const tbody = document.getElementById('tablaResultados');

        if (reuniones.length === 0) {
            tbody.innerHTML = '<tr><td colspan="3" class="text-center p-5 text-muted fst-italic">No se encontraron registros.</td></tr>';
            return;
        }

        let html = '';

        reuniones.forEach(reunion => {
            // Filtro Presentes
            const presentes = reunion.asistentes.filter(a => a.estado === 'PRESENTE' || a.estado === 'ATRASADO');

            html += `
                <tr class="bg-light border-top border-3 border-primary table-group-divider">
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

            if (presentes.length > 0) {
                html += `
                    <tr class="bg-white fade-in">
                        <td colspan="3" class="p-0">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover mb-0">
                                    <thead class="bg-white text-secondary border-bottom">
                                        <tr>
                                            <th class="ps-5 py-2 w-50 small text-uppercase">Consejero Regional</th>
                                            <th class="py-2 w-30 small text-uppercase">Tipo de Registro</th>
                                            <th class="py-2 w-20 small text-uppercase">Hora</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                `;

                presentes.forEach(asistente => {
                    let icon = 'fa-user-edit';
                    let colorClass = 'text-muted';
                    let estiloNombre = 'fw-bold text-dark';
                    let textoOrigen = 'Secretario Técnico';

                    if (asistente.origen && asistente.origen.includes('Autogestión')) {
                        icon = 'fa-mobile-alt';
                        colorClass = 'text-success fw-bold';
                        textoOrigen = 'Autogestión';
                    }

                    let etiquetaHora = `<span class="badge bg-light text-dark border font-monospace">${asistente.hora}</span>`;

                    if (asistente.atrasado) { // Usamos el booleano 'atrasado' que envia el controlador
                        estiloNombre = 'fw-bold text-warning-emphasis';
                        etiquetaHora += ` <span class="badge bg-warning text-dark ms-1" style="font-size:0.65rem">ATRASADO</span>`;
                    }

                    html += `
                        <tr>
                            <td class="ps-5 py-2 ${estiloNombre}">${asistente.nombre}</td>
                            <td class="py-2 small ${colorClass}"><i class="fas ${icon} me-1"></i> ${textoOrigen}</td>
                            <td class="py-2">${etiquetaHora}</td>
                        </tr>
                    `;
                });
                html += `</tbody></table></div></td></tr>`;
            } else {
                html += `<tr><td colspan="3" class="text-center py-3 text-muted fst-italic bg-white border-bottom">Sin asistentes presentes.</td></tr>`;
            }
        });

        tbody.innerHTML = html;
    }

    function renderizarPaginacion(current, total) {
        const pag = document.getElementById('paginacion');
        if (!pag) return;

        let html = '';

        if (total > 1) {
            // Botón Anterior
            html += `<li class="page-item ${current == 1 ? 'disabled' : ''}">
                        <button class="page-link" onclick="cargarTabla(${current - 1})">Anterior</button>
                     </li>`;

            // Números de Página
            for (let i = 1; i <= total; i++) {
                // Mostrar primera, última, y cercanas a la actual
                if (i == 1 || i == total || (i >= current - 2 && i <= current + 2)) {
                    html += `<li class="page-item ${current == i ? 'active' : ''}">
                                <button class="page-link" onclick="cargarTabla(${i})">${i}</button>
                             </li>`;
                } else if (i == current - 3 || i == current + 3) {
                    html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
                }
            }

            // Botón Siguiente
            html += `<li class="page-item ${current == total ? 'disabled' : ''}">
                        <button class="page-link" onclick="cargarTabla(${current + 1})">Siguiente</button>
                     </li>`;
        }

        pag.innerHTML = html;
    }

    function generarPDF() {
        const desde = document.getElementById('fechaDesde').value;
        const hasta = document.getElementById('fechaHasta').value;
        const comision = document.getElementById('filtroComision').value;
        window.open(`index.php?action=generar_reporte_pdf&desde=${desde}&hasta=${hasta}&comision=${comision}`, '_blank');
    }
</script>