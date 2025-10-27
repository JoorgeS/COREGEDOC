<?php
require_once __DIR__ . '/../class/class.conectorDB.php';
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');

$db = new conectorDB();
$pdo = $db->getDatabase();

$idVotacion = $_GET['idVotacion'] ?? null;

// Si no llega ID, tomar la votación activa
if (!$idVotacion) {
  $sqlLast = "SELECT idVotacion FROM t_votacion WHERE habilitada = 1 ORDER BY idVotacion DESC LIMIT 1";
  $stmt = $pdo->query($sqlLast);
  $idVotacion = $stmt->fetchColumn();
}

// Consulta principal
$sql = "
SELECT 
  u.idUsuario,
  CONCAT(u.pNombre, ' ', u.aPaterno) AS nombre,
  v.opcionVoto
FROM t_usuario u
LEFT JOIN t_voto v 
  ON v.idUsuario = u.idUsuario
  AND v.idVotacion = :idVotacion
WHERE u.tipoUsuario_id = 1
ORDER BY u.aPaterno ASC;
";

$stmt = $pdo->prepare($sql);
$stmt->execute([':idVotacion' => $idVotacion]);
$result = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Enviar respuesta JSON
echo json_encode($result);
exit;
