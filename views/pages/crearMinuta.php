<?php
// views/pages/crearMinuta.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../class/class.conectorDB.php';
// (Tuve que comentar esto, asegúrate que la ruta es correcta si lo necesitas)
// require_once __DIR__ . '/../../controllers/VotacionController.php'; 

$db = new conectorDB();
$pdo = $db->getDatabase();

$idMinutaActual = $_GET['id'] ?? null;
$minutaData = null;
$reunionData = null;
$temas_de_la_minuta = [];
$asistencia_guardada_ids = []; // Esta variable ahora solo se usa para la carga inicial
$existeAsistenciaGuardada = false;
$secretarioNombre = trim(($_SESSION['pNombre'] ?? '') . ' ' . ($_SESSION['aPaterno'] ?? 'N/A'));

// --- Para Votaciones ---
$idReunionActual = null;
$comisionesDeLaReunion = [];

// --- Variables para encabezado ---
$nombreComisionPrincipal = 'N/A';
$nombrePresidentePrincipal = 'N/A';
$idPresidentePrincipal = null;
$nombreComisionMixta1 = null;
$nombrePresidenteMixta1 = null;
$idPresidenteMixta1 = null;
$nombreComisionMixta2 = null;
$nombrePresidenteMixta2 = null;
$idPresidenteMixta2 = null;
$all_commissions = [];
$all_presidents = [];

$estadoMinuta = 'BORRADOR'; // Default para nuevas
$puedeEnviar = false; // Default

// --- Solo intentar cargar si el ID es válido ---
if ($idMinutaActual && is_numeric($idMinutaActual)) {
    try {
        // 1. Cargar datos de t_minuta
        $sql_minuta = "SELECT t_comision_idComision, t_usuario_idPresidente, estadoMinuta, fechaMinuta, horaMinuta 
                        FROM t_minuta 
                        WHERE idMinuta = :idMinutaActual";
        $stmt_minuta = $pdo->prepare($sql_minuta);
        $stmt_minuta->execute([':idMinutaActual' => $idMinutaActual]);
        $minutaData = $stmt_minuta->fetch(PDO::FETCH_ASSOC);

        if (!$minutaData) {
            throw new Exception("Minuta con ID $idMinutaActual no encontrada.");
        }

        $estadoMinuta = $minutaData['estadoMinuta']; // Guardar el estado

        // 2. Cargar datos de t_reunion
        $sql_reunion = "SELECT idReunion, t_comision_idComision, t_comision_idComision_mixta, t_comision_idComision_mixta2 
                        FROM t_reunion 
                        WHERE t_minuta_idMinuta = :idMinutaActual";
        $stmt_reunion = $pdo->prepare($sql_reunion);
        $stmt_reunion->execute([':idMinutaActual' => $idMinutaActual]);
        $reunionData = $stmt_reunion->fetch(PDO::FETCH_ASSOC);
        $idReunionActual = $reunionData['idReunion'] ?? null;

        // 3. Cargar TODAS las comisiones vigentes y TODOS los posibles presidentes (Consejeros)
        $stmt_all_com = $pdo->query("SELECT idComision, nombreComision, t_usuario_idPresidente FROM t_comision WHERE vigencia = 1");
        if ($stmt_all_com) {
            $all_commissions_raw = $stmt_all_com->fetchAll(PDO::FETCH_ASSOC);
            foreach ($all_commissions_raw as $com) {
                $all_commissions[$com['idComision']] = $com;
            }
        }

        $stmt_all_pres = $pdo->query("SELECT idUsuario, pNombre, aPaterno FROM t_usuario WHERE tipoUsuario_id = 3"); // tipoUsuario_id = 3 (Presidente)
        if ($stmt_all_pres) {
            $all_presidents_raw = $stmt_all_pres->fetchAll(PDO::FETCH_ASSOC);
            foreach ($all_presidents_raw as $pres) {
                $all_presidents[$pres['idUsuario']] = trim($pres['pNombre'] . ' ' . $pres['aPaterno']);
            }
        }

        // --- 4. ASIGNAR NOMBRES PARA MOSTRAR EN EL ENCABEZADO ---
        $idComisionPrincipal = $minutaData['t_comision_idComision'];
        $idPresidentePrincipal = $minutaData['t_usuario_idPresidente'];
        $nombreComisionPrincipal = $all_commissions[$idComisionPrincipal]['nombreComision'] ?? 'Comisión No Encontrada/Inválida';
        $nombrePresidentePrincipal = $all_presidents[$idPresidentePrincipal] ?? 'Presidente No Encontrado/Inválido';

        if (isset($all_commissions[$idComisionPrincipal])) {
            $comisionesDeLaReunion[$idComisionPrincipal] = $nombreComisionPrincipal;
        }

        if ($reunionData && !empty($reunionData['t_comision_idComision_mixta'])) {
            $idComisionMixta1 = $reunionData['t_comision_idComision_mixta'];
            if (isset($all_commissions[$idComisionMixta1])) {
                $nombreComisionMixta1 = $all_commissions[$idComisionMixta1]['nombreComision'];
                $idPresidenteMixta1 = $all_commissions[$idComisionMixta1]['t_usuario_idPresidente'] ?? null;
                $nombrePresidenteMixta1 = $idPresidenteMixta1 ? ($all_presidents[$idPresidenteMixta1] ?? 'Presidente No Asignado') : 'N/A';
                $comisionesDeLaReunion[$idComisionMixta1] = $nombreComisionMixta1;
            } else {
                $nombreComisionMixta1 = 'Comisión Mixta 1 No Encontrada/Inválida';
                $nombrePresidenteMixta1 = 'N/A';
            }
        }
        if ($reunionData && !empty($reunionData['t_comision_idComision_mixta2'])) {
            $idComisionMixta2 = $reunionData['t_comision_idComision_mixta2'];
            if (isset($all_commissions[$idComisionMixta2])) {
                $nombreComisionMixta2 = $all_commissions[$idComisionMixta2]['nombreComision'];
                $idPresidenteMixta2 = $all_commissions[$idComisionMixta2]['t_usuario_idPresidente'] ?? null;
                $nombrePresidenteMixta2 = $idPresidenteMixta2 ? ($all_presidents[$idPresidenteMixta2] ?? 'Presidente No Asignado') : 'N/A';
                $comisionesDeLaReunion[$idComisionMixta2] = $nombreComisionMixta2;
            } else {
                $nombreComisionMixta2 = 'Comisión Mixta 2 No Encontrada/Inválida';
                $nombrePresidenteMixta2 = 'N/A';
            }
        }

        // 5. Cargar temas (sin cambios)
        $sql_temas = "SELECT t.idTema, t.nombreTema, t.objetivo, t.compromiso, t.observacion, a.descAcuerdo
                        FROM t_tema t LEFT JOIN t_acuerdo a ON a.t_tema_idTema = t.idTema
                        WHERE t.t_minuta_idMinuta = :idMinutaActual ORDER BY t.idTema ASC";
        $stmt_temas = $pdo->prepare($sql_temas);
        $stmt_temas->execute([':idMinutaActual' => $idMinutaActual]);
        $temas_de_la_minuta = $stmt_temas->fetchAll(PDO::FETCH_ASSOC);

        // 6. Cargar asistencia (SOLO PARA LA CARGA INICIAL)
        $sql_asistencia = "SELECT t_usuario_idUsuario FROM t_asistencia WHERE t_minuta_idMinuta = :idMinutaActual";
        $stmt_asistencia = $pdo->prepare($sql_asistencia);
        $stmt_asistencia->execute([':idMinutaActual' => $idMinutaActual]);
        $asistencia_guardada_ids = $stmt_asistencia->fetchAll(PDO::FETCH_COLUMN, 0);
        $existeAsistenciaGuardada = !empty($asistencia_guardada_ids);
    } catch (Exception $e) {
        error_log("Error cargando datos para edición (Minuta ID: {$idMinutaActual}): " . $e->getMessage());
        die("❌ Error al cargar los datos de la minuta: " . htmlspecialchars($e->getMessage()) . "<br><a href='menu.php?pagina=minutas_pendientes'>Volver al listado</a>");
    } finally {
        $pdo = null; // Cerrar conexión
    }
} else {
    die("❌ Error: No se especificó un ID de minuta válido para editar. <a href='menu.php?pagina=minutas_pendientes'>Volver al listado</a>");
}

$puedeEnviar = ($estadoMinuta !== 'APROBADA');

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Gestión de Minuta #<?php echo htmlspecialchars($idMinutaActual); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="/corevota/public/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="/corevota/public/css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .card-header.bg-primary {
            background-color: #0d6efd !important;
        }

        .card-body.bg-light {
            background-color: #f8f9fa !important;
        }

        dl.row dt {
            font-weight: 600;
            text-align: right;
            padding-right: 0.5em;
        }

        dl.row>div {
            margin-bottom: 0.3rem;
        }

        dl.row dd {
            word-break: break-word;
        }

        .btn:disabled {
            cursor: not-allowed;
        }

        .bb-editor-toolbar {
            padding: 5px;
            background: #f8f9fa;
            border: 1px solid #ced4da;
            border-bottom: none;
            border-radius: 0.25rem 0.25rem 0 0;
        }

        .editable-area {
            min-height: 100px;
            max-height: 250px;
            overflow-y: auto;
            background: #fff;
            border-radius: 0 0 0.25rem 0.25rem;
        }

        .editable-area:focus {
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, .25);
        }

        .editable-area[placeholder]:empty:before {
            content: attr(placeholder);
            color: #6c757d;
            opacity: 1;
        }

        .dropdown-form-block .btn.collapsed {
            background-color: #f8f9fa;
        }

        /* == INICIO: ESTILOS PARA VOTACIÓN EN VIVO == */
        .votacion-block-ui ul {
            padding-left: 1.2rem;
            margin: 0.5rem 0;
            list-style-type: disc;
        }

        .votacion-block-ui td {
            vertical-align: top;
            font-size: 0.85rem;
        }

        .votacion-block-ui h5 {
            font-size: 1.1rem;
        }

        /* == FIN: ESTILOS PARA VOTACIÓN EN VIVO == */
    </style>
</head>

<body>

    <div class="container-fluid app-container p-4">
        <h5 class="fw-bold mb-3">GESTIÓN DE LA MINUTA</h5>

        <div id="feedback-display-container" class="alert alert-danger shadow-sm" style="display:none;">
            <h4 class="alert-heading"><i class="fas fa-comment-dots me-2"></i> Feedback Pendiente</h4>
            <p>Un presidente ha enviado las siguientes observaciones. Por favor, realiza las correcciones y luego haz clic en "Aplicar y Reenviar p/ Aprobación".</p>
            <hr>
            <div id="feedback-display-texto" style="white-space: pre-wrap; font-family: monospace; max-height: 200px; overflow-y: auto; background-color: rgba(255,255,255,0.1); padding: 10px; border-radius: 5px;">
            </div>
        </div>

        <div class="row g-3">

            <div class="col-12 mb-3">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white fw-bold d-flex justify-content-between align-items-center">
                        <span>Encabezado Minuta</span>
                        <span class="badge 
                            <?php
                            switch ($estadoMinuta) {
                                case 'APROBADA':
                                    echo 'bg-success';
                                    break;
                                case 'PARCIAL':
                                    echo 'bg-info';
                                    break;
                                case 'PENDIENTE':
                                    echo 'bg-warning text-dark';
                                    break;
                                case 'REQUIERE_REVISION':
                                    echo 'bg-danger';
                                    break;
                                case 'BORRADOR':
                                default:
                                    echo 'bg-light text-dark';
                                    break;
                            }
                            ?>
                        ">ESTADO: <?php echo htmlspecialchars($estadoMinuta); ?></span>
                    </div>
                    <div class="card-body bg-light">
                        <div class="row">
                            <div class="col-md-6 border-end pe-4">
                                <dl class="row mb-0">
                                    <dt class="col-sm-5 col-lg-4">N° Sesión:</dt>
                                    <dd class="col-sm-7 col-lg-8"><?php echo htmlspecialchars($idMinutaActual); ?></dd>
                                    <dt class="col-sm-5 col-lg-4">Fecha:</dt>
                                    <dd class="col-sm-7 col-lg-8"><?php echo htmlspecialchars(date('d-m-Y', strtotime($minutaData['fechaMinuta'] ?? 'now'))); ?></dd>
                                    <dt class="col-sm-5 col-lg-4">Hora:</dt>
                                    <dd class="col-sm-7 col-lg-8"><?php echo htmlspecialchars(date('H:i', strtotime($minutaData['horaMinuta'] ?? 'now'))); ?> hrs.</dd>
                                    <dt class="col-sm-5 col-lg-4">Secretario Técnico:</dt>
                                    <dd class="col-sm-7 col-lg-8"><?php echo htmlspecialchars($secretarioNombre); ?></dd>
                                </dl>
                            </div>
                            <div class="col-md-6 ps-4">
                                <dl class="row mb-0">
                                    <?php if (!$nombreComisionMixta1 && !$nombreComisionMixta2) : // Caso: Comisión Única 
                                    ?>
                                        <dt class="col-sm-5 col-lg-4">Comisión:</dt>
                                        <dd class="col-sm-7 col-lg-8"><?php echo htmlspecialchars($nombreComisionPrincipal); ?></dd>
                                        <dt class="col-sm-5 col-lg-4">Presidente:</dt>
                                        <dd class="col-sm-7 col-lg-8"><?php echo htmlspecialchars($nombrePresidentePrincipal); ?></dd>
                                    <?php else : // Caso: Comisión Mixta/Conjunta 
                                    ?>
                                        <dt class="col-sm-5 col-lg-4">1° Comisión:</dt>
                                        <dd class="col-sm-7 col-lg-8"><?php echo htmlspecialchars($nombreComisionPrincipal); ?></dd>
                                        <dt class="col-sm-5 col-lg-4">1° Presidente:</dt>
                                        <dd class="col-sm-7 col-lg-8"><?php echo htmlspecialchars($nombrePresidentePrincipal); ?></dd>
                                        <?php if ($nombreComisionMixta1) : ?>
                                            <dt class="col-sm-5 col-lg-4 mt-1">2° Comisión:</dt>
                                            <dd class="col-sm-7 col-lg-8 mt-1"><?php echo htmlspecialchars($nombreComisionMixta1); ?></dd>
                                            <dt class="col-sm-5 col-lg-4">2° Presidente:</dt>
                                            <dd class="col-sm-7 col-lg-8"><?php echo htmlspecialchars($nombrePresidenteMixta1); ?></dd>
                                        <?php endif; ?>
                                        <?php if ($nombreComisionMixta2) : ?>
                                            <dt class="col-sm-5 col-lg-4 mt-1">3° Comisión:</dt>
                                            <dd class="col-sm-7 col-lg-8 mt-1"><?php echo htmlspecialchars($nombreComisionMixta2); ?></dd>
                                            <dt class="col-sm-5 col-lg-4">3° Presidente:</dt>
                                            <dd class="col-sm-7 col-lg-8"><?php echo htmlspecialchars($nombrePresidenteMixta2); ?></dd>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-12 mt-2">
                <div class="dropdown-form-block mb-3">
                    <button class="btn btn-secondary dropdown-toggle w-100 text-start fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#asistenciaForm" aria-expanded="false" aria-controls="asistenciaForm">
                        Asistencia (Marcar estado)
                    </button>
                    <div class="collapse" id="asistenciaForm">
                        <div class="p-4 border rounded-bottom bg-white">
                            <div id="contenedorTablaAsistenciaEstado" style="max-height: 400px; overflow-y: auto;">
                                <p class="text-muted">Cargando lista de consejeros...</p>
                            </div>

                            <div class="d-flex justify-content-end align-items-center mt-3 gap-2" id="botonesAsistenciaContainer">
                                <span id="guardarAsistenciaStatus" class="me-auto small text-muted"></span>

                                <button type="button" class="btn btn-outline-primary btn-sm" id="btn-refrescar-asistencia" onclick="cargarTablaAsistencia()">
                                    <i class="fas fa-sync me-1"></i> Refrescar
                                </button>
                                <button type="button" class="btn btn-info btn-sm" onclick="guardarAsistencia()">
                                    <i class="fas fa-save me-1"></i> Guardar Asistencia
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-12 mt-2">
                <div class="dropdown-form-block mb-3">
                    <button class="btn btn-info dropdown-toggle w-100 text-start fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#votacionForm" aria-expanded="false" aria-controls="votacionForm">
                        <i class="fa-solid fa-check-to-slot me-2"></i> Gestión de Votaciones
                    </button>
                    <div class="collapse" id="votacionForm">
                        <div class="p-4 border rounded-bottom bg-white">

                            <form id="formCrearVotacionMinuta" onsubmit="guardarNuevaVotacion(); return false;">
                                <h6 class="fw-bold">Crear Nueva Votación</h6>
                                <div class="mb-3">
                                    <label for="votacionComisionId" class="form-label">Asociar a Comisión (para lista de votantes):</label>
                                    <select class="form-select" id="votacionComisionId" required>
                                        <option value="">Seleccione una comisión...</option>
                                        <?php if (empty($comisionesDeLaReunion)): ?>
                                            <option value="" disabled>No hay comisiones cargadas</option>
                                        <?php else: ?>
                                            <?php foreach ($comisionesDeLaReunion as $idCom => $nombreCom): ?>
                                                <option value="<?php echo $idCom; ?>"><?php echo htmlspecialchars($nombreCom); ?></option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="votacionNombre" class="form-label">Texto de la Votación (Pregunta):</label>
                                    <input type="text" class="form-control" id="votacionNombre" placeholder="Ej: ¿Aprueba el presupuesto para...?" required>
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="fas fa-plus me-1"></i> Crear y Habilitar Votación
                                </button>
                            </form>

                            <hr class="my-4">

                            <h6 class="fw-bold">Votaciones Creadas</h6>
                            <div id="listaVotacionesMinuta">
                                <p class="text-muted" id="votacionesStatus">Cargando votaciones...</p>
                            </div>

                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-12 mt-2">
                <div class="card shadow-sm">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fa-solid fa-chart-simple me-2"></i>
                            Resultados Preliminares de Votación
                        </h5>
                        <button class="btn btn-sm btn-outline-primary" id="btn-refrescar-votaciones" onclick="cargarResultadosVotacion()">
                            <i class="fa-solid fa-sync me-1"></i> Refrescar
                        </button>
                    </div>

                    <div class="card-body" id="votacion-resultados-live">
                        <p class="text-muted text-center" id="votacion-placeholder">Cargando resultados...</p>
                    </div>
                </div>
            </div>
            <div class="col-12 mt-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0">DESARROLLO DE LA MINUTA</h5>
                </div>
                <div id="contenedorTemas">
                </div>
                <button type="button" class="btn btn-outline-dark btn-sm mt-2" onclick="agregarTema()">Agregar Tema <span class="ms-1">➕</span></button>

                <div class="adjuntos-section mt-4 pt-3 border-top">
                    <h5 class="fw-bold mb-3">DOCUMENTOS ADJUNTOS</h5>
                    <input type="hidden" id="idMinutaActual" value="<?php echo htmlspecialchars($idMinutaActual); ?>">
                    <form id="formSubirArchivo" class="mb-3">
                        <label for="inputArchivo" class="form-label">Añadir nuevo archivo (PDF, JPG, PNG, XLSX, MP4, PPT, DOCX)</label>
                        <div class="input-group">
                            <input type="file" class="form-control" id="inputArchivo" name="archivo" required accept=".pdf,.jpg,.jpeg,.png,.xlsx,.mp4,.ppt,.pptx,.doc,.docx">
                            <button class="btn btn-primary" type="submit" id="btnSubirArchivo">
                                <i class="fas fa-upload me-2"></i>Subir
                            </button>
                        </div>
                    </form>
                    <form id="formAgregarLink" class="mb-3">
                        <label for="inputUrlLink" class="form-label">Añadir nuevo enlace</label>
                        <div class="input-group">
                            <input type="url" class="form-control" id="inputUrlLink" name="urlLink" placeholder="https://ejemplo.com" required>
                            <button class="btn btn-info" type="submit" id="btnAgregarLink">
                                <i class="fas fa-link me-2"></i>Añadir
                            </button>
                        </div>
                    </form>
                    <div id="adjuntosExistentesContainer" class="mt-4">
                        <h6>Archivos y Enlaces Existentes:</h6>
                        <ul id="listaAdjuntosExistentes" class="list-group list-group-flush">
                            <li class="list-group-item text-muted">Cargando...</li>
                        </ul>
                    </div>
                </div>

                <div class="d-flex justify-content-center gap-3 mt-4 pt-4 border-top">
                    <div class="text-end">

                        <button type="button" class="btn btn-success fw-bold" id="btnGuardarBorrador"
                            onclick="if (validarCamposMinuta()) guardarBorrador(true);">
                            <i class="fas fa-save"></i> Guardar Borrador
                        </button>

                        <button type="button" class="btn btn-danger fw-bold ms-3" id="btnEnviarAprobacion"
                            onclick="if (validarCamposMinuta()) confirmarEnvioAprobacion();"
                            <?php echo !$puedeEnviar ? 'disabled' : ''; ?>>
                            <i class="fas fa-paper-plane"></i> Enviar para Aprobación
                        </button>

                        <?php if (!$puedeEnviar) : ?>
                            <small class="d-block text-danger mt-2">Esta minuta ya fue APROBADA y no puede volver a enviarse.</small>
                        <?php endif; ?>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <template id="plantilla-tema">
        <div class="tema-block mb-4 border rounded p-3 bg-white shadow-sm position-relative">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold text-primary mb-0">Tema #</h6>
            </div>
            <div class="dropdown-form-block mb-3">
                <button class="btn btn-light border text-start w-100 fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#temaTratado_ID_" aria-expanded="true" aria-controls="temaTratado_ID_">TEMA TRATADO</button>
                <div class="collapse show" id="temaTratado_ID_">
                    <div class="editor-container p-3 border border-top-0 bg-white">
                        <div class="bb-editor-toolbar no-select mb-2" role="toolbar"> <button type="button" class="btn btn-sm btn-light border me-1" onclick="format('bold')"><b>B</b></button> <button type="button" class="btn btn-sm btn-light border me-1" onclick="format('italic')"><i>I</i></button> <button type="button" class="btn btn-sm btn-light border me-1" onclick="format('underline')"><u>U</u></button> </div>
                        <div class="editable-area form-control" contenteditable="true" placeholder="Escribe el tema..."></div>
                    </div>
                </div>
            </div>
            <div class="dropdown-form-block mb-3">
                <button class="btn btn-light border text-start w-100 fw-bold collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#objetivo_ID_" aria-expanded="false" aria-controls="objetivo_ID_">OBJETIVO</button>
                <div class="collapse" id="objetivo_ID_">
                    <div class="editor-container p-3 border border-top-0 bg-white">
                        <div class="bb-editor-toolbar no-select mb-2" role="toolbar"> <button type="button" class="btn btn-sm btn-light border me-1" onclick="format('bold')"><b>B</b></button> <button type="button" class="btn btn-sm btn-light border me-1" onclick="format('italic')"><i>I</i></button> <button type="button" class="btn btn-sm btn-light border me-1" onclick="format('underline')"><u>U</u></button> </div>
                        <div class="editable-area form-control" contenteditable="true" placeholder="Describe el objetivo..."></div>
                    </div>
                </div>
            </div>
            <div class="dropdown-form-block mb-3">
                <button class="btn btn-light border text-start w-100 fw-bold collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#acuerdos_ID_" aria-expanded="false" aria-controls="acuerdos_ID_">ACUERDOS ADOPTADOS</button>
                <div class="collapse" id="acuerdos_ID_">
                    <div class="editor-container p-3 border border-top-0 bg-white">
                        <div class="bb-editor-toolbar no-select mb-2" role="toolbar"> <button type="button" class="btn btn-sm btn-light border me-1" onclick="format('bold')"><b>B</b></button> <button type="button" class="btn btn-sm btn-light border me-1" onclick="format('italic')"><i>I</i></button> <button type="button" class="btn btn-sm btn-light border me-1" onclick="format('underline')"><u>U</u></button> </div>
                        <div class="editable-area form-control" contenteditable="true" placeholder="Anota acuerdos..."></div>
                    </div>
                </div>
            </div>
            <div class="dropdown-form-block mb-3">
                <button class="btn btn-light border text-start w-100 fw-bold collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#compromisos_ID_" aria-expanded="false" aria-controls="compromisos_ID_">COMPROMISOS Y RESPONSABLES</button>
                <div class="collapse" id="compromisos_ID_">
                    <div class="editor-container p-3 border border-top-0 bg-white">
                        <div class="bb-editor-toolbar no-select mb-2" role="toolbar"> <button type="button" class="btn btn-sm btn-light border me-1" onclick="format('bold')"><b>B</b></button> <button type="button" class="btn btn-sm btn-light border me-1" onclick="format('italic')"><i>I</i></button> <button type="button" class="btn btn-sm btn-light border me-1" onclick="format('underline')"><u>U</u></button> </div>
                        <div class="editable-area form-control" contenteditable="true" placeholder="Registra compromisos..."></div>
                    </div>
                </div>
            </div>
            <div class="dropdown-form-block mb-3">
                <button class="btn btn-light border text-start w-100 fw-bold text-primary collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#observaciones_ID_" aria-expanded="false" aria-controls="observaciones_ID_">OBSERVACIONES Y COMENTARIOS</button>
                <div class="collapse" id="observaciones_ID_">
                    <div class="editor-container p-3 border border-top-0 bg-white">
                        <div class="bb-editor-toolbar no-select mb-2" role="toolbar"> <button type="button" class="btn btn-sm btn-light border me-1" onclick="format('bold')"><b>B</b></button> <button type="button" class="btn btn-sm btn-light border me-1" onclick="format('italic')"><i>I</i></button> <button type="button" class="btn btn-sm btn-light border me-1" onclick="format('underline')"><u>U</u></button> </div>
                        <div class="editable-area form-control" contenteditable="true" placeholder="Añade observaciones..."></div>
                    </div>
                </div>
            </div>
            <div class="text-end mt-3"> <button type="button" class="btn btn-outline-danger btn-sm eliminar-tema" onclick="eliminarTema(this)" style="display:none;">❌ Eliminar Tema</button> </div>
        </div>
    </template>

    <script>
        // --- Variables Globales (Tu JS) ---
        let contadorTemas = 0;
        const contenedorTemasGlobal = document.getElementById("contenedorTemas");
        const idMinutaGlobal = <?php echo json_encode($idMinutaActual); ?>;
        const ID_REUNION_GLOBAL = <?php echo json_encode($idReunionActual); ?>;
        const ID_SECRETARIO_LOGUEADO = <?php echo json_encode($_SESSION['idUsuario'] ?? 0); ?>;
        const ESTADO_MINUTA_ACTUAL = <?php echo json_encode($estadoMinuta); ?>;
        const DATOS_TEMAS_CARGADOS = <?php echo json_encode($temas_de_la_minuta ?? []); ?>;

        // ¡MODIFICADO! Esta variable AHORA se actualizará con cada fetch
        let ASISTENCIA_GUARDADA_IDS = <?php echo json_encode($asistencia_guardada_ids ?? []); ?>;

        // --- Evento Principal de Carga (Tu JS) ---
        document.addEventListener("DOMContentLoaded", () => {
            cargarTablaAsistencia(); // Esta función ahora refresca la lista de IDs
            cargarOPrepararTemas();
            cargarYMostrarAdjuntosExistentes();
            cargarVotacionesDeLaMinuta();
            cargarResultadosVotacion();
        });

        // ==================================================================
        // --- INICIO: SECCIÓN DE ASISTENCIA MODIFICADA ---
        // ==================================================================

        function cargarTablaAsistencia() {
            const cont = document.getElementById("contenedorTablaAsistenciaEstado");
            const btnRefresh = document.getElementById("btn-refrescar-asistencia");
            if (btnRefresh) btnRefresh.disabled = true;

            cont.innerHTML = '<p class="text-muted"><i class="fas fa-spinner fa-spin me-2"></i>Cargando lista de consejeros y asistencia...</p>';

            // 1. Cargar la lista MAESTRA de consejeros
            const fetchConsejeros = fetch("/corevota/controllers/fetch_data.php?action=asistencia_all")
                .then(res => res.ok ? res.json() : Promise.reject(new Error('Error fetch_data.php')));

            // 2. Cargar la lista ACTUAL de asistencia (NUEVA API)
            const fetchAsistenciaActual = fetch(`/corevota/controllers/obtener_asistencia_actual.php?idMinuta=${idMinutaGlobal}`)
                .then(res => res.ok ? res.json() : Promise.reject(new Error('Error obtener_asistencia_actual.php')));

            // 3. Esperar ambas
            Promise.all([fetchConsejeros, fetchAsistenciaActual])
                .then(([responseConsejeros, responseAsistencia]) => {

                    if (responseConsejeros.status !== 'success') {
                        throw new Error('No se pudo cargar la lista de consejeros.');
                    }
                    if (responseAsistencia.status !== 'success') {
                        throw new Error('No se pudo cargar la asistencia actual.');
                    }

                    const data = responseConsejeros.data; // Lista de usuarios

                    // ¡ACTUALIZAMOS LA VARIABLE GLOBAL!
                    ASISTENCIA_GUARDADA_IDS = responseAsistencia.data; // Array de IDs [15, 37, 40]

                    if (data && data.length > 0) {
                        const asistenciaGuardadaStrings = ASISTENCIA_GUARDADA_IDS.map(String);

                        let tabla = `<table class="table table-sm table-hover" id="tablaAsistenciaEstado"><thead><tr><th style="text-align: left;">Nombre Consejero</th><th style="width: 100px;">Presente</th><th style="width: 100px;">Ausente</th></tr></thead><tbody>`;
                        data.forEach(c => {
                            const userIdString = String(c.idUsuario);
                            // Usamos la lista NUEVA (asistenciaGuardadaStrings)
                            const isPresent = asistenciaGuardadaStrings.includes(userIdString);
                            const isAbsent = !isPresent;
                            tabla += `<tr data-userid="${c.idUsuario}"><td style="text-align: left;"><label class="form-check-label w-100" for="present_${userIdString}">${c.nombreCompleto}</label></td><td><input class="form-check-input asistencia-checkbox present-check" type="checkbox" id="present_${userIdString}" value="${userIdString}" onchange="handleAsistenciaChange('${userIdString}', 'present')" ${isPresent ? 'checked' : ''}></td><td><input class="form-check-input asistencia-checkbox absent-check default-absent" type="checkbox" id="absent_${userIdString}" onchange="handleAsistenciaChange('${userIdString}', 'absent')" ${isAbsent ? 'checked' : ''}></td></tr>`;
                        });
                        tabla += `</tbody></table>`;
                        cont.innerHTML = tabla;
                    } else {
                        cont.innerHTML = '<p class="text-danger">No hay consejeros para cargar.</p>';
                    }
                })
                .catch(err => {
                    console.error("Error carga asistencia:", err);
                    cont.innerHTML = `<p class="text-danger">Error al refrescar asistencia: ${err.message}</p>`;
                })
                .finally(() => {
                    if (btnRefresh) btnRefresh.disabled = false;
                });
        }

        function handleAsistenciaChange(userId, changedType) {
            const present = document.getElementById(`present_${userId}`);
            const absent = document.getElementById(`absent_${userId}`);
            if (changedType === 'present') {
                absent.checked = !present.checked;
            } else if (changedType === 'absent') {
                present.checked = !absent.checked;
            }
        }

        function recolectarAsistencia() {
            const ids = [];
            const presentes = document.querySelectorAll("#tablaAsistenciaEstado .present-check:checked");
            presentes.forEach(chk => ids.push(chk.value));
            return {
                asistenciaIDs: ids
            };
        }

        function guardarAsistencia() {
            const {
                asistenciaIDs
            } = recolectarAsistencia();
            const status = document.getElementById('guardarAsistenciaStatus');
            const btn = document.querySelector('#botonesAsistenciaContainer button[onclick="guardarAsistencia()"]');
            let datos = {
                idMinuta: idMinutaGlobal,
                asistencia: asistenciaIDs
            };
            btn.disabled = true;
            status.textContent = 'Guardando...';
            status.className = 'me-auto small text-muted';

            fetch("/corevota/controllers/guardar_asistencia.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json"
                    },
                    body: JSON.stringify(datos)
                })
                .then(res => res.ok ? res.json() : res.text().then(text => Promise.reject(new Error("Respuesta servidor inválida: " + text))))
                .then(resp => {
                    btn.disabled = false;
                    if (resp.status === "success") {
                        status.textContent = "✅ Guardado";
                        status.className = 'me-auto small text-success fw-bold';

                        // ¡MODIFICACIÓN!
                        // Volvemos a cargar la tabla para asegurar que el estado (local y servidor)
                        // estén sincronizados.
                        cargarTablaAsistencia();
                        // ¡FIN MODIFICACIÓN!

                        setTimeout(() => {
                            status.textContent = '';
                        }, 3000);
                    } else {
                        status.textContent = `⚠️ Error: ${resp.message}`;
                        status.className = 'me-auto small text-danger';
                    }
                })
                .catch(err => {
                    btn.disabled = false;
                    status.textContent = "Error conexión.";
                    status.className = 'me-auto small text-danger';
                    console.error("Error fetch asistencia:", err);
                    setTimeout(() => {
                        status.textContent = '';
                    }, 5000);
                });
        }

        // ==================================================================
        // --- FIN: SECCIÓN DE ASISTENCIA MODIFICADA ---
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
                if (btnEliminar) btnEliminar.style.display = (contadorTemas > 1) ? 'inline-block' : 'none';
            });
        }

        // --- (CORREGIDO) ---
        document.getElementById('formSubirArchivo').addEventListener('submit', handleSubirArchivo);
        document.getElementById('formAgregarLink').addEventListener('submit', handleAgregarLink);

        function handleSubirArchivo(e) {
            e.preventDefault();
            const input = document.getElementById('inputArchivo');
            const btn = document.getElementById('btnSubirArchivo');
            if (!input.files || input.files.length === 0) {
                Swal.fire('Error', 'Debe seleccionar un archivo.', 'warning');
                return;
            }
            const formData = new FormData();
            formData.append('idMinuta', idMinutaGlobal);
            formData.append('archivo', input.files[0]);
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Subiendo...';

            // (CORREGIDO) Usar ruta absoluta
            fetch('/corevota/controllers/agregar_adjunto.php?action=upload', {
                    method: 'POST',
                    body: formData
                })
                .then(res => {
                    if (res.ok) {
                        return res.json();
                    }
                    return res.text().then(text => {
                        console.error("Respuesta de error del servidor (upload):", text);
                        // El error 404/HTML entrará aquí
                        throw new Error("El servidor respondió con un error (ver consola).");
                    });
                })
                .then(data => {
                    if (data.status === 'success') {
                        Swal.fire('Éxito', 'Archivo subido correctamente.', 'success');
                        agregarAdjuntoALista(data.data);
                        input.value = '';
                    } else {
                        // El error "Acción no válida" entrará aquí
                        Swal.fire('Error', data.message || 'No se pudo subir el archivo.', 'error');
                    }
                })
                .catch(err => Swal.fire('Error', 'Error de conexión al subir: ' + err.message, 'error'))
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-upload me-2"></i>Subir';
                });
        }
        // --- (NUEVA FUNCIÓN AUXILIAR) ---
        // Esta función añade un solo adjunto a la lista en el DOM
        function agregarAdjuntoALista(adj) {
            const listaUl = document.getElementById('listaAdjuntosExistentes');
            if (!listaUl) return;

            // 1. Revisa si está el mensaje "Cargando..." o "No hay adjuntos..."
            const placeholder = listaUl.querySelector('.text-muted');
            if (placeholder) {
                placeholder.remove(); // Lo borra para añadir el primer item
            }

            // 2. Crea el nuevo elemento <li> (copiado de tu función mostrarAdjuntosExistentes)
            const li = document.createElement('li');
            li.className = 'list-group-item d-flex justify-content-between align-items-center';

            const link = document.createElement('a');

            // Usa la misma lógica de ruta que ya tenías
            const url = (adj.tipoAdjunto === 'file' || adj.tipoAdjunto === 'asistencia') ? `/corevota/${adj.pathAdjunto}` : adj.pathAdjunto;
            link.href = url;
            link.target = '_blank';

            let icon = (adj.tipoAdjunto === 'link') ? '🔗' : '📄'; // (No aplica 'asistencia' aquí)
            let nombreArchivo = adj.pathAdjunto.split('/').pop();
            if (adj.tipoAdjunto === 'link') {
                nombreArchivo = adj.pathAdjunto.length > 50 ? adj.pathAdjunto.substring(0, 50) + '...' : adj.pathAdjunto;
            }

            link.textContent = ` ${icon} ${nombreArchivo}`;
            link.title = adj.pathAdjunto;
            li.appendChild(link);

            // 3. Añade el botón de eliminar
            if (adj.tipoAdjunto !== 'asistencia') {
                const deleteBtn = document.createElement('button');
                deleteBtn.className = 'btn btn-sm btn-outline-danger ms-2';
                deleteBtn.innerHTML = '<i class="fas fa-trash-alt"></i>';
                // Importante: Pasa el elemento 'li' para que la función eliminar sepa qué borrar
                deleteBtn.onclick = () => eliminarAdjunto(adj.idAdjunto, li);
                li.appendChild(deleteBtn);
            }

            // 4. Añade el <li> a la lista <ul>
            listaUl.appendChild(li);
        }

        function handleAgregarLink(e) {
            e.preventDefault();
            const input = document.getElementById('inputUrlLink');
            const btn = document.getElementById('btnAgregarLink');
            const url = input.value.trim();
            if (!url) {
                Swal.fire('Error', 'Debe ingresar una URL.', 'warning');
                return;
            }
            const formData = new FormData();
            formData.append('idMinuta', idMinutaGlobal);
            formData.append('urlLink', url);
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

            // (CORREGIDO) Usar ruta absoluta
            fetch('/corevota/controllers/agregar_adjunto.php?action=link', {
                    method: 'POST',
                    body: formData
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
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-link me-2"></i>Añadir';
                });
        }

        function cargarYMostrarAdjuntosExistentes() {
            if (!idMinutaGlobal) return;

            fetch(`/corevota/controllers/obtener_adjuntos.php?idMinuta=${idMinutaGlobal}&_cacheBust=${new Date().getTime()}`)
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
            const listaUl = document.getElementById('listaAdjuntosExistentes');
            if (!listaUl) return;
            listaUl.innerHTML = '';
            if (!adjuntos || adjuntos.length === 0) {
                listaUl.innerHTML = '<li class="list-group-item text-muted">No hay adjuntos guardados para esta minuta.</li>';
                return;
            }
            adjuntos.forEach(adj => {
                const li = document.createElement('li');
                li.className = 'list-group-item d-flex justify-content-between align-items-center';
                const link = document.createElement('a');

                // (CORREGIDO) Ruta absoluta para el link
                // Esto asume que la BD guarda "public/docs/DocumentosAdjuntos/..."
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
                if (adj.tipoAdjunto !== 'asistencia') {
                    const deleteBtn = document.createElement('button');
                    deleteBtn.className = 'btn btn-sm btn-outline-danger ms-2';
                    deleteBtn.innerHTML = '<i class="fas fa-trash-alt"></i>';
                    deleteBtn.onclick = () => eliminarAdjunto(adj.idAdjunto, li);
                    li.appendChild(deleteBtn);
                }
                listaUl.appendChild(li);
            });
        }

        function eliminarAdjunto(idAdjunto, listItemElement) {
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
                    // (CORREGIDO) Usar ruta absoluta
                    fetch(`/corevota/controllers/eliminar_adjunto.php?idAdjunto=${idAdjunto}`)
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

        // --- ⭐ ================== INICIO BLOQUE ACCIONES (CORREGIDO) ================== ⭐ ---

        function guardarBorrador(guardarYSalir, callback = null) {
            if (!idMinutaGlobal) {
                alert("Error Crítico: No hay ID de Minuta.");
                if (callback) callback(false);
                return;
            }

            const {
                asistenciaIDs
            } = recolectarAsistencia();
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

            // (CORREGIDO) Usar ruta absoluta
            fetch("/corevota/controllers/guardar_minuta_completa.php", {
                    method: "POST",
                    body: formData
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
                            callback(true); // Flujo "Enviar Aprobación"
                        } else {
                            Swal.fire('Guardado', 'Borrador guardado con éxito.', 'success');
                            if (guardarYSalir) {
                                // ===== INICIO DE MODIFICACIÓN (Redirección 1) =====
                                // (Apunta a menu.php y al listado general del ST con la pestaña correcta)
                                window.location.href = 'menu.php?pagina=minutas_listado_general&tab=borradores';
                                // ===== FIN DE MODIFICACIÓN =====
                            }
                        }
                    } else {
                        throw new Error(resp.message || 'Error al guardar el borrador.');
                    }
                })
                .catch(err => {
                    if (callback) {
                        callback(false); // Flujo "Enviar Aprobación": Guardado falló
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
                    btnEnviar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 1/2 Guardando...';

                    // 1. PRIMERO, guardar el borrador
                    guardarBorrador(false, function(guardadoExitoso) {

                        if (!guardadoExitoso) {
                            Swal.fire('Error al Guardar', 'No se pudo guardar el borrador antes de enviar. Por favor, intente de nuevo.', 'error');
                            if (btnGuardar) btnGuardar.disabled = false;
                            if (btnEnviar) btnEnviar.disabled = false;
                            btnEnviar.innerHTML = '<i class="fas fa-paper-plane"></i> Enviar para Aprobación';
                            return;
                        }

                        // 2. SEGUNDO, llamar a 'enviar_aprobacion.php'
                        btnEnviar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 2/2 Enviando...';

                        // (CORREGIDO) Usar ruta absoluta
                        fetch('/corevota/controllers/enviar_aprobacion.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json'
                                },
                                body: JSON.stringify({
                                    idMinuta: idMinuta
                                })
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
                                        text: data.message, // 'Minuta enviada con éxito...'
                                        icon: 'success'
                                    }).then(() => {
                                        // ===== INICIO DE MODIFICACIÓN (Redirección 2) =====
                                        // (Apunta a la pestaña de pendientes)
                                        window.location.href = 'menu.php?pagina=minutas_listado_general&tab=pendientes_aprobacion';
                                        // ===== FIN DE MODIFICACIÓN =====
                                    });
                                } else {
                                    throw new Error(data.message || 'Error desconocido al enviar.');
                                }
                            })
                            .catch(error => {
                                Swal.fire('Error en el Envío', error.message, 'error');
                                if (btnGuardar) btnGuardar.disabled = false;
                                if (btnEnviar) btnEnviar.disabled = false;
                                btnEnviar.innerHTML = '<i class="fas fa-paper-plane"></i> Enviar para Aprobación';
                            });
                    });
                }
            });
        }

        // --- (NUEVO) Lógica Votaciones ---
        async function guardarNuevaVotacion() {
            const idComision = document.getElementById('votacionComisionId').value;
            const nombreVotacion = document.getElementById('votacionNombre').value.trim();
            const btn = document.querySelector('#formCrearVotacionMinuta button');
            if (!idComision || !nombreVotacion) {
                Swal.fire('Campos incompletos', 'Debe seleccionar una comisión y escribir el texto de la votación.', 'warning');
                return;
            }
            if (!idMinutaGlobal || !ID_REUNION_GLOBAL) {
                Swal.fire('Error de Sistema', 'No se pudo encontrar el ID de la Minuta o Reunión. Recargue la página.', 'error');
                return;
            }
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
            const formData = new FormData();
            formData.append('action', 'create');
            formData.append('idMinuta', idMinutaGlobal);
            formData.append('idReunion', ID_REUNION_GLOBAL);
            formData.append('idComision', idComision);
            formData.append('nombreVotacion', nombreVotacion);
            try {
                // (CORREGIDO) Usar ruta absoluta
                const resp = await fetch('/corevota/controllers/gestionar_votacion_minuta.php', {
                    method: 'POST',
                    body: formData
                });
                if (!resp.ok) {
                    const text = await resp.text();
                    console.error("Error guardar votacion:", text);
                    throw new Error(`Error HTTP ${resp.status} (ver consola)`);
                }
                const data = await resp.json();
                if (data.status === 'success') {
                    Swal.fire('¡Creada!', 'La votación ha sido creada y habilitada. Los usuarios ya pueden votar.', 'success');
                    document.getElementById('votacionNombre').value = '';
                    cargarVotacionesDeLaMinuta();
                    // ===================================
                    // INICIO: Refrescar Votos en Vivo
                    // ===================================
                    cargarResultadosVotacion();
                    // ===================================
                    // FIN: Refrescar Votos en Vivo
                    // ===================================
                } else {
                    Swal.fire('Error', 'No se pudo crear la votación: ' + data.message, 'error');
                }
            } catch (err) {
                console.error("Error fetch guardar votacion:", err);
                Swal.fire('Error de Red', 'No se pudo conectar con el servidor.', 'error');
            }
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-plus me-1"></i> Crear y Habilitar Votación';
        }
        async function cargarVotacionesDeLaMinuta() {
            const cont = document.getElementById('listaVotacionesMinuta');
            const status = document.getElementById('votacionesStatus');
            if (!cont || !status) return;
            cont.innerHTML = '';
            status.textContent = 'Cargando...';
            try {
                // (CORREGIDO) Usar ruta absoluta
                const resp = await fetch(`/corevota/controllers/gestionar_votacion_minuta.php?action=list&idMinuta=${idMinutaGlobal}`);
                if (!resp.ok) throw new Error(`Error HTTP ${resp.status}`);
                const data = await resp.json();
                if (data.status === 'success' && data.data.length > 0) {
                    status.textContent = `Mostrando ${data.data.length} votacion(es).`;
                    let html = '<ul class="list-group">';
                    data.data.forEach(v => {
                        html += `
                             <li class="list-group-item d-flex justify-content-between align-items-center">
                                 <div>
                                     <span class="fw-bold">${v.nombreVotacion}</span>
                                     <small class="d-block text-muted">
                                         Comisión: ${v.nombreComision} | Estado: 
                                         ${v.habilitada == 1 ? '<span class="badge bg-success">Habilitada</span>' : '<span class="badge bg-secondary">Cerrada</span>'}
                                     </small>
                                 </div>
                                 <div>
                                     <button class="btn btn-warning btn-sm" title="Registrar votos manualmente" onclick="abrirModalVoto(${v.idVotacion})">
                                         <i class="fas fa-person-booth"></i> Registrar Voto
                                     </button>
                                 </div>
                             </li>`;
                    });
                    html += '</ul>';
                    cont.innerHTML = html;
                } else {
                    status.textContent = 'No hay votaciones creadas para esta minuta.';
                }
            } catch (err) {
                console.error("Error cargando votaciones:", err);
                status.textContent = 'Error al cargar votaciones.';
                status.className = 'text-danger small';
            }
        }
        async function abrirModalVoto(idVotacion) {
            if (ASISTENCIA_GUARDADA_IDS.length === 0) {
                Swal.fire('Sin Asistentes', 'No hay asistentes marcados como "Presente" en esta minuta. Guarde la asistencia primero.', 'info');
                return;
            }
            Swal.fire({
                title: 'Cargando Estado de Votación...',
                text: 'Buscando asistentes y votos...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            try {
                const formData = new FormData();
                formData.append('action', 'get_status');
                formData.append('idVotacion', idVotacion);
                formData.append('asistentes_ids', JSON.stringify(ASISTENCIA_GUARDADA_IDS));
                // (CORREGIDO) Usar ruta absoluta
                const resp = await fetch('/corevota/controllers/gestionar_votacion_minuta.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await resp.json();
                if (data.status !== 'success') {
                    throw new Error(data.message);
                }

                let modalHtml = '<div class="container-fluid" style="text-align: left;">';
                modalHtml += '<p class="text-muted">Seleccione el voto para cada asistente que no haya votado.</p>';
                modalHtml += '<table class="table table-sm table-hover">';
                modalHtml += '<thead><tr><th>Asistente</th><th class="text-center">Voto Registrado</th><th class="text-center">Registrar Voto</th></tr></thead><tbody>';
                data.data.asistentes.forEach(asistente => {
                    const voto = data.data.votos[asistente.idUsuario] || null;
                    modalHtml += `<tr><td>${asistente.nombreCompleto}</td>`;
                    if (voto) {
                        let badge = 'secondary';
                        if (voto === 'SI') badge = 'success';
                        if (voto === 'NO') badge = 'danger';
                        modalHtml += `<td class="text-center"><span class="badge bg-${badge}">${voto}</span></td>`;
                        modalHtml += `<td></td>`;
                    } else {
                        modalHtml += `<td class="text-center"><span class="badge bg-light text-dark">Pendiente</span></td>`;
                        modalHtml += `<td class="text-center" style="white-space: nowrap;">
                                             <button class="btn btn-success btn-sm" onclick="registrarVotoSecretario(${idVotacion}, ${asistente.idUsuario}, 'SI', this)">SÍ</button>
                                             <button class="btn btn-danger btn-sm mx-1" onclick="registrarVotoSecretario(${idVotacion}, ${asistente.idUsuario}, 'NO', this)">NO</button>
                                             <button class="btn btn-secondary btn-sm" onclick="registrarVotoSecretario(${idVotacion}, ${asistente.idUsuario}, 'ABSTENCION', this)">ABS</button>
                                         </td>`;
                    }
                    modalHtml += '</tr>';
                });
                modalHtml += '</tbody></table></div>';
                Swal.fire({
                    title: 'Registrar Votos Manualmente',
                    html: modalHtml,
                    width: '800px',
                    showConfirmButton: false,
                    showCloseButton: true
                });
            } catch (err) {
                Swal.fire('Error', 'No se pudo cargar el estado de la votación: ' + err.message, 'error');
            }
        }
        async function registrarVotoSecretario(idVotacion, idUsuario, voto, btn) {
            const parentTD = btn.parentNode;
            parentTD.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Registrando...';
            try {
                const formData = new FormData();
                formData.append('action', 'register_vote');
                formData.append('idVotacion', idVotacion);
                formData.append('idUsuario', idUsuario);
                formData.append('voto', voto);
                formData.append('idSecretario', ID_SECRETARIO_LOGUEADO);
                // (CORREGIDO) Usar ruta absoluta
                const resp = await fetch('/corevota/controllers/gestionar_votacion_minuta.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await resp.json();
                if (data.status === 'success') {
                    const statusTD = parentTD.previousElementSibling;
                    let badge = 'secondary';
                    if (voto === 'SI') badge = 'success';
                    if (voto === 'NO') badge = 'danger';
                    statusTD.innerHTML = `<span class="badge bg-${badge}">${voto}</span>`;
                    parentTD.innerHTML = '✅ Registrado';
                    // ===================================
                    // INICIO: Refrescar Votos en Vivo
                    // ===================================
                    cargarResultadosVotacion();
                    // ===================================
                    // FIN: Refrescar Votos en Vivo
                    // ===================================
                } else {
                    throw new Error(data.message);
                }
            } catch (err) {
                parentTD.innerHTML = '<span class="text-danger">Error</span>';
                alert('Error al registrar voto: ' + err.message);
            }
        }
        // --- ⭐ ================== FIN BLOQUE ACCIONES ================== ⭐ ---

        let REGLAS_FEEDBACK = null;

        /**
         * Se ejecuta después de que la página principal (crearMinuta.php)
         * ha cargado todos sus datos iniciales (asistencia, temas, etc.).
         */
        document.addEventListener("DOMContentLoaded", () => {
            // Solo intentamos cargar feedback si la minuta NO está Aprobada
            if (ESTADO_MINUTA_ACTUAL !== 'APROBADA') {
                cargarYAplicarFeedback();
            }
        });

        /**
         * Llama al controlador para ver si existe feedback para esta minuta.
         */
        async function cargarYAplicarFeedback() {
            console.log("Buscando feedback para Minuta ID:", idMinutaGlobal);
            try {
                const response = await fetch(`/corevota/controllers/obtener_feedback_json.php?idMinuta=${idMinutaGlobal}`);
                if (!response.ok) {
                    throw new Error("No se pudo conectar al script de feedback.");
                }

                const data = await response.json();

                if (data.status === 'success' && data.data) {
                    console.log("Feedback encontrado:", data.data);
                    REGLAS_FEEDBACK = data.data; // Guardamos las reglas globalmente

                    // --- ¡NUEVO! MOSTRAR EL TEXTO DEL FEEDBACK ---
                    if (data.textoFeedback) {
                        const container = document.getElementById('feedback-display-container');
                        const textoDiv = document.getElementById('feedback-display-texto');
                        if (container && textoDiv) {
                            textoDiv.textContent = data.textoFeedback;
                            container.style.display = 'block';
                        }
                    }
                    // --- FIN NUEVO ---

                    // Si hay feedback, aplicamos las reglas
                    deshabilitarCamposSegunFeedback(REGLAS_FEEDBACK);
                    actualizarBotonesParaFeedback();

                } else if (data.status === 'no_feedback') {
                    console.log("No hay feedback pendiente para esta minuta.");
                    // No hacemos nada, la minuta se queda 100% editable
                } else {
                    throw new Error(data.message || "Error desconocido al cargar feedback.");
                }
            } catch (error) {
                console.error("Error al cargar feedback:", error);
                // No bloqueamos la UI, solo mostramos un aviso sutil
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

        /**
         * Deshabilita las secciones del formulario según las reglas de feedback.
         */
        function deshabilitarCamposSegunFeedback(reglas) {

            // Regla para Asistencia
            if (reglas.asistencia === false) {
                deshabilitarSeccion('asistenciaForm', 'Asistencia bloqueada (sin feedback)');
            }

            // Regla para Votaciones
            if (reglas.votaciones === false) {
                deshabilitarSeccion('votacionForm', 'Votaciones bloqueadas (sin feedback)');
            }

            // Regla para Adjuntos
            if (reglas.adjuntos === false) {
                deshabilitarSeccion('adjuntos-section', 'Adjuntos bloqueados (sin feedback)');
            }

            // Regla para Temas
            // Si 'temas' es falso Y 'otro' es falso, bloqueamos los temas.
            if (reglas.temas === false && reglas.otro === false) {
                deshabilitarSeccion('contenedorTemas', 'Temas bloqueados (sin feedback)');
                const btnAgregarTema = document.querySelector('button[onclick="agregarTema()"]');
                if (btnAgregarTema) {
                    btnAgregarTema.disabled = true;
                    btnAgregarTema.title = 'Bloqueado (sin feedback)';
                }
            }
        }

        /**
         * Función helper para deshabilitar visualmente una sección.
         */
        function deshabilitarSeccion(idElemento, mensaje) {
            // Busca el botón que controla el colapso
            const btnCollapse = document.querySelector(`button[data-bs-target="#${idElemento}"]`);
            if (btnCollapse) {
                btnCollapse.disabled = true;
                btnCollapse.title = mensaje;
                btnCollapse.classList.add('opacity-50');
            }

            // Busca el contenedor de la sección
            const seccion = document.getElementById(idElemento);
            if (seccion) {
                // Deshabilita todos los inputs, selects, textareas y botones dentro
                seccion.querySelectorAll('input, select, textarea, button, .editable-area').forEach(el => {
                    el.disabled = true;
                    el.contentEditable = false;
                    el.style.cursor = 'not-allowed';
                    el.style.backgroundColor = '#e9ecef'; // Color de fondo "disabled"
                });

                // Oculta las barras de herramientas de los editores
                seccion.querySelectorAll('.bb-editor-toolbar').forEach(toolbar => {
                    toolbar.style.display = 'none';
                });

                // Si es un colapso, lo cerramos
                if (seccion.classList.contains('collapse')) {
                    const bsCollapse = bootstrap.Collapse.getInstance(seccion);
                    if (bsCollapse) {
                        bsCollapse.hide();
                    }
                }
            }

            // Caso especial para adjuntos (que no es un colapso)
            if (idElemento === 'adjuntos-section') {
                const seccionAdjuntos = document.querySelector('.adjuntos-section');
                if (seccionAdjuntos) {
                    seccionAdjuntos.style.opacity = '0.6';
                    seccionAdjuntos.style.pointerEvents = 'none';
                    seccionAdjuntos.title = mensaje;
                }
            }

            // Caso especial para temas (que no es un colapso)
            if (idElemento === 'contenedorTemas') {
                const seccionTemas = document.getElementById('contenedorTemas');
                if (seccionTemas) {
                    seccionTemas.style.opacity = '0.6';
                    seccionTemas.style.pointerEvents = 'none';
                    seccionTemas.title = mensaje;
                }
            }
        }

        /**
         * Cambia los botones de acción al final de la página.
         */
        function actualizarBotonesParaFeedback() {
            const btnGuardar = document.getElementById('btnGuardarBorrador');
            const btnEnviar = document.getElementById('btnEnviarAprobacion');

            // 1. Modificar el botón de "Guardar"
            if (btnGuardar) {
                btnGuardar.innerHTML = '<i class="fas fa-save"></i> Guardar Correcciones';
                // Mantenemos el onclick="guardarBorrador(true)"
            }

            // 2. Modificar el botón de "Enviar"
            if (btnEnviar) {
                btnEnviar.innerHTML = '<i class="fas fa-check-double"></i> Aplicar y Reenviar p/ Aprobación';
                btnEnviar.classList.remove('btn-danger');
                btnEnviar.classList.add('btn-success'); // Cambiamos a color verde

                // ¡IMPORTANTE! Cambiamos la función que llama
                btnEnviar.setAttribute('onclick', 'if (validarCamposMinuta()) confirmarAplicarFeedback()');
            }
        }

        /**
         * Nueva función de confirmación que llama a aplicar_feedback.php
         * (Esta se activa SOLO si estábamos en modo feedback)
         */
        function confirmarAplicarFeedback() {
            // 1. Primero, guardamos los cambios en el borrador
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

                // 2. Si el guardado fue exitoso, llamamos a aplicar_feedback.php
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

        /**
         * Función final que llama al controlador aplicar_feedback.php
         */
        function llamarAplicarFeedback() {
            const btnEnviar = document.getElementById('btnEnviarAprobacion');
            if (btnEnviar) {
                btnEnviar.disabled = true;
                btnEnviar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Reenviando...';
            }

            const formData = new FormData();
            formData.append('idMinuta', idMinutaGlobal);

            // (CORREGIDO) Usar ruta absoluta
            fetch('/corevota/controllers/aplicar_feedback.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        Swal.fire({
                            title: '¡Minuta Reenviada!',
                            text: data.message, // "Minuta corregida y reenviada..."
                            icon: 'success'
                        }).then(() => {
                            // ===== INICIO DE MODIFICACIÓN (Redirección 3) =====
                            window.location.href = 'menu.php?pagina=minutas_listado_general&tab=pendientes_aprobacion';
                            // ===== FIN DE MODIFICACIÓN =====
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
            const bloques = document.querySelectorAll('#contenedorTemas .tema-block');
            let valido = true;

            bloques.forEach((bloque, index) => {
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

                    // Abrir el bloque del tema y enfocar el campo vacío
                    const collapse = bloque.querySelector('.collapse');
                    if (collapse && collapse.classList.contains('collapse')) {
                        const bsCollapse = new bootstrap.Collapse(collapse, {
                            show: true
                        });
                    }

                    (tema ? objetivoEl : temaEl)?.focus();
                    return false; // sale del bucle en el primer error
                }
            });

            return valido;
        }

        // ==================================================================
        // --- INICIO: FUNCIONES JS PARA VOTACIÓN EN VIVO (Sin cambios) ---
        // ==================================================================

        /**
         * Llama a la API y renderiza los resultados de la votación
         */
        function cargarResultadosVotacion() {
            const container = document.getElementById('votacion-resultados-live');
            const placeholder = document.getElementById('votacion-placeholder');
            const refreshButton = document.getElementById('btn-refrescar-votaciones');

            if (!container || !idMinutaGlobal) {
                console.error("No se pudo encontrar el contenedor o el idMinuta.");
                if (container) container.innerHTML = '<p class="text-danger text-center">Error: No se pudo cargar el ID de la minuta.</p>';
                return;
            }

            // Poner en modo "cargando"
            if (refreshButton) refreshButton.disabled = true;
            if (placeholder) placeholder.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Cargando resultados...';

            // Llamar a la nueva API (usando la ruta absoluta)
            fetch(`/corevota/controllers/obtener_resultados_votacion.php?idMinuta=${idMinutaGlobal}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Error de red al cargar votaciones.');
                    }
                    return response.json();
                })
                .then(data => {
                    if (refreshButton) refreshButton.disabled = false;

                    if (data.status !== 'success') {
                        throw new Error(data.message || 'Error en la respuesta del servidor.');
                    }

                    if (data.data.length === 0) {
                        container.innerHTML = '<p class="text-muted text-center" id="votacion-placeholder">No hay votaciones asociadas a esta minuta/reunión todavía.</p>';
                        return;
                    }

                    // Limpiar contenedor
                    container.innerHTML = '';

                    // Construir el HTML (similar al PDF)
                    data.data.forEach(votacion => {
                        const votosSi = [];
                        const votosNo = [];
                        const votosAbs = [];

                        if (votacion.votos) {
                            votacion.votos.forEach(voto => {
                                const nombre = escapeHTML(voto.nombreVotante);
                                if (voto.opcionVoto === 'SI') {
                                    votosSi.push(nombre);
                                } else if (voto.opcionVoto === 'NO') {
                                    votosNo.push(nombre);
                                } else {
                                    votosAbs.push(nombre);
                                }
                            });
                        }

                        const votacionHtml = `
                            <div class="votacion-block-ui mb-4">
                                <h5 class="fw-bold border-bottom pb-2">${escapeHTML(votacion.nombreVotacion)}</h5>
                                <table class="table table-sm table-bordered table-striped" style="font-size: 0.9rem;">
                                    <thead class="table-light text-center">
                                        <tr>
                                            <th style="width: 33.3%;">A Favor (${votosSi.length})</th>
                                            <th style="width: 33.3%;">En Contra (${votosNo.length})</th>
                                            <th style="width: 33.3%;">Abstención (${votosAbs.length})</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>${votosSi.length > 0 ? '<ul><li>' + votosSi.join('</li><li>') + '</li></ul>' : '<em class="text-muted ps-2">Sin votos</em>'}</td>
                                            <td>${votosNo.length > 0 ? '<ul><li>' + votosNo.join('</li><li>') + '</li></ul>' : '<em class="text-muted ps-2">Sin votos</em>'}</td>
                                            <td>${votosAbs.length > 0 ? '<ul><li>' + votosAbs.join('</li><li>') + '</li></ul>' : '<em class="text-muted ps-2">Sin votos</em>'}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        `;
                        container.innerHTML += votacionHtml;
                    });
                })
                .catch(error => {
                    if (refreshButton) refreshButton.disabled = false;
                    container.innerHTML = `<p class="text-danger text-center" id="votacion-placeholder"><strong>Error:</strong> ${error.message}</p>`;
                    console.error('Error al cargar votaciones:', error);
                });
        }

        /**
         * Helper para evitar inyección XSS
         */
        function escapeHTML(str) {
            if (!str) return '';
            return str.replace(/[&<>"']/g, function(m) {
                return {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#39;'
                } [m];
            });
        }
        // ==================================================================
        // --- FIN: FUNCIONES JS PARA VOTACIÓN EN VIVO ---
        // ==================================================================
    </script>
</body>

</html>