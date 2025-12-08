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

    // REGISTRO: Usa las columnas nuevas (t_...) para compatibilidad con Diciembre
    public function registrarVoto($idVotacion, $idUsuario, $opcion)
    {
        // 1. Verificar si ya votó (Revisando ambas columnas por seguridad)
        $check = "SELECT idVoto FROM t_voto 
                  WHERE (idVotacion = :idVotacion OR t_votacion_idVotacion = :idVotacion) 
                  AND (idUsuario = :idUsuario OR t_usuario_idUsuario = :idUsuario)";
        
        $stmtCheck = $this->conn->prepare($check);
        $stmtCheck->execute([':idVotacion' => $idVotacion, ':idUsuario' => $idUsuario]);

        if ($stmtCheck->fetch()) {
            throw new Exception("Ya has emitido tu voto para esta votación.");
        }

        // 2. Insertar en formato NUEVO
        $sql = "INSERT INTO t_voto 
                (t_votacion_idVotacion, t_usuario_idUsuario, idVotacion, idUsuario, opcionVoto, fechaVoto, origenVoto) 
                VALUES 
                (:idVotacion, :idUsuario, 0, 0, :opcion, NOW(), 'SALA_VIRTUAL')";

        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':idVotacion' => $idVotacion,
            ':idUsuario' => $idUsuario,
            ':opcion' => $opcion
        ]);
    }

    // RESULTADOS (Para el panel de control): Suma votos de ambas estructuras
    public function obtenerResultados($idMinuta)
    {
        $sql = "SELECT v.*, c.nombreComision 
                FROM t_votacion v 
                LEFT JOIN t_comision c ON v.idComision = c.idComision
                WHERE v.t_minuta_idMinuta = :idMinuta 
                ORDER BY v.idVotacion DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':idMinuta' => $idMinuta]);
        $votaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $resultados = [];

        foreach ($votaciones as $v) {
            $sqlVotos = "SELECT opcionVoto, COUNT(*) as total 
                         FROM t_voto 
                         WHERE (idVotacion = :id OR t_votacion_idVotacion = :id) 
                         GROUP BY opcionVoto";
            
            $stmt = $this->conn->prepare($sqlVotos);
            $stmt->execute([':id' => $v['idVotacion']]);
            $conteo = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

            // Detalle de usuarios (Join compatible)
            $sqlDetalle = "SELECT u.pNombre, u.aPaterno, t.opcionVoto 
                           FROM t_voto t 
                           JOIN t_usuario u ON (t.idUsuario = u.idUsuario OR t.t_usuario_idUsuario = u.idUsuario)
                           WHERE (t.idVotacion = :id OR t.t_votacion_idVotacion = :id)
                           AND u.idUsuario > 0";
            
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
        $sql = "SELECT v.*, m.t_comision_idComision 
                FROM t_votacion v
                LEFT JOIN t_minuta m ON v.t_minuta_idMinuta = m.idMinuta
                WHERE v.habilitada = 1 
                AND NOT EXISTS (
                    SELECT 1 FROM t_voto vo 
                    WHERE (vo.idVotacion = v.idVotacion OR vo.t_votacion_idVotacion = v.idVotacion)
                    AND (vo.idUsuario = :idUsuario OR vo.t_usuario_idUsuario = :idUsuario)
                )
                ORDER BY v.idVotacion DESC LIMIT 1";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':idUsuario' => $idUsuario]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // --- ESTE ES EL MÉTODO QUE FALTABA ---
    // RESULTADOS HISTÓRICOS (Para la pestaña 'Resultados Globales')
    public function getResultadosHistoricos()
    {
        // Trae las últimas 10 votaciones cerradas con sus totales
        // CORREGIDO: Las subconsultas ahora usan OR para contar votos de Diciembre correctamente
        $sql = "SELECT v.idVotacion, v.nombreVotacion, v.fechaCreacion,
                    (SELECT COUNT(*) FROM t_voto WHERE (idVotacion = v.idVotacion OR t_votacion_idVotacion = v.idVotacion) AND opcionVoto = 'SI') as si,
                    (SELECT COUNT(*) FROM t_voto WHERE (idVotacion = v.idVotacion OR t_votacion_idVotacion = v.idVotacion) AND opcionVoto = 'NO') as no,
                    (SELECT COUNT(*) FROM t_voto WHERE (idVotacion = v.idVotacion OR t_votacion_idVotacion = v.idVotacion) AND opcionVoto = 'ABSTENCION') as abs
                FROM t_votacion v
                WHERE v.habilitada = 0 
                ORDER BY v.fechaCreacion DESC LIMIT 10";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

// --- FILTRAR HISTORIAL DE VOTOS PERSONAL (Con Resultado Calculado) ---
    public function getHistorialVotosPersonalFiltrado($idUsuario, $filtros, $limit, $offset)
    {
        $sqlWhere = " WHERE vo.t_usuario_idUsuario = :idUsuario ";
        $params = [':idUsuario' => $idUsuario];

        // 1. Filtros Dinámicos
        if (!empty($filtros['desde'])) {
            $sqlWhere .= " AND DATE(vo.fechaVoto) >= :desde ";
            $params[':desde'] = $filtros['desde'];
        }
        if (!empty($filtros['hasta'])) {
            $sqlWhere .= " AND DATE(vo.fechaVoto) <= :hasta ";
            $params[':hasta'] = $filtros['hasta'];
        }
        if (!empty($filtros['comision'])) {
            $sqlWhere .= " AND v.idComision = :comision ";
            $params[':comision'] = $filtros['comision'];
        }
        if (!empty($filtros['q'])) {
            $sqlWhere .= " AND (v.nombreVotacion LIKE :q OR r.nombreReunion LIKE :q) ";
            $params[':q'] = '%' . trim($filtros['q']) . '%';
        }

        // --- Query Total ---
        $sqlCount = "SELECT COUNT(*) 
                     FROM t_voto vo
                     JOIN t_votacion v ON vo.t_votacion_idVotacion = v.idVotacion
                     LEFT JOIN t_minuta m ON v.t_minuta_idMinuta = m.idMinuta
                     LEFT JOIN t_reunion r ON r.t_minuta_idMinuta = m.idMinuta
                     $sqlWhere";
        
        $stmtCount = $this->conn->prepare($sqlCount);
        foreach($params as $k => $v) $stmtCount->bindValue($k, $v);
        $stmtCount->execute();
        $total = $stmtCount->fetchColumn();

        // --- Query Data (Con Cálculo de Resultado) ---
        $sqlData = "SELECT 
                        v.nombreVotacion, 
                        vo.opcionVoto, 
                        vo.fechaVoto,
                        COALESCE(r.nombreReunion, 'Sin Reunión Asociada') as nombreReunion,
                        
                        -- CÁLCULO DEL RESULTADO EN TIEMPO REAL
                        CASE 
                            WHEN (SELECT COUNT(*) FROM t_voto WHERE t_votacion_idVotacion = v.idVotacion) = 0 THEN 'SIN DATOS'
                            WHEN (SELECT COUNT(*) FROM t_voto WHERE t_votacion_idVotacion = v.idVotacion AND opcionVoto = 'SI') > 
                                 (SELECT COUNT(*) FROM t_voto WHERE t_votacion_idVotacion = v.idVotacion AND opcionVoto = 'NO') THEN 'APROBADA'
                            WHEN (SELECT COUNT(*) FROM t_voto WHERE t_votacion_idVotacion = v.idVotacion AND opcionVoto = 'NO') > 
                                 (SELECT COUNT(*) FROM t_voto WHERE t_votacion_idVotacion = v.idVotacion AND opcionVoto = 'SI') THEN 'RECHAZADA'
                            ELSE 'EMPATE'
                        END as resultado_final

                    FROM t_voto vo
                    JOIN t_votacion v ON vo.t_votacion_idVotacion = v.idVotacion
                    LEFT JOIN t_minuta m ON v.t_minuta_idMinuta = m.idMinuta
                    LEFT JOIN t_reunion r ON r.t_minuta_idMinuta = m.idMinuta
                    $sqlWhere
                    ORDER BY vo.fechaVoto DESC
                    LIMIT :limit OFFSET :offset";

        $stmt = $this->conn->prepare($sqlData);
        
        foreach($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue(':limit', (int)$limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, \PDO::PARAM_INT);
        
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'data' => $data,
            'total' => $total,
            'totalPages' => ceil($total / $limit)
        ];
    }
}
