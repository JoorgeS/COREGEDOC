<?php
// =======================================
// CONTROLADOR DE VOTACIONES - CORE VOTA
// =======================================
require_once __DIR__ . '/../class/class.conectorDB.php';

class VotacionController
{
    private $pdo;

    public function __construct()
    {
        $db = new conectorDB();
        $this->pdo = $db->getDatabase();
    }

    // ======================================================
    // 1️⃣ CREAR VOTACIÓN
    // ======================================================
    public function storeVotacion($data)
    {
        $nombre = trim($data['nombreVotacion'] ?? '');
        $idComision = intval($data['t_comision_idComision'] ?? 0);
        $habilitada = isset($data['habilitada']) ? 1 : 0;
        $idTema = intval($data['idTema'] ?? 0); // opcional, por ahora 0

        if ($nombre === '' || $idComision <= 0) {
            return ['status' => 'error', 'message' => 'Debe ingresar el nombre de la votación y seleccionar una comisión.'];
        }

        try {
            $this->pdo->beginTransaction();

            $sql = "INSERT INTO t_votacion (nombreVotacion, idComision, idTema, habilitada)
                    VALUES (:nombre, :idComision, :idTema, :habilitada)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':nombre' => $nombre,
                ':idComision' => $idComision,
                ':idTema' => $idTema ?: null,
                ':habilitada' => $habilitada
            ]);

            $this->pdo->commit();
            return ['status' => 'success', 'message' => 'Votación creada correctamente.'];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['status' => 'error', 'message' => 'Error al crear votación: ' . $e->getMessage()];
        }
    }

    // ======================================================
    // 2️⃣ LISTAR VOTACIONES
    // ======================================================
    public function listar()
    {
        try {
            $sql = "SELECT v.idVotacion, v.nombreVotacion, v.habilitada, 
                           c.nombreComision, v.fechaCreacion
                    FROM t_votacion v
                    INNER JOIN t_comision c ON v.idComision = c.idComision
                    ORDER BY v.idVotacion DESC";
            $stmt = $this->pdo->query($sql);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return ['status' => 'success', 'data' => $result];
        } catch (Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage(), 'data' => []];
        }
    }

    // ======================================================
    // 3️⃣ CAMBIAR ESTADO HABILITADA/DESHABILITADA
    // ======================================================
    public function cambiarEstado($idVotacion, $nuevoEstado)
    {
        try {
            $sql = "UPDATE t_votacion SET habilitada = :estado WHERE idVotacion = :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':estado' => $nuevoEstado, ':id' => $idVotacion]);
            return ['status' => 'success', 'message' => 'Estado actualizado correctamente.'];
        } catch (Exception $e) {
            return ['status' => 'error', 'message' => 'Error al cambiar el estado: ' . $e->getMessage()];
        }
    }
}
