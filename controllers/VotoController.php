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
    public function registrarVoto($idVotacion, $idUsuario, $opcionVoto)
    {
        try {
            // Evitar duplicado
            $check = $this->pdo->prepare("
                SELECT idVoto 
                FROM t_voto 
                WHERE idUsuario = :usuario AND idVotacion = :votacion
            ");
            $check->execute([
                ':usuario' => $idUsuario,
                ':votacion' => $idVotacion
            ]);

            if ($check->fetch()) {
                return ['status' => 'error', 'message' => 'Ya has votado en esta votación.'];
            }

            // Insertar voto
            $sql = "
                INSERT INTO t_voto (idVotacion, idUsuario, opcionVoto, fechaVoto)
                VALUES (:votacion, :usuario, :opcion, NOW())
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':votacion' => $idVotacion,
                ':usuario'  => $idUsuario,
                ':opcion'   => $opcionVoto
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
        $sql = "
            SELECT opcionVoto, COUNT(*) AS cantidad
            FROM t_voto
            WHERE idVotacion = :id
            GROUP BY opcionVoto
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $idVotacion]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
