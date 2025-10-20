<?php
// controllers/guardar_minuta_completa.php

require_once __DIR__ . '/../cfg/config.php';
header('Content-Type: application/json');

$input = file_get_contents('php://input');
$data = json_decode($input, true);

// ❗️ Recuperamos el ID de la minuta (si existe, es una actualización)
$idMinuta = $data['minuta']['idMinuta'] ?? null; // Asumiendo que el JS lo envía

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
                                -- Faltaría lógica para comisión mixta si la guardas aquí
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
                $sqlMinuta = "INSERT INTO t_minuta (pathArchivo, t_comision_idComision, t_usuario_idPresidente, horaMinuta, fechaMinuta /* ... más columnas si son necesarias ... */)
                              VALUES (:path, :comision_id, :presidente_id, :hora, :fecha /* ... más NULLs ... */)";
                // Asegúrate que las columnas y valores coincidan como antes
                $stmtMinuta = $this->db->prepare($sqlMinuta);
                $stmtMinuta->execute([
                    ':path' => "", // Path vacío al crear
                    ':comision_id' => $datosMinuta['t_comision_idComision'],
                    ':presidente_id' => $datosMinuta['t_usuario_idPresidente'],
                    ':hora' => $datosMinuta['horaMinuta'],
                    ':fecha' => $datosMinuta['fechaMinuta']
                    // Añadir NULLs para las otras columnas si tu INSERT lo requiere
                ]);
                $idMinuta = $this->db->lastInsertId(); // Obtenemos el nuevo ID
            }

            if (!$idMinuta) throw new Exception("Error con ID de Minuta.");

            // --- 2. ACTUALIZAR ASISTENCIA (t_asistencia) ---
            // Borramos la asistencia ANTERIOR de esta minuta
            $sqlDeleteAsistencia = "DELETE FROM t_asistencia WHERE t_minuta_idMinuta = :idMinuta";
            $stmtDeleteAsistencia = $this->db->prepare($sqlDeleteAsistencia);
            $stmtDeleteAsistencia->execute([':idMinuta' => $idMinuta]);

            // Insertamos la asistencia ACTUAL (los IDs que llegaron del form)
            $idTipoReunion = 1; // Asumido
            $sqlAsistencia = "INSERT INTO t_asistencia (t_minuta_idMinuta, t_usuario_idUsuario, t_tipoReunion_idTipoReunion)
                              VALUES (:idMinuta, :idUsuario, :idTipoReunion)";
            $stmtAsistencia = $this->db->prepare($sqlAsistencia);
            foreach ($asistenciaIDs as $idUsuario) {
                $stmtAsistencia->execute([
                    ':idMinuta' => $idMinuta,
                    ':idUsuario' => $idUsuario,
                    ':idTipoReunion' => $idTipoReunion
                ]);
            }

            // --- 3. ACTUALIZAR TEMAS (t_tema y t_acuerdo) ---
            // Necesitamos saber qué temas borrar (los que estaban antes pero ya no vienen)
            $idsTemasActuales = array_filter(array_column($temasData, 'idTema')); // IDs de temas que SÍ vienen del form

            if (!empty($idsTemasActuales)) {
                // Borramos los temas asociados a la minuta que NO están en la lista actual
                $placeholders = implode(',', array_fill(0, count($idsTemasActuales), '?'));
                $sqlDeleteTemas = "DELETE FROM t_tema WHERE t_minuta_idMinuta = ? AND idTema NOT IN ($placeholders)";
                $params = array_merge([$idMinuta], $idsTemasActuales);
                $stmtDeleteTemas = $this->db->prepare($sqlDeleteTemas);
                $stmtDeleteTemas->execute($params);
            } else {
                // Si no viene ningún tema con ID, borramos TODOS los temas anteriores de esa minuta
                $sqlDeleteTemas = "DELETE FROM t_tema WHERE t_minuta_idMinuta = ?";
                $stmtDeleteTemas = $this->db->prepare($sqlDeleteTemas);
                $stmtDeleteTemas->execute([$idMinuta]);
            }


            // Preparamos las consultas para insertar/actualizar temas y acuerdos
            $sqlInsertTema = "INSERT INTO t_tema (t_minuta_idMinuta, nombreTema, objetivo, compromiso, observacion) VALUES (:idMinuta, :nombre, :objetivo, :compromiso, :observacion)";
            $sqlUpdateTema = "UPDATE t_tema SET nombreTema = :nombre, objetivo = :objetivo, compromiso = :compromiso, observacion = :observacion WHERE idTema = :idTema AND t_minuta_idMinuta = :idMinuta";
            // Asumiendo que t_acuerdo tiene una relación única con t_tema
            $sqlInsertAcuerdo = "INSERT INTO t_acuerdo (descAcuerdo, t_tipoReunion_idTipoReunion, t_tema_idTema) VALUES (:descAcuerdo, :idTipoReunion, :idTema)";
            $sqlUpdateAcuerdo = "UPDATE t_acuerdo SET descAcuerdo = :descAcuerdo WHERE t_tema_idTema = :idTema"; // Simple, podría necesitar más lógica

            $stmtInsertTema = $this->db->prepare($sqlInsertTema);
            $stmtUpdateTema = $this->db->prepare($sqlUpdateTema);
            $stmtInsertAcuerdo = $this->db->prepare($sqlInsertAcuerdo);
            $stmtUpdateAcuerdo = $this->db->prepare($sqlUpdateAcuerdo);

            foreach ($temasData as $tema) {
                $idTema = $tema['idTema'] ?? null; // ID del tema que viene del form
                $paramsTema = [
                    ':idMinuta' => $idMinuta,
                    ':nombre' => $tema['nombreTema'],
                    ':objetivo' => $tema['objetivo'],
                    ':compromiso' => $tema['compromiso'],
                    ':observacion' => $tema['observacion']
                ];

                if ($idTema) { // Si tiene ID, es ACTUALIZAR tema existente
                    $paramsTema[':idTema'] = $idTema;
                    $stmtUpdateTema->execute($paramsTema);

                    // Actualizar acuerdo asociado (simplificado)
                    if (isset($tema['descAcuerdo'])) {
                        $stmtUpdateAcuerdo->execute([':descAcuerdo' => $tema['descAcuerdo'], ':idTema' => $idTema]);
                    }
                } else { // Si no tiene ID, es INSERTAR tema nuevo
                    $stmtInsertTema->execute($paramsTema);
                    $idTema = $this->db->lastInsertId(); // Obtenemos el nuevo ID del tema

                    // Insertar acuerdo asociado
                    if (isset($tema['descAcuerdo']) && !empty($tema['descAcuerdo'])) {
                        $stmtInsertAcuerdo->execute([
                            ':descAcuerdo' => $tema['descAcuerdo'],
                            ':idTipoReunion' => 1, // Asumido
                            ':idTema' => $idTema
                        ]);
                    }
                }
            }

            // --- 4. COMMIT ---
            $this->db->commit();
            return ['status' => 'success', 'message' => 'Minuta guardada con éxito.', 'idMinuta' => $idMinuta];
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return ['status' => 'error', 'message' => 'Error en el proceso de base de datos.', 'error' => $e->getMessage()];
        }
    }
}

// --- Ejecución ---
try {
    $manager = new MinutaManager();
    $result = $manager->guardarMinutaCompleta($idMinuta, $datosMinuta, $asistenciaIDs, $temasData);
    echo json_encode($result);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error interno.', 'error' => $e->getMessage()]);
}
