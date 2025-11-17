<?php
// /corevota/controllers/obtener_resultados_votacion.php
header('Content-Type: application/json');
error_reporting(0);
ini_set('display_errors', 0);

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

require_once __DIR__ . '/../class/class.conectorDB.php';

if (!isset($_SESSION['idUsuario'])) {
  http_response_code(403);
  echo json_encode(['status' => 'error', 'message' => 'Acceso no autorizado. Debe iniciar sesión.']);
  exit;
}

$idMinuta = $_GET['idMinuta'] ?? null;
$idVotacionEspecifica = $_GET['idVotacion'] ?? null; // Variable para el botón "Ver"

$idUsuarioLogueado = $_SESSION['idUsuario'];
$idTipoUsuario = $_SESSION['tipoUsuario_id'];
$esSecretarioTecnico = ($idTipoUsuario == 2);

if (!$idMinuta) {
  http_response_code(400);
  echo json_encode(['status' => 'error', 'message' => 'ID de Minuta no proporcionado.']);
  exit;
}

try {
  $db = new conectorDB();
  $pdo = $db->getDatabase();

  $sqlReunion = $pdo->prepare("SELECT idReunion FROM t_reunion WHERE t_minuta_idMinuta = :idMinuta LIMIT 1");
  $sqlReunion->execute([':idMinuta' => $idMinuta]);
  $reunion = $sqlReunion->fetch(PDO::FETCH_ASSOC);
  $idReunion = $reunion ? $reunion['idReunion'] : null;

    // --- LÓGICA SQL DINÁMICA (Esta parte ya está correcta) ---
    $params = [
        ':idMinuta' => $idMinuta,
        ':idReunion' => $idReunion
    ];
    $query_where = "WHERE (v.t_minuta_idMinuta = :idMinuta OR v.t_reunion_idReunion = :idReunion)";

    if ($idVotacionEspecifica) {
        // Para el botón "Ver"
        $query_where .= " AND v.idVotacion = :idVotacion";
        $params[':idVotacion'] = $idVotacionEspecifica;
    } else {
        // Para "Resultados en Vivo"
        $query_where .= " AND v.habilitada = 1";
    }

  $sqlVotaciones = $pdo->prepare("SELECT v.idVotacion, v.nombreVotacion, v.idComision, v.habilitada 
                                    FROM t_votacion v
                  $query_where
                  ORDER BY v.idVotacion ASC");
  $sqlVotaciones->execute($params);
  $votaciones = $sqlVotaciones->fetchAll(PDO::FETCH_ASSOC);

  if (empty($votaciones)) {
    echo json_encode(['status' => 'success', 'data' => []]); 
    exit;
  }

  // OBTENER TOTAL DE ASISTENTES (El JS lo necesita)
  $sqlAsistentes = $pdo->prepare("SELECT t_usuario_idUsuario FROM t_asistencia 
                 WHERE t_minuta_idMinuta = :idMinuta AND t_usuario_idUsuario IN (SELECT idUsuario FROM t_usuario WHERE tipoUsuario_id = 1 OR tipoUsuario_id = 3)");
  $sqlAsistentes->execute([':idMinuta' => $idMinuta]);
  $asistentesIDs = $sqlAsistentes->fetchAll(PDO::FETCH_COLUMN, 0);
  $totalAsistentes = count($asistentesIDs); // El JS espera esta variable

  $resultadosFinales = [];

    // --- INICIO DE LA MODIFICACIÓN 3: REESTRUCTURAR EL FOREACH ---
    // (Esta es la corrección principal al problema de 'undefined')

  foreach ($votaciones as $votacion) {
    $idVotacion = $votacion['idVotacion'];

        // 1. Inicializamos el array con las claves que el JS SÍ espera
    $resultado = [
      'idVotacion' => $idVotacion,
            // 'nombreAcuerdo' es la clave que espera tu JS (la usaste en la función del modal)
      'nombreAcuerdo' => $votacion['nombreVotacion'], 
      'habilitada' => (int)$votacion['habilitada'],
            'votosSi' => 0, // JS espera 'votosSi'
            'votosNo' => 0, // JS espera 'votosNo'
            'votosAbstencion' => 0, // JS espera 'votosAbstencion'
            'totalPresentes' => $totalAsistentes, // JS espera 'totalPresentes'
            'votosSi_nombres' => [], // JS espera un array simple de nombres
            'votosNo_nombres' => [], // JS espera un array simple de nombres
            'votosAbstencion_nombres' => [], // JS espera un array simple de nombres
      'votoPersonal' => null, // Tu código ya lo tenía
    ];

    // 2. Obtenemos los votos (esta consulta está bien)
    $sqlVotos = $pdo->prepare("
      SELECT v.opcionVoto, 
         v.t_usuario_idUsuario,
         CONCAT(u.pNombre, ' ', u.aPaterno) as nombreVotante
      FROM t_voto v
      JOIN t_usuario u ON v.t_usuario_idUsuario = u.idUsuario
      WHERE v.t_votacion_idVotacion = :idVotacion
    ");
    $sqlVotos->execute([':idVotacion' => $idVotacion]);
    $votosData = $sqlVotos->fetchAll(PDO::FETCH_ASSOC);

    // 3. Procesamos los votos y los guardamos en las claves correctas
    foreach ($votosData as $voto) {
      $opcion = strtoupper($voto['opcionVoto']);
            $nombreVotante = $voto['nombreVotante'];

      if ($opcion === 'SI') {
                $resultado['votosSi']++;
                if ($esSecretarioTecnico) $resultado['votosSi_nombres'][] = $nombreVotante;
            } 
            elseif ($opcion === 'NO') {
                $resultado['votosNo']++;
                if ($esSecretarioTecnico) $resultado['votosNo_nombres'][] = $nombreVotante;
            } 
            elseif ($opcion === 'ABSTENCION') {
                $resultado['votosAbstencion']++;
                if ($esSecretarioTecnico) $resultado['votosAbstencion_nombres'][] = $nombreVotante;
            }

      // Cargar voto personal
      if ($voto['t_usuario_idUsuario'] == $idUsuarioLogueado) {
        $resultado['votoPersonal'] = $opcion;
      }
    }
        // El JS calcula 'faltanVotar' por sí mismo, así que no es necesario enviarlo

    $resultadosFinales[] = $resultado;
  }
    // --- FIN DE LA MODIFICACIÓN 3 ---

  echo json_encode(['status' => 'success', 'data' => $resultadosFinales, 'esSecretarioTecnico' => $esSecretarioTecnico]);

} catch (Exception $e) {
  http_response_code(500); // Internal Server Error
  error_log("Error en obtener_resultados_votacion.php: " . $e->getMessage());
  echo json_encode(['status' => 'error', 'message' => 'Error de base de datos.']);
}