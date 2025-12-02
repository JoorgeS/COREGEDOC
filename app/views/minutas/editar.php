<?php
// LÓGICA DE BLOQUEO DE EDICIÓN
// El sistema es editable SI:
// 1. Está en BORRADOR
// 2. O requiere revisión (correcciones del presidente)
$estadoMinuta = $data['minuta']['estadoMinuta']; // BORRADOR, EN_FIRMA, APROBADA, REQUIERE_REVISION
$esEditable = ($estadoMinuta === 'BORRADOR' || $estadoMinuta === 'REQUIERE_REVISION');

// Atributo helper para deshabilitar inputs masivamente
$disabledAttr = $esEditable ? '' : 'disabled';
$dNoneClass = $esEditable ? '' : 'd-none';
?>

<div class="container-fluid py-4">

    <div class="card shadow-sm mb-4 border-0">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center py-2">
            <h6 class="mb-0 fw-bold text-uppercase"><i class="fas fa-file-alt me-2"></i>Gestión de la Minuta</h6>
            <span class="badge bg-light text-primary fw-bold px-3 border border-white">
                ESTADO: <?= strtoupper($data['minuta']['estadoMinuta']) ?>
            </span>

        </div>
        <!-- BARRA DE ACCIONES SUPERIOR -->
        <div class="bg-light p-3 border-bottom d-flex justify-content-between align-items-center">
            <div>
                <span class="text-muted small text-uppercase fw-bold">Estado de Sesión:</span>

                <?php
                $st = $data['estado_reunion'];
                // 1. EN CURSO (Vigente=1)
                if ($st['vigente'] == 1): ?>
                    <span class="badge bg-success ms-2 shadow-sm"><i class="fas fa-circle fa-beat-fade me-1" style="--fa-animation-duration: 2s;"></i> EN CURSO</span>

                <?php // 2. FINALIZADA (Validada=1) 
                elseif ($st['asistencia_validada'] == 1): ?>
                    <span id="badgeEstadoSesion" class="badge bg-info text-dark ms-2 border border-info">
                        <i class="fas fa-clock me-1"></i> REUNIÓN FINALIZADA (ESPERA APROBACIÓN)
                    </span>

                <?php // 3. ESPERA (Ni vigente ni validada)
                else: ?>
                    <span class="badge bg-warning text-dark ms-2 border border-warning">ESPERANDO INICIO</span>
                <?php endif; ?>
            </div>

            <div class="d-flex gap-2">
                <?php if ($data['permisos']['esSecretario']): ?>

                    <!-- BOTÓN DE CONTROL DE SESIÓN (Dinámico) -->
                    <?php
                    if ($st['vigente'] == 1) {
                        // ESTADO: EN CURSO -> Mostrar FINALIZAR
                        echo '<button id="btnFinalizar" class="btn btn-danger btn-sm text-white shadow-sm" onclick="finalizarReunion()">
                            <i class="fas fa-stop-circle me-1"></i> Finalizar Reunión y Enviar Asistencia
                          </button>';
                    } elseif ($st['asistencia_validada'] == 1) {
                        // ESTADO: FINALIZADA -> Mostrar BLOQUEADO
                        echo '<button class="btn btn-secondary btn-sm text-white" disabled>
                            <i class="fas fa-check-circle me-1"></i> Reunión Cerrada
                          </button>';
                    } else {
                        // ESTADO: ESPERA -> Mostrar INICIAR
                        echo '<button id="btnIniciar" class="btn btn-primary btn-sm text-white shadow-sm" onclick="iniciarReunion()">
                            <i class="fas fa-play me-1"></i> Habilitar / Iniciar Reunión
                          </button>';
                    }
                    ?>

                    <!-- BOTÓN ENVIAR A FIRMA (Lógica existente) -->
                    <?php
                    $estadoMin = $data['minuta']['estadoMinuta'];
                    $puedeEnviar = ($estadoMin === 'BORRADOR' || $estadoMin === 'REQUIERE_REVISION');
                    $listoParaEnviar = ($st['vigente'] == 0 && $st['asistencia_validada'] == 1); // Solo si terminó

                    if (!$puedeEnviar) {
                        echo '<button class="btn btn-secondary btn-sm" disabled><i class="fas fa-check-double me-1"></i> En Proceso de Firma</button>';
                    } else {
                        $claseBtn = $listoParaEnviar ? 'btn-success text-white' : 'btn-secondary';
                        $estadoBtn = $listoParaEnviar ? '' : 'disabled';
                        $textoBtn = ($estadoMin === 'REQUIERE_REVISION') ? 'Reenviar a Firma' : 'Enviar a Firma';
                        $iconoBtn = ($estadoMin === 'REQUIERE_REVISION') ? 'fa-sync-alt' : 'fa-paper-plane';

                        echo '<button id="btnEnviarFirma" class="btn ' . $claseBtn . ' btn-sm" onclick="enviarAFirma()" ' . $estadoBtn . '>
                            <i class="fas ' . $iconoBtn . ' me-1"></i> <span id="lblBtnFirma">' . $textoBtn . '</span>
                          </button>';
                    }
                    ?>

                <?php endif; ?>
            </div>
        </div>
        <div class="card-body bg-white">
            <div class="row g-3">
                <div class="col-md-6 border-end">
                    <div class="row mb-1">
                        <div class="col-4 fw-bold text-end text-secondary">N° Minuta:</div>
                        <div class="col-8 text-dark fw-bold fs-5"><?= $data['minuta']['idMinuta'] ?></div>
                    </div>
                    <div class="row mb-1">
                        <div class="col-4 fw-bold text-end text-secondary">Reunión:</div>
                        <div class="col-8 text-primary fw-bold text-uppercase" style="font-size: 0.9rem;">
                            <?= $data['header_info']['nombre_reunion'] ?>
                        </div>
                    </div>
                    <div class="row mb-1">
                        <div class="col-4 fw-bold text-end text-secondary">Fecha:</div>
                        <div class="col-8"><?= $data['header_info']['fecha_formateada'] ?></div>
                    </div>
                    <div class="row mb-1">
                        <div class="col-4 fw-bold text-end text-secondary">Hora:</div>
                        <div class="col-8"><?= $data['header_info']['hora_formateada'] ?></div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="row mb-1">
                        <div class="col-3 fw-bold text-end text-secondary">Comisión:</div>
                        <div class="col-9 fw-bold text-dark"><?= $data['header_info']['comisiones_str'] ?></div>
                    </div>
                    <div class="row mb-1">
                        <div class="col-3 fw-bold text-end text-secondary">Presidente:</div>
                        <div class="col-9 text-dark"><?= $data['header_info']['presidente_completo'] ?></div>
                    </div>
                    <div class="row">
                        <div class="col-3 fw-bold text-end text-secondary">Secretario:</div>
                        <div class="col-9 text-secondary"><?= $data['header_info']['secretario_completo'] ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white border-bottom-0 pt-3 px-3">
            <ul class="nav nav-tabs card-header-tabs" id="minutaTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active fw-bold" id="desarrollo-tab" data-bs-toggle="tab" data-bs-target="#desarrollo" type="button" role="tab">
                        <i class="fas fa-edit me-2 text-primary"></i>Desarrollo
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-bold" id="asistencia-tab" data-bs-toggle="tab" data-bs-target="#asistencia" type="button" role="tab">
                        <i class="fas fa-users me-2 text-info"></i>Asistencia
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-bold" id="votaciones-tab" data-bs-toggle="tab" data-bs-target="#votaciones" type="button" role="tab">
                        <i class="fas fa-vote-yea me-2 text-success"></i>Votaciones
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-bold" id="adjuntos-tab" data-bs-toggle="tab" data-bs-target="#adjuntos" type="button" role="tab">
                        <i class="fas fa-paperclip me-2 text-secondary"></i>Adjuntos
                    </button>
                </li>
            </ul>
        </div>

        <div class="card-body bg-white min-vh-50">
            <div class="tab-content" id="minutaTabsContent">

                <div class="tab-pane fade show active" id="desarrollo" role="tabpanel" aria-labelledby="desarrollo-tab">

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="alert alert-light border-start border-primary border-4 py-2 mb-0 w-75">
                            <h6 class="text-primary mb-1"><i class="fas fa-edit"></i> Desarrollo de la Reunión</h6>
                            <small class="text-muted">Agregue los temas tratados. El contenido se guarda automáticamente.</small>
                        </div>

                        <div id="statusGuardado" class="text-muted fw-bold small">
                            <i class="fas fa-check-circle text-success"></i> Todo al día
                        </div>
                    </div>

                    <div class="accordion mb-4" id="accordionTemas"></div>

                    <?php if ($esEditable): ?>
                        <button class="btn btn-outline-primary btn-sm mb-5" onclick="agregarNuevoTema()">
                            <i class="fas fa-plus"></i> Agregar Nuevo Tema
                        </button>
                    <?php else: ?>
                        <div class="alert alert-secondary small"><i class="fas fa-lock"></i> Edición bloqueada.</div>
                    <?php endif; ?>

                </div>
                <div class="tab-pane fade" id="asistencia" role="tabpanel" aria-labelledby="asistencia-tab">

                    <div class="row mb-3 align-items-center">
                        <div class="col-md-8">
                            <div class="alert alert-info border-start border-info border-4 py-2 mb-0">
                                <div class="d-flex align-items-center">
                                    <?php if ($st['vigente'] == 1): ?>
                                        <div class="spinner-grow text-info spinner-grow-sm me-2" role="status"></div>
                                    <?php endif; ?>
                                    <div>
                                        <strong>Registro de Asistencia</strong>
                                        <small class="d-block">Gestione la asistencia de los consejeros.</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 text-end">
                            <div class="card bg-light border-0">
                                <div class="card-body py-2 px-3">
                                    <small class="text-muted fw-bold">RESUMEN</small>
                                    <h4 class="mb-0 text-primary" id="contadorAsistencia">0 / 0</h4>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive shadow-sm border rounded">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th width="40%">Consejero</th>
                                    <th width="20%">Hora Registro</th>
                                    <th width="20%">Estado</th>
                                    <th width="20%" class="text-center">Acción (Secretario)</th>
                                </tr>
                            </thead>
                            <tbody id="tablaAsistenciaBody">
                                <tr>
                                    <td colspan="4" class="text-center py-4">Cargando lista...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                </div>
                <div class="tab-pane fade" id="votaciones" role="tabpanel" aria-labelledby="votaciones-tab">

                    <div class="border-top border-primary border-4 pt-3 mt-2">

                        <?php if ($esEditable): ?>
                            <h6 class="mb-3 text-dark fw-bold">Crear Nueva Votación</h6>

                            <?php if (isset($data['lista_comisiones_select']) && count($data['lista_comisiones_select']) > 1): ?>
                                <div class="mb-3">
                                    <label class="form-label small text-muted">Asignar a Comisión:</label>
                                    <select id="selectComisionVoto" class="form-select border-primary">
                                        <?php foreach ($data['lista_comisiones_select'] as $com): ?>
                                            <option value="<?= $com['id'] ?>"><?= htmlspecialchars($com['nombre']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php else: ?>
                                <input type="hidden" id="selectComisionVoto" value="<?= $data['lista_comisiones_select'][0]['id'] ?? '' ?>">
                            <?php endif; ?>

                            <div class="row g-2 align-items-center mb-4">
                                <div class="col-md-9">
                                    <label class="form-label small text-muted">Moción / Tema a Votar:</label>
                                    <input type="text" id="inputNombreVotacion" class="form-control" placeholder="Ej: Aprobar fondos para...">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label d-none d-md-block">&nbsp;</label>
                                    <button type="button" class="btn btn-success w-100 text-white" onclick="crearVotacion()">
                                        <i class="fas fa-plus me-1"></i> Iniciar
                                    </button>
                                </div>
                            </div>

                        <?php else: ?>
                            <div class="alert alert-secondary mt-3 mb-4">
                                <i class="fas fa-lock me-2"></i> Las votaciones están cerradas.
                            </div>
                        <?php endif; ?>

                        <h6 class="mb-3 text-dark fw-bold">Votaciones Creadas (Historial)</h6>
                        <div id="contenedorVotaciones">
                            <div class="p-4 text-center bg-light rounded text-muted">
                                <small class="d-block mb-2">No hay votaciones registradas.</small>
                            </div>
                        </div>
                    </div>

                </div>
                <div class="tab-pane fade" id="adjuntos" role="tabpanel" aria-labelledby="adjuntos-tab">

                    <div class="alert alert-light border-start border-secondary border-4 mb-4">
                        <h6 class="text-secondary mb-1"><i class="fas fa-folder-open"></i> Documentos y Enlaces</h6>
                        <small class="text-muted">Soporta: PDF, Word, Excel, PowerPoint, Videos y Enlaces Web.</small>
                    </div>

                    <div class="row">
                        <?php if ($esEditable): ?>
                            <div class="col-md-5">
                                <div class="card bg-light border-2 border-dashed h-100" id="dropZoneAdjuntos" style="border-style: dashed !important;">
                                    <div class="card-body text-center d-flex flex-column justify-content-center py-5">
                                        <i class="fas fa-cloud-upload-alt fa-3x text-secondary mb-3"></i>
                                        <h6 class="fw-bold text-secondary">Arrastre archivos aquí</h6>
                                        <span class="text-muted small mb-3">o</span>

                                        <button class="btn btn-primary btn-sm mx-auto px-4" onclick="document.getElementById('inputArchivoOculto').click()">
                                            <i class="fas fa-file-upload me-1"></i> Subir Archivo
                                        </button>
                                        <input type="file" id="inputArchivoOculto" class="d-none" multiple onchange="subirArchivos(this.files)">

                                        <hr class="w-50 mx-auto my-3 text-muted">

                                        <button class="btn btn-outline-dark btn-sm mx-auto px-4" data-bs-toggle="modal" data-bs-target="#modalNuevoLink">
                                            <i class="fas fa-link me-1"></i> Agregar Enlace Web
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="<?= $esEditable ? 'col-md-7' : 'col-12' ?>">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="fw-bold mb-0">Archivos Cargados</h6>
                                <button class="btn btn-link btn-sm text-decoration-none p-0" onclick="cargarAdjuntos()">
                                    <i class="fas fa-sync-alt"></i> Actualizar
                                </button>
                            </div>
                            <div class="card border-0 shadow-sm">
                                <ul class="list-group list-group-flush" id="listaAdjuntosUl">
                                    <li class="list-group-item text-center text-muted py-4">
                                        <div class="spinner-border spinner-border-sm"></div> Cargando...
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal fade" id="modalDetalleVoto" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title">
                                <i class="fas fa-list-ol me-2"></i>Detalle de la Votación
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body p-0">
                            <table class="table table-striped mb-0">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th class="ps-4">Consejero</th>
                                        <th class="text-center">Opción</th>
                                    </tr>
                                </thead>
                                <tbody id="tablaDetalleVoto">
                                </tbody>
                            </table>
                        </div>
                        <div class="modal-footer py-1">
                            <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                        </div>
                    </div>
                </div>
            </div>
            <script>
                // ============================================
                //  LÓGICA PESTAÑA: ADJUNTOS
                // ============================================

                // 1. Inicializar al cargar la página
                document.addEventListener('DOMContentLoaded', function() {
                    configurarDragDrop();
                    // Cargar lista inicial si estamos en la tab (o esperar al click)
                    const tabAdjuntos = document.getElementById('adjuntos-tab');
                    if (tabAdjuntos) {
                        tabAdjuntos.addEventListener('shown.bs.tab', cargarAdjuntos);
                    }
                });

                // 2. Cargar Lista desde el Servidor
                function cargarAdjuntos() {
                    const ul = document.getElementById('listaAdjuntosUl');
                    ul.innerHTML = '<li class="list-group-item text-center text-muted py-3"><div class="spinner-border spinner-border-sm text-primary"></div></li>';

                    fetch(`index.php?action=api_adjunto_listar&id=${idMinutaGlobal}`)
                        .then(r => r.json())
                        .then(resp => {
                            if (resp.status === 'success') {
                                renderizarListaAdjuntos(resp.data);
                            } else {
                                ul.innerHTML = '<li class="list-group-item text-danger">Error al cargar adjuntos.</li>';
                            }
                        })
                        .catch(e => console.error(e));
                }

                const btnReenviar = document.getElementById('btnReenviarFirma');

                // Solo activamos la lógica si estamos en modo "REQUIERE_REVISION" (el botón existe)
                if (btnReenviar) {

                    // Función que "Enciende" el botón
                    const activarBotonReenvio = () => {
                        if (btnReenviar.disabled) {
                            btnReenviar.disabled = false;
                            btnReenviar.classList.remove('btn-secondary');
                            btnReenviar.classList.add('btn-warning', 'text-dark', 'fa-beat-fade'); // Efecto visual

                            // Cambiar texto e icono
                            const label = document.getElementById('lblBtnFirma');
                            if (label) label.innerText = "Aplicar Correcciones y Reenviar";

                            // Quitar animación después de unos segundos para no molestar
                            setTimeout(() => {
                                btnReenviar.classList.remove('fa-beat-fade');
                            }, 1500);
                        }
                    };

                    // Escuchar cambios en todos los campos de texto del editor
                    // Usamos 'delegación de eventos' para capturar incluso campos nuevos dinámicos
                    document.body.addEventListener('input', function(e) {
                        // Si el elemento editado es un textarea o input dentro del área de desarrollo
                        if (e.target.tagName === 'TEXTAREA' || e.target.tagName === 'INPUT') {
                            activarBotonReenvio();
                        }
                    });

                    // Escuchar cambios en checkboxes o selects si los hubiera
                    document.body.addEventListener('change', function(e) {
                        if (e.target.tagName === 'SELECT' || e.target.type === 'checkbox') {
                            activarBotonReenvio();
                        }
                    });

                    console.log("Sistema de detección de correcciones activado.");
                }

                function renderizarListaAdjuntos(data) {
                    const ul = document.getElementById('listaAdjuntosUl');
                    ul.innerHTML = '';

                    if (data.length === 0) {
                        ul.innerHTML = '<li class="list-group-item text-center text-muted fst-italic py-4">No hay archivos ni enlaces adjuntos.</li>';
                        return;
                    }

                    data.forEach(file => {
                        let icon = '<i class="fas fa-file text-secondary"></i>';
                        let nombre = file.nombreArchivo;
                        let badge = '';

                        // Detectar tipo para icono
                        if (file.tipoAdjunto === 'link') {
                            icon = '<i class="fas fa-link text-primary"></i>';
                            badge = '<span class="badge bg-light text-primary border ms-2">LINK</span>';
                        } else {
                            const ext = nombre.split('.').pop().toLowerCase();
                            if (['pdf'].includes(ext)) icon = '<i class="fas fa-file-pdf text-danger"></i>';
                            if (['doc', 'docx'].includes(ext)) icon = '<i class="fas fa-file-word text-primary"></i>';
                            if (['xls', 'xlsx'].includes(ext)) icon = '<i class="fas fa-file-excel text-success"></i>';
                            if (['ppt', 'pptx'].includes(ext)) icon = '<i class="fas fa-file-powerpoint text-warning"></i>';
                            if (['mp4', 'avi', 'flv', 'mov'].includes(ext)) icon = '<i class="fas fa-file-video text-info"></i>';
                        }

                        const item = `
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center text-truncate">
                        <div class="me-3 fs-5">${icon}</div>
                        <div class="text-truncate">
                            <span class="fw-bold text-dark">${nombre}</span>
                            ${badge}
                            <div class="small text-muted">${file.fechaSubida || 'Reciente'}</div>
                        </div>
                    </div>
                    <button class="btn btn-sm btn-outline-danger border-0" onclick="eliminarAdjunto(${file.idAdjunto})" title="Eliminar">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </li>
            `;
                        ul.innerHTML += item;
                    });
                }

                // 3. Subir Archivo (Físico)
                function subirArchivos(files) {
                    if (files.length === 0) return;

                    const formData = new FormData();
                    formData.append('idMinuta', idMinutaGlobal);

                    // Soporte para múltiples archivos a la vez
                    for (let i = 0; i < files.length; i++) {
                        formData.append('archivos[]', files[i]);
                    }

                    // Mostrar carga
                    const ul = document.getElementById('listaAdjuntosUl');
                    ul.innerHTML = '<li class="list-group-item text-center py-3"><div class="spinner-border text-primary"></div> Subiendo archivos...</li>';

                    fetch('index.php?action=api_adjunto_subir', {
                            method: 'POST',
                            body: formData
                        })
                        .then(r => r.json())
                        .then(resp => {
                            if (resp.status === 'success') {
                                cargarAdjuntos(); // Recargar lista
                            } else {
                                alert('Error al subir: ' + resp.message);
                                cargarAdjuntos();
                            }
                        })
                        .catch(err => {
                            console.error(err);
                            alert('Error de conexión al subir archivo.');
                        });
                }

              // 4. Guardar Link (Enlace) - VERSIÓN DEFINITIVA (SIN ERRORES BOOTSTRAP)
function guardarLink() {
    const nombre = document.getElementById('linkNombre').value;
    const url = document.getElementById('linkUrl').value;

    if (!nombre || !url) {
        Swal.fire('Atención', 'Debe completar nombre y URL', 'warning');
        return;
    }

    const payload = {
        idMinuta: idMinutaGlobal,
        nombre: nombre,
        url: url
    };

    // NOTA: Asegúrate que en tu index.php 'api_adjunto_link' dirija a 'apiGuardarLink'
    fetch('index.php?action=api_adjunto_link', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(resp => {
        if (resp.status === 'success') {
            
            // --- CIERRE MANUAL DEL MODAL (EVITA ERROR DE BOOTSTRAP) ---
            const modalEl = document.getElementById('modalNuevoLink');
            if (modalEl) {
                // 1. Quitar clases visuales
                modalEl.classList.remove('show');
                modalEl.style.display = 'none';
                modalEl.setAttribute('aria-hidden', 'true');
                modalEl.removeAttribute('aria-modal');
                modalEl.removeAttribute('role');

                // 2. Limpiar el 'body' para reactivar el scroll
                document.body.classList.remove('modal-open');
                document.body.style.overflow = '';
                document.body.style.paddingRight = '';

                // 3. Eliminar el fondo gris oscuro (Backdrop)
                const backdrops = document.querySelectorAll('.modal-backdrop');
                backdrops.forEach(backdrop => backdrop.remove());
            }
            // ----------------------------------------------------------

            // Limpiar campos
            document.getElementById('linkNombre').value = '';
            document.getElementById('linkUrl').value = '';

            // Recargar la lista
            cargarAdjuntos();
            
            // Mensaje de éxito discreto
            const Toast = Swal.mixin({
                toast: true, position: 'top-end', showConfirmButton: false, timer: 3000
            });
            Toast.fire({ icon: 'success', title: 'Enlace guardado' });

        } else {
            Swal.fire('Error', resp.message || 'No se pudo guardar', 'error');
        }
    })
    .catch(err => {
        console.error(err);
        Swal.fire('Error', 'Error de conexión', 'error');
    });
}
                // 5. Eliminar Adjunto
                function eliminarAdjunto(idAdjunto) {
                    if (!confirm('¿Estás seguro de eliminar este adjunto?')) return;

                    fetch('index.php?action=api_adjunto_eliminar', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                idAdjunto: idAdjunto
                            })
                        })
                        .then(r => r.json())
                        .then(resp => {
                            if (resp.status === 'success') cargarAdjuntos();
                            else alert(resp.message);
                        });
                }

                // 6. Configuración Visual Drag & Drop
                function configurarDragDrop() {
                    const dropZone = document.getElementById('dropZoneAdjuntos');
                    const input = document.getElementById('inputArchivoOculto');

                    if (!dropZone) return;

                    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                        dropZone.addEventListener(eventName, preventDefaults, false);
                    });

                    function preventDefaults(e) {
                        e.preventDefault();
                        e.stopPropagation();
                    }

                    ['dragenter', 'dragover'].forEach(eventName => {
                        dropZone.addEventListener(eventName, () => dropZone.classList.add('bg-white', 'border-primary'));
                    });

                    ['dragleave', 'drop'].forEach(eventName => {
                        dropZone.addEventListener(eventName, () => dropZone.classList.remove('bg-white', 'border-primary'));
                    });

                    dropZone.addEventListener('drop', (e) => {
                        const dt = e.dataTransfer;
                        const files = dt.files;
                        subirArchivos(files);
                    });
                }
                // ============================================
                //  VARIABLES GLOBALES
                // ============================================
                const idMinutaGlobal = <?php echo isset($idMinuta) ? $idMinuta : 'null'; ?>;
                const esEditableJS = <?php echo $esEditable ? 'true' : 'false'; ?>;

                const idComisionGlobal = <?php echo isset($idComision) ? $idComision : 'null'; ?>;

                // -- Desarrollo (AutoSave) --
                let timerGuardado;
                let contadorTemas = 0;
                const statusDiv = document.getElementById('statusGuardado');
                const temasIniciales = <?= json_encode($data['temas']) ?>;

                // -- Asistencia (Tiempo Real) --
                let intervaloAsistencia;
                const VELOCIDAD_REFRESCO = 1000; // 1 Segundo exacto

                // ============================================
                //  INICIALIZACIÓN
                // ============================================
                // ============================================
                //  INICIALIZACIÓN
                // ============================================
                document.addEventListener('DOMContentLoaded', function() {
                    // Cargar Temas
                    if (temasIniciales && temasIniciales.length > 0) {
                        temasIniciales.forEach(tema => {
                            renderizarTema(tema);
                        });
                    } else {
                        agregarNuevoTema();
                    }

                    // --- CORRECCIÓN AQUÍ: Capitalizar primera letra ---
                    // Esto debe ir aquí afuera, no dentro de la función del botón
                    const inputVotacion = document.getElementById('inputNombreVotacion');
                    if (inputVotacion) {
                        inputVotacion.addEventListener('input', function() {
                            if (this.value.length > 0) {
                                // Capitaliza la primera letra y concatena el resto
                                this.value = this.value.charAt(0).toUpperCase() + this.value.slice(1);
                            }
                        });
                    }
                });

                // ============================================
                //  LÓGICA PESTAÑA: DESARROLLO (Temas + AutoSave)
                // ============================================
                function agregarNuevoTema() {
                    renderizarTema({});
                    triggerAutoSave();
                }

                function renderizarTema(data) {
                    contadorTemas++;
                    const contenedor = document.getElementById('accordionTemas');

                    const collapseId = `collapseTema${contadorTemas}`;
                    const headingId = `headingTema${contadorTemas}`;

                    const nombre = data.nombreTema || '';
                    const objetivo = data.objetivo || '';
                    const acuerdos = data.acuerdos || '';
                    const compromiso = data.compromiso || '';
                    const observacion = data.observacion || '';

                    // LÓGICA DE BLOQUEO
                    const bgState = esEditableJS ? 'bg-white' : 'bg-light';
                    const readOnlyAttr = esEditableJS ? '' : 'readonly';
                    const btnDeleteDisplay = esEditableJS ? '' : 'd-none'; // Ocultar botón eliminar

                    const itemHTML = `
            <div class="accordion-item mb-3 border shadow-sm item-tema">
                <h2 class="accordion-header" id="${headingId}">
                    <button class="accordion-button ${contadorTemas > 1 ? 'collapsed' : ''} fw-bold text-primary bg-light" type="button" data-bs-toggle="collapse" data-bs-target="#${collapseId}">
                        <i class="fas fa-list-ul me-2"></i> Tema ${contadorTemas}
                    </button>
                </h2>
                <div id="${collapseId}" class="accordion-collapse collapse ${contadorTemas === 1 ? 'show' : ''}" data-bs-parent="#accordionTemas">
                    <div class="accordion-body ${bgState}">
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-secondary">TEMA TRATADO</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="fas fa-heading"></i></span>
                                <textarea class="form-control input-tema auto-expand" rows="2" placeholder="Escribe el tema tratado..." ${readOnlyAttr}>${nombre}</textarea>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-secondary">OBJETIVO</label>
                            <textarea class="form-control input-objetivo auto-expand" rows="2" placeholder="Describa el objetivo..." ${readOnlyAttr}>${objetivo}</textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-secondary">ACUERDOS ADOPTADOS</label>
                            <textarea class="form-control input-acuerdos auto-expand" rows="2" placeholder="Liste los acuerdos..." ${readOnlyAttr}>${acuerdos}</textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-secondary">COMPROMISOS Y RESPONSABLES</label>
                            <textarea class="form-control input-compromiso auto-expand" rows="2" placeholder="Indique compromisos y responsables..." ${readOnlyAttr}>${compromiso}</textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-secondary">OBSERVACIONES Y COMENTARIOS</label>
                            <textarea class="form-control input-observacion auto-expand" rows="2" placeholder="Observaciones adicionales..." ${readOnlyAttr}>${observacion}</textarea>
                        </div>

                        <div class="text-end border-top pt-2 ${btnDeleteDisplay}">
                            <button class="btn btn-sm btn-outline-danger btn-eliminar-tema">
                                <i class="fas fa-trash-alt me-1"></i> Eliminar Tema
                            </button>
                        </div>

                    </div>
                </div>
            </div>
        `;

                    contenedor.insertAdjacentHTML('beforeend', itemHTML);
                    if (esEditableJS) {
                        activarListeners();
                    }
                }
                document.getElementById('accordionTemas').addEventListener('click', function(e) {
                    if (e.target.closest('.btn-eliminar-tema')) {
                        if (confirm('¿Estás seguro de eliminar este bloque de tema?')) {
                            e.target.closest('.item-tema').remove();
                            triggerAutoSave();
                        }
                    }
                });

                function activarListeners() {
                    const inputs = document.querySelectorAll('.item-tema textarea');
                    inputs.forEach(input => {
                        input.removeEventListener('input', handleInput);
                        input.addEventListener('input', handleInput);
                    });
                }

                function handleInput(e) {
                    const input = e.target;
                    // Mayúscula Inicial
                    let val = input.value;
                    if (val.length > 0) {
                        const firstChar = val.charAt(0);
                        if (firstChar !== firstChar.toUpperCase()) {
                            const start = input.selectionStart;
                            const end = input.selectionEnd;
                            input.value = firstChar.toUpperCase() + val.slice(1);
                            input.setSelectionRange(start, end);
                        }
                    }
                    triggerAutoSave();
                }

                function triggerAutoSave() {
                    statusDiv.innerHTML = '<span class="text-warning"><i class="fas fa-sync fa-spin"></i> Guardando...</span>';
                    clearTimeout(timerGuardado);
                    timerGuardado = setTimeout(guardarDatosEnServidor, 1000);
                }

                function guardarDatosEnServidor() {
                    const bloques = document.querySelectorAll('.item-tema');
                    let datosTemas = [];

                    bloques.forEach(bloque => {
                        const tema = bloque.querySelector('.input-tema').value;
                        const objetivo = bloque.querySelector('.input-objetivo').value;
                        const acuerdos = bloque.querySelector('.input-acuerdos').value;
                        const compromiso = bloque.querySelector('.input-compromiso').value;
                        const observacion = bloque.querySelector('.input-observacion').value;

                        if (tema.trim() || objetivo.trim() || acuerdos.trim() || compromiso.trim() || observacion.trim()) {
                            datosTemas.push({
                                nombreTema: tema,
                                objetivo: objetivo,
                                acuerdos: acuerdos,
                                compromiso: compromiso,
                                observacion: observacion
                            });
                        }
                    });

                    fetch('index.php?action=api_guardar_borrador', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                idMinuta: idMinutaGlobal,
                                temas: datosTemas
                            })
                        })
                        .then(r => r.json())
                        .then(data => {
                            if (data.status === 'success') {
                                statusDiv.innerHTML = '<span class="text-success"><i class="fas fa-check-double"></i> Guardado</span>';
                                setTimeout(() => {
                                    statusDiv.innerHTML = '<span class="text-muted"><i class="fas fa-check-circle"></i> Todo al día</span>';
                                }, 2000);
                            } else {
                                statusDiv.innerHTML = '<span class="text-danger">Error al guardar</span>';
                            }
                        })
                        .catch(e => console.error(e));
                }

                // ============================================
                //  LÓGICA PESTAÑA: ASISTENCIA (Tiempo Real)
                // ============================================

                // Iniciar monitoreo al entrar
                document.getElementById('asistencia-tab').addEventListener('shown.bs.tab', function() {
                    cargarAsistencia();
                    intervaloAsistencia = setInterval(cargarAsistencia, VELOCIDAD_REFRESCO);
                });

                // Detener monitoreo al salir
                document.getElementById('asistencia-tab').addEventListener('hidden.bs.tab', function() {
                    clearInterval(intervaloAsistencia);
                });

                function cargarAsistencia() {
                    // TRUCO: Agregamos timestamp para evitar caché y forzar recarga
                    fetch(`index.php?action=api_get_asistencia&id=${idMinutaGlobal}&t=${Date.now()}`)
                        .then(r => r.json())
                        .then(resp => {
                            if (resp.status === 'success') {
                                renderizarTablaAsistencia(resp.data);
                            }
                        })
                        .catch(e => console.error("Error polling asistencia", e));
                }

                function renderizarTablaAsistencia(data) {
                    const tbody = document.getElementById('tablaAsistenciaBody');
                    const contador = document.getElementById('contadorAsistencia');
                    const lista = data.asistentes;

                    // LÓGICA DE BLOQUEO
                    const disabledSwitch = esEditableJS ? '' : 'disabled';

                    let html = '';
                    let presentes = 0;

                    lista.forEach(user => {
                        const esPresente = parseInt(user.estaPresente) === 1;
                        if (esPresente) presentes++;

                        let horaDisplay = '--:--';
                        let badgeEstado = '<span class="badge bg-secondary text-white-50">Ausente</span>';
                        let claseFila = '';

                        if (esPresente) {
                            const fechaRaw = user.fechaRegistroAsistencia ? user.fechaRegistroAsistencia.replace(/-/g, '/') : null;
                            if (fechaRaw) {
                                const fechaObj = new Date(fechaRaw);
                                horaDisplay = fechaObj.toLocaleTimeString('es-CL', {
                                    hour: '2-digit',
                                    minute: '2-digit'
                                });
                            }

                            if (user.estado_visual === 'manual') {
                                badgeEstado = '<span class="badge bg-primary"><i class="fas fa-user-edit me-1"></i>Manual (ST)</span>';
                                claseFila = 'bg-success bg-opacity-10';
                            } else if (user.estado_visual === 'atrasado') {
                                badgeEstado = '<span class="badge bg-warning text-dark"><i class="fas fa-clock me-1"></i>Atrasado</span>';
                                claseFila = 'bg-warning bg-opacity-10';
                            } else {
                                badgeEstado = '<span class="badge bg-success"><i class="fas fa-check me-1"></i>Presente</span>';
                                claseFila = 'bg-success bg-opacity-10';
                            }
                        }

                        const checked = esPresente ? 'checked' : '';

                        html += `
                <tr id="tr-user-${user.idUsuario}" class="${claseFila}">
                    <td>
                        <div class="fw-bold text-dark">${user.pNombre} ${user.aPaterno}</div>
                        <small class="text-muted">Consejero Regional</small>
                    </td>
                    <td>${horaDisplay}</td>
                    <td id="td-estado-${user.idUsuario}">${badgeEstado}</td>
                    <td class="text-center">
                        <div class="form-check form-switch d-flex justify-content-center">
                            <input class="form-check-input" type="checkbox" role="switch" 
                                   ${checked} ${disabledSwitch} 
                                   onchange="toggleAsistencia(${user.idUsuario}, this.checked)">
                        </div>
                    </td>
                </tr>
            `;
                    });

                    // Evitamos renderizar si el usuario está interactuando, pero si está bloqueado da igual
                    if (esEditableJS) {
                        if (document.activeElement.type !== 'checkbox') tbody.innerHTML = html;
                    } else {
                        tbody.innerHTML = html;
                    }

                    if (contador) contador.innerText = `${presentes} / ${lista.length}`;
                }

                function toggleAsistencia(idUsuario, nuevoEstado) {
                    // 1. CAMBIO VISUAL INMEDIATO (Optimistic UI)
                    const fila = document.getElementById(`tr-user-${idUsuario}`);
                    const celdaEstado = document.getElementById(`td-estado-${idUsuario}`);

                    if (fila && celdaEstado) {
                        if (nuevoEstado) {
                            // Si activamos: Ponemos verde y badge azul de Manual
                            fila.className = 'bg-success bg-opacity-10';
                            celdaEstado.innerHTML = '<span class="badge bg-primary"><i class="fas fa-user-edit me-1"></i>Manual (ST)</span>';
                        } else {
                            // Si desactivamos: Quitamos color y ponemos Ausente
                            fila.className = '';
                            celdaEstado.innerHTML = '<span class="badge bg-secondary text-white-50">Ausente</span>';
                        }
                    }

                    // 2. Detener polling para que no sobrescriba nuestro cambio visual
                    clearInterval(intervaloAsistencia);

                    // 3. Enviar cambio al servidor en segundo plano
                    fetch('index.php?action=api_alternar_asistencia', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                idMinuta: idMinutaGlobal,
                                idUsuario: idUsuario,
                                estado: nuevoEstado
                            })
                        })
                        .then(r => r.json())
                        .then(resp => {
                            if (resp.status === 'success') {
                                // Opcional: Recargar datos reales para asegurar sincronía
                                // cargarAsistencia(); 
                                // Nota: Como ya hicimos el cambio visual, no es estrictamente urgente recargar YA,
                                // el polling lo hará en unos segundos.
                            } else {
                                alert('Error al guardar: ' + resp.message);
                                cargarAsistencia(); // Si falló, recargamos para revertir el color
                            }
                        })
                        .catch(err => {
                            console.error(err);
                            cargarAsistencia(); // Revertir si hay error de red
                        })
                        .finally(() => {
                            // 4. Reactivar el monitoreo automático
                            intervaloAsistencia = setInterval(cargarAsistencia, VELOCIDAD_REFRESCO);
                        });
                }

                // ============================================
                //  LÓGICA PESTAÑA: VOTACIONES (Tiempo Real)
                // ============================================
                let intervaloVotaciones;

                document.getElementById('votaciones-tab').addEventListener('shown.bs.tab', function() {
                    cargarVotaciones();
                    intervaloVotaciones = setInterval(cargarVotaciones, 1000); // 1 segundo
                    // Mostrar indicador de "en vivo" si existe en el HTML
                    const indicador = document.getElementById('liveIndicator');
                    if (indicador) indicador.classList.remove('d-none');
                });

                document.getElementById('votaciones-tab').addEventListener('hidden.bs.tab', function() {
                    clearInterval(intervaloVotaciones);
                    const indicador = document.getElementById('liveIndicator');
                    if (indicador) indicador.classList.add('d-none');
                });

                // ============================================
                //  FUNCIONES DE VOTACIÓN (SECRETARIO)
                // ============================================


                function cerrarVotacion(idVotacion) {
                    // --- POPUP: CONFIRMACIÓN DE CIERRE ---
                    Swal.fire({
                        title: '¿Cerrar Votación?',
                        text: "Se dejarán de recibir votos y se calcularán los resultados finales.",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#d33',
                        cancelButtonColor: '#3085d6',
                        confirmButtonText: 'Sí, cerrar votación',
                        cancelButtonText: 'Cancelar'
                    }).then((result) => {
                        if (result.isConfirmed) {

                            // Si confirma, enviamos la petición
                            fetch('index.php?action=api_cerrar_votacion', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json'
                                    },
                                    body: JSON.stringify({
                                        idVotacion: idVotacion
                                    })
                                })
                                .then(r => r.json())
                                .then(resp => {
                                    if (resp.status === 'success') {

                                        // --- POPUP: ÉXITO AL CERRAR ---
                                        Swal.fire(
                                            '¡Cerrada!',
                                            'La votación ha finalizado correctamente.',
                                            'success'
                                        );
                                        cargarVotaciones(); // Actualizar para ver gráfico final
                                    } else {
                                        Swal.fire('Error', resp.message, 'error');
                                    }
                                });
                        }
                    });
                }


                function cargarVotaciones() {
                    fetch(`index.php?action=api_get_votaciones&id=${idMinutaGlobal}&t=${Date.now()}`)
                        .then(r => r.json())
                        .then(resp => {
                            if (resp.status === 'success') {
                                renderizarVotaciones(resp.data);
                            }
                        })
                        .catch(e => console.error(e));
                }

                function renderizarVotaciones(data) {
                    const container = document.getElementById('contenedorVotaciones');
                    let html = '';

                    if (data.length === 0) {
                        html = '<div class="text-center py-5 text-muted"><p>No hay votaciones registradas.</p></div>';
                    } else {
                        data.forEach(v => {
                            const esAbierta = (v.habilitada == 1);

                            // 1. Determinar Colores según Resultado (Solo si está CERRADA)
                            let colorTema = 'secondary'; // Gris por defecto (SIN DATOS)
                            let textClass = 'text-white'; // Texto blanco para la mayoría

                            if (!esAbierta) {
                                if (v.resultado === 'APROBADO') {
                                    colorTema = 'success'; // Verde
                                } else if (v.resultado === 'RECHAZADO') {
                                    colorTema = 'danger'; // Rojo
                                } else if (v.resultado === 'EMPATE') {
                                    colorTema = 'warning'; // Amarillo
                                    textClass = 'text-dark'; // Texto oscuro para que se lea bien en amarillo
                                }
                            } else {
                                colorTema = 'primary'; // Azul para EN CURSO (para no confundir con aprobado)
                            }

                            // 2. Configurar Clases CSS
                            // El borde sigue el color del tema
                            const claseBorde = `border-${colorTema}`;

                            // El Badge
                            const animacion = esAbierta ? 'fa-beat-fade' : '';
                            const badgeEstado = esAbierta ?
                                `<span class="badge bg-primary"><i class="fas fa-circle ${animacion} me-1"></i>EN CURSO</span>` :
                                `<span class="badge bg-${colorTema} ${textClass}">CERRADA: ${v.resultado}</span>`;

                            const btnCerrar = esAbierta ?
                                `<button class="btn btn-sm btn-outline-danger" onclick="cerrarVotacion(${v.idVotacion})">Cerrar Votación</button>` :
                                '';

                            // 3. Generar Grilla de Asistentes
                            let gridHtml = '<div class="row g-2 mt-2">';
                            if (v.detalle_asistentes && v.detalle_asistentes.length > 0) {
                                v.detalle_asistentes.forEach(persona => {
                                    let icon = '<i class="fas fa-clock"></i>';
                                    if (persona.voto === 'SI') icon = '<i class="fas fa-thumbs-up"></i>';
                                    if (persona.voto === 'NO') icon = '<i class="fas fa-thumbs-down"></i>';
                                    if (persona.voto === 'ABSTENCION') icon = '<i class="fas fa-minus-circle"></i>';

                                    gridHtml += `
                            <div class="col-6 col-md-4 col-lg-3">
                                <div class="p-2 rounded border small fw-bold d-flex justify-content-between align-items-center ${persona.clase}" title="${persona.voto}">
                                    <span class="text-truncate me-2">${persona.nombre}</span>
                                    <span>${icon}</span>
                                </div>
                            </div>
                        `;
                                });
                            } else {
                                gridHtml += '<div class="col-12 text-muted fst-italic">No hay asistentes registrados.</div>';
                            }
                            gridHtml += '</div>';

                            // 4. Armar HTML Final
                            html += `
                <div class="card mb-3 shadow-sm border-start border-4 ${claseBorde}">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <h5 class="card-title fw-bold mb-0 text-dark">${v.nombreVotacion}</h5>
                                <small class="text-muted">
                                    ${v.nombreComision || 'Comisión'} • ${new Date(v.fechaCreacion).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}
                                </small>
                            </div>
                            <div class="text-end">
                                ${badgeEstado}
                                <div class="mt-2">${btnCerrar}</div>
                            </div>
                        </div>

                        <div class="d-flex gap-3 text-muted small fw-bold border-bottom pb-2 mb-2">
                            <span><i class="fas fa-check text-success"></i> SÍ: ${v.contadores.SI}</span>
                            <span><i class="fas fa-times text-danger"></i> NO: ${v.contadores.NO}</span>
                            <span><i class="fas fa-minus text-warning"></i> ABS: ${v.contadores.ABS}</span>
                            <span class="${v.contadores.PEND > 0 ? 'text-primary' : ''}"><i class="fas fa-user-clock"></i> PEND: ${v.contadores.PEND}</span>
                        </div>
                        
                        ${gridHtml}

                    </div>
                </div>`;
                        });
                    }

                    if (container) container.innerHTML = html;
                }

                function verDetalleVoto(idVotacion) {
                    const tbody = document.getElementById('tablaDetalleVoto');
                    // Asegúrate de agregar el MODAL al final de tu archivo HTML si no lo has hecho
                    const modalEl = document.getElementById('modalDetalleVoto');
                    if (!modalEl) return; // Seguridad

                    tbody.innerHTML = '<tr><td colspan="2" class="text-center">Cargando...</td></tr>';
                    const modal = new bootstrap.Modal(modalEl);
                    modal.show();

                    fetch(`index.php?action=api_get_detalle_voto&id=${idVotacion}`)
                        .then(r => r.json())
                        .then(resp => {
                            if (resp.status === 'success') {
                                let html = '';
                                resp.data.forEach(v => {
                                    let color = 'text-dark';
                                    if (v.opcionVoto === 'SI') color = 'text-success fw-bold';
                                    if (v.opcionVoto === 'NO') color = 'text-danger fw-bold';
                                    if (v.opcionVoto === 'ABSTENCION') color = 'text-warning fw-bold';

                                    html += `<tr>
                            <td>${v.pNombre} ${v.aPaterno}</td>
                            <td class="${color}">${v.opcionVoto}</td>
                        </tr>`;
                                });
                                tbody.innerHTML = html || '<tr><td colspan="2" class="text-center text-muted">Nadie ha votado aún.</td></tr>';
                            }
                        });
                }

                function finalizarReunion() {
                    Swal.fire({
                        title: '¿Finalizar la Reunión?',
                        html: `
            <div class="text-start small">
                <p>Se realizarán las siguientes acciones:</p>
                <ol>
                    <li>Se registrará la hora de término.</li>
                    <li>Se generará el PDF de asistencia.</li>
                    <li>Se enviará el correo de respaldo.</li>
                </ol>
                <p class="text-danger fw-bold mb-0">Esta acción habilitará el botón para enviar a firma.</p>
            </div>
        `,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#dc3545', // Rojo para indicar acción importante
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Sí, Finalizar',
                        cancelButtonText: 'Cancelar',
                        showLoaderOnConfirm: true,
                        preConfirm: () => {
                            // Retornamos la promesa del fetch para que Swal maneje el loading
                            return fetch('index.php?action=api_finalizar_reunion', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json'
                                    },
                                    body: JSON.stringify({
                                        idMinuta: idMinutaGlobal
                                    })
                                })
                                .then(response => {
                                    if (!response.ok) throw new Error(response.statusText);
                                    return response.json();
                                })
                                .catch(error => {
                                    Swal.showValidationMessage(`Error de conexión: ${error}`);
                                });
                        }
                    }).then((result) => {
                        if (result.isConfirmed) {
                            const resp = result.value;
                            const btn = document.getElementById('btnFinalizar');

                            // Aceptamos 'success' o 'warning' (ej: si falló el correo pero se cerró la reunión)
                            if (resp.status === 'success' || resp.status === 'warning') {

                                // --- ACTUALIZACIÓN VISUAL DE LA UI (NUEVO) ---
                                // 1. Actualizar el Badge de Estado (De "En Curso" a "Finalizada")
                                const badge = document.getElementById('badgeEstadoSesion');
                                if (badge) {
                                    badge.className = 'badge bg-info text-dark ms-2 border border-info';
                                    badge.innerHTML = '<i class="fas fa-clock me-1"></i> REUNIÓN FINALIZADA (ESPERA APROBACIÓN)';
                                }
                                // ---------------------------------------------

                                // 2. Actualizar Botón Finalizar (Deshabilitarlo visualmente)
                                if (btn) {
                                    btn.classList.remove('btn-danger');
                                    btn.classList.add('btn-secondary');
                                    btn.disabled = true;
                                    btn.innerHTML = '<i class="fas fa-check-circle me-1"></i> Reunión Finalizada y Asistencia Enviada';
                                }

                                // 3. Activar Botón "Enviar a Firma"
                                const btnFirma = document.getElementById('btnEnviarFirma');
                                if (btnFirma) {
                                    btnFirma.disabled = false;
                                    btnFirma.classList.remove('btn-secondary');
                                    btnFirma.classList.add('btn-success', 'text-white');
                                    btnFirma.title = "Enviar solicitud de firma";
                                }

                                // Mostrar mensaje final
                                Swal.fire({
                                    title: resp.status === 'success' ? '¡Reunión Finalizada!' : 'Finalizada con Advertencia',
                                    text: resp.message,
                                    icon: resp.status, // success o warning
                                    confirmButtonText: 'Excelente'
                                });

                            } else {
                                // ERROR
                                Swal.fire('Error', resp.message || 'Ocurrió un error inesperado', 'error');
                            }
                        }
                    });
                }

                function enviarAFirma() {
                    // 1. Detección del botón y contexto (Envío normal vs Reenvío)
                    let btn = document.getElementById('btnEnviarFirma') || document.getElementById('btnReenviarFirma');

                    // Seguridad por si no encuentra el ID exacto
                    if (!btn) btn = document.activeElement;

                    if (!btn) {
                        Swal.fire('Error', 'No se pudo localizar el botón de acción.', 'error');
                        return;
                    }

                    // Determinar textos según si es reenvío o no
                    const esReenvio = btn.innerText.toLowerCase().includes('reenviar') || btn.innerText.toLowerCase().includes('correc');

                    const tituloSwal = esReenvio ? '¿Reenviar Minuta Corregida?' : '¿Enviar a Firma?';
                    const textoSwal = esReenvio ?
                        'Se notificará nuevamente a los presidentes indicando que se han aplicado las correcciones.' :
                        'Se enviará la minuta para su APROBACIÓN y firma electrónica.';
                    const colorBtn = esReenvio ? '#ffc107' : '#198754'; // Amarillo para reenvío, Verde para normal
                    const txtBtn = esReenvio ? 'Sí, Reenviar' : 'Sí, Enviar';

                    // 2. Disparar SweetAlert
                    Swal.fire({
                        title: tituloSwal,
                        text: textoSwal,
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: colorBtn,
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: txtBtn,
                        cancelButtonText: 'Cancelar',
                        showLoaderOnConfirm: true, // Spinner automático en el botón de confirmación del Swal
                        preConfirm: () => {
                            return fetch('index.php?action=api_enviar_aprobacion', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json'
                                    },
                                    body: JSON.stringify({
                                        idMinuta: idMinutaGlobal
                                    })
                                })
                                .then(response => {
                                    if (!response.ok) throw new Error(response.statusText);
                                    return response.json();
                                })
                                .catch(error => {
                                    Swal.showValidationMessage(`Error de conexión: ${error}`);
                                });
                        }
                    }).then((result) => {
                        if (result.isConfirmed) {
                            const resp = result.value;

                            if (resp.status === 'success') {
                                Swal.fire({
                                    title: '¡Enviado!',
                                    text: resp.message,
                                    icon: 'success',
                                    timer: 2500,
                                    showConfirmButton: false
                                }).then(() => {
                                    // RECARGAR PÁGINA PARA APLICAR EL BLOQUEO DE PHP
                                    window.location.reload();
                                });

                                // Actualizar UI del botón
                                btn.disabled = true;
                                btn.classList.remove('btn-success', 'btn-warning', 'fa-beat-fade');
                                btn.classList.add('btn-secondary');

                                // Cambiar texto e ícono
                                const nuevoTexto = esReenvio ? 'Minuta Reenviada' : 'Minuta Enviada a Presidentes';
                                btn.innerHTML = `<i class="fas fa-check-double me-1"></i> ${nuevoTexto}`;

                            } else {
                                Swal.fire('Error', resp.message || 'No se pudo enviar la solicitud.', 'error');
                            }
                        }
                    });
                }

                function iniciarReunion() {
                    if (!confirm("¿Desea HABILITAR la sala de reuniones?\n\nEsto permitirá que los Consejeros registren su asistencia y el cronómetro de 30 minutos comenzará ahora.")) {
                        return;
                    }

                    const btn = document.getElementById('btnIniciar');
                    const txt = btn.innerHTML;
                    btn.disabled = true;
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Iniciando...';

                    fetch('index.php?action=api_iniciar_reunion', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                idMinuta: idMinutaGlobal
                            })
                        })
                        .then(r => r.json())
                        .then(resp => {
                            if (resp.status === 'success') {
                                alert("✅ " + resp.message);
                                location.reload(); // Recargar para cambiar el botón a "Finalizar"
                            } else {
                                alert("❌ Error: " + resp.message);
                                btn.disabled = false;
                                btn.innerHTML = txt;
                            }
                        });
                }



                function crearVotacion() {
                    const inputNombre = document.getElementById('inputNombreVotacion');
                    const selectComision = document.getElementById('selectComisionVoto'); // Nuevo

                    if (!inputNombre || inputNombre.value.trim() === '') {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Falta información',
                            text: 'Debe escribir el Tema o Moción.'
                        });
                        return;
                    }

                    // Validación extra de seguridad
                    if (!selectComision || !selectComision.value) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'No se pudo identificar la comisión.'
                        });
                        return;
                    }

                    Swal.fire({
                        title: '¿Crear Votación?',
                        text: `Se abrirá la votación: "${inputNombre.value}"`,
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '#198754',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Sí, crear'
                    }).then((result) => {
                        if (result.isConfirmed) {

                            const payload = {
                                idMinuta: idMinutaGlobal,
                                nombre: inputNombre.value.trim(),
                                idComision: selectComision.value // Enviamos el ID seleccionado
                            };

                            fetch('index.php?action=api_crear_votacion', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json'
                                    },
                                    body: JSON.stringify(payload)
                                })
                                .then(r => r.json())
                                .then(resp => {
                                    if (resp.status === 'success') {
                                        Swal.fire({
                                            title: '¡Votación Habilitada!',
                                            text: 'Los consejeros ya pueden votar.',
                                            icon: 'success',
                                            timer: 2000,
                                            showConfirmButton: false
                                        });
                                        inputNombre.value = '';
                                        cargarVotaciones();
                                    } else {
                                        Swal.fire('Error', resp.message, 'error');
                                    }
                                })
                                .catch(err => {
                                    console.error(err);
                                    Swal.fire('Error', 'Error de conexión', 'error');
                                });
                        }
                    });
                }




                // Esta función se llama desde el botón dentro del panel negro
                function cerrarVotacionActiva() {
                    const id = document.getElementById('idVotacionEnCurso').value;
                    cerrarVotacion(id);
                }

                function cerrarVotacion(idVotacion) {
                    Swal.fire({
                        title: '¿Cerrar Votación?',
                        text: "Se dejarán de recibir votos y se calcularán los resultados finales.",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#d33',
                        confirmButtonText: 'Sí, cerrar votación',
                        cancelButtonText: 'Cancelar'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            fetch('index.php?action=api_cerrar_votacion', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json'
                                    },
                                    body: JSON.stringify({
                                        idVotacion: idVotacion
                                    })
                                })
                                .then(r => r.json())
                                .then(resp => {
                                    if (resp.status === 'success') {

                                        // --- CORRECCIÓN: SOLO ALERTAR Y RECARGAR LISTA ---
                                        Swal.fire(
                                            '¡Cerrada!',
                                            'La votación ha finalizado correctamente.',
                                            'success'
                                        );
                                        cargarVotaciones();
                                        // ------------------------------------------------

                                    } else {
                                        Swal.fire('Error', resp.message, 'error');
                                    }
                                });
                        }
                    });
                }
            </script>

            <style>
                /* Estilos personalizados para las Tabs */
                .nav-tabs .nav-link {
                    color: #6c757d;
                    border: none;
                    border-bottom: 3px solid transparent;
                    padding: 12px 20px;
                    transition: all 0.3s ease;
                }

                .nav-tabs .nav-link:hover {
                    color: #0d6efd;
                    background-color: #f8f9fa;
                    border-color: transparent;
                }

                .nav-tabs .nav-link.active {
                    color: #0d6efd;
                    background-color: #fff;
                    border-bottom: 3px solid #0d6efd;
                }

                .card-header-tabs {
                    margin-bottom: -1px;
                }

                /* Para que los textarea se vean bien */
                .auto-expand {
                    resize: vertical;
                    min-height: 60px;
                }
            </style>