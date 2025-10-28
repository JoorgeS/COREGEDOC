<?php
// ===============================================
//  Archivo: fetch_votos.php
//  Función: Devuelve el listado de usuarios con su voto
// ===============================================

if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}

require_once __DIR__ . '/../class/class.conectorDB.php';
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');

try {
    $db = new conectorDB();
    $pdo = $db->getDatabase();

    // Obtener id de la votación
    $idVotacion = $_GET['idVotacion'] ?? null;

    // Si no llega ID, obtener la votación habilitada o la más reciente
    if (!$idVotacion) {
        // Votación habilitada
        $sql = "SELECT idVotacion 
                FROM t_votacion 
                WHERE habilitada = 1 
                ORDER BY idVotacion DESC 
                LIMIT 1";
        $stmt = $pdo->query($sql);
        $idVotacion = $stmt->fetchColumn();

        // Si no hay habilitadas, usar la última creada
        if (!$idVotacion) {
            $sql = "SELECT idVotacion 
                    FROM t_votacion 
                    ORDER BY idVotacion DESC 
                    LIMIT 1";
            $stmt = $pdo->query($sql);
            $idVotacion = $stmt->fetchColumn();
        }
    }

    // Si sigue sin haber votación válida
    if (!$idVotacion) {
        echo json_encode([
            'status' => 'error',
            'message' => 'No hay votaciones registradas aún.',
            'data' => []
        ]);
        exit;
    }

    // Consulta principal: lista de consejeros y su voto
    $sql = "
        SELECT 
          u.idUsuario,
          CONCAT(u.pNombre, ' ', u.aPaterno) AS nombre,
          COALESCE(v.opcionVoto, 'Sin votar') AS opcionVoto
        FROM t_usuario u
        LEFT JOIN t_voto v 
          ON v.idUsuario = u.idUsuario
          AND v.idVotacion = :idVotacion
        WHERE u.tipoUsuario_id = 1
        ORDER BY u.aPaterno ASC, u.pNombre ASC;
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':idVotacion' => $idVotacion]);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 🔹 Obtener nombre de la votación actual
    $nombreVotacion = null;
    $stmtNombre = $pdo->prepare("SELECT nombreVotacion FROM t_votacion WHERE idVotacion = :id");
    $stmtNombre->execute([':id' => $idVotacion]);
    $nombreVotacion = $stmtNombre->fetchColumn();

    // Usuario actual (opcional para destacar en la tabla)
    $usuarioSesion = $_SESSION['idUsuario'] ?? null;

    // 🔹 Respuesta final JSON
    echo json_encode([
        'status' => 'success',
        'idVotacion' => $idVotacion,
        'usuarioSesion' => $usuarioSesion,
        'nombreVotacion' => $nombreVotacion, // <<--- agregado aquí
        'data' => $result
    ]);
    exit;

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Error al obtener los votos: ' . $e->getMessage(),
        'data' => []
    ]);
    exit;
}
