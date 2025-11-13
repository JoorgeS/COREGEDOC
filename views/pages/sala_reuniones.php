<?php
// views/pages/sala_reuniones.php
// (Página de acción principal de asistencia)

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['idUsuario'])) {
    header("Location: /corevota/views/pages/login.php");
    exit;
}

// 1. OBTENER LÓGICA DE ASISTENCIA
require_once __DIR__ . '/../../class/class.conectorDB.php';

// --- 🚀 CORRECCIÓN AQUÍ ---
// La variable se llamaba $idUsuarioLogueado, pero el enlace (línea 170) usa $idUsuario.
// La renombramos para que coincida.
$idUsuario = $_SESSION['idUsuario'] ?? null;
// --- 🚀 FIN CORRECCIÓN ---


$reunionesParaMostrar = []; // Array final de reuniones a mostrar
$ahora = new DateTime(); 

try {
    $db = new conectorDB();
    $pdo = $db->getDatabase();

    // Regla 2: No mostrar reuniones de ayer o antes.
    $hoyInicio = (new DateTime())->setTime(0, 0, 0);

    // 1. Obtener TODAS las reuniones activas (vigente=1, estado correcto Y de hoy en adelante)
    $sql_reuniones = "SELECT 
                        r.idReunion, r.nombreReunion, 
                        r.fechaInicioReunion, r.fechaTerminoReunion,
                        c.nombreComision, m.idMinuta
                    FROM t_reunion r
                    JOIN t_minuta m ON r.t_minuta_idMinuta = m.idMinuta
                    JOIN t_comision c ON r.t_comision_idComision = c.idComision
                    WHERE r.vigente = 1 
                    AND m.estadoMinuta IN ('PENDIENTE', 'BORRADOR', 'PARCIAL')
                    AND r.fechaInicioReunion >= :hoyInicio  -- <-- REGLA 2 APLICADA
                    ORDER BY r.fechaInicioReunion ASC";
    
    $stmt_reuniones = $pdo->prepare($sql_reuniones);
    $stmt_reuniones->execute([':hoyInicio' => $hoyInicio->format('Y-m-d H:i:s')]);
    $reunionesActivas = $stmt_reuniones->fetchAll(PDO::FETCH_ASSOC);

    // 2. Si encontramos reuniones, verificar asistencia
    if ($idUsuario && !empty($reunionesActivas)) {
        
        $idsMinutasActivas = array_column($reunionesActivas, 'idMinuta');
        $placeholders = implode(',', array_fill(0, count($idsMinutasActivas), '?'));
        
        $sql_asistencia = "SELECT t_minuta_idMinuta 
                           FROM t_asistencia 
                           WHERE t_usuario_idUsuario = ? 
                           AND t_minuta_idMinuta IN ({$placeholders})";
        
        $stmt_asistencia = $pdo->prepare($sql_asistencia);
        $params = array_merge([$idUsuario], $idsMinutasActivas); // Usamos $idUsuario
        $stmt_asistencia->execute($params);
        $asistenciaUsuario = $stmt_asistencia->fetchAll(PDO::FETCH_COLUMN, 0);
        
        // 2b. Regla 1: Filtrar el array para quitar las que ya asistió
        foreach ($reunionesActivas as $reunion) {
            $yaAsistio = in_array($reunion['idMinuta'], $asistenciaUsuario);
            if (!$yaAsistio) {
                // Si no ha asistido, la agregamos a la lista final
                $reunionesParaMostrar[] = $reunion;
            }
        }
    }
    
    $pdo = null;
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Error al cargar datos: " . htmlspecialchars($e->getMessage()) . "</div>";
}
?>

<div class="container mt-5">
    <h3 class="fw-bold text-primary mb-4">
        <i class="fa-solid fa-chalkboard-user me-2"></i> Sala de Reuniones
    </h3>

    <div class="row g-4">

        <div class="col-12">
            <div class="card shadow-lg border-0 rounded-4">
                <div class="card-header bg-primary text-white fw-bold fs-5">
                    <i class="fas fa-hand-paper me-2"></i> 1. Registrar Asistencia
                </div>
                <div class="card-body py-4">
                    
                    <?php if (empty($reunionesParaMostrar)): ?>
                        <div class="alert alert-info text-center mb-0">
                            <i class="fas fa-spinner fa-spin me-2"></i>
                            No hay reuniones habilitadas para registrar asistencia en este momento. Esperando...
                        </div>
                    <?php else: ?>
                        <div class="list-group">
                        <?php foreach ($reunionesParaMostrar as $reunion): ?>
                            <?php
                            // Verificar si está dentro del plazo de 30 minutos
                            $inicioReunion = new DateTime($reunion['fechaInicioReunion']);
                            $limiteRegistro = (clone $inicioReunion)->modify('+30 minutes');
                            $dentroDelPlazo = ($ahora >= $inicioReunion && $ahora <= $limiteRegistro);
                            $limiteFormat = $limiteRegistro->format('H:i');

                            // Definir mensaje de error si está fuera de plazo
                            $mensajeFueraPlazo = "Plazo expirado";
                            if (!$dentroDelPlazo) {
                                $mensajeFueraPlazo = ($ahora < $inicioReunion)
                                    ? 'Registro no iniciado (Inicia: ' . $inicioReunion->format('H:i') . ')'
                                    : 'Plazo expirado (Límite: ' . $limiteFormat . ' hrs.)';
                            }
                            ?>

                            <div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center mb-2 border rounded">
                                <div>
                                    <h5 class="mb-1"><?php echo htmlspecialchars($reunion['nombreReunion']); ?></h5>
                                    <p class="mb-1 text-muted"><?php echo htmlspecialchars($reunion['nombreComision']); ?></p>
                                    <small>
                                        Programada: <?php echo htmlspecialchars((new DateTime($reunion['fechaInicioReunion']))->format('d-m-Y H:i')); ?>
                                        (Límite de registro: <strong><?php echo htmlspecialchars($limiteFormat); ?></strong> hrs.)
                                    </small>
                                </div>

                                <?php
                                // Muestra el botón según el estado
                                if ($dentroDelPlazo): 
                                ?>
                                    <button class="btn btn-success" style="min-width: 200px;"
                                        id="btn_asistencia_<?php echo $reunion['idMinuta']; ?>"
                                        onclick="registrarAsistencia(this, <?php echo $reunion['idMinuta']; ?>)">
                                        <i class="fa-solid fa-check me-2"></i> Registrar mi Asistencia
                                    </button>
                                <?php else: // Si está FUERA del plazo
                                ?>
                                    <button class="btn btn-outline-secondary disabled" style="min-width: 200px;" title="El registro de asistencia sólo está habilitado durante los primeros 30 minutos de la reunión.">
                                        <i class="fa-solid fa-clock me-2"></i> <?php echo $mensajeFueraPlazo; ?>
                                    </button>
                                <?php endif; ?>
                            </div>

                        <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-light fw-bold">
                    <i class="fas fa-calendar-alt me-2 text-info"></i> 2. Calendario
                </div>
                <div class="card-body text-center d-flex flex-column justify-content-center align-items-center">
                    <p class="text-muted">Revise el calendario con todas las reuniones programadas.</p>
                    <a href="menu.php?pagina=reunion_calendario" class="btn btn-info btn-lg mt-auto" style="min-width: 250px;">
                        <i class="fas fa-calendar-alt me-2"></i> Ver Calendario
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
                    <p class="text-muted">Vea un listado de todas las reuniones a las que ha asistido.</p>
                    <a href="menu.php?pagina=historial_asistencia&idUsuario=<?= $idUsuario ?>" class="btn btn-outline-dark btn-lg mt-auto" style="min-width: 250px;">
                        <i class="fas fa-user-check me-2"></i> Ver Mi Historial
                    </a>
                </div>
            </div>
        </div>

    </div>
</div>

<style>
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
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    // --- FUNCIÓN DE REGISTRO (Sin cambios) ---
    function registrarAsistencia(button, idMinuta) {
        if (!idMinuta) {
            Swal.fire('Error', 'ID de reunión no válido.', 'error');
            return;
        }

        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Registrando...';

        fetch("/corevota/controllers/AsistenciaController.php", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    idMinuta: idMinuta
                })
            })
            .then(res => res.ok ? res.json() : res.text().then(text => Promise.reject(new Error("Respuesta inválida: " + text))))
            .then(data => {
                if (data.status === 'success') {
                    
                    const ahora = new Date();
                    const fechaHoraFormato = ahora.toLocaleString('es-CL', {
                        day: '2-digit',
                        month: '2-digit',
                        year: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });

                    button.className = "btn btn-outline-success disabled";
                    button.innerHTML = '<i class="fa-solid fa-check-double me-2"></i> Asistencia Registrada';
                    
                    Swal.fire({
                        title: '¡Éxito!',
                        html: `Asistencia registrada correctamente.<br><br><small><strong>${fechaHoraFormato} hrs.</strong></small>`,
                        icon: 'success',
                        timer: 3000,
                        showConfirmButton: false
                    });
                    
                } else {
                    Swal.fire('Error', data.message, 'error');
                    button.disabled = false;
                    button.innerHTML = '<i class="fa-solid fa-check me-2"></i> Registrar mi Asistencia';
                }
            })
            .catch(err => {
                Swal.fire('Error de Conexión', 'No se pudo conectar con el servidor.\n' + err.message, 'error');
                console.error("Error fetch asistencia:", err);
                button.disabled = false;
                button.innerHTML = '<i class="fa-solid fa-check me-2"></i> Registrar mi Asistencia';
            });
    }

    // --- LÓGICA DE POLLING (Sin cambios) ---
    document.addEventListener('DOMContentLoaded', function() {
        
        const noHabiaReunionesAlCargar = <?php echo json_encode(empty($reunionesParaMostrar)); ?>;
        let pollerNuevaReunion = null;

        async function verificarReunionNueva() {
            try {
                const response = await fetch(`/corevota/controllers/verificar_reunion_activa.php`);
                if (!response.ok) {
                    console.warn("Error chequeando nueva reunión, se reintentará.");
                    return;
                }
                const data = await response.json();
                
                if (data.status === 'success' && data.reunionActiva === true) {
                    if (pollerNuevaReunion) clearInterval(pollerNuevaReunion);

                    Swal.fire({
                        title: '¡Reunión Habilitada!',
                        text: 'Se ha habilitado una nueva reunión para registrar asistencia.',
                        icon: 'info',
                        timer: 2000,
                        showConfirmButton: false,
                        allowOutsideClick: false
                    }).then(() => {
                        window.location.reload(); 
                    });
                }
            } catch (error) {
                console.error("Error en poller de nueva reunión:", error);
            }
        }

        if (noHabiaReunionesAlCargar) {
            pollerNuevaReunion = setInterval(verificarReunionNueva, 3000); // Chequea cada 3 seg
        }

    });
</script>