<?php
// views/pages/crearMinuta.php - VERSIÓN CON ENCABEZADO UNIFICADO
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// Asegúrate que la timezone esté definida globalmente (ej. en class.conectorDB.php)
// date_default_timezone_set('America/Santiago'); 

require_once __DIR__ . '/../../class/class.conectorDB.php';
require_once __DIR__ . '/../../controllers/VotacionController.php'; // 🟨 AÑADIDO PARA EL TEMPLATE

$db = new conectorDB();
$pdo = $db->getDatabase();

$idMinutaActual = $_GET['id'] ?? null;
$minutaData = null;
$reunionData = null; // Para IDs de comisiones mixtas
$temas_de_la_minuta = [];
$asistencia_guardada_ids = [];
$existeAsistenciaGuardada = false;
$secretarioNombre = trim(($_SESSION['pNombre'] ?? '') . ' ' . ($_SESSION['aPaterno'] ?? 'N/A')); // Nombre del usuario logueado

// --- 🔽 AÑADIDO PARA VOTACIONES 🔽 ---
$idReunionActual = null;
$comisionesDeLaReunion = []; // Para el <select> de votación
// --- 🔼 FIN AÑADIDO 🔼 ---

// --- Variables para almacenar los nombres a mostrar en el encabezado ---
$nombreComisionPrincipal = 'N/A';
$nombrePresidentePrincipal = 'N/A';
$idPresidentePrincipal = null; // ⭐ NUEVO: Guardar ID
$nombreComisionMixta1 = null;
$nombrePresidenteMixta1 = null;
$idPresidenteMixta1 = null; // ⭐ NUEVO: Guardar ID
$nombreComisionMixta2 = null;
$nombrePresidenteMixta2 = null;
$idPresidenteMixta2 = null; // ⭐ NUEVO: Guardar ID
$all_commissions = []; // Array [idComision => ['nombreComision' => ..., 't_usuario_idPresidente' => ...]]
$all_presidents = []; // Array [idUsuario => 'Nombre Apellido']

// --- Solo intentar cargar si el ID es válido ---
if ($idMinutaActual && is_numeric($idMinutaActual)) {
  try {
    // 1. Cargar datos de t_minuta (Comisión principal y su presidente asociado a la minuta)
    $sql_minuta = "SELECT t_comision_idComision, t_usuario_idPresidente, estadoMinuta, fechaMinuta, horaMinuta 
                         FROM t_minuta 
                         WHERE idMinuta = :idMinutaActual";
    $stmt_minuta = $pdo->prepare($sql_minuta);
    $stmt_minuta->execute([':idMinutaActual' => $idMinutaActual]);
    $minutaData = $stmt_minuta->fetch(PDO::FETCH_ASSOC);

    if (!$minutaData) {
      // Si la minuta no existe, detenemos la ejecución o redirigimos
      throw new Exception("Minuta con ID $idMinutaActual no encontrada.");
    }

    // 2. Cargar datos de t_reunion (para obtener IDs de comisiones mixtas)
    // 🟨 MODIFICADO 🟨
    $sql_reunion = "SELECT idReunion, t_comision_idComision, t_comision_idComision_mixta, t_comision_idComision_mixta2 
                          FROM t_reunion 
                          WHERE t_minuta_idMinuta = :idMinutaActual";
    $stmt_reunion = $pdo->prepare($sql_reunion);
    $stmt_reunion->execute([':idMinutaActual' => $idMinutaActual]);
    $reunionData = $stmt_reunion->fetch(PDO::FETCH_ASSOC);
    $idReunionActual = $reunionData['idReunion'] ?? null; // <-- Guardamos el ID de la reunión
    // $reunionData puede ser 'false' si no hay reunión asociada, aunque no debería pasar

    // 3. Cargar TODAS las comisiones vigentes y TODOS los posibles presidentes (Consejeros)
    // Comisiones (Indexadas por ID)
    $stmt_all_com = $pdo->query("SELECT idComision, nombreComision, t_usuario_idPresidente FROM t_comision WHERE vigencia = 1");
    if ($stmt_all_com) {
      $all_commissions_raw = $stmt_all_com->fetchAll(PDO::FETCH_ASSOC);
      foreach ($all_commissions_raw as $com) {
        $all_commissions[$com['idComision']] = $com;
      }
    }

    // Presidentes (Indexados por ID, asumiendo tipoUsuario_id = 1)
    $stmt_all_pres = $pdo->query("SELECT idUsuario, pNombre, aPaterno FROM t_usuario WHERE tipoUsuario_id = 1");
    if ($stmt_all_pres) {
      $all_presidents_raw = $stmt_all_pres->fetchAll(PDO::FETCH_ASSOC);
      foreach ($all_presidents_raw as $pres) {
        $all_presidents[$pres['idUsuario']] = trim($pres['pNombre'] . ' ' . $pres['aPaterno']);
      }
    }

    // --- 4. ASIGNAR NOMBRES PARA MOSTRAR EN EL ENCABEZADO ---
    $idComisionPrincipal = $minutaData['t_comision_idComision'];

    // ⭐ CORRECCIÓN: Usamos el ID de presidente "en vivo" de la comisión, no el "congelado"
    $idPresidentePrincipal = $all_commissions[$idComisionPrincipal]['t_usuario_idPresidente'] ?? null; // ⭐ CORRECCIÓN: Guardamos el ID

    // Buscar nombres usando los arrays cargados
    $nombreComisionPrincipal = $all_commissions[$idComisionPrincipal]['nombreComision'] ?? 'Comisión No Encontrada/Inválida';
    $nombrePresidentePrincipal = $all_presidents[$idPresidentePrincipal] ?? 'Presidente No Encontrado/Inválido';

    // --- 🔽 AÑADIDO PARA VOTACIONES 🔽 ---
    // Llenar el array de comisiones para el <select> de votación
    if (isset($all_commissions[$idComisionPrincipal])) {
      $comisionesDeLaReunion[$idComisionPrincipal] = $nombreComisionPrincipal;
    }
    // --- 🔼 FIN AÑADIDO 🔼 ---

    // Buscar nombres para comisiones mixtas (si existen en $reunionData)
    if ($reunionData && !empty($reunionData['t_comision_idComision_mixta'])) {
      $idComisionMixta1 = $reunionData['t_comision_idComision_mixta'];
      if (isset($all_commissions[$idComisionMixta1])) {
        $nombreComisionMixta1 = $all_commissions[$idComisionMixta1]['nombreComision'];
        // Buscar el presidente oficial de ESTA comisión mixta
        $idPresidenteMixta1 = $all_commissions[$idComisionMixta1]['t_usuario_idPresidente'] ?? null; // ⭐ CORRECCIÓN: Guardamos el ID
        $nombrePresidenteMixta1 = $idPresidenteMixta1 ? ($all_presidents[$idPresidenteMixta1] ?? 'Presidente No Asignado') : 'N/A';

        // --- 🔽 AÑADIDO PARA VOTACIONES 🔽 ---
        $comisionesDeLaReunion[$idComisionMixta1] = $nombreComisionMixta1;
        // --- 🔼 FIN AÑADIDO 🔼 ---

      } else {
        $nombreComisionMixta1 = 'Comisión Mixta 1 No Encontrada/Inválida';
        $nombrePresidenteMixta1 = 'N/A';
      }
    }
    if ($reunionData && !empty($reunionData['t_comision_idComision_mixta2'])) {
      $idComisionMixta2 = $reunionData['t_comision_idComision_mixta2'];
      if (isset($all_commissions[$idComisionMixta2])) {
        $nombreComisionMixta2 = $all_commissions[$idComisionMixta2]['nombreComision'];
        // Buscar el presidente oficial de ESTA comisión mixta
        $idPresidenteMixta2 = $all_commissions[$idComisionMixta2]['t_usuario_idPresidente'] ?? null; // ⭐ CORRECCIÓN: Guardamos el ID
        $nombrePresidenteMixta2 = $idPresidenteMixta2 ? ($all_presidents[$idPresidenteMixta2] ?? 'Presidente No Asignado') : 'N/A';

        // --- 🔽 AÑADIDO PARA VOTACIONES 🔽 ---
        $comisionesDeLaReunion[$idComisionMixta2] = $nombreComisionMixta2;
        // --- 🔼 FIN AÑADIDO 🔼 ---

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

    // 6. Cargar asistencia (sin cambios)
    $sql_asistencia = "SELECT t_usuario_idUsuario FROM t_asistencia WHERE t_minuta_idMinuta = :idMinutaActual";
    $stmt_asistencia = $pdo->prepare($sql_asistencia);
    $stmt_asistencia->execute([':idMinutaActual' => $idMinutaActual]);
    $asistencia_guardada_ids = $stmt_asistencia->fetchAll(PDO::FETCH_COLUMN, 0); // Sigue siendo array de strings
    $existeAsistenciaGuardada = !empty($asistencia_guardada_ids);

    $estadoFirmaUsuario = null;
    if (isset($_SESSION['idUsuario'])) {
      $sqlFirma = $pdo->prepare("SELECT estado_firma FROM t_aprobacion_minuta 
                  WHERE t_minuta_idMinuta = :idMinuta 
                  AND t_usuario_idPresidente = :idUsuario");
      $sqlFirma->execute([':idMinuta' => $idMinutaActual, ':idUsuario' => $_SESSION['idUsuario']]);
      // fetchColumn() devolverá 'CONFIRMADA', 'PENDIENTE_REVISION', o false si no hay fila
      $estadoFirmaUsuario = $sqlFirma->fetchColumn();
    }
  } catch (Exception $e) {
    error_log("Error cargando datos para edición (Minuta ID: {$idMinutaActual}): " . $e->getMessage());
    // Mostrar un mensaje de error o redirigir
    die("❌ Error al cargar los datos de la minuta: " . htmlspecialchars($e->getMessage()) . "<br><a href='menu.php?pagina=minutas_pendientes'>Volver al listado</a>");
  } finally {
    $pdo = null; // Cerrar conexión
  }
} else {
  // Manejar el caso de ID de minuta no proporcionado o inválido
  die("❌ Error: No se especificó un ID de minuta válido para editar. <a href='menu.php?pagina=minutas_pendientes'>Volver al listado</a>");
}

// ⭐ ================== INICIO BLOQUE MODIFICADO ==================
// --- 4b. ⭐ NUEVO: Crear un array con TODOS los IDs de presidentes requeridos ---
// (Usamos los IDs que ya encontramos más arriba: $idPresidentePrincipal, $idPresidenteMixta1, $idPresidenteMixta2)
$listaPresidentesRequeridos = array_unique(array_filter([
  $idPresidentePrincipal,
  $idPresidenteMixta1,
  $idPresidenteMixta2
]));

// --- Variables PHP para pasar a JS ---
$estadoMinuta = $minutaData['estadoMinuta'] ?? 'PENDIENTE';

// ⭐ CORREGIDO: Pasamos el array de IDs a JS
// Usamos array_map('intval', ...) para asegurar que sean números [10, 15, 20] y no strings
$jsArrayPresidentesRequeridos = json_encode(array_map('intval', $listaPresidentesRequeridos));
// ⭐ =================== FIN BLOQUE MODIFICADO ===================

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
    /* Estilos específicos */
    /* Encabezado azul */
    .card-header.bg-primary {
      background-color: #0d6efd !important;
    }

    /* Cuerpo gris claro */
    .card-body.bg-light {
      background-color: #f8f9fa !important;
    }

    /* Etiquetas en negrita */
    dl.row dt {
      font-weight: 600;
      text-align: right;
      padding-right: 0.5em;
    }

    /* Aumentar espacio entre filas del encabezado */
    dl.row>div {
      margin-bottom: 0.3rem;
    }

    /* Evitar desbordamiento en valores largos */
    dl.row dd {
      word-break: break-word;
    }

    /* Otros estilos heredados */
    @keyframes fadeIn {
      from {
        opacity: 0;
      }

      to {
        opacity: 1;
      }
    }

    .asistencia-checkbox.absent-check.default-absent:checked {
      background-color: #adb5bd;
      border-color: #adb5bd;
      opacity: 0.7;
    }

    #tablaAsistenciaEstado th,
    #tablaAsistenciaEstado td {
      text-align: center;
      vertical-align: middle;
    }

    #tablaAsistenciaEstado td:first-child {
      text-align: left;
    }

    .bb-editor-toolbar button {
      margin-right: 2px;
    }

    .hidden-block {
      display: none;
      transition: opacity 0.3s ease-in-out;
      opacity: 0;
    }

    .hidden-block.show {
      display: block;
      opacity: 1;
    }
  </style>
</head>

<body>

  <div class="container-fluid app-container p-4">
    <h5 class="fw-bold mb-3">GESTIÓN DE LA MINUTA</h5>

    <div class="row g-3">


      <div class="col-12 mb-3">
        <div class="card shadow-sm">
          <div class="card-header bg-primary text-white fw-bold">
            Encabezado Minuta
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
                <button type="button" class="btn btn-info btn-sm" onclick="guardarAsistencia()">
                  <i class="fas fa-save me-1"></i> Guardar Asistencia
                </button>
                <a href="#" class="btn btn-success btn-sm <?php echo !$existeAsistenciaGuardada ? 'disabled' : ''; ?>" id="btnExportarExcel" role="button" <?php echo $idMinutaActual ? 'data-idminuta="' . $idMinutaActual . '"' : ''; ?> <?php echo ($existeAsistenciaGuardada && $idMinutaActual) ? 'href="/corevota/controllers/exportar_asistencia_excel.php?idMinuta=' . $idMinutaActual . '"' : 'href="#"'; ?>>
                  <i class="fas fa-file-excel me-1"></i> Exportar Asistencia (Excel)
                </a>
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

              <div class="card shadow-sm mb-4">
                <div class="card-header fw-bold">
                  Crear Nueva Votación para esta Minuta
                </div>
                <div class="card-body">
                  <form id="formCrearVotacionMinuta">
                    <div class="mb-3">
                      <label for="votacionComisionId" class="form-label">Comisión *</label>
                      <select class="form-select" id="votacionComisionId" required>
                        <?php if (count($comisionesDeLaReunion) === 0) : ?>
                          <option value="">-- No hay comisiones válidas --</option>
                        <?php elseif (count($comisionesDeLaReunion) === 1) :
                          // Si solo hay una, seleccionarla por defecto
                          $idCom = key($comisionesDeLaReunion);
                          $nombreCom = $comisionesDeLaReunion[$idCom];
                        ?>
                          <option value="<?php echo $idCom; ?>" selected><?php echo htmlspecialchars($nombreCom); ?></option>
                        <?php else :
                          // Si hay varias (mixta), dar a elegir
                        ?>
                          <option value="">-- Seleccione comisión (Mixta) --</option>
                          <?php foreach ($comisionesDeLaReunion as $id => $nombre) : ?>
                            <option value="<?php echo $id; ?>"><?php echo htmlspecialchars($nombre); ?></option>
                          <?php endforeach; ?>
                        <?php endif; ?>
                      </select>
                    </div>
                    <div class="mb-3">
                      <label for="votacionNombre" class="form-label">Texto de la Votación (Adenda) *</label>
                      <textarea class="form-control" id="votacionNombre" rows="2" placeholder="Ej: ¿Aprueba el presupuesto para TI?" required></textarea>
                    </div>
                    <div class="text-end">
                      <button type="button" class="btn btn-primary" onclick="guardarNuevaVotacion()">
                        <i class="fas fa-plus me-1"></i> Crear y Habilitar Votación
                      </button>
                    </div>
                  </form>
                </div>
              </div>

              <div class="card shadow-sm">
                <div class="card-header fw-bold">
                  Votaciones de esta Reunión
                </div>
                <div class="card-body">
                  <p class="text-muted small" id="votacionesStatus">Cargando votaciones...</p>
                  <div id="listaVotacionesMinuta">
                  </div>
                </div>
              </div>

            </div>
          </div>
        </div>
      </div>
      <div class="col-12 mt-2">
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


        <div class="d-flex justify-content-center gap-3 mt-4">
          <div class="text-end mt-3">
            <button type="button" class="btn btn-success fw-bold" onclick="guardarMinutaCompleta()">💾 Guardar Borrador</button>
            <button type="button" class="btn btn-primary fw-bold ms-3" id="btnAprobarMinuta" onclick="aprobarMinuta(idMinutaGlobal)" style="display:none;">Registrar mi firma
            </button>
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
        <div class="form-check mt-2">
          <input class="form-check-input toggle-votacion" type="checkbox" id="checkVotacion_{{index}}">
          <label class="form-check-label fw-semibold text-success" for="checkVotacion_{{index}}">
            Asociar votación existente
          </label>
        </div>

        <div class="mt-2 select-votacion" id="selectVotacion_{{index}}" style="display:none;">
          <label class="form-label">Seleccionar votación habilitada</label>
          <select class="form-select votacion-select" name="idVotacion[]" required>
            <option value="">Seleccione una votación...</option>
            <?php
            // 🟨 MODIFICADO: $vCtrl ya se inicializó al inicio del archivo
            $vCtrl = new VotacionController();
            $votaciones = $vCtrl->listar()['data'] ?? [];
            foreach ($votaciones as $v) {
              if ($v['habilitada'] == 1) {
                echo '<option value="' . $v['idVotacion'] . '">' . htmlspecialchars($v['nombreVotacion']) . '</option>';
              }
            }
            ?>
          </select>
        </div>

      </div>
      <div class="text-end mt-3"> <button type="button" class="btn btn-outline-danger btn-sm eliminar-tema" onclick="eliminarTema(this)" style="display:none;">❌ Eliminar Tema</button> </div>
    </div>
  </template>


  <script>
    // --- Variables Globales (Reducidas) ---
    let contadorTemas = 0;
    const contenedorTemasGlobal = document.getElementById("contenedorTemas");
    let idMinutaGlobal = <?php echo json_encode($idMinutaActual); ?>;

    // ⭐ ================== INICIO BLOQUE MODIFICADO ==================
    // ⭐ CORREGIDO: Usamos el array de presidentes
    const IDS_PRESIDENTES_REQUERIDOS = <?php echo $jsArrayPresidentesRequeridos; ?>; // Esto será un array [10, 15, 20]
    const ESTADO_MINUTA_ACTUAL = <?php echo json_encode($estadoMinuta); ?>;
    const ESTADO_FIRMA_USUARIO = <?php echo json_encode($estadoFirmaUsuario); ?>; // <-- AÑADE ESTA LÍNEA

    // Forzamos el ID de usuario a ser un número para una comparación segura

    // Forzamos el ID de usuario a ser un número para una comparación segura
    const ID_USUARIO_LOGUEADO = <?php echo json_encode($_SESSION['idUsuario'] ? intval($_SESSION['idUsuario']) : null); ?>;
    // ⭐ =================== FIN BLOQUE MODIFICADO ===================

    // Asegurar que sean arrays, incluso si PHP devuelve null
    const DATOS_TEMAS_CARGADOS = <?php echo json_encode($temas_de_la_minuta ?? []); ?>;
    let ASISTENCIA_GUARDADA_IDS = <?php echo json_encode($asistencia_guardada_ids ?? []); ?>; // 🟨 Sigue siendo array de strings
    let btnExportarExcelGlobal = null;

    // --- Evento Principal de Carga (Simplificado) ---
    document.addEventListener("DOMContentLoaded", () => {
      btnExportarExcelGlobal = document.getElementById('btnExportarExcel');

      // --- Cargas iniciales ---
      cargarTablaAsistencia();
      gestionarVisibilidadBotonAprobar(); // ⭐ Llamamos a la función (que ahora está corregida)
      cargarOPrepararTemas();
      // ¡IMPORTANTE! El código para cargar/manejar adjuntos YA ESTÁ EN menu.php
      // Se ejecutará automáticamente porque los IDs del HTML ahora coinciden.

      // Listener Exportar Excel
      if (btnExportarExcelGlobal) {
        btnExportarExcelGlobal.addEventListener('click', function(event) {
          if (this.classList.contains('disabled')) {
            event.preventDefault();
            alert('Debe guardar la asistencia primero antes de exportar.');
          } else if (!idMinutaGlobal) {
            event.preventDefault();
            alert('Error: No se ha definido el ID de la minuta para exportar.');
          }
        });
      }
    });

    // --- Funciones de Carga de Datos (FETCH - SOLO ASISTENCIA) ---
    function cargarTablaAsistencia() {
      // (Copiar la función cargarTablaAsistencia completa de la respuesta anterior aquí)
      fetch("/corevota/controllers/fetch_data.php?action=asistencia_all")
        .then(res => res.ok ? res.json() : Promise.reject(res))
        .then(response => {
          const cont = document.getElementById("contenedorTablaAsistenciaEstado");
          if (response.status === 'success' && response.data && response.data.length > 0) {
            const data = response.data;
            const asistenciaGuardadaStrings = Array.isArray(ASISTENCIA_GUARDADA_IDS) ? ASISTENCIA_GUARDADA_IDS.map(String) : [];
            let tabla = `<table class="table table-sm table-hover" id="tablaAsistenciaEstado"><thead><tr><th style="text-align: left;">Nombre Consejero</th><th style="width: 100px;">Presente</th><th style="width: 100px;">Ausente</th></tr></thead><tbody>`;
            data.forEach(c => {
              const userIdString = String(c.idUsuario);
              const isPresent = asistenciaGuardadaStrings.includes(userIdString);
              const isAbsent = !isPresent;
              tabla += `<tr data-userid="${c.idUsuario}"><td style="text-align: left;"><label class="form-check-label w-100" for="present_${userIdString}">${c.nombreCompleto}</label></td><td><input class="form-check-input asistencia-checkbox present-check" type="checkbox" id="present_${userIdString}" value="${userIdString}" onchange="handleAsistenciaChange('${userIdString}', 'present')" ${isPresent ? 'checked' : ''}></td><td><input class="form-check-input asistencia-checkbox absent-check default-absent" type="checkbox" id="absent_${userIdString}" onchange="handleAsistenciaChange('${userIdString}', 'absent')" ${isAbsent ? 'checked' : ''}></td></tr>`;
            });
            tabla += `</tbody></table>`;
            cont.innerHTML = tabla;
          } else {
            cont.innerHTML = '<p class="text-danger">No hay consejeros para cargar o error en la respuesta.</p>'; // Mensaje mejorado
          }
        })
        .catch(err => {
          console.error("Error carga asistencia:", err);
          const cont = document.getElementById("contenedorTablaAsistenciaEstado");
          if (cont) cont.innerHTML = '<p class="text-danger">Error al conectar para cargar asistencia.</p>'; // Mensaje mejorado
        });
    }

    // --- Lógica Asistencia (SIN CAMBIOS desde la versión anterior) ---
    function handleAsistenciaChange(userId, changedType) {
      // (Función sin cambios)
      const present = document.getElementById(`present_${userId}`);
      const absent = document.getElementById(`absent_${userId}`);
      if (changedType === 'present') {
        absent.checked = !present.checked;
      } else if (changedType === 'absent') {
        present.checked = !absent.checked;
      }
    }

    function recolectarAsistencia() {
      // (Función sin cambios)
      const ids = [];
      const presentes = document.querySelectorAll("#tablaAsistenciaEstado .present-check:checked");
      presentes.forEach(chk => ids.push(chk.value));
      return {
        asistenciaIDs: ids
      };
    }

    function guardarAsistencia() {
      // 🟨 MODIFICADO 🟨
      const {
        asistenciaIDs
      } = recolectarAsistencia();
      const status = document.getElementById('guardarAsistenciaStatus');
      const btn = document.querySelector('#botonesAsistenciaContainer button[onclick="guardarAsistencia()"]');
      let datos = {
        idMinuta: idMinutaGlobal,
        asistencia: asistenciaIDs
      };

      // Si es minuta nueva, necesita encabezado para crearla primero - ESTA LÓGICA YA NO APLICA AQUÍ
      // if (!idMinutaGlobal) { ... } // <- Se puede borrar este if

      btn.disabled = true;
      status.textContent = 'Guardando...';
      status.className = 'me-auto small text-muted';
      if (btnExportarExcelGlobal) {
        btnExportarExcelGlobal.classList.add('disabled');
        btnExportarExcelGlobal.href = '#';
      }

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
            ASISTENCIA_GUARDADA_IDS = asistenciaIDs.map(String); // <-- 🟨 IMPORTANTE: Actualiza la var global

            // Lógica de 'newMinutaId' ya no aplica aquí
            // if (resp.newMinutaId) { ... } // <- Se puede borrar este if

            if (idMinutaGlobal && btnExportarExcelGlobal) {
              btnExportarExcelGlobal.classList.remove('disabled');
              btnExportarExcelGlobal.href = `/corevota/controllers/exportar_asistencia_excel.php?idMinuta=${idMinutaGlobal}`;
            }
            setTimeout(() => {
              status.textContent = '';
            }, 3000);
          } else {
            status.textContent = `⚠️ Error: ${resp.message}`;
            status.className = 'me-auto small text-danger';
            console.error("Error BD asistencia:", resp.error);
            if (btnExportarExcelGlobal) {
              btnExportarExcelGlobal.classList.add('disabled');
              btnExportarExcelGlobal.href = '#';
            }
          }
        })
        .catch(err => {
          btn.disabled = false;
          status.textContent = "Error conexión.";
          status.className = 'me-auto small text-danger';
          console.error("Error fetch asistencia:", err);
          alert("Error al guardar asistencia:\n" + err.message);
          if (btnExportarExcelGlobal) {
            btnExportarExcelGlobal.classList.add('disabled');
            btnExportarExcelGlobal.href = '#';
          }
          setTimeout(() => {
            status.textContent = '';
          }, 5000);
        });
    }


    // --- Lógica TEMAS (SIN CAMBIOS) ---
    function format(command) {
      /* ... sin cambios ... */
      try {
        document.execCommand(command, false, null);
      } catch (e) {
        console.error("Format command failed:", e);
      }
    }

    function cargarOPrepararTemas() {
      /* ... sin cambios ... */
      if (DATOS_TEMAS_CARGADOS && DATOS_TEMAS_CARGADOS.length > 0) {
        DATOS_TEMAS_CARGADOS.forEach(t => crearBloqueTema(t));
      } else {
        crearBloqueTema(); // Crear uno vacío si no hay datos
      }
    }

    function agregarTema() {
      /* ... sin cambios ... */
      crearBloqueTema();
    }

    function crearBloqueTema(tema = null) {
      /* ... 🟨 CORREGIDO 🟨 ... */
      contadorTemas++;
      const plantilla = document.getElementById("plantilla-tema");
      if (!plantilla || !plantilla.content) return; // Verificar que la plantilla exista
      const nuevo = plantilla.content.cloneNode(true);
      const div = nuevo.querySelector('.tema-block');
      if (!div) return; // Verificar elemento principal

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
      nuevo.querySelectorAll('[for*="checkVotacion_"]').forEach(el => { // 🟨 CORREGIDO 🟨
        el.htmlFor = `checkVotacion_${contadorTemas}`;
      });
      nuevo.querySelectorAll('[id*="checkVotacion_"]').forEach(el => { // 🟨 CORREGIDO 🟨
        el.id = `checkVotacion_${contadorTemas}`;
      });
      nuevo.querySelectorAll('[id*="selectVotacion_"]').forEach(el => { // 🟨 CORREGIDO 🟨
        el.id = `selectVotacion_${contadorTemas}`;
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
      if (btnEliminar && contadorTemas > 1) {
        btnEliminar.style.display = 'inline-block';
      } else if (btnEliminar) {
        btnEliminar.style.display = 'none'; // Asegurar que el primero no tenga botón eliminar
      }
      contenedorTemasGlobal.appendChild(nuevo);
    }

    function eliminarTema(btn) {
      /* ... sin cambios ... */
      const temaBlock = btn.closest('.tema-block');
      if (temaBlock) {
        temaBlock.remove();
        actualizarNumerosDeTema();
      }
    }

    function actualizarNumerosDeTema() {
      /* ... 🟨 CORREGIDO 🟨 ... */
      const bloques = contenedorTemasGlobal.querySelectorAll('.tema-block');
      contadorTemas = 0; // Reiniciar contador
      bloques.forEach(b => {
        contadorTemas++;
        const h6 = b.querySelector('h6');
        if (h6) h6.innerText = `Tema ${contadorTemas}`;
        // 🟨 CORRECCIÓN: IDs de votación también deben actualizarse
        b.querySelectorAll('[for*="checkVotacion_"]').forEach(el => {
          el.htmlFor = `checkVotacion_${contadorTemas}`;
        });
        b.querySelectorAll('[id*="checkVotacion_"]').forEach(el => {
          el.id = `checkVotacion_${contadorTemas}`;
        });
        b.querySelectorAll('[id*="selectVotacion_"]').forEach(el => {
          el.id = `selectVotacion_${contadorTemas}`;
        });
        const btnEliminar = b.querySelector('.eliminar-tema');
        if (btnEliminar) btnEliminar.style.display = (contadorTemas > 1) ? 'inline-block' : 'none';
      });
    }

    // --- Lógica ACCIONES FINALES (SIN CAMBIOS) ---

    // ⭐ ================== INICIO BLOQUE MODIFICADO ==================
    // (Línea ~1230)
    function gestionarVisibilidadBotonAprobar() {
      const btn = document.getElementById('btnAprobarMinuta');
      if (!btn) return; // Salir si el botón no existe

      // Comprobamos si el ID del usuario logueado está INCLUIDO en el array de presidentes requeridos
      const esPresidenteRequerido = ID_USUARIO_LOGUEADO && IDS_PRESIDENTES_REQUERIDOS.includes(ID_USUARIO_LOGUEADO);

      // El botón solo se muestra si:
      // 1. La minuta NO está 'APROBADA'
      // 2. Y el usuario logueado ES uno de los presidentes requeridos
      if (idMinutaGlobal && ESTADO_MINUTA_ACTUAL !== 'APROBADA' && esPresidenteRequerido) {
        btn.style.display = 'inline-block';
        btn.disabled = false; // Habilitado por defecto

        // --- NUEVA LÓGICA DE TEXTO Y ESTADO ---
        if (ESTADO_FIRMA_USUARIO === 'PENDIENTE_REVISION') {
          // El secretario editó. El presidente debe re-confirmar.
          btn.innerHTML = '⚠️ Confirmar Cambios y Re-Firmar';
          btn.classList.remove('btn-primary', 'btn-success', 'btn-secondary');
          btn.classList.add('btn-warning', 'fw-bold');
        } else if (ESTADO_FIRMA_USUARIO === 'CONFIRMADA') {
          // El presidente ya firmó y su firma es válida. Está esperando a otros.
          btn.innerHTML = '✅ Firma Registrada (En espera de otros)';
          btn.classList.remove('btn-primary', 'btn-warning', 'btn-secondary');
          btn.classList.add('btn-success');
          btn.disabled = true; // Deshabilitar, ya firmó.
        } else {
          // Es null o PENDIENTE, significa que no ha firmado
          btn.innerHTML = '🔒 Registrar Mi Firma';
          btn.classList.remove('btn-warning', 'btn-success', 'btn-secondary');
          btn.classList.add('btn-primary', 'fw-bold');
        }
        // --- FIN NUEVA LÓGICA ---

      } else {
        btn.style.display = 'none';
      }
    }
    // ⭐ =================== FIN BLOQUE MODIFICADO ===================

    function guardarMinutaCompleta() {
      /* ... 🟨 CORREGIDO 🟨 ... */
      console.log('[DEBUG] Inicio guardarMinutaCompleta. idMinutaGlobal:', idMinutaGlobal);
      if (!idMinutaGlobal || isNaN(parseInt(idMinutaGlobal)) || parseInt(idMinutaGlobal) <= 0) {
        alert("¡Error Crítico JS! El ID de la minuta (idMinutaGlobal) no es válido ANTES de recolectar datos.");
        console.error('[DEBUG] idMinutaGlobal inválido al inicio:', idMinutaGlobal);
        return;
      }

      const {
        asistenciaIDs
      } = recolectarAsistencia();
      const bloques = document.querySelectorAll("#contenedorTemas .tema-block");
      const temasData = [];

      bloques.forEach(b => {
        const c = b.querySelectorAll(".editable-area");
        const n = c[0]?.innerHTML.trim() || "";
        const o = c[1]?.innerHTML.trim() || "";
        temasData.push({
          nombreTema: n,
          objetivo: o,
          descAcuerdo: c[2]?.innerHTML.trim() || "",
          compromiso: c[3]?.innerHTML.trim() || "",
          observacion: c[4]?.innerHTML.trim() || "",
          idTema: b.dataset.idTema || null
        });
      });

      const archivoInput = document.getElementById('adjuntosArchivos'); // Este ID no existe en tu HTML, pero se mantiene por si acaso
      const enlaceInput = document.getElementById('enlaceAdjunto'); // Este ID tampoco
      const archivosParaSubir = archivoInput ? archivoInput.files : [];
      const enlaceValor = enlaceInput ? enlaceInput.value.trim() : '';

      const formData = new FormData();

      console.log('[DEBUG] Valor de idMinutaGlobal ANTES de append:', idMinutaGlobal);
      if (!idMinutaGlobal || isNaN(parseInt(idMinutaGlobal)) || parseInt(idMinutaGlobal) <= 0) {
        alert('¡Error Crítico JS! El ID de la minuta no es válido justo antes de añadirlo a FormData.');
        console.error('[DEBUG] idMinutaGlobal inválido antes de append:', idMinutaGlobal);
        return;
      }
      console.log('[DEBUG] Añadiendo idMinuta a FormData:', idMinutaGlobal);

      formData.append('idMinuta', idMinutaGlobal);
      formData.append('asistencia', JSON.stringify(asistenciaIDs));
      formData.append('temas', JSON.stringify(temasData));

      if (enlaceValor) {
        formData.append('enlaceAdjunto', enlaceValor);
      }

      if (archivosParaSubir.length > 0) {
        for (let i = 0; i < archivosParaSubir.length; i++) {
          formData.append('adjuntos[]', archivosParaSubir[i]);
        }
      }

      const btnGuardar = document.querySelector('button[onclick="guardarMinutaCompleta()"]');
      if (!btnGuardar) return;
      btnGuardar.disabled = true;
      btnGuardar.innerHTML = 'Guardando...';

      console.log('[DEBUG] Enviando datos (FormData):');
      console.log('  idMinuta:', formData.get('idMinuta'));
      console.log('  asistencia:', formData.get('asistencia'));
      console.log('  temas:', formData.get('temas'));

      fetch("/corevota/controllers/guardar_minuta_completa.php", {
          method: "POST",
          body: formData
        })
        .then(res => {
          if (!res.ok) {
            return res.text().then(text => { // <--- 🟨 CORRECCIÓN: Llave faltante
              console.error('[DEBUG] Respuesta no OK del servidor:', res.status, res.statusText, text);
              throw new Error(`Error del servidor (${res.status}): ${text || res.statusText}`);
            });
          }
          return res.json();
        })
        .then(resp => {
          console.log('[DEBUG] Respuesta JSON recibida:', resp);

          btnGuardar.disabled = false;
          btnGuardar.innerHTML = '💾 Guardar Borrador';
          if (resp.status === "success") {
            alert("✅ Minuta guardada correctamente.");

            if (archivoInput) archivoInput.value = '';
            if (enlaceInput) enlaceInput.value = '';

            if (resp.adjuntosActualizados) {
              mostrarAdjuntosExistentes(resp.adjuntosActualizados);
            } else {
              cargarYMostrarAdjuntosExistentes();
            }

            gestionarVisibilidadBotonAprobar();
            if (btnExportarExcelGlobal) {
              if (asistenciaIDs.length > 0) {
                btnExportarExcelGlobal.classList.remove('disabled');
                btnExportarExcelGlobal.href = `/corevota/controllers/exportar_asistencia_excel.php?idMinuta=${idMinutaGlobal}`;
              } else {
                btnExportarExcelGlobal.classList.add('disabled');
                btnExportarExcelGlobal.href = '#';
              }
            }
          } else {
            alert(`⚠️ Error al guardar: ${resp.message}\nDetalles: ${resp.error || 'No disponibles'}`);
            console.error("Error guardado completo (respuesta JSON):", resp.error || resp.message);
          }
        })
        .catch(err => {
          console.error('[DEBUG] Error en fetch o .json():', err);

          btnGuardar.disabled = false;
          btnGuardar.innerHTML = '💾 Guardar Borrador';

          alert("Error de conexión o respuesta inválida al guardar:\n" + err.message);
          console.error("Error fetch-guardar o json():", err);
        });
    } // <- 🟨 CORRECCIÓN: Llave de cierre que faltaba

    function cargarYMostrarAdjuntosExistentes() {
      if (!idMinutaGlobal) return; // No hacer nada si no hay ID de minuta

      fetch(`/corevota/controllers/fetch_data.php?action=adjuntos_por_minuta&idMinuta=${idMinutaGlobal}`)
        .then(response => response.ok ? response.json() : Promise.reject('Error al obtener adjuntos'))
        .then(data => {
          if (data.status === 'success' && data.data) {
            mostrarAdjuntosExistentes(data.data);
          } else {
            mostrarAdjuntosExistentes([]); // Mostrar lista vacía o mensaje
            console.warn('No se encontraron adjuntos o hubo un error:', data.message);
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

      listaUl.innerHTML = ''; // Limpiar lista actual

      if (!adjuntos || adjuntos.length === 0) {
        listaUl.innerHTML = '<li class="list-group-item text-muted">No hay adjuntos guardados para esta minuta.</li>';
        return;
      }

      adjuntos.forEach(adj => {
        const li = document.createElement('li');
        li.className = 'list-group-item d-flex justify-content-between align-items-center';

        const link = document.createElement('a');
        link.href = (adj.tipoAdjunto === 'file') ? `/corevota/public/${adj.pathAdjunto}` : adj.pathAdjunto; // Asume que la ruta guardada es relativa a 'public/'
        link.target = '_blank';
        link.textContent = (adj.tipoAdjunto === 'file') ?
          `📄 ${adj.pathAdjunto.split('/').pop()}` // Mostrar solo nombre de archivo
          :
          `🔗 Enlace Externo`;
        link.title = adj.pathAdjunto; // Mostrar ruta completa en tooltip

        li.appendChild(link);

        /*
        const deleteBtn = document.createElement('button');
        deleteBtn.className = 'btn btn-sm btn-outline-danger ms-2';
        deleteBtn.innerHTML = '<i class="fas fa-trash-alt"></i>';
        deleteBtn.onclick = () => eliminarAdjunto(adj.idAdjunto, li); // Necesitarás implementar eliminarAdjunto
        li.appendChild(deleteBtn);
        */

        listaUl.appendChild(li);
      });
    }

    // --- Llamar a la función al cargar la página ---
    document.addEventListener("DOMContentLoaded", () => {
      // ... (código existente en DOMContentLoaded) ...

      // Cargar adjuntos existentes
      cargarYMostrarAdjuntosExistentes();

    });

    function aprobarMinuta(idMinuta) {
      // (Función sin cambios)
      if (!idMinuta) {
        alert("Error: ID de minuta no válido.");
        return;
      }

      // ⭐ MEJORA: Mostrar un Swal de confirmación más elegante
      Swal.fire({
        title: '¿Confirmar Firma?',
        text: "Está a punto de firmar y aprobar esta minuta. Esta acción es irreversible.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Sí, Firmar y Aprobar',
        cancelButtonText: 'Cancelar'
      }).then((result) => {
        if (result.isConfirmed) {
          // Si confirma, procede con el fetch
          Swal.fire({
            title: 'Procesando Firma...',
            text: 'Por favor espere.',
            allowOutsideClick: false,
            didOpen: () => {
              Swal.showLoading();
            }
          });

          fetch("/corevota/controllers/aprobar_minuta.php", {
              method: "POST",
              headers: {
                "Content-Type": "application/json"
              },
              body: JSON.stringify({
                idMinuta: idMinuta
              })
            })
            .then(res => res.ok ? res.json() : res.text().then(text => Promise.reject(new Error("Respuesta servidor inválida: " + text))))
            .then(response => {
              // ⭐ MEJORA: Respuestas con Swal
              if (response.status === 'success_final') {
                Swal.fire(
                  '¡Aprobada!',
                  'Minuta aprobada y PDF generado con todas las firmas. Será redirigido.',
                  'success'
                ).then(() => {
                  window.location.href = 'menu.php?pagina=minutas_aprobadas';
                });
              } else if (response.status === 'success_partial') {
                Swal.fire(
                  '¡Firma Registrada!',
                  `${response.message} Será redirigido.`,
                  'info'
                ).then(() => {
                  window.location.href = 'menu.php?pagina=minutas_pendientes'; // Redirigir a pendientes
                });
              } else {
                Swal.fire(
                  'Error',
                  `Error al aprobar: ${response.message}`,
                  'error'
                );
              }
            })
            .catch(err => {
              Swal.fire(
                'Error de Red',
                `No se pudo conectar para aprobar: ${err.message}`,
                'error'
              );
            });
        }
      });
    }
    // --- Lógica Mostrar/Ocultar Select Votación ---
    document.addEventListener('change', function(e) {
      if (e.target.classList.contains('toggle-votacion')) {
        const index = e.target.id.split('_')[1];
        const selectDiv = document.getElementById('selectVotacion_' + index);
        if (e.target.checked) {
          selectDiv.style.display = 'block';
        } else {
          selectDiv.style.display = 'none';
          const select = selectDiv.querySelector('select');
          if (select) select.value = '';
        }
      }
    });


    // --- 🔽🔽 INICIO CÓDIGO VOTACIONES (CORREGIDO) 🔽🔽 ---

    // --- Variables Globales para Votación ---
    const ID_REUNION_GLOBAL = <?php echo json_encode($idReunionActual); ?>;
    const ID_SECRETARIO_LOGUEADO = <?php echo json_encode($_SESSION['idUsuario'] ?? null); ?>;
    // Se usará la variable global 'ASISTENCIA_GUARDADA_IDS' (definida arriba)

    // --- Carga Inicial ---
    document.addEventListener("DOMContentLoaded", () => {
      cargarVotacionesDeLaMinuta();
    });

    /**
     * Carga la lista de votaciones ya creadas para esta minuta.
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
                                            <button class="btn btn-warning btn-sm" title="Registrar votos manually" onclick="abrirModalVoto(${v.idVotacion})">
                                                <i class="fas fa-person-booth"></i> Registrar Voto
                                            </button>
                                        </div>
                                    </li>
                                `;
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
     * Llama al controlador para guardar la nueva votación.
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
        if (!resp.ok) throw new Error(`Error HTTP ${resp.status}`);
        const data = await resp.json();

        if (data.status === 'success') {
          Swal.fire('¡Creada!', 'La votación ha sido creada y habilitada. Los usuarios ya pueden votar.', 'success');
          document.getElementById('votacionNombre').value = ''; // Limpiar
          cargarVotacionesDeLaMinuta(); // Recargar la lista
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
     * Abre el modal para que el secretario registre votos manualmente.
     */
    /**
     * Abre el modal para que el secretario registre votos manualmente.
     */
    async function abrirModalVoto(idVotacion) {
      // Usa la variable global 'ASISTENCIA_GUARDADA_IDS'
      if (ASISTENCIA_GUARDADA_IDS.length === 0) {
        Swal.fire('Sin Asistentes', 'No hay asistentes marcados como "Presente" en esta minuta. Guarde la asistencia primero.', 'info');
        return;
      }

      // 1. Mostrar modal de carga
      Swal.fire({
        title: 'Cargando Estado de Votación...',
        text: 'Buscando asistentes y votos...',
        allowOutsideClick: false,
        didOpen: () => {
          Swal.showLoading();
        }
      });

      // 2. Buscar datos (Asistentes y Votos)
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

        // 3. Construir el HTML del Modal
        let modalHtml = '<div class="container-fluid" style="text-align: left;">';
        modalHtml += '<p class="text-muted">Seleccione el voto para cada asistente que no haya votado.</p>';
        modalHtml += '<table class="table table-sm table-hover">';
        modalHtml += '<thead><tr><th>Asistente</th><th class="text-center">Voto Registrado</th><th class="text-center">Registrar Voto</th></tr></thead><tbody>';

        // --- 🟨 INICIO DE LA CORRECCIÓN 🟨 ---
        data.data.asistentes.forEach(asistente => {
          // 'voto' ahora es un string (ej: "SI") o null, gracias al FETCH_KEY_PAIR
          const voto = data.data.votos[asistente.idUsuario] || null;
          modalHtml += `<tr><td>${asistente.nombreCompleto}</td>`;

          if (voto) {
            // Ya votó
            let badge = 'secondary';
            if (voto === 'SI') badge = 'success'; // Compara el string
            if (voto === 'NO') badge = 'danger'; // Compara el string
            modalHtml += `<td class="text-center"><span class="badge bg-${badge}">${voto}</span></td>`; // Muestra el string
            modalHtml += `<td></td>`; // Sin acciones
          } else {
            // No ha votado
            modalHtml += `<td class="text-center"><span class="badge bg-light text-dark">Pendiente</span></td>`;
            modalHtml += `<td class="text-center" style="white-space: nowrap;">
                            <button class="btn btn-success btn-sm" onclick="registrarVotoSecretario(${idVotacion}, ${asistente.idUsuario}, 'SI', this)">SÍ</button>
                            <button class="btn btn-danger btn-sm mx-1" onclick="registrarVotoSecretario(${idVotacion}, ${asistente.idUsuario}, 'NO', this)">NO</button>
                            <button class="btn btn-secondary btn-sm" onclick="registrarVotoSecretario(${idVotacion}, ${asistente.idUsuario}, 'ABSTENCION', this)">ABS</button>
                        </td>`;
          }
          modalHtml += '</tr>';
        });
        // --- 🟨 FIN DE LA CORRECCIÓN 🟨 ---

        modalHtml += '</tbody></table></div>';

        Swal.fire({
          title: 'Registrar Votos Manualmente',
          html: modalHtml, // Corregido (sin 'Read')
          width: '800px',
          showConfirmButton: false,
          showCloseButton: true
        });

      } catch (err) {
        Swal.fire('Error', 'No se pudo cargar el estado de la votación: ' + err.message, 'error');
      }
    }

    // --- 🔼🔼 FIN CÓDIGO VOTACIONES 🔼🔼 ---
  </script>

</body>

</html>