<?php
// views/pages/asistencia_autogestion.php

// 1. CORRECCIÓN DE RUTA: Subir DOS niveles a /corevota/
require_once __DIR__ . '/../../class/class.conectorDB.php';

// 2. ELIMINACIÓN: El session_start() se borra de aquí.
// menu.php (el archivo padre) ya inició la sesión.
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
                     WHERE r.vigente = 1 AND m.estadoMinuta IN ('PENDIENTE', 'BORRADOR', 'PARCIAL')
                     ORDER BY r.fechaInicioReunion DESC"; // Agregado estado PARCIAL
    $stmt_reuniones = $pdo->query($sql_reuniones);
    $reunionesActivas = $stmt_reuniones->fetchAll(PDO::FETCH_ASSOC);

    // 2. Verificar asistencia previa (sin cambios)
    if ($idUsuarioLogueado && !empty($reunionesActivas)) {
        $idsMinutasActivas = array_column($reunionesActivas, 'idMinuta');

        // Evitar error si el array está vacío (aunque ya lo chequeamos)
        if (!empty($idsMinutasActivas)) {
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
    }

    $pdo = null;
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Error al cargar datos: " . htmlspecialchars($e->getMessage()) . "</div>";
}

// 3. OBTENER HORA ACTUAL (CON LA ZONA HORARIA CORRECTA)
// Asume que la zona horaria ya fue establecida en menu.php (America/Santiago)
$ahora = new DateTime();
?>

<div class="container mt-4">
    <h3 class="mb-4">Registro de Asistencia (Consejeros)</h3>
    <p>Aquí puede registrar su asistencia a las reuniones activas. El botón solo estará disponible durante los **primeros 30 minutos** contados desde la hora de inicio de la sesión.</p>

    <div class="list-group shadow-sm">
        <?php if (empty($reunionesActivas)): ?>
            <div class="list-group-item">No hay reuniones activas para registrar asistencia en este momento.</div>
        <?php else: ?>
            <?php foreach ($reunionesActivas as $reunion): ?>
                <?php
                // 4. COMPARAR HORAS DENTRO DEL BUCLE
                $inicioReunion = new DateTime($reunion['fechaInicioReunion']);
                // --- NUEVO CÁLCULO DE LÍMITE ---
                // Calculamos el límite añadiendo 30 minutos a la hora de inicio.
                $limiteRegistro = (clone $inicioReunion)->modify('+30 minutes');
                // Comprueba si la hora actual está DENTRO del período de 30 minutos (inicio <= ahora <= limite)
                $dentroDelPlazo = ($ahora >= $inicioReunion && $ahora <= $limiteRegistro);
                // --- FIN NUEVO CÁLCULO DE LÍMITE ---

                // Comprueba si ya asistió (sin cambios)
                $yaAsistio = in_array($reunion['idMinuta'], $asistenciaUsuario);

                // Formato de la hora de límite para el mensaje
                $limiteFormat = $limiteRegistro->format('H:i');
                ?>
                <div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-1"><?php echo htmlspecialchars($reunion['nombreReunion']); ?></h5>
                        <p class="mb-1 text-muted"><?php echo htmlspecialchars($reunion['nombreComision']); ?></p>
                        <small>
                            Programada: <?php echo htmlspecialchars($inicioReunion->format('d-m-Y H:i')); ?>
                            (Límite de registro: **<?php echo htmlspecialchars($limiteFormat); ?>** hrs.)
                        </small>
                    </div>

                    <?php
                    // 5. LÓGICA CONDICIONAL PARA MOSTRAR EL BOTÓN
                    if ($yaAsistio):
                    ?>
                        <button class="btn btn-outline-success disabled">
                            <i class="fa-solid fa-check-double me-2"></i> Asistencia Registrada
                        </button>
                    <?php elseif ($dentroDelPlazo): // Si NO ha asistido Y está DENTRO del plazo
                    ?>
                        <button class="btn btn-success"
                            id="btn_asistencia_<?php echo $reunion['idMinuta']; ?>"
                            onclick="registrarAsistencia(this, <?php echo $reunion['idMinuta']; ?>)">
                            <i class="fa-solid fa-check me-2"></i> Registrar mi Asistencia
                        </button>
                    <?php else: // Si NO ha asistido Y está FUERA del plazo
                        // Mensaje ajustado para reflejar la regla de 30 minutos
                        $mensajeFueraPlazo = ($ahora < $inicioReunion)
                            ? 'Registro no iniciado (Inicia: ' . $inicioReunion->format('H:i') . ')'
                            : 'Plazo expirado (Límite: ' . $limiteFormat . ' hrs.)';
                    ?>
                        <button class="btn btn-outline-secondary disabled" title="El registro de asistencia sólo está habilitado durante los primeros 30 minutos de la reunión.">
                            <i class="fa-solid fa-clock me-2"></i> <?php echo $mensajeFueraPlazo; ?>
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
                    // Si el registro fue exitoso, actualiza el botón
                    button.className = "btn btn-outline-success disabled";
                    button.innerHTML = '<i class="fa-solid fa-check-double me-2"></i> Asistencia Registrada';
                    // Muestra una notificación de éxito
                    alert('Éxito: ' + data.message);
                } else {
                    // Si hubo un error (incluyendo el error de plazo expirado del controlador)
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