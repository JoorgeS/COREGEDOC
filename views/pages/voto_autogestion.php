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
                    <?php if (empty($votacionVigente)): ?>
                        <div class="alert alert-info text-center mb-0">
                            <i class="fas fa-spinner fa-spin me-2"></i>
                            No hay votaciones habilitadas en este momento. Esperando...
                        </div>
                    <?php else: ?>
                        <h4 class="fw-bold mb-2 text-dark"><?= htmlspecialchars($votacionVigente['nombreVotacion']) ?></h4>
                        <p class="mb-4 text-muted">Comisión: <strong><?= htmlspecialchars($votacionVigente['nombreComision'] ?? 'No definida') ?></strong></p>

                        <form method="post" class="form-voto text-center" id="form-de-votacion" data-nombre="<?= htmlspecialchars($votacionVigente['nombreVotacion']) ?>" <?php if ($yaVoto) echo 'style="display:none;"'; ?>>
                            <input type="hidden" name="idVotacion" value="<?= $votacionVigente['idVotacion'] ?>">
                            <input type="hidden" name="opcionVoto" value="">
                            <h5 class="mb-4">¿Cuál es tu voto?</h5>
                            <div class="d-flex justify-content-center gap-4">
                                <button type="button" class="btn btn-success btn-lg voto-btn px-4 py-2 fw-semibold" data-value="SI">SÍ</button>
                                <button type="button" class="btn btn-danger btn-lg voto-btn px-4 py-2 fw-semibold" data-value="NO">NO</button>
                                <button type="button" class="btn btn-secondary btn-lg voto-btn px-4 py-2 fw-semibold" data-value="ABSTENCION">ABS</button>
                            </div>
                        </form>

                        <div id="dashboard-en-vivo-container" <?php if (!$yaVoto) echo 'style="display:none;"'; ?>>
                            <h5 class="fw-bold mb-3 text-center">Resultados en Vivo</h5>
                            <div class="row text-center">
                                <div class="col-4">
                                    <div class="p-3 bg-success-soft rounded-3">
                                        <h1 class="display-4 fw-bold text-success mb-0" id="total-si">0</h1>
                                        <span class="fw-bold text-success fs-5">SÍ</span>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="p-3 bg-danger-soft rounded-3">
                                        <h1 class="display-4 fw-bold text-danger mb-0" id="total-no">0</h1>
                                        <span class="fw-bold text-danger fs-5">NO</span>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="p-3 bg-secondary-soft rounded-3">
                                        <h1 class="display-4 fw-bold text-secondary mb-0" id="total-abstencion">0</h1>
                                        <span class="fw-bold text-secondary fs-5">ABS</span>
                                    </div>
                                </div>
                            </div>

                            <hr class="my-4">

                            <div class="row">
                                <div class="col-6">
                                    <div class="card h-100 text-center">
                                        <div class="card-body">
                                            <h6 class="card-title text-muted">MI VOTO</h6>
                                            <span class="fs-4 fw-bold text-primary" id="mi-voto">--</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="card h-100 text-center">
                                        <div class="card-body">
                                            <h6 class="card-title text-muted">FALTAN POR VOTAR</h6>
                                            <span class="fs-4 fw-bold text-warning" id="faltan-votar">--</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer text-muted text-center bg-light mt-3 rounded-3">
                                <i class="fa-solid fa-clock-rotate-left me-1"></i>
                                Actualizado: <span id="hora-actualizacion">--:--:--</span>
                            </div>
                        </div> <?php endif; ?>
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
    .voto-btn { width: 100px; border-radius: 10px; transition: transform 0.2s ease, box-shadow 0.2s ease; }
    .voto-btn:hover { transform: translateY(-3px); box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15); }
    .card { border-radius: 0.5rem; }
    .card-body.py-4 { padding-top: 1.5rem !important; padding-bottom: 1.5rem !important; }
    .card-header.bg-primary { background-color: #0d6efd !important; }
    .bg-success-soft { background-color: rgba(25, 135, 84, 0.1); }
    .bg-danger-soft { background-color: rgba(220, 53, 69, 0.1); }
    .bg-secondary-soft { background-color: rgba(108, 117, 125, 0.1); }
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {

        // --- 1. CONFIGURACIÓN DEL DASHBOARD ---
        const idMinuta = <?= json_encode($idMinuta); ?>;
        const idVotacionActual = <?= $votacionVigente ? json_encode($votacionVigente['idVotacion']) : 'null'; ?>;
        const yaVoto = <?= json_encode($yaVoto); ?>;
        const formVotacion = document.getElementById('form-de-votacion');
        const dashboardContainer = document.getElementById('dashboard-en-vivo-container');
        let timerInterval = null;
        let votacionEstaAbierta = (idVotacionActual != null); // Estado inicial


        // --- 2. LÓGICA DE VOTACIÓN (Tu código original, sin cambios) ---
        document.querySelectorAll('.voto-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                // ... (toda tu lógica de Swal.fire y fetch para votar) ...
                // ... (la dejamos intacta, ya funciona bien) ...
                const form = this.closest('.form-voto');
                const nombre = form.dataset.nombre;
                const opcion = this.dataset.value;
                const idVotacion = form.querySelector('input[name="idVotacion"]').value;

                Swal.fire({
                    title: `¿Confirmas tu voto "${opcion}"?`,
                    text: `Votación: ${nombre}`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#198754',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Sí, votar',
                    cancelButtonText: 'Cancelar'
                }).then(result => {
                    if (result.isConfirmed) {
                        const formData = new FormData();
                        formData.append('idVotacion', idVotacion);
                        formData.append('opcionVoto', opcion);

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
                                    if (formVotacion) {
                                        formVotacion.style.display = 'none';
                                    }
                                    if (dashboardContainer) {
                                        dashboardContainer.style.display = 'block';
                                    }
                                    // Forzamos una actualización inmediata
                                    actualizarResultados(); 
                                } else if (resp.status === 'duplicate') {
                                    Swal.fire('⚠️ Ya registraste tu voto', 'No puedes votar nuevamente.', 'warning');
                                } else if (resp.status === 'unauthorized') {
                                    Swal.fire('❌ Voto no permitido', resp.message || 'Debe registrar su asistencia.', 'error');
                                } else {
                                    Swal.fire('Error', resp.message || 'Inténtalo nuevamente.', 'error');
                                }
                            })
                            .catch(error => {
                                console.error("Error en la promesa fetch:", error);
                                Swal.fire('Error de conexión', 'No se pudo comunicar con el servidor.', 'error');
                            });
                    }
                });
            });
        });


        // --- 3. NUEVAS FUNCIONES DEL DASHBOARD ---

        function iniciarDashboardEnVivo() {
            if (!idMinuta || !idVotacionActual) {
                console.error('Faltan idMinuta o idVotacionActual para iniciar el dashboard.');
                return;
            }
            if (timerInterval) {
                clearInterval(timerInterval);
            }
            
            actualizarResultados(); // Primera llamada inmediata
            timerInterval = setInterval(actualizarResultados, 1000); // Actualiza cada segundo
        }

        async function actualizarResultados() {
            // Si la votación ya se marcó como cerrada, no hacemos nada más.
            if (!votacionEstaAbierta) {
                clearInterval(timerInterval);
                return;
            }

            const elTotalSi = document.getElementById('total-si');
            const elTotalNo = document.getElementById('total-no');
            const elTotalAbs = document.getElementById('total-abstencion');
            const elMiVoto = document.getElementById('mi-voto');
            const elFaltanVotar = document.getElementById('faltan-votar');
            const elHoraActualizacion = document.getElementById('hora-actualizacion');

            if (!elTotalSi || !elMiVoto || !elFaltanVotar) {
                if (timerInterval) clearInterval(timerInterval);
                return;
            }

            try {
                // 1. Llamar al API
                const response = await fetch(`/corevota/controllers/obtener_resultados_votacion.php?idMinuta=${idMinuta}`);
                if (!response.ok) throw new Error(`Error en la respuesta del API: ${response.statusText}`);
                
                const resultadoAPI = await response.json();
                if (resultadoAPI.status !== 'success') throw new Error(resultadoAPI.message || 'El API no devolvió un éxito');

                // 2. Encontrar la votación específica
                const votacion = resultadoAPI.data.find(v => v.idVotacion == idVotacionActual);
                if (!votacion) return; 

                // 3. Actualizar los contadores
                elTotalSi.textContent = votacion.totalSi;
                elTotalNo.textContent = votacion.totalNo;
                elTotalAbs.textContent = votacion.totalAbstencion;
                elFaltanVotar.textContent = votacion.faltanVotar;

                // 4. Actualizar "Mi Voto"
                if (votacion.votoPersonal) {
                    elMiVoto.textContent = votacion.votoPersonal;
                    if (votacion.votoPersonal === 'SI') elMiVoto.className = 'fs-4 fw-bold text-success';
                    else if (votacion.votoPersonal === 'NO') elMiVoto.className = 'fs-4 fw-bold text-danger';
                    else elMiVoto.className = 'fs-4 fw-bold text-secondary';
                } else {
                    elMiVoto.textContent = 'PENDIENTE';
                    elMiVoto.className = 'fs-4 fw-bold text-warning';
                }

                // 5. Actualizar la hora
                elHoraActualizacion.textContent = new Date().toLocaleTimeString('es-CL');

                // 6. Chequeo de estado (para cierre automático)
                const nuevoEstado = (votacion.habilitada == 1); 

                if (votacionEstaAbierta && !nuevoEstado) {
                    // ¡ACABA DE CERRARSE!
                    clearInterval(timerInterval); 
                    votacionEstaAbierta = false;  

                    const resultadosHtml = `
                        <div style="text-align: left; padding: 0 1rem; margin-top: 1rem;">
                            <hr>
                            <p style="text-align: center;"><strong>Resultados Finales:</strong></p>
                            <p style="font-size: 1.3rem; text-align: center;">
                                <span style="color: #198754;"><strong>SÍ: ${votacion.totalSi}</span></strong><br>
                                <span style="color: #dc3545;"><strong>NO: ${votacion.totalNo}</span></strong><br>
                                <span style="color: #6c757d;"><strong>ABSTENCIÓN: ${votacion.totalAbstencion}</span></strong>
                            </p>
                        </div>
                    `;

                    Swal.fire({
                        title: 'Votación Cerrada',
                        html: 'La votación ha sido cerrada por el Secretario Técnico.' + resultadosHtml,
                        icon: 'info',
                        allowOutsideClick: false, 
                        allowEscapeKey: false,  
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#0d6efd'
                    }).then(() => {
                        // 🚀 --- INICIO DE LA MODIFICACIÓN --- 🚀
                        // Al hacer clic en OK, reemplazamos el contenido de la tarjeta
                        // con el mensaje de "No hay votaciones".
                        const tarjetaVotacion = document.getElementById('tarjetaVotacionVigente');
                        if (tarjetaVotacion) {
                            tarjetaVotacion.innerHTML = `
                                <div class="alert alert-info text-center mb-0">
                                    <i class="fas fa-spinner fa-spin me-2"></i>
                                    No hay votaciones habilitadas en este momento. Esperando...
                                </div>
                            `;
                            // IMPORTANTE: Reiniciamos el poller para buscar
                            // la *próxima* votación que habilite el ST.
                            iniciarPollerVotacionNueva();
                        }
                        // 🚀 --- FIN DE LA MODIFICACIÓN --- 🚀
                    });
                }

            } catch (error) {
                console.error('Error al actualizar dashboard:', error);
                if (elHoraActualizacion) elHoraActualizacion.textContent = 'Error de conexión';
                if (timerInterval) clearInterval(timerInterval);
            }
        }

        // --- 4. LÓGICA DE INICIO AUTOMÁTICO ---
        
        // Variable para el nuevo poller
        let pollerNuevaVotacion = null;

        /**
         * Esta función revisa si el ST ha habilitado una nueva votación.
         */
        async function verificarVotacionNueva() {
            try {
                // 🚀 Usamos la nueva API que creamos
                const response = await fetch(`/corevota/controllers/verificar_votacion_activa.php`);
                if (!response.ok) {
                    console.warn("Error chequeando nueva votación, se reintentará.");
                    return;
                }
                const data = await response.json();
                
                if (data.status === 'success' && data.votacionActiva === true) {
                    // ¡VOTACIÓN ENCONTRADA!
                    // 1. Detenemos este poller
                    if (pollerNuevaVotacion) clearInterval(pollerNuevaVotacion);

                    // 2. Mostramos un aviso y recargamos
                    Swal.fire({
                        title: '¡Nueva Votación!',
                        text: 'Se ha habilitado una nueva votación.',
                        icon: 'info',
                        timer: 2000,
                        showConfirmButton: false,
                        allowOutsideClick: false
                    }).then(() => {
                        window.location.reload(); // Recarga la página para mostrar los botones
                    });
                }
                // Si es false, no hace nada y sigue sondeando.

            } catch (error) {
                console.error("Error en poller de nueva votación:", error);
            }
        }

        // --- LÓGICA DE ARRANQUE ---
        if (idVotacionActual) {
            // Caso 1: La página cargó CON una votación activa.
            // Iniciamos el dashboard (para tiempo real)
            iniciarDashboardEnVivo(); 
        } else {
            // Caso 2: La página cargó SIN votación activa ("No hay votaciones...").
            // Iniciamos el poller para VERIFICAR si una nueva votación aparece.
            pollerNuevaVotacion = setInterval(verificarVotacionNueva, 3000); // Chequea cada 3 seg
        }
        
    });
</script>