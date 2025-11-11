<?php
// controllers/VotoController.php

// Solo requerimos dependencias si la clase no existe (Autoload o uso directo)
if (!class_exists('conectorDB')) {
    require_once __DIR__ . '/../cfg/config.php';
    require_once __DIR__ . '/../class/class.conectorDB.php';
}

// Clase unificada para manejar la lógica de votos
class VotoController
{
    private $pdo;

    public function __construct()
    {
        $conector = new conectorDB();
        $this->pdo = $conector->getDatabase();
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    // ======================================================
    // 1. Lógica para el NUEVO sistema de Votación (t_votacion)
    //    Usado por el Secretario Técnico (ST) para registro manual.
    // ======================================================
    public function registrarVotoVotacion(int $idVotacion, int $idUsuario, string $opcionVoto, ?int $idSecretario)
    {
        if ($idVotacion <= 0 || $idUsuario <= 0 || !in_array($opcionVoto, ['SI', 'NO', 'ABSTENCION'])) {
            return ['status' => 'error', 'message' => 'Datos de voto inválidos para t_votacion.'];
        }

        try {
            // 1. Verificar si ya existe un voto (para actualizar o reemplazar)
            // 🛑 NOTA: Asumimos que tu tabla t_voto ahora tiene la columna t_votacion_idVotacion
            $sqlCheck = "SELECT idVoto FROM t_voto 
                         WHERE t_usuario_idUsuario = :idUsuario 
                         AND t_votacion_idVotacion = :idVotacion";
            $stmtCheck = $this->pdo->prepare($sqlCheck);
            $stmtCheck->execute([':idUsuario' => $idUsuario, ':idVotacion' => $idVotacion]);
            $votoExistente = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            // Determinar el origen del voto y si es modificación
            $origen = $idSecretario ? 'MANUAL-ST' : 'AUTOGESTION-VOTACION';
            $origen = $votoExistente ? $origen . '-MOD' : $origen;

            if ($votoExistente) {
                // Actualizar voto existente
                $sql_update = "UPDATE t_voto 
                               SET opcionVoto = :opcionVoto, 
                                   fechaVoto = NOW(), 
                                   origenVoto = :origen,
                                   t_usuario_idUsuarioRegistra = :idSecretario
                               WHERE idVoto = :idVoto";
                $stmt_update = $this->pdo->prepare($sql_update);
                $stmt_update->execute([
                    ':opcionVoto' => $opcionVoto,
                    ':origen' => $origen,
                    ':idSecretario' => $idSecretario,
                    ':idVoto' => $votoExistente['idVoto']
                ]);
                return ['status' => 'success', 'message' => 'Voto actualizado correctamente.'];
            } else {
                // Insertar nuevo voto
                $sql_insert = "INSERT INTO t_voto 
                               (t_usuario_idUsuario, t_votacion_idVotacion, opcionVoto, fechaVoto, origenVoto, t_usuario_idUsuarioRegistra) 
                               VALUES (:idUsuario, :idVotacion, :opcionVoto, NOW(), :origen, :idSecretario)";

                $stmt_insert = $this->pdo->prepare($sql_insert);
                $stmt_insert->execute([
                    ':idUsuario' => $idUsuario,
                    ':idVotacion' => $idVotacion,
                    ':opcionVoto' => $opcionVoto,
                    ':origen' => $origen,
                    ':idSecretario' => $idSecretario
                ]);
                return ['status' => 'success', 'message' => 'Voto registrado con éxito.'];
            }
        } catch (PDOException $e) {
            error_log("Error VotoController::registrarVotoVotacion: " . $e->getMessage());
            return ['status' => 'error', 'message' => 'Error al registrar el voto en DB.', 'error' => $e->getMessage()];
        }
    }


    // ======================================================
    // 2. Lógica LEGACY para Propuestas (Mantiene la funcionalidad original)
    //    Esto asume que el archivo original maneja la autogestión de votos.
    // ======================================================
    public function registrarVotoPropuesta(int $idPropuesta, int $idUsuario, string $opcionVoto)
    {
        // 1. Obtener la Minuta asociada a la Propuesta
        $sqlMinutaId = "
            SELECT t.t_minuta_idMinuta
            FROM t_propuesta p
            JOIN t_acuerdo a ON p.t_acuerdo_idAcuerdo = a.idAcuerdo
            JOIN t_tema t ON a.t_tema_idTema = t.idTema
            WHERE p.idPropuesta = :idPropuesta
        ";
        $stmtMinutaId = $this->pdo->prepare($sqlMinutaId);
        $stmtMinutaId->execute([':idPropuesta' => $idPropuesta]);
        $minuta = $stmtMinutaId->fetch(PDO::FETCH_ASSOC);

        if (!$minuta || empty($minuta['t_minuta_idMinuta'])) {
            return ['status' => 'error', 'message' => 'No se pudo encontrar la reunión asociada a esta votación.'];
        }

        $idMinuta = $minuta['t_minuta_idMinuta'];

        // 2. Validar que el usuario esté presente en esa Minuta
        $sqlCheckAsistencia = "
            SELECT COUNT(*) AS presente
            FROM t_asistencia
            WHERE t_minuta_idMinuta = :idMinuta AND t_usuario_idUsuario = :idUsuario
        ";
        $stmtCheckAsistencia = $this->pdo->prepare($sqlCheckAsistencia);
        $stmtCheckAsistencia->execute([
            ':idMinuta' => $idMinuta,
            ':idUsuario' => $idUsuario
        ]);
        $asistencia = $stmtCheckAsistencia->fetch(PDO::FETCH_ASSOC);

        // 3. Si no está presente, denegar el voto.
        if (!$asistencia || (int)$asistencia['presente'] === 0) {
            return ['status' => 'error', 'message' => 'Error: No puede votar si no ha registrado asistencia para esta reunión.'];
        }

        // 4. Verificar que la propuesta exista
        $sql = "SELECT idPropuesta FROM t_propuesta WHERE idPropuesta = :idPropuesta";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':idPropuesta' => $idPropuesta]);
        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            return ['status' => 'error', 'message' => 'La propuesta de votación no fue encontrada.'];
        }

        // 5. Verificar si el usuario ya votó en esta propuesta
        $sql = "SELECT idVoto FROM t_voto 
                 WHERE t_usuario_idUsuario = :idUsuario 
                 AND t_propuesta_idPropuesta = :idPropuesta";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':idUsuario' => $idUsuario, ':idPropuesta' => $idPropuesta]);
        $votoExistente = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($votoExistente) {
            // Actualizar voto existente
            $sql_update = "UPDATE t_voto 
                           SET opcionVoto = :opcionVoto, fechaVoto = NOW(), origenVoto = 'AUTOGESTION-MOD'
                           WHERE idVoto = :idVoto";
            $stmt_update = $this->pdo->prepare($sql_update);
            $stmt_update->execute([
                ':opcionVoto' => $opcionVoto,
                ':idVoto' => $votoExistente['idVoto']
            ]);
            return ['status' => 'success', 'message' => 'Voto actualizado con éxito.'];
        } else {
            // Insertar nuevo voto
            // 🛑 NOTA: Esta consulta NO usa t_votacion_idVotacion, lo cual es correcto para el flujo de Propuesta.
            $sql_insert = "INSERT INTO t_voto (t_usuario_idUsuario, t_propuesta_idPropuesta, opcionVoto, fechaVoto, origenVoto) 
                           VALUES (:idUsuario, :idPropuesta, :opcionVoto, NOW(), 'AUTOGESTION')";

            $stmt_insert = $this->pdo->prepare($sql_insert);
            $stmt_insert->execute([
                ':idUsuario' => $idUsuario,
                ':idPropuesta' => $idPropuesta,
                ':opcionVoto' => $opcionVoto
            ]);
            return ['status' => 'success', 'message' => 'Voto registrado con éxito.'];
        }
    }
}


// ====================================================================================================
// --- BLOQUE DE EJECUCIÓN (Asegura que el antiguo flujo de script directo no se rompa si se usa) ---
// ====================================================================================================
if (basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {

    // Este código simula el comportamiento original del script si se llama directamente
    // (Ej: por el voto de autogestión de un usuario, que aún puede usar 'idPropuesta')
    $idPropuesta = $_POST['idPropuesta'] ?? null;
    $idUsuario = $_SESSION['idUsuario'] ?? null;
    $opcionVoto = $_POST['opcionVoto'] ?? null;

    if ($idPropuesta && $idUsuario && $opcionVoto) {
        $votoCtrl = new VotoController();
        $response = $votoCtrl->registrarVotoPropuesta((int)$idPropuesta, (int)$idUsuario, $opcionVoto);
        // Si hay un error de asistencia, usa el código de error 403
        if ($response['status'] === 'error' && strpos($response['message'], 'asistencia') !== false) {
            http_response_code(403);
        } elseif ($response['status'] === 'error') {
            http_response_code(500);
        }
        echo json_encode($response);
        exit;
    } elseif ($idPropuesta || $idUsuario || $opcionVoto) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Datos incompletos para el voto de Propuesta.']);
        exit;
    }
    // Si no es un POST o faltan datos, el script simplemente termina sin hacer nada (comportamiento habitual de un controlador vacío).
}
