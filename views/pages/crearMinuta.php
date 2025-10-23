<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// 1. INCLUIR CONEXIÓN Y CARGAR DATOS (SI ES EDICIÓN)
require_once __DIR__ . '/../../class/class.conectorDB.php';
// ❗️ (Opcional pero recomendado) Incluir el modelo para verificar asistencia al cargar
// require_once __DIR__ . '/../../models/minutaModel.php';

$db = new conectorDB();
$pdo = $db->getDatabase();
// $minutaModel = new MinutaModel(); // Descomentar si incluyes el modelo

$idMinutaActual = $_GET['id'] ?? null;
// Datos por defecto
$datos_minuta = [
  't_comision_idComision' => null,
  't_usuario_idPresidente' => null,
  'estadoMinuta' => 'PENDIENTE',
  'fechaMinuta' => null,
  'horaMinuta' => null
];
$temas_de_la_minuta = [];
$asistencia_guardada_ids = []; // IDs de usuarios presentes (se espera array de strings o ints)
$existeAsistenciaGuardada = false; // Para habilitar/deshabilitar botón Excel al cargar

if ($idMinutaActual) {
  try {
    // ❗️ CAMBIO REQ 4: Cargar también fechaMinuta y horaMinuta
    $sql_minuta = "SELECT t_comision_idComision, t_usuario_idPresidente, estadoMinuta, fechaMinuta, horaMinuta 
                   FROM t_minuta 
                   WHERE idMinuta = :idMinutaActual";
    $stmt_minuta = $pdo->prepare($sql_minuta);
    $stmt_minuta->execute([':idMinutaActual' => $idMinutaActual]);
    $minuta_db = $stmt_minuta->fetch(PDO::FETCH_ASSOC);
    if ($minuta_db) {
      $datos_minuta = $minuta_db;
    }

    // Cargar temas asociados
    $sql_temas = "SELECT t.idTema, t.nombreTema, t.objetivo, t.compromiso, t.observacion, a.descAcuerdo
                  FROM t_tema t LEFT JOIN t_acuerdo a ON a.t_tema_idTema = t.idTema
                  WHERE t.t_minuta_idMinuta = :idMinutaActual ORDER BY t.idTema ASC";
    $stmt_temas = $pdo->prepare($sql_temas);
    $stmt_temas->execute([':idMinutaActual' => $idMinutaActual]);
    $temas_de_la_minuta = $stmt_temas->fetchAll(PDO::FETCH_ASSOC);

    // Cargar asistencia guardada (Obtiene array de IDs como string o int)
    $sql_asistencia = "SELECT t_usuario_idUsuario FROM t_asistencia WHERE t_minuta_idMinuta = :idMinutaActual";
    $stmt_asistencia = $pdo->prepare($sql_asistencia);
    $stmt_asistencia->execute([':idMinutaActual' => $idMinutaActual]);
    $asistencia_guardada_ids = $stmt_asistencia->fetchAll(PDO::FETCH_COLUMN, 0);
    $existeAsistenciaGuardada = !empty($asistencia_guardada_ids);
  } catch (Exception $e) {
    error_log("Error cargando datos para edición: " . $e->getMessage());
  }
}

// Variables para PHP y JS
$idComisionGuardada = $datos_minuta['t_comision_idComision'];
$idPresidenteAsignado = $datos_minuta['t_usuario_idPresidente'];
$estadoMinuta = $datos_minuta['estadoMinuta'];
$pNombre = $_SESSION['pNombre'] ?? '';
$aPaterno = $_SESSION['aPaterno'] ?? '';
$nombreUsuario = trim($pNombre . ' ' . $aPaterno);

$fechaMinutaGuardada = $datos_minuta['fechaMinuta'];
$horaMinutaGuardada = $datos_minuta['horaMinuta'];

?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <title>Gestión de Minuta</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="/corevota/public/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="/corevota/public/css/style.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <style>
    /* Estilos específicos */
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
  </style>
</head>

<body>

  <div class="container-fluid app-container p-4 bg-light">
    <h5 class="fw-bold mb-3">GESTIÓN DE LA MINUTA</h5>

    <div class="row g-3">

      <div class="col-md-6">
        <div class="dropdown-form-block mb-3">
          <button class="btn btn-success dropdown-toggle w-100 text-start fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#crearMinutaForm" aria-expanded="false" aria-controls="crearMinutaForm"> Encabezado Minuta </button>
          <div class="collapse" id="crearMinutaForm">
            <form class="p-4 border rounded-bottom bg-white">
              <div class="mb-3"> <label for="comision1" class="form-label">Seleccionar Comisión</label> <select class="form-select" id="comision1"></select> </div>
              <div class="mb-3"> <label for="presidente1" class="form-label">Presidente de Comisión</label> <select class="form-select" id="presidente1"></select> </div>
              <div class="form-check mb-3"> <input class="form-check-input" type="checkbox" id="comisionMixta" onchange="toggleComisionMixta()"> <label class="form-check-label fw-semibold" for="comisionMixta">Comisión Mixta</label> </div>
              <div id="bloqueMixta" class="border-top pt-3 mt-3" style="display:none;">
                <div class="mb-3"><label for="comision2" class="form-label">Segunda Comisión</label><select class="form-select" id="comision2"></select></div>
                <div class="mb-3"><label for="presidente2" class="form-label">Presidente Segunda Comisión</label><select class="form-select" id="presidente2"></select></div>
              </div>
            </form>
          </div>
        </div>
      </div>

      <div class="col-md-6">
        <div class="dropdown-form-block mb-3">
          <button class="btn btn-primary dropdown-toggle w-100 text-start fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#datosSesionForm" aria-expanded="false" aria-controls="datosSesionForm"> Datos de Sesión </button>
          <div class="collapse" id="datosSesionForm">
            <form class="p-4 border rounded-bottom bg-white">
              <div class="row">
                <div class="col-md-6 mb-3"><label for="hora" class="form-label">Hora</label><input type="time" class="form-control" id="hora" readonly></div>
                <div class="col-md-6 mb-3"><label for="nSesion" class="form-label">N° Sesión</label><input type="text" class="form-control" id="nSesion" readonly></div>
                <div class="col-md-6 mb-3"><label for="fecha" class="form-label">Fecha</label><input type="date" class="form-control" id="fecha" readonly></div>
                <div class="col-md-6 mb-3"><label for="secretario" class="form-label">Secretario Técnico</label><input type="text" class="form-control" id="secretario" readonly></div>
              </div>
            </form>
          </div>
        </div>
      </div>

      <div class="col-md-12 mt-4">
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

      <div class="col-12 mt-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h5 class="fw-bold mb-0">DESARROLLO DE LA MINUTA</h5>
        </div>
        <div id="contenedorTemas"></div>
        <button type="button" class="btn btn-outline-dark btn-sm" onclick="agregarTema()">Agregar Tema <span class="ms-1">➕</span></button>

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
    // --- Variables Globales ---
    let contadorTemas = 0;
    const contenedorTemasGlobal = document.getElementById("contenedorTemas");
    let idMinutaGlobal = <?php echo json_encode($idMinutaActual); ?>;
    const ID_USUARIO_LOGUEADO = <?php echo json_encode($_SESSION['idUsuario'] ?? null); ?>;
    const ID_COMISION_GUARDADA = <?php echo json_encode($idComisionGuardada); ?>;
    const ID_PRESIDENTE_ASIGNADO = <?php echo json_encode($idPresidenteAsignado); ?>;
    const ESTADO_MINUTA_ACTUAL = <?php echo json_encode($estadoMinuta); ?>;
    const DATOS_TEMAS_CARGADOS = <?php echo json_encode($temas_de_la_minuta); ?>;
    let ASISTENCIA_GUARDADA_IDS = <?php echo json_encode($asistencia_guardada_ids); ?>; // Usa la variable PHP correcta
    // ⬇️ Referencia al botón Exportar ⬇️
    let btnExportarExcelGlobal = null;
    const FECHA_MINUTA_GUARDADA = <?php echo json_encode($fechaMinutaGuardada); ?>;
    const HORA_MINUTA_GUARDADA = <?php echo json_encode($horaMinutaGuardada); ?>;


    // --- Evento Principal de Carga ---
    document.addEventListener("DOMContentLoaded", () => {
      // ⬇️ Guardar referencia al botón ⬇️
      btnExportarExcelGlobal = document.getElementById('btnExportarExcel');

      cargarComisiones("comision1");
      cargarConsejeros("presidente1");
      cargarDatosSesion();
      cargarTablaAsistencia();
      gestionarVisibilidadBotonAprobar();
      cargarOPrepararTemas();

      // ⬇️ Añadir listener para validación al hacer clic en Exportar ⬇️
      if (btnExportarExcelGlobal) {
        btnExportarExcelGlobal.addEventListener('click', function(event) {
          if (this.classList.contains('disabled')) {
            event.preventDefault(); // Evita seguir el enlace '#'
            alert('Debe guardar la asistencia primero antes de exportar.');
          } else if (!idMinutaGlobal) {
            event.preventDefault();
            alert('Error: No se ha definido el ID de la minuta para exportar.');
          }
          // Si no está deshabilitado y tiene ID, el enlace href funcionará normalmente.
        });
      }
    });

    // --- Funciones de Carga de Datos (FETCH) ---
    function cargarComisiones(selectId) {
      fetch("/corevota/controllers/fetch_data.php?action=comisiones")
        .then(res => res.ok ? res.json() : Promise.reject(res))
        .then(response => { // <--- Cambiado a 'response'
          if (response.status === 'success') { // <--- Añadida esta verificación
            const select = document.getElementById(selectId);
            select.innerHTML = '<option selected disabled value="">Seleccione...</option>';
            response.data.forEach(c => { // <--- Cambiado a 'response.data'
              const isSelected = (selectId === 'comision1' && ID_COMISION_GUARDADA != null && c.idComision == ID_COMISION_GUARDADA) ? 'selected' : '';
              select.innerHTML += `<option value="${c.idComision}" ${isSelected}>${c.nombreComision}</option>`;
            });
            if (selectId === 'comision1' && ID_COMISION_GUARDADA) {
              select.value = ID_COMISION_GUARDADA;
            }
          }
        })
        .catch(err => console.error("Error cargando comisiones:", err));
    }

    function cargarConsejeros(selectId) { // Carga Presidentes/Consejeros
      fetch("/corevota/controllers/fetch_data.php?action=presidentes") // Endpoint para presidentes/consejeros
        .then(res => res.ok ? res.json() : Promise.reject(res))
        .then(response => { // <--- Cambiado a 'response'
          if (response.status === 'success') { // <--- Añadida esta verificación
            const select = document.getElementById(selectId);
            select.innerHTML = '<option selected disabled value="">Seleccione...</option>';
            response.data.forEach(u => { // <--- Cambiado a 'response.data'
              const isSelected = (selectId === 'presidente1' && ID_PRESIDENTE_ASIGNADO != null && u.idUsuario == ID_PRESIDENTE_ASIGNADO) ? 'selected' : '';
              select.innerHTML += `<option value="${u.idUsuario}" ${isSelected}>${u.nombreCompleto}</option>`;
            });
            if (selectId === 'presidente1' && ID_PRESIDENTE_ASIGNADO) {
              select.value = ID_PRESIDENTE_ASIGNADO;
            }
          }
        })
        .catch(err => console.error("Error cargando consejeros:", err));
    }

    function cargarDatosSesion() {
      const horaInput = document.getElementById("hora");
      const fechaInput = document.getElementById("fecha");

      // Usar datos guardados (de la minuta) si existen, si no, usar fecha/hora actual
      if (idMinutaGlobal && FECHA_MINUTA_GUARDADA && HORA_MINUTA_GUARDADA) {
        fechaInput.value = FECHA_MINUTA_GUARDADA;
        horaInput.value = HORA_MINUTA_GUARDADA;
      } else {
        // Si es una minuta nueva (sin ID) o no tiene fecha/hora, usa la actual
        const ahora = new Date();
        horaInput.value = ahora.toTimeString().slice(0, 5);
        fechaInput.value = ahora.toISOString().split('T')[0];
      }

      // Cargar Secretario (sin cambios)
      fetch("/corevota/controllers/session_user.php").then(res => res.json()).then(data => {
        document.getElementById("secretario").value = data.nombreUsuario || "N/D";
      }).catch(() => document.getElementById("secretario").value = "Error");

      // Cargar N° Sesión (sin cambios, ya usa idMinutaGlobal)
      const nSesionInput = document.getElementById("nSesion");
      if (idMinutaGlobal) { // Si estamos editando (idMinutaGlobal tiene un valor)
        nSesionInput.value = String(idMinutaGlobal).padStart(2, '0'); // Muestra el ID de la minuta
      } else { // Si es una minuta nueva
        nSesionInput.value = "Nuevo";
      }
    }

    // --- Lógica del Formulario (Comisión Mixta) ---
    function toggleComisionMixta() {
      const check = document.getElementById('comisionMixta');
      const bloque = document.getElementById('bloqueMixta');
      if (check.checked) {
        bloque.style.display = 'block';
        bloque.style.animation = 'fadeIn 0.3s';
        cargarComisiones("comision2");
        cargarConsejeros("presidente2");
      } else {
        bloque.style.display = 'none';
        document.getElementById("comision2").innerHTML = "";
        document.getElementById("presidente2").innerHTML = "";
      }
    }

    // --- Lógica de ASISTENCIA (Presente/Ausente) ---
    // --- Lógica de ASISTENCIA (Presente/Ausente) ---
    function cargarTablaAsistencia() {
      fetch("/corevota/controllers/fetch_data.php?action=asistencia_all")
        .then(res => res.ok ? res.json() : Promise.reject(res))
        .then(response => { // <--- CAMBIADO a 'response'
          const cont = document.getElementById("contenedorTablaAsistenciaEstado");

          // AÑADIDA esta verificación (igual que en las otras funciones)
          if (response.status === 'success' && response.data && response.data.length > 0) {
            const data = response.data; // <--- Asignamos 'response.data' a 'data'

            // (El resto de tu función original desde aquí hacia abajo)
            // Asegurar que ASISTENCIA_GUARDADA_IDS sea un array de strings
            const asistenciaGuardadaStrings = Array.isArray(ASISTENCIA_GUARDADA_IDS) ? ASISTENCIA_GUARDADA_IDS.map(String) : [];

            let tabla = `<table class="table table-sm table-hover" id="tablaAsistenciaEstado"><thead><tr><th style="text-align: left;">Nombre Consejero</th><th style="width: 100px;">Presente</th><th style="width: 100px;">Ausente</th></tr></thead><tbody>`;
            data.forEach(c => {
              const userIdString = String(c.idUsuario); // Convertir ID a string para comparar
              const isPresent = asistenciaGuardadaStrings.includes(userIdString);
              const isAbsent = !isPresent;
              tabla += `<tr data-userid="${c.idUsuario}"><td style="text-align: left;"><label class="form-check-label w-100" for="present_${userIdString}">${c.nombreCompleto}</label></td><td><input class="form-check-input asistencia-checkbox present-check" type="checkbox" id="present_${userIdString}" value="${userIdString}" onchange="handleAsistenciaChange('${userIdString}', 'present')" ${isPresent ? 'checked' : ''}></td><td><input class="form-check-input asistencia-checkbox absent-check default-absent" type="checkbox" id="absent_${userIdString}" onchange="handleAsistenciaChange('${userIdString}', 'absent')" ${isAbsent ? 'checked' : ''}></td></tr>`;
            });
            tabla += `</tbody></table>`;
            cont.innerHTML = tabla;

          } else { // <--- Bloque 'else' añadido
            cont.innerHTML = '<p class="text-danger">No hay consejeros para cargar.</p>';
          }
        })
        .catch(err => {
          console.error("Error carga asistencia:", err);
          document.getElementById("contenedorTablaAsistenciaEstado").innerHTML = '<p class="text-danger">Error al cargar.</p>';
        });
    }

    function handleAsistenciaChange(userId, changedType) { // userId aquí es string
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
      }; // Devuelve array de strings
    }

    // --- Lógica de TEMAS (Cargar, Crear, Eliminar) ---
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
      const nuevo = plantilla.content.cloneNode(true);
      const div = nuevo.querySelector('.tema-block');
      nuevo.querySelector('h6').innerText = `Tema ${contadorTemas}`;
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
        areas[0].innerHTML = tema.nombreTema || '';
        areas[1].innerHTML = tema.objetivo || '';
        areas[2].innerHTML = tema.descAcuerdo || '';
        areas[3].innerHTML = tema.compromiso || '';
        areas[4].innerHTML = tema.observacion || '';
        div.dataset.idTema = tema.idTema;
      }
      if (contadorTemas > 1) {
        nuevo.querySelector('.eliminar-tema').style.display = 'inline-block';
      }
      contenedorTemasGlobal.appendChild(nuevo);
    }

    function eliminarTema(btn) {
      btn.closest('.tema-block').remove();
      actualizarNumerosDeTema();
    }

    function actualizarNumerosDeTema() {
      const bloques = contenedorTemasGlobal.querySelectorAll('.tema-block');
      contadorTemas = 0;
      bloques.forEach(b => {
        contadorTemas++;
        b.querySelector('h6').innerText = `Tema ${contadorTemas}`;
        b.querySelector('.eliminar-tema').style.display = (contadorTemas > 1) ? 'inline-block' : 'none';
      });
    }

    // --- Lógica de Acciones Finales (Guardar, Aprobar, PDF) ---
    function gestionarVisibilidadBotonAprobar() {
      const btn = document.getElementById('btnAprobarMinuta');
      // Usar idMinutaGlobal y comparación no estricta
      if (btn && idMinutaGlobal && ID_USUARIO_LOGUEADO == ID_PRESIDENTE_ASIGNADO && ESTADO_MINUTA_ACTUAL === 'PENDIENTE') {
        btn.style.display = 'inline-block';
      } else if (btn) {
        btn.style.display = 'none';
      }
    }

    // ❗️ Modificar guardarMinutaCompleta para asegurar que actualiza el idMinutaGlobal
    function guardarMinutaCompleta() {
      const com1 = document.getElementById('comision1');
      const pres1 = document.getElementById('presidente1');
      const mixta = document.getElementById('comisionMixta');
      if (!com1.value) {
        alert("Selecciona Comisión.");
        return;
      }
      if (!pres1.value) {
        alert("Selecciona Presidente.");
        return;
      }

      const datosMinuta = {
        idMinuta: idMinutaGlobal, // Enviar ID actual (puede ser null)
        t_comision_idComision: com1.value,
        t_usuario_idPresidente: pres1.value,
        horaMinuta: document.getElementById('hora').value,
        fechaMinuta: document.getElementById('fecha').value,
        pathArchivo: "", // Se genera al aprobar
        comisionMixta: mixta.checked ? {
          comision2: document.getElementById('comision2').value,
          presidente2: document.getElementById('presidente2').value
        } : null
      };

      const {
        asistenciaIDs
      } = recolectarAsistencia();
      const bloques = document.querySelectorAll(".tema-block");
      const temasData = [];
      if (bloques.length === 0) {
        alert("Agrega al menos un tema.");
        return;
      }
      let errorTema = false;
      bloques.forEach(b => {
        const c = b.querySelectorAll(".editable-area");
        const n = c[0]?.innerHTML.trim() || "";
        const o = c[1]?.innerHTML.trim() || "";
        if (!n || !o) errorTema = true;
        temasData.push({
          nombreTema: n,
          objetivo: o,
          descAcuerdo: c[2]?.innerHTML.trim() || "",
          compromiso: c[3]?.innerHTML.trim() || "",
          observacion: c[4]?.innerHTML.trim() || "",
          idTema: b.dataset.idTema || null // Enviar ID del tema si existe
        });
      });
      if (errorTema) {
        alert("Todos los temas deben tener Nombre y Objetivo.");
        return;
      }

      const datosCompletos = {
        minuta: datosMinuta,
        asistencia: asistenciaIDs,
        temas: temasData
      };
      const btnGuardar = document.querySelector('button[onclick="guardarMinutaCompleta()"]');
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
            alert("✅ Minuta guardada correctamente. ID: " + resp.idMinuta);
            // ❗️ Actualizar idMinutaGlobal si es una nueva minuta O si cambió
            if (resp.idMinuta && idMinutaGlobal !== resp.idMinuta) {
              idMinutaGlobal = resp.idMinuta;
              // ❗️ Actualizar también el botón de exportar y aprobar
              if (btnExportarExcelGlobal) {
                btnExportarExcelGlobal.dataset.idminuta = idMinutaGlobal;
                // Si ya se guardó asistencia, actualizar href
                if (!btnExportarExcelGlobal.classList.contains('disabled')) {
                  btnExportarExcelGlobal.href = `/corevota/controllers/exportar_asistencia_excel.php?idMinuta=${idMinutaGlobal}`;
                }
              }
              gestionarVisibilidadBotonAprobar(); // Reevaluar si se muestra botón aprobar
            }
            // Redirigir siempre a la URL de edición con el ID correcto
            window.location.href = `menu.php?pagina=editar_minuta&id=${idMinutaGlobal}`;
          } else {
            alert(`⚠️ Error al guardar: ${resp.message}\nDetalles: ${resp.error || 'No disponibles'}`);
            console.error("Error guardado completo:", resp.error);
          }
        })
        .catch(err => {
          btnGuardar.disabled = false;
          btnGuardar.innerHTML = '💾 Guardar Borrador';
          alert("Error: " + err.message);
          console.error("Error fetch-guardar:", err);
        });
    }


    // ❗️ Modificar guardarAsistencia para habilitar el botón Excel en éxito
    function guardarAsistencia() {
      const {
        asistenciaIDs
      } = recolectarAsistencia();
      const status = document.getElementById('guardarAsistenciaStatus');
      const btn = document.querySelector('#botonesAsistenciaContainer button[onclick="guardarAsistencia()"]'); // Seleccionar botón correcto

      let datos = {
        idMinuta: idMinutaGlobal,
        asistencia: asistenciaIDs
      };

      // Si es minuta nueva, necesita encabezado para crearla primero
      if (!idMinutaGlobal) {
        const c1 = document.getElementById('comision1');
        const p1 = document.getElementById('presidente1');
        if (!c1.value || !p1.value) {
          alert("Selecciona Comisión y Presidente antes de guardar asistencia por primera vez.");
          return;
        }
        datos.minutaHeader = {
          t_comision_idComision: c1.value,
          t_usuario_idPresidente: p1.value,
          horaMinuta: document.getElementById('hora').value,
          fechaMinuta: document.getElementById('fecha').value
          // No incluir estado aquí, el backend lo pone por defecto
        };
      }

      btn.disabled = true;
      status.textContent = 'Guardando...';
      status.className = 'me-auto small text-muted';
      // ⬇️ Deshabilitar botón Excel mientras guarda ⬇️
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
            ASISTENCIA_GUARDADA_IDS = asistenciaIDs.map(String); // Actualizar estado local

            // Si se creó una nueva minuta, actualizar ID global y redirigir
            if (resp.newMinutaId) {
              alert(`Minuta creada (ID ${resp.newMinutaId}) y asistencia guardada.`);
              idMinutaGlobal = resp.newMinutaId;
              // ❗️ Actualizar botones ANTES de redirigir
              if (btnExportarExcelGlobal) {
                btnExportarExcelGlobal.dataset.idminuta = idMinutaGlobal;
                btnExportarExcelGlobal.classList.remove('disabled'); // Habilitar ahora que hay asistencia
                btnExportarExcelGlobal.href = `/corevota/controllers/exportar_asistencia_excel.php?idMinuta=${idMinutaGlobal}`;
              }
              gestionarVisibilidadBotonAprobar();
              // Redirigir para cargar todo el entorno de edición
              window.location.href = `menu.php?pagina=editar_minuta&id=${idMinutaGlobal}`;
              return; // Detener ejecución para evitar habilitar de nuevo
            }

            // Si ya existía la minuta, solo habilitar el botón
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
            // Asegurarse de que el botón Excel siga deshabilitado si falla
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
          // Asegurarse de que el botón Excel siga deshabilitado si falla
          if (btnExportarExcelGlobal) {
            btnExportarExcelGlobal.classList.add('disabled');
            btnExportarExcelGlobal.href = '#';
          }
          setTimeout(() => {
            status.textContent = '';
          }, 5000);
        });
    }


    function aprobarMinuta(idMinuta) { // Recibe idMinutaGlobal
      if (!idMinuta) {
        alert("Guarda la minuta antes de aprobar.");
        return;
      }
      if (!confirm("¿FIRMAR y APROBAR? ¡Irreversible!")) return;

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
            alert("✅ Minuta aprobada.");
            window.location.href = 'menu.php?pagina=minutas_aprobadas'; // Ir a aprobadas
          } else {
            alert(`⚠️ Error: ${response.message}`);
          }
        })
        .catch(err => alert("Error de red al aprobar:\n" + err.message));
    }

    // Ya no es necesaria, el botón de Excel hace la llamada directa
    // function exportarPDF() { alert("Exportar PDF no implementado."); }
  </script>
  <script src="/corevota/public/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

</body>

</html>