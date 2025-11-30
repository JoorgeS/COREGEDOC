<?php
$c_azul = '#0071bc';
$c_naranja = '#f7931e';
$c_verde = '#00a650';
$c_gris = '#808080';
?>

<!-- IMPORTANTE: SweetAlert2 CDN -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    :root {
        --inst-azul: #0071bc;
        --inst-verde: #00a650;
        --inst-naranja: #f7931e;
        --inst-gris: #808080;
    }

    /* Pestañas */
    .nav-tabs-inst { border-bottom: 2px solid #e0e0e0; }
    .nav-tabs-inst .nav-link { color: var(--inst-gris); font-weight: 600; border: none; padding: 12px 20px; transition: all 0.3s; }
    .nav-tabs-inst .nav-link:hover { color: var(--inst-azul); background-color: #f8f9fa; }
    .nav-tabs-inst .nav-link.active { color: var(--inst-azul); border-bottom: 3px solid var(--inst-azul); background: transparent; }

    /* Tarjetas */
    .card-status { border: 1px solid #e0e0e0; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); overflow: hidden; transition: all 0.3s; }
    
    /* Animación Pulso (Buscando) */
    .scanning-pulse { width: 80px; height: 80px; background: rgba(0, 113, 188, 0.1); color: var(--inst-azul); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; animation: pulse 2s infinite; }
    @keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(0, 113, 188, 0.4); } 70% { box-shadow: 0 0 0 20px rgba(0, 113, 188, 0); } 100% { box-shadow: 0 0 0 0 rgba(0, 113, 188, 0); } }

    /* Botón Acción */
    .btn-action-main { background: var(--inst-naranja); border: none; color: white; font-weight: bold; text-transform: uppercase; padding: 15px 30px; border-radius: 50px; box-shadow: 0 4px 10px rgba(247, 147, 30, 0.3); transition: all 0.3s; width: 100%; }
    .btn-action-main:hover:not(:disabled) { background: #e87b00; transform: translateY(-2px); box-shadow: 0 6px 15px rgba(247, 147, 30, 0.4); color: white; }
    .btn-action-main:disabled { background: var(--inst-gris); cursor: not-allowed; opacity: 0.7; box-shadow: none; }

    /* Estado En Reunión */
    .status-in-meeting { border-left: 6px solid var(--inst-verde); }
    .live-indicator { display: inline-block; width: 10px; height: 10px; background: red; border-radius: 50%; animation: blink 1s infinite; margin-right: 5px; }
    @keyframes blink { 50% { opacity: 0; } }
</style>

<div class="container-fluid py-5">
    
    <div class="d-flex justify-content-between align-items-center mb-5">
        <div>
            <h2 class="fw-bold mb-1" style="color: var(--inst-negro);">
                <i class="fas fa-door-open me-2 text-inst-azul" style="color: var(--inst-azul);"></i> Sala de Reuniones
            </h2>
            <p class="text-muted mb-0">Registro de asistencia y control de sesiones.</p>
        </div>
        <div class="d-none d-md-block">
            <span class="badge bg-white text-secondary border px-3 py-2 rounded-pill shadow-sm">
                <i class="far fa-calendar-alt me-2" style="color: var(--inst-naranja);"></i> <?php echo date('d/m/Y'); ?>
            </span>
        </div>
    </div>

    <ul class="nav nav-tabs nav-tabs-inst mb-4" id="asistenciaTabs" role="tablist">
        <li class="nav-item"><button class="nav-link active" id="marcar-tab" data-bs-toggle="tab" data-bs-target="#marcar"><i class="fas fa-fingerprint me-2"></i> Autoconsulta</button></li>
        <li class="nav-item"><button class="nav-link" id="historial-tab" data-bs-toggle="tab" data-bs-target="#historial"><i class="fas fa-history me-2"></i> Mi Historial</button></li>
        <li class="nav-item"><button class="nav-link" id="calendario-tab" data-bs-toggle="tab" data-bs-target="#calendario"><i class="far fa-calendar-alt me-2"></i> Calendario</button></li>
    </ul>

    <div class="tab-content">
        
        <!-- PANEL 1: AUTOCONSULTA -->
        <div class="tab-pane fade show active" id="marcar">
            <div class="row justify-content-center">
                <div class="col-lg-6 col-md-8">
                    
                    <!-- ESTADO A: BUSCANDO (Por defecto) -->
                    <div id="panel-scan" class="card card-status text-center p-5 bg-white">
                        <div class="card-body">
                            <div class="scanning-pulse"><i class="fas fa-wifi fa-2x"></i></div>
                            <h4 class="fw-bold mb-2" style="color: var(--inst-azul);">Esperando Inicio de Sesión...</h4>
                            <p class="text-muted">El sistema le notificará automáticamente cuando el Secretario Técnico habilite la reunión.</p>
                            <div class="mt-4 text-muted small"><div class="spinner-border spinner-border-sm me-2"></div> Escaneando...</div>
                        </div>
                    </div>

                    <!-- ESTADO B: CONFIRMAR ASISTENCIA (Reunión activa, usuario NO presente) -->
                    <div id="panel-confirmar" class="card card-status border-0 shadow-lg" style="display: none;">
                        <div class="card-header text-white text-center py-3" style="background-color: var(--inst-azul);">
                            <h5 class="mb-0 fw-bold"><i class="fas fa-check-circle me-2"></i> Sesión Habilitada</h5>
                        </div>
                        <div class="card-body text-center p-5">
                            <p class="text-uppercase text-muted fw-bold small ls-1 mb-2">Bienvenido a la sesión:</p>
                            <h2 id="nombre-reunion" class="fw-bold mb-4" style="color: var(--inst-negro);">...</h2>
                            
                            <!-- Alerta de tiempo -->
                            <div id="alerta-tiempo-ok" class="alert alert-info border-0 bg-opacity-10 bg-info small mb-4">
                                <i class="fas fa-clock me-1"></i> El registro está habilitado (dentro de los 30 min).
                            </div>
                            <div id="alerta-tiempo-out" class="alert alert-danger border-0 bg-opacity-10 bg-danger small mb-4" style="display:none;">
                                <i class="fas fa-exclamation-triangle me-1"></i> <strong>Tiempo Expirado:</strong> Han pasado más de 30 minutos. Debe solicitar su ingreso al Secretario Técnico.
                            </div>

                            <div class="d-grid gap-2 col-10 mx-auto">
                                <button id="btn-marcar" class="btn btn-action-main" onclick="marcarPresente()">
                                    <i class="fas fa-user-check me-2"></i> Registrar mi Asistencia
                                </button>
                            </div>
                            <input type="hidden" id="idMinutaActiva"><input type="hidden" id="idReunionActiva">
                        </div>
                    </div>

                    <!-- ESTADO C: YA EN REUNIÓN (Usuario YA presente) -->
                    <div id="panel-activo" class="card card-status status-in-meeting p-5 bg-white" style="display: none;">
                        <div class="card-body text-center">
                            <div class="mb-4 text-success"><i class="fas fa-id-badge fa-4x"></i></div>
                            <h2 class="fw-bold mb-2 text-dark">Te encuentras en sesión</h2>
                            <h4 id="nombre-reunion-activo" class="text-secondary mb-4">...</h4>
                            
                            <div class="d-inline-block bg-light border rounded-pill px-4 py-2">
                                <span class="live-indicator"></span> <span class="fw-bold text-danger small">EN CURSO</span>
                            </div>
                            
                            <p class="text-muted mt-4 small">Su asistencia ya fue registrada y confirmada.<br>No es necesario realizar ninguna acción adicional.</p>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <!-- PANEL 2: HISTORIAL -->
        <div class="tab-pane fade" id="historial">
            <div class="card card-status border-0 shadow-sm">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center mb-4">
                        <div class="bg-light rounded-circle p-3 me-3 text-primary"><i class="fas fa-list-alt fa-2x"></i></div>
                        <div><h5 class="fw-bold mb-0">Mis Asistencias</h5><small class="text-muted">Registro histórico.</small></div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 small">
                            <thead class="table-light">
                                <tr><th>Reunión</th><th>Fecha</th><th>Hora</th><th>Origen</th></tr>
                            </thead>
                            <tbody>
                                <?php if(empty($data['historial'])): ?>
                                    <tr><td colspan="4" class="text-center py-4 text-muted">Sin registros.</td></tr>
                                <?php else: ?>
                                    <?php foreach($data['historial'] as $h): ?>
                                    <tr>
                                        <td class="fw-bold text-primary"><?= htmlspecialchars($h['nombreReunion']) ?></td>
                                        <td><?= date('d/m/Y', strtotime($h['fechaInicioReunion'])) ?></td>
                                        <td><?= date('H:i', strtotime($h['fechaRegistroAsistencia'])) ?></td>
                                        <td><span class="badge bg-light text-dark border"><?= $h['origenAsistencia'] ?></span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- PANEL 3: CALENDARIO -->
        <div class="tab-pane fade" id="calendario">
            <div class="card card-status border-0 shadow-sm">
                <div class="card-body p-0">
                    <iframe src="index.php?action=reunion_calendario&embedded=true" style="width: 100%; height: 650px; border: none;"></iframe>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    let intervalAsistencia;
    let lastState = 'none'; // Para controlar el popup de "Reunión Iniciada"

    document.addEventListener("DOMContentLoaded", () => { 
        iniciarScanner(); 
    });
    
    function iniciarScanner() { 
        intervalAsistencia = setInterval(buscarReunion, 3000); 
        buscarReunion(); 
    }
    
    function buscarReunion() {
        // Si cambiamos de pestaña, no consultar para ahorrar recursos
        const activeTab = document.querySelector('#marcar-tab.active');
        if(!activeTab) return;

        fetch('index.php?action=api_asistencia_check')
        .then(r => r.json())
        .then(resp => {
            procesarEstado(resp);
        })
        .catch(err => console.error("Scanner waiting...", err));
    }

    function procesarEstado(resp) {
        const pScan = document.getElementById('panel-scan');
        const pConf = document.getElementById('panel-confirmar');
        const pActivo = document.getElementById('panel-activo');

        // 1. CASO: NO HAY REUNIÓN
        if (resp.status === 'none') {
            pScan.style.display = 'block';
            pConf.style.display = 'none';
            pActivo.style.display = 'none';
            lastState = 'none';
            return;
        }

        // 2. CASO: HAY REUNIÓN ACTIVA
        if (resp.status === 'active') {
            const data = resp.data;

            // ¿El usuario ya marcó presente?
            if (data.ya_marco) {
                // MOSTRAR PANEL DE "YA ESTÁS EN SESIÓN"
                pScan.style.display = 'none';
                pConf.style.display = 'none';
                pActivo.style.display = 'block';
                
                document.getElementById('nombre-reunion-activo').textContent = data.nombreReunion;
                lastState = 'joined'; // Estado terminal
                return;
            }

            // Si el usuario NO ha marcado, mostramos el panel de confirmar
            pScan.style.display = 'none';
            pConf.style.display = 'block';
            pActivo.style.display = 'none';

            // Llenar datos
            document.getElementById('idMinutaActiva').value = data.t_minuta_idMinuta;
            document.getElementById('idReunionActiva').value = data.idReunion;
            document.getElementById('nombre-reunion').textContent = data.nombreReunion;

            // --- ALERTA DE INICIO DE REUNIÓN (Solo 1 vez) ---
            if (lastState === 'none') {
                Swal.fire({
                    icon: 'info',
                    title: '¡Sesión Habilitada!',
                    text: 'El Secretario Técnico ha iniciado: ' + data.nombreReunion,
                    confirmButtonColor: '#0071bc',
                    confirmButtonText: 'Entendido'
                });
                if (navigator.vibrate) navigator.vibrate([200, 100, 200]);
            }

            // --- LÓGICA DE 30 MINUTOS ---
            const minutos = parseInt(data.minutosTranscurridos);
            const btn = document.getElementById('btn-marcar');
            const alertOk = document.getElementById('alerta-tiempo-ok');
            const alertOut = document.getElementById('alerta-tiempo-out');
            

            if (minutos > 30) {
                // Fuera de tiempo
                btn.disabled = true;
                btn.classList.remove('btn-action-main');
                btn.classList.add('btn', 'btn-secondary');
                btn.innerHTML = '<i class="fas fa-lock me-2"></i> Registro Cerrado';
                
                alertOk.style.display = 'none';
                alertOut.style.display = 'block';
            } else {
                // A tiempo
                btn.disabled = false;
                // Restaurar clases si es necesario
                if(btn.classList.contains('btn-secondary')) {
                    btn.classList.remove('btn', 'btn-secondary');
                    btn.classList.add('btn-action-main');
                    btn.innerHTML = '<i class="fas fa-user-check me-2"></i> Registrar mi Asistencia';
                }
                alertOk.innerHTML = `<i class="fas fa-clock me-1"></i> Puedes registrar tu asistencia hasta las: <strong>${data.horaLimite} hrs</strong>.`;
                alertOk.style.display = 'block';
                alertOut.style.display = 'none';
            }

            lastState = 'active';
        }
    }

    function marcarPresente() {
        const idMinuta = document.getElementById('idMinutaActiva').value;
        const idReunion = document.getElementById('idReunionActiva').value;
        const btn = document.getElementById('btn-marcar');
        
        btn.disabled = true; 
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Conectando...';

        fetch('index.php?action=api_asistencia_marcar', {
            method: 'POST', 
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ idMinuta, idReunion })
        })
        .then(r => r.json())
        .then(d => {
            if(d.status === 'success') {
                // ALERT DE CONFIRMACIÓN
                Swal.fire({
                    icon: 'success',
                    title: '¡Asistencia Registrada!',
                    text: 'Se ha confirmado su presencia en la sesión correctamente.',
                    confirmButtonColor: '#00a650',
                    confirmButtonText: 'Excelente',
                    timer: 3000,
                    timerProgressBar: true
                }).then(() => {
                    // Forzar actualización inmediata para cambiar al panel verde
                    buscarReunion(); 
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: d.message,
                    confirmButtonColor: '#d33'
                });
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-user-check me-2"></i> Registrar mi Asistencia';
            }
        })
        .catch(e => {
            console.error(e);
            Swal.fire('Error', 'Problema de conexión con el servidor.', 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-user-check me-2"></i> Registrar mi Asistencia';
        });
    }
</script>