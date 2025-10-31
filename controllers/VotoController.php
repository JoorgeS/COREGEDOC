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
            // MODIFICACIÓN: La clave única es (idUsuario, idVotacion)
            // Tu SQL original usaba idVotacion dos veces, lo corregí.
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
                return ['status' => 'error', 'message' => 'El usuario ya ha votado en esta votación.'];
            }

            // MODIFICACIÓN: Añadir idUsuarioRegistra a la consulta
            $sql = "
        INSERT INTO t_voto (t_votacion_idVotacion, t_usuario_idUsuario, opcionVoto, fechaVoto, idUsuarioRegistra)
        VALUES (:votacion, :usuario, :opcion, NOW(), :idRegistra)
      ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':votacion' => $idVotacion,
                ':usuario' => $idUsuario,
                ':opcion'  => $opcionVoto,
                ':idRegistra' => $idUsuarioRegistra // Nuevo campo
            ]);

            return ['status' => 'success', 'message' => '✅ Voto registrado correctamente.'];
        } catch (PDOException $e) {
            return ['status' => 'error', 'message' => 'Error al registrar voto: ' . $e->getMessage()];
        }
    }

    // Mostrar resultados de una votación
    public function resultados($idVotacion)
    {
        $sql = "SELECT opcionVoto, COUNT(*) AS cantidad
                FROM t_voto
                WHERE idVotacion = :id
                GROUP BY opcionVoto";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $idVotacion]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
