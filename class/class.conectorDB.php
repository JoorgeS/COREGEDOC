<?php
date_default_timezone_set('America/Santiago');

require_once(__DIR__ . "/../cfg/config.php");

class conectorDB extends BaseConexion
{
    private $conexion;

    public function __construct()
    {
        $this->conexion = parent::conectar();
    }

    /**
     * Retorna el objeto PDO de conexión.
     */
    public function getDatabase()
    {
        return $this->conexion;
    }

    /**
     * Crear usuario
     */
    public function crear($consulta, $datos)
    {
        $resultado = true;

        if ($statement = $this->conexion->prepare($consulta)) {
            try {
                $statement->execute([
                    ":pNombre"        => $datos['pNombre'],
                    ":sNombre"        => $datos['sNombre'],
                    ":aPaterno"       => $datos['aPaterno'],
                    ":aMaterno"       => $datos['aMaterno'],
                    ":correo"         => $datos['correo'],
                    ":contrasena"     => $datos['contrasena'],
                    ":perfil_id"      => $datos['perfil_id'],
                    ":tipoUsuario_id" => $datos['tipoUsuario_id'],
                    ":partido_id"     => $datos['partido_id'],
                    ":comuna_id"      => $datos['comuna_id']
                ]);
            } catch (PDOException $e) {
                echo "Error al crear usuario: " . $e->getMessage();
                return false;
            }
        }

        return $resultado;
    }

    /**
     * Editar usuario (respeta tu lógica original: a veces con contraseña, a veces sin)
     */
    public function editarUsuario($consulta, $datos)
    {
        $resultado = true;

        if ($statement = $this->conexion->prepare($consulta)) {
            try {
                if (array_key_exists("contrasena", $datos)) {
                    $statement->execute([
                        ":pNombre"        => $datos['pNombre'],
                        ":sNombre"        => $datos['sNombre'],
                        ":aPaterno"       => $datos['aPaterno'],
                        ":aMaterno"       => $datos['aMaterno'],
                        ":correo"         => $datos['correo'],
                        ":contrasena"     => $datos['contrasena'],
                        ":perfil_id"      => $datos['perfil_id'],
                        ":tipoUsuario_id" => $datos['tipoUsuario_id'],
                        ":partido_id"     => $datos['partido_id'],
                        ":comuna_id"      => $datos['comuna_id'],
                        ":idUsuario"      => $datos['idUsuario'],
                    ]);
                } else {
                    $statement->execute([
                        ":pNombre"        => $datos['pNombre'],
                        ":sNombre"        => $datos['sNombre'],
                        ":aPaterno"       => $datos['aPaterno'],
                        ":aMaterno"       => $datos['aMaterno'],
                        ":correo"         => $datos['correo'],
                        ":perfil_id"      => $datos['perfil_id'],
                        ":tipoUsuario_id" => $datos['tipoUsuario_id'],
                        ":partido_id"     => $datos['partido_id'],
                        ":comuna_id"      => $datos['comuna_id'],
                        ":idUsuario"      => $datos['idUsuario'],
                    ]);
                }
            } catch (PDOException $e) {
                echo "Error al editar usuario: " . $e->getMessage();
                return false;
            }
        }

        return $resultado;
    }

    /**
     * Buscar usuario por ID
     */
    public function buscarUsuario($consulta, $id)
    {
        if ($statement = $this->conexion->prepare($consulta)) {
            try {
                $statement->bindParam(":idUsuario", $id, PDO::PARAM_INT);
                $statement->execute();

                $resultado = $statement->fetch(PDO::FETCH_ASSOC);
                return $resultado ?: null;
            } catch (PDOException $e) {
                echo "Error al buscar usuario: " . $e->getMessage();
            }
        }

        return null;
    }

    /**
     * Método genérico SELECT / INSERT / UPDATE / DELETE
     * - SELECT => array de resultados
     * - Mutación => true/false
     * - Error => false
     */
    public function consultarBD($consulta, $valores = array())
    {
        $resultado = false;

        if ($statement = $this->conexion->prepare($consulta)) {

            // Bind automático de parámetros nombrados
            if (preg_match_all("/(:\w+)/", $consulta, $campo, PREG_PATTERN_ORDER)) {
                $campo = array_pop($campo);

                foreach ($campo as $parametro) {
                    $paramName = substr($parametro, 1);

                    if (array_key_exists($paramName, $valores)) {
                        $value = $valores[$paramName];
                        $type  = PDO::PARAM_STR;

                        if (is_null($value)) {
                            $type = PDO::PARAM_NULL;
                        } elseif (is_int($value)) {
                            $type = PDO::PARAM_INT;
                        }

                        $statement->bindValue($parametro, $value, $type);
                    }
                }
            }

            try {
                if (!$statement->execute()) {
                    $resultado = false;
                } else {
                    if (stripos(trim($consulta), 'SELECT') === 0) {
                        $resultado = $statement->fetchAll(PDO::FETCH_ASSOC);
                    } else {
                        $resultado = $statement->rowCount() > 0;
                    }
                }
            } catch (PDOException $e) {
                return false;
            }
        }

        return $resultado;
    }

    /**
     * guardarTokenRestablecimiento
     * - Busca el usuario por correo.
     * - Si existe, guarda reset_token y reset_expira para recuperación de contraseña.
     * - Devuelve ['idUsuario' => ..., 'correo' => ...] o null.
     */
    public function guardarTokenRestablecimiento($correo_input, $token, $expira)
    {
        $correoNormalizado = mb_strtolower(trim($correo_input));

        // Buscar usuario por correo
        $sql_select = "
            SELECT idUsuario, correo
            FROM t_usuario
            WHERE LOWER(correo) = :correo
            LIMIT 1
        ";
        $stmt_select = $this->conexion->prepare($sql_select);
        $stmt_select->execute(['correo' => $correoNormalizado]);
        $user = $stmt_select->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return null;
        }

        // Guardar token y expiración
        $sql_update = "
            UPDATE t_usuario
            SET reset_token  = :token,
                reset_expira = :expira
            WHERE idUsuario  = :id
            LIMIT 1
        ";
        $stmt_update = $this->conexion->prepare($sql_update);
        $stmt_update->execute([
            'token'  => $token,
            'expira' => $expira,
            'id'     => $user['idUsuario']
        ]);

        return [
            'idUsuario' => $user['idUsuario'],
            'correo'    => $user['correo']
        ];
    }
}
