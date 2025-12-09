<?php

namespace App\Controllers;

use App\Models\Votacion;
use App\Models\Minuta;
use App\Models\Comision;

class VotacionController
{
    // Helper para JSON responses
    private function jsonResponse($data)
    {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    // API: Crear nueva votación
    // En app/controllers/VotacionController.php

    public function apiCrear()
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $idMinuta = $input['idMinuta'] ?? 0;
        $nombre = $input['nombre'] ?? '';

        // CORRECCIÓN: Obtener idComision directamente del frontend si viene
        $idComision = $input['idComision'] ?? null;

        if (!$idMinuta || !$nombre) {
            $this->jsonResponse(['status' => 'error', 'message' => 'Datos incompletos']);
        }

        try {
            $model = new Votacion();

            // Si por alguna razón no llegó el idComision del front, intentamos buscarlo (fallback)
            if (!$idComision) {
                $minutaModel = new Minuta();
                $datosMinuta = $minutaModel->getMinutaById($idMinuta);
                $idComision = $datosMinuta['t_comision_idComision'] ?? null;
            }

            $nuevoId = $model->crear([
                'nombre' => $nombre,
                'idMinuta' => $idMinuta,
                'idComision' => $idComision // Ahora sí pasamos el valor correcto
            ]);

            $this->jsonResponse([
                'status' => 'success',
                'message' => 'Votación creada',
                'idVotacion' => $nuevoId
            ]);
        } catch (\Exception $e) {
            $this->jsonResponse(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }
    // API: Listar votaciones (para el panel de control)
    public function apiListar()
    {
        $idMinuta = $_GET['idMinuta'] ?? 0;
        $model = new Votacion();
        $lista = $model->listarPorMinuta($idMinuta);
        $this->jsonResponse(['status' => 'success', 'data' => $lista]);
    }

    // API: Cambiar estado (Habilitar/Cerrar)
    public function apiCambiarEstado()
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $idVotacion = $input['idVotacion'] ?? 0;
        $estado = $input['estado'] ?? 0; // 1 o 0

        $model = new Votacion();
        $model->cambiarEstado($idVotacion, $estado);
        $this->jsonResponse(['status' => 'success', 'message' => 'Estado actualizado']);
    }

    // API: Obtener resultados en tiempo real
    // En VotacionController.php

    public function apiResultados()
    {
        header('Content-Type: application/json');
        $db = new \App\Config\Database();
        $conn = $db->getConnection();

        $idVotacion = $_GET['id'] ?? 0;

        try {
            $sql = "SELECT opcionVoto, COUNT(*) as total 
                FROM t_voto 
                WHERE t_votacion_idVotacion = :id 
                GROUP BY opcionVoto";

            $stmt = $conn->prepare($sql);
            $stmt->execute([':id' => $idVotacion]);
            $resultados = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $conteo = [
                'APRUEBO' => 0,
                'RECHAZO' => 0,
                'ABSTENCION' => 0,
                'TOTAL' => 0
            ];

            // --- CORRECCIÓN: MAPEO INVERSO (BD -> JSON) ---
            foreach ($resultados as $row) {
                $opcionBD = $row['opcionVoto'];
                $cantidad = (int)$row['total'];

                if ($opcionBD === 'SI') {
                    $conteo['APRUEBO'] = $cantidad;
                } elseif ($opcionBD === 'NO') {
                    $conteo['RECHAZO'] = $cantidad;
                } elseif ($opcionBD === 'ABSTENCION') {
                    $conteo['ABSTENCION'] = $cantidad;
                }

                $conteo['TOTAL'] += $cantidad;
            }
            // ----------------------------------------------

            echo json_encode(['status' => 'success', 'data' => $conteo]);
        } catch (\Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function sala()
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['idUsuario'])) {
            header('Location: index.php?action=login');
            exit();
        }

        $model = new Votacion();

        // Cargar Comisiones para el Select de Filtros
        $comisionModel = new Comision();
        $comisiones = $comisionModel->listarTodas();

        $data = [
            'usuario' => ['nombre' => $_SESSION['pNombre'] ?? '', 'apellido' => $_SESSION['aPaterno'] ?? '', 'rol' => $_SESSION['tipoUsuario_id'] ?? 0],
            'pagina_actual' => 'sala_votaciones',
            'historial_personal' => [], // Se carga por AJAX
            'comisiones' => $comisiones, // <--- Pasamos las comisiones
            'resultados_generales' => $model->getResultadosHistoricos()
        ];

        $childView = __DIR__ . '/../views/votacion/sala.php';
        require_once __DIR__ . '/../views/layouts/main.php';
    }

public function apiHistorialGlobal()
    {
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json');

        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['idUsuario'])) {
            echo json_encode(['status' => 'error', 'message' => 'Sesión no iniciada']);
            exit;
        }

        $idUsuario = $_SESSION['idUsuario']; // <--- CLAVE PARA "MI VOTO"

        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $offset = ($page - 1) * $limit;

        $filtros = [
            'desde'    => $_GET['desde'] ?? '',
            'hasta'    => $_GET['hasta'] ?? '',
            'comision' => $_GET['comision'] ?? '',
            'q'        => $_GET['q'] ?? ''
        ];

        try {
            $model = new Votacion();
            
            // Pasamos $idUsuario al modelo
            $data = $model->getHistorialGlobalFiltrado($filtros, $limit, $offset, $idUsuario);
            $total = $model->countHistorialGlobalFiltrado($filtros);

            // Procesamiento de Datos
            foreach ($data as &$row) {
                $si = (int)$row['votos_si'];
                $no = (int)$row['votos_no'];
                
                // Resultado Global
                if ($si > $no) $row['resultado_final'] = 'APROBADA';
                elseif ($no > $si) $row['resultado_final'] = 'RECHAZADA';
                elseif ($si == $no && $si > 0) $row['resultado_final'] = 'EMPATE';
                else $row['resultado_final'] = 'SIN QUÓRUM';

                // Normalizar "MI VOTO" (Manejo seguro de nulos y alias)
                $miVoto = $row['mi_voto_personal'] ?? null;
                
                if ($miVoto === 'APRUEBO') $miVoto = 'SI';
                if ($miVoto === 'RECHAZO') $miVoto = 'NO';
                if ($miVoto === 'ABSTENCION') $miVoto = 'ABS';

                // Enviamos 'mi_voto_personal' limpio para la vista
                $row['mi_voto'] = $miVoto;
            }

            echo json_encode([
                'status'     => 'success',
                'data'       => $data,
                'total'      => $total,
                'totalPages' => ceil($total / $limit),
                'page'       => $page
            ]);

        } catch (\Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }



    

    public function apiCheckActive()
    {
        // 1. Limpieza preventiva de basura (espacios en blanco de otros archivos)
        if (ob_get_length()) ob_clean();

        header('Content-Type: application/json');

        if (session_status() === PHP_SESSION_NONE) session_start();
        $idUsuario = $_SESSION['idUsuario'] ?? 0;

        if (!$idUsuario) {
            echo json_encode(['status' => 'error', 'message' => 'Sesión perdida']);
            exit;
        }

        $db = new \App\Config\Database();
        $conn = $db->getConnection();

        try {
            // 1. Buscar si hay alguna votación habilitada (vigente = 1)
            $sql = "SELECT idVotacion, nombreVotacion 
                    FROM t_votacion 
                    WHERE habilitada = 1 
                    ORDER BY idVotacion DESC LIMIT 1";

            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $votacion = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($votacion) {
                // 2. Si hay votación, verificamos SI el usuario ya votó
                // Usamos las columnas correctas: t_votacion_idVotacion y t_usuario_idUsuario
                $sqlCheck = "SELECT opcionVoto FROM t_voto 
                             WHERE t_votacion_idVotacion = :idVoto 
                             AND t_usuario_idUsuario = :idUser";

                $stmtCheck = $conn->prepare($sqlCheck);
                $stmtCheck->execute([
                    ':idVoto' => $votacion['idVotacion'],
                    ':idUser' => $idUsuario
                ]);
                $miVoto = $stmtCheck->fetch(\PDO::FETCH_ASSOC);

                $data = [
                    'idVotacion' => $votacion['idVotacion'],
                    'nombreVotacion' => $votacion['nombreVotacion'],
                    'ya_voto' => ($miVoto ? true : false),
                    'opcion_registrada' => ($miVoto ? $miVoto['opcionVoto'] : null)
                ];

                echo json_encode(['status' => 'active', 'data' => $data]);
            } else {
                echo json_encode(['status' => 'waiting', 'message' => 'Esperando votación...']);
            }
        } catch (\Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }

        exit; // <--- IMPORTANTE: Detener ejecución aquí
    }

    public function apiEmitirVoto()
    {
        header('Content-Type: application/json');

        // 1. Verificaciones Básicas
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['idUsuario'])) {
            echo json_encode(['status' => 'error', 'message' => 'Sesión no válida']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $idVotacion = $input['idVotacion'] ?? 0;
        $opcionInput = $input['opcion'] ?? '';
        $idUsuario = $_SESSION['idUsuario'];

        $opcionDb = '';
        if ($opcionInput === 'APRUEBO') {
            $opcionDb = 'SI';
        } elseif ($opcionInput === 'RECHAZO') {
            $opcionDb = 'NO';
        } elseif ($opcionInput === 'ABSTENCION') {
            $opcionDb = 'ABSTENCION';
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Opción de voto no válida']);
            exit;
        }

        $db = new \App\Config\Database();
        $conn = $db->getConnection();

        try {

            // 2. VALIDACIÓN CRÍTICA: Asistencia
            $sqlMinuta = "SELECT t_minuta_idMinuta FROM t_votacion WHERE idVotacion = :idVoto";
            $stmtM = $conn->prepare($sqlMinuta);
            $stmtM->execute([':idVoto' => $idVotacion]);
            $rowM = $stmtM->fetch(\PDO::FETCH_ASSOC);

            if (!$rowM) {
                throw new \Exception("Votación no encontrada.");
            }
            $idMinuta = $rowM['t_minuta_idMinuta'];

            // Verificamos asistencia
            // 2. VALIDACIÓN ROBUSTA: Asistencia
            $sqlMinuta = "SELECT t_minuta_idMinuta FROM t_votacion WHERE idVotacion = :idVoto";
            $stmtM = $conn->prepare($sqlMinuta);
            $stmtM->execute([':idVoto' => $idVotacion]);
            $rowM = $stmtM->fetch(\PDO::FETCH_ASSOC);

            if (!$rowM) {
                echo json_encode(['status' => 'error', 'message' => 'Votación no encontrada.']);
                exit;
            }
            $idMinuta = $rowM['t_minuta_idMinuta'];

            // CORRECCIÓN: Contamos si existe al menos un registro 'PRESENTE' para este usuario y minuta.
            // Esto soluciona el problema si existen filas duplicadas donde una dice AUSENTE y la otra PRESENTE.
            $sqlAsistencia = "SELECT COUNT(*) as total 
                  FROM t_asistencia 
                  WHERE t_usuario_idUsuario = :user 
                  AND t_minuta_idMinuta = :minuta 
                  AND TRIM(UPPER(estadoAsistencia)) = 'PRESENTE'";

            $stmtA = $conn->prepare($sqlAsistencia);
            $stmtA->execute([':user' => $idUsuario, ':minuta' => $idMinuta]);
            $resultado = $stmtA->fetch(\PDO::FETCH_ASSOC);

            // Si el conteo es 0, significa que no está presente (o no marcó, o marcó pero sigue ausente)
            if ($resultado['total'] == 0) {
                echo json_encode([
                    'status' => 'error',
                    'message' => "No puede votar. El sistema indica que su estado es 'AUSENTE'. Debe marcar asistencia primero."
                ]);
                exit;
            }
            // --- FIN DE LA CORRECCIÓN ---

            // 3. Validar si ya votó ...

            // 3. Validar si ya votó (Doble seguridad)
            $sqlCheck = "SELECT idVoto FROM t_voto WHERE t_votacion_idVotacion = :idVoto AND t_usuario_idUsuario = :user";
            $stmtCheck = $conn->prepare($sqlCheck);
            $stmtCheck->execute([':idVoto' => $idVotacion, ':user' => $idUsuario]);
            if ($stmtCheck->fetch()) {
                echo json_encode(['status' => 'error', 'message' => 'Usted ya emitió su voto en esta sesión.']);
                exit;
            }

            $sqlInsert = "INSERT INTO t_voto (t_votacion_idVotacion, t_usuario_idUsuario, opcionVoto, fechaHoraVoto, origenVoto) 
                          VALUES (:idVoto, :user, :opcion, NOW(), 'SALA_VIRTUAL')";

            $stmtInsert = $conn->prepare($sqlInsert);
            $stmtInsert->execute([':idVoto' => $idVotacion, ':user' => $idUsuario, ':opcion' => $opcionDb]);

            // --- CORRECCIÓN AQUÍ ---
            echo json_encode(['status' => 'success', 'message' => 'Voto registrado correctamente']);
            exit; // <--- AGREGAR ESTE EXIT OBLIGATORIAMENTE

        } catch (\Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            exit;
        }
    }
}
