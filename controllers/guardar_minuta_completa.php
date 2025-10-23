<?php
// controllers/guardar_minuta_completa.php

require_once __DIR__ . '/../cfg/config.php'; // Usa BaseConexion
require_once __DIR__ . '/../class/class.conectorDB.php'; // Asegura que la timezone esté definida si BaseConexion no lo hace
header('Content-Type: application/json');

// Asegurar que la sesión esté iniciada (para logs futuros o verificaciones)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Recuperamos el ID de la minuta (si existe, es una actualización)
$idMinuta = $data['minuta']['idMinuta'] ?? null;

if (!isset($data['minuta']) || !isset($data['asistencia']) || !isset($data['temas'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Faltan datos requeridos.']);
    exit;
}

$datosMinuta = $data['minuta'];
$asistenciaIDs = $data['asistencia']; // IDs de los asistentes MARCADOS ahora
$temasData = $data['temas'];

class MinutaManager extends BaseConexion
{
    private $db;

    public function __construct()
    {
        $this->db = $this->conectar();
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function guardarMinutaCompleta($idMinuta, $datosMinuta, $asistenciaIDs, $temasData)
    {
        try {
            $this->db->beginTransaction();

            // --- 1. GUARDAR/ACTUALIZAR MINUTA (t_minuta) ---
            if ($idMinuta) { // ACTUALIZAR
                $sqlMinuta = "UPDATE t_minuta SET
                                t_comision_idComision = :comision_id,
                                t_usuario_idPresidente = :presidente_id,
                                horaMinuta = :hora,
                                fechaMinuta = :fecha
                              WHERE idMinuta = :idMinuta";
                $stmtMinuta = $this->db->prepare($sqlMinuta);
                $stmtMinuta->execute([
                    ':comision_id' => $datosMinuta['t_comision_idComision'],
                    ':presidente_id' => $datosMinuta['t_usuario_idPresidente'],
                    ':hora' => $datosMinuta['horaMinuta'],
                    ':fecha' => $datosMinuta['fechaMinuta'],
                    ':idMinuta' => $idMinuta
                ]);
            } else { // INSERTAR NUEVA
                // NOTA: Esta lógica asume que la minuta se crea ANTES por ReunionController.
                // Si este script *también* puede crear minutas, la lógica debe ser revisada.
                // Por ahora, asumimos que $idMinuta siempre tendrá un valor aquí si el flujo es correcto.
                throw new Exception("Intento de guardar sin un ID de Minuta válido.");
                /* // Si este script DEBE poder crear la minuta si no existe:
                $sqlMinuta = "INSERT INTO t_minuta (pathArchivo, t_comision_idComision, t_usuario_idPresidente, horaMinuta, fechaMinuta, estadoMinuta)
                              VALUES (:path, :comision_id, :presidente_id, :hora, :fecha, 'PENDIENTE')";
                $stmtMinuta = $this->db->prepare($sqlMinuta);
                $stmtMinuta->execute([
                    ':path' => "",
                    ':comision_id' => $datosMinuta['t_comision_idComision'],
                    ':presidente_id' => $datosMinuta['t_usuario_idPresidente'],
                    ':hora' => $datosMinuta['horaMinuta'],
                    ':fecha' => $datosMinuta['fechaMinuta']
                ]);
                $idMinuta = $this->db->lastInsertId(); 
                */
            }

            if (!$idMinuta) throw new Exception("Error crítico: No se pudo obtener/confirmar el ID de Minuta.");

            // --- 2. ACTUALIZAR ASISTENCIA (t_asistencia) ---
            $sqlDeleteAsistencia = "DELETE FROM t_asistencia WHERE t_minuta_idMinuta = :idMinuta";
            $stmtDeleteAsistencia = $this->db->prepare($sqlDeleteAsistencia);
            $stmtDeleteAsistencia->execute([':idMinuta' => $idMinuta]);

            $idTipoReunion = 1; // Asumido desde tu código de AsistenciaController
            if (!empty($asistenciaIDs)) { // Solo insertamos si hay asistentes marcados
                $sqlAsistencia = "INSERT INTO t_asistencia (t_minuta_idMinuta, t_usuario_idUsuario, t_tipoReunion_idTipoReunion, fechaRegistroAsistencia)
                                  VALUES (:idMinuta, :idUsuario, :idTipoReunion, NOW())"; // Añadido NOW()
                $stmtAsistencia = $this->db->prepare($sqlAsistencia);
                foreach ($asistenciaIDs as $idUsuario) {
                    // Validar que el ID sea numérico antes de insertar
                    if (is_numeric($idUsuario)) {
                        $stmtAsistencia->execute([
                            ':idMinuta' => $idMinuta,
                            ':idUsuario' => $idUsuario,
                            ':idTipoReunion' => $idTipoReunion
                        ]);
                    } else {
                        // Opcional: Registrar un warning si un ID no es válido
                        error_log("Warning: ID de asistencia no válido encontrado para minuta {$idMinuta}: " . print_r($idUsuario, true));
                    }
                }
            }

            // --- 3. ACTUALIZAR TEMAS Y ACUERDOS (t_tema y t_acuerdo) ---
            $idsTemasActuales = array_filter(array_column($temasData, 'idTema'));

            if (!empty($idsTemasActuales)) {
                $placeholders = implode(',', array_fill(0, count($idsTemasActuales), '?'));
                // Asegurarse de que t_acuerdo se borre si el tema se borra (ON DELETE CASCADE ya lo hace)
                $sqlDeleteTemas = "DELETE FROM t_tema WHERE t_minuta_idMinuta = ? AND idTema NOT IN ($placeholders)";
                $params = array_merge([$idMinuta], $idsTemasActuales);
                $stmtDeleteTemas = $this->db->prepare($sqlDeleteTemas);
                $stmtDeleteTemas->execute($params);
            } else {
                // Si no vienen temas con ID, borramos todos los temas de esta minuta
                // ON DELETE CASCADE se encargará de los acuerdos asociados
                $sqlDeleteTemas = "DELETE FROM t_tema WHERE t_minuta_idMinuta = ?";
                $stmtDeleteTemas = $this->db->prepare($sqlDeleteTemas);
                $stmtDeleteTemas->execute([$idMinuta]);
            }

            $sqlInsertTema = "INSERT INTO t_tema (t_minuta_idMinuta, nombreTema, objetivo, compromiso, observacion) VALUES (:idMinuta, :nombre, :objetivo, :compromiso, :observacion)";
            $sqlUpdateTema = "UPDATE t_tema SET nombreTema = :nombre, objetivo = :objetivo, compromiso = :compromiso, observacion = :observacion WHERE idTema = :idTema AND t_minuta_idMinuta = :idMinuta";
            // Usamos INSERT ... ON DUPLICATE KEY UPDATE para simplificar acuerdos (asume que t_tema_idTema es UNIQUE o PK en t_acuerdo)
            $sqlUpsertAcuerdo = "INSERT INTO t_acuerdo (descAcuerdo, t_tipoReunion_idTipoReunion, t_tema_idTema) 
                                 VALUES (:descAcuerdo, :idTipoReunion, :idTema)
                                 ON DUPLICATE KEY UPDATE descAcuerdo = VALUES(descAcuerdo)";

            $stmtInsertTema = $this->db->prepare($sqlInsertTema);
            $stmtUpdateTema = $this->db->prepare($sqlUpdateTema);
            $stmtUpsertAcuerdo = $this->db->prepare($sqlUpsertAcuerdo);

            foreach ($temasData as $tema) {
                $idTema = $tema['idTema'] ?? null;
                $paramsTema = [
                    ':idMinuta' => $idMinuta,
                    ':nombre' => trim($tema['nombreTema'] ?? ''), // Asegurar strings vacíos si no vienen
                    ':objetivo' => trim($tema['objetivo'] ?? ''),
                    ':compromiso' => trim($tema['compromiso'] ?? ''),
                    ':observacion' => trim($tema['observacion'] ?? '')
                ];
                // Validar que al menos nombre y objetivo no estén vacíos antes de guardar
                if (empty($paramsTema[':nombre']) && empty($paramsTema[':objetivo'])) {
                    continue; // Saltar este tema si está completamente vacío
                }


                if ($idTema && in_array($idTema, $idsTemasActuales)) { // Es ACTUALIZAR tema existente
                    $paramsTema[':idTema'] = $idTema;
                    $stmtUpdateTema->execute($paramsTema);
                } else { // Es INSERTAR tema nuevo
                    $stmtInsertTema->execute($paramsTema);
                    $idTema = $this->db->lastInsertId(); // Obtenemos el nuevo ID del tema
                }

                // Insertar o actualizar acuerdo asociado (solo si hay descripción y un idTema válido)
                $descAcuerdo = trim($tema['descAcuerdo'] ?? '');
                if ($idTema && !empty($descAcuerdo)) {
                    $stmtUpsertAcuerdo->execute([
                        ':descAcuerdo' => $descAcuerdo,
                        ':idTipoReunion' => $idTipoReunion, // Asumido
                        ':idTema' => $idTema
                    ]);
                }
                // Opcional: Borrar acuerdo si descAcuerdo está vacío
                /* else if ($idTema && empty($descAcuerdo)) {
                     $sqlDeleteAcuerdo = "DELETE FROM t_acuerdo WHERE t_tema_idTema = :idTema";
                     $stmtDelAc = $this->db->prepare($sqlDeleteAcuerdo);
                     $stmtDelAc->execute([':idTema' => $idTema]);
                 }*/
            }

            // --- 4. ACTUALIZAR HORA DE TÉRMINO DE LA REUNIÓN --- <<-- LÓGICA AÑADIDA AQUÍ
            $sql_find_reunion = "SELECT idReunion FROM t_reunion WHERE t_minuta_idMinuta = :idMinuta LIMIT 1";
            $stmt_find = $this->db->prepare($sql_find_reunion);
            $stmt_find->execute([':idMinuta' => $idMinuta]);
            $reunion = $stmt_find->fetch(PDO::FETCH_ASSOC);

            if ($reunion) {
                $idReunion = $reunion['idReunion'];
                $sql_update_termino = "UPDATE t_reunion SET fechaTerminoReunion = NOW() WHERE idReunion = :idReunion";
                $stmt_update = $this->db->prepare($sql_update_termino);
                $stmt_update->execute([':idReunion' => $idReunion]);
                $mensajeExito = 'Minuta guardada y hora de término de reunión actualizada.';
            } else {
                // Si no se encuentra la reunión, igual guardamos la minuta pero registramos un warning
                error_log("Warning: No se encontró reunión asociada a idMinuta {$idMinuta} para actualizar fechaTerminoReunion.");
                $mensajeExito = 'Minuta guardada (pero no se encontró reunión asociada para actualizar hora de término).';
            }
            // --- FIN LÓGICA AÑADIDA ---


            // --- 5. COMMIT ---
            $this->db->commit();
            return ['status' => 'success', 'message' => $mensajeExito, 'idMinuta' => $idMinuta];
        } catch (Exception $e) {
            // Asegurarse de hacer rollback si algo falla
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            // Registrar el error detallado en el log del servidor
            error_log("Error en guardarMinutaCompleta para idMinuta {$idMinuta}: " . $e->getMessage());
            // Devolver un mensaje genérico al usuario
            return ['status' => 'error', 'message' => 'Ocurrió un error al guardar los datos.', 'error' => $e->getMessage()]; // Devolver e->getMessage() opcionalmente para debug
        }
    }
}

// --- Ejecución ---
try {
    $manager = new MinutaManager();
    $result = $manager->guardarMinutaCompleta($idMinuta, $datosMinuta, $asistenciaIDs, $temasData);

    // Si hubo un error en la lógica de negocio pero no una excepción, devolver código 4xx
    if ($result['status'] === 'error') {
        http_response_code(400); // Bad request (o 500 si fue error de BD)
    }

    echo json_encode($result);
} catch (Exception $e) { // Captura errores en la instanciación o llamadas fuera del método
    http_response_code(500); // Internal Server Error
    error_log("Error fatal en guardar_minuta_completa.php: " . $e->getMessage()); // Log detallado
    echo json_encode(['status' => 'error', 'message' => 'Error interno del servidor.']); // Mensaje genérico
}
