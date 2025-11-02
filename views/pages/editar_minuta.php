<?php
// views/pages/editar_minuta.php
// Este archivo implementa la lógica de roles que describiste.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../class/class.conectorDB.php';

// --- 1. OBTENER DATOS BÁSICOS ---
$idMinuta = (int)($_GET['id'] ?? 0);
$idUsuarioLogueado = (int)($_SESSION['idUsuario'] ?? 0);
// Usamos tipoUsuario_id (ST=2, Presidente=3) para la lógica de roles
$idTipoUsuario = (int)($_SESSION['tipoUsuario_id'] ?? 0);

if ($idMinuta === 0 || $idUsuarioLogueado === 0) {
    echo "<div class='alert alert-danger'>Error: No se pudo cargar la minuta o la sesión es inválida.</div>";
    return;
}

// --- 2. CARGAR DATOS DE LA MINUTA ---
// (Debes reemplazar esta simulación con tu lógica real para cargar los datos)
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

    // SIMULACIÓN: Carga el primer tema (en tu app real, harías un bucle)
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

    if ($estadoFirma !== false) { // ¡Es un firmante!
        $esPresidenteFirmante = true;

        // (Usamos 'EN_ESPERA' basado en tu lógica de minutas_listado_general.php)
        if ($estadoFirma === 'EN_ESPERA') {
            // 'EN_ESPERA' es el estado que tu 'enviar_feedback.php' busca.
            // Significa "Aún no ha firmado NI enviado feedback"
        } else if ($estadoFirma === 'REQUIERE_REVISION') {
            $haEnviadoFeedback = true; // El presidente ya envió feedback
        } else {
            // Cualquier otro estado (como 'FIRMADO' si lo implementas)
            // O si el estado es 'PENDIENTE' (en tu BD `t_aprobacion_minuta` tiene 'PENDIENTE' y 'EN_ESPERA', 
            // deberías unificar eso. Asumiré que PENDIENTE y EN_ESPERA significan lo mismo: "en revisión")

            // Revisando tu 'enviar_feedback.php', solo permite feedback si el estado es 'EN_ESPERA'.
            // Si tu estado por defecto es 'PENDIENTE', debes ajustar la consulta en 'enviar_feedback.php' (línea 87)
            // a: AND estado_firma IN ('EN_ESPERA', 'PENDIENTE')
        }
    }
}

// El rol de ST (editor) tiene prioridad sobre el de Presidente (revisor)
if ($esSecretarioTecnico) {
    $esPresidenteFirmante = false;
}

// Determinar si los campos deben ser de solo lectura
// El ST puede editar, el Presidente solo puede leer.
$esSoloLectura = ($esPresidenteFirmante || $estadoMinuta === 'APROBADA' || $haFirmado || $haEnviadoFeedback);
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

        <div class="card shadow-sm mb-4">
            <div class="card-header">
                <h5 class="mb-0">Acciones</h5>
            </div>
            <div class="card-body text-end">

                <?php if ($esSecretarioTecnico && $estadoMinuta !== 'APROBADA'): ?>
                    <button type="submit" class="btn btn-secondary me-2" id="btn-guardar-borrador">
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
                            <i class="fas fa-clock"></i> Usted envió feedback (estado REQUIERE_REVISION). La minuta está en espera de revisión por el Secretario Técnico.
                        </div>
                    <?php else: // Aún no ha hecho nada, es su turno 
                    ?>
                        <div class="form-check form-switch text-start mb-3">
                            <input class="form-check-input" type="checkbox" role="switch" id="checkFeedback">
                            <label class="form-check-label" for="checkFeedback"><b>Añadir Feedback / Observaciones</b> (Marque esta casilla si NO va a firmar)</label>
                        </div>

                        <div class="mb-3" id="cajaFeedbackContenedor" style="display: none;">
                            <label for="cajaFeedbackTexto" class="form-label text-start d-block">Indique sus observaciones (requerido):</label>
                            <textarea class="form-control" id="cajaFeedbackTexto" rows="4" placeholder="Escriba aquí sus correcciones o comentarios para el Secretario Técnico..."></textarea>
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

        // --- Lógica para el Presidente ---
        const checkFeedback = document.getElementById('checkFeedback');
        const feedbackBox = document.getElementById('cajaFeedbackContenedor');
        const btnAccion = document.getElementById('btn-accion-presidente');
        const feedbackTexto = document.getElementById('cajaFeedbackTexto');
        const idMinuta = document.getElementById('idMinuta').value;

        if (checkFeedback) {
            checkFeedback.addEventListener('change', function() {
                if (this.checked) {
                    // Modo Feedback
                    feedbackBox.style.display = 'block';
                    btnAccion.classList.remove('btn-success');
                    btnAccion.classList.add('btn-warning');
                    btnAccion.innerHTML = '<i class="fas fa-comment-dots"></i> Enviar Feedback';
                } else {
                    // Modo Firma
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
                    // Está en modo "Enviar Feedback"
                    enviarFeedbackDesdeEditor();
                } else {
                    // Está en modo "Firmar Minuta"
                    firmarMinutaDesdeEditor();
                }
            });
        }

        function firmarMinutaDesdeEditor() {
            Swal.fire({
                title: '¿Confirmar Aprobación?',
                text: "Esta acción registrará su firma digital y no se puede deshacer. ¿Está seguro?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, aprobar y firmar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Llamamos al controlador 'aprobar_minuta.php' (que ya debes tener)
                    fetch('../controllers/aprobar_minuta.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                idMinuta: idMinuta
                            })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.status === 'success_final' || data.status === 'success_partial') {
                                Swal.fire(data.status === 'success_final' ? '¡Minuta Aprobada!' : '¡Firma Registrada!', data.message, 'success')
                                    .then(() => {
                                        window.location.href = 'menu.php?pagina=minutas_pendientes'; // Redirigir
                                    });
                            } else {
                                throw new Error(data.message || 'Error desconocido');
                            }
                        })
                        .catch(error => {
                            Swal.fire('Error', error.message, 'error');
                        });
                }
            });
        }

        function enviarFeedbackDesdeEditor() {
            const feedback = feedbackTexto.value;

            // Validamos aquí (aunque tu controlador también lo hace, es bueno para UX)
            if (feedback.trim().length < 10) {
                Swal.fire('Error', 'Debe ingresar un feedback de al menos 10 caracteres.', 'error');
                return;
            }

            Swal.fire({
                title: '¿Enviar Feedback?',
                text: "Esto devolverá la minuta al Secretario Técnico con sus observaciones. ¿Está seguro?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ffc107',
                confirmButtonText: 'Sí, enviar feedback',
                cancelButtonText: 'Cancelar',
                showLoaderOnConfirm: true,
                preConfirm: () => {
                    // Llamamos a TU controlador 'enviar_feedback.php'
                    return fetch('../controllers/enviar_feedback.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                idMinuta: idMinuta,
                                feedback: feedback // Tu controlador espera 'feedback'
                            })
                        })
                        .then(response => {
                            if (!response.ok) {
                                // Si el servidor da 400 o 500, leemos el error
                                return response.json().then(errData => {
                                    throw new Error(errData.message || 'Error del servidor.');
                                });
                            }
                            return response.json();
                        })
                        .catch(error => {
                            Swal.showValidationMessage(`Error: ${error.message}`);
                        });
                },
                allowOutsideClick: () => !Swal.isLoading()
            }).then((result) => {
                if (result.isConfirmed && result.value.status === 'success') {
                    Swal.fire({
                        title: 'Feedback Enviado',
                        text: 'Se ha notificado al Secretario Técnico.',
                        icon: 'success'
                    }).then(() => {
                        // Redirigir a la lista de pendientes
                        window.location.href = 'menu.php?pagina=minutas_pendientes';
                    });
                }
            });
        }


        // --- Lógica para el Secretario Técnico ---
        // (Esta es la función que llama tu botón "Enviar para Aprobación")
        // (Asegúrate de que el script que contiene esta función esté cargado)
        window.enviarParaAprobacion = function(idMinuta) {
            Swal.fire({
                title: '¿Enviar para Aprobación?',
                text: "Se notificará a todos los presidentes requeridos. Esta acción reiniciará cualquier firma o feedback anterior. ¿Continuar?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'Sí, Enviar',
                cancelButtonText: 'Cancelar',
                showLoaderOnConfirm: true,
                preConfirm: () => {
                    return fetch('../controllers/enviar_aprobacion.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                idMinuta: idMinuta
                            })
                        })
                        .then(response => {
                            if (!response.ok) {
                                // Si el servidor da 400 o 500, leemos el error
                                return response.json().then(errData => {
                                    throw new Error(errData.message || 'Error del servidor.');
                                });
                            }
                            return response.json();
                        })
                        .then(data => {
                            if (data.status !== 'success') {
                                throw new Error(data.message);
                            }
                            return data;
                        })
                        .catch(error => {
                            Swal.showValidationMessage(`Error: ${error.message}`);
                        });
                },
                allowOutsideClick: () => !Swal.isLoading()
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire('Enviada', result.value.message, 'success')
                        .then(() => {
                            window.location.href = 'menu.php?pagina=minutas_pendientes';
                        });
                }
            });
        }

        // (Aquí debes poner tu lógica AJAX para 'Guardar Borrador' (el submit del form))
        // Ejemplo:
        const formMinuta = document.getElementById('form-crear-minuta');
        if (formMinuta) {
            formMinuta.addEventListener('submit', function(e) {
                e.preventDefault();
                // Solo si el botón "guardar borrador" fue presionado
                // (Necesitarías lógica adicional para diferenciar submit de "guardar borrador" 
                // vs. "enviar aprobación" si ambos fueran type=submit)

                // Asumiré que el botón "Guardar Borrador" es el único type="submit"
                console.log("Enviando formulario para guardar borrador...");
                // const formData = new FormData(this);
                // fetch('../controllers/guardar_minuta_completa.php', { method: 'POST', body: formData })
                // ... (lógica de guardar borrador) ...
            });
        }

    });
</script>