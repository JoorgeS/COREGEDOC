<?php
// controllers/ReunionController.php

// Dependencias
require_once __DIR__ . '/../cfg/config.php';
require_once __DIR__ . '/../class/class.conectorDB.php';

class ReunionManager extends BaseConexion
{
    private $db;

    public function __construct()
    {
        $this->db = $this->conectar();
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /**
     * ¡MODIFICADO!
     * Este método ahora crea la Minuta (pendiente) y la Reunión en una sola transacción.
     * Ya no existe una reunión "sin iniciar".
     */
    public function storeReunion($data)
    {
        // 1. Validar datos de entrada
        $comisionId = $data['t_comision_idComision'] ?? null;
        $nombreReunion = $data['nombreReunion'] ?? 'Reunión sin nombre';
        $fechaInicio = $data['fechaInicioReunion'] ?? null;
        $fechaTermino = $data['fechaTerminoReunion'] ?? null;

        if (!$comisionId || !$fechaInicio || !$fechaTermino) {
            return ['status' => 'error', 'message' => 'Faltan datos obligatorios (Comisión, Inicio o Término).'];
        }

        try {
            $this->db->beginTransaction();

            // 2. Obtener el Presidente de la Comisión seleccionada
            $sql_pres = "SELECT t_usuario_idPresidente FROM t_comision WHERE idComision = :comisionId";
            $stmt_pres = $this->db->prepare($sql_pres);
            $stmt_pres->execute([':comisionId' => $comisionId]);
            $presidenteId = $stmt_pres->fetchColumn();

            if (empty($presidenteId)) {
                throw new Exception('La comisión seleccionada no tiene un presidente asignado.');
            }

            // 3. Crear la MINUTA primero para obtener el ID correlativo
            // Asumimos que la fecha/hora de la minuta es la de inicio de la reunión
            $fechaObj = new DateTime($fechaInicio);
            $fechaMinuta = $fechaObj->format('Y-m-d');
            $horaMinuta = $fechaObj->format('H:i:s');

            $sql_minuta = "INSERT INTO t_minuta (
                                t_comision_idComision, t_usuario_idPresidente, estadoMinuta,
                                horaMinuta, fechaMinuta, pathArchivo
                            ) VALUES (
                                :comisionId, :presidenteId, 'PENDIENTE',
                                :horaMinuta, :fechaMinuta, ''
                            )";
            $stmt_minuta = $this->db->prepare($sql_minuta);
            $stmt_minuta->execute([
                ':comisionId' => $comisionId,
                ':presidenteId' => $presidenteId,
                ':horaMinuta' => $horaMinuta,
                ':fechaMinuta' => $fechaMinuta
            ]);
            
            // ¡Este es el ID que querías! (Ej. 26)
            $newIdMinuta = $this->db->lastInsertId();

            // 4. Crear la REUNIÓN y vincularla al t_minuta_idMinuta
            // Nota: Se elimina "numeroReunion", no existe en tu BBDD.
            $sql_reunion = "INSERT INTO t_reunion (
                                nombreReunion, fechaInicioReunion, fechaTerminoReunion,
                                vigente, t_comision_idComision, t_minuta_idMinuta
                            ) VALUES (
                                :nombre, :inicio, :termino, 1, :comisionId, :idMinuta
                            )";
            $stmt_reunion = $this->db->prepare($sql_reunion);
            $stmt_reunion->execute([
                ':nombre' => $nombreReunion,
                ':inicio' => $fechaInicio,
                ':termino' => $fechaTermino,
                ':comisionId' => $comisionId,
                ':idMinuta' => $newIdMinuta // Vinculamos la reunión a la minuta
            ]);

            $this->db->commit();
            
            // Devolvemos el ID de la Minuta para la redirección
            return [
                'status' => 'success', 
                'message' => 'Reunión y Minuta creadas exitosamente.',
                'idMinuta' => $newIdMinuta 
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error DB storeReunion: " . $e->getMessage());
            return ['status' => 'error', 'message' => 'Error al crear la reunión: ' . $e->getMessage()];
        }
    }

    // Método para obtener el listado de reuniones
    public function getReunionesList()
    {
        try {
            // Query actualizada para traer el ID de la minuta y su estado
            $sql = "SELECT 
                        r.idReunion, r.nombreReunion, 
                        r.fechaInicioReunion, r.fechaTerminoReunion, c.nombreComision,
                        r.t_minuta_idMinuta, m.estadoMinuta
                    FROM t_reunion r
                    LEFT JOIN t_comision c ON r.t_comision_idComision = c.idComision
                    LEFT JOIN t_minuta m ON r.t_minuta_idMinuta = m.idMinuta
                    WHERE r.vigente = 1
                    ORDER BY r.fechaInicioReunion DESC";
            $stmt = $this->db->query($sql);
            $reuniones = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return ['status' => 'success', 'data' => $reuniones];
        } catch (PDOException $e) {
            error_log("Error DB getReunionesList: " . $e->getMessage());
            return ['status' => 'error', 'message' => 'Error al obtener listado.'];
        }
    }

    // Método para "borrar" reunión (borrado lógico)
    public function deleteReunion($id)
    {
        $sql = "UPDATE t_reunion SET vigente = 0 WHERE idReunion = :id";
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':id' => $id]);
            return ['status' => 'success', 'message' => 'Reunión deshabilitada.'];
        } catch (PDOException $e) {
            error_log("Error DB deleteReunion: " . $e->getMessage());
            return ['status' => 'error', 'message' => 'Error al deshabilitar.'];
        }
    }

    // La función iniciarReunion() ya no es necesaria con este flujo.
}

// --- Enrutamiento ---
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$action = $_POST['action'] ?? $_GET['action'] ?? null;
$manager = new ReunionManager();
$redirectUrl = '/corevota/views/pages/menu.php?pagina=reunion_listado';

try {
    // --- ACCIONES POST (Formularios) ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        
        if ($action === 'store_reunion') {
            $data = $_POST;
            $response = $manager->storeReunion($data);
            
            if ($response['status'] === 'success') {
                $_SESSION['success'] = $response['message'];
                // ¡REDIRECCIÓN MODIFICADA!
                // Redirigimos directo a editar la minuta recién creada.
                header('Location: /corevota/views/pages/menu.php?pagina=editar_minuta&id=' . $response['idMinuta']);
            } else {
                $_SESSION['error'] = $response['message'];
                // Si falla, volvemos al listado
                header('Location: ' . $redirectUrl);
            }
            exit;
        }

    // --- ACCIONES GET (Enlaces) ---
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        
        if ($action === 'list') {
            // Usado por menu.php para incluir la lista
            $response = $manager->getReunionesList();
            if ($response['status'] === 'success') {
                $reuniones = $response['data'];
                // Carga la vista del listado
                include __DIR__ . '/../views/pages/reunion_listado.php';
            } else {
                echo "<div class='alert alert-danger'>Error: " . htmlspecialchars($response['message']) . "</div>";
            }
            // No hay 'exit' ni 'header' aquí

        } elseif ($action === 'delete' && isset($_GET['id'])) {
            // Esto sigue funcionando igual
            $reunionId = (int)$_GET['id'];
            $response = $manager->deleteReunion($reunionId);
            if ($response['status'] === 'success') $_SESSION['success'] = $response['message'];
            else $_SESSION['error'] = $response['message'];
            header('Location: ' . $redirectUrl);
            exit;
        
        } elseif ($action === 'iniciar_reunion') {
             // Esta acción ya no se usa, pero por si acaso.
             $_SESSION['error'] = 'Esta acción ya no es válida. Las reuniones se inician al crearse.';
             header('Location: ' . $redirectUrl);
             exit;
        }
    }

} catch (Exception $e) {
    $_SESSION['error'] = 'Error inesperado del controlador.';
    error_log("Error Controller: " . $e->getMessage());
    header('Location: ' . $redirectUrl);
    exit;
}
?>