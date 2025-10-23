<?php
// views/pages/asistencia_autogestion.php
require_once __DIR__ . '/../../class/class.conectorDB.php';

// Obtener la lista de reuniones INICIADAS (tienen idMinuta) y PENDIENTES
$reunionesActivas = [];
try {
    $db = new conectorDB();
    $pdo = $db->getDatabase();
    $sql = "SELECT r.idReunion, r.nombreReunion, r.fechaInicioReunion, c.nombreComision, m.idMinuta
            FROM t_reunion r
            JOIN t_minuta m ON r.t_minuta_idMinuta = m.idMinuta
            JOIN t_comision c ON r.t_comision_idComision = c.idComision
            WHERE r.vigente = 1 AND m.estadoMinuta = 'PENDIENTE'
            ORDER BY r.fechaInicioReunion DESC";
    $stmt = $pdo->query($sql);
    $reunionesActivas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $pdo = null;
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Error al cargar reuniones activas: " . $e->getMessage() . "</div>";
}
?>

<div class="container mt-4">
    <h3 class="mb-4">Registro de Asistencia (Consejeros)</h3>
    <p>Aquí puede registrar su asistencia a las reuniones que han sido iniciadas por el Secretario Técnico.</p>

    <div class="list-group shadow-sm">
        <?php if (empty($reunionesActivas)): ?>
            <div class="list-group-item">No hay reuniones activas para registrar asistencia en este momento.</div>
        <?php else: ?>
            <?php foreach ($reunionesActivas as $reunion): ?>
                <div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-1"><?php echo htmlspecialchars($reunion['nombreReunion']); ?></h5>
                        <p class="mb-1 text-muted"><?php echo htmlspecialchars($reunion['nombreComision']); ?></p>
                        <small>Iniciada: <?php echo htmlspecialchars(date('d-m-Y H:i', strtotime($reunion['fechaInicioReunion']))); ?> hrs.</small>
                    </div>
                    <button classt="btn btn-success"
                        id="btn_asistencia_<?php echo $reunion['idMinuta']; ?>"
                        onclick="registrarAsistencia(this, <?php echo $reunion['idMinuta']; ?>)">
                        <i class="fa-solid fa-check me-2"></i> Registrar mi Asistencia
                    </button>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
    function registrarAsistencia(button, idMinuta) {
        if (!idMinuta) {
            alert("Error: ID de reunión no válido.");
            return;
        }

        // Deshabilitar botón para evitar clics múltiples
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Registrando...';

        fetch("/corevota/controllers/AsistenciaController.php", { // Apunta al nuevo controlador
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'autogestionar',
                    idMinuta: idMinuta
                }) // 'action' en el body no es estándar, pero lo pusimos en el controller
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    alert(data.message);
                    // Cambiar botón a estado "Registrado"
                    button.className = "btn btn-outline-success disabled";
                    button.innerHTML = '<i class="fa-solid fa-check-double me-2"></i> Asistencia Registrada';
                } else {
                    alert('Error: ' + data.message);
                    button.disabled = false; // Reactivar si falló
                    button.innerHTML = '<i class="fa-solid fa-check me-2"></i> Registrar mi Asistencia';
                }
            })
            .catch(err => {
                alert('Error de conexión al registrar asistencia.');
                console.error("Error fetch asistencia:", err);
                button.disabled = false;
                button.innerHTML = '<i class="fa-solid fa-check me-2"></i> Registrar mi Asistencia';
            });
    }
</script>