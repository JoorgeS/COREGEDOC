<?php
// views/pages/crearMinuta.php

// ===============================================
// 1. INICIALIZACIÓN Y CONFIGURACIÓN (SIN CAMBIOS EN PHP)
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
$puedeEnviar    = false;      // Permite enviar a aprobación si no está APROBADA

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
// 2. LÓGICA DE CARGA DE DATOS (EDICIÓN) (SIN CAMBIOS EN PHP)
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
// 3. LÓGICA DE PERMISOS Y ESTADO (SIN CAMBIOS EN PHP)
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
// 4. INICIO DEL HTML (Estructura de Pestañas APLICADA)
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

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        /* Estilos CSS (Actualizados para las pestañas) */

        /* Resaltar la pestaña activa */
        .nav-link.active {
            font-weight: bold;
            border-bottom: 3px solid #0d6efd !important;
        }

        /* Ocultar pestañas por defecto (para control con checkboxes) */
        .nav-item.hidden-tab {
            display: none !important;
        }

        /* Estilos genéricos */
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

        /* Asegurar que las secciones en las pestañas no tienen el estilo de colapsable */
        #asistencia-tab-pane,
        #votaciones-tab-pane,
        #documentos-tab-pane {
            padding-top: 15px;
            /* Espacio para el contenido debajo del nav */
        }

        /* Ocultar los títulos de acordeón/dropdown que ya no se usan como tal */
        #asistencia-tab-pane h5,
        #votaciones-tab-pane h5 {
            display: none;
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
        </div>

        <ul class="nav nav-tabs mt-4" id="nav-tabs-minuta" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="desarrollo-tab" data-bs-toggle="tab" data-bs-target="#desarrollo-tab-pane" type="button" role="tab" aria-controls="desarrollo-tab-pane" aria-selected="true"><i class="fa-solid fa-pen-to-square me-1"></i> Desarrollo</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="asistencia-tab" data-bs-toggle="tab" data-bs-target="#asistencia-tab-pane" type="button" role="tab" aria-controls="asistencia-tab-pane" aria-selected="false"><i class="fa-solid fa-users me-1"></i> Asistencia</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="votaciones-tab" data-bs-toggle="tab" data-bs-target="#votaciones-tab-pane" type="button" role="tab" aria-controls="votaciones-tab-pane" aria-selected="false">
                    <i class="fas fa-vote-yea me-2"></i> Votaciones
                </button>
            </li>
            <li class="nav-item hidden-tab" role="presentation" id="nav-item-documentos">
                <button class="nav-link" id="documentos-tab" data-bs-toggle="tab" data-bs-target="#documentos-tab-pane" type="button" role="tab" aria-controls="documentos-tab-pane" aria-selected="false"><i class="fa-solid fa-paperclip me-1"></i> Documentos Adjuntos</button>
            </li>
        </ul>

        <div class="tab-content border border-top-0 p-3 bg-white shadow-sm" id="tab-content-minuta">

            <div class="tab-pane fade show active" id="desarrollo-tab-pane" role="tabpanel" aria-labelledby="desarrollo-tab" tabindex="0">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0">DESARROLLO DE LA MINUTA</h5>
                </div>

                <div class="card p-3 mb-4 border-primary">
                    <h6 class="fw-bold text-primary"><i class="fa-solid fa-lightbulb me-1"></i> Opciones de Contenido:</h6>
                    <small class="text-muted mb-2">Marque las opciones que desea gestionar para que aparezcan en la barra de pestañas.</small>
                    <div class="d-flex flex-wrap gap-3">
                        <div class="form-check form-check-inline">
                            <input class="form-check-input navigate-to-tab" type="checkbox" id="chkAdjuntos" data-target-tab="documentos-tab">
                            <label class="form-check-label" for="chkAdjuntos">Adjuntar Documentos</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input navigate-to-tab" type="checkbox" id="chkVotaciones" data-target-tab="votaciones-tab">
                            <label class="form-check-label" for="chkVotaciones">Gestionar Votaciones</label>
                        </div>
                    </div>
                </div>

                <div id="contenedorTemas">
                </div>

            </div>
            <div class="tab-pane fade" id="asistencia-tab-pane" role="tabpanel" aria-labelledby="asistencia-tab" tabindex="0">
                <div class="p-4 border rounded-bottom bg-white" style="border: none !important;">
                    <h5 class="fw-bold mb-3">Asistencia (Marcar estado en tiempo real)</h5>

                    <div id="contenedorTablaAsistenciaEstado" style="max-height: 500px; overflow-y: auto;">
                        <p class="text-muted"><i class="fas fa-spinner fa-spin me-2"></i>Cargando lista de consejeros...</p>
                    </div>

                    <div class="d-flex justify-content-end align-items-center mt-3 gap-2" id="botonesAsistenciaContainer">
                        <span id="guardarAsistenciaStatus" class="me-auto small text-muted"></span>

                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-refrescar-asistencia" onclick="cargarTablaAsistencia()">
                            <i class="fas fa-sync me-1"></i> Refrescar (Manual)
                        </button>
                        <button type="button" class="btn btn-warning fw-bold btn-sm" onclick="guardarAsistencia()" <?php echo $esSoloLectura ? 'disabled title="La minuta no es editable en el estado actual."' : ''; ?>>
                            <i class="fas fa-save me-1"></i> Guardar Asistencia (ST)
                        </button>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="votaciones-tab-pane" role="tabpanel" aria-labelledby="votaciones-tab" tabindex="0">

                <?php if ($esSecretarioTecnico && $estadoMinuta !== 'APROBADA') : ?>
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-vote-yea me-2"></i>Panel de Votaciones de la Minuta</h5>
                        </div>
                        <div class="card-body">

                            <h6 class="border-bottom pb-2 mb-3">Crear Nueva Votación</h6>
                            <form id="form-crear-votacion" class="row g-3 align-items-end mb-3">

                                <input type="hidden" id="votacion_idMinuta" value="<?php echo $idMinutaActual; ?>">
                                <input type="hidden" id="votacion_idReunion" value="<?php echo htmlspecialchars($idReunionActual); ?>">
                                <input type="hidden" id="votacion_idComision" value="<?php echo htmlspecialchars($minutaData['t_comision_idComision']); ?>">

                                <div class="col-md-8">
                                    <label for="nombreVotacion" class="form-label">Nombre de la Votación:</label>
                                    <input type="text" class="form-control" id="nombreVotacion" placeholder="Ej: Aprobar fondos para..." required>
                                </div>
                                <div class="col-md-4">
                                    <button type="submit" class="btn btn-success w-100">
                                        <i class="fas fa-plus me-2"></i>Crear Votación
                                    </button>
                                </div>
                            </form>

                            <hr class="my-4">

                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0">Votaciones Creadas</h6>
                                <span id="poll-status-display" class="text-muted small" style="font-family: 'Courier New', monospace;">
                                </span>
                            </div>
                            <div id="panel-votaciones-lista">
                            </div>

                            <hr class="my-5">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0 text-primary"><i class="fas fa-chart-bar me-2"></i>Resultados en Vivo</h6>
                                <span id="poll-resultados-status" class="text-muted small" style="font-family: 'Courier New', monospace;">
                                </span>
                            </div>

                            <div id="panel-resultados-en-vivo">
                                <p class="text-muted text-center" id="votacion-placeholder">
                                    <i class="fas fa-spinner fa-spin me-2"></i>Cargando resultados...
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="tab-pane fade" id="documentos-tab-pane" role="tabpanel" aria-labelledby="documentos-tab" tabindex="0">

                <div class="adjuntos-section">
                    <h5 class="fw-bold mb-3">Gestión de Archivos y Enlaces</h5>
                    <input type="hidden" id="idMinutaActual" value="<?php echo htmlspecialchars($idMinutaActual); ?>">

                    <form id="formSubirArchivo" class="mb-3">
                        <label for="inputArchivo" class="form-label">Añadir nuevo archivo (PDF, JPG, PNG, DOCX, etc.) <span id="file-upload-status" class="badge bg-light text-dark"></span></label>
                        <div class="input-group">
                            <input type="file" class="form-control" id="inputArchivo" name="archivo" required accept=".pdf,.jpg,.jpeg,.png,.xlsx,.mp4,.ppt,.pptx,.doc,.docx" <?php echo $esSoloLectura ? 'disabled' : ''; ?>>
                        </div>
                    </form>

                    <form id="formAgregarLink" class="mb-3" onsubmit="handleAgregarLink(event); return false;">
                        <label for="inputUrlLink" class="form-label">Añadir nuevo enlace (Escriba la URL y presione Enter o haga clic fuera):</label>
                        <div class="input-group">
                            <input type="url" class="form-control" id="inputUrlLink" name="urlLink" placeholder="https://ejemplo.com" required <?php echo $esSoloLectura ? 'readonly' : ''; ?>>
                        </div>
                    </form>

                    <div id="adjuntosExistentesContainer" class="mt-4 pt-3 border-top">
                        <h6>Archivos y Enlaces Existentes:</h6>
                        <ul id="listaAdjuntosExistentes" class="list-group list-group-flush">
                            <li class="list-group-item text-muted">Cargando...</li>
                        </ul>
                    </div>
                </div>

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
    </div> <template id="plantilla-tema">
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
    </div>
    <script>
        // ==================================================================
        // --- 1. VARIABLES GLOBALES ---
        // ==================================================================
        let contadorTemas = 0;
        const contenedorTemasGlobal = document.getElementById("contenedorTemas");
        const idMinutaGlobal = <?php echo json_encode($idMinutaActual); ?>;
        const ID_REUNION_GLOBAL = <?php echo json_encode($idReunionActual); ?>;
        const ID_SECRETARIO_LOGUEADO = <?php echo json_encode($_SESSION['idUsuario'] ?? 0); ?>;
        let bsModalValidarAsistencia = null;
        const ESTADO_MINUTA_ACTUAL = <?php echo json_encode($estadoMinuta); ?>;
        const DATOS_TEMAS_CARGADOS = <?php echo json_encode($temas_de_la_minuta ?? []); ?>;
        let ASISTENCIA_GUARDADA_IDS = <?php echo json_encode($asistencia_guardada_ids ?? []); ?>;
        const HORA_INICIO_REUNION = "<?php echo htmlspecialchars(date('H:i:s', strtotime($minutaData['horaMinuta'] ?? 'now'))); ?>";
        const ES_ST_EDITABLE = <?php echo $esSoloLectura ? 'false' : 'true'; ?>;

        const LIMITE_MINUTOS_AUTOGESTION = 30;
        const INTERVALO_ASISTENCIA = 1000;
        const INTERVALO_VOTACIONES = 3000;

        let intervalAsistenciaID = null;
        let asistenciaModificando = false;
        let REGLAS_FEEDBACK = null;

        let intervalListaVotacionID = null;
        let intervalResultadosID = null;
        let cacheVotacionesList = "";
        let cacheResultados = "";

        // Elementos de UI
        const formSubirArchivo = document.getElementById('formSubirArchivo');
        const inputArchivo = document.getElementById('inputArchivo');
        const formAgregarLink = document.getElementById('formAgregarLink');
        const inputUrlLink = document.getElementById('inputUrlLink');
        const fileStatus = document.getElementById('file-upload-status');

        // Variable "puente" (por si se usa en otro lado)
        let exposedCargarVotaciones;

        // ==================================================================
        // --- 2. DEFINICIONES DE FUNCIONES ---
        // (Reordenadas para evitar errores de "función no definida")
        // ==================================================================

        // --- Funciones de Utilidad ---

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

        // --- Funciones de Polling (Asistencia) ---

        function detenerPollingAsistencia() {
            if (intervalAsistenciaID !== null) {
                clearInterval(intervalAsistenciaID);
                intervalAsistenciaID = null;
                asistenciaModificando = true;
                console.log('Polling de asistencia DETENIDO por acción del ST/Guardado manual.');
            }
        }

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

        // --- Funciones de Votaciones (Definiciones) ---

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
                
                ${!habilitada ? `
                <button type="button" class="btn btn-primary btn-sm btn-ver-resultados" 
                        data-id="${votacion.idVotacion}" 
                        data-nombre="${escapeHTML(nombreVotacion)}">
                    <i class="fas fa-chart-bar me-1"></i> Ver
                </button>
                ` : ''}
                            </div>`;
            return div;
        }

        async function cargarListaDeVotaciones(esCargaInicial = false, callback = null) {
            const container = document.getElementById('panel-votaciones-lista');
            if (!container) return;

            if (esCargaInicial) {
                container.innerHTML = '<p class="text-center"><i class="fas fa-spinner fa-spin"></i> Cargando lista de votaciones...</p>';
            }

            try {
                // ⚡ RUTA CORREGIDA
                const response = await fetch(`../../controllers/gestionar_votacion_minuta.php?action=list&idMinuta=${idMinutaGlobal}`, {
                    method: 'GET',
                    cache: 'no-store',
                    credentials: 'same-origin'
                });

                if (!response.ok) throw new Error('Error de red al listar votaciones.');

                const text = await response.text();
                if (text === cacheVotacionesList) {
                    if (callback) callback(false);
                    return;
                }
                cacheVotacionesList = text;
                const data = JSON.parse(text);

                if (data.status !== 'success') throw new Error(data.message);

                container.innerHTML = '';
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

                if (callback) callback(true);

            } catch (error) {
                console.error('Error en cargarListaDeVotaciones:', error);
                if (esCargaInicial) {
                    container.innerHTML = `<p class="text-danger text-center"><strong>Error:</strong> ${error.message}</p>`;
                }
                if (callback) callback(false);
            }
        }

        async function cargarResultadosVotacion(esCargaInicial = false, callback = null) {
            const container = document.getElementById('panel-resultados-en-vivo');
            if (!container) return;

            if (esCargaInicial) {
                container.innerHTML = '<p class="text-center" id="votacion-placeholder"><i class="fas fa-spinner fa-spin"></i> Cargando resultados...</p>';
            }

            try {
                // ⚡ RUTA CORREGIDA: De '/corevota/...' a '../../...'
                const response = await fetch(`../../controllers/obtener_resultados_votacion.php?idMinuta=${encodeURIComponent(idMinutaGlobal)}`, {
                    method: 'GET',
                    cache: 'no-store',
                    credentials: 'same-origin'
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
                    container.innerHTML = `<p class="text-danger text-center" id="votacion-placeholder"><strong>Error:</strong> ${data.message}</p>`;
                    if (callback) callback(false);
                    return;
                }

                // ⬇️ --- AQUÍ ESTÁ LA CORRECCIÓN --- ⬇️
                if (!data.data || data.data.length === 0) { // Cambiamos 'data.votaciones' por 'data.data'
                    container.innerHTML = `<p class="text-muted text-center" id="votacion-placeholder">No hay votaciones activas para esta minuta.</p>`;
                    if (callback) callback(false);
                    return;
                }

                container.innerHTML = '';

                data.data.forEach(v => { // Cambiamos 'data.votaciones' por 'data.data'
                    // ⬆️ --- FIN DE LA CORRECCIÓN --- ⬆️
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


        async function mostrarResultadosCerrados(idVotacion, nombreVotacion) {
            Swal.fire({
                title: `Resultados de: ${escapeHTML(nombreVotacion)}`,
                html: '<p><i class="fas fa-spinner fa-spin"></i> Cargando resultados finales...</p>',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            try {
                // Llamamos al MISMO endpoint, pero le pasamos un idVotacion específico
                // Tu backend (obtener_resultados_votacion.php) deberá ser actualizado
                // para que si recibe 'idVotacion', devuelva solo esa.
                const response = await fetch(`../../controllers/obtener_resultados_votacion.php?idMinuta=${idMinutaGlobal}&idVotacion=${idVotacion}`, {
                    method: 'GET',
                    cache: 'no-store',
                    credentials: 'same-origin' // ¡Crucial para la sesión!
                });

                if (!response.ok) throw new Error('Error de red al buscar resultados.');

                const data = await response.json();
                if (data.status === 'error') throw new Error(data.message);

                // ⬇️ --- AQUÍ ESTÁ LA CORRECCIÓN --- ⬇️
                if (!data.data || data.data.length === 0) throw new Error('No se encontraron resultados para esta votación.'); // Cambiamos 'data.votaciones'

                const v = data.data[0]; // Cambiamos 'data.votaciones'
                // ⬆️ --- FIN DE LA CORRECCIÓN --- ⬆️

                // --- Lógica de renderizado REUTILIZADA de cargarResultadosVotacion ---
                const totalVotantes = v.votosSi + v.votosNo + v.votosAbstencion;
                const faltanVotar = v.totalPresentes - totalVotantes;
                const getVoterList = (list) => list.length > 0 ? `<ul class="list-unstyled mb-0 small">${list.map(name => `<li><i class="fas fa-user fa-fw me-1 text-muted"></i>${escapeHTML(name)}</li>`).join('')}</ul>` : '<em class="text-muted small ps-2">Sin votos</em>';

                const votacionHtml = `
            <div class="card mb-4 shadow-sm votacion-block-ui" style="text-align: left; border: none; box-shadow: none !important;">
                <div class="card-body" style="padding: 0;">
                    <div class="row text-center mb-4">
                        <div class="col-4"><h3 class="text-success mb-0">${v.votosSi}</h3><p class="mb-0 small text-uppercase">A Favor</p></div>
                        <div class="col-4"><h3 class="text-danger mb-0">${v.votosNo}</h3><p class="mb-0 small text-uppercase">En Contra</p></div>
                        <div class="col-4"><h3 class="text-secondary mb-0">${v.votosAbstencion}</h3><p class="mb-0 small text-uppercase">Abstención</p></div>
                    </div>
                    <hr>
                    <div class="row text-center mb-3">
                        <div class="col-6"><h4 class="text-info mb-0">${v.totalPresentes}</h4><p class="mb-0 small">Asistentes</p></div>
                        <div class="col-6"><h4 class="text-secondary mb-0">${totalVotantes}</h4><p class="mb-0 small">Total Votos</p></div>
                    </div>
                    <h6 class="mt-4 border-bottom pb-1 small text-uppercase text-muted">Detalle de Votantes</h6>
                    <table class="table table-sm table-bordered" style="font-size: 0.9rem;">
                        <thead><tr class="table-light">
                            <th class="text-success">Sí (${v.votosSi})</th>
                            <th class="text-danger">No (${v.votosNo})</th>
                            <th class="text-secondary">Abst. (${v.votosAbstencion})</th>
                        </tr></thead>
                        <tbody><tr>
                            <td style="vertical-align: top;">${getVoterList(v.votosSi_nombres || [])}</td>
                            <td style="vertical-align: top;">${getVoterList(v.votosNo_nombres || [])}</td>
                            <td style="vertical-align: top;">${getVoterList(v.votosAbstencion_nombres || [])}</td>
                        </tr></tbody>
                    </table>
                </div>
            </div>`;
                // --- Fin de la lógica reutilizada ---

                Swal.update({
                    html: votacionHtml,
                    showConfirmButton: true,
                    confirmButtonText: 'Cerrar',
                    showLoading: false,
                    width: '800px' // Modal más ancho para la tabla
                });

            } catch (error) {
                Swal.fire('Error', `No se pudieron cargar los resultados: ${error.message}`, 'error');
            }
        }






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
                    actualizarTimestamp('fa-sync fa-spin text-primary', 'Buscando cambios...');
                    cargarListaDeVotaciones(false, (cambiosDetectados) => {
                        if (cambiosDetectados) {
                            actualizarTimestamp('fa-check text-success', 'Lista actualizada');
                        } else {
                            actualizarTimestamp('fa-satellite-dish text-muted', 'Sincronizado');
                        }
                    });
                } else {
                    if (statusDisplay.innerHTML !== '') statusDisplay.innerHTML = '';
                }
            }, INTERVALO_VOTACIONES);
        }

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

        // --- Funciones de Asistencia ---

        function cargarTablaAsistencia(isInitialLoad) {
            const cont = document.getElementById("contenedorTablaAsistenciaEstado");
            const btnRefresh = document.getElementById("btn-refrescar-asistencia");

            if (isInitialLoad) {
                if (btnRefresh) btnRefresh.disabled = true;
                cont.innerHTML = '<p class="text-muted"><i class="fas fa-spinner fa-spin me-2"></i>Cargando lista de consejeros y asistencia...</p>';
            }

            // ⚡ RUTA CORREGIDA
            const fetchConsejeros = fetch("../../controllers/fetch_data.php?action=asistencia_all", {
                method: 'GET',
                credentials: 'same-origin'
            }).then(res => res.ok ? res.json() : Promise.reject(new Error('Error fetch_data.php')));

            // ⚡ RUTA CORREGIDA
            const fetchAsistenciaActual = fetch(`../../controllers/obtener_asistencia_actual.php?idMinuta=${idMinutaGlobal}`, {
                method: 'GET',
                credentials: 'same-origin'
            }).then(res => res.ok ? res.json() : Promise.reject(new Error('Error obtener_asistencia_actual.php')));

            Promise.all([fetchConsejeros, fetchAsistenciaActual])
                .then(([responseConsejeros, responseAsistencia]) => {
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
                        timeWarning += '<p class="text-muted small">El Secretario Técnico puede seguir marcando asistencia manualmente.</p>';
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
            return ids;
        }

        function guardarAsistencia() {
            const asistenciaIDs = recolectarAsistencia();
            if (!ES_ST_EDITABLE) {
                Swal.fire('Prohibido', 'No puede guardar la asistencia en este estado.', 'error');
                return;
            }
            const status = document.getElementById('guardarAsistenciaStatus');
            const btn = document.querySelector('#botonesAsistenciaContainer button[onclick="guardarAsistencia()"]');
            const formData = new FormData();
            formData.append('idMinuta', idMinutaGlobal);
            formData.append('asistencia', JSON.stringify(asistenciaIDs));
            btn.disabled = true;
            status.textContent = 'Guardando...';
            status.className = 'me-auto small text-muted';

            // ⚡ RUTA CORREGIDA
            fetch("../../controllers/guardar_asistencia.php", {
                    method: "POST",
                    body: formData,
                    credentials: 'same-origin'
                })
                .then(res => res.ok ? res.json() : res.text().then(text => Promise.reject(new Error("Respuesta servidor inválida: " + text))))
                .then(resp => {
                    btn.disabled = false;
                    if (resp.status === "success") {
                        status.textContent = "✅ Guardado";
                        status.className = 'me-auto small text-success fw-bold';
                        cargarTablaAsistencia(true);
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

        // --- Funciones de Acciones de Minuta (Guardar/Enviar) ---

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
                        // ⚡ RUTA CORREGIDA (y en minúsculas)
                        fetch(`../../controllers/obtener_preview_asistencia.php?idMinuta=${encodeURIComponent(idMinutaGlobal)}`, {
                                method: 'GET',
                                credentials: 'same-origin'
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

            // ⚡ RUTA CORREGIDA
            fetch("../../controllers/guardar_minuta_completa.php", {
                    method: "POST",
                    body: formData,
                    credentials: 'same-origin'
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

                        // ⚡ RUTA CORREGIDA
                        fetch('../../controllers/enviar_aprobacion.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json'
                                },
                                body: JSON.stringify({
                                    idMinuta: idMinuta
                                }),
                                credentials: 'same-origin'
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

        // --- Funciones de Feedback ---

        async function cargarYAplicarFeedback() {
            try {
                // ⚡ RUTA CORREGIDA
                const response = await fetch(`../../controllers/obtener_feedback_json.php?idMinuta=${idMinutaGlobal}`, {
                    method: 'GET',
                    credentials: 'same-origin'
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
            const btnEnviar = document.getElementById('btnEnviarAprobacion');
            if (btnEnviar) {
                btnEnviar.disabled = true;
                btnEnviar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Reenviando...';
            }
            const formData = new FormData();
            formData.append('idMinuta', idMinutaGlobal);

            // ⚡ RUTA CORREGIDA
            fetch('../../controllers/aplicar_feedback.php', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
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

        // --- Funciones de Validación y Temas ---

        function validarCamposMinuta() {
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

        // --- Funciones de Adjuntos ---

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

            // ⚡ RUTA CORREGIDA
            fetch('../../controllers/agregar_adjunto.php?action=upload', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
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

            // ⚡ RUTA CORREGIDA
            fetch('../../controllers/agregar_adjunto.php?action=link', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
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

            // ⚡ RUTA CORREGIDA
            fetch(`../../controllers/obtener_adjuntos.php?idMinuta=${idMinutaGlobal}&_cacheBust=${new Date().getTime()}`, {
                    method: 'GET',
                    credentials: 'same-origin'
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
            // ⚡ RUTA CORREGIDA: Quitamos '/corevota/'
            const url = (adj.tipoAdjunto === 'file' || adj.tipoAdjunto === 'asistencia') ? `../../${adj.pathAdjunto}` : adj.pathAdjunto;
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
                    // ⚡ RUTA CORREGIDA
                    fetch(`../../controllers/eliminar_adjunto.php?idAdjunto=${idAdjunto}`, {
                            method: 'GET',
                            credentials: 'same-origin'
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


        // ==================================================================
        // --- 3. EVENT LISTENER PRINCIPAL (DOMContentLoaded) ---
        // ==================================================================
        document.addEventListener("DOMContentLoaded", () => {

            // 1. Carga Inicial de datos
            cargarTablaAsistencia(true);
            cargarOPrepararTemas();
            cargarYMostrarAdjuntosExistentes();
            cargarListaDeVotaciones(true);
            cargarResultadosVotacion(true);

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
            iniciarPollingCondicional();
            iniciarPollingListaVotaciones();
            iniciarPollingResultados();

            // 4. Lógica de Navegación por Checkbox
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

            // 5. Eventos para archivos y enlaces
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
            if (formSubirArchivo) {
                formSubirArchivo.addEventListener('submit', handleSubirArchivo);
            }
            if (formAgregarLink) {
                formAgregarLink.addEventListener('submit', handleAgregarLink);
            }

            // 6. Lógica del Modal de Validación
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

                    // ⚡ RUTA CORREGIDA (y en minúsculas)
                    fetch('../../controllers/enviar_asistencia_validada.php', {
                            method: 'POST',
                            body: formData,
                            credentials: 'same-origin'
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

            // 7. Listeners de Votaciones
            const formCrearVotacion = document.getElementById('form-crear-votacion');
            const listaContainer = document.getElementById('panel-votaciones-lista');
            const inputNombreVotacion = document.getElementById('nombreVotacion');

            if (formCrearVotacion && listaContainer && inputNombreVotacion) {

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
                    const idComisionActual = document.getElementById('votacion_idComision').value;
                    formData.append('idComision', idComisionActual);

                    try {
                        // ⚡ RUTA CORREGIDA
                        const response = await fetch('../../controllers/gestionar_votacion_minuta.php', {
                            method: 'POST',
                            body: formData,
                            credentials: 'same-origin'
                        });

                        const data = await response.json();
                        if (data.status !== 'success') throw new Error(data.message);

                        Swal.fire('¡Éxito!', 'Votación creada correctamente.', 'success');
                        inputNombreVotacion.value = '';
                        cargarListaDeVotaciones(true);

                    } catch (error) {
                        Swal.fire('Error', error.message, 'error');
                    } finally {
                        btnSubmit.disabled = false;
                        btnSubmit.innerHTML = '<i class="fas fa-plus me-2"></i>Crear Votación';
                    }
                });

                listaContainer.addEventListener('click', async (e) => {
                    const boton = e.target.closest('.btn-cambiar-estado');
                    const botonVer = e.target.closest('.btn-ver-resultados');

                    // --- INICIO: LÓGICA PARA HABILITAR/CERRAR VOTACIÓN ---
                    if (boton) {
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
                                const response = await fetch('../../controllers/gestionar_votacion_minuta.php', {
                                    method: 'POST',
                                    body: formData,
                                    credentials: 'same-origin'
                                });

                                const data = await response.json();
                                if (data.status !== 'success') throw new Error(data.message);

                                Swal.fire('¡Éxito!', `Votación ${accionTexto.toLowerCase()}da.`, 'success');
                                cargarListaDeVotaciones(true);

                            } catch (error) {
                                Swal.fire('Error', error.message, 'error');
                                cargarListaDeVotaciones(true);
                            }
                        }
                        // --- FIN: LÓGICA HABILITAR/CERRAR ---
                    }

                    // --- INICIO: LÓGICA PARA "VER RESULTADOS" ---
                    // Esta es la parte que faltaba en tu código
                    if (botonVer) {
                        const idVotacion = botonVer.dataset.id;
                        const nombreVotacion = botonVer.dataset.nombre;
                        // Esta función la creamos en el "Ajuste 2" de la respuesta anterior
                        await mostrarResultadosCerrados(idVotacion, nombreVotacion);
                    }
                    // --- FIN: LÓGICA "VER RESULTADOS" ---




                });
            }
        });
    </script>
</body>

</html>