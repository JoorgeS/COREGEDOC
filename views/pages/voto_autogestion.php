<?php
// views/pages/voto_autogestion.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['idUsuario'])) {
    header("Location: /corevota/views/pages/login.php");
    exit;
}

require_once __DIR__ . '/../../class/class.conectorDB.php';
require_once __DIR__ . '/../../controllers/VotacionController.php';
require_once __DIR__ . '/../../controllers/VotoController.php';

$db = new conectorDB();
$pdo = $db->getDatabase();

$idUsuario = $_SESSION['idUsuario'];
$votacionCtrl = new VotacionController();
$votoCtrl = new VotoController();

// --- 1. LÓGICA DE PROCESAMIENTO POST (VOTO DE AUTOGESTIÓN) ---
// (Esta lógica está intacta y funcional)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['idVotacion'], $_POST['opcionVoto'])) {
    header('Content-Type: application/json');
    $idVotacion = $_POST['idVotacion'];
    $opcionVoto = $_POST['opcionVoto'];
    $response = ['status' => 'error', 'message' => 'Error al registrar voto.'];

    try {
        // A. Verificar si ya votó
        $sqlCheck = "SELECT COUNT(*) FROM t_voto 
          WHERE t_usuario_idUsuario = :idUsuario AND t_votacion_idVotacion = :idVotacion";
        $stmt = $pdo->prepare($sqlCheck);
        $stmt->execute([':idUsuario' => $idUsuario, ':idVotacion' => $idVotacion]);
        if ($stmt->fetchColumn() > 0) {
            echo json_encode(['status' => 'duplicate', 'message' => 'Ya registraste tu voto.']);
            exit;
        }

        // B. Obtener el idMinuta para verificar asistencia
        $sqlMinuta = "SELECT t_minuta_idMinuta FROM t_votacion WHERE idVotacion = :idVotacion";
        $stmtMinuta = $pdo->prepare($sqlMinuta);
        $stmtMinuta->execute([':idVotacion' => $idVotacion]);
        $idMinuta = $stmtMinuta->fetchColumn();

        if (!$idMinuta) {
            throw new Exception('Votación no asociada a ninguna minuta para verificar asistencia.');
        }

        // C. Verificar si el usuario está presente en la minuta (t_asistencia)
        $sqlAsistencia = "SELECT COUNT(*) FROM t_asistencia 
             WHERE t_minuta_idMinuta = :idMinuta AND t_usuario_idUsuario = :idUsuario";
        $stmtAsistencia = $pdo->prepare($sqlAsistencia);
        $stmtAsistencia->execute([':idMinuta' => $idMinuta, ':idUsuario' => $idUsuario]);

        if ($stmtAsistencia->fetchColumn() == 0) {
            echo json_encode([
                'status' => 'unauthorized',
                'message' => 'No puede votar. Debe registrar su asistencia a la reunión correspondiente.'
            ]);
            exit;
        }

        // D. Registrar voto
        $response = $votoCtrl->registrarVotoVotacion(
            (int)$idVotacion,
            (int)$idUsuario,
            (string)$opcionVoto,
            null // Voto de autogestión
        );
        echo json_encode($response);
        exit;
    } catch (Exception $e) {
        error_log("Error en voto_autogestion (POST): " + $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}

// --- 2. LÓGICA DE CARGA DE DATOS (VISTA) ---
// (Modificada para obtener $idMinuta para el dashboard)
$votaciones = $votacionCtrl->listar()['data'] ?? [];
$votacionesHabilitadas = array_filter($votaciones, fn($v) => (int)$v['habilitada'] === 1);
$votacionVigente = reset($votacionesHabilitadas); // Tomamos la primera votación habilitada

$votoPrevio = null;
$yaVoto = false;
$idMinuta = null; // <-- Necesario para el dashboard

if ($votacionVigente) {
    // Obtenemos el idMinuta para pasarlo al JavaScript del dashboard
    $sqlMinuta = "SELECT t_minuta_idMinuta FROM t_votacion WHERE idVotacion = :idVotacion";
    $stmtMinuta = $pdo->prepare($sqlMinuta);
    $stmtMinuta->execute([':idVotacion' => $votacionVigente['idVotacion']]);
    $idMinuta = $stmtMinuta->fetchColumn();

    // Verificamos si el usuario ya votó (lógica original)
    $sqlCheck = "SELECT opcionVoto FROM t_voto 
        WHERE t_usuario_idUsuario = :idUsuario AND t_votacion_idVotacion = :idVotacion";
    $stmt = $pdo->prepare($sqlCheck);
    $stmt->execute([':idUsuario' => $idUsuario, ':idVotacion' => $votacionVigente['idVotacion']]);
    $votoPrevio = $stmt->fetchColumn();
    $yaVoto = !empty($votoPrevio);
}
?>

<div class="container mt-5">
    <h3 class="fw-bold text-primary mb-4">
        <i class="fa-solid fa-person-booth me-2"></i> Sala de Votaciones
    </h3>

    <div class="row g-4">

        <div class="col-12">
            <div class="card shadow-lg border-0 rounded-4">
                <div class="card-header bg-primary text-white fw-bold fs-5">
                    <i class="fas fa-bullhorn me-2"></i> Votación Abierta
                </div>
                <div class="card-body py-4" id="tarjetaVotacionVigente">

                    <div id="votacionContainer">

                        <?php if (empty($votacionVigente)): ?>
                            <div class="alert alert-info text-center mb-0">
                                <i class="fas fa-spinner fa-spin me-2"></i>
                                No hay votaciones habilitadas en este momento.
                            </div>
                        <?php else: ?>
                            <h2 id="tituloVotacion" class="fw-bold mb-2 text-dark text-center">
                                <?= htmlspecialchars($votacionVigente['nombreVotacion']) ?>
                            </h2>
                            <p class="mb-4 text-muted text-center">Comisión: <strong><?= htmlspecialchars($votacionVigente['nombreComision'] ?? 'No definida') ?></strong></p>

                            <div id="opcionesVotoContainer" class="text-center" <?php if ($yaVoto) echo 'style="display:none;"'; ?>>

                                <h5 class="mb-4">¿Cuál es tu voto?</h5>
                                <div class="d-flex justify-content-center gap-4">
                                    <button type="button" class="btn btn-success btn-lg voto-btn px-4 py-2 fw-semibold" data-value="SI" onclick="registrarVoto(<?= $votacionVigente['idVotacion'] ?>, 'SI')">SÍ</button>
                                    <button type="button" class="btn btn-danger btn-lg voto-btn px-4 py-2 fw-semibold" data-value="NO" onclick="registrarVoto(<?= $votacionVigente['idVotacion'] ?>, 'NO')">NO</button>
                                    <button type="button" class="btn btn-secondary btn-lg voto-btn px-4 py-2 fw-semibold" data-value="ABSTENCION" onclick="registrarVoto(<?= $votacionVigente['idVotacion'] ?>, 'ABSTENCION')">ABS</button>
                                </div>

                            </div>

                            <div id="votoPropioContainer" class="my-3" <?php if (!$yaVoto) echo 'style="display:none;"'; ?>>
                                <?php
                                // Si ya votó al cargar, ponemos un mensaje de estado inicial
                                if ($yaVoto) {
                                    echo "<div class='alert alert-info text-center'>Cargando estado de tu voto...</div>";
                                }
                                ?>
                            </div>

                            <div id="dashboardResultados">
                                <div class="alert alert-light text-center" role="alert">
                                    Cargando resultados en vivo...
                                </div>
                            </div>

                        <?php endif; ?>
                    </div>

                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-light fw-bold">
                    <i class="fas fa-chart-bar me-2 text-info"></i> 2. Resultados de Votaciones
                </div>
                <div class="card-body text-center d-flex flex-column justify-content-center align-items-center">
                    <p class="text-muted">Consulta el dashboard restringido con los resultados consolidados de todas las votaciones cerradas.</p>
                    <a href="menu.php?pagina=votacion_listado&filtro_estado=CERRADA" class="btn btn-info btn-lg mt-auto" style="min-width: 250px;">
                        <i class="fas fa-lock me-2"></i> Ver Resultados Consolidados
                    </a>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-light fw-bold">
                    <i class="fas fa-history me-2 text-dark"></i> 3. Mi Historial
                </div>
                <div class="card-body text-center d-flex flex-column justify-content-center align-items-center">
                    <p class="text-muted">Revisa un listado de todas las votaciones en las que has participado y la opción que elegiste en cada una.</p>
                    <a href="menu.php?pagina=historial_votacion&idUsuario=<?= $idUsuario ?>" class="btn btn-outline-dark btn-lg mt-auto" style="min-width: 250px;">
                        <i class="fas fa-user-check me-2"></i> Ver Mi Historial de Votos
                    </a>
                </div>
            </div>
        </div>

    </div>
</div>

<style>
    /* Estilos (sin cambios) */
    .voto-btn {
        width: 100px;
        border-radius: 10px;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .voto-btn:hover {
        transform: translateY(-3px);
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
    }

    .card {
        border-radius: 0.5rem;
    }

    .card-body.py-4 {
        padding-top: 1.5rem !important;
        padding-bottom: 1.5rem !important;
    }

    .card-header.bg-primary {
        background-color: #0d6efd !important;
    }

    .bg-success-soft {
        background-color: rgba(25, 135, 84, 0.1);
    }

    .bg-danger-soft {
        background-color: rgba(220, 53, 69, 0.1);
    }

    .bg-secondary-soft {
        background-color: rgba(108, 117, 125, 0.1);
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    // --- (INICIO DEL NUEVO SCRIPT) ---

    // --- 1. CONFIGURACIÓN GLOBAL ---
    const ID_USUARIO = <?= json_encode($idUsuario); ?>;
    const INTERVALO_POLLING = 3000; // Buscar cambios cada 3 segundos
    let pollerID = null;
    let cacheDatos = ""; // Caché para evitar "parpadeos"
    let idVotacionActivaCache = null; // Para saber en qué votación estamos
    let estadoAnteriorVotacion = null;

    // Contenedores principales (los definimos una sola vez)
    const contenedorPrincipal = document.getElementById('votacionContainer');
    const contenedorOpciones = document.getElementById('opcionesVotoContainer');
    const contenedorVotoPropio = document.getElementById('votoPropioContainer');
    const contenedorResultados = document.getElementById('dashboardResultados');
    const tituloVotacionEl = document.getElementById('tituloVotacion');

    /**
     * Función principal que se ejecuta al cargar la página
     */
    document.addEventListener('DOMContentLoaded', function() {
        iniciarPollingSala();
    });

    /**
     * Inicia el sondeo (polling) para mantener la sala actualizada
     */
    function iniciarPollingSala() {
        if (pollerID !== null) return; // Evitar múltiples pollers
        console.log('Sala de Votaciones: Polling INICIADO.');

        // Ejecutar inmediatamente al cargar
        actualizarSala();

        // Iniciar el intervalo
        pollerID = setInterval(actualizarSala, INTERVALO_POLLING);
    }

    function mostrarNotificacion(nuevoEstado) {
        // 1. CLAVE: Determinamos el estado booleano de forma explícita
        const isAbierta = nuevoEstado === 1;

        // 2. Asignación de contenido (usa el booleano isAbierta)
        const titulo = isAbierta ? '¡VOTACIÓN ABIERTA!' : 'VOTACIÓN CERRADA';
        const texto = isAbierta ? 'El Secretario Técnico ha habilitado la votación.' : 'El Secretario Técnico ha cerrado la votación.';
        const icon = isAbierta ? 'info' : 'warning';

        // 3. Disparo de SweetAlert
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: icon,
            title: titulo,
            text: texto,
            showConfirmButton: false,
            timer: 5000,
            timerProgressBar: true
        });
    }
    /**
     * Busca en el servidor el estado actual de la votación y notifica los cambios.
     */
    async function actualizarSala() {
        try {
            // 1. Fetch de datos sin caché
            const response = await fetch(`/corevota/controllers/obtener_estado_sala_votante.php?_t=${new Date().getTime()}`, {
                method: 'GET',
                cache: 'no-store'
            });

            if (!response.ok) throw new Error('Error de red al obtener el estado.');

            const textoRespuesta = await response.text();

            // 2. Lógica Anti-Parpadeo y Carga
            if (textoRespuesta === cacheDatos) {
                return;
            }
            cacheDatos = textoRespuesta; // Actualizar caché

            const data = JSON.parse(textoRespuesta);
            if (data.status === 'error') throw new Error(data.message);

            // 3. Lógica de Estado y Notificación
            const votacion = data.votacion;
            const nuevoEstado = votacion ? 1 : 0;

            // 🚨 CRÍTICO: Comprobar si el estado ha cambiado y si ya pasamos la carga inicial
            if (estadoAnteriorVotacion !== null && nuevoEstado !== estadoAnteriorVotacion) {
                mostrarNotificacion(nuevoEstado);
            }

            // 4. Actualizar el estado global y renderizar
            estadoAnteriorVotacion = nuevoEstado;
            renderSala(data);

        } catch (error) {
            console.error('Error en el polling de la sala:', error);
        }
    }

    /**
     * Dibuja la interfaz según los datos recibidos del servidor
     */
    function renderSala(data) {
        // CASO 1: No hay votación activa
        if (!data.votacion) {
            idVotacionActivaCache = null;
            contenedorPrincipal.innerHTML = `
        <div class="alert alert-info text-center mb-0">
          <i class="fas fa-spinner fa-spin me-2"></i>
          No hay votaciones habilitadas en este momento.
        </div>`;
            return;
        }

        // Si la votación que llegó es NUEVA, regeneramos el HTML
        if (data.votacion.idVotacion !== idVotacionActivaCache) {
            idVotacionActivaCache = data.votacion.idVotacion;
            const idVot = idVotacionActivaCache; // ID para los botones

            // Regeneramos el HTML base (título, botones, contenedores vacíos)
            contenedorPrincipal.innerHTML = `
        <h2 id="tituloVotacion" class="fw-bold mb-2 text-dark text-center">
          ${escapeHTML(data.votacion.nombreAcuerdo)}
        </h2>
        <p class="mb-4 text-muted text-center">Comisión: <strong>${escapeHTML(data.votacion.nombreComision || 'No definida')}</strong></p>

        <div id="opcionesVotoContainer" class="text-center">
          <h5 class="mb-4">¿Cuál es tu voto?</h5>
          <div class="d-flex justify-content-center gap-4">
            <button type="button" class="btn btn-success btn-lg voto-btn px-4 py-2 fw-semibold" data-value="SI" onclick="registrarVoto(${idVot}, 'SI')">SÍ</button>
            <button type="button" class="btn btn-danger btn-lg voto-btn px-4 py-2 fw-semibold" data-value="NO" onclick="registrarVoto(${idVot}, 'NO')">NO</button>
            <button type="button" class="btn btn-secondary btn-lg voto-btn px-4 py-2 fw-semibold" data-value="ABSTENCION" onclick="registrarVoto(${idVot}, 'ABSTENCION')">ABS</button>
          </div>
        </div>

        <div id="votoPropioContainer" class="my-3" style="display:none;"></div>
        <div id="dashboardResultados"></div>
      `;
        }

        // CASO 2: Hay votación Y el usuario YA VOTÓ
        if (data.votoUsuario) {
            document.getElementById('opcionesVotoContainer').style.display = 'none';
            renderVotoPropio(data.votoUsuario.opcionVoto);
            renderResultados(data.resultados); // Mostrar resultados
        }
        // CASO 3: Hay votación Y el usuario NO HA VOTADO
        else {
            document.getElementById('opcionesVotoContainer').style.display = 'block';
            document.getElementById('votoPropioContainer').style.display = 'none';
            document.getElementById('dashboardResultados').innerHTML = ''; // Ocultar resultados
        }
    }

    /**
     * Dibuja el mensaje "Usted ha votado..."
     */
    function renderVotoPropio(opcionVoto) {
        const container = document.getElementById('votoPropioContainer');
        if (!container) return;

        let badgeClass = 'bg-secondary';
        let opcionTexto = 'Abstención';
        if (opcionVoto === 'SI') {
            badgeClass = 'bg-success';
            opcionTexto = 'Sí';
        } else if (opcionVoto === 'NO') {
            badgeClass = 'bg-danger';
            opcionTexto = 'No';
        }

        container.innerHTML = `
      <div class="alert alert-info text-center mt-3">
        <h5 class="alert-heading">¡Tu voto ha sido registrado!</h5>
        <p class="mb-0">Has votado: <span class="badge ${badgeClass} fs-5">${opcionTexto}</span></p>
      </div>`;
        container.style.display = 'block';
    }

    /**
     * Dibuja el panel de resultados
     */
    function renderResultados(resultados) {
        const container = document.getElementById('dashboardResultados');
        if (!container) return;

        const totalVotantes = resultados.votosSi + resultados.votosNo + resultados.votosAbstencion;
        const faltanVotar = resultados.totalPresentes - totalVotantes;

        container.innerHTML = `
      <div class="card mt-4 shadow-sm">
        <div class="card-header bg-light">
          <h6 class="mb-0">Resultados Preliminares</h6>
        </div>
        <div class="card-body">
          <div class="row text-center">
            <div class="col-3">
              <h4 class="text-success">${resultados.votosSi}</h4>
              <p class="mb-0 small">SÍ</p>
            </div>
            <div class="col-3">
              <h4 class="text-danger">${resultados.votosNo}</h4>
              <p class="mb-0 small">NO</p>
            </div>
            <div class="col-3">
              <h4 class="text-secondary">${resultados.votosAbstencion}</h4>
              <p class="mb-0 small">ABS.</p>
            </div>
            <div class="col-3">
              <h4 class="text-warning">${Math.max(0, faltanVotar)}</h4>
              <p class="mb-0 small">FALTAN</p>
            </div>
          </div>
        </div>
      </div>`;
    }

    /**
     * Esta es la función que llaman los botones.
     * Envía el voto al servidor.
     */
    function registrarVoto(idVotacion, opcionVoto) {
        const nombreVotacion = document.getElementById('tituloVotacion')?.textContent || 'esta votación';

        Swal.fire({
            title: `¿Confirmas tu voto "${opcionVoto}"?`,
            text: `Votación: ${nombreVotacion}`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#198754',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, votar',
            cancelButtonText: 'Cancelar'
        }).then(result => {
            if (result.isConfirmed) {
                // Deshabilitar botones para evitar doble voto
                document.querySelectorAll('.voto-btn').forEach(btn => btn.disabled = true);

                const formData = new FormData();
                formData.append('idVotacion', idVotacion);
                formData.append('opcionVoto', opcionVoto);

                // Usamos la URL de esta misma página (voto_autogestion.php) para el POST
                fetch('voto_autogestion.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(resp => {
                        if (resp.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: '✅ Voto registrado',
                                text: 'Mostrando resultados en vivo...',
                                showConfirmButton: false,
                                timer: 1500
                            });
                            // Forzamos una actualización inmediata de la sala
                            actualizarSala();
                        } else if (resp.status === 'duplicate') {
                            Swal.fire('⚠️ Ya registraste tu voto', 'No puedes votar nuevamente.', 'warning');
                        } else if (resp.status === 'unauthorized') {
                            Swal.fire('❌ Voto no permitido', resp.message || 'Debe registrar su asistencia.', 'error');
                            // Revertimos el disabled, pero la lógica de polling actualizará la UI de todas formas
                        } else {
                            Swal.fire('Error', resp.message || 'Inténtalo nuevamente.', 'error');
                        }
                    })
                    .catch(error => {
                        console.error("Error en la promesa fetch:", error);
                        Swal.fire('Error de conexión', 'No se pudo comunicar con el servidor.', 'error');
                    })
                    .finally(() => {
                        // Volver a habilitar los botones si el voto falló (excepto si fue duplicado)
                        document.querySelectorAll('.voto-btn').forEach(btn => btn.disabled = false);
                    });
            }
        });
    }

    /**
     * Función auxiliar para evitar XSS
     */
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

    // --- (FIN DEL NUEVO SCRIPT) ---
</script>