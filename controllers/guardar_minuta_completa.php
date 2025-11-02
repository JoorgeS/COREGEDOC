<?php
// controllers/guardar_minuta_completa.php

// ----------------------------------------------------------------------
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../cfg/config.php';
require_once __DIR__ . '/../class/class.conectorDB.php';
require_once __DIR__ . '/../vendor/autoload.php'; // Incluye el autoloader de Composer/Dompdf

use Dompdf\Dompdf;
use Dompdf\Options;
// (PHPMailer eliminado de este archivo)

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
    $idSecretario = $_SESSION['idUsuario'] ?? 0;
    // ... (resto del código)
}

// --- 1. Recepción de Datos desde $_POST y $_FILES ---
$idMinuta = $_POST['idMinuta'] ?? null;
$asistenciaJson = $_POST['asistencia'] ?? '[]'; // JSON string
$temasJson = $_POST['temas'] ?? '[]'; // JSON string
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
    echo json_encode(['status' => 'error', 'message' => 'Error al decodificar datos de asistencia o temas.']);
    exit;
}
if (!is_array($asistenciaIDs) || !is_array($temasData)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Los datos de asistencia o temas no son arrays válidos.']);
    exit;
}


class MinutaManager extends BaseConexion
{
    private $db;

    public function __construct()
    {
        $this->db = $this->conectar();
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    // Método 1: Obtener nombres de los asistentes (Sin cambios)
    private function getNombresAsistentes(array $asistenciaIDs, int $idMinuta): array
    {
        if (empty($asistenciaIDs)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($asistenciaIDs), '?'));
        $params = $asistenciaIDs;

        $sqlReunion = "SELECT nombreReunion FROM t_reunion WHERE t_minuta_idMinuta = ?";
        $stmtReunion = $this->db->prepare($sqlReunion);
        $stmtReunion->execute([$idMinuta]);
        $reunion = $stmtReunion->fetch(PDO::FETCH_ASSOC);
        $nombreReunion = $reunion['nombreReunion'] ?? 'Reunión sin título';

        $sql = "SELECT idUsuario, TRIM(CONCAT(pNombre, ' ', COALESCE(sNombre, ''), ' ', aPaterno, ' ', aMaterno)) AS nombreCompleto
                FROM t_usuario
                WHERE idUsuario IN ({$placeholders})
                ORDER BY nombreCompleto";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $nombres = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return ['nombreReunion' => $nombreReunion, 'asistentes' => $nombres];
    }

    // Método 2: Generar el PDF de asistencia (Sin cambios)
    private function generarPdfAsistencia(int $idMinuta, array $dataAsistencia): string
    {
        $nombresAsistentes = $dataAsistencia['asistentes'];
        $nombreReunion = $dataAsistencia['nombreReunion'];
        $fechaGeneracion = (new \DateTime())->format('Y-m-d H:i:s');
        $fechaParaNombreArchivo = (new \DateTime())->format('Ymd_His');

        $html = "
        <!DOCTYPE html><html><head><meta charset='UTF-8'><title>Lista de Asistencia - Minuta {$idMinuta}</title>
        <style>
         body { font-family: DejaVu Sans, sans-serif; font-size: 12px; } .header { text-align: center; margin-bottom: 20px; }
         .header h1 { font-size: 20px; } .header h2 { font-size: 16px; }
         .attendance-list { width: 100%; border-collapse: collapse; margin-top: 20px; }
         .attendance-list th, .attendance-list td { border: 1px solid #ccc; padding: 10px; text-align: left; }
         .attendance-list th { background-color: #f2f2f2; }
         .footer { position: fixed; bottom: 0; width: 100%; text-align: center; font-size: 10px; color: #999; }
        </style></head><body>
         <div class='header'>
          <h1>Listado de Asistencia</h1>
          <h2>Minuta N° {$idMinuta}: {$nombreReunion}</h2>
          <p>Fecha de la reunión: " . (new \DateTime())->format('d/m/Y') . "</p>
         </div>
         <table class='attendance-list'><thead><tr><th>N°</th><th>Nombre Completo</th><th>Firma</th></tr></thead><tbody>";

        if (empty($nombresAsistentes)) {
            $html .= "<tr><td colspan='3' style='text-align: center;'>No se registró asistencia para esta minuta.</td></tr>";
        } else {
            foreach ($nombresAsistentes as $index => $asistente) {
                $html .= "<tr><td>" . ($index + 1) . "</td><td>" . htmlspecialchars($asistente['nombreCompleto']) . "</td><td></td></tr>";
            }
        }
        $html .= "</tbody></table><div class='footer'>Generado por CoreVota el {$fechaGeneracion}</div></body></html>";

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('fontDir', __DIR__ . '/../vendor/dompdf/dompdf/lib/fonts');
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('Letter', 'portrait');
        $dompdf->render();

        $rutaBase = __DIR__ . '/../public/docs/asistencia/';
        if (!is_dir($rutaBase)) {
            mkdir($rutaBase, 0775, true);
        }
        $nombreArchivo = "asistencia_minuta_{$idMinuta}_{$fechaParaNombreArchivo}.pdf";
        $rutaCompleta = $rutaBase . $nombreArchivo;
        $relativePath = 'public/docs/asistencia/' . $nombreArchivo;

        file_put_contents($rutaCompleta, $dompdf->output());
        return $relativePath;
    }

    // (Función getEstadoMinuta eliminada, no es necesaria aquí)
    // (Función enviarCorreoAsistencia eliminada)
    // (Función actualizarConteoPresidentes eliminada)


    public function guardarMinutaCompleta($idMinuta, $asistenciaIDs, $temasData, $enlaceAdjunto)
    {
        $adjuntosGuardados = [];
        try {
            $this->db->beginTransaction();

            // (LÓGICA DE REINICIO DE FIRMAS ELIMINADA DE AQUÍ)

            // --- 2. ACTUALIZAR ASISTENCIA (t_asistencia) ---
            $sqlDeleteAsistencia = "DELETE FROM t_asistencia WHERE t_minuta_idMinuta = :idMinuta";
            $stmtDeleteAsistencia = $this->db->prepare($sqlDeleteAsistencia);
            $stmtDeleteAsistencia->execute([':idMinuta' => $idMinuta]);

            $idTipoReunion = 1; // Asumido
            if (!empty($asistenciaIDs)) {
                $sqlAsistencia = "INSERT INTO t_asistencia (t_minuta_idMinuta, t_usuario_idUsuario, t_tipoReunion_idTipoReunion, fechaRegistroAsistencia)
                                  VALUES (:idMinuta, :idUsuario, :idTipoReunion, NOW())";
                $stmtAsistencia = $this->db->prepare($sqlAsistencia);
                foreach ($asistenciaIDs as $idUsuario) {
                    if (is_numeric($idUsuario)) {
                        $stmtAsistencia->execute([
                            ':idMinuta' => $idMinuta,
                            ':idUsuario' => $idUsuario,
                            ':idTipoReunion' => $idTipoReunion
                        ]);
                    } else {
                        error_log("Warning idMinuta {$idMinuta}: ID de asistencia no válido ignorado: " . print_r($idUsuario, true));
                    }
                }
            }

            // --- Generación PDF Asistencia (SIN CAMBIOS) ---
            if (!empty($asistenciaIDs)) {
                $dataAsistencia = $this->getNombresAsistentes($asistenciaIDs, $idMinuta);
                $rutaPdfAsistencia = $this->generarPdfAsistencia($idMinuta, $dataAsistencia);

                $sqlInsertAdjunto = "INSERT INTO t_adjunto (t_minuta_idMinuta, pathAdjunto, tipoAdjunto) VALUES (:idMinuta, :path, :tipo)";
                $stmtInsertAdjunto = $this->db->prepare($sqlInsertAdjunto);
                $stmtInsertAdjunto->execute([
                    ':idMinuta' => $idMinuta,
                    ':path' => $rutaPdfAsistencia,
                    ':tipo' => 'asistencia' // (Usando 'asistencia' como en tu SQL)
                ]);
                $lastAdjId = $this->db->lastInsertId();
                $adjuntosGuardados[] = ['idAdjunto' => $lastAdjId, 'pathAdjunto' => $rutaPdfAsistencia, 'tipoAdjunto' => 'asistencia'];
                error_log("DEBUG idMinuta {$idMinuta}: PDF de asistencia generado y guardado en la BD: {$rutaPdfAsistencia}");

                // (LÓGICA DE CORREO ELIMINADA DE AQUÍ)
            }

            // --- 3. ACTUALIZAR TEMAS Y ACUERDOS (SIN CAMBIOS) ---
            $idsTemasActuales = [];
            foreach ($temasData as $tema) {
                if (!empty($tema['idTema']) && is_numeric($tema['idTema'])) {
                    $idsTemasActuales[] = $tema['idTema'];
                }
            }
            $sqlTemasEnDB = "SELECT idTema FROM t_tema WHERE t_minuta_idMinuta = :idMinuta";
            $stmtTemasEnDB = $this->db->prepare($sqlTemasEnDB);
            $stmtTemasEnDB->execute([':idMinuta' => $idMinuta]);
            $idsTemasEnDB = $stmtTemasEnDB->fetchAll(PDO::FETCH_COLUMN, 0);
            $idsTemasABorrar = array_diff($idsTemasEnDB, $idsTemasActuales);

            if (!empty($idsTemasABorrar)) {
                $placeholdersBorrar = implode(',', array_fill(0, count($idsTemasABorrar), '?'));
                $sqlDeleteAcuerdos = "DELETE FROM t_acuerdo WHERE t_tema_idTema IN ($placeholdersBorrar)";
                $stmtDeleteAcuerdos = $this->db->prepare($sqlDeleteAcuerdos);
                $stmtDeleteAcuerdos->execute($idsTemasABorrar);
                $sqlDeleteTemas = "DELETE FROM t_tema WHERE idTema IN ($placeholdersBorrar) AND t_minuta_idMinuta = ?";
                $paramsBorrarTemas = array_merge($idsTemasABorrar, [$idMinuta]);
                $stmtDeleteTemas = $this->db->prepare($sqlDeleteTemas);
                $stmtDeleteTemas->execute($paramsBorrarTemas);
            }
            $sqlInsertTema = "INSERT INTO t_tema (t_minuta_idMinuta, nombreTema, objetivo, compromiso, observacion) VALUES (:idMinuta, :nombre, :objetivo, :compromiso, :observacion)";
            $sqlUpdateTema = "UPDATE t_tema SET nombreTema = :nombre, objetivo = :objetivo, compromiso = :compromiso, observacion = :observacion WHERE idTema = :idTema AND t_minuta_idMinuta = :idMinuta";
            $stmtInsertTema = $this->db->prepare($sqlInsertTema);
            $stmtUpdateTema = $this->db->prepare($sqlUpdateTema);

            foreach ($temasData as $index => $tema) {
                $idTema = $tema['idTema'] ?? null;
                $paramsTema = [
                    ':idMinuta' => $idMinuta,
                    ':nombre' => trim($tema['nombreTema'] ?? ''),
                    ':objetivo' => trim($tema['objetivo'] ?? ''),
                    ':compromiso' => trim($tema['compromiso'] ?? ''),
                    ':observacion' => trim($tema['observacion'] ?? '')
                ];
                if (empty($paramsTema[':nombre'])) {
                    error_log("DEBUG idMinuta {$idMinuta}: Saltando tema {$index} por estar vacío.");
                    continue;
                }

                if ($idTema && in_array($idTema, $idsTemasActuales)) { // ACTUALIZAR
                    $paramsTema[':idTema'] = $idTema;
                    $stmtUpdateTema->execute($paramsTema);
                } else { // INSERTAR
                    $stmtInsertTema->execute($paramsTema);
                    $idTema = $this->db->lastInsertId(); // Obtenemos el nuevo ID
                }

                $descAcuerdo = trim($tema['descAcuerdo'] ?? '');
                $sqlDeleteAcuerdo = "DELETE FROM t_acuerdo WHERE t_tema_idTema = :idTema";
                $stmtDelAc = $this->db->prepare($sqlDeleteAcuerdo);
                $stmtDelAc->execute([':idTema' => $idTema]);

                if ($idTema && !empty($descAcuerdo)) {
                    $sqlInsertAcuerdo = "INSERT INTO t_acuerdo (descAcuerdo, t_tema_idTema, t_tipoReunion_idTipoReunion) 
                                         VALUES (:descAcuerdo, :idTema, :idTipoReunion)";
                    $stmtInsAc = $this->db->prepare($sqlInsertAcuerdo);
                    $idTipoReunion = 1; // Asumido
                    $stmtInsAc->execute([
                        ':descAcuerdo' => $descAcuerdo,
                        ':idTema' => $idTema,
                        ':idTipoReunion' => $idTipoReunion
                    ]);
                }
            }

            // --- 4. PROCESAR ADJUNTOS (Tu lógica sin cambios) ---
            $sqlInsertAdjunto = "INSERT INTO t_adjunto (t_minuta_idMinuta, pathAdjunto, tipoAdjunto) VALUES (:idMinuta, :path, :tipo)";
            $stmtInsertAdjunto = $this->db->prepare($sqlInsertAdjunto);
            $baseUploadPath = __DIR__ . '/../public/DocumentosAdjuntos/';
            $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'xlsx', 'mp4', 'ppt', 'pptx', 'doc', 'docx'];

            if (isset($_FILES['adjuntos']) && !empty($_FILES['adjuntos']['name'][0])) {
                $files = $_FILES['adjuntos'];
                $numFiles = count($files['name']);

                for ($i = 0; $i < $numFiles; $i++) {
                    $fileName = $files['name'][$i];
                    $tmpName = $files['tmp_name'][$i];
                    $fileError = $files['error'][$i];

                    if ($fileError === UPLOAD_ERR_OK) {
                        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                        if (in_array($fileExtension, $allowedExtensions)) {
                            $targetDir = $baseUploadPath . strtoupper($fileExtension) . '/';
                            if (!is_dir($targetDir)) {
                                if (!mkdir($targetDir, 0775, true)) {
                                    throw new Exception("Error al crear directorio de subida: " . $targetDir);
                                }
                            }
                            $safeOriginalName = preg_replace("/[^a-zA-Z0-9.\-_]/", "_", basename($fileName));
                            $newFileName = uniqid('adj_', true) . '_' . $safeOriginalName;
                            $targetPath = $targetDir . $newFileName;
                            // (Ruta corregida para la BD)
                            $relativePath = 'public/DocumentosAdjuntos/' . strtoupper($fileExtension) . '/' . $newFileName;

                            if (move_uploaded_file($tmpName, $targetPath)) {
                                $stmtInsertAdjunto->execute([
                                    ':idMinuta' => $idMinuta,
                                    ':path' => $relativePath,
                                    ':tipo' => 'file'
                                ]);
                                $lastAdjId = $this->db->lastInsertId();
                                $adjuntosGuardados[] = ['idAdjunto' => $lastAdjId, 'pathAdjunto' => $relativePath, 'tipoAdjunto' => 'file'];
                            } else {
                                throw new Exception("Error al mover el archivo subido: " . $fileName);
                            }
                        }
                    }
                }
            }

            if (!empty($enlaceAdjunto)) {
                $enlaceSanitized = filter_var(trim($enlaceAdjunto), FILTER_SANITIZE_URL);
                if (filter_var($enlaceSanitized, FILTER_VALIDATE_URL)) {
                    $stmtInsertAdjunto->execute([
                        ':idMinuta' => $idMinuta,
                        ':path' => $enlaceSanitized,
                        ':tipo' => 'link'
                    ]);
                    $lastAdjId = $this->db->lastInsertId();
                    $adjuntosGuardados[] = ['idAdjunto' => $lastAdjId, 'pathAdjunto' => $enlaceSanitized, 'tipoAdjunto' => 'link'];
                }
            }

            // --- 5. ACTUALIZAR HORA DE TÉRMINO DE LA REUNIÓN (Tu lógica sin cambios) ---
            $sql_find_reunion = "SELECT idReunion, fechaTerminoReunion FROM t_reunion WHERE t_minuta_idMinuta = :idMinuta LIMIT 1";
            $stmt_find = $this->db->prepare($sql_find_reunion);
            $stmt_find->execute([':idMinuta' => $idMinuta]);
            $reunion = $stmt_find->fetch(PDO::FETCH_ASSOC);
            $mensajeExito = 'Borrador guardado con éxito.'; // (Mensaje cambiado)

            if (empty($reunion['fechaTerminoReunion']) && $reunion) {
                $idReunion = $reunion['idReunion'];
                $sql_update_termino = "UPDATE t_reunion SET fechaTerminoReunion = NOW() WHERE idReunion = :idReunion";
                $stmt_update = $this->db->prepare($sql_update_termino);
                $stmt_update->execute([':idReunion' => $idReunion]);
                $mensajeExito = 'Borrador guardado y hora de término de reunión actualizada.';
            }

            // --- 5b. (ELIMINADO) ACTUALIZAR CONTEO DE PRESIDENTES REQUERIDOS ---
            // $this->actualizarConteoPresidentes($idMinuta);

            // --- (NUEVO) 6. ASEGURAR QUE EL ESTADO SEA 'BORRADOR' ---
            // Si el estado era 'PENDIENTE' o 'PARCIAL', se revierte a 'BORRADOR'
            // porque el ST la editó, invalidando las firmas anteriores.
            $sqlSetBorrador = "UPDATE t_minuta 
                               SET estadoMinuta = 'BORRADOR' 
                               WHERE idMinuta = :idMinuta 
                               AND estadoMinuta <> 'APROBADA'"; // No modificar si ya está Aprobada
            $this->db->prepare($sqlSetBorrador)->execute([':idMinuta' => $idMinuta]);

            // --- 7. COMMIT ---
            $this->db->commit();

            // --- Opcional: Obtener lista completa de adjuntos para devolver ---
            $sqlTodosAdjuntos = "SELECT idAdjunto, pathAdjunto, tipoAdjunto FROM t_adjunto WHERE t_minuta_idMinuta = :idMinuta ORDER BY idAdjunto";
            $stmtTodosAdjuntos = $this->db->prepare($sqlTodosAdjuntos);
            $stmtTodosAdjuntos->execute([':idMinuta' => $idMinuta]);
            $listaCompletaAdjuntos = $stmtTodosAdjuntos->fetchAll(PDO::FETCH_ASSOC);

            return ['status' => 'success', 'message' => $mensajeExito, 'idMinuta' => $idMinuta, 'adjuntosActualizados' => $listaCompletaAdjuntos];
        } catch (Exception $e) {
            error_log("ERROR CATCH idMinuta {$idMinuta}: Excepción capturada - " . $e->getMessage());
            if ($this->db->inTransaction()) {
                error_log("ERROR CATCH idMinuta {$idMinuta}: Realizando db->rollBack().");
                $this->db->rollBack();
            }
            return ['status' => 'error', 'message' => 'Ocurrió un error al guardar los datos.', 'error' => $e->getMessage()];
        }
    }
} // <-- Cierre de la clase MinutaManager

// --- INICIO DEL CÓDIGO DE EJECUCIÓN (AGREGADO) ---
$manager = null;
$resultado = null;

try {
    // 1. Instanciar el manager
    $manager = new MinutaManager();

    // 2. Llamar al método principal con los datos ya validados
    $resultado = $manager->guardarMinutaCompleta(
        $idMinuta,
        $asistenciaIDs,
        $temasData,
        $enlaceAdjunto
    );

    // 3. Establecer el código de respuesta HTTP basado en el resultado
    if (isset($resultado['status']) && $resultado['status'] === 'error') {
        http_response_code(500);
    } else {
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
echo json_encode($resultado);

// 5. Finalizar la ejecución
exit;
