<?php
require_once("../../cfg/config.php");

class MinutaPendiente extends BaseConexion {
    public function obtenerMinutas() {
        $conexion = $this->conectar();

        $sql = "
            SELECT 
                m.idMinuta,
                c.nombreComision,
                u.pNombre AS presidenteNombre,
                u.aPaterno AS presidenteApellido,
                m.fechaMinuta AS fecha,
                m.horaMinuta AS hora,
                s.numeroSesion AS nSesion,
                st.pNombre AS secretarioNombre,
                st.aPaterno AS secretarioApellido
            FROM t_minuta m
            INNER JOIN t_comision c ON c.idComision = m.t_acuerdo_idAcuerdo
            LEFT JOIN t_usuario u ON u.idUsuario = c.id_presidente
            LEFT JOIN t_sesion s ON s.idSesion = m.t_voto_t_propuesta_t_acuerdo_idAcuerdo
            LEFT JOIN t_usuario st ON st.idUsuario = s.secretario_id
            ORDER BY m.idMinuta DESC
        ";

        $stmt = $conexion->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

$minutaModel = new MinutaPendiente();
$minutas = $minutaModel->obtenerMinutas();
?>

<div class="container mt-4">
  <h4 class="fw-bold mb-4">Minutas Pendientes</h4>

  <?php if (!empty($minutas)): ?>
    <?php foreach ($minutas as $minuta): ?>
      <div class="card mb-4 shadow-sm">
        <div class="card-header bg-primary text-white fw-bold">
          Minuta N° <?= htmlspecialchars($minuta['idMinuta']) ?>
        </div>
        <div class="card-body">
          <div class="row mb-2">
            <div class="col-md-3"><strong>Comisión:</strong><br><?= htmlspecialchars($minuta['nombreComision']) ?></div>
            <div class="col-md-3"><strong>Presidente:</strong><br><?= htmlspecialchars($minuta['presidenteNombre'] . " " . $minuta['presidenteApellido']) ?></div>
            <div class="col-md-3"><strong>Fecha:</strong><br><?= date("d-m-Y", strtotime($minuta['fecha'])) ?></div>
            <div class="col-md-3"><strong>Hora:</strong><br><?= date("H:i", strtotime($minuta['hora'])) ?></div>
          </div>
          <div class="row">
            <div class="col-md-3"><strong>N° Sesión:</strong><br><?= htmlspecialchars($minuta['nSesion']) ?></div>
            <div class="col-md-3"><strong>Secretario Técnico:</strong><br><?= htmlspecialchars($minuta['secretarioNombre'] . " " . $minuta['secretarioApellido']) ?></div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  <?php else: ?>
    <p class="text-muted">No hay minutas registradas.</p>
  <?php endif; ?>
</div>
