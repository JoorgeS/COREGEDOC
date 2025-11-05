<?php
// views/pages/asistencia_autogestion.php
require_once __DIR__ . '/../../class/class.conectorDB.php'; // Asegura que la timezone 'America/Santiago' esté cargada

// Asegurarse de que la sesión esté iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$idUsuarioLogueado = $_SESSION['idUsuario'] ?? null;

// Obtener la lista de reuniones INICIADAS (tienen idMinuta) y PENDIENTES
$reunionesActivas = [];
$asistenciaUsuario = []; // Para guardar las minutas donde el usuario YA asistió

try {
    $db = new conectorDB();
    $pdo = $db->getDatabase();

    // 1. Obtener reuniones activas (AÑADIMOS fechaTerminoReunion)
    $sql_reuniones = "SELECT 
                        r.idReunion, r.nombreReunion, 
                        r.fechaInicioReunion, r.fechaTerminoReunion, /* <-- Añadido término */
                        c.nombreComision, m.idMinuta
                    FROM t_reunion r
                    JOIN t_minuta m ON r.t_minuta_idMinuta = m.idMinuta
                    JOIN t_comision c ON r.t_comision_idComision = c.idComision
                    WHERE r.vigente = 1 AND m.estadoMinuta IN ('PENDIENTE', 'BORRADOR')
                    ORDER BY r.fechaInicioReunion DESC";
    $stmt_reuniones = $pdo->query($sql_reuniones);
    $reunionesActivas = $stmt_reuniones->fetchAll(PDO::FETCH_ASSOC);

    // 2. Verificar asistencia previa (sin cambios)
    if ($idUsuarioLogueado && !empty($reunionesActivas)) {
        $idsMinutasActivas = array_column($reunionesActivas, 'idMinuta');
        $placeholders = implode(',', array_fill(0, count($idsMinutasActivas), '?'));
        $sql_asistencia = "SELECT t_minuta_idMinuta 
                           FROM t_asistencia 
                           WHERE t_usuario_idUsuario = ? 
                           AND t_minuta_idMinuta IN ({$placeholders})";
        $stmt_asistencia = $pdo->prepare($sql_asistencia);
        $params = array_merge([$idUsuarioLogueado], $idsMinutasActivas);
        $stmt_asistencia->execute($params);
        $asistenciaUsuario = $stmt_asistencia->fetchAll(PDO::FETCH_COLUMN, 0);
    }

    $pdo = null;
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Error al cargar datos: " . htmlspecialchars($e->getMessage()) . "</div>";
}

// 3. OBTENER HORA ACTUAL (CON LA ZONA HORARIA CORRECTA)
$ahora = new DateTime();
?>

<div class="container mt-4">
    <h3 class="mb-4">Registro de Asistencia (Consejeros)</h3>
    <p>Aquí puede registrar su asistencia a las reuniones activas. El botón solo estará disponible durante el horario programado de la reunión.</p>

    <div class="list-group shadow-sm">
        <?php if (empty($reunionesActivas)): ?>
            <div class="list-group-item">No hay reuniones activas para registrar asistencia en este momento.</div>
        <?php else: ?>
            <?php foreach ($reunionesActivas as $reunion): ?>
                <?php
                // 4. COMPARAR HORAS DENTRO DEL BUCLE
                $inicioReunion = new DateTime($reunion['fechaInicioReunion']);
                $terminoReunion = new DateTime($reunion['fechaTerminoReunion']);
                // Comprueba si la hora actual está ENTRE o es IGUAL a inicio Y término
                $dentroDelHorario = ($ahora >= $inicioReunion && $ahora <= $terminoReunion);

                // Comprueba si ya asistió (sin cambios)
                $yaAsistio = in_array($reunion['idMinuta'], $asistenciaUsuario);
                ?>
                <div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-1"><?php echo htmlspecialchars($reunion['nombreReunion']); ?></h5>
                        <p class="mb-1 text-muted"><?php echo htmlspecialchars($reunion['nombreComision']); ?></p>
                        <small>
                            Programada: <?php echo htmlspecialchars($inicioReunion->format('d-m-Y H:i')); ?>
                            a <?php echo htmlspecialchars($terminoReunion->format('H:i')); ?> hrs.
                        </small>
                    </div>

                    <?php
                    // 5. LÓGICA CONDICIONAL PARA MOSTRAR EL BOTÓN
                    if ($yaAsistio):
                    ?>
                        <button class="btn btn-outline-success disabled">
                            <i class="fa-solid fa-check-double me-2"></i> Asistencia Registrada
                        </button>
                    <?php elseif ($dentroDelHorario): // Si NO ha asistido Y está DENTRO del horario 
                    ?>
                        <button class="btn btn-success"
                            id="btn_asistencia_<?php echo $reunion['idMinuta']; ?>"
                            onclick="registrarAsistencia(this, <?php echo $reunion['idMinuta']; ?>)">
                            <i class="fa-solid fa-check me-2"></i> Registrar mi Asistencia
                        </button>
                    <?php else: // Si NO ha asistido Y está FUERA del horario 
                    ?>
                        <button class="btn btn-outline-secondary disabled">
                            <i class="fa-solid fa-clock me-2"></i> Fuera de horario
                        </button>
                    <?php endif; ?>

                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
    // El script de registrarAsistencia() no necesita cambios
    function registrarAsistencia(button, idMinuta) {
        if (!idMinuta) {
            alert("Error: ID de reunión no válido.");
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
                    button.className = "btn btn-outline-success disabled";
                    button.innerHTML = '<i class="fa-solid fa-check-double me-2"></i> Asistencia Registrada';
                } else {
                    alert('Error: ' + data.message);
                    button.disabled = false;
                    button.innerHTML = '<i class="fa-solid fa-check me-2"></i> Registrar mi Asistencia';
                }
            })
            .catch(err => {
                alert('Error de conexión al registrar asistencia.\n' + err.message);
                console.error("Error fetch asistencia:", err);
                button.disabled = false;
                button.innerHTML = '<i class="fa-solid fa-check me-2"></i> Registrar mi Asistencia';
            });
    }
</script>