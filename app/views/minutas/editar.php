<?php
$m = $data['minuta'];
$permisos = $data['permisos'];
$readonly = $permisos['esSoloLectura'] ? 'readonly' : '';
$disabled = $permisos['esSoloLectura'] ? 'disabled' : '';
?>

<div class="container-fluid">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php?action=minutas_dashboard">Minutas</a></li>
            <li class="breadcrumb-item"><a href="index.php?action=minutas_pendientes">Pendientes</a></li>
            <li class="breadcrumb-item active">Gestionar #<?php echo $m['idMinuta']; ?></li>
        </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>
            <?php echo $permisos['esSecretario'] ? 'Editar' : 'Revisar'; ?> Minuta N° <?php echo $m['idMinuta']; ?>
            <span class="badge bg-secondary ms-2 text-uppercase"><?php echo $m['estadoMinuta']; ?></span>
        </h3>
    </div>

    <form id="form-crear-minuta">
        <input type="hidden" id="idMinuta" value="<?php echo $m['idMinuta']; ?>">

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h5 class="mb-0 text-primary"><i class="fas fa-list-alt me-2"></i>Temas</h5>
                <?php if (!$permisos['esSoloLectura']): ?>
                    <button type="button" class="btn btn-sm btn-primary" onclick="agregarTema()">
                        <i class="fas fa-plus-circle"></i> Añadir Tema
                    </button>
                <?php endif; ?>
            </div>
            <div class="card-body bg-light bg-opacity-10">
                <div id="contenedorTemas"></div>
                <div class="text-center text-muted small mt-2" id="msgSinTemas" style="display:none;">
                    No hay temas.
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-users me-2"></i> Asistencia</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th class="text-center">Presente</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($data['asistencia'] as $asistente): ?>
                                <tr>
                                    <td><?php echo $asistente['nombreCompleto']; ?></td>
                                    <td class="text-center">
                                        <input class="form-check-input" type="checkbox"
                                            value="<?php echo $asistente['idUsuario']; ?>"
                                            <?php echo $asistente['presente'] ? 'checked' : ''; ?>
                                            <?php echo $disabled; ?>>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if (!$permisos['esSoloLectura']): ?>
                    <div class="d-flex justify-content-end gap-2 mt-3">
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="guardarAsistencia()">
                            <i class="fas fa-save"></i> Guardar Cambios
                        </button>

                        <?php if (empty($m['asistencia_validada'])): ?>
                            <button type="button" class="btn btn-warning btn-sm text-dark" onclick="validarYEnviarAsistencia()">
                                <i class="fas fa-envelope"></i> Validar y Enviar a Gestión
                            </button>
                        <?php else: ?>
                            <span class="badge bg-success"><i class="fas fa-check"></i> Asistencia Validada</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h5 class="mb-0 text-dark"><i class="fas fa-vote-yea me-2"></i> Votaciones</h5>
            </div>
            <div class="card-body">
                <?php if ($permisos['esSecretario'] && !$permisos['esSoloLectura']): ?>
                    <div class="input-group mb-3">
                        <input type="text" id="nombreNuevaVotacion" class="form-control" placeholder="Nombre del acuerdo a votar...">
                        <button class="btn btn-primary" type="button" onclick="crearVotacion()">
                            <i class="fas fa-plus"></i> Crear Votación
                        </button>
                    </div>
                <?php endif; ?>

                <div id="listaVotaciones" class="list-group mb-4">
                </div>

                <h6 class="text-muted border-bottom pb-2">Resultados en Vivo</h6>
                <div id="resultadosVotaciones" class="row g-3">
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="fas fa-paperclip me-2"></i> Documentos Adjuntos</h5>
            </div>
            <div class="card-body">
                <?php if (!$permisos['esSoloLectura']): ?>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <div class="d-flex"> <input type="file" id="inputArchivo" class="form-control me-2">
                                <button type="button" class="btn btn-primary" onclick="subirArchivo()">
                                    <i class="fas fa-upload"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex"> <input type="url" id="inputUrlLink" class="form-control me-2" placeholder="https://ejemplo.com">
                                <button type="button" class="btn btn-info text-white" onclick="agregarLink()">
                                    <i class="fas fa-link"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <ul id="listaAdjuntos" class="list-group list-group-flush">
                </ul>
            </div>
        </div>

        <div class="card shadow-sm mb-5">
            <div class="card-body text-end">

                <?php if ($permisos['esSecretario']): ?>
                    <?php if ($m['estadoMinuta'] !== 'APROBADA' && $m['estadoMinuta'] !== 'PENDIENTE'): ?>
                        <button type="button" class="btn btn-secondary me-2" onclick="guardarBorrador()">
                            <i class="fas fa-save"></i> Guardar Borrador
                        </button>

                        <?php if (empty($m['asistencia_validada'])): ?>
                            <span class="d-inline-block" tabindex="0" data-bs-toggle="tooltip" title="Debe validar y enviar la asistencia primero">
                                <button type="button" class="btn btn-danger" disabled>
                                    <i class="fas fa-lock"></i> Enviar para Aprobación
                                </button>
                            </span>
                        <?php else: ?>
                            <button type="button" class="btn btn-danger" onclick="confirmarEnvio()">
                                <i class="fas fa-paper-plane"></i> Enviar para Aprobación
                            </button>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="text-muted">Minuta en proceso de firma o finalizada.</span>
                    <?php endif; ?>

                <?php elseif ($permisos['esPresidente']): ?>
                    <?php if ($permisos['haFirmado']): ?>
                        <div class="alert alert-success m-0"><i class="fas fa-check-circle"></i> Ya has firmado esta minuta.</div>
                    <?php elseif ($permisos['haEnviadoFeedback']): ?>
                        <div class="alert alert-warning m-0"><i class="fas fa-clock"></i> Esperando correcciones del Secretario.</div>
                    <?php else: ?>
                        <div id="areaFeedback" class="mb-3 text-start" style="display:none;">
                            <label class="form-label fw-bold text-danger">Indique las correcciones necesarias:</label>
                            <textarea id="txtFeedback" class="form-control" rows="3" placeholder="Ej: Corregir el nombre del consejero en el tema 2..."></textarea>
                            <div class="mt-2 text-end">
                                <button type="button" class="btn btn-secondary btn-sm" onclick="cancelarFeedback()">Cancelar</button>
                                <button type="button" class="btn btn-danger btn-sm" onclick="enviarFeedbackReal()">Enviar Observaciones</button>
                            </div>
                        </div>

                        <div id="botonesPresidente">
                            <button type="button" class="btn btn-outline-danger me-2" onclick="mostrarFeedback()">
                                <i class="fas fa-times-circle"></i> Rechazar / Pedir Cambios
                            </button>
                            <button type="button" class="btn btn-success btn-lg" onclick="firmarMinuta()">
                                <i class="fas fa-file-signature"></i> Firmar Minuta
                            </button>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

            </div>
        </div>

    </form>
</div>

<template id="plantilla-tema">
    <div class="card mb-3 tema-block border-start border-4 border-primary shadow-sm">
        <div class="card-body">
            <div class="d-flex justify-content-between mb-2">
                <h6 class="titulo-tema fw-bold text-primary">Tema Nuevo</h6>
                <?php if (!$permisos['esSoloLectura']): ?>
                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="this.closest('.tema-block').remove()"><i class="fas fa-trash"></i></button>
                <?php endif; ?>
            </div>
            <input type="text" class="form-control mb-2 editable-area" placeholder="Título del tema" <?php echo $readonly; ?>>
            <textarea class="form-control mb-2 editable-area" rows="2" placeholder="Objetivo" <?php echo $readonly; ?>></textarea>
            <textarea class="form-control mb-2 editable-area" rows="3" placeholder="Desarrollo" <?php echo $readonly; ?>></textarea>
        </div>
    </div>
</template>

<script>
    // Recuperamos los temas pasados desde PHP
    const TEMAS_INICIALES = <?php echo json_encode($data['temas']); ?>;

    document.addEventListener("DOMContentLoaded", () => {
        if (TEMAS_INICIALES && TEMAS_INICIALES.length > 0) {
            TEMAS_INICIALES.forEach(t => crearBloqueTema(t));
        } else {
            crearBloqueTema(); // Uno vacío por defecto
        }
    });

    function crearBloqueTema(data = null) {
        const tpl = document.getElementById('plantilla-tema');
        const clon = tpl.content.cloneNode(true);
        const inputs = clon.querySelectorAll('.editable-area');

        if (data) {
            inputs[0].value = data.nombreTema || '';
            inputs[1].value = data.objetivo || '';

            // CAMBIO AQUÍ: Leemos 'compromiso' para llenar el tercer input
            if (inputs[2]) inputs[2].value = data.compromiso || '';
        }

        document.getElementById('contenedorTemas').appendChild(clon);
    }

    // --- FUNCION: Guardar Asistencia ---
    function guardarAsistencia() {
        const idMinuta = document.getElementById('idMinuta').value;
        const checks = document.querySelectorAll('input[type="checkbox"]:checked');
        const ids = Array.from(checks).map(c => c.value).filter(val => val !== 'on'); // Filtramos valores basura

        fetch('index.php?action=api_guardar_asistencia', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    idMinuta: idMinuta,
                    asistencia: ids
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    // Usamos SweetAlert si lo tienes, o un alert nativo
                    if (typeof Swal !== 'undefined') Swal.fire('¡Guardado!', data.message, 'success');
                    else alert(data.message);
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(err => console.error('Error:', err));
    }

    // --- FUNCION: Guardar Borrador (Temas) ---
    // --- FUNCION CORREGIDA: Guardar Borrador ---
    function guardarBorrador() {
        const idMinuta = document.getElementById('idMinuta').value;
        const bloques = document.querySelectorAll('.tema-block');
        const temas = [];

        bloques.forEach(bloque => {
            const inputs = bloque.querySelectorAll('.editable-area');

            // Validamos que el bloque tenga inputs para evitar errores
            if (inputs.length > 0) {
                temas.push({
                    // Mapeamos los inputs visuales a las columnas de la BD
                    nombreTema: inputs[0].value, // Primer input -> nombreTema
                    objetivo: inputs[1] ? inputs[1].value : '', // Segundo -> objetivo
                    compromiso: inputs[2] ? inputs[2].value : '', // Tercero -> compromiso (Usado como Desarrollo)
                    observacion: '' // Dejamos vacío u opcional si agregas un 4to input
                });
            }
        });

        // Envío de datos...
        fetch('index.php?action=api_guardar_borrador', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    idMinuta: idMinuta,
                    temas: temas
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    if (typeof Swal !== 'undefined') Swal.fire('¡Guardado!', 'Borrador actualizado correctamente.', 'success');
                    else alert('Borrador guardado.');

                    // Opcional: Recargar para verificar visualmente
                    // location.reload(); 
                } else {
                    alert('Error del servidor: ' + data.message);
                }
            })
            .catch(err => console.error('Error:', err));
    }

    function agregarTema() {
        crearBloqueTema();
    }

    function confirmarEnvio() {
        const idMinuta = document.getElementById('idMinuta').value;

        // Usamos SweetAlert para confirmar (se ve más profesional)
        // Si no tienes SweetAlert cargado, usa un confirm() normal
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: '¿Enviar a Firma?',
                text: "La minuta pasará a estado PENDIENTE y se notificará al Presidente. No podrás editarla después.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sí, enviar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    procesarEnvio(idMinuta);
                }
            });
        } else {
            if (confirm("¿Seguro que desea enviar la minuta a firma? Ya no podrá editarla.")) {
                procesarEnvio(idMinuta);
            }
        }
    }

    function procesarEnvio(idMinuta) {
        // Mostrar feedback visual
        const btn = document.querySelector('.btn-danger');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';
        }

        fetch('index.php?action=api_enviar_aprobacion', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    idMinuta: idMinuta
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire('¡Enviada!', data.message, 'success').then(() => {
                            window.location.href = 'index.php?action=minutas_pendientes'; // Volver al listado
                        });
                    } else {
                        alert(data.message);
                        window.location.href = 'index.php?action=minutas_pendientes';
                    }
                } else {
                    if (typeof Swal !== 'undefined') Swal.fire('Error', data.message, 'error');
                    else alert('Error: ' + data.message);

                    if (btn) {
                        btn.disabled = false;
                        btn.innerText = 'Enviar para Aprobación';
                    }
                }
            })
            .catch(err => {
                console.error(err);
                alert('Error de conexión');
                if (btn) {
                    btn.disabled = false;
                    btn.innerText = 'Enviar para Aprobación';
                }
            });
    }

    // --- FUNCIONES PRESIDENTE ---
    function firmarMinuta() {
        const idMinuta = document.getElementById('idMinuta').value;

        Swal.fire({
            title: '¿Firmar Minuta?',
            text: "Esta acción es definitiva y generará el documento oficial.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, Firmar',
            confirmButtonColor: '#198754'
        }).then((result) => {
            if (result.isConfirmed) {
                // Mostrar indicador de carga
                Swal.fire({
                    title: 'Firmando...',
                    didOpen: () => Swal.showLoading()
                });

                fetch('index.php?action=api_firmar_minuta', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            idMinuta: idMinuta
                        })
                    })
                    .then(response => response.text()) // 1. Obtenemos texto primero para ver qué llega
                    .then(text => {
                        try {
                            return JSON.parse(text); // 2. Intentamos convertir a JSON
                        } catch (e) {
                            console.error("Respuesta del servidor no válida:", text);
                            throw new Error("El servidor devolvió un error no esperado (ver consola).");
                        }
                    })
                    .then(data => {
                        if (data.status === 'success') {
                            Swal.fire('¡Firmado!', data.message, 'success').then(() => location.reload());
                        } else {
                            Swal.fire('Error', data.message, 'error');
                        }
                    })
                    .catch(error => {
                        console.error(error);
                        Swal.fire('Error Crítico', error.message, 'error');
                    });
            }
        });
    }

    function mostrarFeedback() {
        document.getElementById('botonesPresidente').style.display = 'none';
        document.getElementById('areaFeedback').style.display = 'block';
        document.getElementById('txtFeedback').focus();
    }

    function cancelarFeedback() {
        document.getElementById('areaFeedback').style.display = 'none';
        document.getElementById('botonesPresidente').style.display = 'block';
        document.getElementById('txtFeedback').value = '';
    }

    function enviarFeedbackReal() {
        const idMinuta = document.getElementById('idMinuta').value;
        const texto = document.getElementById('txtFeedback').value.trim();

        if (texto === '') {
            Swal.fire('Atención', 'Debe escribir el motivo del rechazo.', 'warning');
            return;
        }

        Swal.fire({
            title: 'Enviando...',
            didOpen: () => Swal.showLoading()
        });

        fetch('index.php?action=api_enviar_feedback', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    idMinuta: idMinuta,
                    feedback: texto
                })
            })
            .then(response => response.text())
            .then(text => {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error("Respuesta del servidor no válida:", text);
                    throw new Error("El servidor devolvió un error no esperado.");
                }
            })
            .then(data => {
                if (data.status === 'success') {
                    Swal.fire('Enviado', 'Observaciones enviadas.', 'success').then(() => location.reload());
                } else {
                    Swal.fire('Error', data.message, 'error');
                }
            })
            .catch(error => {
                Swal.fire('Error Crítico', error.message, 'error');
            });
    }

    // --- FUNCIONES DE VOTACION ---

    // Cargar lista al iniciar
    document.addEventListener("DOMContentLoaded", () => {
        // ... tu código existente ...
        cargarVotaciones();
        setInterval(cargarResultados, 3000); // Polling cada 3s para ver votos en vivo
    });

    function crearVotacion() {
        const nombre = document.getElementById('nombreNuevaVotacion').value.trim();
        const idMinuta = document.getElementById('idMinuta').value;

        if (!nombre) return Swal.fire('Error', 'Escribe un nombre.', 'warning');

        fetch('index.php?action=api_votacion_crear', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    idMinuta: idMinuta,
                    nombre: nombre
                })
            })
            .then(r => r.json())
            .then(d => {
                if (d.status === 'success') {
                    document.getElementById('nombreNuevaVotacion').value = '';
                    cargarVotaciones();
                    Swal.fire('Creada', 'Votación lista.', 'success');
                } else {
                    Swal.fire('Error', d.message, 'error');
                }
            });
    }

    function cargarVotaciones() {
        const idMinuta = document.getElementById('idMinuta').value;
        fetch(`index.php?action=api_votacion_listar&idMinuta=${idMinuta}`)
            .then(r => r.json())
            .then(d => {
                const contenedor = document.getElementById('listaVotaciones');
                contenedor.innerHTML = '';

                if (d.data.length === 0) {
                    contenedor.innerHTML = '<div class="text-center text-muted py-2">No hay votaciones creadas.</div>';
                    return;
                }

                d.data.forEach(v => {
                    // CORRECCIÓN AQUÍ: Agregamos type="button" para evitar que envíe el formulario
                    const estadoBtn = v.habilitada == 1 ?
                        `<button type="button" class="btn btn-sm btn-danger" onclick="cambiarEstadoVotacion(${v.idVotacion}, 0)">Cerrar Votación</button>` :
                        `<button type="button" class="btn btn-sm btn-success" onclick="cambiarEstadoVotacion(${v.idVotacion}, 1)">Habilitar (Abrir)</button>`;

                    const badge = v.habilitada == 1 ?
                        '<span class="badge bg-success">ABIERTA</span>' :
                        '<span class="badge bg-secondary">CERRADA</span>';

                    contenedor.innerHTML += `
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <strong>${v.nombreVotacion}</strong> ${badge}
                        </div>
                        <div>${estadoBtn}</div>
                    </div>
                `;
                });
                // Actualizamos también los resultados visuales
                cargarResultados();
            });
    }

    function cambiarEstadoVotacion(id, estado) {
        fetch('index.php?action=api_votacion_estado', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    idVotacion: id,
                    estado: estado
                })
            })
            .then(r => r.json())
            .then(() => cargarVotaciones());
    }

    function cargarResultados() {
        const idMinuta = document.getElementById('idMinuta').value;
        const contenedor = document.getElementById('resultadosVotaciones');

        // No limpiamos el contenedor si no es necesario para evitar parpadeo, 
        // pero por simplicidad en este paso lo haremos así.

        fetch(`index.php?action=api_votacion_resultados&idMinuta=${idMinuta}`)
            .then(r => r.json())
            .then(d => {
                let html = '';
                d.data.forEach(v => {
                    html += `
                    <div class="col-md-6">
                        <div class="card h-100 border-${v.habilitada == 1 ? 'success' : 'secondary'}">
                            <div class="card-body text-center">
                                <h6 class="card-title">${v.nombre}</h6>
                                <div class="row mt-3">
                                    <div class="col-4"><h3 class="text-success">${v.votos.SI}</h3><small>APRUEBO</small></div>
                                    <div class="col-4"><h3 class="text-danger">${v.votos.NO}</h3><small>RECHAZO</small></div>
                                    <div class="col-4"><h3 class="text-secondary">${v.votos.ABSTENCION}</h3><small>ABST.</small></div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                });
                contenedor.innerHTML = html;
            });
    }

    document.addEventListener("DOMContentLoaded", () => {
        // ... (tus otros inits) ...
        cargarAdjuntos();

        // Listeners para formularios
        const formFile = document.getElementById('formSubirArchivo');
        if (formFile) formFile.addEventListener('submit', subirArchivo);

        const formLink = document.getElementById('formAgregarLink');
        if (formLink) formLink.addEventListener('submit', agregarLink);
    });

    function cargarAdjuntos() {
        const idMinuta = document.getElementById('idMinuta').value;
        fetch(`index.php?action=api_adjunto_listar&idMinuta=${idMinuta}`)
            .then(r => r.json())
            .then(d => {
                const ul = document.getElementById('listaAdjuntos');
                ul.innerHTML = '';
                if (d.data.length === 0) {
                    ul.innerHTML = '<li class="list-group-item text-muted text-center">Sin adjuntos.</li>';
                    return;
                }
                d.data.forEach(a => {
                    let icono = a.tipoAdjunto === 'link' ? 'fa-link' : 'fa-file-alt';
                    let contenido = a.tipoAdjunto === 'link' ?
                        `<a href="${a.pathAdjunto}" target="_blank">${a.pathAdjunto}</a>` :
                        `<a href="${a.pathAdjunto}" target="_blank" download>Descargar Documento</a>`;

                    // Botón eliminar (solo si no es solo lectura - verificamos si existe el form)
                    let btnEliminar = document.getElementById('formSubirArchivo') ?
                        `<button class="btn btn-sm btn-outline-danger float-end" onclick="eliminarAdjunto(${a.idAdjunto})"><i class="fas fa-trash"></i></button>` :
                        '';

                    ul.innerHTML += `
                    <li class="list-group-item">
                        <i class="fas ${icono} me-2 text-muted"></i> ${contenido}
                        ${btnEliminar}
                    </li>
                `;
                });
            });
    }

    function subirArchivo() {
        const idMinuta = document.getElementById('idMinuta').value;
        const input = document.getElementById('inputArchivo');
        const file = input.files[0];

        if (!file) {
            Swal.fire('Atención', 'Selecciona un archivo primero.', 'warning');
            return;
        }

        const formData = new FormData();
        formData.append('idMinuta', idMinuta);
        formData.append('archivo', file);

        Swal.fire({
            title: 'Subiendo...',
            didOpen: () => Swal.showLoading()
        });

        fetch('index.php?action=api_adjunto_subir', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(d => {
                if (d.status === 'success') {
                    input.value = ''; // Limpiar input
                    cargarAdjuntos(); // Recargar lista
                    Swal.close(); // Cerrar loading
                    const Toast = Swal.mixin({
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000
                    });
                    Toast.fire({
                        icon: 'success',
                        title: 'Archivo subido'
                    });
                } else {
                    Swal.fire('Error', d.message, 'error');
                }
            })
            .catch(err => {
                console.error(err);
                Swal.fire('Error', 'Error de conexión', 'error');
            });
    }

    function agregarLink() {
        const idMinuta = document.getElementById('idMinuta').value;
        const input = document.getElementById('inputUrlLink');
        const url = input.value.trim();

        if (!url) {
            Swal.fire('Atención', 'Escribe una URL válida.', 'warning');
            return;
        }

        const formData = new FormData();
        formData.append('idMinuta', idMinuta);
        formData.append('urlLink', url);

        // Mostrar carga
        const btn = event.target.closest('button'); // Referencia visual opcional
        if (btn) btn.disabled = true;

        fetch('index.php?action=api_adjunto_link', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(d => {
                if (d.status === 'success') {
                    input.value = '';
                    cargarAdjuntos();
                    const Toast = Swal.mixin({
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000
                    });
                    Toast.fire({
                        icon: 'success',
                        title: 'Enlace agregado'
                    });
                } else {
                    Swal.fire('Error', d.message, 'error');
                }
            })
            .catch(err => Swal.fire('Error', err.message, 'error'))
            .finally(() => {
                if (btn) btn.disabled = false;
            });
    }

    function eliminarAdjunto(id) {
        Swal.fire({
            title: '¿Eliminar?',
            text: "No podrás recuperarlo.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Sí, eliminar'
        }).then((result) => {
            if (result.isConfirmed) {
                const formData = new FormData();
                formData.append('idAdjunto', id);

                fetch('index.php?action=api_adjunto_eliminar', {
                        method: 'POST',
                        body: formData
                    })
                    .then(r => r.json())
                    .then(d => {
                        if (d.status === 'success') {
                            cargarAdjuntos();
                            Swal.fire('Eliminado', '', 'success');
                        }
                    });
            }
        });
    }

    function validarYEnviarAsistencia() {
        const idMinuta = document.getElementById('idMinuta').value;

        Swal.fire({
            title: '¿Validar Asistencia?',
            text: "Se generará un PDF con la asistencia actual y se enviará por correo a Gestión (Génesis). Esto es requisito para poder enviar la minuta a firma.",
            icon: 'info',
            showCancelButton: true,
            confirmButtonText: 'Sí, Validar y Enviar',
            confirmButtonColor: '#ffc107' // Amarillo warning
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Enviando correo...',
                    text: 'Esto puede tardar unos segundos.',
                    didOpen: () => Swal.showLoading()
                });

                fetch('index.php?action=api_validar_asistencia', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            idMinuta: idMinuta
                        })
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.status === 'success') {
                            Swal.fire('¡Enviado!', data.message, 'success').then(() => location.reload());
                        } else {
                            Swal.fire('Error', data.message, 'error');
                        }
                    })
                    .catch(err => Swal.fire('Error Crítico', err.message, 'error'));
            }
        });
    }
</script>