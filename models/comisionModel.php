<?php
require_once __DIR__ . '/../class/class.conectorDB.php';

class ComisionModel
{
    private $db;
    private $pdo;

    public function __construct()
    {
        $this->db  = new conectorDB();
        $this->pdo = $this->db->getDatabase();
    }

    /**
     * Obtener todas las comisiones (con presidente y vicepresidente)
     */
    public function getAllComisiones($incluirInactivas = true)
    {
        $sql = "
            SELECT 
                c.idComision,
                c.nombreComision,
                c.vigencia,
                c.t_usuario_idPresidente,
                c.t_usuario_idVicepresidente,

                CONCAT(up.pNombre, ' ', up.aPaterno) AS presidenteNombre,
                CONCAT(uv.pNombre, ' ', uv.aPaterno) AS vicepresidenteNombre
            FROM t_comision c
            LEFT JOIN t_usuario up ON up.idUsuario = c.t_usuario_idPresidente
            LEFT JOIN t_usuario uv ON uv.idUsuario = c.t_usuario_idVicepresidente
        ";

        if (!$incluirInactivas) {
            $sql .= " WHERE c.vigencia = 1 ";
        }

        $sql .= " ORDER BY c.nombreComision ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener comisión por ID (con presidente y vicepresidente)
     */
    public function getComisionById($idComision)
    {
        $sql = "
            SELECT 
                c.idComision,
                c.nombreComision,
                c.vigencia,
                c.t_usuario_idPresidente,
                c.t_usuario_idVicepresidente,
                CONCAT(up.pNombre, ' ', up.aPaterno) AS presidenteNombre,
                CONCAT(uv.pNombre, ' ', uv.aPaterno) AS vicepresidenteNombre
            FROM t_comision c
            LEFT JOIN t_usuario up ON up.idUsuario = c.t_usuario_idPresidente
            LEFT JOIN t_usuario uv ON uv.idUsuario = c.t_usuario_idVicepresidente
            WHERE c.idComision = :id
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $idComision]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Crear nueva comisión (con presidente y vicepresidente)
     */
    public function createComision($nombre, $vigencia, $presidenteId, $vicepresidenteId = null)
    {
        $sql = "
            INSERT INTO t_comision (nombreComision, vigencia, t_usuario_idPresidente, t_usuario_idVicepresidente)
            VALUES (:nombre, :vigencia, :presidente, :vicepresidente)
        ";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':nombre'         => $nombre,
            ':vigencia'       => $vigencia,
            ':presidente'     => $presidenteId,
            ':vicepresidente' => $vicepresidenteId
        ]);
    }

    /**
     * Actualizar comisión
     */
    public function updateComision($id, $nombre, $vigencia, $presidenteId, $vicepresidenteId = null)
    {
        $sql = "
            UPDATE t_comision
            SET nombreComision = :nombre,
                vigencia = :vigencia,
                t_usuario_idPresidente = :presidente,
                t_usuario_idVicepresidente = :vicepresidente
            WHERE idComision = :id
        ";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':nombre'         => $nombre,
            ':vigencia'       => $vigencia,
            ':presidente'     => $presidenteId,
            ':vicepresidente' => $vicepresidenteId,
            ':id'             => $id,
        ]);
    }

    /**
     * Deshabilitar comisión (vigencia = 0)
     */
    public function deleteComision($id)
    {
        $sql = "
            UPDATE t_comision
            SET vigencia = 0
            WHERE idComision = :id
        ";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Posibles presidentes (y también aplicable para vicepresidentes)
     */
    public function getUsuariosPosiblesPresidentes()
    {
        $sql = "
            SELECT 
                idUsuario,
                CONCAT(pNombre, ' ', aPaterno) AS nombreCompleto
            FROM t_usuario
            WHERE tipoUsuario_id IN (1,3)
            ORDER BY pNombre ASC, aPaterno ASC
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
