<?php
namespace App\Controllers;

use App\Models\User;

class UserController {
    
    // Seguridad: Solo permite acceso si es Admin
    private function verificarAdmin() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        // ROL_ADMINISTRADOR debe ser 6 según tu BD
        if (!isset($_SESSION['idUsuario']) || $_SESSION['tipoUsuario_id'] != ROL_ADMINISTRADOR) {
            header('Location: index.php?action=home'); // Expulsar si no es admin
            exit();
        }
    }

    public function index() {
        $this->verificarAdmin();
        $model = new User();
        
        $data = [
            'usuario' => ['nombre' => $_SESSION['pNombre'], 'apellido' => $_SESSION['aPaterno'], 'rol' => $_SESSION['tipoUsuario_id']],
            'pagina_actual' => 'usuarios_dashboard',
            'usuarios' => $model->getAll()
        ];

        $childView = __DIR__ . '/../views/usuarios/listado.php';
        require_once __DIR__ . '/../views/layouts/main.php';
    }

    public function form() {
        $this->verificarAdmin();
        $model = new User();
        $id = $_GET['id'] ?? null;

        $data = [
            'usuario' => ['nombre' => $_SESSION['pNombre'], 'apellido' => $_SESSION['aPaterno'], 'rol' => $_SESSION['tipoUsuario_id']],
            'pagina_actual' => 'usuarios_dashboard',
            'edit_user' => $id ? $model->getById($id) : null,
            'roles' => $model->getRoles(),
            'partidos' => $model->getPartidos(),
            'provincias' => $model->getProvincias()
        ];

        $childView = __DIR__ . '/../views/usuarios/form.php';
        require_once __DIR__ . '/../views/layouts/main.php';
    }

    public function store() {
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

    public function delete() {
        $this->verificarAdmin();
        $id = $_GET['id'] ?? 0;
        if ($id) {
            $model = new User();
            $model->delete($id);
        }
        header('Location: index.php?action=usuarios_dashboard');
        exit;
    }
}