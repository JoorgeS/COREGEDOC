<?php
require_once __DIR__ . '/../../class/class.conectorDB.php';
$db = new conectorDB();
$pdo = $db->getDatabase();

// ✅ Buscar votación activa
$sqlVotacion = "SELECT idVotacion, nombreVotacion 
                FROM t_votacion 
                WHERE habilitada = 1 
                ORDER BY idVotacion DESC 
                LIMIT 1";
$stmtV = $pdo->query($sqlVotacion);
$votacionActiva = $stmtV->fetch(PDO::FETCH_ASSOC);
$idVotacionActiva = $votacionActiva['idVotacion'] ?? null;
$nombreVotacion = $votacionActiva['nombreVotacion'] ?? 'Sin votación activa';
?>

<div class="container mt-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="fw-bold text-success mb-0">
      <i class="fa-solid fa-square-poll-vertical me-2"></i>Votación en Curso
    </h3>
    <span class="badge bg-success fs-6 px-3 py-2 shadow-sm">
      <?= htmlspecialchars($nombreVotacion) ?>
    </span>
  </div>

  <!-- 🔹 Resumen dinámico -->
  <div id="resumenVotos" class="d-flex gap-3 mb-3">
    <span class="badge bg-success fs-6">Sí: 0</span>
    <span class="badge bg-danger fs-6">No: 0</span>
    <span class="badge bg-secondary fs-6">Abstención: 0</span>
    <span class="badge bg-dark fs-6">Sin votar: 0</span>
  </div>

  <table class="table table-hover table-bordered align-middle text-center shadow-sm" id="tablaVotos">
    <thead class="table-success">
      <tr>
        <th style="width:5%;">#</th>
        <th style="width:65%;">Consejero Regional</th>
        <th style="width:30%;">Voto Emitido</th>
      </tr>
    </thead>
    <tbody id="tbodyVotos">
      <tr><td colspan="3" class="text-muted">Cargando votos...</td></tr>
    </tbody>
  </table>
</div>

<script>
// 🔁 Recargar datos cada 1 segundo SIN usar caché
const idVotacion = <?= $idVotacionActiva ? (int)$idVotacionActiva : 'null' ?>;

function actualizarVotos() {
  if (!idVotacion) return;

  fetch(`/corevota/controllers/fetch_votos.php?idVotacion=${idVotacion}&t=${Date.now()}`, { cache: "no-store" })
    .then(res => res.json())
    .then(data => {
      const tbody = document.getElementById('tbodyVotos');
      const resumen = { SI: 0, NO: 0, ABSTENCION: 0, SINVOTO: 0 };

      tbody.innerHTML = '';

      data.forEach((v, i) => {
        let color = 'secondary', texto = 'Sin votar';
        if (v.opcionVoto === 'SI') { color = 'success'; texto = 'Sí'; resumen.SI++; }
        else if (v.opcionVoto === 'NO') { color = 'danger'; texto = 'No'; resumen.NO++; }
        else if (v.opcionVoto === 'ABSTENCION') { color = 'secondary'; texto = 'Abstención'; resumen.ABSTENCION++; }
        else { resumen.SINVOTO++; }

        tbody.innerHTML += `
          <tr>
            <td>${i + 1}</td>
            <td>${v.nombre}</td>
            <td><span class="badge bg-${color} fs-6 px-3 py-2">${texto}</span></td>
          </tr>`;
      });

      // 🔹 Actualizar resumen superior
      document.getElementById('resumenVotos').innerHTML = `
        <span class="badge bg-success fs-6">Sí: ${resumen.SI}</span>
        <span class="badge bg-danger fs-6">No: ${resumen.NO}</span>
        <span class="badge bg-secondary fs-6">Abstención: ${resumen.ABSTENCION}</span>
        <span class="badge bg-dark fs-6">Sin votar: ${resumen.SINVOTO}</span>
      `;
    })
    .catch(err => console.error('Error al actualizar votos:', err));
}

setInterval(actualizarVotos, 1000);
actualizarVotos();
</script>
