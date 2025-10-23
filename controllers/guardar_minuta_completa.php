<?php
// controllers/guardar_minuta_completa.php

require_once __DIR__ . '/../cfg/config.php';
require_once __DIR__ . '/../class/class.conectorDB.php';
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Recuperamos SOLO el ID de la minuta
$idMinuta = $data['minuta']['idMinuta'] ?? null;

// Validar que tengamos ID, asistencia y temas
if (!$idMinuta || !is_numeric($idMinuta) || !isset($data['asistencia']) || !isset($data['temas'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Faltan datos requeridos o ID de minuta inválido.']);
    exit;
}

// $datosMinuta = $data['minuta']; // Ya no necesitamos esto
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

    // Modificado para recibir solo ID, asistencia y temas
    public function guardarMinutaCompleta($idMinuta, $asistenciaIDs, $temasData)
    {
        try {
            $this->db->beginTransaction();

            // --- 1. ACTUALIZAR MINUTA (t_minuta) ---
            // YA NO ES NECESARIO ACTUALIZAR EL ENCABEZADO AL GUARDAR BORRADOR
            /* // EL SIGUIENTE BLOQUE SE ELIMINA:
            if ($idMinuta) { // ACTUALIZAR
                 // ... (Código UPDATE t_minuta eliminado) ...
             } else { // INSERTAR NUEVA - Esta lógica no debería estar aquí ahora
                 throw new Exception("Intento de guardar sin un ID de Minuta válido.");
             }
            */

            // --- 2. ACTUALIZAR ASISTENCIA (t_asistencia) ---
            // Borramos la asistencia ANTERIOR de esta minuta
            $sqlDeleteAsistencia = "DELETE FROM t_asistencia WHERE t_minuta_idMinuta = :idMinuta";
            $stmtDeleteAsistencia = $this->db->prepare($sqlDeleteAsistencia);
            $stmtDeleteAsistencia->execute([':idMinuta' => $idMinuta]);

            // Insertamos la asistencia ACTUAL (los IDs que llegaron del form)
            $idTipoReunion = 1; // Asumido
            if (!empty($asistenciaIDs)) {
                $sqlAsistencia = "INSERT INTO t_asistencia (t_minuta_idMinuta, t_usuario_idUsuario, t_tipoReunion_idTipoReunion, fechaRegistroAsistencia)
                                  VALUES (:idMinuta, :idUsuario, :idTipoReunion, NOW())";
                $stmtAsistencia = $this->db->prepare($sqlAsistencia);
                foreach ($asistenciaIDs as $idUsuario) {
                    if (is_numeric($idUsuario)) { // Validar
                        $stmtAsistencia->execute([
                            ':idMinuta' => $idMinuta,
                            ':idUsuario' => $idUsuario,
                            ':idTipoReunion' => $idTipoReunion
                        ]);
                    } else {
                        error_log("Warning: ID de asistencia no válido ignorado para minuta {$idMinuta}: " . print_r($idUsuario, true));
                    }
                }
            }

            // --- 3. ACTUALIZAR TEMAS Y ACUERDOS (t_tema y t_acuerdo) ---
            // Obtenemos los IDs de los temas que SÍ vienen en los datos actuales
            $idsTemasActuales = [];
            foreach ($temasData as $tema) {
                if (!empty($tema['idTema']) && is_numeric($tema['idTema'])) {
                    $idsTemasActuales[] = $tema['idTema'];
                }
            }

            // --- INICIO CORRECCIÓN DELETE ---
            // A. Obtener IDs de temas a borrar (los que están en DB pero NO en $idsTemasActuales)
            $sqlTemasEnDB = "SELECT idTema FROM t_tema WHERE t_minuta_idMinuta = :idMinuta";
            $stmtTemasEnDB = $this->db->prepare($sqlTemasEnDB);
            $stmtTemasEnDB->execute([':idMinuta' => $idMinuta]);
            $idsTemasEnDB = $stmtTemasEnDB->fetchAll(PDO::FETCH_COLUMN, 0);

            $idsTemasABorrar = array_diff($idsTemasEnDB, $idsTemasActuales);

            if (!empty($idsTemasABorrar)) {
                $placeholdersBorrar = implode(',', array_fill(0, count($idsTemasABorrar), '?'));

                // B. BORRAR PRIMERO los acuerdos asociados a los temas que se van a eliminar
                $sqlDeleteAcuerdos = "DELETE FROM t_acuerdo WHERE t_tema_idTema IN ($placeholdersBorrar)";
                $stmtDeleteAcuerdos = $this->db->prepare($sqlDeleteAcuerdos);
                $stmtDeleteAcuerdos->execute($idsTemasABorrar);

                // C. BORRAR AHORA los temas
                $sqlDeleteTemas = "DELETE FROM t_tema WHERE idTema IN ($placeholdersBorrar) AND t_minuta_idMinuta = ?"; // Doble check con idMinuta
                $paramsBorrarTemas = array_merge($idsTemasABorrar, [$idMinuta]);
                $stmtDeleteTemas = $this->db->prepare($sqlDeleteTemas);
                $stmtDeleteTemas->execute($paramsBorrarTemas);
            }
            // --- FIN CORRECCIÓN DELETE ---


            // Preparamos las consultas para insertar/actualizar temas y acuerdos (sin cambios)
            $sqlInsertTema = "INSERT INTO t_tema (t_minuta_idMinuta, nombreTema, objetivo, compromiso, observacion) VALUES (:idMinuta, :nombre, :objetivo, :compromiso, :observacion)";
            $sqlUpdateTema = "UPDATE t_tema SET nombreTema = :nombre, objetivo = :objetivo, compromiso = :compromiso, observacion = :observacion WHERE idTema = :idTema AND t_minuta_idMinuta = :idMinuta";
            $sqlUpsertAcuerdo = "INSERT INTO t_acuerdo (descAcuerdo, t_tipoReunion_idTipoReunion, t_tema_idTema) 
                                 VALUES (:descAcuerdo, :idTipoReunion, :idTema)
                                 ON DUPLICATE KEY UPDATE descAcuerdo = VALUES(descAcuerdo)";

            $stmtInsertTema = $this->db->prepare($sqlInsertTema);
            $stmtUpdateTema = $this->db->prepare($sqlUpdateTema);
            $stmtUpsertAcuerdo = $this->db->prepare($sqlUpsertAcuerdo);

            // Procesar temas enviados (sin cambios)
            foreach ($temasData as $tema) {
                $idTema = $tema['idTema'] ?? null;
                $paramsTema = [
                    ':idMinuta' => $idMinuta,
                    ':nombre' => trim($tema['nombreTema'] ?? ''),
                    ':objetivo' => trim($tema['objetivo'] ?? ''),
                    ':compromiso' => trim($tema['compromiso'] ?? ''),
                    ':observacion' => trim($tema['observacion'] ?? '')
                ];
                if (empty($paramsTema[':nombre']) && empty($paramsTema[':objetivo'])) {
                    continue; // Saltar temas totalmente vacíos
                }

                if ($idTema && in_array($idTema, $idsTemasActuales)) { // ACTUALIZAR
                    $paramsTema[':idTema'] = $idTema;
                    $stmtUpdateTema->execute($paramsTema);
                } else { // INSERTAR
                    $stmtInsertTema->execute($paramsTema);
                    $idTema = $this->db->lastInsertId(); // Obtenemos el nuevo ID
                }

                // Insertar/Actualizar acuerdo
                $descAcuerdo = trim($tema['descAcuerdo'] ?? '');
                if ($idTema && !empty($descAcuerdo)) {
                    $stmtUpsertAcuerdo->execute([
                        ':descAcuerdo' => $descAcuerdo,
                        ':idTipoReunion' => $idTipoReunion,
                        ':idTema' => $idTema
                    ]);
                }
                // Considerar borrar acuerdo si descAcuerdo está vacío
                else if ($idTema && empty($descAcuerdo)) {
                    $sqlDeleteAcuerdo = "DELETE FROM t_acuerdo WHERE t_tema_idTema = :idTema";
                    $stmtDelAc = $this->db->prepare($sqlDeleteAcuerdo);
                    $stmtDelAc->execute([':idTema' => $idTema]);
                }
            }

            // --- 4. ACTUALIZAR HORA DE TÉRMINO DE LA REUNIÓN --- (Sin cambios)
            $sql_find_reunion = "SELECT idReunion FROM t_reunion WHERE t_minuta_idMinuta = :idMinuta LIMIT 1";
            $stmt_find = $this->db->prepare($sql_find_reunion);
            $stmt_find->execute([':idMinuta' => $idMinuta]);
            $reunion = $stmt_find->fetch(PDO::FETCH_ASSOC);
            $mensajeExito = 'Minuta guardada con éxito.'; // Mensaje base

            if ($reunion) {
                $idReunion = $reunion['idReunion'];
                $sql_update_termino = "UPDATE t_reunion SET fechaTerminoReunion = NOW() WHERE idReunion = :idReunion";
                $stmt_update = $this->db->prepare($sql_update_termino);
                $stmt_update->execute([':idReunion' => $idReunion]);
                $mensajeExito = 'Minuta guardada y hora de término de reunión actualizada.';
            } else {
                error_log("Warning: No se encontró reunión asociada a idMinuta {$idMinuta} para actualizar fechaTerminoReunion.");
                // $mensajeExito = 'Minuta guardada (pero no se encontró reunión asociada para actualizar hora de término).'; // Opcional: informar al usuario
            }

            // --- 5. COMMIT ---
            $this->db->commit();
            return ['status' => 'success', 'message' => $mensajeExito, 'idMinuta' => $idMinuta];
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("Error en guardarMinutaCompleta para idMinuta {$idMinuta}: " . $e->getMessage());
            // Devolvemos el mensaje SQL real para depuración, pero podrías cambiarlo
            return ['status' => 'error', 'message' => 'Ocurrió un error al guardar los datos.', 'error' => $e->getMessage()];
        } finally {
            // Asegurar que la conexión se cierre si se usa 'finally'
            $this->db = null;
        }
    }
}

// --- Ejecución ---
try {
    // Verificar que $idMinuta no sea null antes de instanciar
    if ($idMinuta === null) {
        throw new Exception("ID de Minuta no proporcionado en la solicitud.");
    }

    $manager = new MinutaManager();
    // Pasar solo los parámetros necesarios
    $result = $manager->guardarMinutaCompleta($idMinuta, $asistenciaIDs, $temasData);

    if ($result['status'] === 'error') {
        // Usar 400 para errores de lógica/datos, 500 para excepciones inesperadas
        http_response_code(isset($result['error']) ? 500 : 400);
    }

    echo json_encode($result);
} catch (Exception $e) {
    http_response_code(500);
    error_log("Error fatal en guardar_minuta_completa.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Error interno del servidor.']);
}
