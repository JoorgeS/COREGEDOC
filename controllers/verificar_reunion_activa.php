<?php
// /coregedoc/controllers/verificar_reunion_activa.php
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Seguridad y Datos de Sesión
if (!isset($_SESSION['idUsuario'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Acceso no autorizado.']);
    exit;
}
$idUsuarioLogueado = $_SESSION['idUsuario'];

require_once __DIR__ . '/../class/class.conectorDB.php';

$hayReunionHabilitadaParaAsistencia = false;
$ahora = new DateTime(); // Zona horaria de PHP (America/Santiago)

try {
    $db = new conectorDB();
    $pdo = $db->getDatabase();

    // 2. Definir la fecha de "hoy" (para filtrar reuniones pasadas)
    $hoyInicio = (new DateTime())->setTime(0, 0, 0);

    // 3. Obtener reuniones vigentes y futuras
    // (Lógica basada en sala_reuniones.php y asistencia_autogestion.php)
    $sql_reuniones = "SELECT 
                        r.fechaInicioReunion, m.idMinuta
                    FROM t_reunion r
                    JOIN t_minuta m ON r.t_minuta_idMinuta = m.idMinuta
                    WHERE r.vigente = 1 
                    AND m.estadoMinuta IN ('PENDIENTE', 'BORRADOR', 'PARCIAL')
                    AND r.fechaInicioReunion >= :hoyInicio";
    
    $stmt_reuniones = $pdo->prepare($sql_reuniones);
    $stmt_reuniones->execute([':hoyInicio' => $hoyInicio->format('Y-m-d H:i:s')]);
    $reunionesActivas = $stmt_reuniones->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($reunionesActivas)) {
        
        // 4. Obtener la asistencia previa del usuario
        //
        $idsMinutasActivas = array_column($reunionesActivas, 'idMinuta');
        $placeholders = implode(',', array_fill(0, count($idsMinutasActivas), '?'));
        
        $sql_asistencia = "SELECT t_minuta_idMinuta 
                           FROM t_asistencia 
                           WHERE t_usuario_idUsuario = ? 
                           AND t_minuta_idMinuta IN ({$placeholders})";
        
        $stmt_asistencia = $pdo->prepare($sql_asistencia);
        $params = array_merge([$idUsuarioLogueado], $idsMinutasActivas);
        $stmt_asistencia->execute($params);
        $asistenciaUsuario = $stmt_asistencia->fetchAll(PDO::FETCH_COLUMN, 0);
        
        // 5. Verificar CADA reunión
        foreach ($reunionesActivas as $reunion) {
            // Regla 1: ¿Ya asistió?
            $yaAsistio = in_array($reunion['idMinuta'], $asistenciaUsuario); //
            if ($yaAsistio) {
                continue; // Ya asistió, no es una "nueva" reunión para él
            }

            // Regla 2: ¿Está dentro del plazo de 30 minutos?
            //
            $inicioReunion = new DateTime($reunion['fechaInicioReunion']);
            $limiteRegistro = (clone $inicioReunion)->modify('+30 minutes');
            $dentroDelPlazo = ($ahora >= $inicioReunion && $ahora <= $limiteRegistro);

            if ($dentroDelPlazo) {
                // ¡Encontramos una!
                $hayReunionHabilitadaParaAsistencia = true;
                break; // No necesitamos seguir buscando
            }
        }
    }

    echo json_encode(['status' => 'success', 'reunionActiva' => $hayReunionHabilitadaParaAsistencia]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>