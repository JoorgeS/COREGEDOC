<?php
require_once("../../cfg/config.php");

class MinutaPendiente extends BaseConexion {
    public function obtenerMinutas() {
        $conexion = $this->conectar();

        // --- Paginación segura ---
        $pPage   = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
        $perPage = isset($_GET['per_page']) ? max(1, (int)$_GET['per_page']) : 10;
        $offset  = ($pPage - 1) * $perPage;

        // Total de filas (para paginación)
        $sqlCount = "SELECT COUNT(*) AS total FROM t_minuta";
        $totalRows = (int)$conexion->query($sqlCount)->fetchColumn();
        $totalPages = max(1, (int)ceil($totalRows / $perPage));

        // Consulta principal (usa solo columnas reales) + LIMIT/OFFSET
        $sql = "
            SELECT 
                m.idMinuta,
                m.nombreComision,
                u.pNombre AS presidenteNombre,
                u.aPaterno AS presidenteApellido,
                m.fechaMinuta AS fecha,
                m.horaMinuta AS hora,
                NULL AS nSesion,            -- no existe numeroSesion en tu BD
                NULL AS secretarioNombre,   -- no existe secretario_id en tu BD
                NULL AS secretarioApellido, -- idem
                (
                    SELECT COUNT(*)
                    FROM t_adjunto a
                    WHERE a.t_minuta_idMinuta = m.idMinuta
                ) AS totalAdjuntos
            FROM t_minuta m
            LEFT JOIN t_usuario u 
                   ON u.idUsuario = m.nombrePresidente
            ORDER BY m.idMinuta DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $conexion->prepare($sql);
        $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'data'       => $rows,
            'page'       => $pPage,
            'per_page'   => $perPage,
            'total'      => $totalRows,
            'totalPages' => $totalPages
        ];
    }
}

$minutaModel = new MinutaPendiente();
$res        = $minutaModel->obtenerMinutas();
$minutas    = $res['data'] ?? [];
$pPage      = $res['page'] ?? 1;
$perPage    = $res['per_page'] ?? 10;
$totalRows  = $res['total'] ?? 0;
$totalPages = $res['totalPages'] ?? 1;

// Helper de paginación (conserva querystring)
function renderPagination($current, $pages) {
    if ($pages <= 1) return;
    echo '<nav aria-label="Paginación"><ul class="pagination pagination-sm">';
    for ($i = 1; $i <= $pages; $i++) {
        $active = ($i === $current) ? ' active' : '';
        $qsArr  = $_GET;
        $qsArr['p'] = $i;
        $qs = http_build_query($qsArr);
        echo '<li class="page-item'.$active.'"><a class="page-link" href="?'.$qs.'">'.$i.'</a></li>';
    }
    echo '</ul></nav>';
}
?>

<div class="container mt-4">
  <h4 class="fw-bold mb-4">Minutas Pendientes</h4>

  <?php if (!empty($minutas)): ?>
    <?php foreach ($minutas as $minuta): ?>
      <?php
        $idMinuta      = (int)($minuta['idMinuta'] ?? 0);
        $totalAdjuntos = (int)($minuta['totalAdjuntos'] ?? 0);
      ?>
      <div class="card mb-4 shadow-sm">
        <div class="card-header bg-primary text-white fw-bold">
          Minuta N° <?= htmlspecialchars($minuta['idMinuta']) ?>
        </div>
        <div class="card-body">
          <div class="row mb-2">
            <div class="col-md-3"><strong>Comisión:</strong><br><?= htmlspecialchars($minuta['nombreComision'] ?? '—') ?></div>
            <div class="col-md-3"><strong>Presidente:</strong><br><?= htmlspecialchars(($minuta['presidenteNombre'] ?? '') . ' ' . ($minuta['presidenteApellido'] ?? '')) ?></div>
            <div class="col-md-3"><strong>Fecha:</strong><br><?= !empty($minuta['fecha']) ? date("d-m-Y", strtotime($minuta['fecha'])) : '—' ?></div>
            <div class="col-md-3"><strong>Hora:</strong><br><?= !empty($minuta['hora']) ? date("H:i", strtotime($minuta['hora'])) : '—' ?></div>
          </div>
          <div class="row mb-2">
            <div class="col-md-3"><strong>N° Sesión:</strong><br><?= htmlspecialchars($minuta['nSesion'] ?? '—') ?></div>
            <div class="col-md-3"><strong>Secretario Técnico:</strong><br><?= htmlspecialchars((($minuta['secretarioNombre'] ?? '') . ' ' . ($minuta['secretarioApellido'] ?? '')) ?: '—') ?></div>
          </div>
          <div class="row">
            <div class="col-md-12">
              <strong>Adjuntos:</strong><br>
              <?php if ($totalAdjuntos > 0): ?>
                <button type="button" class="btn btn-info btn-sm"
                        title="Ver adjuntos"
                        onclick="verAdjuntos(<?= $idMinuta; ?>)">
                  <i class="fas fa-paperclip"></i> (<?= $totalAdjuntos; ?>)
                </button>
              <?php else: ?>
                <span class="text-muted">No posee archivo adjunto</span>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>

    <?php renderPagination($pPage, $totalPages); ?>

  <?php else: ?>
    <p class="text-muted">No hay minutas registradas.</p>
  <?php endif; ?>
</div>
