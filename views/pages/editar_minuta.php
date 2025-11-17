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
    // ==================================================================
    // --- VARIABLES GLOBALES JAVASCRIPT ---
    // ==================================================================
    let contadorTemas = 0;
    const contenedorTemasGlobal = document.getElementById("contenedorTemas");
    const idMinutaGlobal = <?php echo json_encode($idMinutaActual); ?>;
    const ID_REUNION_GLOBAL = <?php echo json_encode($idReunionActual); ?>;
    const ID_SECRETARIO_LOGUEADO = <?php echo json_encode($_SESSION['idUsuario'] ?? 0); ?>;
    let bsModalValidarAsistencia = null;
    const ESTADO_MINUTA_ACTUAL = <?php echo json_encode($estadoMinuta); ?>;
    const DATOS_TEMAS_CARGADOS = <?php echo json_encode($temas_de_la_minuta ?? []); ?>;
    let ASISTENCIA_GUARDADA_IDS = <?php echo json_encode($asistencia_guardada_ids ?? []); ?>; // Se actualiza con cada fetch
    const HORA_INICIO_REUNION = "<?php echo htmlspecialchars(date('H:i:s', strtotime($minutaData['horaMinuta'] ?? 'now'))); ?>";
    const ES_ST_EDITABLE = <?php echo $esSoloLectura ? 'false' : 'true'; ?>;

    // === Lógica de tiempo ===
    const LIMITE_MINUTOS_AUTOGESTION = 30;
    const INTERVALO_ASISTENCIA = 1000;
    const INTERVALO_VOTACIONES = 1000; // 3 segundos para votaciones

    // === NUEVAS VARIABLES GLOBALES ===
    let intervalAsistenciaID = null;
    let asistenciaModificando = false;
    let REGLAS_FEEDBACK = null;

    // === ⚡ INICIO CORRECCIÓN (Variables que faltaban) ===
    let intervalListaVotacionID = null; // Para el polling de la lista
    let intervalResultadosID = null; // Para el polling de resultados
    let cacheVotacionesList = ""; // Caché para la LISTA
    let cacheResultados = ""; // Caché para los RESULTADOS
    // === ⚡ FIN CORRECCIÓN ===

    // Elementos de UI
    const formSubirArchivo = document.getElementById('formSubirArchivo');
    const inputArchivo = document.getElementById('inputArchivo');
    const formAgregarLink = document.getElementById('formAgregarLink');
    const inputUrlLink = document.getElementById('inputUrlLink');
    const fileStatus = document.getElementById('file-upload-status');


    // ==============================================================================
    // === 2. FUNCIONES DE POLLING (DEBEN IR AQUÍ PARA SER GLOBALES) ===
    // ==============================================================================

    /**
     * Detiene el polling de asistencia.
     */
    function detenerPollingAsistencia() {
        if (intervalAsistenciaID !== null) {
            clearInterval(intervalAsistenciaID);
            intervalAsistenciaID = null;
            asistenciaModificando = true;
            console.log('Polling de asistencia DETENIDO por acción del ST/Guardado manual.');
        }
    }


    /**
     * Inicia o reanuda el polling condicionalmente. (Usando la lógica de fecha corregida)
     */
    function iniciarPollingCondicional() {
        const now = new Date();

        const HORA_INICIO_REUNION_ISO = HORA_INICIO_REUNION.replace(' ', 'T');
        const [h, m, s] = HORA_INICIO_REUNION_ISO.split(':').map(part => parseInt(part, 10));

        const horaInicioHoy = new Date(now.getFullYear(), now.getMonth(), now.getDate(), h, m, s);

        if (isNaN(horaInicioHoy.getTime())) {
            console.error('Error al parsear HORA_INICIO_REUNION. Polling no iniciado.');
            return;
        }

        const horaLimiteAutogestion = new Date(horaInicioHoy.getTime() + LIMITE_MINUTOS_AUTOGESTION * 60 * 1000);
        const haPasadoLimite = now.getTime() > horaLimiteAutogestion.getTime();

        if (!ES_ST_EDITABLE || haPasadoLimite || asistenciaModificando) {
            let causa = !ES_ST_EDITABLE ?
                'Usuario no es ST' :
                haPasadoLimite ?
                `Límite de ${LIMITE_MINUTOS_AUTOGESTION} minutos excedido.` :
                'Modificación manual activa.';

            console.log(`Polling no iniciado/reanudado. Causa: ${causa}.`);
            return;
        }

        if (intervalAsistenciaID === null) {
            console.log('Asistencia: Auto-refresh iniciado (ST, < 30 min)');

            intervalAsistenciaID = setInterval(() => {
                const asistenciaTabButton = document.getElementById('asistencia-tab');
                if (asistenciaTabButton && asistenciaTabButton.classList.contains('active')) {
                    cargarTablaAsistencia(false);
                }
            }, INTERVALO_ASISTENCIA);
        }
    }

    function escapeHTML(str) {
        if (!str) return '';
        return String(str).replace(/[&<>\"']/g, function(m) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '\"': '&quot;',
                "\'": '&#39;'
            } [m];
        });
    }


    // ==================================================================
    // --- 🚀 SECCIÓN DE VOTACIONES (UNIFICADA Y CORREGIDA) ---
    // ==================================================================

    // (Esta variable ya no se usa, pero la dejamos por si la usas en otro lado)
    let exposedCargarVotaciones;

    /**
     * ⚡ NUEVA FUNCIÓN (traída de editar_minuta.php)
     * Renderiza un solo item de control de votación.
     */
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

        // Aseguramos que el nombre de la votación y comisión existan
        const nombreVotacion = votacion.nombreVotacion || 'Votación sin nombre';
        const nombreComision = votacion.nombreComision || 'No especificada';

        div.innerHTML = `
            <div>
                <span class="badge ${badgeClass} me-2"><i class="fas ${badgeIcon} me-1"></i> ${badgeText}</span>
                <strong>${escapeHTML(nombreVotacion)}</strong>
                <small class="text-muted d-block">Comisión: ${escapeHTML(nombreComision)}</small>
            </div>
            <div class="btn-group" role="group">
                <button type="button" class="btn ${btnClass} btn-sm btn-cambiar-estado" data-id="${votacion.idVotacion}" data-nuevo-estado="${nuevoEstado}">
                    <i class="fas ${btnIcon} me-1"></i> ${btnText}
                </button>
            </div>`;
        return div;
    }


    /**
     * ⚡ FUNCIÓN CORREGIDA/IMPLEMENTADA
     * Carga la LISTA de votaciones (los controles)
     */
    async function cargarListaDeVotaciones(esCargaInicial = false, callback = null) {
        const container = document.getElementById('panel-votaciones-lista');
        if (!container) return;

        if (esCargaInicial) {
            container.innerHTML = '<p class="text-center"><i class="fas fa-spinner fa-spin"></i> Cargando lista de votaciones...</p>';
        }

        try {
            // ⚡ CORRECCIÓN: Se usa la ruta correcta (de tu editar_minuta.php) y se añade 'credentials'
            const response = await fetch(`../../controllers/gestionar_votacion_minuta.php?action=list&idMinuta=${idMinutaGlobal}`, {
                method: 'GET',
                cache: 'no-store',
                credentials: 'same-origin' // ⚡ CORRECCIÓN DE SESIÓN
            });

            if (!response.ok) throw new Error('Error de red al listar votaciones.');

            const text = await response.text();
            if (text === cacheVotacionesList) {
                if (callback) callback(false); // No hubo cambios
                return;
            }
            cacheVotacionesList = text;
            const data = JSON.parse(text);

            if (data.status !== 'success') throw new Error(data.message);

            container.innerHTML = ''; // Limpiar
            if (data.data && data.data.length > 0) {
                const listGroup = document.createElement('div');
                listGroup.className = 'list-group';
                data.data.forEach(votacion => {
                    listGroup.appendChild(renderVotacionItem(votacion));
                });
                container.appendChild(listGroup);
            } else {
                container.innerHTML = '<p class="text-muted text-center">No se han creado votaciones para esta minuta.</p>';
            }

            if (callback) callback(true); // Hubo cambios

        } catch (error) {
            console.error('Error en cargarListaDeVotaciones:', error);
            if (esCargaInicial) {
                container.innerHTML = `<p class="text-danger text-center"><strong>Error:</strong> ${error.message}</p>`;
            }
            if (callback) callback(false);
        }
    }


    /**
     * ⚡ FUNCIÓN CORREGIDA
     * Carga los RESULTADOS de las votaciones (el panel de abajo)
     */
    async function cargarResultadosVotacion(esCargaInicial = false, callback = null) {
        const container = document.getElementById('panel-resultados-en-vivo');
        if (!container) return;

        if (esCargaInicial) {
            container.innerHTML = '<p class="text-center" id="votacion-placeholder"><i class="fas fa-spinner fa-spin"></i> Cargando resultados...</p>';
        }

        try {
            // ⚡ CORRECCIÓN: Se añade 'credentials'
            const response = await fetch(`/corevota/controllers/obtener_resultados_votacion.php?idMinuta=${encodeURIComponent(idMinutaGlobal)}`, {
                method: 'GET',
                cache: 'no-store',
                credentials: 'same-origin' // ⚡ CORRECCIÓN DE SESIÓN
            });

            if (!response.ok) throw new Error('Error de red al obtener resultados.');

            const text = await response.text();

            if (text === cacheResultados) {
                if (callback) callback(false);
                return;
            }
            cacheResultados = text;

            const data = JSON.parse(text);

            if (data.status === 'error') {
                // ⚡ CORRECCIÓN: Este es el error que viste. 'Datos insuficientes'
                // Ahora debería estar solucionado con 'credentials'.
                container.innerHTML = `<p class="text-danger text-center" id="votacion-placeholder"><strong>Error:</strong> ${data.message}</p>`;
                if (callback) callback(false);
                return;
            }

            if (!data.votaciones || data.votaciones.length === 0) {
                container.innerHTML = `<p class="text-muted text-center" id="votacion-placeholder">No hay votaciones activas para esta minuta.</p>`;
                if (callback) callback(false);
                return;
            }

            container.innerHTML = '';

            data.votaciones.forEach(v => {
                const totalVotantes = v.votosSi + v.votosNo + v.votosAbstencion;
                const faltanVotar = v.totalPresentes - totalVotantes;
                const getVoterList = (list) => list.length > 0 ? `<ul class="list-unstyled mb-0 small">${list.map(name => `<li><i class="fas fa-user fa-fw me-1 text-muted"></i>${escapeHTML(name)}</li>`).join('')}</ul>` : '<em class="text-muted small ps-2">Sin votos</em>';

                const votacionHtml = `
                    <div class="card mb-4 shadow-sm votacion-block-ui" data-id-votacion="${v.idVotacion}">
                        <div class="card-header bg-light border-bottom">
                            <h5 class="mb-0 fw-bold">${escapeHTML(v.nombreAcuerdo)}</h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center mb-4">
                                <div class="col-4">
                                    <h3 class="text-success mb-0">${v.votosSi}</h3>
                                    <p class="mb-0 small text-uppercase">A Favor</p>
                                </div>
                                <div class="col-4">
                                    <h3 class="text-danger mb-0">${v.votosNo}</h3>
                                    <p class="mb-0 small text-uppercase">En Contra</p>
                                </div>
                                <div class="col-4">
                                    <h3 class="text-secondary mb-0">${v.votosAbstencion}</h3>
                                    <p class="mb-0 small text-uppercase">Abstención</p>
                                </div>
                            </div>
                            <hr>
                            <div class="row text-center mb-3">
                                <div class="col-6">
                                    <h4 class="text-info mb-0">${v.totalPresentes}</h4>
                                    <p class="mb-0 small">Asistentes Requeridos</p>
                                </div>
                                <div class="col-6">
                                    <h4 class="text-warning mb-0">${Math.max(0, faltanVotar)}</h4>
                                    <p class="mb-0 small">Faltan Votar</p>
                                </div>
                            </div>
                            
                            <h6 class="mt-4 border-bottom pb-1 small text-uppercase text-muted">Detalle de Votantes (ST)</h6>
                            <table class="table table-sm table-bordered" style="font-size: 0.9rem;">
                                <thead>
                                    <tr class="table-light">
                                        <th class="text-success">Sí (${v.votosSi})</th>
                                        <th class="text-danger">No (${v.votosNo})</th>
                                        <th class="text-secondary">Abst. (${v.votosAbstencion})</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td style="vertical-align: top;">${getVoterList(v.votosSi_nombres || [])}</td>
                                        <td style="vertical-align: top;">${getVoterList(v.votosNo_nombres || [])}</td>
                                        <td style="vertical-align: top;">${getVoterList(v.votosAbstencion_nombres || [])}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>`;
                container.innerHTML += votacionHtml;
            });

            if (callback) callback(true);

        } catch (error) {
            if (esCargaInicial) {
                container.innerHTML = `<p class="text-danger text-center" id="votacion-placeholder"><strong>Error:</strong> ${error.message}</p>`;
            }
            console.error('Error al cargar resultados:', error);
            if (callback) callback(false);
        }
    }


    /**
     * ⚡ NUEVA FUNCIÓN (traída de tu `editar_minuta.php` y adaptada)
     * Inicia el polling para la LISTA de votaciones (controles)
     */
    function iniciarPollingListaVotaciones() {
        if (intervalListaVotacionID !== null) return;

        const statusDisplay = document.getElementById('poll-status-display');
        if (!statusDisplay) return;

        console.log('Polling de Lista de Votaciones (Smart) INICIADO.');

        const actualizarTimestamp = (icono, mensaje) => {
            if (!statusDisplay) return;
            const now = new Date();
            const timeString = now.toLocaleTimeString('es-CL', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
            statusDisplay.innerHTML = `<i class="fas ${icono} me-1"></i> ${mensaje} (${timeString})`;
        };

        intervalListaVotacionID = setInterval(() => {
            const votacionTabButton = document.getElementById('votaciones-tab');

            if (votacionTabButton && votacionTabButton.classList.contains('active')) {
                actualizarTimestamp('', '');

                // ⚡ CORRECCIÓN: Ahora esta función SÍ existe
                cargarListaDeVotaciones(false, (cambiosDetectados) => {
                    if (cambiosDetectados) {
                        actualizarTimestamp('fa-check text-success', 'Lista actualizada');
                    } else {
                        actualizarTimestamp('', '');
                    }
                });
            } else {
                if (statusDisplay.innerHTML !== '') statusDisplay.innerHTML = '';
            }
        }, INTERVALO_VOTACIONES);
    }

    /**
     * ⚡ NUEVA FUNCIÓN (traída de tu `editar_minuta.php` y adaptada)
     * Inicia el polling para los RESULTADOS de votación
     */
    function iniciarPollingResultados() {
        if (intervalResultadosID !== null) return;

        const statusDisplay = document.getElementById('poll-resultados-status');
        if (!statusDisplay) return;

        console.log('Polling de Resultados (Smart) INICIADO.');

        const actualizarTimestamp = (icono, mensaje) => {
            if (!statusDisplay) return;
            const now = new Date();
            const timeString = now.toLocaleTimeString('es-CL', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
            statusDisplay.innerHTML = `<i class="fas ${icono} me-1"></i> ${mensaje} (${timeString})`;
        };

        intervalResultadosID = setInterval(() => {
            const votacionTabButton = document.getElementById('votaciones-tab');

            if (votacionTabButton && votacionTabButton.classList.contains('active')) {
                actualizarTimestamp('fa-sync fa-spin text-primary', 'Buscando votos...');

                cargarResultadosVotacion(false, (cambiosDetectados) => {
                    if (cambiosDetectados) {
                        actualizarTimestamp('fa-check text-success', 'Resultados actualizados');
                    } else {
                        actualizarTimestamp('fa-satellite-dish text-muted', 'Sin nuevos votos');
                    }
                });
            } else {
                if (statusDisplay.innerHTML !== '') statusDisplay.innerHTML = '';
            }
        }, INTERVALO_VOTACIONES);
    }
    // ==================================================================
    // --- 🚀 FIN SECCIÓN DE VOTACIONES ---
    // ==================================================================


    // ==================================================================
    // --- EVENTOS PRINCIPALES Y LÓGICA DE TABS ---
    // ==================================================================
    document.addEventListener("DOMContentLoaded", () => {

        // 1. Carga Inicial de datos
        cargarTablaAsistencia(true);
        cargarOPrepararTemas();
        cargarYMostrarAdjuntosExistentes();

        // === ⚡ INICIO CORRECCIÓN VOTACIONES ===
        cargarListaDeVotaciones(true); // Carga la lista de controles
        cargarResultadosVotacion(true); // Carga la tabla de resultados
        // === ⚡ FIN CORRECCIÓN VOTACIONES ===

        const modalElement = document.getElementById('modalValidarAsistencia');
        if (modalElement) {
            bsModalValidarAsistencia = new bootstrap.Modal(modalElement, {
                backdrop: 'static',
                keyboard: false
            });
        } else {
            console.error("CRÍTICO: Elemento modal ValidarAsistencia no encontrado en el DOM.");
        }

        if (ESTADO_MINUTA_ACTUAL !== 'APROBADA') {
            cargarYAplicarFeedback();
        }

        // 2. Implementación de Polling (Auto-refresh)
        iniciarPollingCondicional(); // Asistencia
        iniciarPollingListaVotaciones(); // Votaciones (Lista)
        iniciarPollingResultados(); // Votaciones (Resultados)

        // 4. Lógica de Navegación por Checkbox (Se mantiene)
        $('.navigate-to-tab').on('change', function() {
            const targetTabName = $(this).data('target-tab');
            const targetNavItemId = `#nav-item-${targetTabName.replace('-tab', '')}`;
            const targetNavItem = $(targetNavItemId);
            const targetTabButton = document.getElementById(targetTabName);

            if ($(this).is(':checked')) {
                targetNavItem.removeClass('hidden-tab');
                if (targetTabButton) {
                    const bsTab = new bootstrap.Tab(targetTabButton);
                    bsTab.show();
                }
            } else {
                targetNavItem.addClass('hidden-tab');
                if (targetTabButton && targetTabButton.classList.contains('active')) {
                    const desarrolloTab = document.getElementById('desarrollo-tab');
                    const bsTab = new bootstrap.Tab(desarrolloTab);
                    bsTab.show();
                }
            }
        });

        // 5. Eventos para archivos y enlaces (Se mantienen)
        if (inputArchivo) {
            inputArchivo.addEventListener('change', function(e) {
                if (this.files.length > 0) {
                    formSubirArchivo.dispatchEvent(new Event('submit', {
                        cancelable: true
                    }));
                }
            });
        }
        if (inputUrlLink) {
            inputUrlLink.addEventListener('change', function() {
                const url = this.value.trim();
                if (url !== '' && (url.startsWith('http://') || url.startsWith('https://'))) {
                    formAgregarLink.dispatchEvent(new Event('submit', {
                        cancelable: true
                    }));
                } else if (url !== '') {
                    Swal.fire('Formato Inválido', 'Asegúrese de que el enlace sea una URL completa y válida (ej: https://ejemplo.com).', 'warning');
                }
            });
        }
        document.getElementById('formSubirArchivo').addEventListener('submit', handleSubirArchivo);
        document.getElementById('formAgregarLink').addEventListener('submit', handleAgregarLink);

        // 6. Lógica del Modal de Validación (Confirmación y Modificación)
        const btnModificar = document.getElementById('btnModificarAsistencia');
        if (btnModificar) {
            btnModificar.addEventListener('click', function() {
                detenerPollingAsistencia();
                if (bsModalValidarAsistencia) {
                    bsModalValidarAsistencia.hide();
                }
                const tabButton = document.getElementById('asistencia-tab');
                const bsTab = new bootstrap.Tab(tabButton);
                bsTab.show();
                const tabPane = document.getElementById('asistencia-tab-pane');
                tabPane.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            });
        }

        const btnConfirmar = document.getElementById('btnConfirmarEnviarAsistencia');
        if (btnConfirmar) {
            btnConfirmar.addEventListener('click', function() {
                if (!idMinutaGlobal) {
                    Swal.fire('Error', 'Error de Javascript: No se pudo encontrar el ID de la minuta.', 'error');
                    return;
                }

                const $this = this;
                $this.disabled = true;
                $this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';

                const formData = new FormData();
                formData.append('idMinuta', idMinutaGlobal);

                // ⚡ CORRECCIÓN: Se añade 'credentials'
                fetch('/COREVOTA/controllers/enviar_asistencia_validada.php', {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin' // ⚡ CORRECCIÓN DE SESIÓN
                    })
                    .then(response => response.json())
                    .then(response => {
                        if (response.success || response.status === 'success') {
                            Swal.fire('Éxito', 'Asistencia validada y correo enviado con éxito.', 'success')
                                .then(() => {
                                    window.location.reload();
                                });
                        } else {
                            Swal.fire('Error', 'Error: ' + response.message, 'error');
                        }
                    })
                    .catch(err => {
                        Swal.fire('Error', 'Error de conexión al intentar enviar el correo.', 'error');
                        console.error("Error fetch enviar_asistencia_validada:", err);
                    })
                    .finally(() => {
                        $this.disabled = false;
                        $this.innerHTML = '<i class="fas fa-check"></i> Confirmar y Enviar Correo';
                    });
            });
        }

        // ========================================================
        // ⚡ INICIO: Listeners para Votaciones (traídos de editar_minuta.php)
        // ========================================================
        const formCrearVotacion = document.getElementById('form-crear-votacion');
        const listaContainer = document.getElementById('panel-votaciones-lista');
        const inputNombreVotacion = document.getElementById('nombreVotacion');

        if (formCrearVotacion && listaContainer && inputNombreVotacion) {

            // --- Listener para CREAR Votación ---
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
                formData.append('idMinuta', idMinutaGlobal);
                formData.append('idReunion', ID_REUNION_GLOBAL);

                // Obtener la comisión principal del formulario
                const idComisionActual = document.getElementById('votacion_idComision').value;
                formData.append('idComision', idComisionActual);

                try {
                    // ⚡ CORRECCIÓN: Se añade 'credentials' y ruta correcta
                    const response = await fetch('../../controllers/gestionar_votacion_minuta.php', {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin' // ⚡ CORRECCIÓN DE SESIÓN
                    });

                    const data = await response.json();
                    if (data.status !== 'success') throw new Error(data.message);

                    Swal.fire('¡Éxito!', 'Votación creada correctamente.', 'success');
                    inputNombreVotacion.value = ''; // Limpiar input
                    cargarListaDeVotaciones(true); // Recargar la lista

                } catch (error) {
                    Swal.fire('Error', error.message, 'error');
                } finally {
                    btnSubmit.disabled = false;
                    btnSubmit.innerHTML = '<i class="fas fa-plus me-2"></i>Crear Votación';
                }
            });

            // --- Listener para Habilitar/Cerrar Votación ---
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
                        // ⚡ CORRECCIÓN: Se añade 'credentials' y ruta correcta
                        const response = await fetch('../../controllers/gestionar_votacion_minuta.php', {
                            method: 'POST',
                            body: formData,
                            credentials: 'same-origin' // ⚡ CORRECCIÓN DE SESIÓN
                        });

                        const data = await response.json();
                        if (data.status !== 'success') throw new Error(data.message);

                        Swal.fire('¡Éxito!', `Votación ${accionTexto.toLowerCase()}da.`, 'success');
                        cargarListaDeVotaciones(true); // Recargar la lista

                    } catch (error) {
                        Swal.fire('Error', error.message, 'error');
                        cargarListaDeVotaciones(true); // Recargar igual
                    }
                }
            });
        }
        // ========================================================
        // ⚡ FIN: Listeners para Votaciones
        // ========================================================

    }); // FIN DOMContentLoaded

    // ==================================================================
    // --- SECCIÓN: ASISTENCIA (CORREGIDA CON 'credentials') ---
    // ==================================================================

    function cargarTablaAsistencia(isInitialLoad) {
        const cont = document.getElementById("contenedorTablaAsistenciaEstado");
        const btnRefresh = document.getElementById("btn-refrescar-asistencia");

        if (isInitialLoad) {
            if (btnRefresh) btnRefresh.disabled = true;
            cont.innerHTML = '<p class="text-muted"><i class="fas fa-spinner fa-spin me-2"></i>Cargando lista de consejeros y asistencia...</p>';
        }

        // ⚡ CORRECCIÓN: Se añade 'credentials'
        const fetchConsejeros = fetch("/corevota/controllers/fetch_data.php?action=asistencia_all", {
            method: 'GET',
            credentials: 'same-origin' // ⚡ CORRECCIÓN DE SESIÓN
        }).then(res => res.ok ? res.json() : Promise.reject(new Error('Error fetch_data.php')));

        // ⚡ CORRECCIÓN: Se añade 'credentials'
        const fetchAsistenciaActual = fetch(`/corevota/controllers/obtener_asistencia_actual.php?idMinuta=${idMinutaGlobal}`, {
            method: 'GET',
            credentials: 'same-origin' // ⚡ CORRECCIÓN DE SESIÓN
        }).then(res => res.ok ? res.json() : Promise.reject(new Error('Error obtener_asistencia_actual.php')));

        Promise.all([fetchConsejeros, fetchAsistenciaActual])
            .then(([responseConsejeros, responseAsistencia]) => {
                // ... (El resto de la lógica de renderizado de asistencia es igual) ...
                if (responseConsejeros.status !== 'success' || responseAsistencia.status !== 'success') {
                    throw new Error('No se pudo cargar la información necesaria.');
                }
                const data = responseConsejeros.data;
                ASISTENCIA_GUARDADA_IDS = responseAsistencia.data.map(String);
                const meetingTimeData = responseAsistencia.meeting_time;
                const meetingDateTimeString = `${meetingTimeData.fecha} ${meetingTimeData.hora}`;
                const meetingStartTime = new Date(meetingDateTimeString);
                const currentTime = new Date();
                const diffInMinutes = (currentTime.getTime() - meetingStartTime.getTime()) / (1000 * 60);
                const autoCheckInDisabled = diffInMinutes > 30;
                let timeWarning = '';
                if (autoCheckInDisabled) {
                    timeWarning = '<p class="text-danger fw-bold"><i class="fas fa-clock me-1"></i> ¡Plazo de autogestión de asistencia (30 minutos) ha expirado!</p>';
                    timeWarning += '<p class="text-muted small">El Secretario Técnico puede seguir marcando asistencia manually.</p>';
                } else {
                    const remainingTime = Math.ceil(30 - diffInMinutes);
                    timeWarning = `<p class="text-success fw-bold"><i class="fas fa-hourglass-half me-1"></i> Plazo restante: ${remainingTime} minutos (aprox.)</p>`;
                }
                const baseDisabledAttr = ES_ST_EDITABLE ? '' : 'disabled';
                const baseTitleAttr = ES_ST_EDITABLE ? '' : 'title="Edición bloqueada por el estado de la minuta o su rol."';
                const userDisabledAttr = ES_ST_EDITABLE ? baseDisabledAttr : (autoCheckInDisabled ? 'disabled' : baseDisabledAttr);
                const userTitleAttr = ES_ST_EDITABLE ? baseTitleAttr : (autoCheckInDisabled ? 'title="El plazo de 30 minutos para la autogestión de asistencia ha expirado."' : baseTitleAttr);

                if (data && data.length > 0) {
                    let tabla = timeWarning;
                    tabla += `<table class="table table-sm table-hover" id="tablaAsistenciaEstado"><thead><tr><th style="text-align: left;">Nombre Consejero</th><th style="width: 100px;">Presente</th><th style="width: 100px;">Ausente</th></tr></thead><tbody>`;
                    data.forEach(c => {
                        const userIdString = String(c.idUsuario);
                        const isPresent = ASISTENCIA_GUARDADA_IDS.includes(userIdString);
                        const isAbsent = !isPresent;
                        tabla += `<tr data-userid="${c.idUsuario}">
                            <td style="text-align: left;"><label class="form-check-label w-100" for="present_${userIdString}">${c.nombreCompleto}</label></td>
                            <td><input class="form-check-input asistencia-checkbox present-check" type="checkbox" id="present_${userIdString}" value="${userIdString}" onchange="handleAsistenciaChange('${userIdString}', 'present')" ${isPresent ? 'checked' : ''} ${userDisabledAttr} ${userTitleAttr}></td>
                            <td><input class="form-check-input asistencia-checkbox absent-check default-absent" type="checkbox" id="absent_${userIdString}" onchange="handleAsistenciaChange('${userIdString}', 'absent')" ${isAbsent ? 'checked' : ''} ${userDisabledAttr} ${userTitleAttr}></td>
                            </tr>`;
                    });
                    tabla += `</tbody></table>`;
                    cont.innerHTML = tabla;
                } else {
                    cont.innerHTML = '<p class="text-danger">No hay consejeros para cargar.</p>';
                }
            })
            .catch(err => {
                console.error("Error carga asistencia:", err);
                if (isInitialLoad) {
                    cont.innerHTML = `<p class="text-danger">Error al refrescar asistencia: ${err.message}</p>`;
                }
            })
            .finally(() => {
                if (btnRefresh) btnRefresh.disabled = false;
                if (REGLAS_FEEDBACK) deshabilitarCamposSegunFeedback(REGLAS_FEEDBACK);
            });
    }

    // --- MODIFICADA PARA CONFIRMACIÓN ---
    async function handleAsistenciaChange(userId, changedType) {
        const present = document.getElementById(`present_${userId}`);
        const absent = document.getElementById(`absent_${userId}`);

        // 1. Encontrar el nombre de la persona
        const row = present.closest('tr');
        const label = row.querySelector('label');
        const nombrePersona = label ? label.textContent.trim() : 'esta persona';

        // 2. Determinar la acción (El 'onchange' se dispara DESPUÉS del clic)
        let accionTexto = "";
        if (changedType === 'present') {
            accionTexto = present.checked ? "marcar como PRESENTE" : "marcar como AUSENTE";
        } else { // 'absent'
            accionTexto = absent.checked ? "marcar como AUSENTE" : "marcar como PRESENTE";
        }

        // 3. Mostrar la confirmación
        const result = await Swal.fire({
            title: '¿Confirmar Cambio?',
            text: `¿Está seguro que desea ${accionTexto} a ${nombrePersona}?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#198754',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Sí, confirmar',
            cancelButtonText: 'Cancelar'
        });

        // 4. Si se confirma, aplicar la lógica. Si se cancela, revertir.
        if (result.isConfirmed) {
            // Lógica original para sincronizar las casillas
            if (changedType === 'present') {
                absent.checked = !present.checked;
            } else if (changedType === 'absent') {
                present.checked = !absent.checked;
            }
        } else {
            // REVERTIR EL CLIC
            if (changedType === 'present') {
                present.checked = !present.checked;
            } else if (changedType === 'absent') {
                absent.checked = !absent.checked;
            }
        }
    }

    function recolectarAsistencia() {
        const ids = [];
        const presentes = document.querySelectorAll("#tablaAsistenciaEstado .present-check:checked");
        presentes.forEach(chk => ids.push(chk.value));
        return ids;
    }

    async function guardarAsistencia() {
        // 1. Validar si puede editar
        if (!ES_ST_EDITABLE) {
            Swal.fire('Prohibido', 'No puede guardar la asistencia en este estado.', 'error');
            return;
        }

        // 2. Referencias a elementos del DOM
        const status = document.getElementById('guardarAsistenciaStatus');
        const btn = document.querySelector('#botonesAsistenciaContainer button[onclick="guardarAsistencia()"]');

        // 3. Preparar datos
        const formData = new FormData();
        formData.append('idMinuta', idMinutaGlobal);
        formData.append('asistencia', JSON.stringify(recolectarAsistencia()));

        try {
            // 4. Iniciar proceso: deshabilitar UI
            btn.disabled = true;
            status.textContent = 'Guardando...';
            status.className = 'me-auto small text-muted';

            // 5. Realizar la petición
            const response = await fetch("../../controllers/guardar_asistencia.php", {
                method: "POST",
                body: formData,
                credentials: 'same-origin'
            });

            if (!response.ok) {
                const errorText = await response.text();
                throw new Error(`Error del servidor (${response.status}): ${errorText}`);
            }

            const resp = await response.json();

            // 6. Manejar respuesta de la aplicación
            if (resp.status === "success") {
                status.textContent = '';
                Swal.fire({ // <-- Pop-up de ÉXITO
                    icon: 'success',
                    title: 'Asistencia Actualizada',
                    text: 'Se ha modificado el registro de asistencia con éxito.',
                    timer: 2500,
                    showConfirmButton: false
                });
                cargarTablaAsistencia(true);
            } else {
                throw new Error(resp.message || 'Error desconocido al guardar');
            }

        } catch (err) {
            // 7. Manejo centralizado de errores
            console.error("Error en guardarAsistencia:", err);
            Swal.fire('Error de Conexión', err.message, 'error');
            status.textContent = `⚠️ Error: ${err.message}`;
            status.className = 'me-auto small text-danger';

            setTimeout(() => {
                if (status.textContent.startsWith('⚠️ Error:')) {
                    status.textContent = '';
                }
            }, 5000);

        } finally {
            // 8. Esto se ejecuta SIEMPRE
            btn.disabled = false;
        }
    }
    // ==================================================================
    // --- SECCIÓN: ACCIONES DE MINUTA (CORREGIDA CON 'credentials') ---
    // ==================================================================

    function iniciarValidacionAsistencia() {
        const btn = document.getElementById('btnRevisarAsistencia');
        if (!btn) return;

        if (!validarCamposMinuta()) {
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando cambios...';
        detenerPollingAsistencia();

        guardarBorrador(false, function(guardadoExitoso) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-users-check"></i> Revisar y Validar Asistencia';

            if (guardadoExitoso) {
                if (bsModalValidarAsistencia) {
                    // ⚡ CORRECCIÓN: Se añade 'credentials'
                    fetch(`/COREVOTA/controllers/obtener_preview_asistencia.php?idMinuta=${encodeURIComponent(idMinutaGlobal)}`, {
                            method: 'GET',
                            credentials: 'same-origin' // ⚡ CORRECCIÓN DE SESIÓN
                        })
                        .then(response => response.json())
                        .then(data => {
                            const modalBody = document.querySelector('#contenidoModalAsistencia');
                            if (data.status === 'success' && data.asistencia) {
                                let html = '<table class="table table-sm table-striped table-hover"><thead><tr><th>Nombre</th><th class="text-center">Estado</th></tr></thead><tbody>';
                                data.asistencia.forEach(item => {
                                    html += `<tr>
                                        <td>${item.nombreCompleto}</td>
                                        <td class="text-center">${item.presente ? '<span class="badge bg-success">Presente</span>' : '<span class="badge bg-secondary">Ausente</span>'}</td>
                                        </tr>`;
                                });
                                html += '</tbody></table>';
                                modalBody.innerHTML = html;
                            } else {
                                modalBody.innerHTML = `<p class="text-danger">Error al cargar la asistencia: ${data.message || 'No se pudo cargar la asistencia.'}</p>`;
                            }
                            bsModalValidarAsistencia.show();
                        })
                        .catch(err => {
                            Swal.fire('Error al cargar preview', 'Error de conexión: ' + err.message, 'error');
                            console.error("Error fetch preview asistencia:", err);
                        });
                } else {
                    Swal.fire('Error JS', 'No se pudo instanciar el modal de validación.', 'error');
                }
            } else {
                Swal.fire('Error al Guardar', 'No se pudieron guardar los cambios. El PDF de asistencia no se pudo generar.', 'error');
            }
        });
    }

    function guardarBorrador(guardarYSalir, callback = null) {
        if (!idMinutaGlobal) {
            alert("Error Crítico: No hay ID de Minuta.");
            if (callback) callback(false);
            return;
        }
        if (!ES_ST_EDITABLE) {
            Swal.fire('Prohibido', 'No puede guardar la minuta en este estado.', 'error');
            if (callback) callback(false);
            return;
        }

        const asistenciaIDs = recolectarAsistencia();
        const bloques = document.querySelectorAll("#contenedorTemas .tema-block");
        const temasData = [];

        bloques.forEach(b => {
            const c = b.querySelectorAll(".editable-area");
            temasData.push({
                nombreTema: c[0]?.innerHTML.trim() || "",
                objetivo: c[1]?.innerHTML.trim() || "",
                descAcuerdo: c[2]?.innerHTML.trim() || "",
                compromiso: c[3]?.innerHTML.trim() || "",
                observacion: c[4]?.innerHTML.trim() || "",
                idTema: b.dataset.idTema || null
            });
        });

        const formData = new FormData();
        formData.append('idMinuta', idMinutaGlobal);
        formData.append('asistencia', JSON.stringify(asistenciaIDs));
        formData.append('temas', JSON.stringify(temasData));

        const btnGuardar = document.getElementById('btnGuardarBorrador');
        const btnEnviar = document.getElementById('btnEnviarAprobacion');

        if (btnGuardar) btnGuardar.disabled = true;
        if (btnEnviar) btnEnviar.disabled = true;

        if (!callback && btnGuardar) {
            btnGuardar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
        }

        // ⚡ CORRECCIÓN: Se añade 'credentials'
        fetch("/corevota/controllers/guardar_minuta_completa.php", {
                method: "POST",
                body: formData,
                credentials: 'same-origin' // ⚡ CORRECCIÓN DE SESIÓN
            })
            .then(response => {
                if (response.ok) {
                    return response.json();
                }
                return response.text().then(text => {
                    console.error("Respuesta de error (guardarBorrador):", text);
                    throw new Error("El servidor respondió con un error (ver consola).");
                });
            })
            .then(resp => {
                if (resp.status === "success") {
                    if (callback) {
                        callback(true);
                    } else {
                        Swal.fire('Guardado', 'Borrador guardado con éxito.', 'success');
                        if (guardarYSalir) {
                            window.location.href = 'menu.php?pagina=minutas_listado_general&tab=borradores';
                        }
                    }
                } else {
                    throw new Error(resp.message || 'Error al guardar el borrador.');
                }
            })
            .catch(err => {
                if (callback) {
                    callback(false);
                }
                Swal.fire("Error al Guardar", err.message, "error");
                console.error("Error fetch-guardar borrador:", err);
            })
            .finally(() => {
                if (!callback) {
                    if (btnGuardar) {
                        btnGuardar.disabled = false;
                        btnGuardar.innerHTML = '<i class="fas fa-save"></i> Guardar Borrador';
                    }
                    if (btnEnviar && <?php echo json_encode($puedeEnviar); ?>) {
                        btnEnviar.disabled = false;
                    }
                }
            });
    }


    function confirmarEnvioAprobacion() {
        const idMinuta = idMinutaGlobal;
        const btnGuardar = document.getElementById('btnGuardarBorrador');
        const btnEnviar = document.getElementById('btnEnviarAprobacion');

        if (!ES_ST_EDITABLE) {
            Swal.fire('Prohibido', 'No puede enviar la minuta en este estado.', 'error');
            return;
        }

        Swal.fire({
            title: '¿Enviar Minuta para Aprobación?',
            text: "Esta acción guardará los últimos cambios, notificará por correo a todos los presidentes requeridos e iniciará el proceso de firma. ¿Está seguro?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, Enviar Ahora',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                if (btnGuardar) btnGuardar.disabled = true;
                if (btnEnviar) btnEnviar.disabled = true;

                Swal.fire({
                    title: 'Enviando para Aprobación',
                    text: 'Se está notificando a el o los presidentes. Espere un momento...',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                guardarBorrador(false, function(guardadoExitoso) {
                    if (!guardadoExitoso) {
                        Swal.fire('Error al Guardar', 'No se pudo guardar el borrador antes de enviar. Por favor, intente de nuevo.', 'error');
                        if (btnGuardar) btnGuardar.disabled = false;
                        if (btnEnviar) btnEnviar.disabled = false;
                        if (btnEnviar) btnEnviar.innerHTML = '<i class="fas fa-paper-plane"></i> Enviar para Aprobación';
                        return;
                    }

                    // ⚡ CORRECCIÓN: Se añade 'credentials'
                    fetch('/corevota/controllers/enviar_aprobacion.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                idMinuta: idMinuta
                            }),
                            credentials: 'same-origin' // ⚡ CORRECCIÓN DE SESIÓN
                        })
                        .then(response => {
                            if (response.ok) {
                                return response.json();
                            }
                            return response.text().then(text => {
                                console.error("Respuesta de error (enviar_aprobacion):", text);
                                throw new Error("Error al enviar (ver consola).");
                            });
                        })
                        .then(data => {
                            if (data.status === 'success') {
                                Swal.fire({
                                    title: '¡Enviada!',
                                    text: data.message,
                                    icon: 'success'
                                }).then(() => {
                                    window.location.href = 'menu.php?pagina=minutas_pendientes';
                                });
                            } else {
                                throw new Error(data.message || 'Error desconocido al enviar.');
                            }
                        })
                        .catch(error => {
                            Swal.fire('Error en el Envío', error.message, 'error');
                            if (btnGuardar) btnGuardar.disabled = false;
                            if (btnEnviar) btnEnviar.disabled = false;
                            if (btnEnviar) btnEnviar.innerHTML = '<i class="fas fa-paper-plane"></i> Enviar para Aprobación';
                        });
                });
            }
        });
    }


    // ==================================================================
    // --- SECCIÓN: FEEDBACK / RE-ENVÍO (CORREGIDA CON 'credentials') ---
    // ==================================================================

    async function cargarYAplicarFeedback() {
        try {
            // ⚡ CORRECCIÓN: Se añade 'credentials'
            const response = await fetch(`/corevota/controllers/obtener_feedback_json.php?idMinuta=${idMinutaGlobal}`, {
                method: 'GET',
                credentials: 'same-origin' // ⚡ CORRECCIÓN DE SESIÓN
            });
            if (!response.ok) {
                throw new Error("No se pudo conectar al script de feedback.");
            }

            const data = await response.json();

            if (data.status === 'success' && data.data) {
                REGLAS_FEEDBACK = data.data;

                if (data.textoFeedback) {
                    const container = document.getElementById('feedback-display-container');
                    const textoDiv = document.getElementById('feedback-display-texto');
                    if (container && textoDiv) {
                        textoDiv.textContent = data.textoFeedback;
                        container.style.display = 'block';
                    }
                }
                deshabilitarCamposSegunFeedback(REGLAS_FEEDBACK);
                actualizarBotonesParaFeedback();

            } else if (data.status === 'no_feedback') {
                // No hacer nada
            } else {
                throw new Error(data.message || "Error desconocido al cargar feedback.");
            }
        } catch (error) {
            console.error("Error al cargar feedback:", error);
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'error',
                title: 'No se pudo cargar el estado de feedback.',
                showConfirmButton: false,
                timer: 3000
            });
        }
    }

    function deshabilitarCamposSegunFeedback(reglas) {
        // (Esta función no cambia)
        if (reglas.asistencia === false) {
            const navItem = document.getElementById('nav-item-asistencia');
            if (navItem) navItem.classList.add('opacity-50');
            deshabilitarSeccion('asistencia-tab-pane', 'Asistencia bloqueada (sin feedback)');
            document.querySelector('#botonesAsistenciaContainer button[onclick="guardarAsistencia()"]').disabled = true;
        }
        if (reglas.votaciones === false) {
            const navItem = document.getElementById('nav-item-votaciones');
            if (navItem) navItem.classList.add('opacity-50');
            deshabilitarSeccion('votaciones-tab-pane', 'Votaciones bloqueadas (sin feedback)');
        }
        if (reglas.adjuntos === false) {
            const navItem = document.getElementById('nav-item-documentos');
            if (navItem) navItem.classList.add('opacity-50');
            deshabilitarSeccion('documentos-tab-pane', 'Adjuntos bloqueados (sin feedback)');
        }
        if (reglas.temas === false && reglas.otro === false) {
            deshabilitarSeccion('contenedorTemas', 'Temas bloqueados (sin feedback)');
            const btnAgregarTema = document.querySelector('button[onclick="agregarTema()"]');
            if (btnAgregarTema) {
                btnAgregarTema.disabled = true;
                btnAgregarTema.title = 'Bloqueado (sin feedback)';
            }
        }
    }

    function deshabilitarSeccion(idElemento, mensaje) {
        // (Esta función no cambia)
        const seccion = document.getElementById(idElemento);
        if (seccion) {
            seccion.querySelectorAll('input, select, textarea, button, .editable-area').forEach(el => {
                el.disabled = true;
                el.contentEditable = false;
                el.style.cursor = 'not-allowed';
                el.style.backgroundColor = '#e9ecef';
                el.title = mensaje;
            });
            seccion.querySelectorAll('.bb-editor-toolbar').forEach(toolbar => {
                toolbar.style.display = 'none';
            });
        }
        if (idElemento === 'contenedorTemas') {
            const target = document.getElementById(idElemento);
            if (target) {
                target.style.opacity = '0.6';
                target.style.pointerEvents = 'none';
                target.title = mensaje;
            }
        }
    }

    function actualizarBotonesParaFeedback() {
        // (Esta función no cambia)
        const btnGuardar = document.getElementById('btnGuardarBorrador');
        const btnEnviar = document.getElementById('btnEnviarAprobacion');

        if (btnGuardar) {
            btnGuardar.innerHTML = '<i class="fas fa-save"></i> Guardar Correcciones';
        }
        if (btnEnviar) {
            btnEnviar.innerHTML = '<i class="fas fa-check-double"></i> Aplicar y Reenviar p/ Aprobación';
            btnEnviar.classList.remove('btn-danger');
            btnEnviar.classList.add('btn-success');
            btnEnviar.setAttribute('onclick', 'if (validarCamposMinuta()) confirmarAplicarFeedback()');
        }
    }

    function confirmarAplicarFeedback() {
        // (Esta función no cambia)
        if (!ES_ST_EDITABLE) {
            Swal.fire('Prohibido', 'No puede reenviar la minuta en este estado.', 'error');
            return;
        }
        Swal.fire({
            title: 'Guardando Correcciones...',
            text: 'Por favor espere, estamos guardando sus cambios antes de reenviar.',
            icon: 'info',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        guardarBorrador(false, function(guardadoExitoso) {
            if (!guardadoExitoso) {
                Swal.fire('Error al Guardar', 'No se pudieron guardar las correcciones. El re-envío fue cancelado.', 'error');
                return;
            }
            Swal.fire({
                title: '¿Confirmar Re-envío?',
                text: "Sus correcciones fueron guardadas. ¿Desea aplicar el 'Sello Verde' y notificar a los presidentes para que firmen de nuevo?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, Reenviar Ahora',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    llamarAplicarFeedback();
                }
            });
        });
    }

    function llamarAplicarFeedback() {
        // (Esta función no cambia)
        const btnEnviar = document.getElementById('btnEnviarAprobacion');
        if (btnEnviar) {
            btnEnviar.disabled = true;
            btnEnviar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Reenviando...';
        }
        const formData = new FormData();
        formData.append('idMinuta', idMinutaGlobal);

        // ⚡ CORRECCIÓN: Se añade 'credentials'
        fetch('/corevota/controllers/aplicar_feedback.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin' // ⚡ CORRECCIÓN DE SESIÓN
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    Swal.fire({
                        title: '¡Minuta Reenviada!',
                        text: data.message,
                        icon: 'success'
                    }).then(() => {
                        window.location.href = 'menu.php?pagina=minutas_listado_general&tab=pendientes_aprobacion';
                    });
                } else {
                    throw new Error(data.message);
                }
            })
            .catch(error => {
                Swal.fire('Error', 'Ocurrió un error al reenviar: ' + error.message, 'error');
                if (btnEnviar) {
                    btnEnviar.disabled = false;
                    btnEnviar.innerHTML = '<i class="fas fa-check-double"></i> Aplicar y Reenviar p/ Aprobación';
                }
            });
    }

    function validarCamposMinuta() {
        // (Esta función no cambia)
        const bloques = document.querySelectorAll('#contenedorTemas .tema-block');
        let valido = true;
        for (let index = 0; index < bloques.length; index++) {
            const bloque = bloques[index];
            const areas = bloque.querySelectorAll('.editable-area');
            const temaEl = areas[0];
            const objetivoEl = areas[1];
            const tema = temaEl ? temaEl.innerHTML.replace(/<br\s*\/?>/gi, '').replace(/&nbsp;/g, '').trim() : '';
            const objetivo = objetivoEl ? objetivoEl.innerHTML.replace(/<br\s*\/?>/gi, '').replace(/&nbsp;/g, '').trim() : '';
            if (!tema || !objetivo) {
                valido = false;
                let faltantes = [];
                if (!tema) faltantes.push('Tema tratado');
                if (!objetivo) faltantes.push('Objetivo');
                Swal.fire({
                    icon: 'warning',
                    title: `Campos obligatorios en Tema ${index + 1}`,
                    text: `Debes ingresar: ${faltantes.join(' y ')} antes de continuar.`,
                    confirmButtonColor: '#198754'
                });
                const collapseTema = bloque.querySelector('button[data-bs-target^="#temaTratado"]');
                if (collapseTema) {
                    const targetId = collapseTema.getAttribute('data-bs-target');
                    const collapseElement = document.querySelector(targetId);
                    if (collapseElement && !collapseElement.classList.contains('show')) {
                        new bootstrap.Collapse(collapseElement, {
                            toggle: true
                        });
                    }
                }
                (tema ? objetivoEl : temaEl)?.focus();
                return false;
            }
        }
        return valido;
    }

    // ==================================================================
    // --- SECCIÓN: EDICIÓN DE TEMAS (Sin cambios) ---
    // ==================================================================

    function format(command) {
        try {
            document.execCommand(command, false, null);
        } catch (e) {
            console.error("Format command failed:", e);
        }
    }

    function cargarOPrepararTemas() {
        if (DATOS_TEMAS_CARGADOS && DATOS_TEMAS_CARGADOS.length > 0) {
            DATOS_TEMAS_CARGADOS.forEach(t => crearBloqueTema(t));
        } else {
            crearBloqueTema();
        }
    }

    function agregarTema() {
        crearBloqueTema();
    }

    function crearBloqueTema(tema = null) {
        contadorTemas++;
        const plantilla = document.getElementById("plantilla-tema");
        if (!plantilla || !plantilla.content) return;
        const nuevo = plantilla.content.cloneNode(true);
        const div = nuevo.querySelector('.tema-block');
        if (!div) return;
        const h6 = nuevo.querySelector('h6');
        if (h6) h6.innerText = `Tema ${contadorTemas}`;
        nuevo.querySelectorAll('[data-bs-target]').forEach(el => {
            let target = el.getAttribute('data-bs-target').replace('_ID_', `_${contadorTemas}_`);
            el.setAttribute('data-bs-target', target);
            el.setAttribute('aria-controls', target.substring(1));
        });
        nuevo.querySelectorAll('.collapse').forEach(el => {
            el.id = el.id.replace('_ID_', `_${contadorTemas}_`);
        });
        const areas = nuevo.querySelectorAll('.editable-area');
        if (tema) {
            if (areas[0]) areas[0].innerHTML = tema.nombreTema || '';
            if (areas[1]) areas[1].innerHTML = tema.objetivo || '';
            if (areas[2]) areas[2].innerHTML = tema.descAcuerdo || '';
            if (areas[3]) areas[3].innerHTML = tema.compromiso || '';
            if (areas[4]) areas[4].innerHTML = tema.observacion || '';
            div.dataset.idTema = tema.idTema;
        }
        const btnEliminar = nuevo.querySelector('.eliminar-tema');
        if (btnEliminar) btnEliminar.style.display = (contadorTemas > 1) ? 'inline-block' : 'none';
        contenedorTemasGlobal.appendChild(nuevo);
        if (!ES_ST_EDITABLE) {
            div.querySelectorAll('input, select, textarea, button, .editable-area').forEach(el => {
                el.disabled = true;
                el.contentEditable = false;
                el.style.cursor = 'not-allowed';
                el.style.backgroundColor = '#e9ecef';
            });
            div.querySelectorAll('.bb-editor-toolbar').forEach(toolbar => {
                toolbar.style.display = 'none';
            });
            if (btnEliminar) btnEliminar.style.display = 'none';
        }
        actualizarNumerosDeTema();
    }

    function eliminarTema(btn) {
        const temaBlock = btn.closest('.tema-block');
        if (temaBlock) {
            temaBlock.remove();
            actualizarNumerosDeTema();
        }
    }

    function actualizarNumerosDeTema() {
        const bloques = contenedorTemasGlobal.querySelectorAll('.tema-block');
        contadorTemas = 0;
        bloques.forEach(b => {
            contadorTemas++;
            const h6 = b.querySelector('h6');
            if (h6) h6.innerText = `Tema ${contadorTemas}`;
            const btnEliminar = b.querySelector('.eliminar-tema');
            if (btnEliminar) btnEliminar.style.display = (contadorTemas > 1 && ES_ST_EDITABLE) ? 'inline-block' : 'none';
        });
    }


    // ==================================================================
    // --- SECCIÓN: ADJUNTOS (CORREGIDA CON 'credentials') ---
    // ==================================================================

    function handleSubirArchivo(e) {
        e.preventDefault();
        const input = document.getElementById('inputArchivo');
        if (!input.files || input.files.length === 0) {
            Swal.fire('Error', 'Debe seleccionar un archivo.', 'warning');
            return;
        }
        if (!ES_ST_EDITABLE) {
            Swal.fire('Prohibido', 'No puede subir archivos en este estado.', 'error');
            return;
        }
        const formData = new FormData();
        formData.append('idMinuta', idMinutaGlobal);
        formData.append('archivo', input.files[0]);
        fileStatus.textContent = 'Subiendo...';
        fileStatus.className = 'badge bg-warning text-dark';
        input.disabled = true;

        // ⚡ CORRECCIÓN: Se añade 'credentials'
        fetch('/corevota/controllers/agregar_adjunto.php?action=upload', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin' // ⚡ CORRECCIÓN DE SESIÓN
            })
            .then(res => {
                if (res.ok) {
                    return res.json();
                }
                return res.text().then(text => {
                    console.error("Respuesta de error del servidor (upload):", text);
                    throw new Error("El servidor respondió con un error (ver consola).");
                });
            })
            .then(data => {
                if (data.status === 'success') {
                    Swal.fire('Éxito', 'Archivo subido correctamente.', 'success');
                    agregarAdjuntoALista(data.data);
                    input.value = '';
                    fileStatus.textContent = '✅ Subido con éxito';
                    fileStatus.className = 'badge bg-success';
                } else {
                    Swal.fire('Error', data.message || 'No se pudo subir el archivo.', 'error');
                    fileStatus.textContent = '❌ Error de subida';
                    fileStatus.className = 'badge bg-danger';
                }
            })
            .catch(err => {
                Swal.fire('Error', 'Error de conexión al subir: ' + err.message, 'error');
                fileStatus.textContent = '❌ Error de conexión';
                fileStatus.className = 'badge bg-danger';
            })
            .finally(() => {
                input.disabled = false;
                setTimeout(() => {
                    fileStatus.textContent = '';
                }, 3000);
            });
    }

    function handleAgregarLink(e) {
        e.preventDefault();
        const input = document.getElementById('inputUrlLink');
        const url = input.value.trim();
        if (!url || !filterUrl(url)) {
            Swal.fire('Error', 'La URL proporcionada no es válida.', 'warning');
            return;
        }
        if (!ES_ST_EDITABLE) {
            Swal.fire('Prohibido', 'No puede agregar enlaces en este estado.', 'error');
            return;
        }
        input.disabled = true;
        input.placeholder = 'Añadiendo...';
        const formData = new FormData();
        formData.append('idMinuta', idMinutaGlobal);
        formData.append('urlLink', url);

        // ⚡ CORRECCIÓN: Se añade 'credentials'
        fetch('/corevota/controllers/agregar_adjunto.php?action=link', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin' // ⚡ CORRECCIÓN DE SESIÓN
            })
            .then(res => {
                if (res.ok) {
                    return res.json();
                }
                return res.text().then(text => {
                    console.error("Respuesta de error del servidor (link):", text);
                    throw new Error("El servidor respondió con un error (ver consola).");
                });
            })
            .then(data => {
                if (data.status === 'success') {
                    Swal.fire('Éxito', 'Enlace agregado correctamente.', 'success');
                    agregarAdjuntoALista(data.data);
                    input.value = '';
                } else {
                    Swal.fire('Error', data.message || 'No se pudo agregar el enlace.', 'error');
                }
            })
            .catch(err => Swal.fire('Error', 'Error de conexión al agregar enlace: ' + err.message, 'error'))
            .finally(() => {
                input.disabled = false;
                input.placeholder = 'https://ejemplo.com';
            });
    }

    function filterUrl(str) {
        // (Esta función no cambia)
        const pattern = new RegExp('^(https?:\\/\\/)?' +
            '((([a-z\\d]([a-z\\d-]*[a-z\\d])*)\\.)+[a-z]{2,}|' +
            '((\\d{1,3}\\.){3}\\d{1,3}))' +
            '(\\:\\d+)?(\\/[-a-z\\d%_.~+]*)*' +
            '(\\?[;&a-z\\d%_.~+=-]*)?' +
            '(\\#[-a-z\\d_]*)?$', 'i');
        return !!pattern.test(str);
    }

    function cargarYMostrarAdjuntosExistentes() {
        if (!idMinutaGlobal) return;

        // ⚡ CORRECCIÓN: Se añade 'credentials'
        fetch(`/corevota/controllers/obtener_adjuntos.php?idMinuta=${idMinutaGlobal}&_cacheBust=${new Date().getTime()}`, {
                method: 'GET',
                credentials: 'same-origin' // ⚡ CORRECCIÓN DE SESIÓN
            })
            .then(response => response.ok ? response.json() : Promise.reject('Error al obtener adjuntos'))
            .then(data => {
                if (data.status === 'success' && data.data) {
                    mostrarAdjuntosExistentes(data.data);
                } else {
                    mostrarAdjuntosExistentes([]);
                }
            })
            .catch(error => {
                console.error('Error al cargar adjuntos:', error);
                const listaUl = document.getElementById('listaAdjuntosExistentes');
                if (listaUl) listaUl.innerHTML = '<li class="list-group-item text-danger">Error al cargar adjuntos actuales.</li>';
            });
    }

    function mostrarAdjuntosExistentes(adjuntos) {
        // (Esta función no cambia)
        const listaUl = document.getElementById('listaAdjuntosExistentes');
        if (!listaUl) return;
        listaUl.innerHTML = '';
        if (!adjuntos || adjuntos.length === 0) {
            listaUl.innerHTML = '<li class="list-group-item text-muted">No hay adjuntos guardados para esta minuta.</li>';
            return;
        }
        adjuntos.forEach(adj => agregarAdjuntoALista(adj));
    }

    function agregarAdjuntoALista(adj) {
        // (Esta función no cambia)
        if (adj.tipoAdjunto === 'asistencia') {
            return;
        }
        const listaUl = document.getElementById('listaAdjuntosExistentes');
        if (!listaUl) return;
        const placeholder = listaUl.querySelector('.text-muted');
        if (placeholder) placeholder.remove();
        const li = document.createElement('li');
        li.className = 'list-group-item d-flex justify-content-between align-items-center';
        const link = document.createElement('a');
        const url = (adj.tipoAdjunto === 'file' || adj.tipoAdjunto === 'asistencia') ? `/corevota/${adj.pathAdjunto}` : adj.pathAdjunto;
        link.href = url;
        link.target = '_blank';
        let icon = (adj.tipoAdjunto === 'link') ? '🔗' : (adj.tipoAdjunto === 'asistencia' ? '👥' : '📄');
        let nombreArchivo = adj.pathAdjunto.split('/').pop();
        if (adj.tipoAdjunto === 'link') {
            nombreArchivo = adj.pathAdjunto.length > 50 ? adj.pathAdjunto.substring(0, 50) + '...' : adj.pathAdjunto;
        }
        link.textContent = ` ${icon} ${nombreArchivo}`;
        link.title = adj.pathAdjunto;
        li.appendChild(link);
        if (adj.tipoAdjunto !== 'asistencia' && ES_ST_EDITABLE) {
            const deleteBtn = document.createElement('button');
            deleteBtn.className = 'btn btn-sm btn-outline-danger ms-2';
            deleteBtn.innerHTML = '<i class="fas fa-trash-alt"></i>';
            deleteBtn.onclick = () => eliminarAdjunto(adj.idAdjunto, li);
            li.appendChild(deleteBtn);
        }
        listaUl.appendChild(li);
    }

    function eliminarAdjunto(idAdjunto, listItemElement) {
        // (Esta función no cambia)
        if (!ES_ST_EDITABLE) {
            Swal.fire('Prohibido', 'No puede eliminar adjuntos en este estado.', 'error');
            return;
        }
        Swal.fire({
            title: '¿Eliminar Adjunto?',
            text: "Esta acción no se puede deshacer.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                // ⚡ CORRECCIÓN: Se añade 'credentials'
                fetch(`/corevota/controllers/eliminar_adjunto.php?idAdjunto=${idAdjunto}`, {
                        method: 'GET',
                        credentials: 'same-origin' // ⚡ CORRECCIÓN DE SESIÓN
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.status === 'success') {
                            listItemElement.remove();
                            Swal.fire('Eliminado', 'El adjunto ha sido eliminado.', 'success');
                        } else {
                            Swal.fire('Error', data.message || 'No se pudo eliminar el adjunto.', 'error');
                        }
                    })
                    .catch(err => Swal.fire('Error', 'Error de conexión: ' + err.message, 'error'));
            }
        });
    }
</script>