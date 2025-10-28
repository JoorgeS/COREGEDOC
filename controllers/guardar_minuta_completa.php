<?php
// controllers/guardar_minuta_completa.php

require_once __DIR__ . '/../cfg/config.php';
require_once __DIR__ . '/../class/class.conectorDB.php'; // Asegúrate que incluya BaseConexion
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- 1. Recepción de Datos desde $_POST y $_FILES ---
$idMinuta = $_POST['idMinuta'] ?? null;
$asistenciaJson = $_POST['asistencia'] ?? '[]'; // JSON string
$temasJson = $_POST['temas'] ?? '[]';       // JSON string
$enlaceAdjunto = $_POST['enlaceAdjunto'] ?? null;

// Validar ID Minuta
if (!$idMinuta || !is_numeric($idMinuta)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ID de minuta inválido o faltante.']);
    exit;
}

// --- 2. Decodificar JSON ---
$asistenciaIDs = json_decode($asistenciaJson, true);
$temasData = json_decode($temasJson, true);

// Validar JSON decodificado
if ($asistenciaIDs === null || $temasData === null) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Error al decodificar datos de asistencia o temas. Asegúrate que el formato JSON sea correcto.', 'received_asistencia' => $_POST['asistencia'] ?? 'No recibido', 'received_temas' => $_POST['temas'] ?? 'No recibido']);
    exit;
}
if (!is_array($asistenciaIDs) || !is_array($temasData)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Los datos de asistencia o temas no son arrays válidos después de decodificar.']);
    exit;
}


class MinutaManager extends BaseConexion // Asegúrate que BaseConexion está definida o incluida
{
    private $db;

    public function __construct()
    {
        $this->db = $this->conectar();
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    // Modificado para recibir $enlaceAdjunto y usar $_FILES internamente
    public function guardarMinutaCompleta($idMinuta, $asistenciaIDs, $temasData, $enlaceAdjunto)
    {
        // --- INICIO DEBUG: Registrar entrada a la función ---
        error_log("DEBUG: Iniciando guardarMinutaCompleta para idMinuta {$idMinuta}");
        // --- FIN DEBUG ---

        $adjuntosGuardados = []; // Para devolver info al frontend
        try {
            $this->db->beginTransaction();

            // --- 1. ACTUALIZAR MINUTA (t_minuta) ---
            // (Ya no es necesario aquí, se asume que la minuta ya existe)

            // --- 2. ACTUALIZAR ASISTENCIA (t_asistencia) ---
            // Borramos la asistencia ANTERIOR de esta minuta
            // --- DEBUG ---
            error_log("DEBUG idMinuta {$idMinuta}: Antes de DELETE FROM t_asistencia");
            // --- FIN DEBUG ---
            $sqlDeleteAsistencia = "DELETE FROM t_asistencia WHERE t_minuta_idMinuta = :idMinuta";
            $stmtDeleteAsistencia = $this->db->prepare($sqlDeleteAsistencia);
            $stmtDeleteAsistencia->execute([':idMinuta' => $idMinuta]);
            // --- DEBUG ---
            error_log("DEBUG idMinuta {$idMinuta}: Después de DELETE FROM t_asistencia");
            // --- FIN DEBUG ---


            // Insertamos la asistencia ACTUAL (los IDs que llegaron del form)
            $idTipoReunion = 1; // Asumido
            if (!empty($asistenciaIDs)) {
                $sqlAsistencia = "INSERT INTO t_asistencia (t_minuta_idMinuta, t_usuario_idUsuario, t_tipoReunion_idTipoReunion, fechaRegistroAsistencia)
                                    VALUES (:idMinuta, :idUsuario, :idTipoReunion, NOW())";
                $stmtAsistencia = $this->db->prepare($sqlAsistencia);
                foreach ($asistenciaIDs as $idUsuario) {
                    if (is_numeric($idUsuario)) { // Validar
                        // --- DEBUG ---
                        error_log("DEBUG idMinuta {$idMinuta}: Antes de INSERT INTO t_asistencia para usuario {$idUsuario}");
                        // --- FIN DEBUG ---
                        $stmtAsistencia->execute([
                            ':idMinuta' => $idMinuta,
                            ':idUsuario' => $idUsuario,
                            ':idTipoReunion' => $idTipoReunion
                        ]);
                        // --- DEBUG ---
                        error_log("DEBUG idMinuta {$idMinuta}: Después de INSERT INTO t_asistencia para usuario {$idUsuario}");
                        // --- FIN DEBUG ---
                    } else {
                        error_log("Warning idMinuta {$idMinuta}: ID de asistencia no válido ignorado: " . print_r($idUsuario, true));
                    }
                }
            }

            // --- 3. ACTUALIZAR TEMAS Y ACUERDOS (t_tema y t_acuerdo) ---
            $idsTemasActuales = [];
            foreach ($temasData as $tema) {
                if (!empty($tema['idTema']) && is_numeric($tema['idTema'])) {
                    $idsTemasActuales[] = $tema['idTema'];
                }
            }
            // --- DEBUG ---
            error_log("DEBUG idMinuta {$idMinuta}: Antes de buscar temas existentes en DB");
            // --- FIN DEBUG ---
            $sqlTemasEnDB = "SELECT idTema FROM t_tema WHERE t_minuta_idMinuta = :idMinuta";
            $stmtTemasEnDB = $this->db->prepare($sqlTemasEnDB);
            $stmtTemasEnDB->execute([':idMinuta' => $idMinuta]);
            $idsTemasEnDB = $stmtTemasEnDB->fetchAll(PDO::FETCH_COLUMN, 0);
            $idsTemasABorrar = array_diff($idsTemasEnDB, $idsTemasActuales);
            // --- DEBUG ---
            error_log("DEBUG idMinuta {$idMinuta}: IDs de temas a borrar: " . implode(', ', $idsTemasABorrar));
            // --- FIN DEBUG ---

            if (!empty($idsTemasABorrar)) {
                $placeholdersBorrar = implode(',', array_fill(0, count($idsTemasABorrar), '?'));
                // --- DEBUG ---
                error_log("DEBUG idMinuta {$idMinuta}: Antes de DELETE FROM t_acuerdo para temas a borrar.");
                // --- FIN DEBUG ---
                $sqlDeleteAcuerdos = "DELETE FROM t_acuerdo WHERE t_tema_idTema IN ($placeholdersBorrar)";
                $stmtDeleteAcuerdos = $this->db->prepare($sqlDeleteAcuerdos);
                $stmtDeleteAcuerdos->execute($idsTemasABorrar);
                // --- DEBUG ---
                error_log("DEBUG idMinuta {$idMinuta}: Antes de DELETE FROM t_tema para temas a borrar.");
                // --- FIN DEBUG ---
                $sqlDeleteTemas = "DELETE FROM t_tema WHERE idTema IN ($placeholdersBorrar) AND t_minuta_idMinuta = ?"; // Doble check con idMinuta
                $paramsBorrarTemas = array_merge($idsTemasABorrar, [$idMinuta]);
                $stmtDeleteTemas = $this->db->prepare($sqlDeleteTemas);
                $stmtDeleteTemas->execute($paramsBorrarTemas);
                // --- DEBUG ---
                error_log("DEBUG idMinuta {$idMinuta}: Después de DELETE de temas/acuerdos.");
                // --- FIN DEBUG ---
            }
            $sqlInsertTema = "INSERT INTO t_tema (t_minuta_idMinuta, nombreTema, objetivo, compromiso, observacion) VALUES (:idMinuta, :nombre, :objetivo, :compromiso, :observacion)";
            $sqlUpdateTema = "UPDATE t_tema SET nombreTema = :nombre, objetivo = :objetivo, compromiso = :compromiso, observacion = :observacion WHERE idTema = :idTema AND t_minuta_idMinuta = :idMinuta";
            $sqlUpsertAcuerdo = "INSERT INTO t_acuerdo (descAcuerdo, t_tema_idTema)
                                  VALUES (:descAcuerdo, :idTema)
                                  ON DUPLICATE KEY UPDATE descAcuerdo = VALUES(descAcuerdo)";
            $stmtInsertTema = $this->db->prepare($sqlInsertTema);
            $stmtUpdateTema = $this->db->prepare($sqlUpdateTema);
            $stmtUpsertAcuerdo = $this->db->prepare($sqlUpsertAcuerdo);

            foreach ($temasData as $index => $tema) {
                $idTema = $tema['idTema'] ?? null;
                $paramsTema = [
                    ':idMinuta' => $idMinuta,
                    ':nombre' => trim($tema['nombreTema'] ?? ''),
                    ':objetivo' => trim($tema['objetivo'] ?? ''),
                    ':compromiso' => trim($tema['compromiso'] ?? ''),
                    ':observacion' => trim($tema['observacion'] ?? '')
                ];
                if (empty($paramsTema[':nombre']) && empty($paramsTema[':objetivo'])) {
                    error_log("DEBUG idMinuta {$idMinuta}: Saltando tema {$index} por estar vacío.");
                    continue;
                }

                if ($idTema && in_array($idTema, $idsTemasActuales)) { // ACTUALIZAR
                    // --- DEBUG ---
                    error_log("DEBUG idMinuta {$idMinuta}: Antes de UPDATE t_tema para idTema {$idTema}");
                    // --- FIN DEBUG ---
                    $paramsTema[':idTema'] = $idTema;
                    $stmtUpdateTema->execute($paramsTema);
                } else { // INSERTAR
                    // --- DEBUG ---
                    error_log("DEBUG idMinuta {$idMinuta}: Antes de INSERT INTO t_tema para tema nuevo (índice {$index})");
                    // --- FIN DEBUG ---
                    $stmtInsertTema->execute($paramsTema);
                    $idTema = $this->db->lastInsertId(); // Obtenemos el nuevo ID
                    // --- DEBUG ---
                    error_log("DEBUG idMinuta {$idMinuta}: Después de INSERT INTO t_tema. Nuevo idTema: {$idTema}");
                    // --- FIN DEBUG ---
                }
                // ... (código que inserta o actualiza el TEMA) ...

                $descAcuerdo = trim($tema['descAcuerdo'] ?? '');

                // --- INICIO DE LA CORRECCIÓN DE ACUERDOS ---

                // 1. Borrar SIEMPRE todos los acuerdos previos asociados a ESTE tema.
                //    Esto evita que se acumulen duplicados.
                error_log("DEBUG idMinuta {$idMinuta}: Limpiando acuerdos para idTema {$idTema}");
                $sqlDeleteAcuerdo = "DELETE FROM t_acuerdo WHERE t_tema_idTema = :idTema";
                $stmtDelAc = $this->db->prepare($sqlDeleteAcuerdo);
                $stmtDelAc->execute([':idTema' => $idTema]);

                // 2. Si el acuerdo que viene del formulario NO está vacío, insertarlo como nuevo.
                if ($idTema && !empty($descAcuerdo)) {
                    error_log("DEBUG idMinuta {$idMinuta}: Insertando nuevo acuerdo para idTema {$idTema}");

                    // TU SQL 'sqlUpsertAcuerdo' original no funcionaba y le faltaba t_tipoReunion_idTipoReunion
                    $sqlInsertAcuerdo = "INSERT INTO t_acuerdo (descAcuerdo, t_tema_idTema, t_tipoReunion_idTipoReunion) 
                         VALUES (:descAcuerdo, :idTema, :idTipoReunion)";

                    $stmtInsAc = $this->db->prepare($sqlInsertAcuerdo);

                    // Usamos el ID 1, igual que en la sección de asistencia.
                    $idTipoReunion = 1;

                    $stmtInsAc->execute([
                        ':descAcuerdo' => $descAcuerdo,
                        ':idTema' => $idTema,
                        ':idTipoReunion' => $idTipoReunion
                    ]);
                }
                // --- FIN DE LA CORRECCIÓN DE ACUERDOS ---
            }
            // --- DEBUG ---
            error_log("DEBUG idMinuta {$idMinuta}: Fin del bucle de temas/acuerdos.");
            // --- FIN DEBUG ---


            // --- 4. PROCESAR ADJUNTOS ---
            // --- DEBUG: Verificar idMinuta antes de adjuntos ---
            if (empty($idMinuta) || !is_numeric($idMinuta) || $idMinuta <= 0) {
                $this->db->rollBack(); // Cancelar transacción
                error_log("ERROR CRITICO idMinuta {$idMinuta}: idMinuta inválido justo antes de insertar adjunto: " . print_r($idMinuta, true));
                return ['status' => 'error', 'message' => 'Error interno: ID de Minuta inválido antes de guardar adjunto.', 'error' => 'ID_MINUTA_INVALIDO'];
            }
            error_log("DEBUG idMinuta {$idMinuta}: Valor de idMinuta ANTES de insertar adjunto: " . $idMinuta);
            // --- FIN DEBUG ---

            $sqlInsertAdjunto = "INSERT INTO t_adjunto (t_minuta_idMinuta, pathAdjunto, tipoAdjunto) VALUES (:idMinuta, :path, :tipo)";
            $stmtInsertAdjunto = $this->db->prepare($sqlInsertAdjunto);

            // 4a. Procesar Archivos Subidos
            $baseUploadPath = __DIR__ . '/../public/DocumentosAdjuntos/';
            $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'xlsx', 'mp4', 'ppt', 'pptx', 'doc', 'docx'];

            if (isset($_FILES['adjuntos']) && !empty($_FILES['adjuntos']['name'][0])) {
                $files = $_FILES['adjuntos'];
                $numFiles = count($files['name']);
                error_log("DEBUG idMinuta {$idMinuta}: Procesando {$numFiles} archivos subidos.");

                for ($i = 0; $i < $numFiles; $i++) {
                    $fileName = $files['name'][$i];
                    $tmpName = $files['tmp_name'][$i];
                    $fileSize = $files['size'][$i];
                    $fileError = $files['error'][$i];

                    if ($fileError === UPLOAD_ERR_OK) {
                        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                        if (in_array($fileExtension, $allowedExtensions)) {
                            // ... (lógica para crear directorio y mover archivo) ...
                            $targetDir = $baseUploadPath . strtoupper($fileExtension) . '/';
                            if (!is_dir($targetDir)) {
                                if (!mkdir($targetDir, 0775, true)) {
                                    throw new Exception("Error al crear directorio de subida: " . $targetDir);
                                }
                            }
                            $safeOriginalName = preg_replace("/[^a-zA-Z0-9.\-_]/", "_", basename($fileName));
                            $newFileName = uniqid('adj_', true) . '_' . $safeOriginalName;
                            $targetPath = $targetDir . $newFileName;
                            $relativePath = 'DocumentosAdjuntos/' . strtoupper($fileExtension) . '/' . $newFileName;

                            if (move_uploaded_file($tmpName, $targetPath)) {
                                // --- DEBUG ---
                                error_log("DEBUG idMinuta {$idMinuta}: Antes de INSERT INTO t_adjunto (file): {$relativePath}");
                                // --- FIN DEBUG ---
                                $stmtInsertAdjunto->execute([
                                    ':idMinuta' => $idMinuta,
                                    ':path' => $relativePath,
                                    ':tipo' => 'file'
                                ]);
                                $lastAdjId = $this->db->lastInsertId();
                                $adjuntosGuardados[] = ['idAdjunto' => $lastAdjId, 'pathAdjunto' => $relativePath, 'tipoAdjunto' => 'file'];
                                // --- DEBUG ---
                                error_log("DEBUG idMinuta {$idMinuta}: Después de INSERT INTO t_adjunto (file). Nuevo idAdjunto: {$lastAdjId}");
                                // --- FIN DEBUG ---
                            } else {
                                throw new Exception("Error al mover el archivo subido: " . $fileName);
                            }
                        } else {
                            error_log("Warning idMinuta {$idMinuta}: Extensión no permitida para archivo adjunto: " . $fileName);
                        }
                    } else {
                        error_log("Error idMinuta {$idMinuta}: Error al subir archivo adjunto $fileName: Código $fileError");
                    }
                }
            } else {
                error_log("DEBUG idMinuta {$idMinuta}: No se recibieron archivos adjuntos en \$_FILES['adjuntos'].");
            }

            // 4b. Procesar Enlace Externo
            if (!empty($enlaceAdjunto)) {
                $enlaceSanitized = filter_var(trim($enlaceAdjunto), FILTER_SANITIZE_URL);
                if (filter_var($enlaceSanitized, FILTER_VALIDATE_URL)) {
                    // --- DEBUG ---
                    error_log("DEBUG idMinuta {$idMinuta}: Antes de INSERT INTO t_adjunto (link): {$enlaceSanitized}");
                    // --- FIN DEBUG ---
                    $stmtInsertAdjunto->execute([
                        ':idMinuta' => $idMinuta,
                        ':path' => $enlaceSanitized,
                        ':tipo' => 'link'
                    ]);
                    $lastAdjId = $this->db->lastInsertId();
                    $adjuntosGuardados[] = ['idAdjunto' => $lastAdjId, 'pathAdjunto' => $enlaceSanitized, 'tipoAdjunto' => 'link'];
                    // --- DEBUG ---
                    error_log("DEBUG idMinuta {$idMinuta}: Después de INSERT INTO t_adjunto (link). Nuevo idAdjunto: {$lastAdjId}");
                    // --- FIN DEBUG ---
                } else {
                    error_log("Warning idMinuta {$idMinuta}: URL no válida proporcionada para adjunto: " . $enlaceAdjunto);
                }
            } else {
                error_log("DEBUG idMinuta {$idMinuta}: No se recibió enlace adjunto en \$_POST['enlaceAdjunto'].");
            }
            // --- DEBUG ---
            error_log("DEBUG idMinuta {$idMinuta}: Fin del procesamiento de adjuntos.");
            // --- FIN DEBUG ---


            // --- 5. ACTUALIZAR HORA DE TÉRMINO DE LA REUNIÓN ---
            // --- DEBUG ---
            error_log("DEBUG idMinuta {$idMinuta}: Antes de buscar idReunion en t_reunion.");
            // --- FIN DEBUG ---
            $sql_find_reunion = "SELECT idReunion FROM t_reunion WHERE t_minuta_idMinuta = :idMinuta LIMIT 1";
            $stmt_find = $this->db->prepare($sql_find_reunion);
            $stmt_find->execute([':idMinuta' => $idMinuta]);
            $reunion = $stmt_find->fetch(PDO::FETCH_ASSOC);
            $mensajeExito = 'Minuta guardada con éxito.';

            if ($reunion) {
                $idReunion = $reunion['idReunion'];
                // --- DEBUG ---
                error_log("DEBUG idMinuta {$idMinuta}: Antes de UPDATE t_reunion SET fechaTerminoReunion para idReunion {$idReunion}.");
                // --- FIN DEBUG ---
                $sql_update_termino = "UPDATE t_reunion SET fechaTerminoReunion = NOW() WHERE idReunion = :idReunion";
                $stmt_update = $this->db->prepare($sql_update_termino);
                $stmt_update->execute([':idReunion' => $idReunion]);
                $mensajeExito = 'Minuta guardada y hora de término de reunión actualizada.';
            } else {
                error_log("Warning idMinuta {$idMinuta}: No se encontró reunión asociada para actualizar fechaTerminoReunion.");
            }

            // --- 6. COMMIT ---
            // --- DEBUG ---
            error_log("DEBUG idMinuta {$idMinuta}: Antes de db->commit().");
            // --- FIN DEBUG ---
            $this->db->commit();
            // --- DEBUG ---
            error_log("DEBUG idMinuta {$idMinuta}: Después de db->commit().");
            // --- FIN DEBUG ---


            // --- Opcional: Obtener lista completa de adjuntos para devolver ---
            // --- DEBUG ---
            error_log("DEBUG idMinuta {$idMinuta}: Antes de SELECT todos los adjuntos para la respuesta.");
            // --- FIN DEBUG ---
            $sqlTodosAdjuntos = "SELECT idAdjunto, pathAdjunto, tipoAdjunto FROM t_adjunto WHERE t_minuta_idMinuta = :idMinuta ORDER BY idAdjunto";
            $stmtTodosAdjuntos = $this->db->prepare($sqlTodosAdjuntos);
            $stmtTodosAdjuntos->execute([':idMinuta' => $idMinuta]);
            $listaCompletaAdjuntos = $stmtTodosAdjuntos->fetchAll(PDO::FETCH_ASSOC);


            return ['status' => 'success', 'message' => $mensajeExito, 'idMinuta' => $idMinuta, 'adjuntosActualizados' => $listaCompletaAdjuntos]; // Devolvemos la lista
        } catch (Exception $e) {
            // --- DEBUG: Registrar error antes de rollback ---
            error_log("ERROR CATCH idMinuta {$idMinuta}: Excepción capturada - " . $e->getMessage());
            // --- FIN DEBUG ---
            if ($this->db->inTransaction()) {
                error_log("ERROR CATCH idMinuta {$idMinuta}: Realizando db->rollBack().");
                $this->db->rollBack();
            }
            // El error ya se registra aquí por el log anterior
            return ['status' => 'error', 'message' => 'Ocurrió un error al guardar los datos.', 'error' => $e->getMessage()];
        }
        // No necesitamos 'finally' aquí si no hay nada más que hacer siempre
    }
}
// --- FIN DE LA CLASE MinutaManager ---


// -----------------------------------------------------------------
// --- INICIO DEL CÓDIGO DE EJECUCIÓN (AGREGADO) ---
// -----------------------------------------------------------------

$manager = null;
$resultado = null;

try {
    // 1. Instanciar el manager
    $manager = new MinutaManager();

    // 2. Llamar al método principal con los datos ya validados
    // (Estos $idMinuta, $asistenciaIDs, $temasData, $enlaceAdjunto 
    // fueron definidos al INICIO del script)
    $resultado = $manager->guardarMinutaCompleta(
        $idMinuta,
        $asistenciaIDs,
        $temasData,
        $enlaceAdjunto
        // $_FILES se maneja internamente en el método, no es necesario pasarlo
    );

    // 3. Establecer el código de respuesta HTTP basado en el resultado
    if (isset($resultado['status']) && $resultado['status'] === 'error') {
        // Si el método detectó un error (ej. 400 por validación interna o 500 por BD)
        // Por simplicidad, si el método devuelve 'error', respondemos con 500.
        http_response_code(500);
    } else {
        // Si todo salió bien
        http_response_code(200);
    }
} catch (Exception $e) {
    // Captura errores en la *creación* de MinutaManager o fallos no capturados
    http_response_code(500);
    error_log("ERROR CRÍTICO en guardar_minuta_completa.php (fuera del método): " . $e->getMessage());
    $resultado = [
        'status' => 'error',
        'message' => 'Error fatal del script.',
        'error' => $e->getMessage()
    ];
}

// 4. Enviar la respuesta JSON al frontend
// Esto es lo que el JavaScript (res.json()) espera recibir.
echo json_encode($resultado);

// 5. Finalizar la ejecución
exit;
// --- FIN DEL CÓDIGO DE EJECUCIÓN ---