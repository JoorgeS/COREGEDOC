<?php
// views/pages/editar_minuta.php
// Implementa la lógica de roles de la minuta.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../class/class.conectorDB.php';

// --- 1. OBTENER DATOS BÁSICOS ---
$idMinuta = (int)($_GET['id'] ?? 0);
$idUsuarioLogueado = (int)($_SESSION['idUsuario'] ?? 0);
$idTipoUsuario = (int)($_SESSION['tipoUsuario_id'] ?? 0);

if ($idMinuta === 0 || $idUsuarioLogueado === 0) {
    echo "<div class='alert alert-danger'>Error: No se pudo cargar la minuta o la sesión es inválida.</div>";
    return;
}

// --- 2. CARGAR DATOS DE LA MINUTA ---
try {
    $db = new conectorDB();
    $pdo = $db->getDatabase();

    // Cargar datos principales de la minuta
    $stmtMinuta = $pdo->prepare("SELECT * FROM t_minuta WHERE idMinuta = :idMinuta");
    $stmtMinuta->execute([':idMinuta' => $idMinuta]);
    $minuta = $stmtMinuta->fetch(PDO::FETCH_ASSOC);

    if (!$minuta) {
        echo "<div class='alert alert-danger'>Error: Minuta no encontrada.</div>";
        return;
    }

    // Carga del primer tema (asumiendo que los temas son arrays en tu app real)
    $stmtTema = $pdo->prepare("SELECT * FROM t_tema WHERE t_minuta_idMinuta = :idMinuta LIMIT 1");
    $stmtTema->execute([':idMinuta' => $idMinuta]);
    $tema = $stmtTema->fetch(PDO::FETCH_ASSOC);

    $nombreTemas = $tema['nombreTema'] ?? 'Temas de ejemplo...';
    $objetivos = $tema['objetivo'] ?? 'Objetivos de ejemplo...';
    $acuerdos = $tema['compromiso'] ?? 'Acuerdos de ejemplo...'; // Asumo que compromiso es acuerdo

} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Error al conectar o cargar datos: " . $e->getMessage() . "</div>";
    return;
}

// --- 3.1 CARGAR ASISTENCIA Y MIEMBROS RELEVANTES ---
try {
    // Obtener la lista de miembros relevantes
    $stmtMiembros = $pdo->prepare("SELECT idUsuario, pNombre, sNombre, aPaterno, aMaterno,
                                 TRIM(CONCAT(pNombre, ' ', COALESCE(sNombre, ''), ' ', aPaterno, ' ', aMaterno)) AS nombreCompleto
                                 FROM t_usuario WHERE tipoUsuario_id IN (1, 3) ORDER BY aPaterno");
    $stmtMiembros->execute();
    $miembros = $stmtMiembros->fetchAll(PDO::FETCH_ASSOC);

    // Obtener la asistencia ya registrada para esta minuta
    $stmtAsistencia = $pdo->prepare("SELECT t_usuario_idUsuario FROM t_asistencia WHERE t_minuta_idMinuta = :idMinuta");
    $stmtAsistencia->execute([':idMinuta' => $idMinuta]);
    $asistenciaActualIDs = $stmtAsistencia->fetchAll(PDO::FETCH_COLUMN, 0);
    $asistenciaMap = array_flip($asistenciaActualIDs);

    // Combinar la información
    $listaAsistencia = [];
    foreach ($miembros as $miembro) {
        $id = (int)$miembro['idUsuario'];
        $listaAsistencia[] = [
            'idUsuario' => $id,
            'nombreCompleto' => htmlspecialchars($miembro['nombreCompleto']),
            'presente' => isset($asistenciaMap[$id])
        ];
    }
} catch (Exception $e) {
    error_log("Error al cargar asistencia: " . $e->getMessage());
    $listaAsistencia = [];
}

// --- 3. DETERMINAR ROL Y PERMISOS ---
$esSecretarioTecnico = ($idTipoUsuario === 2); // 2 = Secretario Técnico
$esPresidenteFirmante = false;
$haFirmado = false;
$haEnviadoFeedback = false;
$estadoMinuta = $minuta['estadoMinuta'] ?? 'BORRADOR';

if ($estadoMinuta === 'PENDIENTE' || $estadoMinuta === 'PARCIAL') {
    // Verificar si el usuario logueado es uno de los firmantes requeridos
    $stmtFirma = $pdo->prepare("SELECT estado_firma FROM t_aprobacion_minuta 
                                WHERE t_minuta_idMinuta = :idMinuta 
                                AND t_usuario_idPresidente = :idUsuario");
    $stmtFirma->execute([':idMinuta' => $idMinuta, ':idUsuario' => $idUsuarioLogueado]);
    $estadoFirma = $stmtFirma->fetchColumn();

    if ($estadoFirma !== false) {
        $esPresidenteFirmante = true;
        if ($estadoFirma === 'REQUIERE_REVISION') {
            $haEnviadoFeedback = true;
        }
    }
}

// El rol de ST (editor) tiene prioridad sobre el de Presidente (revisor)
if ($esSecretarioTecnico) {
    $esPresidenteFirmante = false;
}

// Lógica de solo lectura
$esSoloLectura = true;
if ($esSecretarioTecnico && $estadoMinuta !== 'APROBADA') {
    $esSoloLectura = false;
} elseif ($esPresidenteFirmante || $estadoMinuta === 'APROBADA') {
    $esSoloLectura = true;
} else {
    $esSoloLectura = true;
}

$readonlyAttr = $esSoloLectura ? 'readonly' : '';
$pdo = null; // Cerrar conexión
?>

<div class="container-fluid mt-4">

    <nav aria-label="breadcrumb" class="mb-2">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="menu.php?pagina=home">Home</a></li>
            <li class="breadcrumb-item"><a href="menu.php?pagina=minutas_dashboard">Módulo de Minutas</a></li>
            <li class="breadcrumb-item"><a href="<?php echo $linkListado; ?>"><?php echo $textoListado; ?></a></li>
            <li class="breadcrumb-item active" aria-current="page"><?php echo $tituloBreadcrumb; ?></li>
        </ol>
    </nav>


    <h3 class="mb-3">
        <?php echo $esSecretarioTecnico ? 'Editar' : 'Revisar'; ?> Minuta N° <?php echo $idMinuta; ?>
        <span class="badge bg-secondary ms-2"><?php echo htmlspecialchars($estadoMinuta); ?></span>
    </h3>

    <form id="form-crear-minuta">
        <input type="hidden" id="idMinuta" name="idMinuta" value="<?php echo $idMinuta; ?>">

        <div class="card shadow-sm mb-4">
            <div class="card-header">
                <h5 class="mb-0">Detalles de la Minuta</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="minutaTemas" class="form-label">Nombre(s) del Tema</label>
                    <textarea class="form-control" id="minutaTemas" name="temas[0][nombre]" rows="3" <?php echo $readonlyAttr; ?>><?php echo htmlspecialchars($nombreTemas); ?></textarea>
                </div>
                <div class="mb-3">
                    <label for="minutaObjetivos" class="form-label">Objetivo(s)</label>
                    <textarea class="form-control" id="minutaObjetivos" name="temas[0][objetivo]" rows="3" <?php echo $readonlyAttr; ?>><?php echo htmlspecialchars($objetivos); ?></textarea>
                </div>
                <div class="mb-3">
                    <label for="minutaAcuerdos" class="form-label">Acuerdos (Compromisos)</label>
                    <textarea class="form-control" id="minutaAcuerdos" name="temas[0][acuerdo]" rows="5" <?php echo $readonlyAttr; ?>><?php echo htmlspecialchars($acuerdos); ?></textarea>
                </div>
            </div>
        </div>

        <input type="hidden" id="asistenciaJson" name="asistencia" value="[]">
        <div class="card shadow-sm mb-4">
            <div class="card-header">
                <h5 class="mb-0">Gestión de Asistencia</h5>
            </div>
            <div class="card-body">
                <?php if (!$esSoloLectura) : ?>
                    <p class="text-info"><i class="fas fa-edit"></i> Marque/desmarque los usuarios **presentes**. Se respetará el registro original de fecha y el origen de los autogestionados.</p>
                <?php endif; ?>

                <div class="table-responsive">
                    <table class="table table-hover table-sm">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Nombre del Miembro</th>
                                <th class="text-center">Presente</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if (empty($listaAsistencia)) {
                                echo '<tr><td colspan="3" class="text-center text-danger">No se pudo cargar la lista de miembros.</td></tr>';
                            }
                            $i = 1;
                            foreach ($listaAsistencia as $miembro) :
                                $checked = $miembro['presente'] ? 'checked' : '';
                                $disabled = $esSoloLectura ? 'disabled' : '';
                            ?>
                                <tr>
                                    <td><?php echo $i++; ?></td>
                                    <td><?php echo $miembro['nombreCompleto']; ?></td>
                                    <td class="text-center">
                                        <input class="form-check-input asistencia-checkbox" type="checkbox" value="<?php echo $miembro['idUsuario']; ?>" id="asistencia_<?php echo $miembro['idUsuario']; ?>" <?php echo $checked; ?> <?php echo $disabled; ?>>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header">
                <h5 class="mb-0">Acciones</h5>
            </div>
            <div class="card-body text-end">
                <?php if ($esSecretarioTecnico && $estadoMinuta !== 'APROBADA') : ?>
                    <button type="button" class="btn btn-secondary me-2" id="btn-guardar-borrador">
                        <i class="fas fa-save"></i> Guardar Borrador
                    </button>

                    <button type="button" class="btn btn-danger" id="btn-enviar-aprobacion">
                        <i class="fas fa-paper-plane"></i> Enviar para Aprobación
                    </button>

                <?php elseif ($esPresidenteFirmante) : ?>
                    <?php if ($haFirmado) : ?>
                        <div class="alert alert-success text-center">
                            <i class="fas fa-check-circle"></i> Usted ya ha registrado su firma para esta versión de la minuta.
                        </div>
                    <?php elseif ($haEnviadoFeedback) : ?>
                        <div class="alert alert-warning text-center">
                            <i class="fas fa-clock"></i> Usted envió feedback. La minuta está en espera de revisión por el Secretario Técnico.
                        </div>
                    <?php else : // Aún no ha hecho nada, es su turno 
                    ?>
                        <div class="form-check form-switch text-start mb-3">
                            <input class="form-check-input" type="checkbox" role="switch" id="checkFeedback">
                            <label class="form-check-label" for="checkFeedback"><b>Añadir Feedback / Observaciones</b> (Marque esta casilla si NO va a firmar)</label>
                        </div>

                        <div class="mb-3" id="cajaFeedbackContenedor" style="display: none;">
                            <label for="cajaFeedbackTexto" class="form-label text-start d-block">Indique sus observaciones (requerido):</label>
                            <textarea class="form-control" id="cajaFeedbackTexto" rows="4" placeholder="Escriba aquí sus correcciones o comentarios..."></textarea>
                        </div>

                        <button type="button" class="btn btn-success btn-lg" id="btn-accion-presidente">
                            <i class="fas fa-check"></i> Firmar Minuta
                        </button>
                    <?php endif; ?>

                <?php else : ?>
                    <p class="text-muted text-center">
                        <i class="fas fa-eye"></i> Minuta en modo de solo lectura.
                    </p>
                <?php endif; ?>

            </div>
        </div>
    </form>
</div>
<div class="modal fade" id="modalConfirmarAsistencia" tabindex="-1" aria-labelledby="modalConfirmarAsistenciaLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalConfirmarAsistenciaLabel">Verificar Asistencia Antes de Enviar</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p>Por favor, revisa la lista de asistencia actual. Esta es la asistencia que se registrará y enviará.</p>
                <p>Si la lista es correcta, presiona "Confirmar y Enviar". Si necesitas hacer ajustes, presiona "Cancelar" y edita la asistencia usando el botón "Guardar Borrador".</p>

                <div class="mt-3">
                    <h6><i class="fas fa-users"></i> Asistencia Actual de la Reunión</h6>
                    <hr>
                    <div id="asistenciaPreviewList" style="max-height: 400px; overflow-y: auto;">
                        <div class="text-center">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Cargando...</span>
                            </div>
                            <p>Cargando asistencia...</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times"></i> Cancelar</button>
                <button type="button" class="btn btn-success" id="btnConfirmarEnvioDefinitivo">
                    <i class="fas fa-check"></i> Confirmar y Enviar
                </button>
            </div>
        </div>
    </div>
</div>

<script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script src="../../public/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script>
    // --- (INICIO DEL SCRIPT) ---
    // NO USAMOS DOMContentLoaded porque esta página se carga dinámicamente

    const checkFeedback = document.getElementById('checkFeedback');
    const feedbackBox = document.getElementById('cajaFeedbackContenedor');
    const btnAccion = document.getElementById('btn-accion-presidente');
    const feedbackTexto = document.getElementById('cajaFeedbackTexto');
    const idMinuta = document.getElementById('idMinuta').value;
    const btnGuardarBorrador = document.getElementById('btn-guardar-borrador');
    const formMinuta = document.getElementById('form-crear-minuta');

    // ==========================================================
    // ==========================================================
    // --- 🚀 INICIO: LÓGICA DEL PANEL DE VOTACIONES (ST) ---
    // ==========================================================
    (function() {
        // Solo ejecuta este script si el panel del ST existe en la página
        const formCrearVotacion = document.getElementById('form-crear-votacion');
        if (!formCrearVotacion) {
            // No es el ST o la minuta está aprobada, no hacer nada.
            return;
        }

        // --- 1. Constantes y Elementos ---
        const idMinutaActual = document.getElementById('votacion_idMinuta').value;
        const idReunionActual = document.getElementById('votacion_idReunion').value;
        const idComisionActual = document.getElementById('votacion_idComision').value;
        // CORRECCIÓN: Ruta con ../../
        const controllerVotacionURL = '../../controllers/gestionar_votacion_minuta.php';

        const listaContainer = document.getElementById('panel-votaciones-lista');
        const inputNombreVotacion = document.getElementById('nombreVotacion');

        // --- 2. Función para Cargar la Lista de Votaciones ---
        async function cargarVotaciones() {
            listaContainer.innerHTML = `<div class="text-center p-3 text-muted">
            <div class="spinner-border spinner-border-sm" role="status"></div>
            <span class="ms-2">Actualizando lista...</span>
            </div>`;

            try {
                // CORRECCIÓN: Añadido { credentials: 'same-origin' }
                const response = await fetch(`${controllerVotacionURL}?action=list&idMinuta=${idMinutaActual}`, {
                    credentials: 'same-origin'
                });

                if (!response.ok) throw new Error('Error de red al listar votaciones.');

                // CORRECCIÓN: Manejo de JSON vacío
                const text = await response.text();
                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    console.error("Respuesta inválida del servidor (gestionar_votacion_minuta.php):", text);
                    throw new Error("El servidor devolvió una respuesta inválida. Revisa la consola.");
                }

                if (data.status !== 'success') throw new Error(data.message);

                // Limpiar y renderizar
                listaContainer.innerHTML = '';
                if (data.data.length === 0) {
                    listaContainer.innerHTML = '<div class="alert alert-light text-center">Aún no se han creado votaciones para esta minuta.</div>';
                    return;
                }

                data.data.forEach(votacion => {
                    listaContainer.appendChild(renderVotacionItem(votacion));
                });

            } catch (error) {
                listaContainer.innerHTML = `<div class="alert alert-danger">${error.message}</div>`;
            }
        }

        // --- 3. Función para Renderizar UN item de la lista ---
        function renderVotacionItem(votacion) {
            const div = document.createElement('div');
            div.className = 'list-group-item d-flex justify-content-between align-items-center';
            const habilitada = parseInt(votacion.habilitada, 10) === 1;

            let badgeClass = habilitada ? 'bg-success' : 'bg-secondary';
            let badgeIcon = habilitada ? 'fa-play-circle' : 'fa-stop-circle';
            let badgeText = habilitada ? 'Abierta' : 'Cerrada';

            let btnClass = habilitada ? 'btn-danger' : 'btn-success';
            let btnIcon = habilitada ? 'fa-stop' : 'fa-play';
            let btnText = habilitada ? 'Cerrar' : 'Habilitar';
            let nuevoEstado = habilitada ? 0 : 1;

            div.innerHTML = `
            <div>
                <span class="badge ${badgeClass} me-2"><i class="fas ${badgeIcon} me-1"></i> ${badgeText}</span>
                <strong>${votacion.nombreVotacion}</strong>
                <small class="text-muted d-block">Comisión: ${votacion.nombreComision || 'No especificada'}</small>
            </div>
            <div class="btn-group" role="group">
                <button type="button" class="btn ${btnClass} btn-sm btn-cambiar-estado" data-id="${votacion.idVotacion}" data-nuevo-estado="${nuevoEstado}">
                    <i class="fas ${btnIcon} me-1"></i> ${btnText}
                </button>
            </div>`;
            return div;
        }

        // --- 4. Event Listener para CREAR Votación ---
        formCrearVotacion.addEventListener('submit', async (e) => {
            e.preventDefault();
            const nombre = inputNombreVotacion.value.trim();
            if (nombre === '') {
                Swal.fire('Error', 'Debe ingresar un nombre para la votación.', 'error');
                return;
            }

            const btnSubmit = formCrearVotacion.querySelector('button[type="submit"]');
            btnSubmit.disabled = true;
            btnSubmit.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Creando...';

            const formData = new FormData();
            formData.append('action', 'create');
            formData.append('nombreVotacion', nombre);
            formData.append('idMinuta', idMinutaActual);
            formData.append('idReunion', idReunionActual);
            formData.append('idComision', idComisionActual);

            try {
                // CORRECCIÓN: Añadido { credentials: 'same-origin' }
                const response = await fetch(controllerVotacionURL, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                });

                const data = await response.json();
                if (data.status !== 'success') throw new Error(data.message);

                Swal.fire('¡Éxito!', 'Votación creada correctamente.', 'success');
                inputNombreVotacion.value = ''; // Limpiar input
                cargarVotaciones(); // Recargar la lista

            } catch (error) {
                Swal.fire('Error', error.message, 'error');
            } finally {
                btnSubmit.disabled = false;
                btnSubmit.innerHTML = '<i class="fas fa-plus me-2"></i>Crear Votación';
            }
        });

        // --- 5. Event Listener para Habilitar/Cerrar ---
        listaContainer.addEventListener('click', async (e) => {
            const boton = e.target.closest('.btn-cambiar-estado');
            if (!boton) return;

            const idVotacion = boton.dataset.id;
            const nuevoEstado = boton.dataset.nuevoEstado;
            const accionTexto = nuevoEstado === '1' ? 'Habilitar' : 'Cerrar';

            const result = await Swal.fire({
                title: `¿Seguro que desea ${accionTexto.toLowerCase()} esta votación?`,
                text: (nuevoEstado === '1') ? 'Los consejeros podrán verla y votar.' : 'Nadie podrá votar y se cerrará la sala.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: `Sí, ${accionTexto}`,
                confirmButtonColor: (nuevoEstado === '1') ? '#198754' : '#dc3545',
                cancelButtonText: 'Cancelar'
            });

            if (result.isConfirmed) {
                boton.disabled = true;
                boton.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

                const formData = new FormData();
                formData.append('action', 'change_status');
                formData.append('idVotacion', idVotacion);
                formData.append('nuevoEstado', nuevoEstado);

                try {
                    // CORRECCIÓN: Añadido { credentials: 'same-origin' }
                    const response = await fetch(controllerVotacionURL, {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin'
                    });

                    const data = await response.json();
                    if (data.status !== 'success') throw new Error(data.message);

                    Swal.fire('¡Éxito!', `Votación ${accionTexto.toLowerCase()}da.`, 'success');
                    cargarVotaciones();

                } catch (error) {
                    Swal.fire('Error', error.message, 'error');
                    cargarVotaciones();
                }
            }
        });

        // --- 6. Carga Inicial ---
        cargarVotaciones();

    })();
    // ==========================================================
    // --- 🚀 FIN: LÓGICA DEL PANEL DE VOTACIONES (ST) ---
    // ==========================================================

    // --- Variables del Modal ---
    const modalConfirmarElement = document.getElementById('modalConfirmarAsistencia');

    // Validamos que el JS de Bootstrap se haya cargado
    if (typeof bootstrap !== 'undefined' && modalConfirmarElement) {

        const modalConfirmar = new bootstrap.Modal(modalConfirmarElement);
        const btnAbrirModalAprobacion = document.getElementById('btn-enviar-aprobacion'); // El botón ROJO
        const btnConfirmarEnvioDefinitivo = document.getElementById('btnConfirmarEnvioDefinitivo'); // El botón VERDE del modal
        const asistenciaPreviewList = document.getElementById('asistenciaPreviewList');

        // 1. Lógica del botón ROJO ("Enviar para Aprobación")
        // Esto ahora ABRE EL MODAL
        if (btnAbrirModalAprobacion) {
            btnAbrirModalAprobacion.addEventListener('click', async (e) => {
                e.preventDefault();

                // Mostrar estado de carga en el modal
                asistenciaPreviewList.innerHTML = `<div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                    <p>Cargando asistencia guardada...</p>
                </div>`;

                // Abrir el modal
                modalConfirmar.show();

                // Llamar al nuevo controlador (Paso 2) para obtener la asistencia
                try {
                    // Usamos la ruta relativa correcta (igual que la de guardar_minuta_completa.php)
                    const response = await fetch(`../controllers/obtener_preview_asistencia.php?idMinuta=${idMinuta}`);
                    if (!response.ok) {
                        const errorText = await response.text();
                        throw new Error(`Error de red al cargar la asistencia: ${response.status} ${errorText}`);
                    }
                    const data = await response.json();

                    if (data.status === 'success') {
                        // Construir la lista HTML
                        let html = '<ul class="list-group">';
                        if (data.asistencia.length === 0) {
                            html += '<li class="list-group-item text-muted">No hay miembros de comisión (Tipo 1 o 3) para listar.</li>';
                        }

                        data.asistencia.forEach(miembro => {
                            if (miembro.presente) {
                                html += `<li class="list-group-item d-flex justify-content-between align-items-center">
                                    ${miembro.nombreCompleto}
                                    <span class="badge bg-success rounded-pill"><i class="fas fa-check"></i> Presente</span>
                                </li>`;
                            } else {
                                html += `<li class="list-group-item d-flex justify-content-between align-items-center text-muted">
                                    ${miembro.nombreCompleto}
                                    <span class="badge bg-secondary rounded-pill"><i class="fas fa-times"></i> Ausente</span>
                                </li>`;
                            }
                        });
                        html += '</ul>';
                        asistenciaPreviewList.innerHTML = html;
                    } else {
                        throw new Error(data.message || 'Error al cargar los datos.');
                    }

                } catch (error) {
                    asistenciaPreviewList.innerHTML = `<div class="alert alert-danger"><b>Error:</b> ${error.message}<br><small>Si la asistencia en pantalla es incorrecta, cierre esta ventana, edite la asistencia y presione <strong>Guardar Borrador</strong> antes de intentar enviar de nuevo.</small></div>`;
                }
            });
        }

        // 2. Lógica del botón VERDE del Modal ("Confirmar y Enviar")
        // Esto es lo que AHORA llama a 'enviar_aprobacion.php'
        if (btnConfirmarEnvioDefinitivo) {
            btnConfirmarEnvioDefinitivo.addEventListener('click', async (e) => {
                e.preventDefault();

                // Mostrar estado de carga en el botón
                btnConfirmarEnvioDefinitivo.disabled = true;
                btnConfirmarEnvioDefinitivo.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Enviando...';

                try {
                    // Esta es la lógica que faltaba 
                    const response = await fetch('../controllers/enviar_aprobacion.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        // El idMinuta ya lo teníamos definido al inicio del script
                        body: JSON.stringify({
                            idMinuta: idMinuta
                        })
                    });

                    const data = await response.json();

                    if (!response.ok || data.status === 'error') {
                        // Si falla el envío, mostramos el error
                        throw new Error(data.message || 'Ocurrió un error inesperado al enviar.');
                    }

                    // Si todo sale bien
                    Swal.fire({
                        title: '¡Enviada!',
                        text: data.message || 'Minuta enviada para aprobación.',
                        icon: 'success',
                        allowOutsideClick: false
                    }).then(() => {
                        // Redirigir al listado de minutas
                        window.location.href = 'index.php?page=minutas_dashboard';
                    });

                } catch (error) {
                    Swal.fire('Error de Envío', error.message, 'error');
                } finally {
                    // Ocultar el modal y reactivar el botón
                    modalConfirmar.hide();
                    btnConfirmarEnvioDefinitivo.disabled = false;
                    btnConfirmarEnvioDefinitivo.innerHTML = '<i class="fas fa-check"></i> Confirmar y Enviar';
                }
            });
        }

    } else {
        console.error("Error: Bootstrap JS no está cargado o el elemento #modalConfirmarAsistencia no se encontró.");
    }


    // --- Lógica de Feedback del Presidente (Sin cambios) ---
    if (checkFeedback) {
        checkFeedback.addEventListener('change', function() {
            if (this.checked) {
                feedbackBox.style.display = 'block';
                btnAccion.classList.remove('btn-success');
                btnAccion.classList.add('btn-warning');
                btnAccion.innerHTML = '<i class="fas fa-comment-dots"></i> Enviar Feedback';
            } else {
                feedbackBox.style.display = 'none';
                btnAccion.classList.remove('btn-warning');
                btnAccion.classList.add('btn-success');
                btnAccion.innerHTML = '<i class="fas fa-check"></i> Firmar Minuta';
            }
        });
    }

    if (btnAccion) {
        btnAccion.addEventListener('click', function() {
            if (checkFeedback.checked) {
                enviarFeedbackDesdeEditor();
            } else {
                firmarMinutaDesdeEditor();
            }
        });
    }

    function firmarMinutaDesdeEditor() {
        /* ... (Tu lógica de firma aquí, sin cambios) ... */
    }

    function enviarFeedbackDesdeEditor() {
        /* ... (Tu lógica de feedback aquí, sin cambios) ... */
    }


    // --- LÓGICA AJAX para 'Guardar Borrador' (Sin cambios) ---
    if (formMinuta && btnGuardarBorrador) {
        btnGuardarBorrador.addEventListener('click', function(e) {
            e.preventDefault();

            Swal.fire({
                title: 'Guardando Borrador... 💾',
                text: 'Por favor, espere mientras se guardan los datos, incluyendo la asistencia.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();

                    const asistenciaIDs = [];
                    document.querySelectorAll('.asistencia-checkbox:checked').forEach(checkbox => {
                        asistenciaIDs.push(checkbox.value);
                    });

                    document.getElementById('asistenciaJson').value = JSON.stringify(asistenciaIDs);
                    const formData = new FormData(formMinuta);

                    fetch('../controllers/guardar_minuta_completa.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => {
                            return response.json().then(data => {
                                if (!response.ok || data.status === 'error') {
                                    let message = data.message || 'Error de red o desconocido.';
                                    if (data.debug) {
                                        message += ` (Debug: ID Recibido: ${data.debug.idMinuta_recibido}, Keys: ${data.debug.post_keys_received.join(',')})`;
                                    }
                                    throw new Error(message);
                                }
                                return data;
                            });
                        })
                        .then(data => {
                            Swal.fire('¡Guardado! ✅', data.message, 'success');
                            // REQUERIMIENTO 3: Intentar reanudar el polling después de guardar exitosamente.
                            iniciarPollingCondicional();
                        })
                        .catch(error => {
                            Swal.fire('Error al Guardar ❌', error.message, 'error');
                        });
                }
            });
        });
    }
</script>