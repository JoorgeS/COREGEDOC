<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="text-primary fw-bold"><i class="fas fa-door-open me-2"></i> Sala de Reuniones</h3>
        <span class="badge bg-light text-dark border"><i class="far fa-calendar-alt me-1"></i> <?php echo date('d/m/Y'); ?></span>
    </div>

    <ul class="nav nav-tabs mb-4" id="asistenciaTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="marcar-tab" data-bs-toggle="tab" data-bs-target="#marcar" type="button" role="tab"><i class="fas fa-fingerprint me-2"></i>Autoconsulta</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="historial-tab" data-bs-toggle="tab" data-bs-target="#historial" type="button" role="tab"><i class="fas fa-history me-2"></i>Mi Historial</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="calendario-tab" data-bs-toggle="tab" data-bs-target="#calendario" type="button" role="tab"><i class="far fa-calendar me-2"></i>Calendario CORE</button>
        </li>
    </ul>

    <div class="tab-content" id="asistenciaTabsContent">
        
        <div class="tab-pane fade show active" id="marcar" role="tabpanel">
            <div class="row justify-content-center">
                <div class="col-lg-6">
                    <div id="panel-scan" class="card text-center shadow-sm p-5 border-0 bg-white">
                        <div class="mb-4 position-relative">
                            <div class="spinner-border text-primary" role="status" style="width: 4rem; height: 4rem;"></div>
                            <div class="position-absolute top-50 start-50 translate-middle text-primary"><i class="fas fa-wifi"></i></div>
                        </div>
                        <h4 class="text-dark fw-bold">Buscando Reunión Activa...</h4>
                        <p class="text-muted">El sistema detectará automáticamente si hay una sesión iniciada.</p>
                    </div>

                    <div id="panel-confirmar" class="card shadow-lg border-primary" style="display: none;">
                        <div class="card-header bg-primary text-white text-center py-3">
                            <h4 class="mb-0"><i class="fas fa-check-circle me-2"></i> Reunión Encontrada</h4>
                        </div>
                        <div class="card-body text-center p-5">
                            <h5 class="text-muted small mb-2 text-uppercase ls-1">Ingresando a:</h5>
                            <h2 id="nombre-reunion" class="fw-bold mb-4 text-primary display-6">...</h2>
                            <button class="btn btn-primary btn-lg w-100 py-3 rounded-pill shadow-sm hover-scale" onclick="marcarPresente()">
                                <i class="fas fa-user-check fa-lg me-2"></i> CONFIRMAR ASISTENCIA
                            </button>
                            <input type="hidden" id="idMinutaActiva"><input type="hidden" id="idReunionActiva">
                        </div>
                    </div>

                    <div id="panel-ok" class="card text-center shadow p-5 border-success bg-success bg-opacity-10" style="display: none;">
                        <div class="mb-3"><i class="fas fa-check-circle text-success display-1"></i></div>
                        <h2 class="text-success fw-bold">¡Presente!</h2>
                        <p class="lead">Su asistencia ha sido registrada exitosamente.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="historial" role="tabpanel">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h5 class="card-title mb-4 text-secondary">Mis Asistencias Registradas</h5>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Reunión</th>
                                    <th>Fecha Reunión</th>
                                    <th>Hora Marcaje</th>
                                    <th>Método</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($data['historial'])): ?>
                                    <tr><td colspan="4" class="text-center py-4">No hay registros recientes.</td></tr>
                                <?php else: ?>
                                    <?php foreach($data['historial'] as $h): ?>
                                    <tr>
                                        <td class="fw-bold text-primary"><?php echo htmlspecialchars($h['nombreReunion']); ?></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($h['fechaInicioReunion'])); ?></td>
                                        <td><?php echo date('H:i:s', strtotime($h['fechaRegistroAsistencia'])); ?></td>
                                        <td><span class="badge bg-secondary"><?php echo $h['origenAsistencia']; ?></span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="calendario" role="tabpanel">
            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <iframe src="index.php?action=reunion_calendario&embedded=true" style="width: 100%; height: 600px; border: none;"></iframe>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
    let intervalAsistencia;
    document.addEventListener("DOMContentLoaded", () => { iniciarScanner(); });
    function iniciarScanner() { intervalAsistencia = setInterval(buscarReunion, 3000); buscarReunion(); }
    
    function buscarReunion() {
        const panelScan = document.getElementById('panel-scan');
        // Solo buscar si el panel de scanner es visible
        if(panelScan && panelScan.offsetParent !== null) {
            fetch('index.php?action=api_asistencia_check')
            .then(r => r.json())
            .then(resp => {
                if (resp.status === 'active') { mostrarConfirmacion(resp.data); }
            })
            .catch(err => console.error(err));
        }
    }

    function mostrarConfirmacion(data) {
        clearInterval(intervalAsistencia);
        document.getElementById('idMinutaActiva').value = data.t_minuta_idMinuta;
        document.getElementById('idReunionActiva').value = data.idReunion;
        document.getElementById('nombre-reunion').textContent = data.nombreReunion;
        document.getElementById('panel-scan').style.display = 'none';
        document.getElementById('panel-confirmar').style.display = 'block';
        if (navigator.vibrate) navigator.vibrate(200);
    }

    function marcarPresente() {
        const idMinuta = document.getElementById('idMinutaActiva').value;
        const idReunion = document.getElementById('idReunionActiva').value;
        const btn = document.querySelector('#panel-confirmar button');
        btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Registrando...';

        fetch('index.php?action=api_asistencia_marcar', {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ idMinuta, idReunion })
        })
        .then(r => r.json())
        .then(d => {
            if(d.status === 'success') {
                document.getElementById('panel-confirmar').style.display = 'none';
                document.getElementById('panel-ok').style.display = 'block';
            } else {
                Swal.fire('Error', d.message, 'error'); btn.disabled = false;
            }
        });
    }
</script>