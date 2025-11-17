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
        // Usamos el nombre 't_comision_idComision' que espera tu BBDD
        $idComision = intval($data['t_comision_idComision'] ?? $data['idComision'] ?? 0);
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
    // 2️⃣ LISTAR VOTACIONES (CON FILTROS Y CONTEO)
    // ======================================================
    public function listar($filtros = []) // <-- 🚀 MODIFICADO
    {
        try {
            // --- 🚀 INICIO LÓGICA DE FILTROS ---
            $params = [];
            $whereClauses = [];

            // Filtro por Comisión
            if (!empty($filtros['comision_id'])) {
                $whereClauses[] = "v.idComision = :comId";
                $params[':comId'] = (int)$filtros['comision_id'];
            }

            // Filtro por Mes y Año (usando fechaCreacion)
            if (!empty($filtros['mes']) && !empty($filtros['anio'])) {
                $mesInt = (int)$filtros['mes'];
                $anioInt = (int)$filtros['anio'];
                $inicioMes = sprintf('%04d-%02d-01 00:00:00', $anioInt, $mesInt);
                $finMes = date('Y-m-t 23:59:59', strtotime($inicioMes));
                
                $whereClauses[] = "v.fechaCreacion BETWEEN :inicio AND :fin";
                $params[':inicio'] = $inicioMes;
                $params[':fin'] = $finMes;
            }
            
            $whereSql = "";
            if (!empty($whereClauses)) {
                $whereSql = " WHERE " . implode(' AND ', $whereClauses);
            }
            // --- 🚀 FIN LÓGICA DE FILTROS ---


            // 🚀 Consulta SQL actualizada para incluir WHERE
            $sql = "
                SELECT 
                    v.idVotacion, v.nombreVotacion, v.habilitada, 
                    v.t_minuta_idMinuta,
                    c.nombreComision, v.fechaCreacion,
                    COUNT(voto.idVoto) AS totalVotos,
                    SUM(CASE WHEN voto.opcionVoto = 'SI' THEN 1 ELSE 0 END) AS totalSi,
                    SUM(CASE WHEN voto.opcionVoto = 'NO' THEN 1 ELSE 0 END) AS totalNo,
                    SUM(CASE WHEN voto.opcionVoto = 'ABSTENCION' THEN 1 ELSE 0 END) AS totalAbstencion
                FROM t_votacion v
                INNER JOIN t_comision c ON v.idComision = c.idComision
                LEFT JOIN t_voto voto ON v.idVotacion = voto.t_votacion_idVotacion
                {$whereSql} 
                GROUP BY v.idVotacion, v.nombreVotacion, v.habilitada, c.nombreComision, v.fechaCreacion
                ORDER BY v.idVotacion DESC
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params); // <-- 🚀 MODIFICADO
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return ['status' => 'success', 'data' => $result];
        } catch (Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage(), 'data' => []];
        }
    }

    // ======================================================
    // 3️⃣ CAMBIAR ESTADO HABILITADA/DESHABILITADA
    // ======================================================
// CAMBIAR ESTADO HABILITADA/DESHABILITADA
    public function cambiarEstado($idVotacion, $nuevoEstado)
    {
        try {
            // Corregido: Usando el nombre de tabla 't_votacion' de tu imagen
            $sql = "UPDATE t_votacion SET habilitada = :estado WHERE idVotacion = :id";

            // Corregido: Usando la variable $sql
            $stmt = $this->pdo->prepare($sql);
            
            $stmt->execute([':estado' => $nuevoEstado, ':id' => $idVotacion]);
            
            return ['status' => 'success', 'message' => 'Estado actualizado correctamente.'];
        } catch (Exception $e) {
            return ['status' => 'error', 'message' => 'Error al cambiar el estado: ' . $e->getMessage()];
        }
    }
}

?>