<?php
namespace App\Models;

use App\Config\Database;
use PDO;
use PDOException;
use Exception;   

class Votacion
{
    private $conn;

    public function __construct()
    {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public function crear($datos)
    {
        $sql = "INSERT INTO t_votacion (nombreVotacion, t_minuta_idMinuta, idComision, fechaCreacion, habilitada) 
                VALUES (:nombre, :idMinuta, :idComision, NOW(), 0)";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':nombre' => $datos['nombre'],
            ':idMinuta' => $datos['idMinuta'],
            // CORRECCIÓN: Si no viene comisión, enviamos NULL, no 0.
            ':idComision' => $datos['idComision'] ?? null 
        ]);
        
        return $this->conn->lastInsertId();
    }

    public function listarPorMinuta($idMinuta)
    {
        $sql = "SELECT v.*, c.nombreComision 
                FROM t_votacion v 
                LEFT JOIN t_comision c ON v.idComision = c.idComision
                WHERE v.t_minuta_idMinuta = :idMinuta 
                ORDER BY v.idVotacion DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':idMinuta' => $idMinuta]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function cambiarEstado($idVotacion, $nuevoEstado)
    {
        $sql = "UPDATE t_votacion SET habilitada = :estado WHERE idVotacion = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':estado' => $nuevoEstado, ':id' => $idVotacion]);
    }

    public function obtenerResultados($idMinuta)
    {
        // Traemos las votaciones y calculamos los votos
        $votaciones = $this->listarPorMinuta($idMinuta);
        $resultados = [];

        foreach ($votaciones as $v) {
            $sqlVotos = "SELECT opcionVoto, COUNT(*) as total FROM t_voto WHERE idVotacion = :id GROUP BY opcionVoto";
            $stmt = $this->conn->prepare($sqlVotos);
            $stmt->execute([':id' => $v['idVotacion']]);
            $conteo = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // Array [ 'SI' => 5, 'NO' => 2 ]

            // Detalles de quién votó qué (para el Secretario)
            $sqlDetalle = "SELECT u.pNombre, u.aPaterno, t.opcionVoto 
                           FROM t_voto t 
                           JOIN t_usuario u ON t.idUsuario = u.idUsuario 
                           WHERE t.idVotacion = :id";
            $stmtDet = $this->conn->prepare($sqlDetalle);
            $stmtDet->execute([':id' => $v['idVotacion']]);
            $detalle = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

            $resultados[] = [
                'idVotacion' => $v['idVotacion'],
                'nombre' => $v['nombreVotacion'],
                'habilitada' => $v['habilitada'],
                'votos' => [
                    'SI' => $conteo['SI'] ?? 0,
                    'NO' => $conteo['NO'] ?? 0,
                    'ABSTENCION' => $conteo['ABSTENCION'] ?? 0
                ],
                'detalle' => $detalle
            ];
        }
        return $resultados;
    }

    public function obtenerVotacionActiva($idUsuario)
    {
        // Buscamos una votación que esté HABILITADA (1)
        // y donde este usuario NO tenga un voto registrado.
        // Priorizamos la más reciente.
        $sql = "SELECT v.*, m.t_comision_idComision 
                FROM t_votacion v
                LEFT JOIN t_minuta m ON v.t_minuta_idMinuta = m.idMinuta
                WHERE v.habilitada = 1 
                AND NOT EXISTS (
                    SELECT 1 FROM t_voto vo 
                    WHERE vo.idVotacion = v.idVotacion 
                    AND vo.idUsuario = :idUsuario
                )
                ORDER BY v.idVotacion DESC LIMIT 1";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':idUsuario' => $idUsuario]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function registrarVoto($idVotacion, $idUsuario, $opcion)
    {
        // Verificar nuevamente si ya votó para evitar duplicados por lag
        $check = "SELECT idVoto FROM t_voto WHERE idVotacion = :idVotacion AND idUsuario = :idUsuario";
        $stmtCheck = $this->conn->prepare($check);
        $stmtCheck->execute([':idVotacion' => $idVotacion, ':idUsuario' => $idUsuario]);
        
        if ($stmtCheck->fetch()) {
            throw new Exception("Ya has emitido tu voto para esta votación.");
        }

        $sql = "INSERT INTO t_voto (idVotacion, idUsuario, opcionVoto, fechaVoto, origenVoto) 
                VALUES (:idVotacion, :idUsuario, :opcion, NOW(), 'SALA_VIRTUAL')";
        
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':idVotacion' => $idVotacion,
            ':idUsuario' => $idUsuario,
            ':opcion' => $opcion
        ]);
    }
    public function getHistorialVotosPersonal($idUsuario)
    {
        $sql = "SELECT v.nombreVotacion, vo.opcionVoto, vo.fechaVoto, 
                       CASE WHEN v.habilitada = 1 THEN 'Abierta' ELSE 'Cerrada' END as estado
                FROM t_voto vo
                JOIN t_votacion v ON vo.idVotacion = v.idVotacion
                WHERE vo.idUsuario = :id
                ORDER BY vo.fechaVoto DESC LIMIT 20";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $idUsuario]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getResultadosHistoricos()
    {
        // Trae las últimas 10 votaciones cerradas con sus totales
        $sql = "SELECT v.idVotacion, v.nombreVotacion, v.fechaCreacion,
                    (SELECT COUNT(*) FROM t_voto WHERE idVotacion = v.idVotacion AND opcionVoto = 'SI') as si,
                    (SELECT COUNT(*) FROM t_voto WHERE idVotacion = v.idVotacion AND opcionVoto = 'NO') as no,
                    (SELECT COUNT(*) FROM t_voto WHERE idVotacion = v.idVotacion AND opcionVoto = 'ABSTENCION') as abs
                FROM t_votacion v
                WHERE v.habilitada = 0 
                ORDER BY v.fechaCreacion DESC LIMIT 10";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}