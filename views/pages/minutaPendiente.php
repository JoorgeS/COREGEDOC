<?php
// views/pages/minutaPendiente.php
require_once("../../cfg/config.php");
// (NUEVO) Cargar el modelo aquí si no se carga automáticamente
require_once __DIR__ . '/../../models/minutaModel.php';

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

class MinutaPendiente extends BaseConexion
{
  public $idUsuarioLogueado;
  private $conexion; // Propiedad para la conexión

  // Constructor para obtener el ID del usuario logueado y conectar
  public function __construct()
  {
    $this->idUsuarioLogueado = $_SESSION['idUsuario'] ?? 0;
    $this->conexion = $this->conectar(); // Conectar una vez
    $this->conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  }

  /**
   * Obtiene la lista precisa de IDs de presidentes requeridos para firmar.
   * *** CORREGIDO: Usa t_usuario_idPresidente, no nombrePresidente ***
   */
  private function getListaPresidentesRequeridos(int $idMinuta): array
  {
    try {
      // 1. Obtener Presidente 1 (guardado en t_minuta)
      // *** CORRECCIÓN CRÍTICA ***
      $sqlMinuta = "SELECT t_usuario_idPresidente FROM t_minuta WHERE idMinuta = ?";
      $stmtMinuta = $this->conexion->prepare($sqlMinuta);
      $stmtMinuta->execute([$idMinuta]);
      $idPresidente1 = $stmtMinuta->fetchColumn();

      $presidentes = [$idPresidente1];

      // 2. Obtener Presidentes 2 y 3 (de comisiones mixtas en t_reunion)
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
          // Consultar los IDs de presidentes para esas comisiones
          $placeholders = implode(',', array_fill(0, count($idComisiones), '?'));
          $sqlComision = "SELECT t_usuario_idPresidente FROM t_comision WHERE idComision IN ($placeholders)";
          $stmtComision = $this->conexion->prepare($sqlComision);
          $stmtComision->execute($idComisiones);

          $idsPresidentesMixtos = $stmtComision->fetchAll(PDO::FETCH_COLUMN, 0);
          $presidentes = array_merge($presidentes, $idsPresidentesMixtos);
        }
      }

      // 3. Devolver lista de IDs únicos, filtrados y forzados a entero
      $presidentesUnicos = array_map('intval', array_unique(array_filter($presidentes)));

      return $presidentesUnicos;
    } catch (Exception $e) {
      error_log("ERROR idMinuta {$idMinuta}: No se pudo OBTENER la lista de presidentes en minutaPendiente.php. Error: " . $e->getMessage());
      return []; // Devolver vacío en caso de error
    }
  }


  public function obtenerMinutas()
  {
    // --- Paginación segura ---
    $pPage = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
    $perPage = isset($_GET['per_page']) ? max(1, (int)$_GET['per_page']) : 10;
    $offset = ($pPage - 1) * $perPage;

    // --- (ACTUALIZADO) Contar solo las PENDIENTES o PARCIALES ---
    $sqlCount = "SELECT COUNT(*) AS total 
                     FROM t_minuta 
                     WHERE estadoMinuta IN ('PENDIENTE', 'PARCIAL')";
    $totalRows = (int)$this->conexion->query($sqlCount)->fetchColumn();
    $totalPages = max(1, (int)ceil($totalRows / $perPage));

    // --- Consulta principal (ACTUALIZADA) ---
    $sql = "
            SELECT 
                m.idMinuta,
                m.t_comision_idComision, 
                (SELECT nombreComision FROM t_comision c WHERE c.idComision = m.t_comision_idComision) AS nombreComision, 
                
                -- *** CORRECCIÓN CRÍTICA ***
                u.pNombre AS presidenteNombre,
                u.aPaterno AS presidenteApellido,
                
                m.fechaMinuta AS fecha,
                m.horaMinuta AS hora,
                
                -- Campos de estado
                m.estadoMinuta,
                m.presidentesRequeridos,
                
                -- (NUEVO) Cuántos han firmado (estado 'FIRMADO')
                (SELECT COUNT(DISTINCT am.t_usuario_idPresidente) 
                 FROM t_aprobacion_minuta am 
                 WHERE am.t_minuta_idMinuta = m.idMinuta
                 AND am.estado_firma = 'FIRMADO') AS firmasActuales,
                
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
            
            -- *** CORRECCIÓN CRÍTICA: Unir usando t_usuario_idPresidente ***
            LEFT JOIN t_usuario u ON u.idUsuario = m.t_usuario_idPresidente
            
            -- (ACTUALIZADO) Filtro para mostrar solo pendientes o parciales
            WHERE m.estadoMinuta IN ('PENDIENTE', 'PARCIAL')
            
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
    foreach ($rows as $i => $minuta) {
      $idMinuta = (int)$minuta['idMinuta'];
      // Usamos la función corregida para obtener la lista
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

      $listaPresidentesRequeridos = $minuta['listaPresidentesRequeridos'] ?? [];
      $esPresidenteRequerido = in_array($idUsuarioLogueado, $listaPresidentesRequeridos, true);

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

      // --- Lógica de Botones (ACTUALIZADA) ---
      // Solo puede firmar o dar feedback si:
      // 1. Es un presidente requerido Y
      // 2. No ha firmado esta versión AÚN Y
      // 3. La minuta NO está bloqueada por feedback de otro presidente
      $puedeAccionar = $esPresidenteRequerido && !$usuarioHaFirmado && !$tieneFeedback;
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
          <div class="row">
            <div class="col-md-12">
              <strong>Adjuntos:</strong><br>
              <?php if ($totalAdjuntos > 0) : ?>
                <button type="button" class="btn btn-info btn-sm" title="Ver adjuntos" onclick="verAdjuntos(<?= $idMinuta; ?>)">
                  <i class="fas fa-paperclip"></i> (<?= $totalAdjuntos; ?>)
                </button>
              <?php else : ?>
                <span class="text-muted">No posee archivos adjuntos</span>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <div class="card-footer bg-light text-end">

          <a href="menu.php?pagina=editar_minuta&id=<?= $idMinuta ?>" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-eye"></i> Ver Detalle
          </a>

          <?php if ($puedeAccionar) : ?>
            <button type="button" class="btn btn-warning btn-sm"
              id="btn-feedback-<?= $idMinuta ?>"
              onclick="enviarFeedback(<?= $idMinuta ?>)">
              <i class="fas fa-comment-dots"></i> Enviar Feedback
            </button>

            <button type="button" class="btn btn-success btn-sm"
              id="btn-aprobar-<?= $idMinuta ?>"
              onclick="aprobarMinuta(<?= $idMinuta ?>)">
              <i class="fas fa-check"></i> Aprobar con Firma
            </button>
          <?php elseif ($esPresidenteRequerido && $usuarioHaFirmado) : ?>
            <span class="text-success fw-bold me-2"><i class="fas fa-check-circle"></i> Ya has firmado esta versión.</span>
          <?php endif; ?>

        </div>
      </div>
    <?php endforeach; ?>

    <?php renderPagination($pPage, $totalPages); ?>

  <?php else : ?>
    <p class="text-muted">No hay minutas pendientes de aprobación.</p>
  <?php endif; ?>
</div>

<script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
  function aprobarMinuta(idMinuta) {
    const boton = document.getElementById('btn-aprobar-' + idMinuta);
    const botonFeedback = document.getElementById('btn-feedback-' + idMinuta);
    boton.disabled = true;
    if (botonFeedback) botonFeedback.disabled = true;
    boton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';

    Swal.fire({
      title: '¿Confirmar Aprobación?',
      text: "Esta acción registrará su firma digital y no se puede deshacer. ¿Está seguro?",
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#28a745',
      cancelButtonColor: '#6c757d',
      confirmButtonText: 'Sí, aprobar',
      cancelButtonText: 'Cancelar'
    }).then((result) => {
      if (result.isConfirmed) {
        // Llamada al controlador que maneja la firma
        fetch('../controllers/aprobar_minuta.php', {
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
              // (MODIFICADO) Leer el error como texto
              return response.text().then(text => {
                console.error("Respuesta de error del servidor:", text);
                throw new Error('Error del servidor (ver consola).');
              });
            }
            return response.json();
          })
          .then(data => {
            if (data.status === 'success_final') {
              // --- ¡FIRMA FINAL! ---
              Swal.fire({
                title: '¡Aprobada!',
                text: data.message, // 'La minuta ha sido aprobada...'
                icon: 'success',
                timer: 2500,
                showConfirmButton: false
              }).then(() => {
                document.getElementById('card-minuta-' + idMinuta).style.display = 'none';
              });
            } else if (data.status === 'success_partial') {
              // --- FIRMA PARCIAL ---
              Swal.fire({
                title: 'Firma Registrada',
                text: data.message, // "Firma registrada. Faltan X aprobación(es) más."
                icon: 'info'
              }).then(() => {
                location.reload();
              });
            } else {
              throw new Error(data.message || 'Error desconocido al aprobar.');
            }
          })
          .catch(error => {
            Swal.fire('Error', error.message, 'error');
            boton.disabled = false;
            if (botonFeedback) botonFeedback.disabled = false;
            boton.innerHTML = '<i class="fas fa-check"></i> Aprobar con Firma';
          });
      } else {
        boton.disabled = false;
        if (botonFeedback) botonFeedback.disabled = false;
        boton.innerHTML = '<i class="fas fa-check"></i> Aprobar con Firma';
      }
    });
  }

  // (NUEVA) Función para Enviar Feedback (Punto 7)
  function enviarFeedback(idMinuta) {
    const boton = document.getElementById('btn-aprobar-' + idMinuta);
    const botonFeedback = document.getElementById('btn-feedback-' + idMinuta);

    Swal.fire({
      title: 'Enviar Feedback al Secretario',
      input: 'textarea',
      inputLabel: 'Observaciones',
      inputPlaceholder: 'Escriba aquí sus correcciones o comentarios para el Secretario Técnico...',
      inputAttributes: {
        'aria-label': 'Escriba sus observaciones'
      },
      showCancelButton: true,
      confirmButtonText: 'Enviar Feedback',
      confirmButtonColor: '#ffc107',
      cancelButtonText: 'Cancelar',
      showLoaderOnConfirm: true,
      preConfirm: (texto) => {
        if (!texto || texto.trim().length < 10) {
          Swal.showValidationMessage(`Por favor, ingrese un comentario (mínimo 10 caracteres).`);
          return false;
        }

        if (boton) boton.disabled = true;
        if (botonFeedback) botonFeedback.disabled = true;

        // (NUEVO) Llamada al NUEVO controlador
        return fetch('../controllers/enviar_feedback.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json'
            },
            body: JSON.stringify({
              idMinuta: idMinuta,
              feedback: texto
            })
          })
          .then(response => {
            if (!response.ok) {
              return response.text().then(text => {
                console.error("Respuesta de error del servidor (feedback):", text);
                throw new Error('Error del servidor (ver consola).');
              });
            }
            return response.json();
          })
          .catch(error => {
            Swal.showValidationMessage(`Error: ${error.message}`);
            if (boton) boton.disabled = false;
            if (botonFeedback) botonFeedback.disabled = false;
          });
      },
      allowOutsideClick: () => !Swal.isLoading()
    }).then((result) => {
      if (result.isConfirmed && result.value.status === 'success') {
        Swal.fire({
          title: 'Feedback Enviado',
          text: 'Se ha notificado al Secretario Técnico. La minuta queda en espera de revisión.',
          icon: 'success'
        }).then(() => {
          location.reload();
        });
      } else if (!result.isConfirmed) {
        // Si el usuario cancela, reactivar botones
        if (boton) boton.disabled = false;
        if (botonFeedback) botonFeedback.disabled = false;
      }
    });
  }

  function verAdjuntos(idMinuta) {
    // (NUEVO) Esta lógica ahora es necesaria
    Swal.fire({
      title: 'Cargando Adjuntos...',
      didOpen: () => {
        Swal.showLoading();
        fetch(`/corevota/controllers/obtener_adjuntos.php?idMinuta=${idMinuta}`)
          .then(response => response.json())
          .then(data => {
            if (data.status === 'success' && data.data.length > 0) {
              let html = '<ul class="list-group list-group-flush text-start">';
              data.data.forEach(adj => {
                const url = (adj.tipoAdjunto === 'file' || adj.tipoAdjunto === 'asistencia') ? `/corevota/${adj.pathAdjunto}` : adj.pathAdjunto;
                const icon = (adj.tipoAdjunto === 'link') ? '🔗' : (adj.tipoAdjunto === 'asistencia' ? '👥' : '📄');
                const nombre = adj.pathAdjunto.split('/').pop();
                html += `<li class="list-group-item"><a href="${url}" target="_blank">${icon} ${nombre}</a></li>`;
              });
              html += '</ul>';
              Swal.update({
                title: 'Adjuntos de la Minuta',
                html: html,
                showConfirmButton: true,
                icon: 'info'
              });
            } else {
              Swal.fire('Sin Adjuntos', 'Esta minuta no tiene archivos adjuntos.', 'info');
            }
          })
          .catch(err => {
            Swal.fire('Error', 'No se pudieron cargar los adjuntos.', 'error');
          });
      }
    });
  }
</script>