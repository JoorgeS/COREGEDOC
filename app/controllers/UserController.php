<?php

namespace App\Controllers;

use App\Models\User;
use App\Config\Database;

class UserController
{

    private $db;
    private $userModel;

    public function __construct()
    {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->userModel = new User($this->db);
    }

    // --- VER PERFIL ---
    public function perfil()
    {
        // Verificar sesión
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['idUsuario'])) header('Location: index.php');

        $datosUsuario = $this->userModel->getUserById($_SESSION['idUsuario']);

        // [CORRECCIÓN] Mapear datos para que main.php no falle
        if ($datosUsuario) {
            $datosUsuario['nombre'] = $datosUsuario['pNombre'];
            $datosUsuario['apellido'] = $datosUsuario['aPaterno'];
            $datosUsuario['rol'] = $datosUsuario['tipoUsuario_id'];
        }

        // Pasar datos a la vista
        $data = ['usuario' => $datosUsuario, 'pagina_actual' => 'perfil'];
        
        // Cargar vista
        $childView = __DIR__ . '/../views/usuarios/perfil.php';
        require_once __DIR__ . '/../views/layouts/main.php';
    }
    // --- ACTUALIZAR FOTO ---
    public function update_perfil()
    {
        if (session_status() === PHP_SESSION_NONE) session_start();

        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['fotoPerfil'])) {
            $idUsuario = $_SESSION['idUsuario'];
            $file = $_FILES['fotoPerfil'];

            // Validaciones básicas
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $file['name'];
            $filetmp = $file['tmp_name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            if (in_array($ext, $allowed)) {
                // Crear nombre único y ruta
                $new_name = 'user_' . $idUsuario . '_' . time() . '.' . $ext;
                $upload_dir = 'public/img/perfiles/';

                if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

                if (move_uploaded_file($filetmp, $upload_dir . $new_name)) {
                    // Guardar ruta en BD
                    $rutaDB = $upload_dir . $new_name;
                    $this->userModel->updateProfilePhoto($idUsuario, $rutaDB);

                    // Actualizar sesión para reflejar cambio inmediato
                    $_SESSION['rutaImagenPerfil'] = $rutaDB;

                    header('Location: index.php?action=perfil&msg=foto_ok');
                } else {
                    header('Location: index.php?action=perfil&msg=error_upload');
                }
            } else {
                header('Location: index.php?action=perfil&msg=formato_invalido');
            }
        }
    }

    // --- CONFIGURACIÓN (PASSWORD) ---
    public function configuracion()
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        
        // [CORRECCIÓN] Inyectar datos de sesión para que el Navbar/Sidebar funcionen
        $data = [
            'pagina_actual' => 'configuracion',
            'usuario' => [
                'nombre' => $_SESSION['pNombre'] ?? 'Usuario',
                'apellido' => $_SESSION['aPaterno'] ?? '',
                'rol' => $_SESSION['tipoUsuario_id'] ?? 0
            ]
        ];

        $childView = __DIR__ . '/../views/usuarios/configuracion.php';
        require_once __DIR__ . '/../views/layouts/main.php';
    }
    public function update_password()
    {
        if (session_status() === PHP_SESSION_NONE) session_start();

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $current = $_POST['current_password'];
            $new = $_POST['new_password'];
            $confirm = $_POST['confirm_password'];
            $id = $_SESSION['idUsuario'];

            if ($new !== $confirm) {
                header('Location: index.php?action=configuracion&msg=no_match');
                exit();
            }

            if ($this->userModel->verifyPassword($id, $current)) {
                $hash = password_hash($new, PASSWORD_DEFAULT);
                if ($this->userModel->updatePassword($id, $hash)) {
                    header('Location: index.php?action=configuracion&msg=pass_ok');
                } else {
                    header('Location: index.php?action=configuracion&msg=error_db');
                }
            } else {
                header('Location: index.php?action=configuracion&msg=wrong_current');
            }
        }
    }

    // --- LOGOUT ---
    public function logout()
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        session_unset();
        session_destroy();
        header('Location: index.php?action=login');
        exit();
    }

    // Seguridad: Solo permite acceso si es Admin
    private function verificarAdmin()
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        // ROL_ADMINISTRADOR debe ser 6 según tu BD
        if (!isset($_SESSION['idUsuario']) || $_SESSION['tipoUsuario_id'] != ROL_ADMINISTRADOR) {
            header('Location: index.php?action=home'); // Expulsar si no es admin
            exit();
        }
    }

 

    public function index()
    {
        $this->verificarAdmin();
        $model = new User();

        // Obtenemos los datos necesarios para los filtros de la vista
        $roles = $model->getRoles();
        $partidos = $model->getPartidos();

        $data = [
            'usuario' => ['nombre' => $_SESSION['pNombre'], 'apellido' => $_SESSION['aPaterno'], 'rol' => $_SESSION['tipoUsuario_id']],
            'pagina_actual' => 'usuarios_dashboard',

            // 1. Inicializamos 'usuarios' como vacío, ya que se carga por AJAX.
            'usuarios' => [],

            // 2. CORRECCIÓN: Pasamos las listas de filtros a la vista.
            'roles' => $roles,
            'partidos' => $partidos
        ];

        $childView = __DIR__ . '/../views/usuarios/listado.php';
        require_once __DIR__ . '/../views/layouts/main.php';
    }
    // En App/Controllers/UserController.php

    public function form()
    {
        $this->verificarAdmin();
        $model = new User();
        $id = $_GET['id'] ?? null;

        // --- CORRECCIÓN CLAVE AQUÍ ---
        $roles = $model->getRoles();
        $partidos = $model->getPartidos();
        $provincias = $model->getProvincias();
        // ----------------------------

        $data = [
            'usuario' => ['nombre' => $_SESSION['pNombre'], 'apellido' => $_SESSION['aPaterno'], 'rol' => $_SESSION['tipoUsuario_id']],
            'pagina_actual' => 'usuarios_dashboard',
            'edit_user' => $id ? $model->getById($id) : null,

            // Asignamos las listas a $data
            'roles' => $roles,       // <-- Datos de roles para el SELECT
            'partidos' => $partidos, // <-- Datos de partidos para el SELECT
            'provincias' => $provincias // <-- Datos de provincias
        ];

        $childView = __DIR__ . '/../views/usuarios/form.php';
        require_once __DIR__ . '/../views/layouts/main.php';
    }

    public function store()
    {
        $this->verificarAdmin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $model = new User();
            $id = $_POST['idUsuario'] ?? '';

            try {
                if ($id) {
                    $model->update($id, $_POST);
                } else {
                    $model->create($_POST);
                }
                header('Location: index.php?action=usuarios_dashboard');
            } catch (\Exception $e) {
                echo "Error: " . $e->getMessage();
            }
            exit;
        }
    }

    public function delete()
    {
        $this->verificarAdmin();
        $id = $_GET['id'] ?? 0;
        if ($id) {
            $model = new User();
            $model->delete($id);
        }
        header('Location: index.php?action=usuarios_dashboard');
        exit;
    }

    public function apiFiltrarUsuarios()
    {
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json');
        $this->verificarAdmin();

        try {
            $page     = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit    = 10;
            $offset   = ($page - 1) * $limit;

            $filters = [
                'keyword' => $_GET['keyword'] ?? null,
                'rol'     => $_GET['rol'] ?? null,
                'partido' => $_GET['partido'] ?? null
            ];

            $model = new User();
            $result = $model->filtrarUsuarios($limit, $offset, $filters);

            $totalPages = ($result['total'] > 0) ? ceil($result['total'] / $limit) : 1;

            echo json_encode([
                'status' => 'success',
                'data' => $result['data'],
                'total' => $result['total'],
                'page' => $page,
                'totalPages' => $totalPages
            ]);
        } catch (\Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }
}
