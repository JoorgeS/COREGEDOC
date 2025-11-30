<?php

namespace App\Controllers;

use App\Models\Reunion;
use App\Models\Minuta;
use App\Models\Comision;
use App\Config\Database;
use Exception;

class ReunionController
{
    // ------------------------------------------------------------------
    // 1. SEGURIDAD: Función privada para verificar acceso
    // ------------------------------------------------------------------
    private function verificarPermisos()
    {
        if (session_status() === PHP_SESSION_NONE) session_start();

        // IDs permitidos: 1 (Admin) y 2 (Secretario Técnico)
        $rolesPermitidos = [1, 2];

        if (!isset($_SESSION['idUsuario']) || !in_array($_SESSION['tipoUsuario_id'], $rolesPermitidos)) {
            // Si no tiene permiso, va al home
            header('Location: index.php?action=home');
            exit();
        }
    }

    // ------------------------------------------------------------------
    // 2. VISTA PRINCIPAL: MENÚ DE TARJETAS (Dashboard)
    // ------------------------------------------------------------------
    public function index()
    {
        $this->verificarPermisos();

        $data = [
            'usuario' => ['nombre' => $_SESSION['pNombre'], 'apellido' => $_SESSION['aPaterno'], 'rol' => $_SESSION['tipoUsuario_id']],
            'pagina_actual' => 'reuniones_menu'
        ];

        // Carga la vista de las 3 tarjetas
        $childView = __DIR__ . '/../views/pages/reunion_menu.php';
        require_once __DIR__ . '/../views/layouts/main.php';
    }

    // ------------------------------------------------------------------
    // 3. VISTA SECUNDARIA: LISTADO TIPO TABLA (Antiguo index)
    // ------------------------------------------------------------------
    public function listar()
    {
        $this->verificarPermisos();
        $modelo = new Reunion();
        $reuniones = $modelo->listar();

        $data = [
            'usuario' => ['nombre' => $_SESSION['pNombre'], 'apellido' => $_SESSION['aPaterno'], 'rol' => $_SESSION['tipoUsuario_id']],
            'pagina_actual' => 'reuniones_menu' // Mantiene activo el menú padre
        ];

        // Carga la vista de la tabla
        $childView = __DIR__ . '/../views/pages/reunion_listado.php';
        require_once __DIR__ . '/../views/layouts/main.php';
    }

    // ------------------------------------------------------------------
    // 4. FORMULARIO DE CREACIÓN
    // ------------------------------------------------------------------
    public function create()
    {
        $this->verificarPermisos();
        $comisionModel = new Comision();
        $listaComisiones = $comisionModel->listarTodas();

        $data = [
            'usuario' => ['nombre' => $_SESSION['pNombre'], 'apellido' => $_SESSION['aPaterno'], 'rol' => $_SESSION['tipoUsuario_id']],
            'pagina_actual' => 'reuniones_menu',
            'comisiones' => $listaComisiones
        ];

        $reunion_data = null;
        $childView = __DIR__ . '/../views/pages/reunion_form.php';
        require_once __DIR__ . '/../views/layouts/main.php';
    }

    // ------------------------------------------------------------------
    // 5. GUARDAR (INSERT)
    // ------------------------------------------------------------------
    public function store()
    {
        $this->verificarPermisos();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $modelo = new Reunion();
            $datos = [
                'nombre'    => $_POST['nombreReunion'],
                'comision'  => $_POST['t_comision_idComision'],
                'comision2' => !empty($_POST['t_comision_idComision_mixta']) ? $_POST['t_comision_idComision_mixta'] : null,
                'comision3' => !empty($_POST['t_comision_idComision_mixta2']) ? $_POST['t_comision_idComision_mixta2'] : null,
                'inicio'    => $_POST['fechaInicioReunion'],
                'termino'   => $_POST['fechaTerminoReunion']
            ];

            $modelo->crear($datos);
            // Redirige al listado de tabla tras guardar
            header('Location: index.php?action=reunion_listado&msg=guardado');
            exit();
        }
    }

    // ------------------------------------------------------------------
    // 6. FORMULARIO DE EDICIÓN
    // ------------------------------------------------------------------
    public function edit()
    {
        $this->verificarPermisos();
        $id = $_GET['id'] ?? 0;

        $modelo = new Reunion();
        $reunion_data = $modelo->obtenerPorId($id);

        // Bloqueo: Si ya tiene minuta, no se edita aquí
        if (!empty($reunion_data['t_minuta_idMinuta'])) {
            echo "<script>alert('La reunión ya fue iniciada y no puede editarse.'); window.history.back();</script>";
            exit;
        }

        $comisionModel = new Comision();
        $listaComisiones = $comisionModel->listarTodas();

        $data = [
            'usuario' => ['nombre' => $_SESSION['pNombre'], 'apellido' => $_SESSION['aPaterno'], 'rol' => $_SESSION['tipoUsuario_id']],
            'pagina_actual' => 'reuniones_menu',
            'comisiones' => $listaComisiones
        ];

        $childView = __DIR__ . '/../views/pages/reunion_form.php';
        require_once __DIR__ . '/../views/layouts/main.php';
    }

    // ------------------------------------------------------------------
    // 7. ACTUALIZAR (UPDATE)
    // ------------------------------------------------------------------
    public function update()
    {
        $this->verificarPermisos();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = $_POST['idReunion'];
            $modelo = new Reunion();

            // Verificamos de nuevo que no tenga minuta antes de actualizar
            $reunionActual = $modelo->obtenerPorId($id);
            if (!empty($reunionActual['t_minuta_idMinuta'])) {
                die("Error: Reunión ya iniciada.");
            }

            $datos = [
                'nombre'    => $_POST['nombreReunion'],
                'comision'  => $_POST['t_comision_idComision'],
                'comision2' => !empty($_POST['t_comision_idComision_mixta']) ? $_POST['t_comision_idComision_mixta'] : null,
                'comision3' => !empty($_POST['t_comision_idComision_mixta2']) ? $_POST['t_comision_idComision_mixta2'] : null,
                'inicio'    => $_POST['fechaInicioReunion'],
                'termino'   => $_POST['fechaTerminoReunion']
            ];

            $modelo->actualizar($id, $datos);
            header('Location: index.php?action=reunion_listado&msg=editado');
            exit();
        }
    }

    // ------------------------------------------------------------------
    // 8. ELIMINAR
    // ------------------------------------------------------------------
    public function delete()
    {
        $this->verificarPermisos();
        $id = $_GET['id'] ?? 0;
        if ($id) {
            $modelo = new Reunion();
            $modelo->eliminar($id);
        }
        header('Location: index.php?action=reunion_listado&msg=eliminado');
        exit();
    }

    // ------------------------------------------------------------------
    // 9. INICIAR REUNIÓN (Generar Minuta y Vincular)
    // ------------------------------------------------------------------
    public function iniciarMinuta()
    {
        $this->verificarPermisos();
        $idReunion = $_GET['idReunion'] ?? 0;
        $idSecretario = $_SESSION['idUsuario'];

        if (!$idReunion) {
            header('Location: index.php?action=reunion_listado');
            exit();
        }

        try {
            $reunionModel = new Reunion();

            // Validar si ya tiene minuta (si ya existe, redirige directo)
            $reunionData = $reunionModel->obtenerPorId($idReunion);
            if (!empty($reunionData['t_minuta_idMinuta'])) {
                header("Location: index.php?action=minuta_gestionar&id=" . $reunionData['t_minuta_idMinuta']);
                exit();
            }

            // Datos para crear minuta (Presidente, Comision)
            $datosComision = $reunionModel->obtenerDatosParaMinuta($idReunion);
            if (!$datosComision || empty($datosComision['t_usuario_idPresidente'])) {
                echo "<script>alert('Error: La comisión no tiene Presidente asignado.'); window.location.href='index.php?action=reunion_listado';</script>";
                exit;
            }

            $db = new Database();
            $conn = $db->getConnection();

            // 1. Crear Minuta en la Base de Datos
            $sql = "INSERT INTO t_minuta (t_comision_idComision, t_usuario_idPresidente, estadoMinuta, horaMinuta, fechaMinuta, t_usuario_idSecretario) 
                    VALUES (:com, :presi, 'BORRADOR', :hora, :fecha, :sec)";

            $ahora = new \DateTime("now", new \DateTimeZone('America/Santiago'));

            $stmt = $conn->prepare($sql);
            $stmt->execute([
                ':com' => $datosComision['t_comision_idComision'],
                ':presi' => $datosComision['t_usuario_idPresidente'],
                ':hora' => $ahora->format('H:i:s'),
                ':fecha' => $ahora->format('Y-m-d'),
                ':sec' => $idSecretario
            ]);

            // Obtenemos el ID de la minuta recién creada
            $idNuevaMinuta = $conn->lastInsertId();

            // 2. Vincular la Minuta a la Reunión
            $reunionModel->vincularMinuta($idReunion, $idNuevaMinuta);

            // =====================================================================
            // 3. NUEVO: GENERAR PLANILLA DE ASISTENCIA
            // =====================================================================
            // Aquí es donde "habilitamos" la asistencia creando los registros vacíos
            $minutaModel = new Minuta();
            $minutaModel->generarPlanillaAsistencia($idNuevaMinuta);
            // =====================================================================

            // 4. Redirigir al gestor
            header("Location: index.php?action=minuta_gestionar&id=" . $idNuevaMinuta);
            exit();
        } catch (Exception $e) {
            echo "Error al iniciar: " . $e->getMessage();
        }
    }
    // ------------------------------------------------------------------
    // 10. CALENDARIO
    // ------------------------------------------------------------------
    public function calendario()
    {
        // Permitimos ver a Consejeros también, así que verificamos sesión general
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['idUsuario'])) {
            header('Location: index.php');
            exit;
        }

        $modelo = new Reunion();
        $reuniones = $modelo->listar();
        $isEmbedded = isset($_GET['embedded']) && $_GET['embedded'] == 'true';

        $data = [
            'reuniones' => $reuniones,
            'isEmbedded' => $isEmbedded
        ];

        $childView = __DIR__ . '/../views/pages/reunion_calendario.php';

        if ($isEmbedded) {
            require_once $childView;
        } else {
            // Pasamos datos extra si es vista completa
            $data['usuario'] = ['nombre' => $_SESSION['pNombre'], 'apellido' => $_SESSION['aPaterno'], 'rol' => $_SESSION['tipoUsuario_id']];
            $data['pagina_actual'] = 'reuniones_menu';
            require_once __DIR__ . '/../views/layouts/main.php';
        }
    }
}
