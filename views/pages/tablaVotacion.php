<?php
require_once __DIR__ . '/../../class/class.conectorDB.php';

$db = new conectorDB();
$pdo = $db->getDatabase();

$idVotacion = $_GET['idVotacion'] ?? null;

if (!$idVotacion) {
  echo "<div class='alert alert-warning text-center m-4'>No se seleccionó ninguna votación.</div>";
 exit;
}

// 🔹 Info votación
$sqlInfo = "SELECT v.nombreVotacion, c.nombreComision
      FROM t_votacion v
      LEFT JOIN t_comision c ON v.idComision = c.idComision
      WHERE v.idVotacion = :id";
$stmtInfo = $pdo->prepare($sqlInfo);
$stmtInfo->execute([':id' => $idVotacion]);
$votacion = $stmtInfo->fetch(PDO::FETCH_ASSOC);

// 🔹 Consejeros
$sqlConsejeros = "SELECT idUsuario, pNombre, aPaterno
         FROM t_usuario
         WHERE tipoUsuario_id = 1
         ORDER BY aPaterno ASC, pNombre ASC";
$stmtCons = $pdo->query($sqlConsejeros);
$consejeros = $stmtCons->fetchAll(PDO::FETCH_ASSOC);

// 🔹 Votos emitidos (CORREGIDO)
// 🟨 CORRECCIÓN: Se usan las columnas con prefijo 't_'
$sqlVotos = "SELECT t_usuario_idUsuario, opcionVoto
      FROM t_voto
      WHERE t_votacion_idVotacion = :id";
$stmtVotos = $pdo->prepare($sqlVotos);
$stmtVotos->execute([':id' => $idVotacion]);
$votos = $stmtVotos->fetchAll(PDO::FETCH_KEY_PAIR); // Esto mapea t_usuario_idUsuario => opcionVoto

// 🔹 Contadores
$totalSI = $totalNO = $totalABS = 0;
foreach ($votos as $v) {
 if ($v === 'SI') $totalSI++;
 elseif ($v === 'NO') $totalNO++;
 elseif ($v === 'ABSTENCION') $totalABS++;
}
$totalConsejeros = count($consejeros);
$totalSinVotar = $totalConsejeros - count($votos);
?>

<div class="container mt-4">
 <div class="d-flex justify-content-between align-items-center mb-3">
  <h3 class="fw-bold text-success">
   <i class="fa-solid fa-chart-simple me-2"></i>
   <?= htmlspecialchars($votacion['nombreVotacion'] ?? 'Votación desconocida') ?>
  </h3>
  <a href="menu.php?pagina=voto_autogestion" class="btn btn-outline-secondary">
   <i class="fa-solid fa-arrow-left me-2"></i>Volver
  </a>
 </div>

 <p class="text-muted mb-4">
  <i class="fa-solid fa-landmark me-2 text-success"></i>
  Comisión: <strong><?= htmlspecialchars($votacion['nombreComision'] ?? 'No definida') ?></strong>
 </p>

 <!-- 🔹 Resumen -->
 <div class="d-flex justify-content-center gap-4 mb-4 text-center fw-bold fs-5">
  <div class="text-success">SÍ: <?= $totalSI ?></div>
  <div class="text-danger">NO: <?= $totalNO ?></div>
  <div class="text-secondary">ABSTENCIÓN: <?= $totalABS ?></div>
  <div class="text-dark">SIN VOTAR: <?= $totalSinVotar ?></div>
 </div>

 <!-- 🔹 Tabla dividida en dos columnas -->
 <div class="row">
  <?php
   $mitad = ceil($totalConsejeros / 2);
   $col1 = array_slice($consejeros, 0, $mitad);
   $col2 = array_slice($consejeros, $mitad);
   $columnas = [$col1, $col2];
  ?>

  <?php foreach ($columnas as $colIndex => $grupo): ?>
   <div class="col-md-6">
    <table class="table table-bordered text-center mb-4">
     <thead class="table-success">
      <tr>
       <th>#</th>
       <th>Consejero Regional</th>
       <th>Voto</th>
      </tr>
     </thead>
     <tbody>
      <?php foreach ($grupo as $i => $c): 
       // 🟨 CORRECCIÓN: $c['idUsuario'] es la key correcta
       $voto = $votos[$c['idUsuario']] ?? null;
       $color = match ($voto) {
        'SI' => 'text-success fw-bold',
        'NO' => 'text-danger fw-bold',
        'ABSTENCION' => 'text-secondary fw-bold',
        default => 'text-muted'
       };
       $texto = $voto ?: 'Sin votar';
      ?>
       <tr>
        <td><?= $i + 1 + ($colIndex * $mitad) ?></td>
        <td class="text-start ps-4"><?= htmlspecialchars($c['pNombre'] . ' ' . $c['aPaterno']) ?></td>
        <td class="<?= $color ?>"><?= htmlspecialchars($texto) ?></td>
       </tr>
      <?php endforeach; ?>
     </tbody>
    </table>
   </div>
  <?php endforeach; ?>
 </div>
</div>

<style>
.table th, .table td { vertical-align: middle; font-size: 0.95rem; }
.text-muted { color: #999 !important; }
</style>

<script>
// 🔁 Actualización automática (sin cambios)
setInterval(() => {
 fetch(window.location.href)
  .then(res => res.text())
  .then(html => {
   const parser = new DOMParser();
   const newDoc = parser.parseFromString(html, 'text/html');
   const newContainer = newDoc.querySelector('.container');
   if (newContainer) {
    document.querySelector('.container').innerHTML = newContainer.innerHTML;
   }
  })
  .catch(err => console.error('Error al actualizar tabla:', err));
}, 1500);
</script>