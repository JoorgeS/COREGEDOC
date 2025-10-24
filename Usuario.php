<?php
// RUTA CRÍTICA: Desde la raíz, buscamos la clase en la carpeta 'class/'
require_once(__DIR__ . '/class/class.conectorDB.php');

class Usuario
{
    private $db;

    public function __construct()
    {
        $this->db = new conectorDB();
    }

    // CREATE (Registrar Usuario)
    public function crearUsuario($datos)
    {
        $consulta = "INSERT INTO t_usuario (
                        pNombre, sNombre, aPaterno, aMaterno, correo, contrasena,
                        perfil_id, tipoUsuario_id, partido_id, comuna_id
                    ) VALUES (
                        :pNombre, :sNombre, :aPaterno, :aMaterno, :correo, :contrasena,
                        :perfil_id, :tipoUsuario_id, :partido_id, :comuna_id
                    )";
        try {
            return $this->db->crear($consulta, $datos);
        } catch (PDOException $e) {
            echo "error al insertar: " . $e->getMessage();
            return $e;
        }
    }

    // READ (Listar TODOS los usuarios con Joins)
    // Orden alfabético A→Z por primer nombre y luego apellido paterno
    public function listarUsuarios()
    {
        $consulta = "SELECT 
                        u.idUsuario,
                        u.pNombre, u.sNombre, u.aPaterno, u.aMaterno,
                        u.correo, 
                        p.descPerfil AS perfil_desc, u.perfil_id,
                        tu.descTipoUsuario AS tipoUsuario_desc, u.tipoUsuario_id,
                        pa.nombrePartido AS partido_desc, u.partido_id,
                        c.nombreComuna AS comuna_desc, u.comuna_id
                    FROM t_usuario u
                    INNER JOIN t_perfil p ON u.perfil_id = p.idPerfil
                    INNER JOIN t_tipousuario tu ON u.tipoUsuario_id = tu.idTipoUsuario
                    LEFT JOIN t_partido pa ON u.partido_id = pa.idPartido
                    LEFT JOIN t_comuna c ON u.comuna_id = c.idComuna
                    ORDER BY 
                        u.pNombre ASC,
                        u.aPaterno ASC";

        return $this->db->consultarBD($consulta);
    }

    // READ filtrado por nombre/apellido/correo (para el buscador)
    // IMPORTANTE: acá ya NO usamos parámetros con :busqueda
    // porque tu consultarBD() probablemente no los está bindeando.
    public function listarUsuariosPorNombre($busqueda)
    {
        // Sanitizamos lo mínimo: escapamos comillas simples para que no rompan el SQL.
        // Esto NO es tan seguro como un prepared statement real, pero evita que la query falle.
        $busqueda = trim($busqueda);
        $busqueda = '%' . $busqueda . '%';
        $busquedaEsc = str_replace("'", "''", $busqueda);

        $consulta = "SELECT 
                        u.idUsuario,
                        u.pNombre, u.sNombre, u.aPaterno, u.aMaterno,
                        u.correo, 
                        p.descPerfil AS perfil_desc, u.perfil_id,
                        tu.descTipoUsuario AS tipoUsuario_desc, u.tipoUsuario_id,
                        pa.nombrePartido AS partido_desc, u.partido_id,
                        c.nombreComuna AS comuna_desc, u.comuna_id
                    FROM t_usuario u
                    INNER JOIN t_perfil p ON u.perfil_id = p.idPerfil
                    INNER JOIN t_tipousuario tu ON u.tipoUsuario_id = tu.idTipoUsuario
                    LEFT JOIN t_partido pa ON u.partido_id = pa.idPartido
                    LEFT JOIN t_comuna c ON u.comuna_id = c.idComuna
                    WHERE 
                        (
                            CONCAT(u.pNombre, ' ', u.aPaterno) LIKE '{$busquedaEsc}'
                            OR u.pNombre LIKE '{$busquedaEsc}'
                            OR u.sNombre LIKE '{$busquedaEsc}'
                            OR u.aPaterno LIKE '{$busquedaEsc}'
                            OR u.aMaterno LIKE '{$busquedaEsc}'
                            OR u.correo LIKE '{$busquedaEsc}'
                        )
                    ORDER BY 
                        u.pNombre ASC,
                        u.aPaterno ASC";

        return $this->db->consultarBD($consulta);
    }

    // READ (Obtener un solo Usuario por ID)
    public function obtenerUsuario($idUsuario)
    {
        $consulta = "SELECT 
                        idUsuario,
                        pNombre, sNombre, aPaterno, aMaterno,
                        correo, contrasena,
                        perfil_id, tipoUsuario_id,
                        partido_id, comuna_id 
                    FROM t_usuario 
                    WHERE idUsuario = :idUsuario";

        $resultado = $this->db->buscarUsuario($consulta, $idUsuario);

        return $resultado;
    }

    // UPDATE (Modificar Usuario)
    // si viene 'contrasena' en $datos => actualiza también la clave
    public function modificarUsuario($datos)
    {
        if (array_key_exists("contrasena", $datos)) {
            $consulta = "UPDATE t_usuario 
                        SET 
                            pNombre = :pNombre,
                            sNombre = :sNombre,
                            aPaterno = :aPaterno,
                            aMaterno = :aMaterno,
                            correo = :correo,
                            contrasena = :contrasena,
                            perfil_id = :perfil_id,
                            tipoUsuario_id = :tipoUsuario_id,
                            partido_id = :partido_id,
                            comuna_id = :comuna_id
                        WHERE idUsuario = :idUsuario";
        } else {
            $consulta = "UPDATE t_usuario 
                        SET 
                            pNombre = :pNombre,
                            sNombre = :sNombre,
                            aPaterno = :aPaterno,
                            aMaterno = :aMaterno,
                            correo = :correo,
                            perfil_id = :perfil_id,
                            tipoUsuario_id = :tipoUsuario_id,
                            partido_id = :partido_id,
                            comuna_id = :comuna_id
                        WHERE idUsuario = :idUsuario";
        }

        return $this->db->editarUsuario($consulta, $datos);
    }

    // DELETE (Eliminar Usuario)
    public function eliminarUsuario($idUsuario)
    {
        $consulta = "DELETE FROM t_usuario WHERE idUsuario = :id";
        return $this->db->consultarBD($consulta, ['id' => $idUsuario]);
    }

    // Combos / selects auxiliares
    public function obtenerPerfiles()
    {
        return $this->db->consultarBD("SELECT idPerfil, descPerfil FROM t_perfil");
    }

    public function obtenerTiposUsuario()
    {
        return $this->db->consultarBD("SELECT idTipoUsuario, descTipoUsuario FROM t_tipousuario");
    }

    public function obtenerPartidos()
    {
        return $this->db->consultarBD("SELECT idPartido, nombrePartido FROM t_partido");
    }

    public function obtenerComunas()
    {
        return $this->db->consultarBD("SELECT idComuna, nombreComuna FROM t_comuna");
    }
}
