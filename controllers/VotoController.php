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
    public function registrarVoto($idUsuario, $idVotacion, $opcion, $desc = null)
    {
        try {
            // Evitar voto duplicado
            $check = $this->pdo->prepare("SELECT idVoto FROM t_voto WHERE idUsuario = :usuario AND idVotacion = :votacion");
            $check->execute([':usuario' => $idUsuario, ':votacion' => $idVotacion]);
            if ($check->fetch()) {
                return ['status' => 'error', 'message' => 'Ya has votado en esta votación.'];
            }

            $sql = "INSERT INTO t_voto (idVotacion, idUsuario, opcionVoto, descVoto)
                    VALUES (:votacion, :usuario, :opcion, :desc)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':votacion' => $idVotacion,
                ':usuario' => $idUsuario,
                ':opcion' => $opcion,
                ':desc' => $desc
            ]);

            return ['status' => 'success', 'message' => 'Voto registrado correctamente.'];
        } catch (Exception $e) {
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
