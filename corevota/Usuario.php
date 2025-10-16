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
        echo "Usuario insertar: ";

        $consulta = "INSERT INTO t_usuario (pNombre, sNombre, aPaterno, aMaterno, correo, contrasena, perfil_id, tipoUsuario_id, partido_id, comuna_id)
                     VALUES (:pNombre, :sNombre, :aPaterno, :aMaterno, :correo, :contrasena, :perfil_id, :tipoUsuario_id, :partido_id, :comuna_id)";
        try {
            return $this->db->crear($consulta, $datos);
        } catch (PDOException $e) {
            echo "error al insertar: " . $e->getMessage();
            return $e;
        }
    }


    // READ (Listar Usuarios con Joins)
    public function listarUsuarios()
    {
        $consulta = "SELECT 
                        u.idUsuario, u.pNombre, u.sNombre, u.aPaterno, u.aMaterno, u.correo, 
                        p.descPerfil AS perfil_desc, u.perfil_id,
                        tu.descTipoUsuario AS tipoUsuario_desc, u.tipoUsuario_id,
                        pa.nombrePartido AS partido_desc, u.partido_id,
                        c.nombreComuna AS comuna_desc, u.comuna_id
                      FROM t_usuario u
                      INNER JOIN t_perfil p ON u.perfil_id = p.idPerfil
                      INNER JOIN t_tipousuario tu ON u.tipoUsuario_id = tu.idTipoUsuario
                      LEFT JOIN t_partido pa ON u.partido_id = pa.idPartido
                      LEFT JOIN t_comuna c ON u.comuna_id = c.idComuna
                      ORDER BY u.aPaterno";
        return $this->db->consultarBD($consulta);
    }

    // En el archivo Usuario.php

    // READ (Obtener un solo Usuario por ID)
    public function obtenerUsuario($idUsuario)
    {
        $consulta = "SELECT idUsuario, pNombre, sNombre, aPaterno, aMaterno, correo, contrasena, 
                        perfil_id, tipoUsuario_id, partido_id, comuna_id 
                 FROM t_usuario 
                 WHERE idUsuario = :idUsuario";

        $resultado = $this->db->buscarUsuario($consulta, $idUsuario);

        // CLAVE: Si hay resultado, devuelve la PRIMERA FILA [0], si no, devuelve false.
        return $resultado;
    }
    // UPDATE (Modificar Usuario)
    public function modificarUsuario($datos)
    {
        $consulta = "";
        if (array_key_exists("contrasena", $datos)) {
            $consulta = "UPDATE t_usuario 
                     SET 
                         pNombre = :pNombre, sNombre = :sNombre, aPaterno = :aPaterno, 
                         aMaterno = :aMaterno, correo = :correo, contrasena = :contrasena, 
                         perfil_id = :perfil_id, tipoUsuario_id = :tipoUsuario_id, 
                         partido_id = :partido_id, comuna_id = :comuna_id
                     WHERE idUsuario = :idUsuario";
        } else {
            $consulta = "UPDATE t_usuario 
                     SET 
                         pNombre = :pNombre, sNombre = :sNombre, aPaterno = :aPaterno, 
                         aMaterno = :aMaterno, correo = :correo, 
                         perfil_id = :perfil_id, tipoUsuario_id = :tipoUsuario_id, 
                         partido_id = :partido_id, comuna_id = :comuna_id
                     WHERE idUsuario = :idUsuario";
        }

        return $this->db->editarUsuario($consulta, $datos);
    }

    // DELETE (Eliminar Usuario)
    public function eliminarUsuario($idUsuario)
    {
        // 1. La consulta SQL usa el placeholder :id
        $consulta = "DELETE FROM t_usuario WHERE idUsuario = :id";

        // 2. CORRECCIÓN: La clave del array debe ser 'id' (sin los dos puntos)
        // para que sea compatible con la lógica de bindeo de consultarBD().
        return $this->db->consultarBD($consulta, ['id' => $idUsuario]);
    }

    // Métodos auxiliares para SELECT de Foreign Keys
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
