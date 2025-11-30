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
                                <button class="btn btn-outline-success btn-lg py-3 fw-bold hover-fill" onclick="confirmarVoto('APRUEBO')">
                                    <i class="fas fa-thumbs-up fa-lg me-2"></i> APRUEBO
                                </button>
                                <button class="btn btn-outline-danger btn-lg py-3 fw-bold hover-fill" onclick="confirmarVoto('RECHAZO')">
                                    <i class="fas fa-thumbs-down fa-lg me-2"></i> RECHAZO
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
    // --- LÓGICA DE VOTACIÓN MEJORADA ---

    let pollingInterval;
    // Variable local para saber si el usuario acaba de votar en esta sesión de JS
    // y evitar que el polling le vuelva a mostrar los botones inmediatamente
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
            .then(response => {
                // Si la respuesta no es exitosa (ej: error 500 PHP), lanzamos error
                if (!response.ok) {
                    return response.text().then(text => {
                        throw new Error(text)
                    });
                }
                return response.json();
            })
            .then(resp => {
                if (resp.status === 'active') {
                    const data = resp.data;

                    if (data.ya_voto === true) {
                        let textoVisual = '';
                        let val = data.opcion_registrada ? data.opcion_registrada.trim().toUpperCase() : '';

                        if (val === 'SI') textoVisual = 'APRUEBO';
                        else if (val === 'NO') textoVisual = 'RECHAZO';
                        else if (val === 'ABSTENCION') textoVisual = 'ABSTENCION';

                        renderizarVistaPrevia(textoVisual, data.nombreVotacion);
                    } else {
                        mostrarVotacion(data);
                    }
                } else if (resp.status === 'error') {
                    // AQUÍ ESTÁ EL CAMBIO: Mostrar el error en pantalla
                    console.error("Error servidor:", resp.message);

                    // Solo si eres admin/desarrollador, descomenta la siguiente linea para ver el error en un alert:
                    // alert("Error del sistema: " + resp.message);

                    mostrarEspera();
                } else {
                    mostrarEspera();
                }
            })
            .catch(err => {
                console.error("Error crítico de red/sintaxis:", err);
                // Esto mostrará qué está rompiendo el código PHP (ej: un punto y coma faltante)
                // Una vez corregido, puedes quitar este alert o cambiarlo a console.error
                // alert("Error Crítico (PHP/Red): " + err.message); 
            });
    }

    function mostrarVotacion(data) {
        // Diagnóstico: Verificar si tenemos los elementos necesarios
        const elTitulo = document.getElementById('titulo-votacion');
        const elInput = document.getElementById('idVotacionActiva');
        const elPanel = document.getElementById('panel-votacion');

        if (!elTitulo || !elInput || !elPanel) {
            console.error("ERROR CRÍTICO: Faltan elementos HTML en sala.php.");
            console.log("titulo-votacion:", elTitulo);
            console.log("idVotacionActiva:", elInput);
            console.log("panel-votacion:", elPanel);
            return;
        }

        // Evitar parpadeos si ya estamos mostrando la misma votación
        if (elPanel.style.display === 'block' && elInput.value == data.idVotacion) {
            return;
        }

        yaVotoLocalmente = false; // Resetear flag local

        // Asignar valores
        elInput.value = data.idVotacion;
        elTitulo.textContent = data.nombreVotacion;

        // Cambiar paneles
        ocultarTodosPaneles();
        elPanel.style.display = 'block';

        // Vibrar celular (opcional)
        if (navigator.vibrate) navigator.vibrate([200, 100, 200]);
    }

    function mostrarEspera() {
        if (document.getElementById('panel-votacion').style.display === 'none' &&
            document.getElementById('panel-ya-voto').style.display === 'none') {
            ocultarTodosPaneles();
            document.getElementById('panel-espera').style.display = 'block';
        }
    }

    function ocultarTodosPaneles() {
        document.getElementById('panel-espera').style.display = 'none';
        document.getElementById('panel-votacion').style.display = 'none';
        document.getElementById('panel-ya-voto').style.display = 'none';
    }

    // FUNCIÓN 1: CONFIRMAR VOTO (POPUP PREGUNTA)
    function confirmarVoto(opcion) {
        let colorBtn = '#6c757d';
        let textoAccion = opcion;

        if (opcion === 'APRUEBO') {
            colorBtn = '#198754';
            textoAccion = 'APROBAR';
        }
        if (opcion === 'RECHAZO') {
            colorBtn = '#dc3545';
            textoAccion = 'RECHAZAR';
        }
        if (opcion === 'ABSTENCION') {
            colorBtn = '#ffc107';
            textoAccion = 'ABSTENERSE';
        }

        Swal.fire({
            title: '¿Está seguro?',
            text: `Usted va a votar: ${opcion}`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: colorBtn,
            confirmButtonText: `Sí, ${textoAccion}`,
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                enviarVotoAlServidor(opcion);
            }
        });
    }

    // FUNCIÓN 2: ENVIAR VOTO (FETCH)
    function enviarVotoAlServidor(opcion) {
        const idVotacion = document.getElementById('idVotacionActiva').value;

        // --- CORRECCIÓN IMPORTANTE ---
        // 1. Capturamos el nombre AQUÍ, al inicio de la función, para que esté disponible después.
        // Verificamos que el elemento exista para evitar errores si el DOM cambió.
        const elementoTitulo = document.getElementById('titulo-votacion');
        const nombreVotacionActual = elementoTitulo ? elementoTitulo.textContent : '';
        // -----------------------------

        // Bloquear botones visualmente para evitar doble clic
        const botones = document.querySelectorAll('#panel-votacion button');
        botones.forEach(b => b.disabled = true);

        fetch('index.php?action=api_voto_emitir', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    idVotacion,
                    opcion
                })
            })
            .then(r => r.json())
            .then(d => {
                if (d.status === 'success') {
                    yaVotoLocalmente = true;

                    // Popup de éxito
                    Swal.fire({
                        title: '¡Voto Registrado!',
                        text: 'Su preferencia ha sido guardada correctamente.',
                        icon: 'success',
                        timer: 2000,
                        showConfirmButton: false
                    });

                    // 2. Ahora sí podemos usar la variable 'nombreVotacionActual' porque fue declarada arriba
                    renderizarVistaPrevia(opcion, nombreVotacionActual);

                } else {
                    Swal.fire('Error', d.message, 'error');
                    // Si falla, reactivamos botones
                    botones.forEach(b => b.disabled = false);
                }
            })
            .catch(err => {
                console.error(err);
                Swal.fire('Error', 'Error de conexión', 'error');
                botones.forEach(b => b.disabled = false);
            });
    }

    // FUNCIÓN 3: VISTA PREVIA (RESULTADO VISUAL)
    function renderizarVistaPrevia(opcion, nombreVotacion) {
        ocultarTodosPaneles();

        const panel = document.getElementById('panel-ya-voto');
        const iconoDiv = document.getElementById('icono-voto-previo');
        const textoDiv = document.getElementById('texto-voto-previo');
        const mensajeDiv = document.getElementById('mensaje-detalle-voto'); // Nuevo elemento

        // Configurar estilos según la opción
        if (opcion === 'APRUEBO') {
            iconoDiv.innerHTML = '<i class="fas fa-check-circle text-success"></i>';
            textoDiv.className = 'fw-bold mb-3 text-success';
        } else if (opcion === 'RECHAZO') {
            iconoDiv.innerHTML = '<i class="fas fa-times-circle text-danger"></i>';
            textoDiv.className = 'fw-bold mb-3 text-danger';
        } else {
            iconoDiv.innerHTML = '<i class="fas fa-minus-circle text-warning"></i>';
            textoDiv.className = 'fw-bold mb-3 text-warning';
        }

        textoDiv.textContent = opcion;

        // --- AQUÍ ASIGNAMOS EL MENSAJE PERSONALIZADO ---
        if (nombreVotacion) {
            mensajeDiv.textContent = `para la adenda "${nombreVotacion}"`;
        } else {
            mensajeDiv.textContent = ''; // Limpiar si no hay nombre
        }
        // -----------------------------------------------

        panel.style.display = 'block';
    }
</script>