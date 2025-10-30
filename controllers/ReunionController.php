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
    /**
     * ¡MODIFICADO!
     * Guarda SOLO la reunión. La minuta se crea al 'Iniciar'.
     */
    public function storeReunion($data)
    {
        // 1. Validar datos de entrada (igual que antes)
        $comisionId = $data['t_comision_idComision'] ?? null;
        $nombreReunion = $data['nombreReunion'] ?? 'Reunión sin nombre';
        $fechaInicio = $data['fechaInicioReunion'] ?? null;
        $fechaTermino = $data['fechaTerminoReunion'] ?? null;
        $esMixta = isset($data['comisionMixta']) && $data['comisionMixta'] == '1';
        $comisionIdMixta = $esMixta ? ($data['t_comision_idComision_mixta'] ?? null) : null;
        $comisionIdMixta2 = $esMixta && !empty($data['t_comision_idComision_mixta2']) ? $data['t_comision_idComision_mixta2'] : null;

        if (!$comisionId || !$fechaInicio || !$fechaTermino) {
            return ['status' => 'error', 'message' => 'Faltan datos obligatorios (Comisión, Inicio o Término).'];
        }
        if ($esMixta && !$comisionIdMixta) {
            return ['status' => 'error', 'message' => 'Marcó Comisión Mixta pero no seleccionó la segunda comisión.'];
        }
        $selectedComisiones = array_filter([$comisionId, $comisionIdMixta, $comisionIdMixta2]);
        if (count($selectedComisiones) !== count(array_unique($selectedComisiones))) {
            return ['status' => 'error', 'message' => 'Las comisiones seleccionadas no pueden repetirse.'];
        }

        // --- INICIO CAMBIOS ---
        try {
            // No necesitamos transacción aquí ya que es una sola inserción simple.

            // Crear la REUNIÓN SIN vincular minuta (t_minuta_idMinuta = NULL)
            $sql_reunion = "INSERT INTO t_reunion (
                    nombreReunion, fechaInicioReunion, fechaTerminoReunion,
                    vigente, t_comision_idComision,
                    t_comision_idComision_mixta,
                    t_comision_idComision_mixta2,
                    t_minuta_idMinuta  -- Se insertará NULL por defecto o explícitamente si es necesario
                ) VALUES (
                    :nombre, :inicio, :termino, 1, :comisionId,
                    :comisionIdMixta,
                    :comisionIdMixta2,
                    NULL -- Aseguramos que sea NULL
                )";

            $stmt_reunion = $this->db->prepare($sql_reunion);
            $success = $stmt_reunion->execute([
                ':nombre' => $nombreReunion,
                ':inicio' => $fechaInicio,
                ':termino' => $fechaTermino,
                ':comisionId' => $comisionId,
                ':comisionIdMixta' => $comisionIdMixta,
                ':comisionIdMixta2' => $comisionIdMixta2
            ]);

            if (!$success || $stmt_reunion->rowCount() === 0) {
                throw new Exception("Error al insertar la reunión.");
            }

            return [
                'status' => 'success',
                'message' => 'Reunión creada exitosamente. Podrá iniciarla desde el listado cuando llegue la hora.'
                // Ya no devolvemos 'idMinuta'
            ];
        } catch (Exception $e) {
            error_log("Error DB storeReunion (simplificado): " . $e->getMessage());
            // Mensajes de error específicos (igual que antes)
            if (strpos($e->getMessage(), 'foreign key constraint fails') !== false) {
                return ['status' => 'error', 'message' => 'Error: Una de las comisiones seleccionadas no es válida.'];
            }
            // ...otros if de errores...
            return ['status' => 'error', 'message' => 'Error al crear la reunión: Verifique los datos. Detalles: ' . $e->getMessage()];
        }
        // --- FIN CAMBIOS ---
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

    /**
     * ¡NUEVO!
     * Crea la minuta para una reunión existente y la vincula.
     * Se llama al presionar "Iniciar Reunión".
     */
    public function iniciarMinuta($idReunion)
    {
        $idReunion = (int)$idReunion;
        if ($idReunion <= 0) {
            return ['status' => 'error', 'message' => 'ID de reunión inválido.'];
        }

        try {
            $this->db->beginTransaction();

            // 1. Verificar que la reunión exista, esté vigente y NO tenga minuta asignada
            $sql_check = "SELECT idReunion, t_comision_idComision, fechaInicioReunion
                          FROM t_reunion
                          WHERE idReunion = :id AND vigente = 1 AND t_minuta_idMinuta IS NULL";
            $stmt_check = $this->db->prepare($sql_check);
            $stmt_check->execute([':id' => $idReunion]);
            $reunionData = $stmt_check->fetch(PDO::FETCH_ASSOC);

            if (!$reunionData) {
                throw new Exception('La reunión no existe, no está vigente o ya tiene una minuta iniciada.');
            }

            // (Opcional) Verificar si ya pasó la hora de inicio - aunque la UI ya lo haría
            $meetingStartTime = strtotime($reunionData['fechaInicioReunion']);
            if (time() < $meetingStartTime) {
                throw new Exception('Aún no es hora de iniciar esta reunión.');
            }

            $comisionId = $reunionData['t_comision_idComision'];
            $fechaInicio = $reunionData['fechaInicioReunion'];

            // 2. Obtener el Presidente de la Comisión
            $sql_pres = "SELECT t_usuario_idPresidente FROM t_comision WHERE idComision = :comisionId";
            $stmt_pres = $this->db->prepare($sql_pres);
            $stmt_pres->execute([':comisionId' => $comisionId]);
            $presidenteId = $stmt_pres->fetchColumn();

            if (empty($presidenteId)) {
                throw new Exception('La comisión de la reunión no tiene un presidente asignado.');
            }

            // 3. Crear la MINUTA
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

            $newIdMinuta = $this->db->lastInsertId();
            if (!$newIdMinuta) {
                throw new Exception('No se pudo crear la minuta.');
            }

            // 4. Actualizar la REUNIÓN para vincular la minuta creada
            $sql_update_reunion = "UPDATE t_reunion SET t_minuta_idMinuta = :idMinuta WHERE idReunion = :idReunion";
            $stmt_update = $this->db->prepare($sql_update_reunion);
            $success_update = $stmt_update->execute([
                ':idMinuta' => $newIdMinuta,
                ':idReunion' => $idReunion
            ]);

            if (!$success_update || $stmt_update->rowCount() === 0) {
                throw new Exception('No se pudo vincular la minuta a la reunión.');
            }

            $this->db->commit();

            // Devolvemos el ID de la Minuta para la redirección a la edición
            return [
                'status' => 'success',
                'message' => 'Minuta iniciada correctamente.',
                'idMinuta' => $newIdMinuta // Necesario para redirigir
            ];
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error DB iniciarMinuta: " . $e->getMessage());
            return ['status' => 'error', 'message' => 'Error al iniciar la minuta: ' . $e->getMessage()];
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

    /**
     * Obtiene los datos de una reunión específica para el formulario de edición.
     */
    public function getReunionById($id)
    {
        // Asegúrate de seleccionar todas las columnas que tu formulario necesita
        $sql = "SELECT
                    idReunion,
                    nombreReunion,
                    fechaInicioReunion,
                    fechaTerminoReunion,
                    t_comision_idComision,
                    t_comision_idComision_mixta,
                    t_comision_idComision_mixta2
                    /* Si agregaste 'numeroReunion' a la DB, añádelo aquí */
                FROM t_reunion
                WHERE idReunion = :id AND vigente = 1";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':id' => $id]);
            $reunion = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($reunion) {
                // Éxito: devuelve los datos de la reunión
                return ['status' => 'success', 'data' => $reunion];
            } else {
                // No se encontró la reunión
                return ['status' => 'error', 'message' => 'Reunión no encontrada o no está vigente.'];
            }
        } catch (PDOException $e) {
            error_log("Error DB getReunionById: " . $e->getMessage());
            return ['status' => 'error', 'message' => 'Error al obtener los datos de la reunión.'];
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
$homeRedirectUrl = '/corevota/views/pages/menu.php?pagina=home';
$listRedirectUrl = '/corevota/views/pages/menu.php?pagina=reunion_listado'; // URL para listar (Renombrado de $redirectUrl)
$homeRedirectUrl = '/corevota/views/pages/menu.php?pagina=home'; // URL de bienvenida
$reunionFormUrl = '/corevota/views/pages/menu.php?pagina=reunion_form'; //

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
                header('Location: ' . $homeRedirectUrl);
            } else {
                $_SESSION['error'] = $response['message'];
                // Si falla, volvemos al listado
                header('Location: /corevota/views/pages/menu.php?pagina=reunion_form');
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
                // --- INICIO MODIFICACIÓN ---
                $now = time(); // Obtiene el timestamp actual para la vista
                // --- FIN MODIFICACIÓN ---
                // Carga la vista del listado (las variables $reuniones y $now estarán disponibles)
                include __DIR__ . '/../views/pages/reunion_listado.php';
            } else {
                echo "<div class='alert alert-danger'>Error: " . htmlspecialchars($response['message']) . "</div>";
            }
            // No hay 'exit' ni 'header' aquí

        } elseif ($action === 'delete' && isset($_GET['id'])) {
            // Esto sigue funcionando igual
            $reunionId = (int)$_GET['id'];
            $response = $manager->deleteReunion($reunionId);
            $_SESSION[$response['status']] = $response['message'];

            header('Location: ' . $redirectUrl);
            exit;
        } elseif ($action === 'iniciarMinuta' && isset($_GET['idReunion'])) {
            $reunionId = (int)$_GET['idReunion'];
            $response = $manager->iniciarMinuta($reunionId);

            if ($response['status'] === 'success') {
                $_SESSION['success'] = $response['message'];
                // --- ¡ERROR AQUÍ! ---
                // Esta línea te manda a la bienvenida incorrectamente:
                // header('Location: ' . $homeRedirectUrl);
                // --- CORRECCIÓN ---
                // Debe redirigir a editar_minuta con el ID de la nueva minuta:
                header('Location: /corevota/views/pages/menu.php?pagina=editar_minuta&id=' . $response['idMinuta']);
            } else {
                $_SESSION['error'] = $response['message'];
                // Si falla, volver al listado
                header('Location: ' . $listRedirectUrl);
            }
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
    header('Location: ' . $listRedirectUrl);
    exit;
}
