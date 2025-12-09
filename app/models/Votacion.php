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

    public function getVotacionById($id)
    {
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

    public function verificarVotoUsuario($idVotacion, $idUsuario)
    {
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

    public function getResultadosHistoricos()
    {
        return [];
    }
   public function getHistorialGlobalFiltrado($filtros, $limit, $offset, $idUsuario)
    {
        $params = [];
        
        $sql = "SELECT 
                    v.idVotacion, 
                    v.nombreVotacion, 
                    v.fechaCreacion, 
                    
                    -- Nombres seguros
                    COALESCE(r.nombreReunion, 'Sin Reunión Asignada') as nombreReunion,
                    COALESCE(c.nombreComision, 'General') as nombreComision,
                    
                    -- Contadores
                    (SELECT COUNT(*) FROM t_voto WHERE t_votacion_idVotacion = v.idVotacion AND opcionVoto IN ('SI', 'APRUEBO')) as votos_si,
                    (SELECT COUNT(*) FROM t_voto WHERE t_votacion_idVotacion = v.idVotacion AND opcionVoto IN ('NO', 'RECHAZO')) as votos_no,
                    (SELECT COUNT(*) FROM t_voto WHERE t_votacion_idVotacion = v.idVotacion AND opcionVoto IN ('ABSTENCION', 'ABS')) as votos_abs,
                    
                    -- MI VOTO (Aquí recuperamos lo que votó el usuario logueado)
                    mv.opcionVoto as mi_voto_personal

                FROM t_votacion v
                
                -- Puentes para nombres
                LEFT JOIN t_minuta m ON v.t_minuta_idMinuta = m.idMinuta
                LEFT JOIN t_reunion r ON m.idMinuta = r.t_minuta_idMinuta
                LEFT JOIN t_comision c ON v.idComision = c.idComision
                
                -- JOIN CRÍTICO: Buscar el voto SOLO de este usuario (:idUser)
                LEFT JOIN t_voto mv ON v.idVotacion = mv.t_votacion_idVotacion AND mv.t_usuario_idUsuario = :idUser

                WHERE v.habilitada = 0 "; 

        // ¡IMPORTANTE! Asignar el parámetro del usuario
        $params[':idUser'] = $idUsuario;

        // --- FILTROS DE FECHA Y COMISIÓN ---
        if (!empty($filtros['desde'])) {
            $sql .= " AND DATE(v.fechaCreacion) >= :desde ";
            $params[':desde'] = $filtros['desde'];
        }
        if (!empty($filtros['hasta'])) {
            $sql .= " AND DATE(v.fechaCreacion) <= :hasta ";
            $params[':hasta'] = $filtros['hasta'];
        }
        if (!empty($filtros['comision'])) {
            $sql .= " AND v.idComision = :comision ";
            $params[':comision'] = $filtros['comision'];
        }

        // --- BÚSQUEDA POR PALABRA CLAVE (La que ya funciona) ---
        if (!empty($filtros['q'])) {
            $term = '%' . trim($filtros['q']) . '%';
            // Usamos parámetros nombrados distintos para evitar conflictos
            $sql .= " AND (v.nombreVotacion LIKE :q1 OR r.nombreReunion LIKE :q2) ";
            $params[':q1'] = $term;
            $params[':q2'] = $term;
        }

        $sql .= " ORDER BY v.fechaCreacion DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->conn->prepare($sql);
        
        // Bind de todos los parámetros (incluyendo :idUser)
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function countHistorialGlobalFiltrado($filtros)
    {
        $params = [];
        
        // Mismos Joins para que el conteo coincida con los resultados
        $sql = "SELECT COUNT(*) 
                FROM t_votacion v
                LEFT JOIN t_minuta m ON v.t_minuta_idMinuta = m.idMinuta
                LEFT JOIN t_reunion r ON m.idMinuta = r.t_minuta_idMinuta
                LEFT JOIN t_comision c ON v.idComision = c.idComision
                WHERE v.habilitada = 0";

        if (!empty($filtros['desde'])) { $sql .= " AND DATE(v.fechaCreacion) >= :desde "; $params[':desde'] = $filtros['desde']; }
        if (!empty($filtros['hasta'])) { $sql .= " AND DATE(v.fechaCreacion) <= :hasta "; $params[':hasta'] = $filtros['hasta']; }
        if (!empty($filtros['comision'])) { $sql .= " AND v.idComision = :comision "; $params[':comision'] = $filtros['comision']; }
        
        // Búsqueda simplificada
        if (!empty($filtros['q'])) {
            $term = '%' . trim($filtros['q']) . '%';
            $sql .= " AND (v.nombreVotacion LIKE :q1 OR r.nombreReunion LIKE :q2) ";
            $params[':q1'] = $term;
            $params[':q2'] = $term;
        }

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

}
