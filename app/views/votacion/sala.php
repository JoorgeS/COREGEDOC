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
                        <div class="mb-4">
                            <div class="spinner-grow text-warning" role="status" style="width: 3rem; height: 3rem;"></div>
                        </div>
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
                                <button class="btn btn-outline-success btn-lg py-3 fw-bold hover-fill" onclick="confirmarVoto('SI')">
                                    <i class="fas fa-check fa-lg me-2"></i> SÍ
                                </button>

                                <button class="btn btn-outline-danger btn-lg py-3 fw-bold hover-fill" onclick="confirmarVoto('NO')">
                                    <i class="fas fa-times fa-lg me-2"></i> NO
                                </button>

                                <button class="btn btn-outline-secondary btn-lg py-3 fw-bold hover-fill" onclick="confirmarVoto('ABSTENCION')">
                                    <i class="fas fa-minus-circle fa-lg me-2"></i> ABSTENCIÓN
                                </button>
                            </div>

                            <input type="hidden" id="idVotacionActiva">
                        </div>
                    </div>
                    <div id="panel-ya-voto" class="card text-center shadow p-4 border-0" style="display: none;">
                        <div class="card-body">
                            <div id="icono-voto-previo" class="mb-3 display-4"></div>

                            <h4 class="card-title text-uppercase fw-bold text-muted">Voto Registrado</h4>
                            <hr>

                            <p class="mb-2 fs-5">Usted ha votado:</p>

                            <h2 id="texto-voto-previo" class="fw-bold mb-3">...</h2>

                            <p id="mensaje-detalle-voto" class="text-secondary fw-bold mb-4"></p>
                            <div class="alert alert-light border small text-muted">
                                <i class="fas fa-lock me-1"></i> Su voto ha sido guardado. Espere al cierre de la votación.
                            </div>
                        </div>
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
                                <tr>
                                    <th>Votación</th>
                                    <th>Opción</th>
                                    <th>Fecha</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($data['historial_personal'])): ?>
                                    <tr>
                                        <td colspan="4" class="text-center">No has votado aún.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($data['historial_personal'] as $v): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($v['nombreVotacion']); ?></td>
                                            <td class="fw-bold text-<?php echo $v['opcionVoto'] == 'APRUEBO' ? 'success' : ($v['opcionVoto'] == 'RECHAZO' ? 'danger' : 'secondary'); ?>">
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
                <?php if (empty($data['resultados_generales'])): ?>
                    <div class="col-12 text-center py-5 text-muted">No hay resultados históricos disponibles.</div>
                <?php else: ?>
                    <?php foreach ($data['resultados_generales'] as $res): ?>
                        <div class="col-md-6 col-xl-4">
                            <div class="card h-100 shadow-sm">
                                <div class="card-header bg-white fw-bold text-truncate" title="<?php echo htmlspecialchars($res['nombreVotacion']); ?>">
                                    <?php echo htmlspecialchars($res['nombreVotacion']); ?>
                                </div>
                                <div class="card-body">
                                    <div class="d-flex justify-content-between text-center mb-2">
                                        <div class="text-success"><strong><?php echo $res['si']; ?></strong><br><small>APRUEBO</small></div>
                                        <div class="text-danger"><strong><?php echo $res['no']; ?></strong><br><small>RECHAZO</small></div>
                                        <div class="text-secondary"><strong><?php echo $res['abs']; ?></strong><br><small>ABS</small></div>
                                    </div>
                                    <div class="progress" style="height: 10px;">
                                        <?php
                                        $total = $res['si'] + $res['no'] + $res['abs'];
                                        $pSi = $total > 0 ? ($res['si'] / $total) * 100 : 0;
                                        $pNo = $total > 0 ? ($res['no'] / $total) * 100 : 0;
                                        $pAbs = $total > 0 ? ($res['abs'] / $total) * 100 : 0;
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
    let pollingInterval;
    let yaVotoLocalmente = false;

    document.addEventListener("DOMContentLoaded", () => {
        iniciarPolling();
    });

    function iniciarPolling() {
        pollingInterval = setInterval(verificarVotacion, 2000);
    }

    function verificarVotacion() {
        if (!document.getElementById('voto').classList.contains('active')) return;

        fetch('index.php?action=api_voto_check')
            .then(r => r.ok ? r.json() : Promise.reject(r))
            .then(resp => {
                if (resp.status === 'active') {
                    const data = resp.data;

                    if (data.ya_voto === true) {
                        // Si la base de datos dice que ya votó, usamos ese valor
                        // La BD devuelve: APRUEBO, RECHAZO, ABSTENCION
                        renderizarVistaPrevia(data.opcion_registrada, data.nombreVotacion);
                    } else {
                        mostrarVotacion(data);
                    }
                } else if (resp.status === 'error') {
                    console.error("Error servidor:", resp.message);
                    mostrarEspera();
                } else {
                    mostrarEspera();
                }
            })
            .catch(err => console.error(err));
    }

    function mostrarVotacion(data) {
        const elTitulo = document.getElementById('titulo-votacion');
        const elInput = document.getElementById('idVotacionActiva');
        const elPanel = document.getElementById('panel-votacion');

        if (!elTitulo || !elInput || !elPanel) return;

        // Evitar parpadeo si ya se muestra la misma votación
        if (elPanel.style.display === 'block' && elInput.value == data.idVotacion) return;

        yaVotoLocalmente = false;
        elInput.value = data.idVotacion;
        elTitulo.textContent = data.nombreVotacion;

        // --- CORRECCIÓN: RE-HABILITAR BOTONES ---
        // Nos aseguramos de que los botones estén clickeables
        const botones = elPanel.querySelectorAll('button');
        botones.forEach(btn => {
            btn.disabled = false;
        });
        // ----------------------------------------

        ocultarTodosPaneles();
        elPanel.style.display = 'block';

        if (navigator.vibrate) navigator.vibrate([200, 100, 200]);
    }

    function mostrarEspera() {
        // Si ya estamos viendo el panel de espera, no hacemos nada (evita parpadeo)
        if (document.getElementById('panel-espera').style.display === 'block') return;

        // Forzamos el reseteo de la interfaz
        ocultarTodosPaneles();
        document.getElementById('panel-espera').style.display = 'block';

        // Limpiamos el ID guardado para que la próxima votación se detecte como nueva
        const elInput = document.getElementById('idVotacionActiva');
        if (elInput) elInput.value = '';

        // Reactivamos botones por si acaso
        const botones = document.querySelectorAll('#panel-votacion button');
        botones.forEach(b => b.disabled = false);
    }

    function ocultarTodosPaneles() {
        // Ocultamos todo sin preguntar
        document.getElementById('panel-espera').style.display = 'none';
        document.getElementById('panel-votacion').style.display = 'none';
        document.getElementById('panel-ya-voto').style.display = 'none';
    }

    function ocultarTodosPaneles() {
        document.getElementById('panel-espera').style.display = 'none';
        document.getElementById('panel-votacion').style.display = 'none';
        document.getElementById('panel-ya-voto').style.display = 'none';
    }

    // --- LÓGICA DE CONFIRMACIÓN ---
    function confirmarVoto(intencion) {
        // intencion recibe: 'SI', 'NO', 'ABSTENCION'

        let colorBtn = '#6c757d';
        let textoPregunta = '';
        let valorParaBD = '';

        if (intencion === 'SI') {
            colorBtn = '#198754';
            textoPregunta = 'SÍ'; // Con tilde solo para mostrar
            valorParaBD = 'APRUEBO';
        } else if (intencion === 'NO') {
            colorBtn = '#dc3545';
            textoPregunta = 'NO';
            valorParaBD = 'RECHAZO';
        } else {
            colorBtn = '#ffc107';
            textoPregunta = 'ABSTENCIÓN';
            valorParaBD = 'ABSTENCION';
        }

        Swal.fire({
            title: '¿Confirmar Voto?',
            text: `Su voto será: ${textoPregunta}`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: colorBtn,
            confirmButtonText: `VOTAR ${textoPregunta}`,
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                // Enviamos el valor interno (APRUEBO) pero pasamos la intención (SI) para pintar
                enviarVotoAlServidor(valorParaBD, intencion);
            }
        });
    }

    function enviarVotoAlServidor(valorBD, valorVisual) {
        const idVotacion = document.getElementById('idVotacionActiva').value;
        const nombreVotacion = document.getElementById('titulo-votacion').textContent;
        const botones = document.querySelectorAll('#panel-votacion button');

        // Desactivar botones para evitar doble click
        botones.forEach(b => b.disabled = true);

        fetch('index.php?action=api_voto_emitir', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    idVotacion: idVotacion,
                    opcion: valorBD
                })
            })
            .then(r => r.json())
            .then(d => {
                if (d.status === 'success') {
                    yaVotoLocalmente = true;
                    Swal.fire({
                        title: '¡Voto Registrado!',
                        icon: 'success',
                        timer: 1500,
                        showConfirmButton: false
                    });
                    renderizarVistaPrevia(valorVisual, nombreVotacion);
                } else {
                    Swal.fire('Error', d.message, 'error');
                    botones.forEach(b => b.disabled = false);
                }
            })
            .catch(err => {
                console.error(err);
                Swal.fire('Error', 'Error de conexión', 'error');
                botones.forEach(b => b.disabled = false);
            });
    }

    // --- LÓGICA VISUAL "BLINDADA" ---
    function renderizarVistaPrevia(valorEntrada, nombreVotacion) {
        ocultarTodosPaneles();
        const panel = document.getElementById('panel-ya-voto');
        const iconoDiv = document.getElementById('icono-voto-previo');
        const textoDiv = document.getElementById('texto-voto-previo');
        const mensajeDiv = document.getElementById('mensaje-detalle-voto');

        // Limpiamos y normalizamos lo que entra (quitamos espacios y pasamos a mayúsculas)
        const op = valorEntrada ? valorEntrada.toString().toUpperCase().trim() : '';

        // Listas de variantes aceptadas para evitar errores de tildes o palabras
        const variantesSI = ['SI', 'SÍ', 'APRUEBO', 'SI '];
        const variantesNO = ['NO', 'RECHAZO', 'NO '];

        if (variantesSI.includes(op)) {
            // ES UN SÍ
            iconoDiv.innerHTML = '<i class="fas fa-check-circle text-success"></i>';
            textoDiv.className = 'fw-bold mb-3 text-success';
            textoDiv.textContent = 'SÍ'; // Forzamos texto bonito
        } else if (variantesNO.includes(op)) {
            // ES UN NO
            iconoDiv.innerHTML = '<i class="fas fa-times-circle text-danger"></i>';
            textoDiv.className = 'fw-bold mb-3 text-danger';
            textoDiv.textContent = 'NO';
        } else {
            // CUALQUIER OTRA COSA ES ABSTENCIÓN
            iconoDiv.innerHTML = '<i class="fas fa-minus-circle text-warning"></i>';
            textoDiv.className = 'fw-bold mb-3 text-warning';
            textoDiv.textContent = 'ABSTENCIÓN';
        }

        mensajeDiv.textContent = nombreVotacion ? `para "${nombreVotacion}"` : '';
        panel.style.display = 'block';
    }
</script>