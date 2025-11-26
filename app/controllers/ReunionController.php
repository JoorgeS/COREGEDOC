<?php
namespace App\Controllers;

use App\Models\Reunion;
use App\Models\Minuta;
use App\Models\Comision;
use App\Config\Database;

class ReunionController
{
    private function verificarSesion() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['idUsuario'])) {
            header('Location: index.php?action=login');
            exit();
        }
    }

    public function index()
    {
        $this->verificarSesion();
        $modelo = new Reunion();
        $reuniones = $modelo->listar();

        // Datos para el layout
        $data = [
            'usuario' => ['nombre' => $_SESSION['pNombre'], 'apellido' => $_SESSION['aPaterno'], 'rol' => $_SESSION['tipoUsuario_id']],
            'pagina_actual' => 'reuniones_dashboard'
        ];

        // Cargamos la vista que ya creaste
        $childView = __DIR__ . '/../views/pages/reunion_listado.php';
        require_once __DIR__ . '/../views/layouts/main.php';
    }

    public function create()
    {
        $this->verificarSesion();
        $comisionModel = new Comision();
        $listaComisiones = $comisionModel->listarTodas();
        $data = [
            'usuario' => ['nombre' => $_SESSION['pNombre'], 'apellido' => $_SESSION['aPaterno'], 'rol' => $_SESSION['tipoUsuario_id']],
            'pagina_actual' => 'reunion_form',
            'comisiones' => $listaComisiones
        ];
        
        // Variable vacía para indicar que es creación
        $reunion_data = null; 

        $childView = __DIR__ . '/../views/pages/reunion_form.php';
        require_once __DIR__ . '/../views/layouts/main.php';
    }

    public function store()
    {
        $this->verificarSesion();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $modelo = new Reunion();
            $datos = [
                'nombre' => $_POST['nombreReunion'],
                'comision' => $_POST['t_comision_idComision'],
                'inicio' => $_POST['fechaInicioReunion'],
                'termino' => $_POST['fechaTerminoReunion']
            ];
            
            $modelo->crear($datos);
            header('Location: index.php?action=reuniones_dashboard');
            exit();
        }
    }

    public function edit()
    {
        $this->verificarSesion();
        $id = $_GET['id'] ?? 0;
        
        $modelo = new Reunion();
        $reunion_data = $modelo->obtenerPorId($id); // Variable que espera tu vista

        $comisionModel = new Comision();
        $listaComisiones = $comisionModel->listarTodas();

        $data = [
            'usuario' => ['nombre' => $_SESSION['pNombre'], 'apellido' => $_SESSION['aPaterno'], 'rol' => $_SESSION['tipoUsuario_id']],
            'pagina_actual' => 'reunion_form',
            'comisiones' => $listaComisiones
        ];

        $childView = __DIR__ . '/../views/pages/reunion_form.php';
        require_once __DIR__ . '/../views/layouts/main.php';
    }

    public function update()
    {
        $this->verificarSesion();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = $_POST['idReunion'];
            $modelo = new Reunion();
            $datos = [
                'nombre' => $_POST['nombreReunion'],
                'comision' => $_POST['t_comision_idComision'],
                'inicio' => $_POST['fechaInicioReunion'],
                'termino' => $_POST['fechaTerminoReunion']
            ];
            
            $modelo->actualizar($id, $datos);
            header('Location: index.php?action=reuniones_dashboard');
            exit();
        }
    }

    public function delete()
    {
        $this->verificarSesion();
        $id = $_GET['id'] ?? 0;
        if ($id) {
            $modelo = new Reunion();
            $modelo->eliminar($id);
        }
        header('Location: index.php?action=reuniones_dashboard');
        exit();
    }

    // --- LA FUNCIÓN MÁGICA: INICIAR Y SINCRONIZAR ---
    public function iniciarMinuta()
    {
        $this->verificarSesion();
        $idReunion = $_GET['idReunion'] ?? 0;
        $idSecretario = $_SESSION['idUsuario'];

        if (!$idReunion) {
            header('Location: index.php?action=reuniones_dashboard');
            exit();
        }

        try {
            $reunionModel = new Reunion();
            
            // 1. Obtener datos para crear la minuta (Fecha, Presidente, Comision)
            $datos = $reunionModel->obtenerDatosParaMinuta($idReunion);
            
            if (!$datos || empty($datos['t_usuario_idPresidente'])) {
                // Manejo de error si no hay presidente asignado
                die("Error: La comisión no tiene presidente asignado."); 
            }

            // 2. Usar SQL directo o un método en Minuta para crearla
            // (Aquí simplificamos insertando directamente para no modificar más el modelo Minuta, 
            // pero lo ideal sería Minuta::crear($datos))
            
            $db = new Database();
            $conn = $db->getConnection();
            
            $sql = "INSERT INTO t_minuta (t_comision_idComision, t_usuario_idPresidente, estadoMinuta, horaMinuta, fechaMinuta, t_usuario_idSecretario) 
                    VALUES (:com, :presi, 'BORRADOR', :hora, :fecha, :sec)";
            
            $fechaObj = new \DateTime($datos['fechaInicioReunion']);
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                ':com' => $datos['t_comision_idComision'],
                ':presi' => $datos['t_usuario_idPresidente'],
                ':hora' => $fechaObj->format('H:i:s'),
                ':fecha' => $fechaObj->format('Y-m-d'),
                ':sec' => $idSecretario
            ]);
            
            $idNuevaMinuta = $conn->lastInsertId();

            // 3. Sincronizar: Guardar ID Minuta en la Reunión
            $reunionModel->vincularMinuta($idReunion, $idNuevaMinuta);

            // 4. Redirigir al editor
            header("Location: index.php?action=minuta_gestionar&id=" . $idNuevaMinuta);
            exit();

        } catch (Exception $e) {
            echo "Error al iniciar: " . $e->getMessage();
        }
    }

    // ... dentro de la clase ReunionController ...

    public function calendario()
    {
        $this->verificarSesion();
        $modelo = new Reunion();
        
        // Reutilizamos el método listar() que ya trae todas las reuniones vigentes
        $reuniones = $modelo->listar();

        $data = [
            'usuario' => ['nombre' => $_SESSION['pNombre'], 'apellido' => $_SESSION['aPaterno'], 'rol' => $_SESSION['tipoUsuario_id']],
            'pagina_actual' => 'reunion_calendario',
            'reuniones' => $reuniones
        ];

        $childView = __DIR__ . '/../views/pages/reunion_calendario.php';
        require_once __DIR__ . '/../views/layouts/main.php';
    }
}