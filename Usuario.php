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
                WHERE u.estado = 1
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
                    u.estado = 1
                    AND (
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
    $idUsuario = (int)$idUsuario;
    if ($idUsuario <= 0) {
        return false;
    }

    // Usamos la conexión PDO real
    $pdo = $this->db->getDatabase();
    if (!$pdo) {
        error_log("Error al obtener conexión en Usuario::eliminarUsuario");
        return false;
    }

    $sql = "UPDATE t_usuario 
            SET estado = 0 
            WHERE idUsuario = :id";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':id', $idUsuario, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        error_log("Error SQL (eliminarUsuario - borrado lógico): " . $e->getMessage());
        return false;
    }
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

    /**
     * Actualiza la contraseña de un usuario específico.
     * (Esta función es necesaria para la página de perfil)
     */
    /**
     * Actualiza la contraseña de un usuario específico.
     * (Esta función es necesaria para la página de perfil)
     * * --- VERSIÓN CORREGIDA ---
     */
    /**
     * Actualiza la contraseña de un usuario específico.
     * (Esta función es necesaria para la página de perfil)
     * * --- VERSIÓN CORREGIDA FINAL ---
     */
    public function actualizarContrasena($idUsuario, $nuevaContrasena)
    {
        if (empty($idUsuario) || empty($nuevaContrasena)) {
            return false;
        }

        // Hashear la nueva contraseña
        $hashContrasena = password_hash($nuevaContrasena, PASSWORD_DEFAULT);

        // --- INICIO DE LA CORRECCIÓN ---
        // 1. Obtenemos la conexión PDO real, que está en la variable pública 'conexion' del objeto conectorDB
        // (según el constructor que me mostraste: $this->conexion = parent::conectar();)
        
        // Asumo que 'conexion' es pública. Si no lo es, necesitamos un getter como 'getDatabase()'
        // Vamos a probar con getDatabase() primero, que es más limpio.
        $pdo = $this->db->getDatabase(); 
        
        if (!$pdo) {
            error_log("Error al conectar a la BD en Usuario::actualizarContrasena");
            return false;
        }
        // --- FIN DE LA CORRECCIÓN ---

        $sql = "UPDATE t_usuario 
                SET contrasena = :contrasena 
                WHERE idUsuario = :idUsuario";
        
        try {
            // 2. Usamos la variable $pdo (la conexión real) para llamar a prepare()
            $stmt = $pdo->prepare($sql); 
            $stmt->execute([
                'contrasena' => $hashContrasena,
                'idUsuario'  => (int)$idUsuario
            ]);
            
            // Verificar si la fila fue afectada
            return $stmt->rowCount() > 0;

        } catch (Exception $e) {
            // Manejar el error
            error_log("Error SQL (actualizarContrasena): " . $e->getMessage());
            return false;
        }
    }

    public function validarContrasenaActual($idUsuario, $passwordActual)
    {
        if (empty($idUsuario) || empty($passwordActual)) {
            return false;
        }

        // 1. Obtenemos la conexión PDO real
        $pdo = $this->db->getDatabase(); 
        
        if (!$pdo) {
            error_log("Error al conectar a la BD en Usuario::validarContrasenaActual");
            return false;
        }

        // 2. Buscamos el hash de la contraseña actual
        $sql = "SELECT contrasena FROM t_usuario WHERE idUsuario = :idUsuario LIMIT 1";
        
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['idUsuario' => (int)$idUsuario]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && isset($user['contrasena'])) {
                // 3. Verificamos la contraseña ingresada contra el hash guardado
                return password_verify($passwordActual, $user['contrasena']);
            }

            return false; // Usuario no encontrado

        } catch (Exception $e) {
            error_log("Error SQL (validarContrasenaActual): " . $e->getMessage());
            return false;
        }
    }

/**
     * Sube y actualiza la foto de perfil de un usuario.
     * --- VERSIÓN CORREGIDA (Usa DOCUMENT_ROOT) ---
     */
    /**
     * Sube y actualiza la foto de perfil de un usuario.
     * --- VERSIÓN CORREGIDA FINAL (Guarda la RUTA WEB en la BD) ---
     */
    public function actualizarFotoPerfil($idUsuario, $fileData)
    {
        // --- 1. Validaciones ---
        if (!isset($fileData) || $fileData['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'Error: No se recibió ningún archivo.'];
        }
        $check = @getimagesize($fileData['tmp_name']);
        if ($check === false) {
            return ['success' => false, 'message' => 'Error: El archivo no es una imagen.'];
        }
        if ($fileData['size'] > 2 * 1024 * 1024) { // 2 MB
            return ['success' => false, 'message' => 'Error: La imagen es demasiado pesada (máx 2MB).'];
        }
        
        // --- 2. Definir rutas ---
        $extension = strtolower(pathinfo($fileData['name'], PATHINFO_EXTENSION));
        $fileName = 'user_' . $idUsuario . '_' . time() . '.' . $extension;
        
        // RUTA WEB (la que se debe guardar en la BD)
        // ej: /corevota/public/img/perfiles/user_41_12345.jpg
        $webPath = '/corevota/public/img/perfiles/' . $fileName;

        // RUTA FÍSICA (donde se moverá el archivo)
        // __DIR__ es la carpeta de Usuario.php (ej: C:/xampp/htdocs/corevota)
        $serverSavePath = __DIR__ . '/public/img/perfiles/' . $fileName;
        
        // --- 3. Obtener foto antigua para borrarla ---
        $usuarioActual = $this->obtenerUsuario($idUsuario);
        $oldDbPath = $usuarioActual['foto_perfil'] ?? null; // Puede ser la ruta C: (error) o la ruta /corevota/ (correcto)

        // --- 4. Mover el nuevo archivo ---
        if (move_uploaded_file($fileData['tmp_name'], $serverSavePath)) {
            
            // --- 5. Actualizar la BD con la RUTA WEB ---
            $pdo = $this->db->getDatabase();
            $sql = "UPDATE t_usuario SET foto_perfil = :foto WHERE idUsuario = :id";
            
            try {
                $stmt = $pdo->prepare($sql);
                
                // *** ¡LA CORRECCIÓN ESTÁ AQUÍ! ***
                // Guardamos la RUTA WEB ($webPath) en la BD, NO la ruta del servidor ($serverSavePath).
                $stmt->execute(['foto' => $webPath, 'id' => $idUsuario]);

                // --- 6. Borrar la foto antigua (lógica mejorada) ---
                if ($oldDbPath) {
                    $oldServerPath = null;
                    
                    // Comprobar si la ruta antigua es una ruta de servidor (el error anterior)
                    // (strpos($oldDbPath, 'C:') === 0) -> Chequea si empieza con C:
                    if (strpos($oldDbPath, 'C:') === 0 || strpos($oldDbPath, '/xampp/') !== false) {
                        $oldServerPath = $oldDbPath; // Ya es una ruta de servidor
                    } else {
                        // Es una ruta web (ej: /corevota/public/...)
                        // La convertimos a ruta de servidor
                        $oldServerPath = $_SERVER['DOCUMENT_ROOT'] . $oldDbPath;
                    }

                    if (file_exists($oldServerPath)) {
                        @unlink($oldServerPath);
                    }
                }
                
                return [
                    'success' => true,
                    'message' => 'Foto de perfil actualizada con éxito.'
                ];

            } catch (Exception $e) {
                error_log("Error SQL (actualizarFotoPerfil): " . $e->getMessage());
                return ['success' => false, 'message' => 'Error al guardar la foto en la base de datos.'];
            }

        } else {
            return ['success' => false, 'message' => 'Error: No se pudo mover el archivo. (Ruta de guardado: ' . $serverSavePath . ')'];
        }
    }
}

