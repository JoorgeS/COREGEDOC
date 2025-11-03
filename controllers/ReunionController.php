<?php
// controllers/ReunionController.php

// Dependencias
require_once __DIR__ . '/../cfg/config.php';
require_once __DIR__ . '/../class/class.conectorDB.php';
require_once __DIR__ . '/../models/minutaModel.php'; //

class ReunionManager extends BaseConexion
{
    private $db;

    public function __construct()
    {
        $this->db = $this->conectar();
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /**
     * Guarda SOLO la reunión (estado "Programada").
     */
    public function storeReunion($data)
    {
        // 1. Validar datos de entrada
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

        try {
            // Crear la REUNIÓN SIN vincular minuta (t_minuta_idMinuta = NULL)
            $sql_reunion = "INSERT INTO t_reunion (
                    nombreReunion, fechaInicioReunion, fechaTerminoReunion,
                    vigente, t_comision_idComision,
                    t_comision_idComision_mixta,
                    t_comision_idComision_mixta2,
                    t_minuta_idMinuta
                ) VALUES (
                    :nombre, :inicio, :termino, 1, :comisionId,
                    :comisionIdMixta,
                    :comisionIdMixta2,
                    NULL
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
            ];
        } catch (Exception $e) {
            error_log("Error DB storeReunion: " . $e->getMessage());
            if (strpos($e->getMessage(), 'foreign key constraint fails') !== false) {
                return ['status' => 'error', 'message' => 'Error: Una de las comisiones seleccionadas no es válida.'];
            }
            return ['status' => 'error', 'message' => 'Error al crear la reunión: Verifique los datos. Detalles: ' . $e->getMessage()];
        }
    }

    /**
     * Actualiza una reunión existente.
     */
    public function updateReunion($idReunion, $data)
    {
        // 1. Validar datos de entrada (similar a storeReunion)
        $comisionId = $data['t_comision_idComision'] ?? null;
        $nombreReunion = $data['nombreReunion'] ?? 'Reunión sin nombre';
        $fechaInicio = $data['fechaInicioReunion'] ?? null;
        $fechaTermino = $data['fechaTerminoReunion'] ?? null;
        $esMixta = isset($data['comisionMixta']) && $data['comisionMixta'] == '1';
        $comisionIdMixta = $esMixta ? ($data['t_comision_idComision_mixta'] ?? null) : null;
        $comisionIdMixta2 = $esMixta && !empty($data['t_comision_idComision_mixta2']) ? $data['t_comision_idComision_mixta2'] : null;

        if (!$idReunion || !$comisionId || !$fechaInicio || !$fechaTermino) {
            return ['status' => 'error', 'message' => 'Faltan datos obligatorios (ID, Comisión, Inicio o Término).'];
        }
        if ($esMixta && !$comisionIdMixta) {
            return ['status' => 'error', 'message' => 'Marcó Comisión Mixta pero no seleccionó la segunda comisión.'];
        }
        $selectedComisiones = array_filter([$comisionId, $comisionIdMixta, $comisionIdMixta2]);
        if (count($selectedComisiones) !== count(array_unique($selectedComisiones))) {
            return ['status' => 'error', 'message' => 'Las comisiones seleccionadas no pueden repetirse.'];
        }

        try {
            $sql_update = "UPDATE t_reunion SET
                            nombreReunion = :nombre,
                            fechaInicioReunion = :inicio,
                            fechaTerminoReunion = :termino,
                            t_comision_idComision = :comisionId,
                            t_comision_idComision_mixta = :comisionIdMixta,
                            t_comision_idComision_mixta2 = :comisionIdMixta2
                        WHERE idReunion = :idReunion 
                          AND t_minuta_idMinuta IS NULL"; // Seguridad: Solo actualizar si no ha iniciado

            $stmt_update = $this->db->prepare($sql_update);
            $success = $stmt_update->execute([
                ':nombre' => $nombreReunion,
                ':inicio' => $fechaInicio,
                ':termino' => $fechaTermino,
                ':comisionId' => $comisionId,
                ':comisionIdMixta' => $comisionIdMixta,
                ':comisionIdMixta2' => $comisionIdMixta2,
                ':idReunion' => $idReunion
            ]);

            if ($success && $stmt_update->rowCount() > 0) {
                return [
                    'status' => 'success',
                    'message' => 'Reunión actualizada exitosamente.'
                ];
            } else if ($success && $stmt_update->rowCount() === 0) {
                return ['status' => 'error', 'message' => 'No se pudo actualizar la reunión (quizás ya fue iniciada o no se detectaron cambios).'];
            } else {
                throw new Exception("Error al ejecutar la actualización.");
            }
        } catch (Exception $e) {
            error_log("Error DB updateReunion: " . $e->getMessage());
            return ['status' => 'error', 'message' => 'Error al actualizar la reunión. Detalles: ' . $e->getMessage()];
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

    /**
     * Crea la minuta para una reunión existente y la vincula.
     */
    public function iniciarMinuta($idReunion, $idSecretarioLogueado)
    {
        $idReunion = (int)$idReunion;
        if ($idReunion <= 0) {
            return ['status' => 'error', 'message' => 'ID de reunión inválido.'];
        }

        if ($idSecretarioLogueado <= 0) {
            return ['status' => 'error', 'message' => 'ID de secretario inválido. Su sesión puede haber expirado.'];
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

            // (Opcional) Verificar si ya pasó la hora de inicio
            $meetingStartTime = strtotime($reunionData['fechaInicioReunion']);
            if (time() < $meetingStartTime) {
                // Permitir un margen de 5 minutos antes
                if (time() < ($meetingStartTime - 300)) {
                    throw new Exception('Aún no es hora de iniciar esta reunión.');
                }
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
                                    horaMinuta, fechaMinuta, pathArchivo,
                                    t_usuario_idSecretario
                                ) VALUES (
                                    :comisionId, :presidenteId, 'BORRADOR',
                                    :horaMinuta, :fechaMinuta, '',
                                    :idSecretario
                                )";
            $stmt_minuta = $this->db->prepare($sql_minuta);
            $stmt_minuta->execute([
                ':comisionId' => $comisionId,
                ':presidenteId' => $presidenteId,
                ':horaMinuta' => $horaMinuta,
                ':fechaMinuta' => $fechaMinuta,
                ':idSecretario' => $idSecretarioLogueado


            ]);

            $newIdMinuta = $this->db->lastInsertId();
            if (!$newIdMinuta) {
                throw new Exception('No se pudo crear la minuta.');
            }

            // 4. Actualizar la REUNIÓN para vincular la minuta creada
            // ... (dentro de iniciarMinuta)
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

            // --- INICIO DE CÓDIGO DE LOG ---
            // Usamos el $idSecretarioLogueado que ya estaba disponible en esta función

            $this->db->commit();
            try {
                $minutaModel = new MinutaModel($this->db);
                $minutaModel->logAccion(
                    $newIdMinuta, // El ID de la minuta que se acaba de crear
                    $idSecretarioLogueado,
                    'CREADA',
                    'Minuta creada en estado BORRADOR por Secretario Técnico.'
                );
            } catch (Exception $logException) {
                // Si el log falla, no queremos detener la creación de la minuta.
                // Solo registramos el error de log.
                error_log("Error al registrar log 'CREADA' para minuta " . $newIdMinuta . ": " . $logException->getMessage());
            }
            // --- FIN DE CÓDIGO DE LOG ---

           

            return [
                // ...
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
        $sql = "UPDATE t_reunion SET vigente = 0 WHERE idReunion = :id AND t_minuta_idMinuta IS NULL";
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':id' => $id]);

            if ($stmt->rowCount() > 0) {
                return ['status' => 'success', 'message' => 'Reunión deshabilitada.'];
            } else {
                return ['status' => 'error', 'message' => 'No se pudo deshabilitar. Es posible que ya esté iniciada.'];
            }
        } catch (PDOException $e) {
            error_log("Error DB deleteReunion: " . $e->getMessage());
            return ['status' => 'error', 'message' => 'Error al deshabilitar.'];
        }
    }

    /**
     * Obtiene los datos de una reunión específica para el formulario de edición.
     * SOLO si aún no ha sido iniciada.
     */
    public function getReunionById($id)
    {
        $sql = "SELECT
                    idReunion,
                    nombreReunion,
                    fechaInicioReunion,
                    fechaTerminoReunion,
                    t_comision_idComision,
                    t_comision_idComision_mixta,
                    t_comision_idComision_mixta2
                FROM t_reunion
                WHERE idReunion = :id 
                  AND vigente = 1 
                  AND t_minuta_idMinuta IS NULL"; // Seguridad: Solo permite editar si NO ha iniciado

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':id' => $id]);
            $reunion = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($reunion) {
                return ['status' => 'success', 'data' => $reunion];
            } else {
                return ['status' => 'error', 'message' => 'Reunión no encontrada o ya no se puede editar (posiblemente ya fue iniciada).'];
            }
        } catch (PDOException $e) {
            error_log("Error DB getReunionById: " . $e->getMessage());
            return ['status' => 'error', 'message' => 'Error al obtener los datos de la reunión.'];
        }
    }

    /**
     * NUEVO: Datos para el calendario (reuniones programadas y vigentes).
     * No altera ningún flujo existente.
     */
    public function getReunionesCalendarData(): array
    {
        try {
            $sql = "
                SELECT
                    r.idReunion,
                    r.nombreReunion,
                    r.fechaInicioReunion,
                    r.fechaTerminoReunion,
                    r.t_comision_idComision,
                    c.nombreComision
                FROM t_reunion r
                LEFT JOIN t_comision c
                       ON c.idComision = r.t_comision_idComision
                WHERE COALESCE(r.vigente, 1) = 1
                ORDER BY r.fechaInicioReunion ASC, r.idReunion ASC
            ";

            $stmt = $this->db->query($sql);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            // Normalizar a ISO 8601 (YYYY-MM-DDTHH:mm:ss)
            foreach ($rows as &$r) {
                if (!empty($r['fechaInicioReunion'])) {
                    $r['fechaInicioReunion']  = date('Y-m-d\TH:i:s', strtotime($r['fechaInicioReunion']));
                }
                if (!empty($r['fechaTerminoReunion'])) {
                    $r['fechaTerminoReunion'] = date('Y-m-d\TH:i:s', strtotime($r['fechaTerminoReunion']));
                }
            }
            unset($r);

            return ['status' => 'success', 'data' => $rows];
        } catch (Throwable $e) {
            error_log('[getReunionesCalendarData] ' . $e->getMessage());
            return ['status' => 'error', 'message' => 'No fue posible cargar el calendario', 'data' => []];
        }
    }
}

// --- Enrutamiento ---
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$action = $_POST['action'] ?? $_GET['action'] ?? null;
$manager = new ReunionManager();

$listRedirectUrl = '/corevota/views/pages/menu.php?pagina=reunion_listado';
$homeRedirectUrl = '/corevota/views/pages/menu.php?pagina=home';
$reunionFormUrl = '/corevota/views/pages/menu.php?pagina=reunion_form'; // Crear

try {
    // --- ACCIONES POST (Formularios) ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        if ($action === 'store_reunion') {
            $data = $_POST;
            $response = $manager->storeReunion($data);

            if ($response['status'] === 'success') {
                $_SESSION['success'] = $response['message'];
                header('Location: ' . $listRedirectUrl); // Corregido: Ir al listado
            } else {
                $_SESSION['error'] = $response['message'];
                // Si falla, volvemos al formulario de creación
                header('Location: ' . $reunionFormUrl);
            }
            exit;
        } elseif ($action === 'update_reunion') {
            $idReunion = $_POST['idReunion'] ?? 0;
            $data = $_POST;
            $response = $manager->updateReunion($idReunion, $data);

            if ($response['status'] === 'success') {
                $_SESSION['success'] = $response['message'];
                header('Location: ' . $listRedirectUrl); // Volver al listado
            } else {
                $_SESSION['error'] = $response['message'];
                // Volver al formulario de edición si hay error
                header('Location: /corevota/views/pages/menu.php?pagina=reunion_editar&id=' . $idReunion);
            }
            exit;
        }

        // --- ACCIONES GET (Enlaces y Carga de Vistas) ---
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {

        if ($action === 'list') {
            // Usado por menu.php para incluir la lista
            $response = $manager->getReunionesList();
            if ($response['status'] === 'success') {
                $reuniones = $response['data'];
                $now = time(); // Obtiene el timestamp actual para la vista
                include __DIR__ . '/../views/pages/reunion_listado.php';
            } else {
                echo "<div class='alert alert-danger'>Error: " . htmlspecialchars($response['message']) . "</div>";
            }
            // No hay 'exit' ni 'header' aquí

        } elseif ($action === 'edit' && isset($_GET['id'])) {
            // Usado por menu.php para incluir el formulario de edición
            $reunionId = (int)$_GET['id'];
            $response = $manager->getReunionById($reunionId);

            if ($response['status'] === 'success') {
                // Inyecta los datos de la reunión en la vista
                $reunion_data = $response['data'];
                // Carga la vista del formulario (que ahora sabe qué hacer con $reunion_data)
                include __DIR__ . '/../views/pages/reunion_form.php';
            } else {
                // Si no se encuentra o hay error, redirigir al listado con error
                $_SESSION['error'] = $response['message'];
                header('Location: ' . $listRedirectUrl);
                exit;
            }
            // No hay 'exit' ni 'header' aquí si tiene éxito

        } elseif ($action === 'iniciarMinuta' && isset($_GET['idReunion'])) {
            $reunionId = (int)$_GET['idReunion'];

            // --- ¡NUEVO! Capturamos el ID del Secretario ---
            $idSecretarioLogueado = $_SESSION['idUsuario'] ?? 0;
            if ($idSecretarioLogueado == 0) {
                $_SESSION['error'] = 'Su sesión ha expirado. Por favor, ingrese de nuevo.';
                header('Location: ' . $listRedirectUrl);
                exit;
            }
            // --- FIN NUEVO ---

            // --- ¡MODIFICADO! Pasamos el ID a la función ---
            $response = $manager->iniciarMinuta($reunionId, $idSecretarioLogueado);

            if ($response['status'] === 'success') {
                $_SESSION['success'] = $response['message'];
                // Redirigir a editar_minuta con el ID de la nueva minuta:
                header('Location: /corevota/views/pages/menu.php?pagina=editar_minuta&id=' . $response['idMinuta']);
            } else {
                $_SESSION['error'] = $response['message'];
                header('Location: ' . $listRedirectUrl);
            }
            exit;
        }
    }
} catch (Exception $e) {
    $_SESSION['error'] = 'Error inesperado del controlador.';
    error_log("Error Controller: " . $e->getMessage());
    header('Location: ' . $listRedirectUrl);
    exit;
}
