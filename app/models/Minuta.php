<?php
namespace App\Models;

use Exception;
use App\Config\Database;
use PDO;
use PDOException;

class Minuta
{
    private $conn;

    public function __construct()
    {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    // =========================================================================
    //  NUEVA GESTIÓN MULTI-PRESIDENTE Y FIRMAS (REEMPLAZA CÓDIGO ANTERIOR)
    // =========================================================================

    public function enviarParaFirma($idMinuta, $idUsuarioLogueado)
    {
        try {
            $this->conn->beginTransaction();

            // 1. IDENTIFICAR TODOS LOS PRESIDENTES (Principal + Mixtas)
            // Buscamos la comisión principal en la Minuta y las mixtas en la Reunión asociada
            $sqlComisiones = "SELECT 
                                m.t_comision_idComision as c1, 
                                r.t_comision_idComision_mixta as c2, 
                                r.t_comision_idComision_mixta2 as c3
                              FROM t_minuta m
                              LEFT JOIN t_reunion r ON m.idMinuta = r.t_minuta_idMinuta
                              WHERE m.idMinuta = :idMinuta";
            
            $stmtC = $this->conn->prepare($sqlComisiones);
            $stmtC->execute([':idMinuta' => $idMinuta]);
            $res = $stmtC->fetch(PDO::FETCH_ASSOC);

            // Recolectamos IDs únicos de comisiones
            $idsComisiones = [];
            if ($res) {
                if (!empty($res['c1'])) $idsComisiones[] = $res['c1'];
                if (!empty($res['c2'])) $idsComisiones[] = $res['c2'];
                if (!empty($res['c3'])) $idsComisiones[] = $res['c3'];
            }
            $idsComisiones = array_unique($idsComisiones);

            if (empty($idsComisiones)) {
                throw new Exception("No se encontraron comisiones asociadas a esta minuta.");
            }

            // 2. OBTENER LOS PRESIDENTES DE ESAS COMISIONES
            // Usamos implosión para crear los placeholders (?,?,?) dinámicos
            $inQuery = implode(',', array_fill(0, count($idsComisiones), '?'));
            
            $sqlPresis = "SELECT DISTINCT t_usuario_idPresidente 
                          FROM t_comision 
                          WHERE idComision IN ($inQuery) 
                          AND t_usuario_idPresidente IS NOT NULL";
            
            $stmtP = $this->conn->prepare($sqlPresis);
            $stmtP->execute($idsComisiones); // Pasamos el array limpio de IDs
            $presidentes = $stmtP->fetchAll(PDO::FETCH_COLUMN);

            if (empty($presidentes)) {
                throw new Exception("Las comisiones asociadas no tienen presidentes asignados en el sistema.");
            }

            // 3. LIMPIAR SOLICITUDES ANTERIORES
            // Borramos para evitar duplicados si es un reenvío por corrección
            $sqlDel = "DELETE FROM t_aprobacion_minuta WHERE t_minuta_idMinuta = :idMinuta";
            $stmtD = $this->conn->prepare($sqlDel);
            $stmtD->execute([':idMinuta' => $idMinuta]);

            // 4. INSERTAR SOLICITUD PARA CADA PRESIDENTE
            // CORRECCIÓN SQLSTATE 1048: Eliminamos 'fechaAprobacion' del insert o pasamos NULL explícito
            // Nota: Asegúrate de haber ejecutado el ALTER TABLE para permitir NULL.
            $sqlIns = "INSERT INTO t_aprobacion_minuta (t_minuta_idMinuta, t_usuario_idPresidente, estado_firma, fechaAprobacion)
                       VALUES (:idMinuta, :idPresidente, 'EN_ESPERA', NULL)"; 
            
            $stmtI = $this->conn->prepare($sqlIns);

            foreach ($presidentes as $idPresidente) {
                $stmtI->execute([
                    ':idMinuta' => $idMinuta, 
                    ':idPresidente' => $idPresidente
                ]);
            }

            // 5. ACTUALIZAR ESTADO MINUTA Y CANTIDAD REQUERIDA
            $sqlUpdate = "UPDATE t_minuta 
                          SET estadoMinuta = 'PENDIENTE', 
                              presidentesRequeridos = :total 
                          WHERE idMinuta = :idMinuta";
            
            $stmtU = $this->conn->prepare($sqlUpdate);
            $stmtU->execute([
                ':idMinuta' => $idMinuta,
                ':total' => count($presidentes) // Guardamos cuántos deben firmar
            ]);

            // 6. LOG DE AUDITORÍA
            $nombresP = count($presidentes);
            $this->logSeguimiento($idMinuta, $idUsuarioLogueado, 'ENVIO_FIRMA', "Minuta enviada a $nombresP presidente(s) para aprobación.");

            $this->conn->commit();
            return true;

        } catch (Exception $e) {
            $this->conn->rollBack();
            // Agregamos contexto al error para depuración
            throw new Exception("Error al enviar a firma: " . $e->getMessage());
        }
    }

    public function firmarMinuta($idMinuta, $idUsuario)
    {
        try {
            $this->conn->beginTransaction();

            // 1. Firmar el registro individual
            $sqlFirma = "UPDATE t_aprobacion_minuta SET estado_firma = 'FIRMADO', fechaAprobacion = NOW()
                         WHERE t_minuta_idMinuta = :idMinuta AND t_usuario_idPresidente = :idUsuario";
            $stmt = $this->conn->prepare($sqlFirma);
            $stmt->execute([':idMinuta' => $idMinuta, ':idUsuario' => $idUsuario]);

            // 2. Verificar si quedan otros presidentes por firmar
            $sqlPendientes = "SELECT COUNT(*) FROM t_aprobacion_minuta
                              WHERE t_minuta_idMinuta = :idMinuta AND estado_firma != 'FIRMADO'";
            $stmtP = $this->conn->prepare($sqlPendientes);
            $stmtP->execute([':idMinuta' => $idMinuta]);
            $pendientes = $stmtP->fetchColumn();

            // 3. Si pendientes es 0, todos firmaron -> APROBADA
            $nuevoEstado = ($pendientes == 0) ? 'APROBADA' : 'PARCIAL';
            
            $sqlUpdate = "UPDATE t_minuta SET estadoMinuta = :estado WHERE idMinuta = :idMinuta";
            $stmtU = $this->conn->prepare($sqlUpdate);
            $stmtU->execute([':estado' => $nuevoEstado, ':idMinuta' => $idMinuta]);

            $this->logSeguimiento($idMinuta, $idUsuario, 'FIRMA_RECIBIDA', "Firma recibida. Estado: $nuevoEstado. Pendientes: $pendientes");

            $this->conn->commit();
            return ['status' => 'success', 'estado_nuevo' => $nuevoEstado];
        } catch (Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }

    public function getCorreosPresidentes($idMinuta)
    {
        // ACTUALIZADO: Obtiene correos de TODOS los presidentes en la tabla de aprobación
        $sql = "SELECT u.pNombre, u.aPaterno, u.correo
                FROM t_aprobacion_minuta ap
                JOIN t_usuario u ON ap.t_usuario_idPresidente = u.idUsuario
                WHERE ap.t_minuta_idMinuta = :id";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $idMinuta]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // =========================================================================
    //  NUEVA GESTIÓN DE VOTACIONES (REEMPLAZA CÓDIGO ANTERIOR)
    // =========================================================================

    public function crearVotacion($idMinuta, $nombre, $idComision)
    {
        // Validar que si llega 0 o vacío, se guarde como NULL
        if (empty($idComision)) {
             $idComision = null; 
        }

        $sql = "INSERT INTO t_votacion (nombreVotacion, t_minuta_idMinuta, idComision, fechaCreacion, habilitada)
                VALUES (:nombre, :idMinuta, :idComision, NOW(), 1)";

        $stmt = $this->conn->prepare($sql);

        try {
            return $stmt->execute([
                ':nombre' => $nombre,
                ':idMinuta' => $idMinuta,
                ':idComision' => $idComision
            ]);
        } catch (PDOException $e) {
            throw new Exception("Error BD al crear votación: " . $e->getMessage());
        }
    }

    // =========================================================================
    //  NUEVA GESTIÓN DE TEMAS CON AUDITORÍA (REEMPLAZA CÓDIGO ANTERIOR)
    // =========================================================================

    public function guardarTemas($idMinuta, $temas, $idUsuarioEditor = null)
    {
        try {
            $this->conn->beginTransaction();

            // --- AUDITORÍA DE CAMBIOS ---
            if ($idUsuarioEditor) {
                $this->auditarCambiosTemas($idMinuta, $temas, $idUsuarioEditor);
            }
            // ---------------------------

            // Borrado e Inserción
            $sqlDelete = "DELETE FROM t_tema WHERE t_minuta_idMinuta = :idMinuta";
            $stmtDelete = $this->conn->prepare($sqlDelete);
            $stmtDelete->execute([':idMinuta' => $idMinuta]);

            $sqlInsert = "INSERT INTO t_tema (t_minuta_idMinuta, nombreTema, objetivo, acuerdos, compromiso, observacion)
                          VALUES (:idMinuta, :nombre, :objetivo, :acuerdos, :compromiso, :observacion)";
            $stmtInsert = $this->conn->prepare($sqlInsert);

            foreach ($temas as $t) {
                $stmtInsert->execute([
                    ':idMinuta' => $idMinuta,
                    ':nombre' => $t['nombreTema'] ?? '',
                    ':objetivo' => $t['objetivo'] ?? '',
                    ':acuerdos' => $t['acuerdos'] ?? '',
                    ':compromiso' => $t['compromiso'] ?? '',
                    ':observacion' => $t['observacion'] ?? ''
                ]);
            }

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollBack();
            return false;
        }
    }

    // --- NUEVAS FUNCIONES AUXILIARES PARA AUDITORÍA ---

    private function auditarCambiosTemas($idMinuta, $nuevosTemas, $idUsuario)
    {
        // 1. Obtenemos estado actual para saber si vale la pena auditar
        $minuta = $this->getMinutaById($idMinuta);
        if ($minuta['estadoMinuta'] !== 'REQUIERE_REVISION') {
            return; // Solo auditamos cambios post-envío
        }

        // 2. Obtenemos los temas "viejos" antes de borrarlos
        $oldTemas = $this->getTemas($idMinuta);

        $maxCount = max(count($oldTemas), count($nuevosTemas));

        for ($i = 0; $i < $maxCount; $i++) {
            $old = $oldTemas[$i] ?? [];
            $new = $nuevosTemas[$i] ?? [];

            // Caso: Tema Nuevo
            if (empty($old) && !empty($new)) {
                $this->logSeguimiento($idMinuta, $idUsuario, 'EDICION', "Se agregó un nuevo tema: " . substr($new['nombreTema'], 0, 30) . "...");
                continue;
            }
            // Caso: Tema Borrado
            if (!empty($old) && empty($new)) {
                $this->logSeguimiento($idMinuta, $idUsuario, 'EDICION', "Se eliminó el tema: " . substr($old['nombreTema'], 0, 30) . "...");
                continue;
            }

            // Caso: Comparación de campos
            $nombreTema = $old['nombreTema'] ?? 'Tema ' . ($i+1);
            
            $this->checkDiff($idMinuta, $idUsuario, "Tema '$nombreTema' (Objetivo)", $old['objetivo'] ?? '', $new['objetivo'] ?? '');
            $this->checkDiff($idMinuta, $idUsuario, "Tema '$nombreTema' (Acuerdos)", $old['acuerdos'] ?? '', $new['acuerdos'] ?? '');
            $this->checkDiff($idMinuta, $idUsuario, "Tema '$nombreTema' (Compromiso)", $old['compromiso'] ?? '', $new['compromiso'] ?? '');
            $this->checkDiff($idMinuta, $idUsuario, "Tema '$nombreTema' (Observación)", $old['observacion'] ?? '', $new['observacion'] ?? '');
        }
    }

    private function checkDiff($idMinuta, $idUsuario, $campo, $valOld, $valNew) {
        if (trim($valOld) !== trim($valNew)) {
            $this->logSeguimiento($idMinuta, $idUsuario, 'CORRECCION', "Editado $campo.");
        }
    }

    // =========================================================================
    //  FUNCIONES EXISTENTES (MANTENIDAS)
    // =========================================================================

    public function getMinutasByEstado($estado, $startDate = null, $endDate = null)
    {
        $estado = strtoupper(trim((string)$estado)) === 'APROBADA' ? 'APROBADA' : 'PENDIENTE';

        $sql = "
            SELECT
                m.idMinuta,
                c.nombreComision,
                u.pNombre AS presidenteNombre,
                u.aPaterno AS presidenteApellido,
                m.estadoMinuta,
                m.pathArchivo,
                r.nombreReunion,
                m.fechaMinuta,
                IFNULL(GROUP_CONCAT(DISTINCT t.nombreTema ORDER BY t.idTema SEPARATOR '<br>'), 'N/A') AS nombreTemas
            FROM t_minuta m
            LEFT JOIN t_tema    t   ON t.t_minuta_idMinuta   = m.idMinuta
            LEFT JOIN t_comision c  ON m.t_comision_idComision = c.idComision
            LEFT JOIN t_usuario u   ON c.t_usuario_idPresidente = u.idUsuario
            LEFT JOIN t_reunion r   ON m.idMinuta = r.t_minuta_idMinuta
            WHERE 1=1
        ";

        $params = [];

        if ($estado === 'APROBADA') {
            $sql .= " AND m.estadoMinuta = 'APROBADA' ";
        } else {
            $sql .= " AND m.estadoMinuta IN ('PENDIENTE', 'REQUIERE_REVISION', 'BORRADOR', 'PARCIAL') ";
        }

        if (!empty($startDate)) {
            $sql .= " AND m.fechaMinuta >= :startDate ";
            $params[':startDate'] = $startDate;
        }

        if (!empty($endDate)) {
            $sql .= " AND m.fechaMinuta <= :endDate ";
            $params[':endDate'] = $endDate . ' 23:59:59';
        }

        $sql .= " GROUP BY m.idMinuta, c.nombreComision, u.pNombre, u.aPaterno, m.estadoMinuta, r.nombreReunion ORDER BY m.fechaMinuta DESC";

        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error en getMinutasByEstado: " . $e->getMessage());
            return [];
        }
    }

    public function getMinutaById($id)
    {
        $sql = "SELECT * FROM t_minuta WHERE idMinuta = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getTemas($idMinuta)
    {
        $sql = "SELECT * FROM t_tema WHERE t_minuta_idMinuta = :id ORDER BY idTema ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $idMinuta]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getEstadoFirma($idMinuta, $idUsuario)
    {
        $sql = "SELECT estado_firma FROM t_aprobacion_minuta
                WHERE t_minuta_idMinuta = :idMinuta
                AND t_usuario_idPresidente = :idUsuario";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':idMinuta' => $idMinuta, ':idUsuario' => $idUsuario]);
        return $stmt->fetchColumn();
    }

    public function getAsistenciaData($idMinuta)
    {
        // CORRECCIÓN: Agregamos "AND estado = 1" para filtrar inactivos
        $sqlMiembros = "SELECT idUsuario, pNombre, sNombre, aPaterno, aMaterno,
                        TRIM(CONCAT(pNombre, ' ', COALESCE(sNombre, ''), ' ', aPaterno, ' ', aMaterno)) AS nombreCompleto
                        FROM t_usuario 
                        WHERE tipoUsuario_id IN (1, 3) 
                        AND estado = 1  -- <--- FILTRO DE USUARIOS ACTIVOS
                        ORDER BY aPaterno";

        $stmt = $this->conn->prepare($sqlMiembros);
        $stmt->execute();
        $miembros = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $sqlAsistencia = "SELECT t_usuario_idUsuario FROM t_asistencia WHERE t_minuta_idMinuta = :idMinuta AND estadoAsistencia = 'PRESENTE'";
        $stmt = $this->conn->prepare($sqlAsistencia);
        $stmt->execute([':idMinuta' => $idMinuta]);
        $presentes = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        $mapaPresentes = array_flip($presentes);

        $resultado = [];
        foreach ($miembros as $m) {
            $id = (int)$m['idUsuario'];
            $resultado[] = [
                'idUsuario' => $id,
                'nombreCompleto' => $m['nombreCompleto'],
                'presente' => isset($mapaPresentes[$id])
            ];
        }
        return $resultado;
    }

    public function guardarAsistencia($idMinuta, $listaIdsUsuarios)
    {
        try {
            $this->conn->beginTransaction();
            $sqlDelete = "DELETE FROM t_asistencia WHERE t_minuta_idMinuta = :idMinuta";
            $stmt = $this->conn->prepare($sqlDelete);
            $stmt->execute([':idMinuta' => $idMinuta]);

            if (!empty($listaIdsUsuarios)) {
                $sqlInsert = "INSERT INTO t_asistencia (t_minuta_idMinuta, t_usuario_idUsuario) VALUES (:idMinuta, :idUsuario)";
                $stmtInsert = $this->conn->prepare($sqlInsert);
                foreach ($listaIdsUsuarios as $idUsuario) {
                    $stmtInsert->execute([':idMinuta' => $idMinuta, ':idUsuario' => $idUsuario]);
                }
            }
            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollBack();
            return false;
        }
    }

    public function guardarFeedback($idMinuta, $idUsuario, $textoFeedback)
    {
        try {
            $this->conn->beginTransaction();

            // 1. Guardar el comentario de feedback
            $sql = "INSERT INTO t_minuta_feedback (t_minuta_idMinuta, t_usuario_idPresidente, textoFeedback, fechaFeedback, resuelto)
                    VALUES (:idMinuta, :idUsuario, :texto, NOW(), 0)";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([':idMinuta' => $idMinuta, ':idUsuario' => $idUsuario, ':texto' => $textoFeedback]);

            // 2. LÓGICA DE RESET: INVALIDAR TODAS LAS FIRMAS
            // Si el Presidente B corrige, la firma del Presidente A ya no vale (el documento cambió).
            // Ponemos a TODOS los aprobadores de esta minuta en estado 'REQUIERE_REVISION'.
            
            $sqlReset = "UPDATE t_aprobacion_minuta 
                         SET estado_firma = 'REQUIERE_REVISION', 
                             fechaAprobacion = NULL 
                         WHERE t_minuta_idMinuta = :idMinuta";
            
            $stmtReset = $this->conn->prepare($sqlReset);
            $stmtReset->execute([':idMinuta' => $idMinuta]);

            // 3. Cambiar estado general de la minuta
            $sqlMin = "UPDATE t_minuta SET estadoMinuta = 'REQUIERE_REVISION' WHERE idMinuta = :idMinuta";
            $stmtMin = $this->conn->prepare($sqlMin);
            $stmtMin->execute([':idMinuta' => $idMinuta]);

            // 4. Log de auditoría
            $this->logSeguimiento($idMinuta, $idUsuario, 'FEEDBACK_ENVIADO', 'Correcciones solicitadas. Se han reiniciado las firmas.');

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }

    private function logSeguimiento($idMinuta, $idUsuario, $accion, $detalle)
    {
        $sql = "INSERT INTO t_minuta_seguimiento (t_minuta_idMinuta, t_usuario_idUsuario, accion, detalle, fecha_hora)
                VALUES (:idMinuta, :idUsuario, :accion, :detalle, NOW())";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':idMinuta' => $idMinuta, ':idUsuario' => $idUsuario, ':accion' => $accion, ':detalle' => $detalle]);
    }

    public function actualizarPathArchivo($idMinuta, $path)
    {
        $sql = "UPDATE t_minuta SET pathArchivo = :path WHERE idMinuta = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':path' => $path, ':id' => $idMinuta]);
    }

    public function obtenerReunionActivaParaAsistencia($idUsuario)
    {
        $sql = "SELECT r.idReunion, r.nombreReunion, r.t_minuta_idMinuta
                FROM t_reunion r
                WHERE r.vigente = 1
                AND DATE(r.fechaInicioReunion) = CURDATE()
                AND NOT EXISTS (
                    SELECT 1 FROM t_asistencia a
                    WHERE a.t_minuta_idMinuta = r.t_minuta_idMinuta
                    AND a.t_usuario_idUsuario = :idUsuario
                )
                LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':idUsuario' => $idUsuario]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function registrarAutoAsistencia($idMinuta, $idUsuario, $idReunion)
    {
        try {
            // 1. Verificar si ya existe registro (Creado por iniciarMinuta)
            $sqlCheck = "SELECT idAsistencia FROM t_asistencia
                         WHERE t_minuta_idMinuta = :idMinuta
                         AND t_usuario_idUsuario = :idUsuario";
            $stmtCheck = $this->conn->prepare($sqlCheck);
            $stmtCheck->execute([':idMinuta' => $idMinuta, ':idUsuario' => $idUsuario]);
            $idExistente = $stmtCheck->fetchColumn();

            if ($idExistente) {
                // UPDATE
                $sqlUpdate = "UPDATE t_asistencia
                              SET fechaRegistroAsistencia = NOW(),
                                  fechaMarca = NOW(),
                                  origenAsistencia = 'AUTOREGISTRO',
                                  estadoAsistencia = 'PRESENTE'
                              WHERE idAsistencia = :idAsistencia";

                $stmtUp = $this->conn->prepare($sqlUpdate);
                return $stmtUp->execute([':idAsistencia' => $idExistente]);
            } else {
                // INSERT
                $sql = "INSERT INTO t_asistencia
                        (t_minuta_idMinuta, t_usuario_idUsuario, t_tipoReunion_idTipoReunion, fechaRegistroAsistencia, fechaMarca, origenAsistencia, estadoAsistencia)
                        VALUES (:idMinuta, :idUsuario, 1, NOW(), NOW(), 'AUTOREGISTRO', 'PRESENTE')";

                $stmt = $this->conn->prepare($sql);
                return $stmt->execute([':idMinuta' => $idMinuta, ':idUsuario' => $idUsuario]);
            }
        } catch (Exception $e) {
            error_log("Error autoasistencia: " . $e->getMessage());
            return false;
        }
    }

    public function getSeguimiento($idMinuta)
    {
        $sql = "SELECT s.*, COALESCE(TRIM(CONCAT(u.pNombre, ' ', u.aPaterno)), 'Sistema') as usuario_nombre
                FROM t_minuta_seguimiento s
                LEFT JOIN t_usuario u ON s.t_usuario_idUsuario = u.idUsuario
                WHERE s.t_minuta_idMinuta = :id
                ORDER BY s.fecha_hora ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $idMinuta]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getHistorialAsistenciaPersonal($idUsuario)
    {
        $sql = "SELECT r.nombreReunion, r.fechaInicioReunion, a.fechaRegistroAsistencia, a.origenAsistencia
                FROM t_asistencia a
                JOIN t_minuta m ON a.t_minuta_idMinuta = m.idMinuta
                JOIN t_reunion r ON m.idMinuta = r.t_minuta_idMinuta
                WHERE a.t_usuario_idUsuario = :id
                ORDER BY r.fechaInicioReunion DESC LIMIT 20";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $idUsuario]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSeguimientoGeneral($filters = [])
    {
        $sql = "
             WITH RankedSeguimiento AS (
                SELECT s.t_minuta_idMinuta, s.detalle as ultimo_detalle, s.fecha_hora as ultima_fecha,
                  COALESCE(TRIM(CONCAT(u.pNombre, ' ', u.aPaterno)), 'Sistema') as ultimo_usuario,
                  ROW_NUMBER() OVER(PARTITION BY s.t_minuta_idMinuta ORDER BY s.fecha_hora DESC) as rn
                FROM t_minuta_seguimiento s
                LEFT JOIN t_usuario u ON s.t_usuario_idUsuario = u.idUsuario
             )
             SELECT m.idMinuta, m.fechaMinuta, m.estadoMinuta, c.nombreComision,
                IFNULL(GROUP_CONCAT(DISTINCT t.nombreTema SEPARATOR '<br>'), 'N/A') AS nombreTemas,
                COALESCE(rs.ultimo_detalle, 'Sin acciones registradas') as ultimo_detalle,
                rs.ultima_fecha as ultima_fecha, COALESCE(rs.ultimo_usuario, 'N/A') as ultimo_usuario
             FROM t_minuta m
             LEFT JOIN t_comision c ON m.t_comision_idComision = c.idComision
             LEFT JOIN t_tema t ON m.idMinuta = t.t_minuta_idMinuta
             LEFT JOIN RankedSeguimiento rs ON m.idMinuta = rs.t_minuta_idMinuta AND rs.rn = 1
             WHERE 1=1
        ";

        $params = [];
        if (!empty($filters['comisionId'])) {
            $sql .= " AND m.t_comision_idComision = :comisionId";
            $params[':comisionId'] = $filters['comisionId'];
        }

        $sql .= " GROUP BY m.idMinuta ORDER BY m.fechaMinuta DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function marcarAsistenciaValidada($id)
    {
        $sql = "UPDATE t_minuta SET asistencia_validada = 1 WHERE idMinuta = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $id]);
    }

   public function getFirmasAprobadas($idMinuta)
    {
        // Esta consulta busca la comisión que preside el usuario firmante
        $sql = "SELECT ap.fechaAprobacion, 
                       CONCAT(u.pNombre, ' ', u.aPaterno) as nombrePresidente,
                       c.nombreComision
                FROM t_aprobacion_minuta ap
                JOIN t_usuario u ON ap.t_usuario_idPresidente = u.idUsuario
                /* Unimos con t_comision para obtener el nombre donde este usuario es presidente */
                JOIN t_comision c ON c.t_usuario_idPresidente = u.idUsuario
                /* Filtro extra opcional: Asegurarnos que esa comisión está relacionada con la minuta (si es estricto) */
                WHERE ap.t_minuta_idMinuta = :id 
                AND ap.estado_firma = 'FIRMADO' 
                ORDER BY ap.fechaAprobacion ASC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $idMinuta]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getVotacionesPorMinuta($idMinuta)
    {
        $sql = "SELECT v.*,
                (SELECT COUNT(*) FROM t_voto WHERE t_votacion_idVotacion = v.idVotacion AND opcionVoto = 'SI') as si,
                (SELECT COUNT(*) FROM t_voto WHERE t_votacion_idVotacion = v.idVotacion AND opcionVoto = 'NO') as no
                FROM t_votacion v
                LEFT JOIN t_reunion r ON v.t_reunion_idReunion = r.idReunion
                WHERE v.t_minuta_idMinuta = :id1 OR r.t_minuta_idMinuta = :id2";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id1' => $idMinuta, ':id2' => $idMinuta]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function finalizarMinuta($idMinuta, $pathArchivo, $hash)
    {
        $sql = "UPDATE t_minuta SET estadoMinuta = 'APROBADA', pathArchivo = :path, hashValidacion = :hash, fechaAprobacion = NOW()
                WHERE idMinuta = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':path' => $pathArchivo, ':hash' => $hash, ':id' => $idMinuta]);
    }

    public function esPresidenteAsignado($idMinuta, $idUsuario)
    {
        $sql = "SELECT 1 FROM t_minuta m JOIN t_comision c ON m.t_comision_idComision = c.idComision
                WHERE m.idMinuta = :idMinuta AND c.t_usuario_idPresidente = :idUsuario";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':idMinuta' => $idMinuta, ':idUsuario' => $idUsuario]);
        return (bool) $stmt->fetchColumn();
    }

    public function actualizarHash($idMinuta, $hash)
    {
        $sql = "UPDATE t_minuta SET hashValidacion = :hash WHERE idMinuta = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':hash' => $hash, ':id' => $idMinuta]);
    }

    public function getMinutasAprobadasFiltradas($filtros, $limit = 10, $offset = 0)
    {
        $condiciones = ["m.estadoMinuta = 'APROBADA'"];
        $params = [];

        $sqlWhere = " WHERE " . implode(" AND ", $condiciones);

        $sqlData = "SELECT m.idMinuta, m.fechaMinuta, m.pathArchivo, r.nombreReunion, c.nombreComision,
                    (SELECT COUNT(*) FROM t_adjunto a WHERE a.t_minuta_idMinuta = m.idMinuta AND a.tipoAdjunto != 'asistencia') as numAdjuntos
                    FROM t_minuta m
                    LEFT JOIN t_reunion r ON r.t_minuta_idMinuta = m.idMinuta
                    LEFT JOIN t_comision c ON m.t_comision_idComision = c.idComision
                    $sqlWhere
                    ORDER BY m.idMinuta DESC LIMIT :limit OFFSET :offset";

        $sqlCount = "SELECT COUNT(DISTINCT m.idMinuta) FROM t_minuta m $sqlWhere";

        try {
            $stmtCount = $this->conn->prepare($sqlCount);
            $stmtCount->execute($params);
            $totalRegistros = $stmtCount->fetchColumn();

            $stmt = $this->conn->prepare($sqlData);
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val);
            }
            $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
            $stmt->execute();

            return ['data' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'total' => $totalRegistros];
        } catch (PDOException $e) {
            return ['error' => true];
        }
    }

    public function getAdjuntosPorMinuta($idMinuta)
    {
        $sql = "SELECT * FROM t_adjunto WHERE t_minuta_idMinuta = :id AND tipoAdjunto != 'asistencia'";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $idMinuta]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAdjuntoPorId($idAdjunto)
    {
        $stmt = $this->conn->prepare("SELECT * FROM t_adjunto WHERE idAdjunto = :id");
        $stmt->execute([':id' => $idAdjunto]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getAsistenciaDetallada($idMinuta)
    {
        // 1. Obtenemos datos de la reunión solo para saber hora inicio (visual)
        $sqlReunion = "SELECT fechaInicioReunion FROM t_reunion WHERE t_minuta_idMinuta = :id";
        $stmtR = $this->conn->prepare($sqlReunion);
        $stmtR->execute([':id' => $idMinuta]);
        $datosReunion = $stmtR->fetch(\PDO::FETCH_ASSOC);

        $inicioReunion = $datosReunion['fechaInicioReunion'] ?? null;

        $condicionAsistencia = "a.t_minuta_idMinuta = :idMinuta";
        
        $sql = "SELECT 
                    u.idUsuario, 
                    u.pNombre, u.aPaterno, 
                    a.fechaRegistroAsistencia,
                    a.origenAsistencia,
                    a.t_minuta_idMinuta as idMinutaMarcada, 
                    CASE WHEN a.estadoAsistencia = 'PRESENTE' THEN 1 ELSE 0 END as estaPresente
                FROM t_usuario u
                LEFT JOIN t_asistencia a ON u.idUsuario = a.t_usuario_idUsuario AND $condicionAsistencia
                WHERE u.tipoUsuario_id IN (1, 3)
                AND u.estado = 1 
                ORDER BY u.pNombre ASC, u.aPaterno ASC";

        $stmt = $this->conn->prepare($sql);
 
        $stmt->execute([':idMinuta' => $idMinuta]);
        $listado = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($listado as &$consejero) {
            $consejero['estado_visual'] = 'ausente';

            if ($consejero['estaPresente']) {
                
                if ($consejero['origenAsistencia'] === 'SECRETARIO') {
                    $consejero['estado_visual'] = 'manual';
                } elseif ($inicioReunion) {
                    $horaInicio = strtotime($inicioReunion);
                    $horaLlegada = strtotime($consejero['fechaRegistroAsistencia']);
                    
                    if ($horaLlegada) {
                        $diferenciaMinutos = ($horaLlegada - $horaInicio) / 60;
                        if ($diferenciaMinutos <= 30) {
                            $consejero['estado_visual'] = 'a_tiempo';
                        } else {
                            $consejero['estado_visual'] = 'atrasado';
                        }
                    } else {
                        $consejero['estado_visual'] = 'a_tiempo';
                    }
                } else {
                    $consejero['estado_visual'] = 'a_tiempo';
                }
            }
        }

        return [
            'inicio_reunion' => $inicioReunion,
            'asistentes' => $listado
        ];
    }

    private function corregirAsistencia($idUsuario, $idMinutaCorrecta, $fecha)
    {
        $sql = "UPDATE t_asistencia SET t_minuta_idMinuta = :idMinuta
                WHERE t_usuario_idUsuario = :idUsuario AND fechaRegistroAsistencia = :fecha";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':idMinuta' => $idMinutaCorrecta, ':idUsuario' => $idUsuario, ':fecha' => $fecha]);
    }

    public function alternarAsistencia($idMinuta, $idUsuario, $estado)
    {
        try {
            // 1. Verificar si existe el registro
            $sqlCheck = "SELECT idAsistencia FROM t_asistencia 
                         WHERE t_minuta_idMinuta = :idMinuta 
                         AND t_usuario_idUsuario = :idUsuario";
            $stmtCheck = $this->conn->prepare($sqlCheck);
            $stmtCheck->execute([':idMinuta' => $idMinuta, ':idUsuario' => $idUsuario]);
            $idExistente = $stmtCheck->fetchColumn();

            $textoEstado = $estado ? 'PRESENTE' : 'AUSENTE';

            if ($idExistente) {
                // UPDATE
                $sql = "UPDATE t_asistencia 
                        SET fechaRegistroAsistencia = NOW(), 
                            origenAsistencia = 'SECRETARIO', 
                            estadoAsistencia = :nuevoEstado 
                        WHERE idAsistencia = :idAsistencia";
                
                $stmt = $this->conn->prepare($sql);
                return $stmt->execute([
                    ':nuevoEstado' => $textoEstado, 
                    ':idAsistencia' => $idExistente
                ]);
            } else {
                // INSERT
                $sql = "INSERT INTO t_asistencia (t_minuta_idMinuta, t_usuario_idUsuario, t_tipoReunion_idTipoReunion, fechaRegistroAsistencia, origenAsistencia, estadoAsistencia) 
                        VALUES (:idMinuta, :idUsuario, 1, NOW(), 'SECRETARIO', :nuevoEstado)";
                
                $stmt = $this->conn->prepare($sql);
                return $stmt->execute([
                    ':idMinuta' => $idMinuta, 
                    ':idUsuario' => $idUsuario, 
                    ':nuevoEstado' => $textoEstado
                ]);
            }
        } catch (Exception $e) {
            return false;
        }
    }

    public function cerrarVotacion($idVotacion)
    {
        $sql = "UPDATE t_votacion SET habilitada = 0 WHERE idVotacion = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':id' => $idVotacion]);
    }

    public function getResultadosVotacion($idMinuta)
    {
        // 1. Obtener lista de asistentes (Consejeros)
        $sqlAsistentes = "SELECT u.idUsuario, u.pNombre, u.aPaterno
                          FROM t_asistencia a
                          JOIN t_usuario u ON a.t_usuario_idUsuario = u.idUsuario
                          WHERE a.t_minuta_idMinuta = :id
                          AND a.estadoAsistencia = 'PRESENTE'
                          AND u.estado = 1
                          ORDER BY u.pNombre ASC, u.aPaterno ASC";

        $stmtA = $this->conn->prepare($sqlAsistentes);
        $stmtA->execute([':id' => $idMinuta]);
        $asistentes = $stmtA->fetchAll(\PDO::FETCH_ASSOC);

        // 2. Obtener las votaciones de esta minuta
        $sqlV = "SELECT v.idVotacion, v.nombreVotacion, v.habilitada, v.fechaCreacion, c.nombreComision
                 FROM t_votacion v
                 LEFT JOIN t_comision c ON v.idComision = c.idComision
                 WHERE v.t_minuta_idMinuta = :id
                 ORDER BY v.idVotacion DESC";
        $stmtV = $this->conn->prepare($sqlV);
        $stmtV->execute([':id' => $idMinuta]);
        $votaciones = $stmtV->fetchAll(\PDO::FETCH_ASSOC);

        // 3. Procesar cada votación
        foreach ($votaciones as &$v) {
            $sqlVotos = "SELECT t_usuario_idUsuario, opcionVoto 
                         FROM t_voto 
                         WHERE t_votacion_idVotacion = :idVoto";

            $stmtVotos = $this->conn->prepare($sqlVotos);
            $stmtVotos->execute([':idVoto' => $v['idVotacion']]);

            $votosRegistrados = $stmtVotos->fetchAll(\PDO::FETCH_KEY_PAIR);

            $listaDetallada = [];
            $contadores = ['SI' => 0, 'NO' => 0, 'ABS' => 0, 'PEND' => 0];

            foreach ($asistentes as $persona) {
                $uid = $persona['idUsuario'];
                $nombreCompleto = $persona['pNombre'] . ' ' . $persona['aPaterno'];

                $votoRaw = $votosRegistrados[$uid] ?? 'PENDIENTE';
                $voto = strtoupper(trim($votoRaw));

                $claseColor = 'bg-light text-secondary border-secondary';

                if ($voto === 'SI') {
                    $claseColor = 'bg-success text-white border-success';
                    $contadores['SI']++;
                } elseif ($voto === 'NO') {
                    $claseColor = 'bg-danger text-white border-danger';
                    $contadores['NO']++;
                } elseif ($voto === 'ABSTENCION') {
                    $claseColor = 'bg-warning text-dark border-warning';
                    $contadores['ABS']++;
                } else {
                    $contadores['PEND']++;
                }

                $listaDetallada[] = [
                    'nombre' => $nombreCompleto,
                    'voto' => $voto,
                    'clase' => $claseColor
                ];
            }

            $v['detalle_asistentes'] = $listaDetallada;
            $v['contadores'] = $contadores;

            if ($contadores['SI'] > $contadores['NO']) $v['resultado'] = 'APROBADO';
            elseif ($contadores['NO'] > $contadores['SI']) $v['resultado'] = 'RECHAZADO';
            elseif ($contadores['SI'] == $contadores['NO'] && $contadores['SI'] > 0) $v['resultado'] = 'EMPATE';
            else $v['resultado'] = 'SIN DATOS';
        }

        return $votaciones;
    }

    public function getDetalleVotos($idVotacion)
    {
        $sql = "SELECT u.pNombre, u.aPaterno, vo.opcionVoto, vo.fechaVoto
                FROM t_voto vo
                JOIN t_usuario u ON vo.t_usuario_idUsuario = u.idUsuario
                WHERE vo.t_votacion_idVotacion = :id
                AND u.estado = 1
                ORDER BY u.aPaterno ASC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $idVotacion]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function cerrarReunionDB($idMinuta)
    {
        $sql = "UPDATE t_reunion SET vigente = 0, fechaTerminoReunion = NOW()
                WHERE t_minuta_idMinuta = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':id' => $idMinuta]);
    }

    public function obtenerDatosReunion($idMinuta)
    {
        $sql = "SELECT r.nombreReunion, c.nombreComision, m.fechaMinuta
                FROM t_minuta m
                LEFT JOIN t_reunion r ON m.idMinuta = r.t_minuta_idMinuta
                LEFT JOIN t_comision c ON m.t_comision_idComision = c.idComision
                WHERE m.idMinuta = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $idMinuta]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function verificarEstadoReunion($idMinuta)
    {
        $sql = "SELECT r.vigente, r.fechaInicioReunion, r.fechaTerminoReunion, m.asistencia_validada
                FROM t_minuta m
                LEFT JOIN t_reunion r ON m.idMinuta = r.t_minuta_idMinuta
                WHERE m.idMinuta = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $idMinuta]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public function iniciarReunionDB($idMinuta)
    {
        $sql = "UPDATE t_reunion SET vigente = 1, fechaInicioReunion = NOW()
                WHERE t_minuta_idMinuta = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':id' => $idMinuta]);
    }

    public function generarPlanillaAsistencia($idMinuta)
    {
        $sql = "INSERT INTO t_asistencia 
                (
                    t_usuario_idUsuario,
                    t_minuta_idMinuta,
                    t_tipoReunion_idTipoReunion,
                    estadoAsistencia,
                    origenAsistencia,
                    fechaRegistroAsistencia,
                    fechaMarca
                )
                SELECT 
                    idUsuario,
                    :idMinuta,
                    1,
                    'AUSENTE',
                    'SISTEMA',
                    NOW(),
                    NULL
                FROM t_usuario 
                WHERE tipoUsuario_id IN (1, 3) 
                AND estado = 1
                AND NOT EXISTS (
                    SELECT 1 FROM t_asistencia 
                    WHERE t_minuta_idMinuta = :idMinutaCheck 
                    AND t_usuario_idUsuario = t_usuario.idUsuario
                )";

        $stmt = $this->conn->prepare($sql);
        
        $stmt->execute([
            ':idMinuta' => $idMinuta,
            ':idMinutaCheck' => $idMinuta
        ]);
    }

    public function registrarDocumentoAsistencia($idMinuta, $path, $hash)
    {
        $sql = "INSERT INTO t_adjunto 
                (pathAdjunto, t_minuta_idMinuta, tipoAdjunto, hash_validacion) 
                VALUES 
                (:path, :idMinuta, 'asistencia', :hash)";
        
        $stmt = $this->conn->prepare($sql);
        
        try {
            return $stmt->execute([
                ':path' => $path,
                ':idMinuta' => $idMinuta,
                ':hash' => $hash
            ]);
        } catch (Exception $e) {
            error_log("Error guardando hash asistencia: " . $e->getMessage());
            return false;
        }
    }

    public function getPendientesPresidente($idPresidente)
    {
        $sql = "SELECT
                    m.idMinuta,
                    m.fechaMinuta,
                    m.horaMinuta,
                    m.estadoMinuta,
                    c.nombreComision,
                    r.nombreReunion,
                    CONCAT(us.pNombre, ' ', us.aPaterno) as nombreSecretario,
                    
                    -- CONTEO DINÁMICO DE FIRMAS
                    (SELECT COUNT(*) FROM t_aprobacion_minuta 
                     WHERE t_minuta_idMinuta = m.idMinuta AND estado_firma = 'FIRMADO') as firmas_actuales,
                    
                    -- TOTAL REQUERIDO (Guardado al enviar a firma, o calculado al vuelo)
                    m.presidentesRequeridos,
                    
                    -- ESTADO DE MI FIRMA (El usuario logueado)
                    ap.estado_firma as mi_estado_firma,
                    
                    -- SI HAY FEEDBACK RESUELTO (Para mostrar alerta 'Corregido')
                    (SELECT COUNT(*) FROM t_minuta_feedback
                     WHERE t_minuta_idMinuta = m.idMinuta
                     AND resuelto = 1
                     AND t_usuario_idPresidente = :idUserFeedback) as correcciones_realizadas

                FROM t_minuta m
                JOIN t_aprobacion_minuta ap ON m.idMinuta = ap.t_minuta_idMinuta
                LEFT JOIN t_reunion r ON m.idMinuta = r.t_minuta_idMinuta
                LEFT JOIN t_comision c ON m.t_comision_idComision = c.idComision
                LEFT JOIN t_usuario us ON m.t_usuario_idSecretario = us.idUsuario
                
                WHERE ap.t_usuario_idPresidente = :idUserMain
                
                -- MOSTRAR SI:
                -- 1. Mi estado es EN_ESPERA (tengo que firmar)
                -- 2. Mi estado es REQUIERE_REVISION (se corrigió y debo revisar de nuevo)
                -- 3. Mi estado es FIRMADO pero la minuta sigue PARCIAL (esperando a otros)
                
                AND m.estadoMinuta IN ('PENDIENTE', 'PARCIAL', 'REQUIERE_REVISION')
                
                ORDER BY m.idMinuta DESC";

        $stmt = $this->conn->prepare($sql);

        $stmt->execute([
            ':idUserFeedback' => $idPresidente,
            ':idUserMain'     => $idPresidente
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function getDatosNotificacion($idMinuta)
    {
        // 1. Obtener datos del Secretario Técnico (ST) y la Minuta
        $sqlST = "SELECT m.idMinuta, m.fechaMinuta, 
                         u.correo as correo_st, CONCAT(u.pNombre, ' ', u.aPaterno) as nombre_st,
                         c.nombreComision as nombre_comision_principal
                  FROM t_minuta m
                  LEFT JOIN t_usuario u ON m.t_usuario_idSecretario = u.idUsuario
                  LEFT JOIN t_comision c ON m.t_comision_idComision = c.idComision
                  WHERE m.idMinuta = :id";
        
        $stmt = $this->conn->prepare($sqlST);
        $stmt->execute([':id' => $idMinuta]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$data) return null;

        // 2. Obtener correos de TODOS los Presidentes involucrados (Mixtas)
        // Reutilizamos la lógica de la tabla de aprobaciones que ya tiene a todos mapeados
        $sqlPresis = "SELECT u.correo, CONCAT(u.pNombre, ' ', u.aPaterno) as nombre
                      FROM t_aprobacion_minuta ap
                      JOIN t_usuario u ON ap.t_usuario_idPresidente = u.idUsuario
                      WHERE ap.t_minuta_idMinuta = :id";
        
        $stmtP = $this->conn->prepare($sqlPresis);
        $stmtP->execute([':id' => $idMinuta]);
        $presidentes = $stmtP->fetchAll(PDO::FETCH_ASSOC);

        return [
            'st' => [
                'email' => $data['correo_st'],
                'nombre' => $data['nombre_st']
            ],
            'presidentes' => $presidentes, // Array de [correo, nombre]
            'minuta' => [
                'id' => $data['idMinuta'],
                'fecha' => $data['fechaMinuta'],
                'comision' => $data['nombre_comision_principal']
            ]
        ];
    }

}