<?php

namespace App\Models;

use Exception;
use PDOException;

use App\Config\Database;
use PDO;

class User
{
    private $conn;
    private $table = 't_usuario';

    public function __construct()
    {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    // ============================================================
    // 🔑 MÉTODO CRÍTICO PARA LOGIN
    // ============================================================
    public function findByEmail($email)
    {
        $query = "SELECT * FROM " . $this->table . " WHERE correo = :email LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':email' => $email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // ============================================================
    // 🛠️ MÉTODOS PARA GESTIÓN DE USUARIOS (CRUD)
    // ============================================================

    // Listar todos (Activos)
    public function getAll()
    {
        $sql = "SELECT u.*, t.descTipoUsuario, p.nombrePartido 
                FROM t_usuario u
                LEFT JOIN t_tipousuario t ON u.tipoUsuario_id = t.idTipoUsuario
                LEFT JOIN t_partido p ON u.partido_id = p.idPartido
                WHERE u.estado = 1
                ORDER BY u.aPaterno ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // --- NUEVO: Filtrar Usuarios con Paginación ---
    // --- NUEVA FUNCIÓN PARA FILTROS Y PAGINACIÓN ---
    public function filtrarUsuarios($limit, $offset, $filters = [])
    {
        $sqlBase = " FROM t_usuario u
                     LEFT JOIN t_tipousuario t ON u.tipoUsuario_id = t.idTipoUsuario
                     LEFT JOIN t_partido p ON u.partido_id = p.idPartido ";

        $whereClauses = ["u.estado = 1"]; // Solo usuarios activos
        $params = [];

        // 1. Filtro por Palabra Clave (CORREGIDO HY093)
        if (!empty($filters['keyword'])) {
            // Usamos marcadores distintos para cada campo
            $whereClauses[] = "(u.pNombre LIKE :kw1 OR u.aPaterno LIKE :kw2 OR u.correo LIKE :kw3)";
            $term = "%" . $filters['keyword'] . "%";
            $params[':kw1'] = $term;
            $params[':kw2'] = $term;
            $params[':kw3'] = $term;
        }

        // 2. Filtro por Rol
        if (!empty($filters['rol'])) {
            $whereClauses[] = "u.tipoUsuario_id = :rol";
            $params[':rol'] = $filters['rol'];
        }

        // 3. Filtro por Partido
        if (!empty($filters['partido'])) {
            $whereClauses[] = "u.partido_id = :partido";
            $params[':partido'] = $filters['partido'];
        }

        $sqlWhere = " WHERE " . implode(" AND ", $whereClauses);

        // --- Query 1: Contar Total ---
        $sqlCount = "SELECT COUNT(*) " . $sqlBase . $sqlWhere;
        $stmtCount = $this->conn->prepare($sqlCount);
        $stmtCount->execute($params);
        $total = $stmtCount->fetchColumn();

        // --- Query 2: Obtener Datos ---
        $sqlData = "SELECT u.*, t.descTipoUsuario, p.nombrePartido " . $sqlBase . $sqlWhere . " 
                    ORDER BY u.idUsuario DESC 
                    LIMIT $limit OFFSET $offset";
        
        $stmt = $this->conn->prepare($sqlData);
        
        // Vinculamos los parámetros manualmente para asegurar el tipo correcto
        foreach ($params as $key => $val) {
            // bindValue es más seguro aquí
            $stmt->bindValue($key, $val);
        }
        
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return ['data' => $data, 'total' => $total];
    }
    // Obtener uno por ID
    public function getById($id)
    {
        $sql = "SELECT * FROM " . $this->table . " WHERE idUsuario = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Alias para getById (usado en el perfil)
    // En app/models/User.php

    public function getUserById($id)
    {
        // Hacemos LEFT JOIN para traer los nombres en lugar de solo los IDs
        $sql = "SELECT u.*, 
                   p.nombrePartido, 
                   prov.nombreProvincia,
                   t.descTipoUsuario
            FROM " . $this->table . " u
            LEFT JOIN t_partido p ON u.partido_id = p.idPartido
            LEFT JOIN t_provincia prov ON u.provincia_id = prov.idProvincia
            LEFT JOIN t_tipousuario t ON u.tipoUsuario_id = t.idTipoUsuario
            WHERE u.idUsuario = :id LIMIT 1";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Crear Usuario
    public function create($data)
    {
        $sql = "INSERT INTO " . $this->table . " 
                (pNombre, sNombre, aPaterno, aMaterno, correo, contrasena, tipoUsuario_id, partido_id, provincia_id, estado, t_partido_nombrePartido) 
                VALUES (:pNombre, :sNombre, :aPaterno, :aMaterno, :correo, :contrasena, :rol, :partido, :provincia, 1, '')";

        $stmt = $this->conn->prepare($sql);

        // Encriptar contraseña
        $hash = password_hash($data['contrasena'], PASSWORD_DEFAULT);

        return $stmt->execute([
            ':pNombre' => $data['pNombre'],
            ':sNombre' => $data['sNombre'] ?? '',
            ':aPaterno' => $data['aPaterno'],
            ':aMaterno' => $data['aMaterno'] ?? '',
            ':correo'   => $data['correo'],
            ':contrasena' => $hash,
            ':rol'     => $data['tipoUsuario_id'],
            ':partido' => !empty($data['partido_id']) ? $data['partido_id'] : null,
            ':provincia' => !empty($data['provincia_id']) ? $data['provincia_id'] : 0
        ]);
    }

    // Actualizar Usuario (Administración)
    public function update($id, $data)
    {
        $sqlPass = !empty($data['contrasena']) ? ", contrasena = :contrasena" : "";

        $sql = "UPDATE " . $this->table . " SET 
                    pNombre = :pNombre, 
                    sNombre = :sNombre, 
                    aPaterno = :aPaterno, 
                    aMaterno = :aMaterno, 
                    correo = :correo, 
                    tipoUsuario_id = :rol,
                    partido_id = :partido,
                    provincia_id = :provincia
                    $sqlPass
                WHERE idUsuario = :id";

        $stmt = $this->conn->prepare($sql);

        $params = [
            ':pNombre' => $data['pNombre'],
            ':sNombre' => $data['sNombre'] ?? '',
            ':aPaterno' => $data['aPaterno'],
            ':aMaterno' => $data['aMaterno'] ?? '',
            ':correo'   => $data['correo'],
            ':rol'     => $data['tipoUsuario_id'],
            ':partido' => !empty($data['partido_id']) ? $data['partido_id'] : null,
            ':provincia' => !empty($data['provincia_id']) ? $data['provincia_id'] : 0,
            ':id'      => $id
        ];

        if (!empty($data['contrasena'])) {
            $params[':contrasena'] = password_hash($data['contrasena'], PASSWORD_DEFAULT);
        }

        return $stmt->execute($params);
    }

    // Eliminar (Lógico)
    public function delete($id)
    {
        $sql = "UPDATE " . $this->table . " SET estado = 0 WHERE idUsuario = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }

    // --- Helpers para los Selects ---
    public function getRoles()
    {
        return $this->conn->query("SELECT * FROM t_tipousuario ORDER BY descTipoUsuario")->fetchAll(PDO::FETCH_ASSOC);
    }
    public function getPartidos()
    {
        return $this->conn->query("SELECT * FROM t_partido ORDER BY nombrePartido")->fetchAll(PDO::FETCH_ASSOC);
    }
    public function getProvincias()
    {
        return $this->conn->query("SELECT * FROM t_provincia ORDER BY nombreProvincia")->fetchAll(PDO::FETCH_ASSOC);
    }

    // ============================================================
    // 🔄 RECUPERACIÓN DE CONTRASEÑA (Token)
    // ============================================================

    public function guardarTokenRecuperacion($email, $token)
    {
        $stmt = $this->conn->prepare("SELECT idUsuario FROM t_usuario WHERE correo = :email AND estado = 1");
        $stmt->execute([':email' => $email]);
        if (!$stmt->fetch()) return false;

        $expira = date("Y-m-d H:i:s", time() + 3600);
        $sql = "UPDATE t_usuario SET reset_token = :token, reset_expira = :expira WHERE correo = :email";

        $stmtUpd = $this->conn->prepare($sql);
        return $stmtUpd->execute([
            ':token' => $token,
            ':expira' => $expira,
            ':email' => $email
        ]);
    }

    public function verificarToken($token)
    {
        $sql = "SELECT idUsuario FROM t_usuario WHERE reset_token = :token AND reset_expira > NOW() AND estado = 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':token' => $token]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function actualizarPassword($idUsuario, $hashPassword)
    {
        $sql = "UPDATE t_usuario SET contrasena = :pass, reset_token = NULL, reset_expira = NULL WHERE idUsuario = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':pass' => $hashPassword, ':id' => $idUsuario]);
    }

    // ============================================================
    // 👤 PERFIL Y CONFIGURACIÓN (Corregidos)
    // ============================================================

    // app/models/User.php

    public function updateProfilePhoto($id, $path)
    {
        // Usamos 'foto_perfil' que es como se llama en tu base de datos
        $query = "UPDATE " . $this->table . " SET foto_perfil = :path WHERE idUsuario = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':path', $path);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }

    // Método para cambiar la contraseña (Desde Configuración)
    public function updatePassword($id, $newHash)
    {
        // CORREGIDO: campo 'contrasena', no 'password'
        $query = "UPDATE " . $this->table . " SET contrasena = :pass WHERE idUsuario = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':pass', $newHash);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }

    // Método para verificar la contraseña actual
    public function verifyPassword($id, $password)
    {
        // CORREGIDO: selecciona 'contrasena'
        $query = "SELECT contrasena FROM " . $this->table . " WHERE idUsuario = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        // CORREGIDO: verifica contra $row['contrasena']
        if ($row && password_verify($password, $row['contrasena'])) {
            return true;
        }
        return false;
    }

// ... (resto del código del modelo User)

    /**
     * Registra el ingreso o salida del sistema
     * CORREGIDO: Usa $this->conn directamente
     */
    public function registrarLogAcceso($idUsuario, $accion)
    {
        try {
            // Obtenemos la IP del cliente
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';

            $sql = "INSERT INTO t_log_acceso (t_usuario_idUsuario, accion, ipAcceso, fechaRegistro) 
                    VALUES (:id, :accion, :ip, NOW())";
            
            // --- CORRECCIÓN AQUÍ ---
            // Usamos $this->conn directamente porque ya es el objeto PDO inicializado en el __construct
            $stmt = $this->conn->prepare($sql);
            
            $stmt->bindParam(':id', $idUsuario);
            $stmt->bindParam(':accion', $accion);
            $stmt->bindParam(':ip', $ip);
            $stmt->execute();
            
            return true;
        } catch (Exception $e) {
            // Si falla el log, no detenemos el sistema
            return false;
        }
    }
} // Fin de la clase User

