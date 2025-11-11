<?php
// views/pages/crearMinuta.php

// ===============================================
// 1. INICIALIZACIÓN Y CONFIGURACIÓN
// ===============================================

// Iniciar sesión si no está activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Requerir la clase de conexión a la base de datos
require_once __DIR__ . '/../../class/class.conectorDB.php';
// (Tuve que comentar esto, asegúrate que la ruta es correcta si lo necesitas)
// require_once __DIR__ . '/../../controllers/VotacionController.php'; 

$db = new conectorDB();
$pdo = $db->getDatabase();

// Obtener ID de la minuta (si existe) y datos iniciales
$idMinutaActual = $_GET['id'] ?? null;
$minutaData     = null;
$reunionData    = null;
$temas_de_la_minuta = [];
$asistencia_guardada_ids = []; // Contiene los IDs de los usuarios presentes
$secretarioNombre = trim(($_SESSION['pNombre'] ?? '') . ' ' . ($_SESSION['aPaterno'] ?? 'N/A'));
$idTipoUsuario  = $_SESSION['tipoUsuario_id'] ?? null; // ID de tipo de usuario para permisos

// Variables de estado y control
$estadoMinuta   = 'BORRADOR'; // Default para nuevas
$puedeEnviar   = false;      // Permite enviar a aprobación si no está APROBADA

// --- Variables para encabezado y votaciones ---
$idReunionActual = null;
$comisionesDeLaReunion = [];
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


// ===============================================
// 2. LÓGICA DE CARGA DE DATOS (EDICIÓN)
// ===============================================

if ($idMinutaActual && is_numeric($idMinutaActual)) {
    try {
        // 1. Cargar datos de t_minuta
        $sql_minuta = "SELECT t_comision_idComision, t_usuario_idPresidente, estadoMinuta, fechaMinuta, horaMinuta, asistencia_validada 
                       FROM t_minuta 
                       WHERE idMinuta = :idMinutaActual";
        $stmt_minuta = $pdo->prepare($sql_minuta);
        $stmt_minuta->execute([':idMinutaActual' => $idMinutaActual]);
        $minutaData = $stmt_minuta->fetch(PDO::FETCH_ASSOC);
        $asistenciaValidada = $minutaData['asistencia_validada'] ?? 0;

        if (!$minutaData) {
            throw new Exception("Minuta con ID $idMinutaActual no encontrada.");
        }

        $estadoMinuta = $minutaData['estadoMinuta']; // Guardar el estado

        // 2. Cargar datos de t_reunion (para comisiones mixtas)
        $sql_reunion = "SELECT idReunion, t_comision_idComision, t_comision_idComision_mixta, t_comision_idComision_mixta2 
                        FROM t_reunion 
                        WHERE t_minuta_idMinuta = :idMinutaActual";
        $stmt_reunion = $pdo->prepare($sql_reunion);
        $stmt_reunion->execute([':idMinutaActual' => $idMinutaActual]);
        $reunionData = $stmt_reunion->fetch(PDO::FETCH_ASSOC);
        $idReunionActual = $reunionData['idReunion'] ?? null;

        // 3. Cargar listados maestros (Comisiones y Presidentes)
        // Comisiones Vigentes
        $stmt_all_com = $pdo->query("SELECT idComision, nombreComision, t_usuario_idPresidente FROM t_comision WHERE vigencia = 1");
        if ($stmt_all_com) {
            $all_commissions_raw = $stmt_all_com->fetchAll(PDO::FETCH_ASSOC);
            foreach ($all_commissions_raw as $com) {
                $all_commissions[$com['idComision']] = $com;
            }
        }
        // Presidentes (tipoUsuario_id = 3)
        $stmt_all_pres = $pdo->query("SELECT idUsuario, pNombre, aPaterno FROM t_usuario WHERE tipoUsuario_id = 3");
        if ($stmt_all_pres) {
            $all_presidents_raw = $stmt_all_pres->fetchAll(PDO::FETCH_ASSOC);
            foreach ($all_presidents_raw as $pres) {
                $all_presidents[$pres['idUsuario']] = trim($pres['pNombre'] . ' ' . $pres['aPaterno']);
            }
        }

        // 4. ASIGNAR NOMBRES PARA EL ENCABEZADO
        $idComisionPrincipal = $minutaData['t_comision_idComision'];
        $idPresidentePrincipal = $minutaData['t_usuario_idPresidente'];
        $nombreComisionPrincipal = $all_commissions[$idComisionPrincipal]['nombreComision'] ?? 'Comisión No Encontrada/Inválida';
        $nombrePresidentePrincipal = $all_presidents[$idPresidentePrincipal] ?? 'Presidente No Encontrado/Inválido';

        // Llenar listado de comisiones de la reunión (para votaciones)
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

        // 5. Cargar temas
        $sql_temas = "SELECT t.idTema, t.nombreTema, t.objetivo, t.compromiso, t.observacion, a.descAcuerdo
                      FROM t_tema t LEFT JOIN t_acuerdo a ON a.t_tema_idTema = t.idTema
                      WHERE t.t_minuta_idMinuta = :idMinutaActual ORDER BY t.idTema ASC";
        $stmt_temas = $pdo->prepare($sql_temas);
        $stmt_temas->execute([':idMinutaActual' => $idMinutaActual]);
        $temas_de_la_minuta = $stmt_temas->fetchAll(PDO::FETCH_ASSOC);

        // 6. Cargar asistencia (SOLO para la carga inicial en JS)
        $sql_asistencia = "SELECT t_usuario_idUsuario FROM t_asistencia WHERE t_minuta_idMinuta = :idMinutaActual";
        $stmt_asistencia = $pdo->prepare($sql_asistencia);
        $stmt_asistencia->execute([':idMinutaActual' => $idMinutaActual]);
        $asistencia_guardada_ids = $stmt_asistencia->fetchAll(PDO::FETCH_COLUMN, 0);
    } catch (Exception $e) {
        error_log("Error cargando datos para edición (Minuta ID: {$idMinutaActual}): " . $e->getMessage());
        die("❌ Error al cargar los datos de la minuta: " . htmlspecialchars($e->getMessage()) . "<br><a href='menu.php?pagina=minutas_pendientes'>Volver al listado</a>");
    } finally {
        $pdo = null; // Cerrar conexión
    }
} else {
    // Si no hay ID o el ID es inválido, salimos (Esto debería ser manejado antes, pero es un buen guardrail)
    die("❌ Error: No se especificó un ID de minuta válido para editar. <a href='menu.php?pagina=minutas_pendientes'>Volver al listado</a>");
}

// ===============================================
// 3. LÓGICA DE PERMISOS Y ESTADO
// ===============================================

// El botón "Enviar para Aprobación" solo está habilitado si el estado NO es APROBADA
$puedeEnviar = ($estadoMinuta !== 'APROBADA');

// Lógica de Permisos (Habilitar edición al ST, bloquear si está APROBADA)
$esSecretarioTecnico = ($idTipoUsuario === 2);
$esSoloLectura = true; // Valor por defecto

if ($esSecretarioTecnico && $estadoMinuta !== 'APROBADA') {
    // REGLA 1: Si es ST Y la minuta no está APROBADA, SIEMPRE puede editar.
    $esSoloLectura = false;
} elseif ($estadoMinuta === 'APROBADA') {
    // REGLA 2: Si está APROBADA, nadie edita.
    $esSoloLectura = true;
} else {
    // Para Presidentes, u otros estados, se mantiene solo lectura.
    $esSoloLectura = true;
}

// Atributo readonly o vacío para usar en el HTML/PHP
$readonlyAttr = $esSoloLectura ? 'readonly' : '';


// ===============================================
// 4. INICIO DEL HTML
// ===============================================
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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        /* Estilos CSS (Sin cambios, pero bien ubicados) */
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
                                <button type="button" class="btn btn-info btn-sm" onclick="guardarAsistencia()" <?php echo $esSoloLectura ? 'disabled title="La minuta no es editable en el estado actual."' : ''; ?>>
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
                        <label for="inputArchivo" class="form-label">Añadir nuevo archivo (PDF, JPG, PNG, XLSX, MP4, PPT, DOCX) <span id="file-upload-status" class="badge bg-light text-dark"></span></label>
                        <div class="input-group">
                            <input type="file" class="form-control" id="inputArchivo" name="archivo" required accept=".pdf,.jpg,.jpeg,.png,.xlsx,.mp4,.ppt,.pptx,.doc,.docx">
                        </div>
                    </form>

                    <form id="formAgregarLink" class="mb-3">
                        <label for="inputUrlLink" class="form-label">Añadir nuevo enlace (Presione Enter o haga clic fuera para añadir)</label>
                        <div class="input-group">
                            <input type="url" class="form-control" id="inputUrlLink" name="urlLink" placeholder="https://ejemplo.com" required>
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

                        <?php
                        // --- INICIO DE LA LÓGICA DE BOTONES MEJORADA ---

                        // 1. Botón Guardar Borrador (Visible si es ST y la minuta NO está Aprobada)
                        if ($esSecretarioTecnico && $estadoMinuta !== 'APROBADA') {
                            echo '<button type="button" class="btn btn-success fw-bold" id="btnGuardarBorrador"
                        onclick="if (validarCamposMinuta()) guardarBorrador(true);">
                    <i class="fas fa-save"></i> Guardar Borrador
                </button>';
                        }

                        // 2. Botón Validar Asistencia (Visible si es ST, la asistencia AÚN NO está validada, y la minuta NO está Aprobada)
                        if ($esSecretarioTecnico && $asistenciaValidada == 0 && $estadoMinuta !== 'APROBADA') {
                            // Quitamos data-bs-toggle y data-bs-target, y añadimos onclick=
                            echo '<button type="button" class="btn btn-info fw-bold ms-3" id="btnRevisarAsistencia"
                onclick="iniciarValidacionAsistencia()">
            <i class="fas fa-users-check"></i> Revisar y Validar Asistencia
        </button>';
                        }

                        // 3. Botón Enviar/Re-enviar (Visible si es ST, la asistencia SÍ está validada, y la minuta NO está Aprobada)
                        // Tu JS existente (línea 939) se encargará de cambiar el texto a "Aplicar y Reenviar" si el estado es REQUIERE_REVISION
                        if ($esSecretarioTecnico && $asistenciaValidada == 1 && $estadoMinuta !== 'APROBADA') {
                            echo '<button type="button" class="btn btn-danger fw-bold ms-3" id="btnEnviarAprobacion"
                        onclick="if (validarCamposMinuta()) confirmarEnvioAprobacion();">
                    <i class="fas fa-paper-plane"></i> Enviar para Aprobación
                </button>';
                        }

                        // Mensaje si ya está Aprobada
                        if ($estadoMinuta === 'APROBADA') {
                            echo '<small class="d-block text-success mt-2">Esta minuta ya fue APROBADA y no puede modificarse.</small>';
                        }

                        // Mensaje si falta validar asistencia
                        if ($esSecretarioTecnico && $asistenciaValidada == 0 && $estadoMinuta !== 'APROBADA') {
                            echo '<small class="d-block text-warning mt-2">Debe "Revisar y Validar Asistencia" para poder enviar a aprobación.</small>';
                        }

                        // --- FIN DE LA LÓGICA DE BOTONES ---
                        ?>

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
                        <div class="bb-editor-toolbar no-select mb-2" role="toolbar"> <button type="button" class="btn btn-sm btn-light border me-1" onclick="format('bold')"><b>B</b></button> <button type="button" class="btn btn-sm btn-light border me-1" onclick="format('italic')"><i>I</i></i< /button> <button type="button" class="btn btn-sm btn-light border me-1" onclick="format('underline')"><u>U</u></button> </div>
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


    <div class="modal fade" id="modalValidarAsistencia" tabindex="-1" aria-labelledby="modalValidarAsistenciaLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalValidarAsistenciaLabel">Validar Asistencia de Minuta</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="contenidoModalAsistencia">
                    <p>Cargando asistencia...</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-warning" id="btnModificarAsistencia">
                        <i class="fas fa-edit"></i> Modificar Asistencia
                    </button>
                    <button type="button" class="btn btn-success" id="btnConfirmarEnviarAsistencia">
                        <i class="fas fa-check"></i> Confirmar y Enviar Correo
                    </button>
                </div>
            </div>
        </div>
    </div>
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
        const ES_ST_EDITABLE = <?php echo json_encode(!$esSoloLectura); ?>; // Nuevo control de edición ST/APROBADA
        let REGLAS_FEEDBACK = null; // Para control de Feedback

        // Elementos de UI
        const formSubirArchivo = document.getElementById('formSubirArchivo');
        const inputArchivo = document.getElementById('inputArchivo');
        const formAgregarLink = document.getElementById('formAgregarLink');
        const inputUrlLink = document.getElementById('inputUrlLink');
        const fileStatus = document.getElementById('file-upload-status');

        // ==================================================================
        // --- EVENTOS PRINCIPALES ---
        // ==================================================================
        document.addEventListener("DOMContentLoaded", () => {
            cargarTablaAsistencia();
            cargarOPrepararTemas();
            cargarYMostrarAdjuntosExistentes();
            cargarVotacionesDeLaMinuta();
            cargarResultadosVotacion();
            // Solo intentar cargar feedback si la minuta NO está Aprobada
            if (ESTADO_MINUTA_ACTUAL !== 'APROBADA') {
                cargarYAplicarFeedback();
            }

            // Eventos automáticos para archivos y enlaces
            if (inputArchivo) {
                // Al seleccionar un archivo, se dispara la subida
                inputArchivo.addEventListener('change', function(e) {
                    if (this.files.length > 0) {
                        formSubirArchivo.dispatchEvent(new Event('submit', {
                            cancelable: true
                        }));
                    }
                });
            }
            if (inputUrlLink) {
                // Al perder el foco o presionar Enter, se dispara la adición de link
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

            // Bind de los submits a las funciones (por si no se disparan por change/focus)
            document.getElementById('formSubirArchivo').addEventListener('submit', handleSubirArchivo);
            document.getElementById('formAgregarLink').addEventListener('submit', handleAgregarLink);
        });


        // --- INICIO: JAVASCRIPT PARA EL MODAL DE VALIDACIÓN (CORREGIDO v2) ---
        document.addEventListener('DOMContentLoaded', (event) => {

            const modalValidarAsistencia = document.getElementById('modalValidarAsistencia');
            if (modalValidarAsistencia) {

                // 1. INICIALIZAR LA INSTANCIA DEL MODAL en nuestra variable global
                bsModalValidarAsistencia = new bootstrap.Modal(modalValidarAsistencia);

                // 2. Cargar el PREVIEW cuando el modal se muestra
                modalValidarAsistencia.addEventListener('show.bs.modal', function(event) {

                    if (!idMinutaGlobal) {
                        console.error("idMinutaGlobal no está definido.");
                        return;
                    }

                    const modalTitle = modalValidarAsistencia.querySelector('.modal-title');
                    const modalBody = modalValidarAsistencia.querySelector('#contenidoModalAsistencia');

                    modalTitle.textContent = 'Validar Asistencia de Minuta N° ' + idMinutaGlobal;
                    modalBody.innerHTML = '<p>Cargando...</p>';

                    fetch(`/COREVOTA/controllers/obtener_preview_asistencia.php?idMinuta=${encodeURIComponent(idMinutaGlobal)}`, {
                            method: 'GET'
                        })
                        .then(response => response.json())
                        .then(data => {
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
                                throw new Error(data.message || 'No se pudo cargar la asistencia.');
                            }
                        })
                        .catch(err => {
                            modalBody.innerHTML = `<p class="text-danger">Error al cargar la asistencia: ${err.message}</p>`;
                            console.error("Error fetch preview asistencia:", err);
                        });
                });

                // 3. Acción del botón "Modificar Asistencia" (CORREGIDO)
                const btnModificar = document.getElementById('btnModificarAsistencia');
                if (btnModificar) {
                    btnModificar.addEventListener('click', function() {
                        // 1. Ocultar el modal
                        // (Usamos la instancia global 'bsModalValidarAsistencia' que definimos al inicio)
                        if (bsModalValidarAsistencia) {
                            bsModalValidarAsistencia.hide();
                        }

                        // 2. Encontrar el botón del acordeón de Asistencia
                        const accordionButton = document.querySelector('button[data-bs-target="#asistenciaForm"]');
                        // 3. Encontrar el contenido del acordeón
                        const accordionContent = document.getElementById('asistenciaForm');

                        if (accordionContent && accordionButton) {
                            // 4. Crear una instancia de Bootstrap Collapse y mostrarla
                            // (Esto asegura que el acordeón se abra)
                            const collapseInstance = bootstrap.Collapse.getOrCreateInstance(accordionContent);
                            collapseInstance.show();

                            // 5. (Opcional) Hacer scroll hasta el acordeón para que el ST lo vea
                            accordionButton.scrollIntoView({
                                behavior: 'smooth',
                                block: 'center'
                            });
                        } else {
                            console.error('No se pudo encontrar el acordeón de asistencia #asistenciaForm');
                        }
                    });
                }

                // 4. Acción del botón "Confirmar y Enviar Correo" (Sin cambios)
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

                        fetch('/COREVOTA/controllers/enviar_asistencia_validada.php', {
                                method: 'POST',
                                body: formData
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
            }
        });


        function iniciarValidacionAsistencia() {
            const btn = document.getElementById('btnRevisarAsistencia');
            if (!btn) return;

            // 1. Validar campos (como el Guardar Borrador)
            // (Esta función ya existe en tu script, línea 973)
            if (!validarCamposMinuta()) {
                return; // Detiene si falta un tema/objetivo
            }

            // 2. Mostrar estado de carga
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando cambios...';

            // 3. Llamar a guardarBorrador (que genera el PDF)
            // (Esta función ya existe en tu script, línea 796)
            guardarBorrador(false, function(guardadoExitoso) {

                // 4. Resetear el botón
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-users-check"></i> Revisar y Validar Asistencia';

                if (guardadoExitoso) {
                    // 5. Si se guardó (y generó el PDF), ABRIR EL MODAL
                    // (Usamos la instancia global que inicializamos en el DOMContentLoaded)
                    if (bsModalValidarAsistencia) {
                        bsModalValidarAsistencia.show();
                    } else {
                        Swal.fire('Error JS', 'No se pudo instanciar el modal de validación.', 'error');
                    }
                } else {
                    // 6. Si falló el guardado, mostrar error
                    Swal.fire('Error al Guardar', 'No se pudieron guardar los cambios. El PDF de asistencia no se pudo generar.', 'error');
                }
            });
        }
        // --- FIN: JAVASCRIPT MODAL ---











        // ==================================================================
        // --- SECCIÓN: ASISTENCIA (CORREGIDA/REFACTORIZADA) ---
        // ==================================================================

        /**
         * Carga la lista maestra de consejeros y la asistencia guardada.
         */
        function cargarTablaAsistencia() {
            const cont = document.getElementById("contenedorTablaAsistenciaEstado");
            const btnRefresh = document.getElementById("btn-refrescar-asistencia");
            if (btnRefresh) btnRefresh.disabled = true;

            cont.innerHTML = '<p class="text-muted"><i class="fas fa-spinner fa-spin me-2"></i>Cargando lista de consejeros y asistencia...</p>';

            // 1. Cargar la lista MAESTRA de consejeros
            const fetchConsejeros = fetch("/corevota/controllers/fetch_data.php?action=asistencia_all")
                .then(res => res.ok ? res.json() : Promise.reject(new Error('Error fetch_data.php')));

            // 2. Cargar la lista ACTUAL de asistencia
            const fetchAsistenciaActual = fetch(`/corevota/controllers/obtener_asistencia_actual.php?idMinuta=${idMinutaGlobal}`)
                .then(res => res.ok ? res.json() : Promise.reject(new Error('Error obtener_asistencia_actual.php')));

            // 3. Esperar ambas y renderizar
            Promise.all([fetchConsejeros, fetchAsistenciaActual])
                .then(([responseConsejeros, responseAsistencia]) => {

                    if (responseConsejeros.status !== 'success' || responseAsistencia.status !== 'success') {
                        throw new Error('No se pudo cargar la información necesaria.');
                    }

                    const data = responseConsejeros.data; // Lista de usuarios
                    // ¡ACTUALIZAMOS LA VARIABLE GLOBAL!
                    ASISTENCIA_GUARDADA_IDS = responseAsistencia.data.map(String); // Array de IDs como strings ['15', '37', '40']

                    // Determinar si los checkboxes deben estar deshabilitados
                    const disabledAttr = ES_ST_EDITABLE ? '' : 'disabled';
                    const titleAttr = ES_ST_EDITABLE ? '' : 'title="Edición bloqueada por el estado de la minuta o su rol."';

                    if (data && data.length > 0) {
                        let tabla = `<table class="table table-sm table-hover" id="tablaAsistenciaEstado"><thead><tr><th style="text-align: left;">Nombre Consejero</th><th style="width: 100px;">Presente</th><th style="width: 100px;">Ausente</th></tr></thead><tbody>`;
                        data.forEach(c => {
                            const userIdString = String(c.idUsuario);
                            const isPresent = ASISTENCIA_GUARDADA_IDS.includes(userIdString);
                            const isAbsent = !isPresent;

                            // Aplicar el atributo disabled/title a los checkboxes
                            tabla += `<tr data-userid="${c.idUsuario}">
                                <td style="text-align: left;"><label class="form-check-label w-100" for="present_${userIdString}">${c.nombreCompleto}</label></td>
                                <td><input class="form-check-input asistencia-checkbox present-check" type="checkbox" id="present_${userIdString}" value="${userIdString}" onchange="handleAsistenciaChange('${userIdString}', 'present')" ${isPresent ? 'checked' : ''} ${disabledAttr} ${titleAttr}></td>
                                <td><input class="form-check-input asistencia-checkbox absent-check default-absent" type="checkbox" id="absent_${userIdString}" onchange="handleAsistenciaChange('${userIdString}', 'absent')" ${isAbsent ? 'checked' : ''} ${disabledAttr} ${titleAttr}></td>
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
                    cont.innerHTML = `<p class="text-danger">Error al refrescar asistencia: ${err.message}</p>`;
                })
                .finally(() => {
                    if (btnRefresh) btnRefresh.disabled = false;
                    // Llamar a la lógica de feedback por si estaba en REQUERIDO_REVISION y debe bloquear la sección
                    if (REGLAS_FEEDBACK) deshabilitarCamposSegunFeedback(REGLAS_FEEDBACK);
                });
        }

        /**
         * Maneja el cambio de estado de un checkbox (presente/ausente).
         */
        function handleAsistenciaChange(userId, changedType) {
            const present = document.getElementById(`present_${userId}`);
            const absent = document.getElementById(`absent_${userId}`);
            if (changedType === 'present') {
                absent.checked = !present.checked;
            } else if (changedType === 'absent') {
                present.checked = !absent.checked;
            }
        }

        /**
         * Recolecta los IDs de los usuarios marcados como 'Presente'.
         * @returns {Array<string>} Array de IDs de usuario.
         */
        function recolectarAsistencia() {
            const ids = [];
            const presentes = document.querySelectorAll("#tablaAsistenciaEstado .present-check:checked");
            presentes.forEach(chk => ids.push(chk.value));
            return ids;
        }

        /**
         * Envía los IDs de asistencia recolectados al servidor.
         */
        function guardarAsistencia() {
            const asistenciaIDs = recolectarAsistencia();

            if (!ES_ST_EDITABLE) {
                Swal.fire('Prohibido', 'No puede guardar la asistencia en este estado.', 'error');
                return;
            }

            const status = document.getElementById('guardarAsistenciaStatus');
            const btn = document.querySelector('#botonesAsistenciaContainer button[onclick="guardarAsistencia()"]');

            // --- INICIO DE LA CORRECCIÓN ---

            // 1. Crear un objeto FormData
            const formData = new FormData();

            // 2. Agregar los datos que el PHP espera en $_POST
            formData.append('idMinuta', idMinutaGlobal);

            // 3. Convertir el array de asistencia en un *string* JSON.
            // (Tu otro script, guardar_minuta_completa.php, espera un string,
            // así que es casi seguro que guardar_asistencia.php también lo espera)
            formData.append('asistencia', JSON.stringify(asistenciaIDs));

            // --- FIN DE LA CORRECCIÓN ---

            btn.disabled = true;
            status.textContent = 'Guardando...';
            status.className = 'me-auto small text-muted';

            fetch("/corevota/controllers/guardar_asistencia.php", {
                    method: "POST",
                    // 4. NO enviar 'headers', el navegador lo hace solo
                    body: formData // 5. Enviar el objeto FormData
                })
                .then(res => res.ok ? res.json() : res.text().then(text => Promise.reject(new Error("Respuesta servidor inválida: " + text))))
                .then(resp => {
                    btn.disabled = false;
                    if (resp.status === "success") {
                        status.textContent = "✅ Guardado";
                        status.className = 'me-auto small text-success fw-bold';

                        // Actualizar la lista global de IDs y la tabla visual
                        cargarTablaAsistencia();

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
        // --- SECCIÓN: EDICIÓN DE TEMAS ---
        // ==================================================================

        /**
         * Aplica un comando de formato al contenido editable.
         */
        function format(command) {
            try {
                document.execCommand(command, false, null);
            } catch (e) {
                console.error("Format command failed:", e);
            }
        }

        /**
         * Carga los temas existentes o prepara un bloque vacío.
         */
        function cargarOPrepararTemas() {
            if (DATOS_TEMAS_CARGADOS && DATOS_TEMAS_CARGADOS.length > 0) {
                DATOS_TEMAS_CARGADOS.forEach(t => crearBloqueTema(t));
            } else {
                crearBloqueTema();
            }
        }

        /**
         * Agrega un nuevo tema vacío.
         */
        function agregarTema() {
            crearBloqueTema();
        }

        /**
         * Crea y añade un bloque de tema al DOM.
         * @param {Object} [tema=null] - Datos del tema a cargar (opcional).
         */
        function crearBloqueTema(tema = null) {
            contadorTemas++;
            const plantilla = document.getElementById("plantilla-tema");
            if (!plantilla || !plantilla.content) return;
            const nuevo = plantilla.content.cloneNode(true);
            const div = nuevo.querySelector('.tema-block');
            if (!div) return;
            const h6 = nuevo.querySelector('h6');
            if (h6) h6.innerText = `Tema ${contadorTemas}`;

            // Actualizar IDs y data-bs-target para Bootstrap Collapse
            nuevo.querySelectorAll('[data-bs-target]').forEach(el => {
                let target = el.getAttribute('data-bs-target').replace('_ID_', `_${contadorTemas}_`);
                el.setAttribute('data-bs-target', target);
                el.setAttribute('aria-controls', target.substring(1));
            });
            nuevo.querySelectorAll('.collapse').forEach(el => {
                el.id = el.id.replace('_ID_', `_${contadorTemas}_`);
            });

            const areas = nuevo.querySelectorAll('.editable-area');
            // Cargar datos si se proporcionan
            if (tema) {
                if (areas[0]) areas[0].innerHTML = tema.nombreTema || '';
                if (areas[1]) areas[1].innerHTML = tema.objetivo || '';
                if (areas[2]) areas[2].innerHTML = tema.descAcuerdo || '';
                if (areas[3]) areas[3].innerHTML = tema.compromiso || '';
                if (areas[4]) areas[4].innerHTML = tema.observacion || '';
                div.dataset.idTema = tema.idTema; // Guardar ID de tema existente
            }

            // Mostrar botón de eliminar si hay más de 1 tema
            const btnEliminar = nuevo.querySelector('.eliminar-tema');
            if (btnEliminar) btnEliminar.style.display = (contadorTemas > 1) ? 'inline-block' : 'none';
            contenedorTemasGlobal.appendChild(nuevo);

            // Si no es editable, deshabilitar áreas
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
                if (btnEliminar) btnEliminar.style.display = 'none'; // Ocultar el botón de eliminar si no es editable
            }
        }

        /**
         * Elimina un bloque de tema del DOM y actualiza la numeración.
         * @param {HTMLElement} btn - Botón de eliminar presionado.
         */
        function eliminarTema(btn) {
            const temaBlock = btn.closest('.tema-block');
            if (temaBlock) {
                temaBlock.remove();
                actualizarNumerosDeTema();
            }
        }

        /**
         * Re-numera los títulos de los bloques de tema.
         */
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
        // --- SECCIÓN: ADJUNTOS (Archivos y Enlaces) ---
        // ==================================================================

        /**
         * Maneja la subida de archivos (disparado por el evento 'change' del input file).
         */
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
                        throw new Error("El servidor respondió con un error (ver consola).");
                    });
                })
                .then(data => {
                    if (data.status === 'success') {
                        Swal.fire('Éxito', 'Archivo subido correctamente.', 'success');
                        agregarAdjuntoALista(data.data);
                        input.value = ''; // Limpiar el input

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

        /**
         * Maneja la adición de enlaces (disparado por el evento 'change' del input url).
         */
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
                    input.disabled = false;
                    input.placeholder = 'https://ejemplo.com';
                });
        }

        /**
         * Valida que una cadena sea una URL con formato correcto.
         */
        function filterUrl(str) {
            const pattern = new RegExp('^(https?:\\/\\/)?' + // protocol
                '((([a-z\\d]([a-z\\d-]*[a-z\\d])*)\\.)+[a-z]{2,}|' + // domain name
                '((\\d{1,3}\\.){3}\\d{1,3}))' + // OR ip (v4) address
                '(\\:\\d+)?(\\/[-a-z\\d%_.~+]*)*' + // port and path
                '(\\?[;&a-z\\d%_.~+=-]*)?' + // query string
                '(\\#[-a-z\\d_]*)?$', 'i'); // fragment locator
            return !!pattern.test(str);
        }

        /**
         * Carga la lista de adjuntos existentes desde el servidor.
         */
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

        /**
         * Renderiza la lista de adjuntos en el DOM.
         */
        function mostrarAdjuntosExistentes(adjuntos) {
            const listaUl = document.getElementById('listaAdjuntosExistentes');
            if (!listaUl) return;
            listaUl.innerHTML = '';
            if (!adjuntos || adjuntos.length === 0) {
                listaUl.innerHTML = '<li class="list-group-item text-muted">No hay adjuntos guardados para esta minuta.</li>';
                return;
            }
            adjuntos.forEach(adj => agregarAdjuntoALista(adj));
        }

        /**
         * Crea y añade un único elemento <li> de adjunto a la lista.
         */
        function agregarAdjuntoALista(adj) {
            const listaUl = document.getElementById('listaAdjuntosExistentes');
            if (!listaUl) return;

            // Borra el placeholder si es el primer item
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

            // Añade el botón de eliminar si no es asistencia
            if (adj.tipoAdjunto !== 'asistencia' && ES_ST_EDITABLE) {
                const deleteBtn = document.createElement('button');
                deleteBtn.className = 'btn btn-sm btn-outline-danger ms-2';
                deleteBtn.innerHTML = '<i class="fas fa-trash-alt"></i>';
                deleteBtn.onclick = () => eliminarAdjunto(adj.idAdjunto, li);
                li.appendChild(deleteBtn);
            }

            listaUl.appendChild(li);
        }

        /**
         * Elimina un adjunto de la base de datos y del DOM.
         */
        function eliminarAdjunto(idAdjunto, listItemElement) {
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

        // ==================================================================
        // --- SECCIÓN: ACCIONES DE MINUTA (Guardar/Enviar) ---
        // ==================================================================

        /**
         * Recolecta todos los datos del formulario (temas, acuerdos, etc.) y los envía al servidor.
         * @param {boolean} guardarYSalir - Si es true, redirige después de guardar.
         * @param {Function} [callback=null] - Función a llamar después de guardar (usado por confirmarEnvioAprobacion).
         */
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
                                // Redirección al listado de borradores
                                window.location.href = 'menu.php?pagina=minutas_listado_general&tab=borradores';
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
                        // Solo re-habilita el botón de envío si el estado lo permite (lógica PHP)
                        if (btnEnviar && <?php echo json_encode($puedeEnviar); ?>) {
                            btnEnviar.disabled = false;
                        }
                    }
                });
        }

        /**
         * Pide confirmación y llama a la secuencia Guardar -> Enviar Aprobación.
         */
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
                                        text: data.message,
                                        icon: 'success'
                                    }).then(() => {
                                        // Redirección al listado de pendientes
                                        window.location.href = 'menu.php?pagina=minutas_listado_general&tab=pendientes_aprobacion';
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

        // ==================================================================
        // --- SECCIÓN: FEEDBACK / RE-ENVÍO ---
        // ==================================================================

        /**
         * Llama al controlador para ver si existe feedback para esta minuta.
         */
        async function cargarYAplicarFeedback() {
            try {
                const response = await fetch(`/corevota/controllers/obtener_feedback_json.php?idMinuta=${idMinutaGlobal}`);
                if (!response.ok) {
                    throw new Error("No se pudo conectar al script de feedback.");
                }

                const data = await response.json();

                if (data.status === 'success' && data.data) {
                    REGLAS_FEEDBACK = data.data; // Guardamos las reglas globalmente

                    // Mostrar el texto del feedback en la alerta
                    if (data.textoFeedback) {
                        const container = document.getElementById('feedback-display-container');
                        const textoDiv = document.getElementById('feedback-display-texto');
                        if (container && textoDiv) {
                            textoDiv.textContent = data.textoFeedback;
                            container.style.display = 'block';
                        }
                    }

                    // Si hay feedback, aplicamos las reglas de edición
                    deshabilitarCamposSegunFeedback(REGLAS_FEEDBACK);
                    actualizarBotonesParaFeedback();

                } else if (data.status === 'no_feedback') {
                    // Si no hay feedback, la minuta queda editable según la lógica del ST
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

        /**
         * Deshabilita las secciones del formulario según las reglas de feedback.
         */
        function deshabilitarCamposSegunFeedback(reglas) {

            // Regla para Asistencia (Bloquea si reglas.asistencia es 'false')
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
            const btnCollapse = document.querySelector(`button[data-bs-target="#${idElemento}"]`);
            if (btnCollapse) {
                btnCollapse.disabled = true;
                btnCollapse.title = mensaje;
                btnCollapse.classList.add('opacity-50');
            }

            const seccion = document.getElementById(idElemento);
            if (seccion) {
                seccion.querySelectorAll('input, select, textarea, button, .editable-area').forEach(el => {
                    el.disabled = true;
                    el.contentEditable = false;
                    el.style.cursor = 'not-allowed';
                    el.style.backgroundColor = '#e9ecef';
                });

                seccion.querySelectorAll('.bb-editor-toolbar').forEach(toolbar => {
                    toolbar.style.display = 'none';
                });
            }

            // Casos especiales para secciones que no son 'collapse' o tienen un manejo de opacidad
            if (idElemento === 'adjuntos-section' || idElemento === 'contenedorTemas') {
                const target = document.querySelector(`.${idElemento}`) || document.getElementById(idElemento);
                if (target) {
                    target.style.opacity = '0.6';
                    target.style.pointerEvents = 'none';
                    target.title = mensaje;
                }
            }
        }

        /**
         * Cambia los botones de acción al final de la página para el modo Feedback.
         */
        function actualizarBotonesParaFeedback() {
            const btnGuardar = document.getElementById('btnGuardarBorrador');
            const btnEnviar = document.getElementById('btnEnviarAprobacion');

            if (btnGuardar) {
                btnGuardar.innerHTML = '<i class="fas fa-save"></i> Guardar Correcciones';
            }

            if (btnEnviar) {
                btnEnviar.innerHTML = '<i class="fas fa-check-double"></i> Aplicar y Reenviar p/ Aprobación';
                btnEnviar.classList.remove('btn-danger');
                btnEnviar.classList.add('btn-success');
                // Cambiamos la función que llama al re-envío
                btnEnviar.setAttribute('onclick', 'if (validarCamposMinuta()) confirmarAplicarFeedback()');
            }
        }

        /**
         * Secuencia de Guardar Correcciones -> Confirmar Re-envío.
         */
        function confirmarAplicarFeedback() {
            if (!ES_ST_EDITABLE) {
                Swal.fire('Prohibido', 'No puede reenviar la minuta en este estado.', 'error');
                return;
            }

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

                // 2. Si el guardado fue exitoso, pedimos confirmación de re-envío
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
         * Función final que llama al controlador aplicar_feedback.php para el re-envío.
         */
        function llamarAplicarFeedback() {
            const btnEnviar = document.getElementById('btnEnviarAprobacion');
            if (btnEnviar) {
                btnEnviar.disabled = true;
                btnEnviar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Reenviando...';
            }

            const formData = new FormData();
            formData.append('idMinuta', idMinutaGlobal);

            fetch('/corevota/controllers/aplicar_feedback.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        Swal.fire({
                            title: '¡Minuta Reenviada!',
                            text: data.message,
                            icon: 'success'
                        }).then(() => {
                            // Redirección al listado de pendientes
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

        /**
         * Valida que los campos obligatorios de cada tema (Tema y Objetivo) no estén vacíos.
         * @returns {boolean} True si todos los campos obligatorios están llenos.
         */
        function validarCamposMinuta() {
            const bloques = document.querySelectorAll('#contenedorTemas .tema-block');
            let valido = true;

            for (let index = 0; index < bloques.length; index++) {
                const bloque = bloques[index];
                const areas = bloque.querySelectorAll('.editable-area');
                const temaEl = areas[0];
                const objetivoEl = areas[1];

                // Limpiar etiquetas HTML y espacios en blanco
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
                    const collapse = bloque.querySelector('#temaTratado_' + (index + 1) + '_');
                    if (collapse && collapse.classList.contains('collapse')) {
                        const bsCollapse = new bootstrap.Collapse(collapse, {
                            show: true
                        });
                    }

                    (tema ? objetivoEl : temaEl)?.focus();
                    return false; // Sale de la función en el primer error
                }
            }

            return valido;
        }

        // ==================================================================
        // --- SECCIÓN: GESTIÓN Y RESULTADOS DE VOTACIONES ---
        // ==================================================================

        /**
         * Crea una nueva votación y la asocia a la minuta.
         */
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
                    cargarResultadosVotacion();
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

        /**
         * Carga la lista de votaciones asociadas a la minuta y las renderiza.
         */
        async function cargarVotacionesDeLaMinuta() {
            const cont = document.getElementById('listaVotacionesMinuta');
            const status = document.getElementById('votacionesStatus');
            if (!cont || !status) return;
            cont.innerHTML = '';
            status.textContent = 'Cargando...';

            try {
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

        /**
         * Abre un modal para registrar o modificar votos de asistentes manualmente.
         */
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

        /**
         * Registra o anula un voto para un usuario específico (usado por el secretario).
         */
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

                    // Actualizar resultados en vivo
                    cargarResultadosVotacion();
                } else {
                    throw new Error(data.message);
                }
            } catch (err) {
                parentTD.innerHTML = '<span class="text-danger">Error</span>';
                alert('Error al registrar voto: ' + err.message);
            }
        }

        /**
         * Llama a la API y renderiza los resultados en vivo de las votaciones.
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

            if (refreshButton) refreshButton.disabled = true;
            if (placeholder) placeholder.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Cargando resultados...';

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

                    container.innerHTML = ''; // Limpiar contenedor

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
         * Helper para evitar inyección XSS.
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
    </script>
</body>

</html>