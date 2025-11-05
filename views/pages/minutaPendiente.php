<?php
// views/pages/minutaPendiente.php
require_once("../../cfg/config.php");
require_once __DIR__ . '/../../models/minutaModel.php';

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

class MinutaPendiente extends BaseConexion
{
  public $idUsuarioLogueado;
  private $conexion;

  public function __construct()
  {
    $this->idUsuarioLogueado = $_SESSION['idUsuario'] ?? 0;
    $this->conexion = $this->conectar();
    $this->conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  }

  /**
   * (Función sin cambios)
   * Obtiene la lista precisa de IDs de presidentes requeridos para firmar.
   */
  private function getListaPresidentesRequeridos(int $idMinuta): array
  {
    try {
      $sqlMinuta = "SELECT t_usuario_idPresidente FROM t_minuta WHERE idMinuta = ?";
      $stmtMinuta = $this->conexion->prepare($sqlMinuta);
      $stmtMinuta->execute([$idMinuta]);
      $idPresidente1 = $stmtMinuta->fetchColumn();
      $presidentes = [$idPresidente1];

      $sqlReunion = "SELECT r.t_comision_idComision_mixta, r.t_comision_idComision_mixta2 
                           FROM t_reunion r
                           WHERE r.t_minuta_idMinuta = ?";
      $stmtReunion = $this->conexion->prepare($sqlReunion);
      $stmtReunion->execute([$idMinuta]);
      $comisionesMixtas = $stmtReunion->fetch(PDO::FETCH_ASSOC);

      if ($comisionesMixtas) {
        $idComisiones = array_filter([
          $comisionesMixtas['t_comision_idComision_mixta'],
          $comisionesMixtas['t_comision_idComision_mixta2']
        ]);

        if (!empty($idComisiones)) {
          $placeholders = implode(',', array_fill(0, count($idComisiones), '?'));
          $sqlComision = "SELECT t_usuario_idPresidente FROM t_comision WHERE idComision IN ($placeholders)";
          $stmtComision = $this->conexion->prepare($sqlComision);
          $stmtComision->execute($idComisiones);
          $idsPresidentesMixtos = $stmtComision->fetchAll(PDO::FETCH_COLUMN, 0);
          $presidentes = array_merge($presidentes, $idsPresidentesMixtos);
        }
      }
      $presidentesUnicos = array_map('intval', array_unique(array_filter($presidentes)));
      return $presidentesUnicos;
    } catch (Exception $e) {
      error_log("ERROR idMinuta {$idMinuta}: No se pudo OBTENER la lista de presidentes en minutaPendiente.php. Error: " . $e->getMessage());
      return [];
    }
  }


  public function obtenerMinutas()
  {
    // --- Paginación segura ---
    $pPage = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
    $perPage = isset($_GET['per_page']) ? max(1, (int)$_GET['per_page']) : 10;
    $offset = ($pPage - 1) * $perPage;

    // --- Contar solo las minutas ASIGNADAS a este presidente ---
    $sqlCount = "SELECT COUNT(DISTINCT m.idMinuta)
                     FROM t_minuta m
                     JOIN t_aprobacion_minuta am ON am.t_minuta_idMinuta = m.idMinuta
                     WHERE m.estadoMinuta IN ('PENDIENTE', 'PARCIAL')
                     AND am.t_usuario_idPresidente = :idUsuarioLogueado";

    $stmtCount = $this->conexion->prepare($sqlCount);
    $stmtCount->execute([':idUsuarioLogueado' => $this->idUsuarioLogueado]);
    $totalRows = (int)$stmtCount->fetchColumn();
    $totalPages = max(1, (int)ceil($totalRows / $perPage));


    // --- Consulta principal (CORREGIDA) ---
    // Esta consulta ahora solo trae las minutas que este presidente debe firmar
    $sql = "
            SELECT 
                m.idMinuta,
                m.t_comision_idComision, 
                (SELECT nombreComision FROM t_comision c WHERE c.idComision = m.t_comision_idComision) AS nombreComision, 
                
                u.pNombre AS presidenteNombre,
                u.aPaterno AS presidenteApellido,
                
                m.fechaMinuta AS fecha,
                m.horaMinuta AS hora,
                
                -- Campos de estado
                m.estadoMinuta,
                m.presidentesRequeridos,
                
                -- (NUEVO) Cuántos han firmado (estado 'FIRMADO')
                (SELECT COUNT(DISTINCT am_count.t_usuario_idPresidente) 
                 FROM t_aprobacion_minuta am_count 
                 WHERE am_count.t_minuta_idMinuta = m.idMinuta
                 AND am_count.estado_firma = 'FIRMADO') AS firmasActuales,
                
                -- (NUEVO) Verificar si el usuario actual ya firmó esta versión
                (SELECT COUNT(*) 
                 FROM t_aprobacion_minuta am2 
                 WHERE am2.t_minuta_idMinuta = m.idMinuta 
                   AND am2.t_usuario_idPresidente = :idUsuarioLogueado
                   AND am2.estado_firma = 'FIRMADO') AS usuarioHaFirmado,
                    
                -- (NUEVO) Verificar si esta minuta tiene feedback pendiente de ST
                (SELECT COUNT(*) 
                 FROM t_aprobacion_minuta am3 
                 WHERE am3.t_minuta_idMinuta = m.idMinuta 
                   AND am3.estado_firma = 'REQUIERE_REVISION') AS tieneFeedback,

                -- Conteo de adjuntos
                (SELECT COUNT(*)
                 FROM t_adjunto a
                 WHERE a.t_minuta_idMinuta = m.idMinuta) AS totalAdjuntos
                 
            FROM t_minuta m
            
            -- Unir con t_aprobacion_minuta para filtrar por el presidente logueado
            JOIN t_aprobacion_minuta am ON am.t_minuta_idMinuta = m.idMinuta
            
            LEFT JOIN t_usuario u ON u.idUsuario = m.t_usuario_idPresidente
            
            -- Filtro para estado Y para el usuario logueado
            WHERE m.estadoMinuta IN ('PENDIENTE', 'PARCIAL')
            AND am.t_usuario_idPresidente = :idUsuarioLogueado
            
            GROUP BY m.idMinuta -- Agrupar por minuta para evitar duplicados si hay múltiples registros
            ORDER BY m.idMinuta DESC
            LIMIT :limit OFFSET :offset
        ";

    $stmt = $this->conexion->prepare($sql);
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':idUsuarioLogueado', $this->idUsuarioLogueado, PDO::PARAM_INT);

    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- Lógica de Presidentes Requeridos (Usando la función corregida) ---
    // Esta lógica ahora solo se usa para mostrar los botones, no para filtrar la lista
    foreach ($rows as $i => $minuta) {
      $idMinuta = (int)$minuta['idMinuta'];
      $rows[$i]['listaPresidentesRequeridos'] = $this->getListaPresidentesRequeridos($idMinuta);
    }

    return [
      'data' => $rows,
      'page' => $pPage,
      'per_page' => $perPage,
      'total' => $totalRows,
      'totalPages' => $totalPages
    ];
  }
} // Fin de la clase MinutaPendiente

// --- Ejecución ---
$minutaModel = new MinutaPendiente();
$res = $minutaModel->obtenerMinutas();
$minutas = $res['data'] ?? [];
$pPage = $res['page'] ?? 1;
$perPage = $res['per_page'] ?? 10;
$totalRows = $res['total'] ?? 0;
$totalPages = $res['totalPages'] ?? 1;

// ID de usuario de la sesión para la lógica del botón
$idUsuarioLogueado = intval($minutaModel->idUsuarioLogueado);

// Helper de paginación (sin cambios)
function renderPagination($current, $pages)
{
  if ($pages <= 1) return;
  echo '<nav aria-label="Paginación"><ul class="pagination pagination-sm">';
  for ($i = 1; $i <= $pages; $i++) {
    $active = ($i === $current) ? ' active' : '';
    $qsArr = $_GET;
    $qsArr['p'] = $i;
    $qs = http_build_query($qsArr);
    echo '<li class="page-item' . $active . '"><a class="page-link" href="?' . $qs . '">' . $i . '</a></li>';
  }
  echo '</ul></nav>';
}
?>

<div class="container mt-4">
  <h4 class="fw-bold mb-4">Minutas Pendientes de Aprobación</h4>

  <?php if (!empty($minutas)) : ?>
    <?php foreach ($minutas as $minuta) : ?>
      <?php
      $idMinuta = (int)($minuta['idMinuta'] ?? 0);
      $totalAdjuntos = (int)($minuta['totalAdjuntos'] ?? 0);

      // --- INICIO: Lógica de Aprobación y Estado (ACTUALIZADA) ---
      $estado = $minuta['estadoMinuta'] ?? 'PENDIENTE';
      $requeridos = max(1, (int)($minuta['presidentesRequeridos'] ?? 1));
      $firmasActuales = (int)($minuta['firmasActuales'] ?? 0); // (NUEVO)
      $usuarioHaFirmado = (int)($minuta['usuarioHaFirmado'] > 0); // (NUEVO)
      $tieneFeedback = (int)($minuta['tieneFeedback'] > 0); // (NUEVO)

      // La consulta SQL ya filtró, así que $esPresidenteRequerido es true
      $esPresidenteRequerido = true;

      // Variable de control principal para los botones
      $puedeAccionar = $esPresidenteRequerido && !$usuarioHaFirmado && !$tieneFeedback;


      // --- Lógica de texto y color del Estado (ACTUALIZADA) ---
      $statusClass = 'text-warning'; // PENDIENTE (default)
      $statusText = "PENDIENTE ($firmasActuales de $requeridos firmas)";

      if ($tieneFeedback) {
        // (NUEVO) Si alguien envió feedback, se bloquea para todos.
        $statusClass = 'text-danger'; // Requiere Revisión ST
        $statusText = "REQUIERE REVISIÓN ST ($firmasActuales de $requeridos)";
      } elseif ($estado === 'PARCIAL') {
        $statusClass = 'text-info'; // PARCIAL
        $statusText = "APROBACIÓN PARCIAL ($firmasActuales de $requeridos firmas)";
      }
      ?>
      <div class="card mb-4 shadow-sm" id="card-minuta-<?= $idMinuta ?>">
        <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap">
          <span class="fw-bold text-primary fs-5">Minuta N° <?= htmlspecialchars($minuta['idMinuta']) ?></span>
          <span class="fw-bold <?= $statusClass ?> ms-3"><?= htmlspecialchars($statusText) ?></span>
        </div>

        <div class="card-body">
          <div class="row mb-2">
            <div class="col-md-3"><strong>Comisión:</strong><br><?= htmlspecialchars($minuta['nombreComision'] ?? '—') ?></div>
            <div class="col-md-3"><strong>Presidente (Principal):</strong><br><?= htmlspecialchars(trim(($minuta['presidenteNombre'] ?? '') . ' ' . ($minuta['presidenteApellido'] ?? ''))) ?: '—' ?></div>
            <div class="col-md-3"><strong>Fecha:</strong><br><?= !empty($minuta['fecha']) ? date("d-m-Y", strtotime($minuta['fecha'])) : '—' ?></div>
            <div class="col-md-3"><strong>Hora:</strong><br><?= !empty($minuta['hora']) ? date("H:i", strtotime($minuta['hora'])) : '—' ?></div>
          </div>


          <div class="row mt-3 pt-3 border-top">
            <!-- 
                        *
                        * ==================
                        * INICIO DE LA CORRECCIÓN (Volver a diseño anterior)
                        * ==================
                        * Se restaura la columna de adjuntos a 12
                        * Se quita el botón "Revisar Minuta" de aquí
                        *
                        -->
            <div class="col-md-12">
              <strong>Adjuntos:</strong><br>
              <?php if ($totalAdjuntos > 0) : ?>
                <button type="button" class="btn btn-info btn-sm" title="Ver adjuntos" onclick="verAdjuntos(<?= $idMinuta; ?>)">
                  <i class="fas fa-paperclip"></i> Ver (<?= $totalAdjuntos; ?>)
                </button>
              <?php else : ?>
                <span class="text-muted">No posee archivos adjuntos</span>
              <?php endif; ?>
            </div>
          </div>
          <!-- 
                    * ==================
                    * FIN DE LA CORRECCIÓN
                    * ==================
                    -->

        </div>

        <div class="card-footer bg-light text-end">
          <!-- 
                    *
                    * ==================
                    * INICIO DE LA CORRECCIÓN (Lógica de botones)
                    * ==================
                    * 1. Se restaura el botón "Ver Detalle"
                    * 2. Se implementa la lógica IF/ELSEIF/ELSE correcta para los botones de acción
                    *
                    -->

          <!-- 1. Restaurar botón "Ver Detalle" -->
          <a href="/corevota/controllers/generar_pdf_borrador.php?id=<?= $idMinuta ?>" target="_blank" class="btn btn-outline-secondary btn-sm" title="Ver Borrador PDF">
            <i class="fas fa-eye"></i> Ver Minuta Borrador
          </a>

          <?php if ($tieneFeedback) : ?>
            <!-- Caso 1: Minuta bloqueada por feedback de alguien -->
            <span class="text-danger fw-bold ms-2">
              <i class="fas fa-clock"></i> Minuta en revisión por ST.
            </span>
          <?php elseif ($usuarioHaFirmado) : ?>
            <!-- Caso 2: El usuario actual YA firmó -->
            <span class="text-success fw-bold ms-2">
              <i class="fas fa-check-circle"></i> Ya has firmado esta versión.
            </span>
          <?php elseif ($puedeAccionar) : ?>
          <!-- Caso 3: Es el turno del usuario (la condición que faltaba) -->
          <?php
          // Verificar si el usuario ya envió feedback anteriormente
          require_once __DIR__ . '/../../models/minutaModel.php';
          $minutaModel = new MinutaModel();
          $idUsuario = $_SESSION['idUsuario'] ?? 0;
          $yaEnvioFeedback = $minutaModel->verificarAccion($idMinuta, $idUsuario, 'FEEDBACK_ENVIADO');
          ?>

          <?php if (!$yaEnvioFeedback): ?>
            <button type="button" class="btn btn-warning btn-sm ms-2"
              id="btn-feedback-<?= $idMinuta ?>"
              onclick="enviarFeedback(<?= $idMinuta ?>)">
              <i class="fas fa-comment-dots"></i> Enviar Feedback
            </button>
          <?php else: ?>
            <span class="badge bg-secondary ms-2">
              <i class="fas fa-comment-slash"></i> Feedback enviado
            </span>
          <?php endif; ?>

          <button type="button" class="btn btn-success btn-sm ms-2"
            id="btn-aprobar-<?= $idMinuta ?>"
            onclick="aprobarMinuta(<?= $idMinuta ?>)">
            <i class="fas fa-check"></i> Aprobar con Firma
          </button>
        <?php else: ?>

            <!-- Caso 4: Otro (Ej. $esPresidenteRequerido falló, aunque no debería) -->
            <span class="text-warning fw-bold ms-2">
              <i class="fas fa-hourglass-start"></i> Firma en espera.
            </span>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>

    <?php renderPagination($pPage, $totalPages); ?>

  <?php else : ?>
    <p class="text-muted">No hay minutas pendientes de aprobación.</p>
  <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
  // === BLOQUE DE FIRMA ===
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[id^="btn-aprobar-"]').forEach(btn => {
      btn.addEventListener('click', function (e) {
        e.preventDefault();

        const id = this.id.split('-')[2];

        Swal.fire({
          title: '¿Confirmar Firma?',
          text: '¿Deseas firmar esta minuta?',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: 'Sí, firmar',
          cancelButtonText: 'Cancelar',
          confirmButtonColor: '#28a745'
        }).then((result) => {
          if (result.isConfirmed) {
            Swal.fire({
              title: '¡Firma registrada!',
              text: 'Tu firma fue realizada con éxito.',
              icon: 'success',
              confirmButtonColor: '#28a745',
              confirmButtonText: 'Aceptar'
            });
            aprobarMinuta(id); // <- se ejecuta después de confirmar
          }
        });
      });
    }); // <- cierra forEach
  });   // <- cierra DOMContentLoaded


  // === FUNCIÓN GLOBAL enviarFeedback ===
  function enviarFeedback(idMinuta) {
    const boton = document.getElementById('btn-aprobar-' + idMinuta);
    const botonFeedback = document.getElementById('btn-feedback-' + idMinuta);

    const feedbackHtml = `
      <style>
        .feedback-form-container { text-align: left; }
        .feedback-form-container .form-check { margin-top: 15px; }
        .feedback-form-container .form-control { display: none; margin-top: 8px; }
        .feedback-form-container .form-check-input:checked ~ .form-control { display: block; }
      </style>
      <div id="feedbackForm" class="feedback-form-container">
        <p>Por favor, marca las secciones que requieren revisión:</p>
        <div class="form-check">
          <input class="form-check-input" type="checkbox" value="Asistencia" id="fb_asistencia">
          <label class="form-check-label" for="fb_asistencia">Asistencia</label>
          <textarea id="fb_asistencia_text" class="form-control" placeholder="Escriba su observación sobre la asistencia..."></textarea>
        </div>
        <div class="form-check">
          <input class="form-check-input" type="checkbox" value="Temas" id="fb_temas">
          <label class="form-check-label" for="fb_temas">Temas Tratados (Objetivos, Acuerdos, etc.)</label>
          <textarea id="fb_temas_text" class="form-control" placeholder="Escriba su observación sobre los temas..."></textarea>
        </div>
        <div class="form-check">
          <input class="form-check-input" type="checkbox" value="Votaciones" id="fb_votaciones">
          <label class="form-check-label" for="fb_votaciones">Gestión de Votaciones</label>
          <textarea id="fb_votaciones_text" class="form-control" placeholder="Escriba su observación sobre la votación..."></textarea>
        </div>
        <div class="form-check">
          <input class="form-check-input" type="checkbox" value="Adjuntos" id="fb_adjuntos">
          <label class="form-check-label" for="fb_adjuntos">Documentos Adjuntos</label>
          <textarea id="fb_adjuntos_text" class="form-control" placeholder="Indique qué documento falta o debe corregirse..."></textarea>
        </div>
        <div class="form-check">
          <input class="form-check-input" type="checkbox" value="Otro" id="fb_otro">
          <label class="form-check-label" for="fb_otro">Otro (General)</label>
          <textarea id="fb_otro_text" class="form-control" placeholder="Escriba cualquier otra observación general..."></textarea>
        </div>
      </div>`;

    Swal.fire({
      title: 'Enviar Feedback al Secretario',
      html: feedbackHtml,
      width: '80%',
      showCancelButton: true,
      confirmButtonText: 'Enviar Feedback',
      confirmButtonColor: '#ffc107',
      cancelButtonText: 'Cancelar',
      showLoaderOnConfirm: true,
      allowOutsideClick: () => !Swal.isLoading(),

      preConfirm: () => {
        const items = ['asistencia', 'temas', 'votaciones', 'adjuntos', 'otro'];
        let feedbackCombinado = "";
        let feedbackCampos = {};
        let itemsSeleccionados = 0;
        let validationError = null;

        for (const id of items) {
          const checkbox = document.getElementById('fb_' + id);
          feedbackCampos[id] = checkbox.checked;

          if (checkbox.checked) {
            itemsSeleccionados++;
            const texto = document.getElementById('fb_' + id + '_text').value;

            if (texto.trim() === "") {
              validationError = `Por favor, escriba un comentario para la sección: ${checkbox.value}`;
              break;
            }
            feedbackCombinado += `--- SECCIÓN: ${checkbox.value.toUpperCase()} ---\n${texto}\n\n`;
          }
        }

        if (validationError) {
          Swal.showValidationMessage(validationError);
          return false;
        }

        if (itemsSeleccionados === 0) {
          Swal.showValidationMessage('Debe seleccionar al menos una sección y escribir un comentario.');
          return false;
        }

        if (boton) boton.disabled = true;
        if (botonFeedback) botonFeedback.disabled = true;

        // 🔹 Confirmación previa antes de enviar
        return Swal.fire({
          title: '¿Confirmar Envío?',
          html: `
            <p>¿Está seguro de enviar este feedback al Secretario Técnico?</p>
            <p><b>Secciones seleccionadas:</b><br>${
              Object.keys(feedbackCampos)
                .filter(k => feedbackCampos[k])
                .map(k => '• ' + k.charAt(0).toUpperCase() + k.slice(1))
                .join('<br>') || 'Ninguna'
            }</p>
          `,
          icon: 'question',
          showCancelButton: true,
          confirmButtonText: 'Sí, enviar',
          cancelButtonText: 'No, cancelar',
          confirmButtonColor: '#28a745',
          cancelButtonColor: '#dc3545'
        }).then(confirmResult => {
          if (!confirmResult.isConfirmed) {
            throw new Error('Envío cancelado por el usuario.');
          }

          // 🔹 Si confirma, ejecutar el envío real
          return fetch('../../controllers/enviar_feedback.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              idMinuta: idMinuta,
              feedback: feedbackCombinado,
              feedbackCampos: feedbackCampos
            })
          }).then(response => {
            if (!response.ok) {
              return response.text().then(text => {
                console.error("Respuesta de error del servidor (feedback):", text);
                throw new Error('Error del servidor (ver consola).');
              });
            }
            return response.json();
          });
        }).catch(error => {
          Swal.showValidationMessage(`Error: ${error.message}`);
          if (boton) boton.disabled = false;
          if (botonFeedback) botonFeedback.disabled = false;
        });
      }
    }).then((result) => {
      if (result.isConfirmed && result.value && result.value.status === 'success') {
        Swal.fire({
          title: 'Feedback Enviado',
          text: 'Se ha notificado al Secretario Técnico. La minuta queda en espera de revisión.',
          icon: 'success',
          confirmButtonColor: '#28a745'
        }).then(() => location.reload());
      } else if (result.isConfirmed && result.value && result.value.status !== 'success') {
        Swal.fire('Error', result.value.message || 'Ocurrió un error al enviar el feedback.', 'error');
        if (boton) boton.disabled = false;
        if (botonFeedback) botonFeedback.disabled = false;
      } else {
        if (boton) boton.disabled = false;
        if (botonFeedback) botonFeedback.disabled = false;
      }
    });
    // --- Recarga automática simple tras acciones confirmadas ---
    document.addEventListener('corevota:accionConfirmada', () => {
      setTimeout(() => location.reload(), 1000); // espera 1 seg. para que se vea el mensaje
    });
  }
</script>