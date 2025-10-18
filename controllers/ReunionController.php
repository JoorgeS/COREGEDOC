<?php
// Asegúrate de tener tu archivo de conexión disponible
require_once __DIR__ . '/../cfg/config.php';

// La clase para manejar la conexión (asumiendo que BaseConexion es tu clase)
class ReunionManager extends BaseConexion
{
    private $db;

    public function __construct()
    {
        $this->db = $this->conectar();
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function createReunion($data)
    {
        $sql = "INSERT INTO t_reunion (
                    numeroReunion, nombreReunion, fechaInicioReunion, fechaTerminoReunion, 
                    vigente, t_acuerdo_idAcuerdo, t_comision_idComision, t_minuta_idMinuta
                ) VALUES (
                    :numero, :nombre, :inicio, :termino, :vigente, 
                    NULL, :comisionId, NULL
                )";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':numero' => $data['numeroReunion'],
                ':nombre' => $data['nombreReunion'],
                ':inicio' => $data['fechaInicioReunion'],
                ':termino' => $data['fechaTerminoReunion'],
                ':vigente' => $data['vigente'],
                ':comisionId' => $data['t_comision_idComision']
            ]);

            $idReunion = $this->db->lastInsertId();
            return ['status' => 'success', 'message' => 'Reunión creada.', 'idReunion' => $idReunion];
        } catch (PDOException $e) {
            return ['status' => 'error', 'message' => 'Error de BD: ' . $e->getMessage(), 'error' => $e->errorInfo];
        }
    }

    public function getReunionesList()
    {
        try {
            $sql = "SELECT 
                        r.idReunion, r.nombreReunion, r.numeroReunion, 
                        r.fechaInicioReunion, r.fechaTerminoReunion, c.nombreComision
                    FROM t_reunion r
                    JOIN t_comision c ON r.t_comision_idComision = c.idComision
                    WHERE r.vigente = 1 /* <-- MUESTRA SOLO LAS VIGENTES */
                    ORDER BY r.fechaInicioReunion DESC";

            $stmt = $this->db->query($sql);
            $reuniones = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return ['status' => 'success', 'data' => $reuniones];
        } catch (PDOException $e) {
            return ['status' => 'error', 'message' => 'Error al obtener listado de BD.', 'error' => $e->getMessage()];
        }
    }

    // --- NUEVO MÉTODO: BORRADO LÓGICO ---
    public function deleteReunion($id)
    {
        // Borrado Lógico: Marcamos 'vigente' como 0 (Inactivo)
        $sql = "UPDATE t_reunion SET vigente = 0 WHERE idReunion = :id";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':id' => $id]);

            if ($stmt->rowCount() > 0) {
                return ['status' => 'success', 'message' => 'Reunión eliminada (lógicamente).'];
            } else {
                return ['status' => 'error', 'message' => 'Reunión no encontrada o ya estaba inactiva.'];
            }
        } catch (PDOException $e) {
            return ['status' => 'error', 'message' => 'Error de BD al eliminar lógicamente: ' . $e->getMessage(), 'error' => $e->errorInfo];
        }
    }
    // ------------------------------------

    public function getReunionById($id)
    {
        try {
            $sql = "SELECT 
                        r.idReunion, 
                        r.nombreReunion, 
                        r.numeroReunion, 
                        r.fechaInicioReunion, 
                        r.fechaTerminoReunion,
                        r.t_comision_idComision
                    FROM t_reunion r
                    WHERE r.idReunion = :id";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([':id' => $id]);

            $reunion = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($reunion) {
                return ['status' => 'success', 'data' => $reunion];
            } else {
                return ['status' => 'error', 'message' => 'Reunión no encontrada.'];
            }
        } catch (PDOException $e) {
            return ['status' => 'error', 'message' => 'Error de BD al buscar reunión.', 'error' => $e->getMessage()];
        }
    }

    public function updateReunion($data)
    {
        $sql = "UPDATE t_reunion SET
                    nombreReunion = :nombre,
                    numeroReunion = :numero,
                    fechaInicioReunion = :inicio,
                    fechaTerminoReunion = :termino,
                    t_comision_idComision = :comisionId,
                    vigente = :vigente
                WHERE idReunion = :id";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':id' => $data['id'],
                ':nombre' => $data['nombreReunion'],
                ':numero' => $data['numeroReunion'],
                ':inicio' => $data['fechaInicioReunion'],
                ':termino' => $data['fechaTerminoReunion'],
                ':vigente' => $data['vigente'],
                ':comisionId' => $data['t_comision_idComision']
            ]);
            
            if ($stmt->rowCount() === 0 && $data['id'] != 0) {
                return ['status' => 'success', 'message' => 'Reunión actualizada (sin cambios).'];
            }
            return ['status' => 'success', 'message' => 'Reunión actualizada.'];
        } catch (PDOException $e) {
            return ['status' => 'error', 'message' => 'Error de BD al actualizar: ' . $e->getMessage(), 'error' => $e->errorInfo];
        }
    }
}

// --- Enrutamiento de Acciones ---
if (isset($_GET['action'])) {
    $manager = new ReunionManager();
    $action = $_GET['action'];
    $response = ['status' => 'error', 'message' => 'Acción no válida.'];

    // Lógica para manejar peticiones POST y GET
    if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        $response = $manager->createReunion($data);
    } elseif ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        $response = $manager->updateReunion($data);
    } elseif ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['id'])) { // <-- ¡NUEVO CASO!
        $reunionId = (int)$_GET['id'];
        $response = $manager->deleteReunion($reunionId);
    } elseif ($action === 'list' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        // Lógica para devolver HTML (vista)

        $response = $manager->getReunionesList();

        if ($response['status'] === 'success') {
            $reuniones = $response['data'];

            // Eliminar y forzar el encabezado HTML para renderizar la vista
            header_remove('Content-Type');
            header('Content-Type: text/html; charset=utf-8');

            // Cargar la vista de listado
            include __DIR__ . '/../views/pages/reunion_listado.php';
            exit;
        }
    } elseif ($action === 'edit' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        // Lógica de edición manejada por crearReunion.php, aquí no hace nada especial.
    }

    // Si la acción no fue 'list', se devuelve el JSON (para create/update/delete/error)
    if ($action !== 'list') {
        echo json_encode($response);
    }
}