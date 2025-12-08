<?php

namespace App\Models;

use App\Config\Database;
use PDO;

class Reunion
{
    private $conn;
    private $table = 't_reunion';

    public function __construct()
    {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public function listar()
    {
        // Traemos también las comisiones mixtas para mostrarlas si es necesario
        $sql = "SELECT r.*, c.nombreComision, m.estadoMinuta, m.idMinuta as minutaId
                FROM t_reunion r
                LEFT JOIN t_comision c ON r.t_comision_idComision = c.idComision
                LEFT JOIN t_minuta m ON r.t_minuta_idMinuta = m.idMinuta
                WHERE r.vigente = 1
                ORDER BY r.fechaInicioReunion DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPorId($id)
    {
        $sql = "SELECT * FROM t_reunion WHERE idReunion = :id AND vigente = 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // AQUI ESTABA EL ERROR: Faltaba guardar las mixtas
    public function crear($data)
    {
        $sql = "INSERT INTO t_reunion 
                (nombreReunion, t_comision_idComision, t_comision_idComision_mixta, t_comision_idComision_mixta2, fechaInicioReunion, fechaTerminoReunion, vigente) 
                VALUES (:nombre, :com1, :com2, :com3, :inicio, :termino, 1)";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':nombre'  => $data['nombre'],
            ':com1'    => $data['comision'],
            ':com2'    => !empty($data['comision2']) ? $data['comision2'] : null, // Manejo de nulos
            ':com3'    => !empty($data['comision3']) ? $data['comision3'] : null,
            ':inicio'  => $data['inicio'],
            ':termino' => $data['termino']
        ]);
        return $this->conn->lastInsertId();
    }

    public function actualizar($id, $data)
    {
        $sql = "UPDATE t_reunion 
                SET nombreReunion = :nombre, 
                    t_comision_idComision = :com1,
                    t_comision_idComision_mixta = :com2,
                    t_comision_idComision_mixta2 = :com3,
                    fechaInicioReunion = :inicio, 
                    fechaTerminoReunion = :termino 
                WHERE idReunion = :id";

        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':nombre'  => $data['nombre'],
            ':com1'    => $data['comision'],
            ':com2'    => !empty($data['comision2']) ? $data['comision2'] : null,
            ':com3'    => !empty($data['comision3']) ? $data['comision3'] : null,
            ':inicio'  => $data['inicio'],
            ':termino' => $data['termino'],
            ':id'      => $id
        ]);
    }

    public function eliminar($id)
    {
        // Solo permite eliminar si NO tiene minuta asociada (Lógica de negocio)
        $sql = "UPDATE t_reunion SET vigente = 0 WHERE idReunion = :id AND t_minuta_idMinuta IS NULL";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }

    public function vincularMinuta($idReunion, $idMinuta)
    {
        $sql = "UPDATE t_reunion SET t_minuta_idMinuta = :idMinuta WHERE idReunion = :idReunion";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':idMinuta' => $idMinuta, ':idReunion' => $idReunion]);
    }

    public function obtenerDatosParaMinuta($idReunion)
    {
        // Necesitamos datos para crear la minuta, incluyendo el presidente de la comision principal
        $sql = "SELECT r.t_comision_idComision, r.fechaInicioReunion, c.t_usuario_idPresidente
                FROM t_reunion r
                JOIN t_comision c ON r.t_comision_idComision = c.idComision
                WHERE r.idReunion = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $idReunion]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // En tu modelo (ej: ReunionModel.php)

    // 1. Método para obtener los datos paginados
    public function getReunionesFiltradas($fechaDesde, $fechaHasta, $idComision, $busqueda, $limit = 10, $offset = 0)
    {
        $sql = "SELECT r.*, c.nombreComision, m.estadoMinuta, m.idMinuta as t_minuta_idMinuta, 
            (SELECT count(idAdjunto) FROM t_adjunto WHERE t_minuta_idMinuta = m.idMinuta) as numAdjuntos
            FROM t_reunion r
            LEFT JOIN t_comision c ON r.t_comision_idComision = c.idComision
            LEFT JOIN t_minuta m ON r.t_minuta_idMinuta = m.idMinuta
            WHERE r.vigente = 1";

        $params = [];

        if (!empty($fechaDesde) && !empty($fechaHasta)) {
            $sql .= " AND DATE(r.fechaInicioReunion) BETWEEN :desde AND :hasta";
            $params[':desde'] = $fechaDesde;
            $params[':hasta'] = $fechaHasta;
        }
        if (!empty($idComision)) {
            $sql .= " AND r.t_comision_idComision = :comision";
            $params[':comision'] = $idComision;
        }
        if (!empty($busqueda)) {
            $sql .= " AND (r.nombreReunion LIKE :q1 OR c.nombreComision LIKE :q2)";
            $params[':q1'] = "%" . $busqueda . "%";
            $params[':q2'] = "%" . $busqueda . "%";
        }

        $sql .= " ORDER BY r.fechaInicioReunion DESC LIMIT $limit OFFSET $offset";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // 2. Método NUEVO para contar el total (necesario para los botoncitos de paginación)
    public function contarReunionesFiltradas($fechaDesde, $fechaHasta, $idComision, $busqueda)
    {
        $sql = "SELECT COUNT(*) as total 
            FROM t_reunion r
            LEFT JOIN t_comision c ON r.t_comision_idComision = c.idComision
            WHERE r.vigente = 1";

        $params = [];

        if (!empty($fechaDesde) && !empty($fechaHasta)) {
            $sql .= " AND DATE(r.fechaInicioReunion) BETWEEN :desde AND :hasta";
            $params[':desde'] = $fechaDesde;
            $params[':hasta'] = $fechaHasta;
        }
        if (!empty($idComision)) {
            $sql .= " AND r.t_comision_idComision = :comision";
            $params[':comision'] = $idComision;
        }
        if (!empty($busqueda)) {
            $sql .= " AND (r.nombreReunion LIKE :q1 OR c.nombreComision LIKE :q2)";
            $params[':q1'] = "%" . $busqueda . "%";
            $params[':q2'] = "%" . $busqueda . "%";
        }

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res['total'];
    }

    // ============================================================
    // 📋 HELPER PARA LLENAR EL COMBOBOX
    // ============================================================
    public function getAllComisiones()
    {
        // Traemos solo las vigentes para el filtro
        $sql = "SELECT * FROM t_comision WHERE vigencia = 1 ORDER BY nombreComision ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // (Opcional) Métodos CRUD básicos si los necesitas luego...
    public function getById($id)
    {
        $stmt = $this->conn->prepare("SELECT * FROM " . $this->table . " WHERE idReunion = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    // ============================================================
    // 📊 NUEVO: REPORTE DE ASISTENCIA MENSUAL
    // ============================================================
    public function obtenerAsistenciaPorFiltro($mes, $anio, $idComision)
    {
        // NOTA: Ajusté los nombres de las columnas para coincidir con tu estructura:
        // - t_reunion_idReunion (FK en t_asistencia)
        // - t_usuario_idUsuario (FK en t_asistencia)
        // - t_tipoAsistencia_idTipoAsistencia (FK en t_asistencia)
        
        $sql = "SELECT 
                    u.nombres, 
                    u.apellidos, 
                    u.rut,
                    r.fechaInicioReunion as fecha, 
                    r.nombreReunion,
                    ta.nombreTipoAsistencia as estado_asistencia, -- Asegúrate que esta columna se llame así en t_tipoasistencia
                    c.nombreComision as nombre_comision
                FROM t_asistencia a
                INNER JOIN t_usuario u ON a.t_usuario_idUsuario = u.idUsuario
                INNER JOIN t_reunion r ON a.t_reunion_idReunion = r.idReunion
                INNER JOIN t_tipoasistencia ta ON a.t_tipoAsistencia_idTipoAsistencia = ta.idTipoAsistencia
                INNER JOIN t_comision c ON r.t_comision_idComision = c.idComision
                WHERE MONTH(r.fechaInicioReunion) = :mes 
                  AND YEAR(r.fechaInicioReunion) = :anio 
                  AND r.t_comision_idComision = :idComision
                  AND r.vigente = 1
                ORDER BY u.apellidos ASC, r.fechaInicioReunion ASC";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':mes', $mes, PDO::PARAM_INT);
        $stmt->bindParam(':anio', $anio, PDO::PARAM_INT);
        $stmt->bindParam(':idComision', $idComision, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
