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



    // --- FUNCIONES EXISTENTES ---



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



    public function guardarTemas($idMinuta, $temas)

    {

        try {

            $this->conn->beginTransaction();



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



    public function enviarParaFirma($idMinuta, $idUsuarioLogueado)

    {

        try {

            $this->conn->beginTransaction();

            $sqlPresi = "SELECT c.t_usuario_idPresidente FROM t_comision c

                         JOIN t_minuta m ON m.t_comision_idComision = c.idComision

                         WHERE m.idMinuta = :idMinuta";

            $stmtP = $this->conn->prepare($sqlPresi);

            $stmtP->execute([':idMinuta' => $idMinuta]);

            $idPresidente = $stmtP->fetchColumn();



            if (!$idPresidente) throw new Exception("No se encontró presidente.");



            $sqlDel = "DELETE FROM t_aprobacion_minuta WHERE t_minuta_idMinuta = :idMinuta";

            $stmtD = $this->conn->prepare($sqlDel);

            $stmtD->execute([':idMinuta' => $idMinuta]);



            $sqlIns = "INSERT INTO t_aprobacion_minuta (t_minuta_idMinuta, t_usuario_idPresidente, estado_firma, fechaAprobacion)

                       VALUES (:idMinuta, :idPresidente, 'EN_ESPERA', NOW())";

            $stmtI = $this->conn->prepare($sqlIns);

            $stmtI->execute([':idMinuta' => $idMinuta, ':idPresidente' => $idPresidente]);



            $sqlUpdate = "UPDATE t_minuta SET estadoMinuta = 'PENDIENTE' WHERE idMinuta = :idMinuta";

            $stmtU = $this->conn->prepare($sqlUpdate);

            $stmtU->execute([':idMinuta' => $idMinuta]);



            $this->logSeguimiento($idMinuta, $idUsuarioLogueado, 'ENVIO_FIRMA', 'Minuta enviada a firma.');

            $this->conn->commit();

            return true;
        } catch (Exception $e) {

            $this->conn->rollBack();

            throw $e;
        }
    }



    public function firmarMinuta($idMinuta, $idUsuario)

    {

        try {

            $this->conn->beginTransaction();

            $sqlFirma = "UPDATE t_aprobacion_minuta SET estado_firma = 'FIRMADO', fechaAprobacion = NOW()

                         WHERE t_minuta_idMinuta = :idMinuta AND t_usuario_idPresidente = :idUsuario";

            $stmt = $this->conn->prepare($sqlFirma);

            $stmt->execute([':idMinuta' => $idMinuta, ':idUsuario' => $idUsuario]);



            $sqlPendientes = "SELECT COUNT(*) FROM t_aprobacion_minuta

                              WHERE t_minuta_idMinuta = :idMinuta AND estado_firma != 'FIRMADO'";

            $stmtP = $this->conn->prepare($sqlPendientes);

            $stmtP->execute([':idMinuta' => $idMinuta]);

            $pendientes = $stmtP->fetchColumn();



            $nuevoEstado = ($pendientes == 0) ? 'APROBADA' : 'PARCIAL';

            $sqlUpdate = "UPDATE t_minuta SET estadoMinuta = :estado WHERE idMinuta = :idMinuta";

            $stmtU = $this->conn->prepare($sqlUpdate);

            $stmtU->execute([':estado' => $nuevoEstado, ':idMinuta' => $idMinuta]);



            $this->logSeguimiento($idMinuta, $idUsuario, 'FIRMA_RECIBIDA', "Nuevo estado: $nuevoEstado");

            $this->conn->commit();

            return ['status' => 'success', 'estado_nuevo' => $nuevoEstado];
        } catch (Exception $e) {

            $this->conn->rollBack();

            throw $e;
        }
    }



    public function guardarFeedback($idMinuta, $idUsuario, $textoFeedback)

    {

        try {

            $this->conn->beginTransaction();

            $sql = "INSERT INTO t_minuta_feedback (t_minuta_idMinuta, t_usuario_idPresidente, textoFeedback, fechaFeedback, resuelto)

                    VALUES (:idMinuta, :idUsuario, :texto, NOW(), 0)";

            $stmt = $this->conn->prepare($sql);

            $stmt->execute([':idMinuta' => $idMinuta, ':idUsuario' => $idUsuario, ':texto' => $textoFeedback]);



            $sqlUp = "UPDATE t_aprobacion_minuta SET estado_firma = 'REQUIERE_REVISION'

                      WHERE t_minuta_idMinuta = :idMinuta AND t_usuario_idPresidente = :idUsuario";

            $stmtUp = $this->conn->prepare($sqlUp);

            $stmtUp->execute([':idMinuta' => $idMinuta, ':idUsuario' => $idUsuario]);



            $sqlMin = "UPDATE t_minuta SET estadoMinuta = 'REQUIERE_REVISION' WHERE idMinuta = :idMinuta";

            $stmtMin = $this->conn->prepare($sqlMin);

            $stmtMin->execute([':idMinuta' => $idMinuta]);



            $this->logSeguimiento($idMinuta, $idUsuario, 'FEEDBACK_ENVIADO', 'Correcciones solicitadas.');

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



    // -------------------------------------------------------------

    // MODIFICADO: LÓGICA DE UPSERT (ACTUALIZAR SI EXISTE)

    // -------------------------------------------------------------

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

                // ============================================================

                // CASO 1: UPDATE (El más común, porque la fila ya existe en AUSENTE)

                // ============================================================

                $sqlUpdate = "UPDATE t_asistencia

                              SET fechaRegistroAsistencia = NOW(),

                                  fechaMarca = NOW(),

                                  origenAsistencia = 'AUTOREGISTRO',

                                  estadoAsistencia = 'PRESENTE'  /* <--- ESTO ES LO QUE FALTABA O FALLABA */

                              WHERE idAsistencia = :idAsistencia";



                $stmtUp = $this->conn->prepare($sqlUpdate);

                return $stmtUp->execute([':idAsistencia' => $idExistente]);
            } else {

                // ============================================================

                // CASO 2: INSERT (Por si acaso no existía)

                // ============================================================

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

        $sql = "SELECT ap.fechaAprobacion, CONCAT(u.pNombre, ' ', u.aPaterno) as nombrePresidente, c.nombreComision

                FROM t_aprobacion_minuta ap

                JOIN t_usuario u ON ap.t_usuario_idPresidente = u.idUsuario

                JOIN t_minuta m ON ap.t_minuta_idMinuta = m.idMinuta

                LEFT JOIN t_comision c ON m.t_comision_idComision = c.idComision

                WHERE ap.t_minuta_idMinuta = :id AND ap.estado_firma = 'FIRMADO' ORDER BY ap.fechaAprobacion ASC";

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

        // --- CORRECCIÓN CRÍTICA ---
        // Eliminamos la condición "OR" de tiempo. 
        // Ahora solo buscamos registros que coincidan EXACTAMENTE con esta minuta.
        // Esto evita que se "cuelen" asistencias de reuniones anteriores del mismo día.
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



    // -------------------------------------------------------------

    // MODIFICADO: LÓGICA DE UPSERT (ACTUALIZAR SI EXISTE) PARA SECRETARIO

    // -------------------------------------------------------------

    public function alternarAsistencia($idMinuta, $idUsuario, $estado)
    {
        try {
            // 1. Verificar si existe el registro (generalmente sí, porque se generó la planilla)
            $sqlCheck = "SELECT idAsistencia FROM t_asistencia 
                         WHERE t_minuta_idMinuta = :idMinuta 
                         AND t_usuario_idUsuario = :idUsuario";
            $stmtCheck = $this->conn->prepare($sqlCheck);
            $stmtCheck->execute([':idMinuta' => $idMinuta, ':idUsuario' => $idUsuario]);
            $idExistente = $stmtCheck->fetchColumn();

            // Definimos el estado texto según el booleano que llega del interruptor
            $textoEstado = $estado ? 'PRESENTE' : 'AUSENTE';

            if ($idExistente) {
                // UPDATE: Si ya existe, actualizamos el estado explícitamente
                $sql = "UPDATE t_asistencia 
                        SET fechaRegistroAsistencia = NOW(), 
                            origenAsistencia = 'SECRETARIO', 
                            estadoAsistencia = :nuevoEstado  -- <--- ESTO FALTABA
                        WHERE idAsistencia = :idAsistencia";
                
                $stmt = $this->conn->prepare($sql);
                return $stmt->execute([
                    ':nuevoEstado' => $textoEstado, 
                    ':idAsistencia' => $idExistente
                ]);
            } else {
                // INSERT: Si no existía (por seguridad), lo creamos con el estado correcto
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


    public function crearVotacion($idMinuta, $nombre, $idComision)

    {

        if (empty($idComision) || $idComision == 0) {

            // Manejo de error o lógica por defecto si idComision no es válido

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
        } catch (\PDOException $e) {

            throw new \Exception("Error al crear votación: La comisión asignada no es válida. (Detalle: " . $e->getMessage() . ")");
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
                          AND a.estadoAsistencia = 'PRESENTE' -- <--- ESTA LÍNEA ES LA CLAVE
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

        // 3. Procesar cada votación para pintar los cuadros
        foreach ($votaciones as &$v) {
            // --- CORRECCIÓN CRÍTICA AQUÍ ---
            // Antes buscabas 'idUsuario', ahora buscamos 't_usuario_idUsuario'
            // que es donde el VotacionController está guardando el ID.
            $sqlVotos = "SELECT t_usuario_idUsuario, opcionVoto 
                         FROM t_voto 
                         WHERE t_votacion_idVotacion = :idVoto";

            $stmtVotos = $this->conn->prepare($sqlVotos);
            $stmtVotos->execute([':idVoto' => $v['idVotacion']]);

            // Crea un array asociativo: [ 37 => 'SI', 40 => 'ABSTENCION' ]
            $votosRegistrados = $stmtVotos->fetchAll(\PDO::FETCH_KEY_PAIR);

            $listaDetallada = [];
            $contadores = ['SI' => 0, 'NO' => 0, 'ABS' => 0, 'PEND' => 0];

            foreach ($asistentes as $persona) {
                $uid = $persona['idUsuario'];
                $nombreCompleto = $persona['pNombre'] . ' ' . $persona['aPaterno'];

                // Buscamos si este usuario tiene voto registrado
                // Usamos trim() y strtoupper() por seguridad
                $votoRaw = $votosRegistrados[$uid] ?? 'PENDIENTE';
                $voto = strtoupper(trim($votoRaw));

                // Lógica de colores (Bootstrap classes)
                $claseColor = 'bg-light text-secondary border-secondary'; // Por defecto (Gris)

                if ($voto === 'SI') {
                    $claseColor = 'bg-success text-white border-success'; // Verde
                    $contadores['SI']++;
                } elseif ($voto === 'NO') {
                    $claseColor = 'bg-danger text-white border-danger'; // Rojo
                    $contadores['NO']++;
                } elseif ($voto === 'ABSTENCION') {
                    $claseColor = 'bg-warning text-dark border-warning'; // Amarillo
                    $contadores['ABS']++;
                } else {
                    $contadores['PEND']++; // Pendiente
                }

                $listaDetallada[] = [
                    'nombre' => $nombreCompleto,
                    'voto' => $voto,
                    'clase' => $claseColor
                ];
            }

            $v['detalle_asistentes'] = $listaDetallada;
            $v['contadores'] = $contadores;

            // Determinar resultado final texto
            if ($contadores['SI'] > $contadores['NO']) $v['resultado'] = 'APROBADO';
            elseif ($contadores['NO'] > $contadores['SI']) $v['resultado'] = 'RECHAZADO';
            elseif ($contadores['SI'] == $contadores['NO'] && $contadores['SI'] > 0) $v['resultado'] = 'EMPATE';
            else $v['resultado'] = 'SIN DATOS';
        }

        return $votaciones;
    }


    public function getDetalleVotos($idVotacion)
    {
        // CORRECCIÓN: 
        // 1. Usamos t_votacion_idVotacion y t_usuario_idUsuario (nombres correctos en BD)
        // 2. Filtramos AND u.estado = 1 para que no salgan los eliminados

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



    // --- NUEVAS FUNCIONES PARA CIERRE Y NOTIFICACIONES ---



    public function cerrarReunionDB($idMinuta)

    {

        // Marca la reunión como finalizada (vigente=0) y establece la hora de término actual

        // Solo si está asociada a esta minuta

        $sql = "UPDATE t_reunion SET vigente = 0, fechaTerminoReunion = NOW()

                WHERE t_minuta_idMinuta = :id";

        $stmt = $this->conn->prepare($sql);

        return $stmt->execute([':id' => $idMinuta]);
    }



    public function obtenerDatosReunion($idMinuta)

    {

        // Obtiene datos básicos para el correo y PDF

        $sql = "SELECT r.nombreReunion, c.nombreComision, m.fechaMinuta

                FROM t_minuta m

                LEFT JOIN t_reunion r ON m.idMinuta = r.t_minuta_idMinuta

                LEFT JOIN t_comision c ON m.t_comision_idComision = c.idComision

                WHERE m.idMinuta = :id";

        $stmt = $this->conn->prepare($sql);

        $stmt->execute([':id' => $idMinuta]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }



    public function getCorreosPresidentes($idMinuta)

    {

        // Obtiene nombre y correo del presidente asignado a la comisión de esta minuta

        $sql = "SELECT u.pNombre, u.aPaterno, u.correo

                FROM t_minuta m

                JOIN t_comision c ON m.t_comision_idComision = c.idComision

                JOIN t_usuario u ON c.t_usuario_idPresidente = u.idUsuario

                WHERE m.idMinuta = :id";

        $stmt = $this->conn->prepare($sql);

        $stmt->execute([':id' => $idMinuta]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function verificarEstadoReunion($idMinuta)

    {

        // Traemos 'vigente' y 'asistencia_validada' para saber el estado exacto

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

        // Pone vigente = 1 y actualiza la hora de inicio al momento real (NOW)

        $sql = "UPDATE t_reunion SET vigente = 1, fechaInicioReunion = NOW()

                WHERE t_minuta_idMinuta = :id";

        $stmt = $this->conn->prepare($sql);

        return $stmt->execute([':id' => $idMinuta]);
    }

    public function generarPlanillaAsistencia($idMinuta)
    {
        // CORRECCIÓN DEFINITIVA: 7 Columnas = 7 Valores
        
        $sql = "INSERT INTO t_asistencia 
                (
                    t_usuario_idUsuario,         /* 1 */
                    t_minuta_idMinuta,           /* 2 */
                    t_tipoReunion_idTipoReunion, /* 3 */
                    estadoAsistencia,            /* 4 */
                    origenAsistencia,            /* 5 */
                    fechaRegistroAsistencia,     /* 6 */
                    fechaMarca                   /* 7 */
                )
                SELECT 
                    idUsuario,                   /* 1 */
                    :idMinuta,                   /* 2 */
                    1,                           /* 3 (1 = Ordinaria) */
                    'AUSENTE',                   /* 4 */
                    'SISTEMA',                   /* 5 */
                    NOW(),                       /* 6 */
                    NULL                         /* 7 */
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
        // CORRECCIÓN FINAL:
        // 1. No incluimos 'idAdjunto' (es autoincrementable).
        // 2. No incluimos 'fechaSubida' (no existe en tu tabla).
        // 3. Solo insertamos las columnas que existen físicamente.
        
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

                    m.estadoMinuta, -- Necesitamos este campo para la vista

                    c.nombreComision,

                    r.nombreReunion,

                    CONCAT(us.pNombre, ' ', us.aPaterno) as nombreSecretario,

                    (SELECT COUNT(*) FROM t_aprobacion_minuta WHERE t_minuta_idMinuta = m.idMinuta AND estado_firma = 'FIRMADO') as firmas_actuales,

                    m.presidentesRequeridos,

                    ap.estado_firma as mi_estado_firma,

                   

                    (SELECT COUNT(*) FROM t_minuta_feedback

                     WHERE t_minuta_idMinuta = m.idMinuta

                     AND t_usuario_idPresidente = :idUserFeedback

                     AND resuelto = 1) as correcciones_realizadas



                FROM t_minuta m

                JOIN t_aprobacion_minuta ap ON m.idMinuta = ap.t_minuta_idMinuta

                LEFT JOIN t_reunion r ON m.idMinuta = r.t_minuta_idMinuta

                LEFT JOIN t_comision c ON m.t_comision_idComision = c.idComision

                LEFT JOIN t_usuario us ON m.t_usuario_idSecretario = us.idUsuario

               

                WHERE ap.t_usuario_idPresidente = :idUserMain

                -- Solo mostramos si la firma personal está EN_ESPERA o REQUIERE_REVISION

                AND (ap.estado_firma = 'EN_ESPERA' OR ap.estado_firma = 'REQUIERE_REVISION')

                -- Y permitimos que la minuta aparezca si está en revisión

                AND m.estadoMinuta IN ('PENDIENTE', 'PARCIAL', 'REQUIERE_REVISION')

                ORDER BY m.idMinuta DESC";



        $stmt = $this->conn->prepare($sql);



        $stmt->execute([

            ':idUserFeedback' => $idPresidente,

            ':idUserMain'     => $idPresidente

        ]);



        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
