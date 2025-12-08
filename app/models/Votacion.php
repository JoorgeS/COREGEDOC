<?php

namespace App\Models;

use App\Config\Database;
use PDO;
use Exception;

class Votacion
{
    private $conn;

    public function __construct()
    {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public function getVotacionById($id) {
        $sql = "SELECT * FROM t_votacion WHERE idVotacion = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function crear($datos)
    {
        $sql = "INSERT INTO t_votacion (nombreVotacion, t_minuta_idMinuta, idComision, fechaCreacion, habilitada) 
                VALUES (:nombre, :idMinuta, :idComision, NOW(), 0)";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':nombre' => $datos['nombre'],
            ':idMinuta' => $datos['idMinuta'],
            ':idComision' => $datos['idComision'] ?? null
        ]);
        return $this->conn->lastInsertId();
    }

    public function cambiarEstado($idVotacion, $nuevoEstado)
    {
        $sql = "UPDATE t_votacion SET habilitada = :estado WHERE idVotacion = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':estado' => $nuevoEstado, ':id' => $idVotacion]);
    }

    public function verificarVotoUsuario($idVotacion, $idUsuario) {
        $sql = "SELECT opcionVoto FROM t_voto 
                WHERE (idVotacion = :id OR t_votacion_idVotacion = :id) 
                AND (idUsuario = :user OR t_usuario_idUsuario = :user) LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $idVotacion, ':user' => $idUsuario]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function registrarVoto($idVotacion, $idUsuario, $opcion)
    {
        if ($this->verificarVotoUsuario($idVotacion, $idUsuario)) {
            throw new Exception("Ya has emitido tu voto.");
        }
        $sql = "INSERT INTO t_voto (t_votacion_idVotacion, t_usuario_idUsuario, idVotacion, idUsuario, opcionVoto, fechaVoto, origenVoto) 
                VALUES (:idVotacion, :idUsuario, 0, 0, :opcion, NOW(), 'SALA_VIRTUAL')";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':idVotacion' => $idVotacion, ':idUsuario' => $idUsuario, ':opcion' => $opcion]);
    }

    public function obtenerVotacionActiva($idUsuario)
    {
        // Trae la última activa donde el usuario NO haya votado aún
        $sql = "SELECT v.* FROM t_votacion v
                WHERE v.habilitada = 1 
                ORDER BY v.idVotacion DESC LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
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

    public function getResultadosHistoricos() {
    return [];
}
public function getHistorialGlobalFiltrado($filtros, $limit, $offset)
    {
        // 1. Construcción de filtros dinámicos
        $where = " WHERE v.habilitada = 0 "; // Solo votaciones cerradas
        $params = [];

        if (!empty($filtros['desde'])) {
            $where .= " AND DATE(v.fechaCreacion) >= :desde ";
            $params[':desde'] = $filtros['desde'];
        }
        if (!empty($filtros['hasta'])) {
            $where .= " AND DATE(v.fechaCreacion) <= :hasta ";
            $params[':hasta'] = $filtros['hasta'];
        }
        if (!empty($filtros['comision'])) {
            $where .= " AND v.idComision = :comision ";
            $params[':comision'] = $filtros['comision'];
        }
        if (!empty($filtros['q'])) {
            $term = '%' . trim($filtros['q']) . '%';
            $where .= " AND (v.nombreVotacion LIKE :q OR r.nombreReunion LIKE :q OR m.objetivo LIKE :q) ";
            $params[':q'] = $term;
        }

        // 2. Query para contar el total (para la paginación)
        $sqlCount = "SELECT COUNT(*) 
                     FROM t_votacion v
                     LEFT JOIN t_minuta m ON v.t_minuta_idMinuta = m.idMinuta
                     LEFT JOIN t_reunion r ON r.t_minuta_idMinuta = m.idMinuta
                     $where";
        
        $stmtCount = $this->conn->prepare($sqlCount);
        foreach ($params as $key => $val) { $stmtCount->bindValue($key, $val); }
        $stmtCount->execute();
        $total = $stmtCount->fetchColumn();

        // 3. Query principal para la tabla (Conteo de votos y Resultado)
        $sqlData = "SELECT 
                        v.idVotacion, 
                        v.nombreVotacion, 
                        v.fechaCreacion,
                        c.nombreComision,
                        COALESCE(r.nombreReunion, 'Sin Reunión Asignada') as nombreReunion,
                        
                        -- Conteos
                        (SELECT COUNT(*) FROM t_voto WHERE (idVotacion = v.idVotacion OR t_votacion_idVotacion = v.idVotacion) AND opcionVoto IN ('SI', 'APRUEBO')) as votos_si,
                        (SELECT COUNT(*) FROM t_voto WHERE (idVotacion = v.idVotacion OR t_votacion_idVotacion = v.idVotacion) AND opcionVoto IN ('NO', 'RECHAZO')) as votos_no,
                        (SELECT COUNT(*) FROM t_voto WHERE (idVotacion = v.idVotacion OR t_votacion_idVotacion = v.idVotacion) AND opcionVoto = 'ABSTENCION') as votos_abs,

                        -- Cálculo de Resultado SQL
                        CASE 
                            WHEN (SELECT COUNT(*) FROM t_voto WHERE (idVotacion = v.idVotacion OR t_votacion_idVotacion = v.idVotacion) AND opcionVoto IN ('SI', 'APRUEBO')) > 
                                 (SELECT COUNT(*) FROM t_voto WHERE (idVotacion = v.idVotacion OR t_votacion_idVotacion = v.idVotacion) AND opcionVoto IN ('NO', 'RECHAZO')) 
                            THEN 'APROBADA'
                            
                            WHEN (SELECT COUNT(*) FROM t_voto WHERE (idVotacion = v.idVotacion OR t_votacion_idVotacion = v.idVotacion) AND opcionVoto IN ('NO', 'RECHAZO')) > 
                                 (SELECT COUNT(*) FROM t_voto WHERE (idVotacion = v.idVotacion OR t_votacion_idVotacion = v.idVotacion) AND opcionVoto IN ('SI', 'APRUEBO')) 
                            THEN 'RECHAZADA'
                            
                            ELSE 'EMPATE'
                        END as resultado_final

                    FROM t_votacion v
                    LEFT JOIN t_comision c ON v.idComision = c.idComision
                    LEFT JOIN t_minuta m ON v.t_minuta_idMinuta = m.idMinuta
                    LEFT JOIN t_reunion r ON r.t_minuta_idMinuta = m.idMinuta
                    $where
                    ORDER BY v.fechaCreacion DESC
                    LIMIT :limit OFFSET :offset";

        $stmt = $this->conn->prepare($sqlData);
        foreach ($params as $key => $val) { $stmt->bindValue($key, $val); }
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'data' => $data,
            'total' => $total,
            'totalPages' => ceil($total / $limit)
        ];
    }
}