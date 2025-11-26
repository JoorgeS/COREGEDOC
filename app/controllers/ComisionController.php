<?php
namespace App\Controllers;

use App\Models\Comision;

class ComisionController {

    private function checkAdmin() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        // Validar ROL_ADMINISTRADOR (ID 6)
        if (!isset($_SESSION['idUsuario']) || $_SESSION['tipoUsuario_id'] != ROL_ADMINISTRADOR) {
            header('Location: index.php?action=home');
            exit();
        }
    }

    public function index() {
        $this->checkAdmin();
        $model = new Comision();
        
        $data = [
            'usuario' => ['nombre' => $_SESSION['pNombre'], 'apellido' => $_SESSION['aPaterno'], 'rol' => $_SESSION['tipoUsuario_id']],
            'pagina_actual' => 'comisiones_dashboard',
            'comisiones' => $model->getAll()
        ];

        $childView = __DIR__ . '/../views/comisiones/listado.php';
        require_once __DIR__ . '/../views/layouts/main.php';
    }

    public function form() {
        $this->checkAdmin();
        $model = new Comision();
        $id = $_GET['id'] ?? null;

        $data = [
            'usuario' => ['nombre' => $_SESSION['pNombre'], 'apellido' => $_SESSION['aPaterno'], 'rol' => $_SESSION['tipoUsuario_id']],
            'pagina_actual' => 'comisiones_dashboard',
            'edit_comision' => $id ? $model->getById($id) : null,
            'candidatos' => $model->getPosiblesAutoridades()
        ];

        $childView = __DIR__ . '/../views/comisiones/form.php';
        require_once __DIR__ . '/../views/layouts/main.php';
    }

    public function store() {
        $this->checkAdmin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $model = new Comision();
            $id = $_POST['idComision'] ?? '';

            try {
                if ($id) {
                    $model->update($id, $_POST);
                } else {
                    $model->create($_POST);
                }
                header('Location: index.php?action=comisiones_dashboard');
            } catch (\Exception $e) {
                echo "Error: " . $e->getMessage();
            }
            exit;
        }
    }

    public function delete() {
        $this->checkAdmin();
        $id = $_GET['id'] ?? 0;
        if ($id) {
            $model = new Comision();
            $model->delete($id);
        }
        header('Location: index.php?action=comisiones_dashboard');
        exit;
    }
}