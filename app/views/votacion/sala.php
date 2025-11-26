<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="text-primary fw-bold"><i class="fas fa-vote-yea me-2"></i> Sala de Votaciones</h3>
    </div>

    <ul class="nav nav-tabs mb-4" id="votacionTabs" role="tablist">
        <li class="nav-item">
            <button class="nav-link active" id="voto-tab" data-bs-toggle="tab" data-bs-target="#voto" type="button"><i class="fas fa-box-open me-2"></i>Votación en Curso</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" id="misvotos-tab" data-bs-toggle="tab" data-bs-target="#misvotos" type="button"><i class="fas fa-list-ol me-2"></i>Mis Votos</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" id="resultados-tab" data-bs-toggle="tab" data-bs-target="#resultados" type="button"><i class="fas fa-chart-pie me-2"></i>Resultados Globales</button>
        </li>
    </ul>

    <div class="tab-content">
        
        <div class="tab-pane fade show active" id="voto">
            <div class="row justify-content-center">
                <div class="col-lg-6">
                     <div id="panel-espera" class="card text-center shadow-sm p-5 border-0 bg-white">
                        <div class="mb-4"><div class="spinner-grow text-warning" role="status" style="width: 3rem; height: 3rem;"></div></div>
                        <h4 class="text-dark">Esperando Votación...</h4>
                        <p class="text-muted">La pantalla se actualizará automáticamente cuando se abra una votación.</p>
                    </div>

                    <div id="panel-votacion" class="card shadow-lg border-primary" style="display: none;">
                        <div class="card-header bg-primary text-white text-center py-3">
                            <h5 class="mb-0 text-uppercase ls-1">Votación Abierta</h5>
                        </div>
                        <div class="card-body text-center p-4">
                            <h6 class="text-muted text-uppercase small">Tema:</h6>
                            <h3 id="titulo-votacion" class="fw-bold mb-5 text-dark">...</h3>
                            
                            <div class="d-grid gap-3">
                                <button class="btn btn-outline-success btn-lg py-3 fw-bold hover-fill" onclick="enviarVoto('SI')">
                                    <i class="fas fa-thumbs-up fa-lg me-2"></i> APRUEBO
                                </button>
                                <button class="btn btn-outline-danger btn-lg py-3 fw-bold hover-fill" onclick="enviarVoto('NO')">
                                    <i class="fas fa-thumbs-down fa-lg me-2"></i> RECHAZO
                                </button>
                                <button class="btn btn-outline-secondary btn-lg py-3 fw-bold hover-fill" onclick="enviarVoto('ABSTENCION')">
                                    <i class="fas fa-minus-circle fa-lg me-2"></i> ABSTENCIÓN
                                </button>
                            </div>
                            <input type="hidden" id="idVotacionActiva">
                        </div>
                    </div>

                    <div id="panel-exito" class="card text-center shadow p-5 border-success bg-success bg-opacity-10" style="display: none;">
                        <div class="mb-3"><i class="fas fa-check-circle text-success display-1"></i></div>
                        <h2 class="text-success">¡Voto Registrado!</h2>
                        <p>Esperando la siguiente votación...</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="misvotos">
             <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr><th>Votación</th><th>Opción</th><th>Fecha</th><th>Estado</th></tr>
                            </thead>
                            <tbody>
                                <?php if(empty($data['historial_personal'])): ?>
                                    <tr><td colspan="4" class="text-center">No has votado aún.</td></tr>
                                <?php else: ?>
                                    <?php foreach($data['historial_personal'] as $v): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($v['nombreVotacion']); ?></td>
                                        <td class="fw-bold text-<?php echo $v['opcionVoto']=='SI'?'success':($v['opcionVoto']=='NO'?'danger':'secondary'); ?>">
                                            <?php echo $v['opcionVoto']; ?>
                                        </td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($v['fechaVoto'])); ?></td>
                                        <td><span class="badge bg-light text-dark border"><?php echo $v['estado']; ?></span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="resultados">
            <div class="row g-3">
                <?php if(empty($data['resultados_generales'])): ?>
                    <div class="col-12 text-center py-5 text-muted">No hay resultados históricos disponibles.</div>
                <?php else: ?>
                    <?php foreach($data['resultados_generales'] as $res): ?>
                    <div class="col-md-6 col-xl-4">
                        <div class="card h-100 shadow-sm">
                            <div class="card-header bg-white fw-bold text-truncate" title="<?php echo htmlspecialchars($res['nombreVotacion']); ?>">
                                <?php echo htmlspecialchars($res['nombreVotacion']); ?>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between text-center mb-2">
                                    <div class="text-success"><strong><?php echo $res['si']; ?></strong><br><small>SI</small></div>
                                    <div class="text-danger"><strong><?php echo $res['no']; ?></strong><br><small>NO</small></div>
                                    <div class="text-secondary"><strong><?php echo $res['abs']; ?></strong><br><small>ABS</small></div>
                                </div>
                                <div class="progress" style="height: 10px;">
                                    <?php 
                                        $total = $res['si'] + $res['no'] + $res['abs']; 
                                        $pSi = $total > 0 ? ($res['si']/$total)*100 : 0;
                                        $pNo = $total > 0 ? ($res['no']/$total)*100 : 0;
                                        $pAbs = $total > 0 ? ($res['abs']/$total)*100 : 0;
                                    ?>
                                    <div class="progress-bar bg-success" style="width: <?php echo $pSi; ?>%"></div>
                                    <div class="progress-bar bg-danger" style="width: <?php echo $pNo; ?>%"></div>
                                    <div class="progress-bar bg-secondary" style="width: <?php echo $pAbs; ?>%"></div>
                                </div>
                            </div>
                            <div class="card-footer bg-light text-muted small">
                                <?php echo date('d/m/Y', strtotime($res['fechaCreacion'])); ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<script>
    // Script de Polling para Votación (Mismo de antes, adaptado al TAB)
    let pollingInterval;
    document.addEventListener("DOMContentLoaded", () => { iniciarPolling(); });

    function iniciarPolling() { pollingInterval = setInterval(verificarVotacion, 2000); }

    function verificarVotacion() {
        // Solo ejecutar si el tab de votar está activo
        if (!document.getElementById('voto').classList.contains('active')) return;

        fetch('index.php?action=api_voto_check')
        .then(r => r.json())
        .then(resp => {
            if (resp.status === 'active') { mostrarVotacion(resp.data); } 
            else { mostrarEspera(); }
        })
        .catch(err => console.error(err));
    }

    function mostrarVotacion(data) {
        if (document.getElementById('panel-votacion').style.display === 'block' && 
            document.getElementById('idVotacionActiva').value == data.idVotacion) return;

        document.getElementById('idVotacionActiva').value = data.idVotacion;
        document.getElementById('titulo-votacion').textContent = data.nombreVotacion;
        document.getElementById('panel-espera').style.display = 'none';
        document.getElementById('panel-exito').style.display = 'none';
        document.getElementById('panel-votacion').style.display = 'block';
        if (navigator.vibrate) navigator.vibrate([200, 100, 200]);
    }

    function mostrarEspera() {
        if (document.getElementById('panel-exito').style.display === 'none') {
            document.getElementById('panel-votacion').style.display = 'none';
            document.getElementById('panel-espera').style.display = 'block';
        }
    }

    function enviarVoto(opcion) {
        const idVotacion = document.getElementById('idVotacionActiva').value;
        const botones = document.querySelectorAll('#panel-votacion button');
        botones.forEach(b => b.disabled = true);

        fetch('index.php?action=api_voto_emitir', {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ idVotacion, opcion })
        })
        .then(r => r.json())
        .then(d => {
            if(d.status === 'success') {
                document.getElementById('panel-votacion').style.display = 'none';
                document.getElementById('panel-exito').style.display = 'block';
                botones.forEach(b => b.disabled = false);
                setTimeout(() => {
                    document.getElementById('panel-exito').style.display = 'none';
                    mostrarEspera();
                }, 3000);
            } else {
                Swal.fire('Error', d.message, 'error');
                botones.forEach(b => b.disabled = false);
            }
        });
    }
</script>