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

    public function apiFiltrarComisiones()
    {
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json');
        
        $db = new \App\Config\Database();
        $conn = $db->getConnection();

        try {
            // 2. Recibir Parámetros
            $page     = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit    = 10;
            $offset   = ($page - 1) * $limit;
            $keyword  = !empty($_GET['keyword']) ? trim($_GET['keyword']) : null;

            // 3. Construcción SQL Base
            $sqlFrom = " FROM t_comision c
                         LEFT JOIN t_usuario p ON c.t_usuario_idPresidente = p.idUsuario
                         LEFT JOIN t_usuario v ON c.t_usuario_idVicepresidente = v.idUsuario ";

            // Filtros WHERE
            $whereClauses = ["1=1"];
            $params = [];

            if ($keyword) {
                // --- CORRECCIÓN AQUÍ ---
                // Usamos marcadores únicos (:kw1, :kw2...) para cada campo
                $whereClauses[] = "(c.nombreComision LIKE :kw1 
                                    OR p.pNombre LIKE :kw2 OR p.aPaterno LIKE :kw3 
                                    OR v.pNombre LIKE :kw4 OR v.aPaterno LIKE :kw5)";
                
                $term = "%$keyword%";
                $params[':kw1'] = $term;
                $params[':kw2'] = $term;
                $params[':kw3'] = $term;
                $params[':kw4'] = $term;
                $params[':kw5'] = $term;
            }

            $sqlWhere = " WHERE " . implode(" AND ", $whereClauses);

            // 4. CONSULTA 1: Contar Total
            $sqlCount = "SELECT COUNT(*) " . $sqlFrom . $sqlWhere;
            $stmtCount = $conn->prepare($sqlCount);
            $stmtCount->execute($params);
            $totalRecords = $stmtCount->fetchColumn();
            $totalPages = ceil($totalRecords / $limit);

            // 5. CONSULTA 2: Obtener Datos
            $sqlData = "SELECT 
                            c.idComision, 
                            c.nombreComision,
                            c.vigencia, 
                            p.pNombre as presNombre, p.aPaterno as presApellido,
                            v.pNombre as viceNombre, v.aPaterno as viceApellido
                        " . $sqlFrom . $sqlWhere . " 
                        ORDER BY c.nombreComision ASC 
                        LIMIT $limit OFFSET $offset";

            $stmt = $conn->prepare($sqlData);
            $stmt->execute($params);
            $data = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // 6. Formatear nombres
            $resultados = [];
            foreach($data as $row) {
                $nombrePres = trim(($row['presNombre'] ?? '') . ' ' . ($row['presApellido'] ?? ''));
                $nombreVice = trim(($row['viceNombre'] ?? '') . ' ' . ($row['viceApellido'] ?? ''));
                
                $resultados[] = [
                    'idComision' => $row['idComision'],
                    'nombreComision' => $row['nombreComision'],
                    'vigencia' => $row['vigencia'],
                    'nombrePresidente' => $nombrePres ?: null,
                    'nombreVicepresidente' => $nombreVice ?: null
                ];
            }

            echo json_encode([
                'status' => 'success',
                'data' => $resultados,
                'total' => $totalRecords,
                'page' => $page,
                'totalPages' => $totalPages
            ]);

        } catch (\Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    // --- NUEVA FUNCIÓN API PARA CAMBIAR ESTADO ---
    public function apiCambiarEstado()
    {
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json');
        $this->checkAdmin(); // Seguridad

        $input = json_decode(file_get_contents('php://input'), true);
        $id = $input['id'] ?? 0;
        $estadoActual = $input['estadoActual'] ?? 0;

        if (!$id) {
            echo json_encode(['status' => 'error', 'message' => 'ID requerido']);
            exit;
        }

        try {
            $model = new Comision();
            $res = $model->toggleVigencia($id, $estadoActual);
            
            if ($res) {
                echo json_encode(['status' => 'success', 'message' => 'Estado actualizado']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'No se pudo actualizar']);
            }
        } catch (\Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
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

    
}