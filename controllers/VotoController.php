<?php
require_once __DIR__ . '/../class/class.conectorDB.php';

class VotoController
{
    private $pdo;

  public function __construct()
  {
    $db = new conectorDB();
    $this->pdo = $db->getDatabase();
  }

  // Registrar voto
  public function registrarVoto($idVotacion, $idUsuario, $opcionVoto, $idUsuarioRegistra = null)
  {
    try {
      // 🟨 CORRECCIÓN: Usar las columnas con prefijo 't_'
      $check = $this->pdo->prepare("
        SELECT idVoto 
        FROM t_voto 
        WHERE t_usuario_idUsuario = :usuario AND t_votacion_idVotacion = :votacion
      ");
      $check->execute([
        ':usuario' => $idUsuario,
        ':votacion' => $idVotacion
      ]);

      if ($check->fetch()) {
        return ['status' => 'error', 'message' => 'Ya has votado en esta votación.'];
      }

      // 🟨 CORRECCIÓN: Insertar en las columnas correctas + idUsuarioRegistra
      $sql = "
        INSERT INTO t_voto (t_votacion_idVotacion, t_usuario_idUsuario, opcionVoto, fechaVoto, idUsuarioRegistra)
        VALUES (:votacion, :usuario, :opcion, NOW(), :idRegistra)
      ";
      $stmt = $this->pdo->prepare($sql);
      $stmt->execute([
        ':votacion' => $idVotacion,
        ':usuario' => $idUsuario,
        ':opcion'  => $opcionVoto,
        ':idRegistra' => $idUsuarioRegistra
      ]);

      return ['status' => 'success', 'message' => '✅ Voto registrado correctamente.'];

    } catch (PDOException $e) {
      return [
        'status' => 'error',
        'message' => '❌ Error al registrar voto: ' . $e->getMessage()
      ];
    }
  }

  // Mostrar resultados
  public function resultados($idVotacion)
  {
    // 🟨 CORRECCIÓN: Usar la columna con prefijo 't_'
    $sql = "
      SELECT opcionVoto, COUNT(*) AS cantidad
      FROM t_voto
      WHERE t_votacion_idVotacion = :id
      GROUP BY opcionVoto
    ";
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([':id' => $idVotacion]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }
}
