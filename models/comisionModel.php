<?php
// models/ComisionModel.php
require_once __DIR__ . '/../class/class.conectorDB.php';

class ComisionModel
{
    private $db_connector;

    public function __construct()
    {
        $this->db_connector = new conectorDB();
    }

    public function getAllComisiones($incluir_inactivas = false)
    {
        $sql = "SELECT idComision, nombreComision, vigencia, t_usuario_idPresidente /* Añadir si quieres mostrarlo en lista */
                FROM t_comision";
        $valores = [];
        if (!$incluir_inactivas) {
            $sql .= " WHERE vigencia = :vigencia_activa";
            $valores['vigencia_activa'] = 1;
        }
        $sql .= " ORDER BY nombreComision ASC";
        $result = $this->db_connector->consultarBD($sql, $valores);
        return is_array($result) ? $result : [];
    }

    /**
     * CREATE: Inserta una nueva comisión.
     * @param string $nombre
     * @param int $vigencia
     * @param int|null $presidenteId ID del usuario presidente o null
     * @return bool
     */
    public function createComision($nombre, $vigencia, $presidenteId)
    {
        // ❗️ Query actualizada
        $sql = "INSERT INTO t_comision (nombreComision, vigencia, t_usuario_idPresidente)
                VALUES (:nombreComision, :vigencia, :presidenteId)";
        $valores = [
            'nombreComision' => $nombre,
            'vigencia' => $vigencia,
            'presidenteId' => $presidenteId // PDO maneja null correctamente
        ];
        return $this->db_connector->consultarBD($sql, $valores);
    }

    /**
     * READ: Obtiene una comisión por ID, incluyendo el ID del presidente.
     * @param int $id
     * @return array|null
     */
    public function getComisionById($id)
    {
        // ❗️ Query actualizada
        $sql = "SELECT idComision, nombreComision, vigencia, t_usuario_idPresidente
                FROM t_comision WHERE idComision = :idComision";
        $valores = ['idComision' => (int)$id];
        $result = $this->db_connector->consultarBD($sql, $valores);
        if (is_array($result) && count($result) > 0) {
            return $result[0];
        }
        return null;
    }

    /**
     * UPDATE: Actualiza una comisión existente.
     * @param int $id
     * @param string $nombre
     * @param int $vigencia
     * @param int|null $presidenteId
     * @return bool
     */
    public function updateComision($id, $nombre, $vigencia, $presidenteId)
    {
        // ❗️ Query actualizada
        $sql = "UPDATE t_comision
                SET nombreComision = :nombreComision,
                    vigencia = :vigencia,
                    t_usuario_idPresidente = :presidenteId
                WHERE idComision = :idComision";
        $valores = [
            'nombreComision' => $nombre,
            'vigencia' => $vigencia,
            'presidenteId' => $presidenteId,
            'idComision' => $id
        ];
        return $this->db_connector->consultarBD($sql, $valores);
    }

    public function deleteComision($id)
    { // Solo cambia vigencia
        $sql = "UPDATE t_comision SET vigencia = :vigencia WHERE idComision = :idComision";
        $valores = ['vigencia' => 0, 'idComision' => $id];
        return $this->db_connector->consultarBD($sql, $valores);
    }
}
