<?php
// /corevota/controllers/obtener_estado_votacion_usuario.php

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Asumo que la ruta al conectorDB.php es correcta
require_once dirname(__DIR__, 2) . '/class/class.conectorDB.php';

$idVotacion = $_GET['idVotacion'] ?? null;
$idUsuario = $_GET['idUsuario'] ?? null;

if (!$idVotacion || !$idUsuario || !is_numeric($idVotacion) || !is_numeric($idUsuario)) {
    echo json_encode(['status' => 'error', 'message' => 'Parámetros inválidos o faltantes.']);
    exit;
}

$db = null;
try {
    $db = new conectorDB();
    $pdo = $db->getDatabase();

    // =========================================================================
    // 1. OBTENER INFORMACIÓN BÁSICA DE LA VOTACIÓN Y SU MINUTA
    // =========================================================================
    $sql_info = "
        SELECT 
            v.nombreAcuerdo, 
            v.idMinuta 
        FROM t_votacion v 
        WHERE v.idVotacion = :idVotacion";
    $stmt_info = $pdo->prepare($sql_info);
    $stmt_info->execute([':idVotacion' => $idVotacion]);
    $votacion = $stmt_info->fetch(PDO::FETCH_ASSOC);

    if (!$votacion) {
        throw new Exception('Votación no encontrada.');
    }
    $idMinuta = $votacion['idMinuta'];
    
    // =========================================================================
    // 2. OBTENER ESTADO DEL VOTO DEL USUARIO LOGUEADO
    // =========================================================================
    $sql_voto_usuario = "
        SELECT opcionVoto 
        FROM t_voto 
        WHERE idVotacion = :idVotacion AND idUsuario = :idUsuario";
    $stmt_voto_usuario = $pdo->prepare($sql_voto_usuario);
    $stmt_voto_usuario->execute([':idVotacion' => $idVotacion, ':idUsuario' => $idUsuario]);
    $votoUsuario = $stmt_voto_usuario->fetch(PDO::FETCH_ASSOC);

    // =========================================================================
    // 3. OBTENER CONTEO TOTAL DE ASISTENTES (TOTAL REQUERIDO)
    // =========================================================================
    $sql_asistencia = "
        SELECT COUNT(idUsuario) 
        FROM t_asistencia 
        WHERE idMinuta = :idMinuta AND estado = 'PRESENTE'";
    $stmt_asistencia = $pdo->prepare($sql_asistencia);
    $stmt_asistencia->execute([':idMinuta' => $idMinuta]);
    $totalPresentes = $stmt_asistencia->fetchColumn();

    // =========================================================================
    // 4. OBTENER CONTEO DE VOTOS POR OPCIÓN
    // =========================================================================
    $sql_conteo = "
        SELECT 
            SUM(CASE WHEN opcionVoto = 'SI' THEN 1 ELSE 0 END) AS votosSi,
            SUM(CASE WHEN opcionVoto = 'NO' THEN 1 ELSE 0 END) AS votosNo,
            SUM(CASE WHEN opcionVoto = 'ABSTENCION' THEN 1 ELSE 0 END) AS votosAbstencion
        FROM t_voto 
        WHERE idVotacion = :idVotacion";
    $stmt_conteo = $pdo->prepare($sql_conteo);
    $stmt_conteo->execute([':idVotacion' => $idVotacion]);
    $conteoVotos = $stmt_conteo->fetch(PDO::FETCH_ASSOC);

    // Si no hay votos registrados, inicializar a cero
    if ($conteoVotos === false) {
        $conteoVotos = ['votosSi' => 0, 'votosNo' => 0, 'votosAbstencion' => 0];
    }

    // =========================================================================
    // 5. CONSTRUIR Y DEVOLVER LA RESPUESTA JSON
    // =========================================================================
    
    $votacionInfo = [
        'idVotacion' => (int)$idVotacion,
        'nombreAcuerdo' => $votacion['nombreAcuerdo']
    ];

    $resultados = [
        'votosSi' => (int)$conteoVotos['votosSi'],
        'votosNo' => (int)$conteoVotos['votosNo'],
        'votosAbstencion' => (int)$conteoVotos['votosAbstencion'],
        'totalPresentes' => (int)$totalPresentes // Total de asistentes que deben votar
    ];

    $response = [
        'status' => 'success',
        'votacion' => $votacionInfo,
        // El votoUsuario es null si no ha votado, o {'opcionVoto': 'SI'}
        'votoUsuario' => $votoUsuario, 
        'resultados' => $resultados
    ];

    echo json_encode($response);

} catch (Exception $e) {
    error_log("Error en obtener_estado_votacion_usuario.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Error interno al obtener el estado de la votación.']);
} finally {
    $pdo = null;
    $db = null;
}
?>