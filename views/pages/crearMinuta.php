<?php
// views/pages/crearMinuta.php - VERSIÓN CON ENCABEZADO UNIFICADO
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// Asegúrate que la timezone esté definida globalmente (ej. en class.conectorDB.php)
// date_default_timezone_set('America/Santiago'); 

require_once __DIR__ . '/../../class/class.conectorDB.php';

$db = new conectorDB();
$pdo = $db->getDatabase();

$idMinutaActual = $_GET['id'] ?? null;
$minutaData = null;
$reunionData = null; // Para IDs de comisiones mixtas
$temas_de_la_minuta = [];
$asistencia_guardada_ids = [];
$existeAsistenciaGuardada = false;
$secretarioNombre = trim(($_SESSION['pNombre'] ?? '') . ' ' . ($_SESSION['aPaterno'] ?? 'N/A')); // Nombre del usuario logueado

// --- Variables para almacenar los nombres a mostrar en el encabezado ---
$nombreComisionPrincipal = 'N/A';
$nombrePresidentePrincipal = 'N/A';
$nombreComisionMixta1 = null;
$nombrePresidenteMixta1 = null;
$nombreComisionMixta2 = null;
$nombrePresidenteMixta2 = null;
$all_commissions = []; // Array [idComision => ['nombreComision' => ..., 't_usuario_idPresidente' => ...]]
$all_presidents = [];  // Array [idUsuario => 'Nombre Apellido']

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
    $sql_reunion = "SELECT t_comision_idComision, t_comision_idComision_mixta, t_comision_idComision_mixta2 
                        FROM t_reunion 
                        WHERE t_minuta_idMinuta = :idMinutaActual";
    $stmt_reunion = $pdo->prepare($sql_reunion);
    $stmt_reunion->execute([':idMinutaActual' => $idMinutaActual]);
    $reunionData = $stmt_reunion->fetch(PDO::FETCH_ASSOC);
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
    // Usamos el presidente guardado en la minuta (que debería ser el de la com. principal al momento de crear)
    $idPresidentePrincipal = $minutaData['t_usuario_idPresidente'];

    // Buscar nombres usando los arrays cargados
    $nombreComisionPrincipal = $all_commissions[$idComisionPrincipal]['nombreComision'] ?? 'Comisión No Encontrada/Inválida';
    $nombrePresidentePrincipal = $all_presidents[$idPresidentePrincipal] ?? 'Presidente No Encontrado/Inválido';

    // Buscar nombres para comisiones mixtas (si existen en $reunionData)
    if ($reunionData && !empty($reunionData['t_comision_idComision_mixta'])) {
      $idComisionMixta1 = $reunionData['t_comision_idComision_mixta'];
      if (isset($all_commissions[$idComisionMixta1])) {
        $nombreComisionMixta1 = $all_commissions[$idComisionMixta1]['nombreComision'];
        // Buscar el presidente oficial de ESTA comisión mixta
        $idPresidenteMixta1 = $all_commissions[$idComisionMixta1]['t_usuario_idPresidente'] ?? null;
        $nombrePresidenteMixta1 = $idPresidenteMixta1 ? ($all_presidents[$idPresidenteMixta1] ?? 'Presidente No Asignado') : 'N/A';
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
        $idPresidenteMixta2 = $all_commissions[$idComisionMixta2]['t_usuario_idPresidente'] ?? null;
        $nombrePresidenteMixta2 = $idPresidenteMixta2 ? ($all_presidents[$idPresidenteMixta2] ?? 'Presidente No Asignado') : 'N/A';
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
    $asistencia_guardada_ids = $stmt_asistencia->fetchAll(PDO::FETCH_COLUMN, 0);
    $existeAsistenciaGuardada = !empty($asistencia_guardada_ids);
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

// Variables PHP para pasar a JS (solo las necesarias ahora)
$estadoMinuta = $minutaData['estadoMinuta']; // Asegurarse que $minutaData no sea null
// Pasamos el ID del presidente que está *guardado en la minuta* para la lógica JS del botón aprobar
$jsIdPresidenteAsignado = json_encode($minutaData['t_usuario_idPresidente'] ?? null);

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
                  <?php if (!$nombreComisionMixta1 && !$nombreComisionMixta2): // Caso: Comisión Única 
                  ?>
                    <dt class="col-sm-5 col-lg-4">Comisión:</dt>
                    <dd class="col-sm-7 col-lg-8"><?php echo htmlspecialchars($nombreComisionPrincipal); ?></dd>
                    <dt class="col-sm-5 col-lg-4">Presidente:</dt>
                    <dd class="col-sm-7 col-lg-8"><?php echo htmlspecialchars($nombrePresidentePrincipal); ?></dd>
                  <?php else: // Caso: Comisión Mixta/Conjunta 
                  ?>
                    <dt class="col-sm-5 col-lg-4">1° Comisión:</dt>
                    <dd class="col-sm-7 col-lg-8"><?php echo htmlspecialchars($nombreComisionPrincipal); ?></dd>
                    <dt class="col-sm-5 col-lg-4">1° Presidente:</dt>
                    <dd class="col-sm-7 col-lg-8"><?php echo htmlspecialchars($nombrePresidentePrincipal); ?></dd>

                    <?php if ($nombreComisionMixta1): ?>
                      <dt class="col-sm-5 col-lg-4 mt-1">2° Comisión:</dt>
                      <dd class="col-sm-7 col-lg-8 mt-1"><?php echo htmlspecialchars($nombreComisionMixta1); ?></dd>
                      <dt class="col-sm-5 col-lg-4">2° Presidente:</dt>
                      <dd class="col-sm-7 col-lg-8"><?php echo htmlspecialchars($nombrePresidenteMixta1); ?></dd>
                    <?php endif; ?>

                    <?php if ($nombreComisionMixta2): ?>
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
                <a href="#"
                  class="btn btn-success btn-sm <?php echo !$existeAsistenciaGuardada ? 'disabled' : ''; ?>"
                  id="btnExportarExcel"
                  role="button"
                  <?php echo $idMinutaActual ? 'data-idminuta="' . $idMinutaActual . '"' : ''; ?>
                  <?php echo ($existeAsistenciaGuardada && $idMinutaActual) ? 'href="/corevota/controllers/exportar_asistencia_excel.php?idMinuta=' . $idMinutaActual . '"' : 'href="#"'; ?>>
                  <i class="fas fa-file-excel me-1"></i> Exportar Asistencia (Excel)
                </a>
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

        <div class="d-flex justify-content-center gap-3 mt-4">
          <div class="text-end mt-3">
            <button type="button" class="btn btn-success fw-bold" onclick="guardarMinutaCompleta()">💾 Guardar Borrador</button>
            <button type="button" class="btn btn-primary fw-bold ms-3" id="btnAprobarMinuta" onclick="aprobarMinuta(idMinutaGlobal)" style="display:none;">🔒 Firmar y Aprobar</button>
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

  <script src="/corevota/public/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

  <script>
    // --- Variables Globales (Reducidas) ---
    let contadorTemas = 0;
    const contenedorTemasGlobal = document.getElementById("contenedorTemas");
    let idMinutaGlobal = <?php echo json_encode($idMinutaActual); ?>;
    const ID_USUARIO_LOGUEADO = <?php echo json_encode($_SESSION['idUsuario'] ?? null); ?>;
    // El presidente asignado a la minuta (para mostrar/ocultar botón aprobar)
    const ID_PRESIDENTE_ASIGNADO = <?php echo $jsIdPresidenteAsignado; ?>;
    const ESTADO_MINUTA_ACTUAL = <?php echo json_encode($estadoMinuta); ?>;
    // Asegurar que sean arrays, incluso si PHP devuelve null
    const DATOS_TEMAS_CARGADOS = <?php echo json_encode($temas_de_la_minuta ?? []); ?>;
    let ASISTENCIA_GUARDADA_IDS = <?php echo json_encode($asistencia_guardada_ids ?? []); ?>;
    let btnExportarExcelGlobal = null;

    // --- Evento Principal de Carga (Simplificado) ---
    document.addEventListener("DOMContentLoaded", () => {
      btnExportarExcelGlobal = document.getElementById('btnExportarExcel');

      // --- Cargas iniciales ---
      cargarTablaAsistencia();
      gestionarVisibilidadBotonAprobar();
      cargarOPrepararTemas();

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
      /* ... sin cambios ... */
      const present = document.getElementById(`present_${userId}`);
      const absent = document.getElementById(`absent_${userId}`);
      if (changedType === 'present') {
        absent.checked = !present.checked;
      } else if (changedType === 'absent') {
        present.checked = !absent.checked;
      }
    }

    function recolectarAsistencia() {
      /* ... sin cambios ... */
      const ids = [];
      const presentes = document.querySelectorAll("#tablaAsistenciaEstado .present-check:checked");
      presentes.forEach(chk => ids.push(chk.value));
      return {
        asistenciaIDs: ids
      };
    }

    function guardarAsistencia() {
      /* ... sin cambios ... */
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
            ASISTENCIA_GUARDADA_IDS = asistenciaIDs.map(String);

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


    // --- Lógica TEMAS (SIN CAMBIOS desde la versión anterior) ---
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
      /* ... sin cambios ... */
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
      /* ... sin cambios ... */
      const bloques = contenedorTemasGlobal.querySelectorAll('.tema-block');
      contadorTemas = 0; // Reiniciar contador
      bloques.forEach(b => {
        contadorTemas++;
        const h6 = b.querySelector('h6');
        if (h6) h6.innerText = `Tema ${contadorTemas}`;
        const btnEliminar = b.querySelector('.eliminar-tema');
        if (btnEliminar) btnEliminar.style.display = (contadorTemas > 1) ? 'inline-block' : 'none';
      });
    }

    // --- Lógica ACCIONES FINALES (SIN CAMBIOS desde la versión anterior) ---
    function gestionarVisibilidadBotonAprobar() {
      /* ... sin cambios ... */
      const btn = document.getElementById('btnAprobarMinuta');
      // Asegurarse que ID_PRESIDENTE_ASIGNADO no sea null antes de comparar
      if (btn && idMinutaGlobal && ID_USUARIO_LOGUEADO && ID_PRESIDENTE_ASIGNADO !== null && ID_USUARIO_LOGUEADO == ID_PRESIDENTE_ASIGNADO && ESTADO_MINUTA_ACTUAL === 'PENDIENTE') {
        btn.style.display = 'inline-block';
      } else if (btn) {
        btn.style.display = 'none';
      }
    }

    function guardarMinutaCompleta() {
      /* ... sin cambios ... */
      // Ya no necesitamos leer comision1, presidente1, etc. desde el form aquí
      // La información del encabezado es estática.
      // Solo necesitamos recolectar asistencia y temas.

      // Validar que exista un ID de minuta
      if (!idMinutaGlobal) {
        alert("Error: No se puede guardar sin un ID de Minuta válido.");
        return;
      }

      const {
        asistenciaIDs
      } = recolectarAsistencia();
      const bloques = document.querySelectorAll("#contenedorTemas .tema-block"); // Asegurar selector
      const temasData = [];
      if (bloques.length === 0) {
        // Permitir guardar sin temas? O requerir al menos uno? Por ahora permitimos.
        // alert("Agrega al menos un tema."); 
        // return; 
      }
      let errorTema = false;
      bloques.forEach(b => {
        const c = b.querySelectorAll(".editable-area");
        const n = c[0]?.innerHTML.trim() || "";
        const o = c[1]?.innerHTML.trim() || "";
        // Considerar tema válido si tiene nombre O objetivo
        if (!n && !o && bloques.length > 1) {
          // Si hay más de un bloque y este está totalmente vacío, lo marcamos para posible eliminación o ignorarlo
          // Por ahora lo incluimos vacío, el backend podría filtrarlo
        } else if (!n && !o && bloques.length === 1) {
          // Si es el único tema y está vacío, no lo enviamos o mostramos error?
          // Por ahora lo enviamos vacío. El backend decide.
        }
        // Ya no marcamos error si falta nombre u objetivo, el backend lo manejará
        // if (!n || !o) errorTema = true; 
        temasData.push({
          nombreTema: n,
          objetivo: o,
          descAcuerdo: c[2]?.innerHTML.trim() || "",
          compromiso: c[3]?.innerHTML.trim() || "",
          observacion: c[4]?.innerHTML.trim() || "",
          idTema: b.dataset.idTema || null
        });
      });
      // Ya no detenemos por errorTema
      // if (errorTema) { alert("Todos los temas deben tener Nombre y Objetivo."); return; }

      // El objeto datosMinuta ya no se necesita aquí, solo enviamos ID, asistencia y temas
      const datosCompletos = {
        minuta: {
          idMinuta: idMinutaGlobal
        }, // Solo necesitamos el ID para el backend
        asistencia: asistenciaIDs,
        temas: temasData
      };
      const btnGuardar = document.querySelector('button[onclick="guardarMinutaCompleta()"]');
      if (!btnGuardar) return; // Salir si el botón no existe
      btnGuardar.disabled = true;
      btnGuardar.innerHTML = 'Guardando...';

      fetch("/corevota/controllers/guardar_minuta_completa.php", {
          method: "POST",
          headers: {
            "Content-Type": "application/json"
          },
          body: JSON.stringify(datosCompletos)
        })
        .then(res => res.ok ? res.json() : res.text().then(text => Promise.reject(new Error("Respuesta servidor inválida: " + text))))
        .then(resp => {
          btnGuardar.disabled = false;
          btnGuardar.innerHTML = '💾 Guardar Borrador';
          if (resp.status === "success") {
            alert("✅ Minuta guardada correctamente.");
            // No redirigimos, dejamos que el usuario siga editando si quiere
            // window.location.href = `menu.php?pagina=editar_minuta&id=${idMinutaGlobal}`; 

            // Actualizar IDs de temas si se crearon nuevos (opcional, más complejo)
            // Por ahora, recargar la página podría ser lo más simple si se necesita actualizar IDs
            // O simplemente confiar en que el backend maneja bien los inserts/updates

            // Re-evaluar botón aprobar por si el estado cambió (aunque no debería con solo guardar)
            gestionarVisibilidadBotonAprobar();

            // Actualizar estado botón excel si la asistencia cambió
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
            console.error("Error guardado completo:", resp.error);
          }
        })
        .catch(err => {
          btnGuardar.disabled = false;
          btnGuardar.innerHTML = '💾 Guardar Borrador';
          alert("Error de conexión al guardar: " + err.message);
          console.error("Error fetch-guardar:", err);
        });
    }

    function aprobarMinuta(idMinuta) {
      /* ... sin cambios ... */
      if (!idMinuta) {
        alert("Error: ID de minuta no válido.");
        return;
      }
      if (!confirm("¿FIRMAR y APROBAR esta minuta? Esta acción es irreversible.")) return;

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
          if (response.status === 'success') {
            alert("✅ Minuta aprobada correctamente. Será redirigido al listado.");
            window.location.href = 'menu.php?pagina=minutas_aprobadas'; // Redirigir a aprobadas
          } else {
            alert(`⚠️ Error al aprobar la minuta: ${response.message}`);
          }
        })
        .catch(err => alert("Error de red al intentar aprobar la minuta:\n" + err.message));
    }
  </script>

</body>

</html>