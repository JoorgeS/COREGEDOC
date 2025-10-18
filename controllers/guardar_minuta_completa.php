<?php
// Incluir la configuración de tu base de datos
require_once __DIR__ . '/../cfg/config.php';

header('Content-Type: application/json');

// Obtener y decodificar los datos JSON
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!isset($data['minuta']) || !isset($data['asistencia']) || !isset($data['temas'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Faltan datos requeridos (minuta, asistencia o temas).']);
    exit;
}

$datosMinuta = $data['minuta'];
$asistenciaIDs = $data['asistencia'];
$temasData = $data['temas'];

class MinutaManager extends BaseConexion
{
    private $db;

    public function __construct()
    {
        $this->db = $this->conectar();
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function guardarMinutaCompleta($datosMinuta, $asistenciaIDs, $temasData)
    {
        try {
            // 1. INICIAR TRANSACCIÓN
            $this->db->beginTransaction();
            $idMinuta = null;

            // 2. INSERTAR EN T_MINUTA (Encabezado)
            // Usamos los NUEVOS nombres de campos: t_comision_idComision y t_usuario_idPresidente
            // Ejemplo del INSERT en t_minuta:

            // En guardar_minuta_completa.php - Revisa la lista de columnas (13 campos en total + los 2 nuevos):
            $sqlMinuta = "INSERT INTO t_minuta (
                pathArchivo, 
                t_comision_idComision, 
                t_usuario_idPresidente, 
                horaMinuta, 
                fechaMinuta,
                t_acuerdo_idAcuerdo, 
                t_propuesta_idPropuesta,
                t_voto_idVoto, 
                t_voto_t_usuario_idUsuario, 
                t_voto_t_propuesta_idPropuesta, 
                t_voto_t_propuesta_t_acuerdo_idAcuerdo, 
                t_voto_t_propuesta_t_acuerdo_t_tipoReunion_idTipoReunion
              ) 
              VALUES (
                :path, :comision_id, :presidente_id, :hora, :fecha, 
                NULL, NULL, NULL, NULL, NULL, NULL, NULL
              )";
            // TOTAL DE COLUMNAS: 12 (pathArchivo + 4 básicos + 7 FKs anidadas).
            // TOTAL DE VALORES: 5 (parámetros) + 7 (NULL) = 12. ¡Debe coincidir!

            $stmtMinuta = $this->db->prepare($sqlMinuta);
            $stmtMinuta->execute([
                // Los IDs se obtienen del frontend. Ya no se usa el nombreComision (VARCHAR)
                ':comision_id' => $datosMinuta['t_comision_idComision'],
                ':presidente_id' => $datosMinuta['t_usuario_idPresidente'],
                ':hora' => $datosMinuta['horaMinuta'],
                ':fecha' => $datosMinuta['fechaMinuta'],
                ':path' => $datosMinuta['pathArchivo'] // Asumiendo que es una cadena vacía inicialmente
            ]);

            $idMinuta = $this->db->lastInsertId();
            if (!$idMinuta) {
                throw new Exception("Error al obtener idMinuta después de la inserción.");
            }

            // 3. INSERTAR EN T_ASISTENCIA
            // Usando el NUEVO campo t_usuario_idUsuario
            // Asumimos t_tipoReunion_idTipoReunion es 1 o debe ser pasado desde el frontend.
            $idTipoReunion = 1;

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

            // 4. INSERTAR TEMAS (t_tema y t_acuerdo)
            $idTipoReunionTema = 1; // Asumimos el mismo ID de tipo reunión
            $idAcuerdo = null;

            foreach ($temasData as $tema) {
                // 4.1. Insertar en t_tema
                // En guardar_minuta_completa.php - Revisa la lista de columnas (4 campos, ya que idTema es AUTO_INCREMENT)
                $sqlTema = "INSERT INTO t_tema (nombreTema, objetivo, compromiso, observacion) 
            VALUES (:nombre, :objetivo, :compromiso, :observacion)";
                // TOTAL DE COLUMNAS: 4.
                // TOTAL DE VALORES: 4. ¡Debe coincidir!
                $stmtTema = $this->db->prepare($sqlTema);
                $stmtTema->execute([
                    ':nombre' => $tema['nombreTema'],
                    ':objetivo' => $tema['objetivo'],
                    ':compromiso' => $tema['compromiso'],
                    ':observacion' => $tema['observacion']
                ]);
                $idTema = $this->db->lastInsertId();

                // 4.2. Insertar en t_acuerdo
                // NOTA: T_acuerdo usa idTema y idTipoReunion (asumido 1)
                $sqlAcuerdo = "INSERT INTO t_acuerdo (descAcuerdo, t_tipoReunion_idTipoReunion, t_tema_idTema) 
                               VALUES (:descAcuerdo, :idTipoReunion, :idTema)";
                $stmtAcuerdo = $this->db->prepare($sqlAcuerdo);
                $stmtAcuerdo->execute([
                    ':descAcuerdo' => $tema['descAcuerdo'],
                    ':idTipoReunion' => $idTipoReunionTema,
                    ':idTema' => $idTema
                ]);
                $idAcuerdo = $this->db->lastInsertId();
            }
            // NOTA: Para un desarrollo completo, la tabla t_minuta DEBERÍA actualizarse con el idAcuerdo del último tema
            // o se debería crear una tabla de relación N:M (t_minuta_has_t_tema). Por simplicidad, omitiremos la actualización de t_minuta.

            // 5. COMMIT
            $this->db->commit();

            return ['status' => 'success', 'message' => 'Minuta guardada con éxito.', 'idMinuta' => $idMinuta];
        } catch (Exception $e) {
            // ROLLBACK en caso de cualquier error
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            // Devolver el mensaje de error de la BD (solo en desarrollo)
            return ['status' => 'error', 'message' => 'Error en el proceso de base de datos.', 'error' => $e->getMessage()];
        }
    }
}

// --- Ejecución del Controlador ---
try {
    $manager = new MinutaManager();
    $result = $manager->guardarMinutaCompleta($datosMinuta, $asistenciaIDs, $temasData);
    echo json_encode($result);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error interno del servidor.', 'error' => $e->getMessage()]);
}
