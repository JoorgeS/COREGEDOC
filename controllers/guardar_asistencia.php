<?php
// controllers/guardar_asistencia.php

require_once __DIR__ . '/../cfg/config.php';
header('Content-Type: application/json');

$input = file_get_contents('php://input');
$data = json_decode($input, true);

$idMinuta = $data['idMinuta'] ?? null; // ID existente (si viene)
$asistenciaIDs = $data['asistencia'] ?? null; // IDs presentes
$minutaHeader = $data['minutaHeader'] ?? null; // Datos encabezado (si es nueva)

$newMinutaId = null; // Para devolver el ID si se crea una nueva

// Validar datos básicos
if (!is_array($asistenciaIDs) || (is_null($idMinuta) && is_null($minutaHeader))) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Faltan datos requeridos (asistencia, y idMinuta o minutaHeader).']);
    exit;
}

// Validar datos del encabezado si es una minuta nueva
if (is_null($idMinuta) && $minutaHeader) {
    if (empty($minutaHeader['t_comision_idComision']) || empty($minutaHeader['t_usuario_idPresidente']) || empty($minutaHeader['horaMinuta']) || empty($minutaHeader['fechaMinuta'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Faltan datos del encabezado para crear la minuta (Comisión, Presidente, Hora, Fecha).']);
        exit;
    }
}


class AsistenciaManager extends BaseConexion
{
    private $db;

    public function __construct()
    {
        $this->db = $this->conectar();
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function actualizarAsistencia($idMinuta, $asistenciaIDs, $minutaHeader)
    {
        $newMinutaId = null;
        try {
            $this->db->beginTransaction();

            // --- 1. CREAR MINUTA SI NO EXISTE ---
            if (is_null($idMinuta) && $minutaHeader) {
                // Crear la minuta con los datos mínimos del encabezado
                $sqlInsertMinuta = "INSERT INTO t_minuta (
                                        pathArchivo, t_comision_idComision, t_usuario_idPresidente,
                                        horaMinuta, fechaMinuta, estadoMinuta
                                        /* Añade aquí NULLs o valores por defecto para otras columnas NOT NULL si las tienes */
                                    ) VALUES (
                                        :path, :comision_id, :presidente_id,
                                        :hora, :fecha, 'PENDIENTE'
                                        /* Añade NULLs correspondientes */
                                    )";
                $stmtInsertMinuta = $this->db->prepare($sqlInsertMinuta);
                $stmtInsertMinuta->execute([
                    ':path' => "",
                    ':comision_id' => $minutaHeader['t_comision_idComision'],
                    ':presidente_id' => $minutaHeader['t_usuario_idPresidente'],
                    ':hora' => $minutaHeader['horaMinuta'],
                    ':fecha' => $minutaHeader['fechaMinuta']
                ]);
                $idMinuta = $this->db->lastInsertId(); // Obtenemos el nuevo ID
                $newMinutaId = $idMinuta; // Guardamos para devolverlo al frontend

                if (!$idMinuta) throw new Exception("No se pudo crear la minuta inicial.");
            }

            // Si $idMinuta sigue siendo null aquí, algo falló
            if (is_null($idMinuta)) {
                throw new Exception("ID de Minuta inválido después de posible creación.");
            }


            // --- 2. ACTUALIZAR ASISTENCIA (Borrar e Insertar) ---
            // Borramos la asistencia ANTERIOR de esta minuta
            $sqlDelete = "DELETE FROM t_asistencia WHERE t_minuta_idMinuta = :idMinuta";
            $stmtDelete = $this->db->prepare($sqlDelete);
            $stmtDelete->execute([':idMinuta' => $idMinuta]);

            // Insertamos la nueva lista de asistentes (solo los presentes)
            $idTipoReunion = 1; // Asumido
            $sqlInsert = "INSERT INTO t_asistencia (t_minuta_idMinuta, t_usuario_idUsuario, t_tipoReunion_idTipoReunion)
                          VALUES (:idMinuta, :idUsuario, :idTipoReunion)";
            $stmtInsert = $this->db->prepare($sqlInsert);

            foreach ($asistenciaIDs as $idUsuario) {
                if (filter_var($idUsuario, FILTER_VALIDATE_INT)) {
                    $stmtInsert->execute([
                        ':idMinuta' => $idMinuta,
                        ':idUsuario' => (int)$idUsuario,
                        ':idTipoReunion' => $idTipoReunion
                    ]);
                }
            }

            // --- 3. COMMIT ---
            $this->db->commit();

            // Devolvemos el nuevo ID si se creó una minuta
            $response = ['status' => 'success', 'message' => 'Asistencia actualizada.'];
            if ($newMinutaId) {
                $response['newMinutaId'] = $newMinutaId;
            }
            return $response;
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("Error al guardar asistencia para minuta $idMinuta: " . $e->getMessage());
            return ['status' => 'error', 'message' => 'Error en la base de datos.', 'error' => $e->getMessage()];
        }
    }
}

// --- Ejecución ---
try {
    $manager = new AsistenciaManager();
    // Pasamos los tres posibles datos a la función
    $result = $manager->actualizarAsistencia($idMinuta, $asistenciaIDs, $minutaHeader);
    echo json_encode($result);
} catch (Exception $e) {
    http_response_code(500);
    error_log("Error fatal en guardar_asistencia.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Error interno del servidor.']);
}
