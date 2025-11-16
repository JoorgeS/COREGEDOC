<?php
// controllers/obtener_estado_sala_votante.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

// Validar sesión
if (!isset($_SESSION['idUsuario'])) {
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

require_once __DIR__ . '/../class/class.conectorDB.php';

$idUsuario = (int)$_SESSION['idUsuario'];
$response = [
    'status' => 'success',
    'votacion' => null,     // La votación activa
    'votoUsuario' => null,    // El voto del usuario
    'resultados' => null    // Los conteos (SI, NO, ABS)
];

try {
    $db = new conectorDB();
    $pdo = $db->getDatabase();

    $sqlVotacion = "SELECT v.idVotacion, v.nombreVotacion AS nombreAcuerdo, v.t_minuta_idMinuta, c.nombreComision
                    FROM t_votacion v
                    LEFT JOIN t_minuta m ON v.t_minuta_idMinuta = m.idMinuta
                    LEFT JOIN t_comision c ON m.t_comision_idComision = c.idComision
                    WHERE v.habilitada = 1 
                    ORDER BY v.idVotacion DESC 
                    LIMIT 1";

    $stmtVotacion = $pdo->query($sqlVotacion);
    $votacion = $stmtVotacion->fetch(PDO::FETCH_ASSOC);

    // Si NO hay votación activa, devolvemos la respuesta vacía (estado "Esperando...")
    if (!$votacion) {
        echo json_encode($response);
        exit;
    }

    $idVotacion = (int)$votacion['idVotacion'];
    $idMinuta = (int)$votacion['t_minuta_idMinuta'];
    $response['votacion'] = $votacion;

    // 2. Buscar si el usuario actual YA VOTÓ en esta votación
    $sqlVoto = "SELECT opcionVoto FROM t_voto 
                WHERE t_usuario_idUsuario = :idUsuario AND t_votacion_idVotacion = :idVotacion";
    $stmtVoto = $pdo->prepare($sqlVoto);
    $stmtVoto->execute([':idUsuario' => $idUsuario, ':idVotacion' => $idVotacion]);
    $voto = $stmtVoto->fetch(PDO::FETCH_ASSOC);

    if ($voto) {
        $response['votoUsuario'] = $voto;
    }

    // 3. Obtener los resultados (conteo)
    $sqlResultados = "SELECT 
                        opcionVoto, 
                        COUNT(*) as total
                      FROM t_voto 
                      WHERE t_votacion_idVotacion = :idVotacion
                      GROUP BY opcionVoto";
    $stmtResultados = $pdo->prepare($sqlResultados);
    $stmtResultados->execute([':idVotacion' => $idVotacion]);
    $conteo = $stmtResultados->fetchAll(PDO::FETCH_KEY_PAIR); // Ej: ['SI' => 5, 'NO' => 2]

    // 4. Obtener el total de asistentes (quórum)
    $sqlAsistentes = "SELECT COUNT(*) FROM t_asistencia 
                      WHERE t_minuta_idMinuta = :idMinuta";
    $stmtAsistentes = $pdo->prepare($sqlAsistentes);
    $stmtAsistentes->execute([':idMinuta' => $idMinuta]);
    $totalPresentes = $stmtAsistentes->fetchColumn();

    $response['resultados'] = [
        'votosSi' => (int)($conteo['SI'] ?? 0),
        'votosNo' => (int)($conteo['NO'] ?? 0),
        'votosAbstencion' => (int)($conteo['ABSTENCION'] ?? 0),
        'totalPresentes' => (int)$totalPresentes
    ];

    echo json_encode($response);
} catch (Exception $e) {
    error_log("Error en obtener_estado_sala_votante: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
