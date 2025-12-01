<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold text-dark mb-1">Minutas Pendientes de Aprobación</h4>
            <p class="text-muted mb-0">Bienvenido. Aquí tiene las actas que requieren su firma.</p>
        </div>
        <div>
            <span class="badge bg-white text-dark border px-3 py-2 rounded-pill shadow-sm">
                <i class="fas fa-clock text-warning me-1"></i> Pendientes: <strong><?= count($data['minutas']) ?></strong>
            </span>
        </div>
    </div>

    <?php if (empty($data['minutas'])): ?>
        <div class="card border-0 shadow-sm text-center py-5">
            <div class="card-body">
                <div class="mb-3 text-success opacity-50">
                    <i class="fas fa-check-circle fa-4x"></i>
                </div>
                <h5 class="fw-bold text-dark">¡Todo al día!</h5>
                <p class="text-muted">No tiene minutas pendientes de firma en este momento.</p>
            </div>
        </div>
    <?php else: ?>

        <div class="row g-4">
            <?php foreach ($data['minutas'] as $m): ?>
                <?php
                $fechaFmt = date('d/m/Y', strtotime($m['fechaMinuta']));
                $horaFmt = date('H:i', strtotime($m['horaMinuta']));
                $firmasActuales = $m['firmas_actuales'];
                $firmasTotal = $m['presidentesRequeridos'];
                $porcentaje = ($firmasTotal > 0) ? ($firmasActuales / $firmasTotal) * 100 : 0;

                $esCorreccion = ($m['correcciones_realizadas'] > 0);

                // Detectar si está en revisión por el ST
                $enRevisionST = ($m['estadoMinuta'] === 'REQUIERE_REVISION');

                // Definir estilos según estado
                $borderClass = 'border-warning'; // Por defecto
                if ($enRevisionST) $borderClass = 'border-secondary'; // Gris si está en revisión
                else if ($esCorreccion) $borderClass = 'border-success'; // Verde si ya volvió corregida
                ?>

                <div class="col-12">
                    <div class="card border-0 shadow-sm border-start border-4 <?= $borderClass ?>">
                        <div class="card-body p-4">

                            <!-- ALERTA: SOLO SI YA FUE CORREGIDA Y ESTÁ PENDIENTE -->
                            <?php if ($esCorreccion && !$enRevisionST): ?>
                                <div class="alert alert-success py-2 px-3 mb-3 border-0 bg-opacity-10 bg-success d-flex align-items-center">
                                    <i class="fas fa-check-circle text-success me-2 fs-5"></i>
                                    <div>
                                        <strong class="text-success">¡Actualización Disponible!</strong>
                                        <span class="text-dark small ms-1">Se han aplicado correcciones. Por favor revise el nuevo borrador.</span>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- ALERTA: SI ESTÁ EN PODER DEL SECRETARIO -->
                            <?php if ($enRevisionST): ?>
                                <div class="alert alert-secondary py-2 px-3 mb-3 border-0 bg-opacity-10 bg-secondary d-flex align-items-center">
                                    <i class="fas fa-tools text-secondary me-2 fs-5"></i>
                                    <div>
                                        <strong class="text-secondary">En Corrección</strong>
                                        <span class="text-dark small ms-1">El Secretario Técnico está editando esta minuta según sus observaciones.</span>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="row align-items-center">
                                <!-- 1. Información Principal -->
                                <div class="col-lg-8">
                                    <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center mb-3">
                                        <h5 class="fw-bold text-primary mb-1 mb-md-0 me-3">
                                            <i class="fas fa-file-alt me-2"></i>Minuta N° <?= $m['idMinuta'] ?>
                                        </h5>
                                        <h6 class="text-secondary fw-bold mb-1 mb-md-0 me-3 border-start ps-3 border-2">
                                            <?= $m['nombreReunion'] ?? 'Reunión' ?>
                                        </h6>

                                        <?php if ($enRevisionST): ?>
                                            <span class="badge bg-secondary text-white ms-md-auto">
                                                <i class="fas fa-user-cog me-1"></i> EN GESTIÓN ST
                                            </span>
                                        <?php elseif ($esCorreccion): ?>
                                            <span class="badge bg-success text-white ms-md-auto">
                                                <i class="fas fa-sync-alt me-1"></i> CORREGIDA
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark bg-opacity-25 border border-warning ms-md-auto">
                                                PENDIENTE DE SU FIRMA
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="small text-muted text-uppercase fw-bold" style="font-size:0.7rem">Comisión</label>
                                            <div class="fw-bold text-dark"><?= $m['nombreComision'] ?></div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="small text-muted text-uppercase fw-bold" style="font-size:0.7rem">Secretario Técnico</label>
                                            <div><?= $m['nombreSecretario'] ?></div>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="small text-muted text-uppercase fw-bold" style="font-size:0.7rem">Fecha</label>
                                            <div><i class="far fa-calendar me-1 text-muted"></i> <?= $fechaFmt ?></div>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="small text-muted text-uppercase fw-bold" style="font-size:0.7rem">Hora</label>
                                            <div><i class="far fa-clock me-1 text-muted"></i> <?= $horaFmt ?></div>
                                        </div>

                                        <div class="col-md-6">
                                            <label class="small text-muted text-uppercase fw-bold mb-1" style="font-size:0.7rem">Progreso Aprobación</label>
                                            <div class="d-flex align-items-center">
                                                <div class="progress flex-grow-1 me-2" style="height: 8px;">
                                                    <div class="progress-bar bg-success" role="progressbar" style="width: <?= $porcentaje ?>%"></div>
                                                </div>
                                                <small class="text-muted fw-bold" style="font-size: 0.8rem;">
                                                    <?= $firmasActuales ?> de <?= $firmasTotal ?> firmas
                                                </small>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mt-3 pt-3 border-top">
                                        <button class="btn btn-sm btn-light text-primary border" onclick="verAdjuntos(<?= $m['idMinuta'] ?>)">
                                            <i class="fas fa-paperclip me-1"></i> Ver Documentos Adjuntos
                                        </button>
                                    </div>
                                </div>

                                <!-- 2. Botones de Acción -->
                                <div class="col-lg-4 text-end border-start ps-lg-4 mt-4 mt-lg-0">
                                    <div class="d-grid gap-2">
                                        <a href="index.php?action=ver_minuta_borrador&id=<?= $m['idMinuta'] ?>" target="_blank" class="btn btn-outline-secondary btn-block">
                                            <i class="fas fa-eye me-2"></i> Ver Minuta (PDF)
                                        </a>

                                        <?php if ($enRevisionST): ?>
                                            <!-- BOTONES DESHABILITADOS SI ESTÁ EN REVISIÓN -->
                                            <button class="btn btn-outline-secondary btn-block" disabled>
                                                <i class="fas fa-clock me-2"></i> Esperando Corrección...
                                            </button>
                                            <button class="btn btn-secondary btn-block py-2 fw-bold shadow-sm" disabled>
                                                <i class="fas fa-lock me-2"></i> Aprobación Pausada
                                            </button>
                                        <?php else: ?>
                                            <!-- BOTONES ACTIVOS SI ES TU TURNO -->
                                            <button class="btn btn-outline-danger btn-block" onclick="abrirFeedback(<?= $m['idMinuta'] ?>)">
                                                <i class="fas fa-comment-dots me-2"></i> Solicitar Corrección
                                            </button>

                                            <button class="btn <?= $esCorreccion ? 'btn-success' : 'btn-primary' ?> btn-block py-2 fw-bold shadow-sm" onclick="firmarMinuta(<?= $m['idMinuta'] ?>)">
                                                <i class="fas fa-file-signature me-2"></i>
                                                <?= $esCorreccion ? 'APROBAR VERSIÓN CORREGIDA' : 'APROBAR Y FIRMAR' ?>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- MODALES -->

<!-- Modal Feedback -->
<div class="modal fade" id="modalFeedback" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Solicitar Corrección</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-light border mb-3 small">
                    <strong>Nota:</strong> Al enviar esta solicitud, la minuta volverá a estado <b>PENDIENTE</b> y el Secretario Técnico será notificado para realizar los cambios.
                </div>
                <input type="hidden" id="idMinutaFeedback">
                <label class="form-label fw-bold">Describa la corrección necesaria:</label>
                <textarea class="form-control" id="textoFeedback" rows="4" placeholder="Ej: Corregir el acuerdo del punto 2..."></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" onclick="enviarFeedback()">Enviar Solicitud</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Adjuntos -->
<div class="modal fade" id="modalAdjuntos" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-folder-open me-2 text-warning"></i>Documentos Adjuntos</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div id="listaAdjuntos" class="list-group list-group-flush"></div>
                <div id="sinAdjuntosMsg" class="text-center text-muted py-4" style="display:none;">
                    Sin archivos adjuntos.
                </div>
            </div>
            <div class="modal-footer py-1">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- LÓGICA JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    // 1. FIRMAR (ACTUALIZADO CON RETRASO PARA PDF)
    function firmarMinuta(id) {
        Swal.fire({
            title: 'Confirmación de Firma',
            html: "¿Ha revisado el documento y <b>APRUEBA</b> su contenido?<br><br>Esta acción registrará su firma electrónica.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#0d6efd',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, Firmar y Aprobar',
            cancelButtonText: 'Cancelar',
            showLoaderOnConfirm: true,
            allowOutsideClick: () => !Swal.isLoading(),
            preConfirm: () => {
                return fetch('index.php?action=api_firmar_minuta', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ idMinuta: id })
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(response.statusText)
                    }
                    return response.json()
                })
                .catch(error => {
                    Swal.showValidationMessage(`Error de conexión: ${error}`)
                })
            }
        }).then((result) => {
            if (result.isConfirmed) {
                const resp = result.value;

                if (resp.status && resp.status.includes('success')) {
                    
                    // LOGICA DE RESPUESTA SEGÚN ESTADO FINAL
                    if (resp.estado_nuevo === 'APROBADA') {
                        // CASO 1: MINUTA TOTALMENTE APROBADA
                        Swal.fire({
                            title: '¡Minuta Aprobada!',
                            text: 'El documento oficial ha sido generado exitosamente. Haga clic en OK para abrirlo.',
                            icon: 'success',
                            confirmButtonText: 'OK, Ver Documento'
                        }).then(() => {
                            // 1. Abrir PDF
                            window.open(`index.php?action=ver_minuta_oficial&id=${id}`, '_blank');
                            
                            // 2. Recargar con pequeño retraso para que no cancele la apertura del PDF
                            setTimeout(() => {
                                location.reload();
                            }, 500);
                        });
                    } else {
                        // CASO 2: FIRMA PARCIAL (Mixta)
                        Swal.fire({
                            title: 'Firma Registrada',
                            text: 'Su firma se guardó correctamente. La minuta permanecerá pendiente hasta que los demás presidentes firmen.',
                            icon: 'success',
                            confirmButtonText: 'Entendido'
                        }).then(() => {
                            location.reload();
                        });
                    }
                    
                } else {
                    Swal.fire('Error', resp.message || 'Error desconocido', 'error');
                }
            }
        });
    }

    // 2. FEEDBACK
    function abrirFeedback(id) {
        document.getElementById('idMinutaFeedback').value = id;
        document.getElementById('textoFeedback').value = '';
        new bootstrap.Modal(document.getElementById('modalFeedback')).show();
    }

    function enviarFeedback() {
        const id = document.getElementById('idMinutaFeedback').value;
        const txt = document.getElementById('textoFeedback').value;

        if (txt.trim().length < 5) {
            Swal.fire('Atención', 'Por favor detalle la corrección necesaria.', 'warning');
            return;
        }

        Swal.fire({
            title: 'Enviando solicitud...',
            text: 'Por favor espere',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading() }
        });

        fetch('index.php?action=api_enviar_feedback', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    idMinuta: id,
                    feedback: txt
                })
            })
            .then(r => r.json())
            .then(resp => {
                if (resp.status === 'success') {
                    Swal.fire('Enviado', 'Observaciones enviadas al Secretario Técnico.', 'success')
                        .then(() => location.reload());
                } else {
                    Swal.fire('Error', resp.message, 'error');
                }
            });
    }

    // 3. ADJUNTOS
    // 3. ADJUNTOS
    function verAdjuntos(id) {
        const lista = document.getElementById('listaAdjuntos');
        const msg = document.getElementById('sinAdjuntosMsg');

        lista.innerHTML = '<div class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div> Cargando...</div>';
        msg.style.display = 'none';
        new bootstrap.Modal(document.getElementById('modalAdjuntos')).show();

        fetch(`index.php?action=api_ver_adjuntos_minuta&id=${id}`)
            .then(r => r.json())
            .then(resp => {
                lista.innerHTML = '';
                if (resp.data && resp.data.length > 0) {
                    msg.style.display = 'none';
                    resp.data.forEach(a => {
                        // MODIFICACIÓN CLAVE: Usar a.nombreArchivo o derivar de la ruta.
                        let nombreMostrar = a.nombreArchivo || a.pathAdjunto.split('/').pop();

                        let urlVisor = `index.php?action=ver_archivo_adjunto&id=${a.idAdjunto}`;
                        let extension = nombreMostrar.split('.').pop().toLowerCase();
                        let icon = 'fa-file text-secondary'; // Icono default

                        // Iconos según extensión
                        if (['pdf'].includes(extension)) icon = 'fa-file-pdf text-danger';
                        else if (['doc', 'docx'].includes(extension)) icon = 'fa-file-word text-primary';
                        else if (['xls', 'xlsx'].includes(extension)) icon = 'fa-file-excel text-success';
                        else if (['jpg', 'jpeg', 'png', 'gif'].includes(extension)) icon = 'fa-file-image text-info';

                        // Lógica para Links Externos y Corrección de "www"
                        if (a.tipoAdjunto === 'link' || a.pathAdjunto.startsWith('http') || a.pathAdjunto.startsWith('www')) {
                            let urlExterna = a.pathAdjunto;
                            if (!urlExterna.match(/^https?:\/\//)) {
                                urlExterna = 'https://' + urlExterna;
                            }
                            urlVisor = urlExterna;
                            icon = 'fa-link text-primary'; // Cambiar icono a link y color
                        }

                        lista.innerHTML += `
                            <a href="${urlVisor}" target="_blank" class="list-group-item list-group-item-action d-flex align-items-center">
                                <div class="me-3 fs-4"><i class="fas ${icon}"></i></div>
                                <div class="text-truncate">
                                    <div class="fw-bold text-dark">${nombreMostrar}</div>
                                    <small class="text-muted">Clic para abrir</small>
                                </div>
                            </a>`;
                    });
                } else {
                    msg.style.display = 'block';
                }
            })
            .catch(e => lista.innerHTML = '<div class="text-danger p-3 text-center">Error al cargar.</div>');
    }
</script>