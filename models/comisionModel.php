<?php
// models/comisionModel.php
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
     * Obtiene todas las comisiones.
     * @param bool $incluirInactivas Si es true trae todas, si es false trae solo vigencia = 1 (activa)
     */
    public function getAllComisiones($incluirInactivas = true)
    {
        // armamos base SELECT con JOIN para traer también el presidente
        $sql = "
            SELECT 
                c.idComision,
                c.nombreComision,
                c.vigencia,
                c.t_usuario_idPresidente,
                CONCAT(u.pNombre, ' ', u.aPaterno) AS presidenteNombre
            FROM t_comision c
            LEFT JOIN t_usuario u
                ON u.idUsuario = c.t_usuario_idPresidente
        ";

        // si NO queremos inactivas, filtramos acá
        if (!$incluirInactivas) {
            $sql .= " WHERE c.vigencia = 1 ";
        }

        $sql .= " ORDER BY c.nombreComision ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Traer una comisión por ID (para editar)
     */
    public function getComisionById($idComision)
    {
        $sql = "
            SELECT 
                c.idComision,
                c.nombreComision,
                c.vigencia,
                c.t_usuario_idPresidente,
                CONCAT(u.pNombre, ' ', u.aPaterno) AS presidenteNombre
            FROM t_comision c
            LEFT JOIN t_usuario u
                ON u.idUsuario = c.t_usuario_idPresidente
            WHERE c.idComision = :id
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $idComision]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Crear comisión nueva
     * $presidenteId puede ser null
     */
    public function createComision($nombre, $vigencia, $presidenteId)
    {
        $sql = "
            INSERT INTO t_comision (nombreComision, vigencia, t_usuario_idPresidente)
            VALUES (:nombre, :vigencia, :presidente)
        ";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':nombre'     => $nombre,
            ':vigencia'   => $vigencia,
            ':presidente' => $presidenteId, // puede ser null
        ]);
    }

    /**
     * Actualizar comisión
     */
    public function updateComision($id, $nombre, $vigencia, $presidenteId)
    {
        $sql = "
            UPDATE t_comision
            SET nombreComision = :nombre,
                vigencia = :vigencia,
                t_usuario_idPresidente = :presidente
            WHERE idComision = :id
        ";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':nombre'     => $nombre,
            ':vigencia'   => $vigencia,
            ':presidente' => $presidenteId,
            ':id'         => $id,
        ]);
    }

    /**
     * "Eliminar" = deshabilitar comisión (vigencia = 0)
     * OJO: Tu controlador ya da a entender eso.
     */
    public function deleteComision($id)
    {
        $sql = "
            UPDATE t_comision
            SET vigencia = 0
            WHERE idComision = :id
        ";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':id' => $id,
        ]);
    }

    /**
     * (Opcional) Si en el formulario necesitas popular el <select> de posibles presidentes,
     * puedes usar esto:
     */
    public function getUsuariosPosiblesPresidentes()
    {
        // Ajusta según tu lógica: por ejemplo solo usuarios con cierto perfil.
        $sql = "
            SELECT 
                idUsuario,
                CONCAT(pNombre, ' ', aPaterno) AS nombreCompleto
            FROM t_usuario
            ORDER BY pNombre ASC, aPaterno ASC
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
