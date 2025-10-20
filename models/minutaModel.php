<?php
// models/MinutaModel.php

require_once __DIR__ . '/../class/class.conectorDB.php';

class MinutaModel
{
    private $db_connector;

    public function __construct()
    {
        $this->db_connector = new conectorDB();
    }

    /**
     * READ: Obtiene el listado de minutas filtrado por estado.
     * @param string $estado
     * @return array
     */
    public function getMinutasByEstado($estado)
    {
        $sql = "";

        // ❗️❗️ INICIO DE LA CORRECCIÓN ❗️❗️
        // La consulta ahora debe agrupar por minuta, ya que una minuta puede tener VARIOS temas.
        // Usamos GROUP_CONCAT para obtener el nombre del PRIMER tema como referencia.
        // El JOIN ahora es t.t_minuta_idMinuta = m.idMinuta

        if ($estado === 'APROBADA') {
            $sql = "SELECT
                        m.idMinuta AS idTema, /* Usamos idMinuta como ID principal */
                        m.t_usuario_idPresidente,
                        m.estadoMinuta,
                        m.pathArchivo,
                        GROUP_CONCAT(t.nombreTema SEPARATOR '; ') AS nombreTema, /* Muestra nombres de temas */
                        GROUP_CONCAT(t.objetivo SEPARATOR '; ') AS objetivo     /* Muestra objetivos */
                    FROM t_minuta m
                    LEFT JOIN t_tema t ON t.t_minuta_idMinuta = m.idMinuta /* <-- JOIN CORREGIDO */
                    WHERE m.estadoMinuta = :estado
                    GROUP BY m.idMinuta /* <-- Agrupamos por minuta */
                    ORDER BY m.fechaAprobacion DESC";

        } else { // PENDIENTE u otro
            $sql = "SELECT
                        m.idMinuta AS idTema, /* Usamos idMinuta como ID principal */
                        m.t_usuario_idPresidente,
                        m.estadoMinuta,
                        GROUP_CONCAT(t.nombreTema SEPARATOR '; ') AS nombreTema, /* Muestra nombres de temas */
                        GROUP_CONCAT(t.objetivo SEPARATOR '; ') AS objetivo     /* Muestra objetivos */
                    FROM t_minuta m
                    LEFT JOIN t_tema t ON t.t_minuta_idMinuta = m.idMinuta /* <-- JOIN CORREGIDO */
                    WHERE m.estadoMinuta = :estado
                    GROUP BY m.idMinuta /* <-- Agrupamos por minuta */
                    ORDER BY m.fechaMinuta DESC";
        }
        // ❗️❗️ FIN DE LA CORRECCIÓN ❗️❗️


        // --- Ejecutar la consulta ---
        $valores = ['estado' => $estado];
        $result = $this->db_connector->consultarBD($sql, $valores);

        return is_array($result) ? $result : [];
    }

    /**
     * READ: Obtiene TODOS los temas (sin agrupar por minuta).
     * @return array
     */
    public function getAllMinutas() // Debería llamarse getAllTemas realmente
    {
        // Esta función ahora es menos útil, ya que los temas pertenecen a minutas.
        // La dejamos por si la usas en otro lado, pero la consulta principal es getMinutasByEstado.
        $sql = "SELECT
                    idTema,
                    t_minuta_idMinuta, /* Añadido para contexto */
                    nombreTema,
                    objetivo,
                    compromiso,
                    observacion
                FROM
                    t_tema
                ORDER BY
                    t_minuta_idMinuta DESC, idTema DESC";

        $valores = [];
        $result = $this->db_connector->consultarBD($sql, $valores);

        return is_array($result) ? $result : [];
    }

    /**
     * READ: Obtiene un solo tema por su ID.
     * @param int $id
     * @return array|null
     */
    public function getTemaById($id)
    {
        $sql = "SELECT
                    idTema,
                    t_minuta_idMinuta,
                    nombreTema,
                    objetivo,
                    compromiso,
                    observacion
                FROM
                    t_tema
                WHERE
                    idTema = :idTema";

        $valores = ['idTema' => (int)$id];
        $result = $this->db_connector->consultarBD($sql, $valores);

        if (is_array($result) && count($result) > 0) {
            return $result[0];
        }
        return null;
    }

    /**
     * UPDATE: Actualiza un tema existente.
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function updateTema($id, $data)
    {
        // Esta función sigue funcionando igual, actualiza un tema específico.
        $sql = "UPDATE t_tema
                SET nombreTema = :nombreTema,
                    objetivo = :objetivo,
                    compromiso = :compromiso,
                    observacion = :observacion
                WHERE idTema = :idTema";

        $valores = [
            'nombreTema' => $data['nombreTema'],
            'objetivo' => $data['objetivo'],
            'compromiso' => $data['compromiso'],
            'observacion' => $data['observacion'],
            'idTema' => (int)$id
        ];

        return $this->db_connector->consultarBD($sql, $valores);
    }
}