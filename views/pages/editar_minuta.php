<?php
// views/pages/editar_minuta.php
// Implementa la lógica de roles de la minuta.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../class/class.conectorDB.php';

// --- 1. OBTENER DATOS BÁSICOS ---
$idMinuta = (int)($_GET['id'] ?? 0);
$idUsuarioLogueado = (int)($_SESSION['idUsuario'] ?? 0);
$idTipoUsuario = (int)($_SESSION['tipoUsuario_id'] ?? 0);

if ($idMinuta === 0 || $idUsuarioLogueado === 0) {
    echo "<div class='alert alert-danger'>Error: No se pudo cargar la minuta o la sesión es inválida.</div>";
    return;
}

// --- 2. CARGAR DATOS DE LA MINUTA ---
try {
    $db = new conectorDB();
    $pdo = $db->getDatabase();

    // Cargar datos principales de la minuta
    $stmtMinuta = $pdo->prepare("SELECT * FROM t_minuta WHERE idMinuta = :idMinuta");
    $stmtMinuta->execute([':idMinuta' => $idMinuta]);
    $minuta = $stmtMinuta->fetch(PDO::FETCH_ASSOC);

    if (!$minuta) {
        echo "<div class='alert alert-danger'>Error: Minuta no encontrada.</div>";
        return;
    }

    // Carga del primer tema (asumiendo que los temas son arrays en tu app real)
    $stmtTema = $pdo->prepare("SELECT * FROM t_tema WHERE t_minuta_idMinuta = :idMinuta LIMIT 1");
    $stmtTema->execute([':idMinuta' => $idMinuta]);
    $tema = $stmtTema->fetch(PDO::FETCH_ASSOC);

    $nombreTemas = $tema['nombreTema'] ?? 'Temas de ejemplo...';
    $objetivos = $tema['objetivo'] ?? 'Objetivos de ejemplo...';
    $acuerdos = $tema['compromiso'] ?? 'Acuerdos de ejemplo...'; // Asumo que compromiso es acuerdo

} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Error al conectar o cargar datos: " . $e->getMessage() . "</div>";
    return;
}

// --- 3.1 CARGAR ASISTENCIA Y MIEMBROS RELEVANTES ---
try {
    // Obtener la lista de miembros relevantes
    $stmtMiembros = $pdo->prepare("SELECT idUsuario, pNombre, sNombre, aPaterno, aMaterno,
                        TRIM(CONCAT(pNombre, ' ', COALESCE(sNombre, ''), ' ', aPaterno, ' ', aMaterno)) AS nombreCompleto
                        FROM t_usuario WHERE tipoUsuario_id IN (1, 3) ORDER BY aPaterno");
    $stmtMiembros->execute();
    $miembros = $stmtMiembros->fetchAll(PDO::FETCH_ASSOC);

    // Obtener la asistencia ya registrada para esta minuta
    $stmtAsistencia = $pdo->prepare("SELECT t_usuario_idUsuario FROM t_asistencia WHERE t_minuta_idMinuta = :idMinuta");
    $stmtAsistencia->execute([':idMinuta' => $idMinuta]);
    $asistenciaActualIDs = $stmtAsistencia->fetchAll(PDO::FETCH_COLUMN, 0);
    $asistenciaMap = array_flip($asistenciaActualIDs);

    // Combinar la información
    $listaAsistencia = [];
    foreach ($miembros as $miembro) {
        $id = (int)$miembro['idUsuario'];
        $listaAsistencia[] = [
            'idUsuario' => $id,
            'nombreCompleto' => htmlspecialchars($miembro['nombreCompleto']),
            'presente' => isset($asistenciaMap[$id])
        ];
    }
} catch (Exception $e) {
    error_log("Error al cargar asistencia: " . $e->getMessage());
    $listaAsistencia = []; 
}

// --- 3. DETERMINAR ROL Y PERMISOS ---
$esSecretarioTecnico = ($idTipoUsuario === 2); // 2 = Secretario Técnico
$esPresidenteFirmante = false;
$haFirmado = false;
$haEnviadoFeedback = false;
$estadoMinuta = $minuta['estadoMinuta'] ?? 'BORRADOR';

if ($estadoMinuta === 'PENDIENTE' || $estadoMinuta === 'PARCIAL') {
    // Verificar si el usuario logueado es uno de los firmantes requeridos
    $stmtFirma = $pdo->prepare("SELECT estado_firma FROM t_aprobacion_minuta 
                WHERE t_minuta_idMinuta = :idMinuta 
                AND t_usuario_idPresidente = :idUsuario");
    $stmtFirma->execute([':idMinuta' => $idMinuta, ':idUsuario' => $idUsuarioLogueado]);
    $estadoFirma = $stmtFirma->fetchColumn();

    if ($estadoFirma !== false) { 
        $esPresidenteFirmante = true;
        if ($estadoFirma === 'REQUIERE_REVISION') {
            $haEnviadoFeedback = true; 
        }
    }
}

// El rol de ST (editor) tiene prioridad sobre el de Presidente (revisor)
if ($esSecretarioTecnico) {
    $esPresidenteFirmante = false;
}

// Lógica de solo lectura
$esSoloLectura = true; 
if ($esSecretarioTecnico && $estadoMinuta !== 'APROBADA') {
    $esSoloLectura = false;
} elseif ($esPresidenteFirmante || $estadoMinuta === 'APROBADA') {
    $esSoloLectura = true;
} else {
    $esSoloLectura = true;
}

$readonlyAttr = $esSoloLectura ? 'readonly' : '';
$pdo = null; // Cerrar conexión
?>

<div class="container-fluid mt-4">
    <h3 class="mb-3">
        <?php echo $esSecretarioTecnico ? 'Editar' : 'Revisar'; ?> Minuta N° <?php echo $idMinuta; ?>
        <span class="badge bg-secondary ms-2"><?php echo htmlspecialchars($estadoMinuta); ?></span>
    </h3>

    <form id="form-crear-minuta">
        <input type="hidden" id="idMinuta" name="idMinuta" value="<?php echo $idMinuta; ?>">

        <div class="card shadow-sm mb-4">
            <div class="card-header">
                <h5 class="mb-0">Detalles de la Minuta</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="minutaTemas" class="form-label">Nombre(s) del Tema</label>
                    <textarea class="form-control" id="minutaTemas" name="temas[0][nombre]" rows="3" <?php echo $readonlyAttr; ?>><?php echo htmlspecialchars($nombreTemas); ?></textarea>
                </div>
                <div class="mb-3">
                    <label for="minutaObjetivos" class="form-label">Objetivo(s)</label>
                    <textarea class="form-control" id="minutaObjetivos" name="temas[0][objetivo]" rows="3" <?php echo $readonlyAttr; ?>><?php echo htmlspecialchars($objetivos); ?></textarea>
                </div>
                <div class="mb-3">
                    <label for="minutaAcuerdos" class="form-label">Acuerdos (Compromisos)</label>
                    <textarea class="form-control" id="minutaAcuerdos" name="temas[0][acuerdo]" rows="5" <?php echo $readonlyAttr; ?>><?php echo htmlspecialchars($acuerdos); ?></textarea>
                </div>
            </div>
        </div>

        <input type="hidden" id="asistenciaJson" name="asistencia" value="[]">
        <div class="card shadow-sm mb-4">
            <div class="card-header">
                <h5 class="mb-0">Gestión de Asistencia</h5>
            </div>
            <div class="card-body">
                <?php if (!$esSoloLectura): ?>
                    <p class="text-info"><i class="fas fa-edit"></i> Marque/desmarque los usuarios **presentes**. Se respetará el registro original de fecha y el origen de los autogestionados.</p>
                <?php endif; ?>

                <div class="table-responsive">
                    <table class="table table-hover table-sm">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Nombre del Miembro</th>
                                <th class="text-center">Presente</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if (empty($listaAsistencia)) {
                                echo '<tr><td colspan="3" class="text-center text-danger">No se pudo cargar la lista de miembros.</td></tr>';
                            }
                            $i = 1;
                            foreach ($listaAsistencia as $miembro):
                                $checked = $miembro['presente'] ? 'checked' : '';
                                $disabled = $esSoloLectura ? 'disabled' : '';
                            ?>
                                <tr>
                                    <td><?php echo $i++; ?></td>
                                    <td><?php echo $miembro['nombreCompleto']; ?></td>
                                    <td class="text-center">
                                        <input class="form-check-input asistencia-checkbox"
                                            type="checkbox"
                                            value="<?php echo $miembro['idUsuario']; ?>"
                                            id="asistencia_<?php echo $miembro['idUsuario']; ?>"
                                            <?php echo $checked; ?>
                                            <?php echo $disabled; ?>>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="card shadow-sm mb-4">
            <div class="card-header">
                <h5 class="mb-0">Acciones</h5>
            </div>
            <div class="card-body text-end">
                <?php if ($esSecretarioTecnico && $estadoMinuta !== 'APROBADA'): ?>
                    <button type="button" class="btn btn-secondary me-2" id="btn-guardar-borrador">
                        <i class="fas fa-save"></i> Guardar Borrador
                    </button>

                    <button type="button" class="btn btn-danger" id="btn-enviar-aprobacion" onclick="enviarParaAprobacion(<?php echo $idMinuta; ?>)">
                        <i class="fas fa-paper-plane"></i> Enviar para Aprobación
                    </button>

                <?php elseif ($esPresidenteFirmante): ?>
                    <?php if ($haFirmado): ?>
                        <div class="alert alert-success text-center">
                            <i class="fas fa-check-circle"></i> Usted ya ha registrado su firma para esta versión de la minuta.
                        </div>
                    <?php elseif ($haEnviadoFeedback): ?>
                        <div class="alert alert-warning text-center">
                            <i class="fas fa-clock"></i> Usted envió feedback. La minuta está en espera de revisión por el Secretario Técnico.
                        </div>
                    <?php else: // Aún no ha hecho nada, es su turno 
                    ?>
                        <div class="form-check form-switch text-start mb-3">
                            <input class="form-check-input" type="checkbox" role="switch" id="checkFeedback">
                            <label class="form-check-label" for="checkFeedback"><b>Añadir Feedback / Observaciones</b> (Marque esta casilla si NO va a firmar)</label>
                        </div>

                        <div class="mb-3" id="cajaFeedbackContenedor" style="display: none;">
                            <label for="cajaFeedbackTexto" class="form-label text-start d-block">Indique sus observaciones (requerido):</label>
                            <textarea class="form-control" id="cajaFeedbackTexto" rows="4" placeholder="Escriba aquí sus correcciones o comentarios..."></textarea>
                        </div>

                        <button type="button" class="btn btn-success btn-lg" id="btn-accion-presidente">
                            <i class="fas fa-check"></i> Firmar Minuta
                        </button>
                    <?php endif; ?>

                <?php else: ?>
                    <p class="text-muted text-center">
                        <i class="fas fa-eye"></i> Minuta en modo de solo lectura.
                    </p>
                <?php endif; ?>

            </div>
        </div>
    </form>
</div>

<script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {

        const checkFeedback = document.getElementById('checkFeedback');
        const feedbackBox = document.getElementById('cajaFeedbackContenedor');
        const btnAccion = document.getElementById('btn-accion-presidente');
        const feedbackTexto = document.getElementById('cajaFeedbackTexto');
        const idMinuta = document.getElementById('idMinuta').value;
        const btnGuardarBorrador = document.getElementById('btn-guardar-borrador');
        const formMinuta = document.getElementById('form-crear-minuta');

        if (checkFeedback) {
            checkFeedback.addEventListener('change', function() {
                if (this.checked) {
                    feedbackBox.style.display = 'block';
                    btnAccion.classList.remove('btn-success');
                    btnAccion.classList.add('btn-warning');
                    btnAccion.innerHTML = '<i class="fas fa-comment-dots"></i> Enviar Feedback';
                } else {
                    feedbackBox.style.display = 'none';
                    btnAccion.classList.remove('btn-warning');
                    btnAccion.classList.add('btn-success');
                    btnAccion.innerHTML = '<i class="fas fa-check"></i> Firmar Minuta';
                }
            });
        }

        if (btnAccion) {
            btnAccion.addEventListener('click', function() {
                if (checkFeedback.checked) {
                    enviarFeedbackDesdeEditor();
                } else {
                    firmarMinutaDesdeEditor();
                }
            });
        }

        function firmarMinutaDesdeEditor() { /* ... (Tu lógica de firma aquí, sin cambios) ... */ }
        function enviarFeedbackDesdeEditor() { /* ... (Tu lógica de feedback aquí, sin cambios) ... */ }
        window.enviarParaAprobacion = function(idMinuta) { /* ... (Tu lógica de enviar para aprobación aquí, sin cambios) ... */ }

        // --- SOLUCIÓN: LÓGICA AJAX para 'Guardar Borrador' (el click del botón) ---
        if (formMinuta && btnGuardarBorrador) {
            btnGuardarBorrador.addEventListener('click', function(e) {
                e.preventDefault();

                Swal.fire({
                    title: 'Guardando Borrador... 💾',
                    text: 'Por favor, espere mientras se guardan los datos, incluyendo la asistencia.',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();

                        // 1. RECOLECCIÓN DE ASISTENCIA
                        const asistenciaIDs = [];
                        document.querySelectorAll('.asistencia-checkbox:checked').forEach(checkbox => {
                            asistenciaIDs.push(checkbox.value);
                        });

                        // 2. Asignar JSON al campo oculto
                        document.getElementById('asistenciaJson').value = JSON.stringify(asistenciaIDs);

                        // 3. Crear objeto FormData 
                        const formData = new FormData(formMinuta);

                        // 4. ENVÍO AL CONTROLADOR CORRECTO
                        fetch('../controllers/guardar_minuta_completa.php', {
                                method: 'POST',
                                body: formData
                            })
                            .then(response => {
                                // Siempre intentamos leer el JSON para obtener el mensaje de error del servidor
                                return response.json().then(data => {
                                    if (!response.ok || data.status === 'error') {
                                        let message = data.message || 'Error de red o desconocido.';
                                        if (data.debug) { 
                                            // Si usamos la versión de debug del controlador, mostramos la info.
                                            message += ` (Debug: ID Recibido: ${data.debug.idMinuta_recibido}, Keys: ${data.debug.post_keys_received.join(',')})`;
                                        }
                                        throw new Error(message);
                                    }
                                    return data;
                                });
                            })
                            .then(data => {
                                Swal.fire('¡Guardado! ✅', data.message, 'success');
                            })
                            .catch(error => {
                                Swal.fire('Error al Guardar ❌', error.message, 'error');
                            });
                    }
                });
            });
        }
    });
</script>