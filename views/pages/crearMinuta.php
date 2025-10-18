<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// Variables seguras
$pNombre = isset($_SESSION['pNombre']) ? $_SESSION['pNombre'] : '';
$aPaterno = isset($_SESSION['aPaterno']) ? $_SESSION['aPaterno'] : '';
$nombreUsuario = trim($pNombre . ' ' . $aPaterno);
?>

<link href="/corevota/public/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
<link href="/corevota/public/css/style.css" rel="stylesheet">

<div class="container-fluid app-container p-4 bg-light">
  <h5 class="fw-bold mb-3">GESTIÓN DE LA MINUTA</h5>

  <div class="row g-3">
    <!-- COLUMNA 1: CREAR MINUTA -->
    <div class="col-md-6">
      <div class="dropdown-form-block mb-3">
        <button class="btn btn-success dropdown-toggle w-100 text-start fw-bold"
          type="button" data-bs-toggle="collapse" data-bs-target="#crearMinutaForm"
          aria-expanded="false" aria-controls="crearMinutaForm">
          Crear Minuta
        </button>

        <div class="collapse" id="crearMinutaForm">
          <form class="p-4 border rounded-bottom bg-white">
            <!-- Comisión principal -->
            <div class="mb-3">
              <label for="comision" class="form-label">Seleccionar Comisión</label>
              <select class="form-select" id="comision1"></select>
            </div>

            <div class="mb-3">
              <label for="presidente" class="form-label">Presidente de Comisión</label>
              <select class="form-select" id="presidente1"></select>
            </div>

            <!-- Checkbox para activar Comisión Mixta -->
            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" id="comisionMixta" onchange="toggleComisionMixta()">
              <label class="form-check-label fw-semibold" for="comisionMixta">
                Comisión Mixta
              </label>
            </div>

            <!-- Bloque dinámico de segunda comisión -->
            <div id="bloqueMixta" class="border-top pt-3 mt-3" style="display:none;">
              <div class="mb-3">
                <label for="comision" class="form-label">Seleccionar Segunda Comisión</label>
                <select class="form-select" id="comision2"></select>
              </div>

              <div class="mb-3">
                <label for="presidente" class="form-label">Presidente Segunda Comisión</label>
                <select class="form-select" id="presidente2"></select>
              </div>
            </div>

          </form>
        </div>
      </div>
    </div>
    <!-- COLUMNA 2: DATOS DE SESIÓN -->
    <div class="col-md-6">
      <div class="dropdown-form-block mb-3">
        <button class="btn btn-primary dropdown-toggle w-100 text-start fw-bold"
          type="button" data-bs-toggle="collapse" data-bs-target="#datosSesionForm"
          aria-expanded="false" aria-controls="datosSesionForm">
          Datos de Sesión
        </button>

        <div class="collapse" id="datosSesionForm">
          <form class="p-4 border rounded-bottom bg-white">
            <div class="row">
              <div class="col-md-6 mb-3">
                <label for="hora" class="form-label">Hora</label>
                <input type="time" class="form-control" id="hora" readonly>
              </div>
              <div class="col-md-6 mb-3">
                <label for="nSesion" class="form-label">N° Sesión</label>
                <input type="text" class="form-control" id="nSesion" readonly>
              </div>
              <div class="col-md-6 mb-3">
                <label for="fecha" class="form-label">Fecha</label>
                <input type="date" class="form-control" id="fecha" readonly>
              </div>
              <div class="col-md-6 mb-3">
                <label for="secretario" class="form-label">Secretario Técnico</label>
                <input type="text" class="form-control" id="secretario" readonly>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>


    <div class="col-md-12 mt-4">
      <div class="dropdown-form-block mb-3">
        <button class="btn btn-secondary dropdown-toggle w-100 text-start fw-bold"
          type="button" data-bs-toggle="collapse" data-bs-target="#asistenciaForm"
          aria-expanded="false" aria-controls="asistenciaForm">
          Asistencia
        </button>

        <div class="collapse" id="asistenciaForm">
          <div class="p-4 border rounded-bottom bg-white">

            <div class="mb-3">
              <label for="selectConsejero" class="form-label">Seleccionar Consejero</label>
              <div class="d-flex gap-2">
                <select class="form-select" id="selectConsejero"></select>
                <button type="button" class="btn btn-success" onclick="agregarConsejero()">Agregar</button>
              </div>
            </div>

            <table class="table table-bordered table-striped text-center align-middle mt-3" id="tablaAsistencia">
              <thead class="table-light">
                <tr>
                  <th>Nombre Consejero</th>
                  <th>Acciones</th>
                </tr>
              </thead>
              <tbody>
              </tbody>
            </table>

          </div>
        </div>
      </div>
    </div>

    <div class="col-12 mt-4">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold mb-0">DESARROLLO DE LA MINUTA</h5>
      </div>

      <div id="contenedorTemas">
        <div class="tema-block mb-4 border rounded p-3 bg-white shadow-sm position-relative">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="fw-bold text-primary mb-0">Tema 1</h6>
          </div>

          <div class="dropdown-form-block mb-3">
            <button class="btn btn-light border text-start w-100 fw-bold" type="button"
              data-bs-toggle="collapse" data-bs-target="#temaTratado1"
              aria-expanded="true" aria-controls="temaTratado1">
              TEMA TRATADO
            </button>

            <div class="collapse show" id="temaTratado1">
              <div class="editor-container p-3 border border-top-0 bg-white">
                <div class="bb-editor-toolbar no-select mb-2" role="toolbar">
                  <button type="button" class="btn btn-sm btn-light border me-1" onclick="format('bold')"><b>B</b></button>
                  <button type="button" class="btn btn-sm btn-light border me-1" onclick="format('italic')"><i>I</i></button>
                  <button type="button" class="btn btn-sm btn-light border me-1" onclick="format('underline')"><u>U</u></button>
                </div>
                <div class="editable-area form-control" contenteditable="true" placeholder="Escribe el tema tratado..."></div>
              </div>
            </div>
          </div>

          <div class="dropdown-form-block mb-3">
            <button class="btn btn-light border text-start w-100 fw-bold" type="button"
              data-bs-toggle="collapse" data-bs-target="#objetivo1"
              aria-expanded="false" aria-controls="objetivo1">
              OBJETIVO
            </button>

            <div class="collapse" id="objetivo1">
              <div class="editor-container p-3 border border-top-0 bg-white">
                <div class="bb-editor-toolbar no-select mb-2" role="toolbar">
                  <button type="button" class="btn btn-sm btn-light border me-1" onclick="format('bold')"><b>B</b></button>
                  <button type="button" class="btn btn-sm btn-light border me-1" onclick="format('italic')"><i>I</i></button>
                  <button type="button" class="btn btn-sm btn-light border me-1" onclick="format('underline')"><u>U</u></button>
                </div>
                <div class="editable-area form-control" contenteditable="true" placeholder="Describe el objetivo..."></div>
              </div>
            </div>
          </div>

          <div class="dropdown-form-block mb-3">
            <button class="btn btn-light border text-start w-100 fw-bold" type="button"
              data-bs-toggle="collapse" data-bs-target="#acuerdos1"
              aria-expanded="false" aria-controls="acuerdos1">
              ACUERDOS ADOPTADOS
            </button>

            <div class="collapse" id="acuerdos1">
              <div class="editor-container p-3 border border-top-0 bg-white">
                <div class="bb-editor-toolbar no-select mb-2" role="toolbar">
                  <button type="button" class="btn btn-sm btn-light border me-1" onclick="format('bold')"><b>B</b></button>
                  <button type="button" class="btn btn-sm btn-light border me-1" onclick="format('italic')"><i>I</i></button>
                  <button type="button" class="btn btn-sm btn-light border me-1" onclick="format('underline')"><u>U</u></button>
                </div>
                <div class="editable-area form-control" contenteditable="true" placeholder="Anota los acuerdos adoptados..."></div>
              </div>
            </div>
          </div>

          <div class="dropdown-form-block mb-3">
            <button class="btn btn-light border text-start w-100 fw-bold" type="button"
              data-bs-toggle="collapse" data-bs-target="#compromisos1"
              aria-expanded="false" aria-controls="compromisos1">
              COMPROMISOS Y RESPONSABLES
            </button>

            <div class="collapse" id="compromisos1">
              <div class="editor-container p-3 border border-top-0 bg-white">
                <div class="bb-editor-toolbar no-select mb-2" role="toolbar">
                  <button type="button" class="btn btn-sm btn-light border me-1" onclick="format('bold')"><b>B</b></button>
                  <button type="button" class="btn btn-sm btn-light border me-1" onclick="format('italic')"><i>I</i></button>
                  <button type="button" class="btn btn-sm btn-light border me-1" onclick="format('underline')"><u>U</u></button>
                </div>
                <div class="editable-area form-control" contenteditable="true" placeholder="Registra compromisos y responsables..."></div>
              </div>
            </div>
          </div>

          <div class="dropdown-form-block mb-3">
            <button class="btn btn-light border text-start w-100 fw-bold text-primary" type="button"
              data-bs-toggle="collapse" data-bs-target="#observaciones1"
              aria-expanded="false" aria-controls="observaciones1">
              OBSERVACIONES Y COMENTARIOS
            </button>

            <div class="collapse" id="observaciones1">
              <div class="editor-container p-3 border border-top-0 bg-white">
                <div class="bb-editor-toolbar no-select mb-2" role="toolbar">
                  <button type="button" class="btn btn-sm btn-light border me-1" onclick="format('bold')"><b>B</b></button>
                  <button type="button" class="btn btn-sm btn-light border me-1" onclick="format('italic')"><i>I</i></button>
                  <button type="button" class="btn btn-sm btn-light border me-1" onclick="format('underline')"><u>U</u></button>
                </div>
                <div class="editable-area form-control" contenteditable="true" placeholder="Añade observaciones y comentarios..."></div>
              </div>
            </div>
          </div>


          <div class="text-end mt-3">
            <button type="button" class="btn btn-outline-danger btn-sm eliminar-tema" onclick="eliminarTema(this)" style="display:none;">
              ❌ Eliminar Tema
            </button>
          </div>
        </div>
      </div>
      <button type="button" class="btn btn-outline-dark btn-sm" onclick="agregarTema()">
        Agregar Tema <span class="ms-1">➕</span>
      </button>

      <div class="d-flex justify-content-center gap-3 mt-4">

        <div class="text-end mt-3">

          <button type="button" class="btn btn-success fw-bold" onclick="guardarMinutaCompleta()">
            💾 Registrar Minuta
          </button>
        </div>
      </div>
    </div>

    <script>
      let contadorTemas = 1;

      function format(command) {
        document.execCommand(command, false, null);
      }

      function agregarTema() {
        contadorTemas++;
        const contenedor = document.getElementById('contenedorTemas');
        const bloqueOriginal = document.querySelector('.tema-block');
        const nuevoBloque = bloqueOriginal.cloneNode(true);

        // Actualiza IDs de collapse
        nuevoBloque.querySelectorAll('[id]').forEach((el) => {
          const base = el.id.replace(/\d+$/, '');
          el.id = base + contadorTemas;
        });

        // Actualiza los data-bs-target
        nuevoBloque.querySelectorAll('[data-bs-target]').forEach((btn) => {
          const base = btn.getAttribute('data-bs-target').replace(/\d+$/, '');
          btn.setAttribute('data-bs-target', base + contadorTemas);
          btn.setAttribute('aria-controls', base + contadorTemas);
        });

        // Limpia el contenido editable
        nuevoBloque.querySelectorAll('.editable-area').forEach((area) => {
          area.innerHTML = '';
        });

        // Actualiza título y muestra botón eliminar
        nuevoBloque.querySelector('h6').innerText = `Tema ${contadorTemas}`;
        nuevoBloque.querySelector('.eliminar-tema').style.display = 'inline-block';

        // Animación de aparición
        nuevoBloque.style.opacity = 0;
        contenedor.appendChild(nuevoBloque);
        setTimeout(() => (nuevoBloque.style.opacity = 1), 100);
      }

      function eliminarTema(btn) {
        const bloque = btn.closest('.tema-block');
        bloque.style.transition = 'all 0.3s ease';
        bloque.style.opacity = 0;
        setTimeout(() => bloque.remove(), 300);
      }
    </script>
    <script>
      document.addEventListener("DOMContentLoaded", function() {
        // Carga inicial de la comisión principal
        cargarComisiones("comision1");
        cargarConsejeros("presidente1");
      });

      // Mostrar / ocultar segunda comisión
      function toggleComisionMixta() {
        const check = document.getElementById('comisionMixta');
        const bloque = document.getElementById('bloqueMixta');

        if (check.checked) {
          bloque.style.display = 'block';
          bloque.style.animation = 'fadeIn 0.3s ease-in-out';
          // Cargar las listas del bloque secundario
          cargarComisiones("comision2");
          cargarConsejeros("presidente2");
        } else {
          bloque.style.display = 'none';
          // Limpiar valores del segundo bloque si se desactiva
          document.getElementById("comision2").innerHTML = "";
          document.getElementById("presidente2").innerHTML = "";
        }
      }

      // Función para cargar las comisiones desde el backend
      function cargarComisiones(selectId) {
        fetch("/corevota/controllers/fetch_data.php?action=comisiones")
          .then(res => res.json())
          .then(data => {
            const select = document.getElementById(selectId);
            select.innerHTML = '<option selected disabled>Seleccione...</option>';
            data.forEach(c => {
              select.innerHTML += `<option value="${c.idComision}">${c.nombreComision}</option>`;
            });
          })
          .catch(err => console.error("Error cargando comisiones:", err));
      }

      // Función para cargar los consejeros (presidentes de comisión)
      function cargarConsejeros(selectId) {
        fetch("/corevota/controllers/fetch_data.php?action=presidentes")
          .then(res => res.json())
          .then(data => {
            const select = document.getElementById(selectId);
            select.innerHTML = '<option selected disabled>Seleccione...</option>';
            data.forEach(u => {
              select.innerHTML += `<option value="${u.idUsuario}">${u.nombreCompleto}</option>`;
            });
          })
          .catch(err => console.error("Error cargando consejeros:", err));
      }

      // Pequeña animación para mostrar el bloque de comisión mixta
      const style = document.createElement('style');
      style.innerHTML = `
   @keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
   }
   `;
      document.head.appendChild(style);
    </script>

    <script>
      document.addEventListener("DOMContentLoaded", () => {
        cargarDatosSesion();
      });

      function cargarDatosSesion() {
        // 🕒 Hora actual
        const horaInput = document.getElementById("hora");
        const fechaInput = document.getElementById("fecha");

        const ahora = new Date();
        const horaFormateada = ahora.toTimeString().slice(0, 5); // ← HH:mm exacto
        const fechaFormateada = ahora.toISOString().split('T')[0]; // yyyy-mm-dd

        horaInput.value = horaFormateada;
        fechaInput.value = fechaFormateada;

        // 👤 Secretario técnico (usuario logueado)
        fetch("/corevota/controllers/session_user.php")
          .then(res => res.json())
          .then(data => {
            document.getElementById("secretario").value = data.nombreUsuario || "No definido";
          })
          .catch(() => {
            document.getElementById("secretario").value = "No definido";
          });

        // 🔢 Número de sesión incremental
        fetch("/corevota/controllers/fetch_sesion.php")
          .then(res => res.json())
          .then(data => {
            document.getElementById("nSesion").value = data.numeroSesion.toString().padStart(2, '0');
          })
          .catch(() => {
            document.getElementById("nSesion").value = "1";
          });
      }

      document.addEventListener("DOMContentLoaded", () => {
        cargarConsejerosSelect();
      });

      function cargarConsejerosSelect() {
        fetch("/corevota/controllers/fetch_data.php?action=asistencia_all")
          .then(res => res.json())
          .then(data => {
            const select = document.getElementById("selectConsejero");
            select.innerHTML = '<option selected disabled>Seleccione...</option>';
            data.forEach(c => {
              select.innerHTML += `
         <option value="${c.idUsuario}">
          ${c.nombreCompleto}
         </option>`;
            });
          })
          .catch(err => console.error("Error cargando consejeros:", err));
      }

      function agregarConsejero() {
        const select = document.getElementById("selectConsejero");
        const tabla = document.getElementById("tablaAsistencia").querySelector("tbody");

        const id = select.value;
        const nombre = select.options[select.selectedIndex].text;

        if (!id || id === "Seleccione...") return;

        // Evitar duplicados
        if (document.getElementById(`row-${id}`)) {
          alert("Este consejero ya fue agregado.");
          return;
        }

        const fila = document.createElement("tr");
        fila.id = `row-${id}`;
        fila.innerHTML = `
      <td>${nombre}</td>
      <td>
       <button type="button" class="btn btn-danger btn-sm" onclick="eliminarConsejero('${id}')">
        Quitar
       </button>
      </td>
     `;
        tabla.appendChild(fila);
      }

      function eliminarConsejero(id) {
        const fila = document.getElementById(`row-${id}`);
        if (fila) fila.remove();
      }
    </script>
    <script>
      document.addEventListener("DOMContentLoaded", () => {
        //traerTemasDesdeBD();
      });

      function traerTemasDesdeBD() {
        fetch("/corevota/controllers/fetch_temas.php")
          .then(res => res.json())
          .then(data => {
            const contenedor = document.getElementById("contenedorTemas");
            contenedor.innerHTML = ""; // Limpia el contenedor antes de insertar

            if (data.length === 0) {
              contenedor.innerHTML = `<div class="alert alert-info text-center">No hay temas registrados.</div>`;
              return;
            }

            data.forEach((tema, index) => {
              const num = index + 1;
              contenedor.innerHTML += generarBloqueTema(num, tema);
            });
          })
          .catch(err => console.error("Error cargando temas:", err));
      }

      // Función para crear el bloque HTML de un tema
      function generarBloqueTema(num, tema) {
        return `
 <div class="tema-block mb-4 border rounded p-3 bg-white shadow-sm position-relative">
  <div class="d-flex justify-content-between align-items-center mb-3">
   <h6 class="fw-bold text-primary mb-0">Tema ${num}</h6>
  </div>

  ${crearEditor("TEMA TRATADO", "temaTratado" + num, tema.nombreTema)}
  ${crearEditor("OBJETIVO", "objetivo" + num, tema.objetivo)}
  ${crearEditor("ACUERDOS ADOPTADOS", "acuerdos" + num, tema.descAcuerdo)}
  ${crearEditor("COMPROMISOS Y RESPONSABLES", "compromisos" + num, tema.compromiso)}
  ${crearEditor("OBSERVACIONES Y COMENTARIOS", "observaciones" + num, tema.observacion)}

  <div class="text-end mt-3">
   <button type="button" class="btn btn-outline-danger btn-sm eliminar-tema" onclick="eliminarTema(this)">
    ❌ Eliminar Tema
   </button>
  </div>
 </div>
 `;
      }

      // Función que genera el HTML de cada bloque editable
      function crearEditor(titulo, id, contenido = "") {
        return `
 <div class="dropdown-form-block mb-3">
  <button class="btn btn-light border text-start w-100 fw-bold" type="button"
   data-bs-toggle="collapse" data-bs-target="#${id}" aria-expanded="false" aria-controls="${id}">
   ${titulo}
  </button>
  <div class="collapse" id="${id}">
   <div class="editor-container p-3 border border-top-0 bg-white">
    <div class="bb-editor-toolbar no-select mb-2" role="toolbar">
     <button type="button" class="btn btn-sm btn-light border me-1" onclick="format('bold')"><b>B</b></button>
     <button type="button" class="btn btn-sm btn-light border me-1" onclick="format('italic')"><i>I</i></button>
     <button type="button" class="btn btn-sm btn-light border me-1" onclick="format('underline')"><u>U</u></button>
    </div>
    <div class="editable-area form-control" contenteditable="true">${contenido || ""}</div>
   </div>
  </div>
 </div>`;
      }

      // Botón “Agregar Tema” reutiliza la misma plantilla
      function agregarTema() {
        const contenedor = document.getElementById("contenedorTemas");
        const num = contenedor.children.length + 1;
        contenedor.insertAdjacentHTML("beforeend", generarBloqueTema(num, {}));
      }

      // Eliminar un bloque manualmente
      function eliminarTema(btn) {
        btn.closest(".tema-block").remove();
      }
    </script>

    <script>
      // FUNCIÓN REUTILIZABLE PARA RECOLECTAR ASISTENCIA (IDs y Nombres)
      function recolectarAsistencia() {
        const filasAsistencia = document.querySelectorAll("#tablaAsistencia tbody tr");
        const asistenciaIDs = [];
        const asistenciaNombres = [];

        filasAsistencia.forEach(fila => {
          // El ID es el 'id' de la fila (ej: "row-25")
          const idUsuario = fila.id.replace('row-', '');
          // El nombre es el texto de la primera celda (lo que se ve en la tabla)
          const nombre = fila.children[0].innerText.trim();

          asistenciaIDs.push(idUsuario);
          asistenciaNombres.push(nombre); // <--- AHORA RECOLECTAMOS LOS NOMBRES
        });
        return {
          asistenciaIDs,
          asistenciaNombres
        };

      }


      function guardarMinutaCompleta() {
        // 1. RECOGER DATOS DEL ENCABEZADO (t_minuta)
        const comisionSelect = document.getElementById('comision1');
        const presidenteSelect = document.getElementById('presidente1');
        const comisionMixtaCheck = document.getElementById('comisionMixta');

        // Validación básica del encabezado
        if (!comisionSelect.value || comisionSelect.value === 'Seleccione...') {
          alert("Por favor, selecciona la Comisión principal.");
          return;
        }
        if (!presidenteSelect.value || presidenteSelect.value === 'Seleccione...') {
          alert("Por favor, selecciona el Presidente de la Comisión.");
          return;
        }

        const datosMinuta = {
          // AHORA ENVIAMOS EL ID DE LA COMISIÓN, NO EL NOMBRE
          t_comision_idComision: comisionSelect.value,
          // ENVIAMOS EL ID DEL PRESIDENTE
          t_usuario_idPresidente: presidenteSelect.value,
          horaMinuta: document.getElementById('hora').value,
          fechaMinuta: document.getElementById('fecha').value,
          pathArchivo: "",

          // Datos de Comisión Mixta (si aplica)
          comisionMixta: comisionMixtaCheck.checked ? {
            comision2: document.getElementById('comision2').value,
            presidente2: document.getElementById('presidente2').value
          } : null
        };

        // 2. RECOGER DATOS DE ASISTENCIA (USANDO LA FUNCIÓN NUEVA)
        const {
          asistenciaIDs
        } = recolectarAsistencia();

        if (asistenciaIDs.length === 0) {
          alert("Debes agregar al menos un asistente.");
          return;
        }

        // 3. RECOGER DATOS DE TEMAS (t_tema / t_acuerdo)
        const bloques = document.querySelectorAll(".tema-block");
        const temasData = [];

        let temasIncompletos = 0;
        bloques.forEach((bloque) => {
          const contenido = bloque.querySelectorAll(".editable-area");
          const nombreTema = contenido[0]?.innerText.trim() || "";
          const objetivo = contenido[1]?.innerText.trim() || "";
          const acuerdo = contenido[2]?.innerText.trim() || ""; // descAcuerdo
          const compromiso = contenido[3]?.innerText.trim() || "";
          const observacion = contenido[4]?.innerText.trim() || "";

          if (!nombreTema || !objetivo || !acuerdo) {
            temasIncompletos++;
          }

          temasData.push({
            nombreTema,
            objetivo,
            descAcuerdo: acuerdo,
            compromiso,
            observacion
          });
        });

        // Filtramos los temas que no tienen la información mínima requerida
        const temasValidos = temasData.filter(t => t.nombreTema && t.objetivo && t.descAcuerdo);

        if (temasIncompletos > 0) {
          console.warn(`Advertencia: ${temasIncompletos} tema(s) incompleto(s) serán omitidos.`);
        }

        // 4. ENVIAR DATOS COMPLETOS AL BACKEND
        const datosCompletos = {
          minuta: datosMinuta,
          asistencia: asistenciaIDs, // <-- Array de IDs (para la DB)
          temas: temasValidos
        };

        // Deshabilitar botón y dar feedback
        const botonGuardar = document.querySelector('button[onclick="guardarMinutaCompleta()"]');
        botonGuardar.disabled = true;
        botonGuardar.innerHTML = 'Guardando...';

        fetch("/corevota/controllers/guardar_minuta_completa.php", { // NUEVO ENDPOINT
            method: "POST",
            headers: {
              "Content-Type": "application/json"
            },
            body: JSON.stringify(datosCompletos)
          })
          .then(res => res.json())
          .then(resp => {
            botonGuardar.disabled = false;
            botonGuardar.innerHTML = '💾 Registrar Minuta';

            if (resp.status === "success") {
              alert("✅ Minuta y todos los datos asociados registrados con éxito. ID: " + resp.idMinuta);
              // Opcional: limpiar formulario o redirigir
            } else {
              alert(`⚠️ Error al registrar la Minuta: ${resp.message}`);
              console.error(resp.error);
            }
          })
          .catch(err => {
            botonGuardar.disabled = false;
            botonGuardar.innerHTML = '💾 Registrar Minuta';
            console.error("Error fatal al registrar la minuta:", err);
            alert("Ocurrió un error de conexión al guardar la minuta.");
          });
      }
    </script>

    <script>
      function getDataFromEditableArea(bloque, index) {
        // Mapeo de índices de contenido a nombres de campo
        const fieldNames = ['nombreTema', 'objetivo', 'descAcuerdo', 'compromiso', 'observacion'];
        return bloque.querySelectorAll(".editable-area")[index]?.innerHTML || "";
      }

      function exportarPDF() {
        const bloquesTemas = document.querySelectorAll(".tema-block");
        const temasData = [];

        if (bloquesTemas.length === 0) {
          alert("No hay temas cargados para exportar.");
          return;
        }

        // 1. Recoger datos de los temas
        bloquesTemas.forEach((bloque, index) => {
          temasData.push({
            nombreTema: getDataFromEditableArea(bloque, 0),
            objetivo: getDataFromEditableArea(bloque, 1),
            descAcuerdo: getDataFromEditableArea(bloque, 2),
            compromiso: getDataFromEditableArea(bloque, 3),
            observacion: getDataFromEditableArea(bloque, 4)
          });
        });

        // 2. Recoger datos de la sesión/encabezado
        const datosSesion = {
          hora: document.getElementById('hora')?.value || 'N/A',
          nSesion: document.getElementById('nSesion')?.value || 'N/A',
          fecha: document.getElementById('fecha')?.value || 'N/A',
          secretario: document.getElementById('secretario')?.value || 'N/A',
          comision1: document.getElementById('comision1')?.options[document.getElementById('comision1').selectedIndex].text || 'N/A',
          presidente1: document.getElementById('presidente1')?.options[document.getElementById('presidente1').selectedIndex].text || 'N/A',
          comisionMixta: document.getElementById('comisionMixta')?.checked,
          // Si es mixta, se recogen los datos de la comisión 2
          comision2: document.getElementById('comisionMixta')?.checked ? document.getElementById('comision2')?.options[document.getElementById('comision2').selectedIndex].text : '',
          presidente2: document.getElementById('comisionMixta')?.checked ? document.getElementById('presidente2')?.options[document.getElementById('presidente2').selectedIndex].text : ''
        };

        // 3. Recoger datos de asistencia (sólo los nombres)
        const { asistenciaNombres } = recolectarAsistencia();

        // ...
        // 4. Enviar los datos al script de generación de PDF
        const datosCompletos = {
          ...datosSesion, // Envía todos los datos de sesión/encabezado
          temas: temasData,
          asistencia: asistenciaNombres // <-- Envía el array de NOMBRES
        };

        // Usamos un formulario oculto para enviar datos complejos por POST
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '/corevota/controllers/exportar_pdf.php'; // RUTA DEL NUEVO ARCHIVO
        form.target = '_blank'; // Abrir el PDF en una nueva pestaña (opcional)

        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'pdf_data';
        input.value = JSON.stringify(datosCompletos);

        form.appendChild(input);
        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
      }
    </script>

    <script src="/corevota/public/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>