<?php

namespace App\Controllers;

use App\Models\Minuta;

class AsistenciaController
{
    private function jsonResponse($data)
    {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    private function verificarSesion()
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['idUsuario'])) {
            // Si es API, error JSON; si es vista, redirect (manejado en el método)
            return false;
        }
        return true;
    }

    // --- VISTA: Sala de Autogestión ---
    public function sala()
    {
        if (!$this->verificarSesion()) {
            header('Location: index.php?action=login');
            exit();
        }

        $minutaModel = new Minuta();
        // Necesitamos también el modelo de Reunión para el calendario/próximas
        // Asumimos que usas ReunionModel o una consulta directa. 
        // Por simplicidad usaremos Minuta para el historial.

        // Cargar historial
        $historial = $minutaModel->getHistorialAsistenciaPersonal($_SESSION['idUsuario']);

        // Cargar próximas reuniones (reutilizando lógica si existe, o array vacío por ahora)
        // Idealmente inyectar ReunionModel aquí.
        $proximas = []; // (Se llenará con JS o lógica extra si quieres)

        $data = [
            'usuario' => ['nombre' => $_SESSION['pNombre'], 'apellido' => $_SESSION['aPaterno'], 'rol' => $_SESSION['tipoUsuario_id']],
            'pagina_actual' => 'sala_reuniones',
            'historial' => $historial,
            'proximas' => $proximas
        ];

        $childView = __DIR__ . '/../views/asistencia/sala.php';
        require_once __DIR__ . '/../views/layouts/main.php';
    }

    // --- API: Verificar si hay reunión activa ---
    public function apiCheck()
    {
        header('Content-Type: application/json');
        $this->verificarSesion();
        $db = new \App\Config\Database();
        $conn = $db->getConnection();

        // 1. Iniciar sesión si no está iniciada
        if (session_status() === PHP_SESSION_NONE) session_start();

        // 2. Verificar estrictamente si existe la variable
        if (!isset($_SESSION['idUsuario'])) {
            // Si no existe, devolvemos error y MATAMOS la ejecución
            echo json_encode(['status' => 'error', 'message' => 'Sesión expirada']);
            exit; // <--- ESTE EXIT ES CRÍTICO
        }



        $idUsuario = $_SESSION['idUsuario'];

        try {
            // CORRECCIÓN IMPORTANTE:
            // Agregamos "AND r.t_minuta_idMinuta IS NOT NULL"
            // Esto asegura que la reunión solo aparezca si el Secretario ya la inició (generó la minuta).

            $sql = "SELECT r.idReunion, r.nombreReunion, r.fechaInicioReunion, r.t_minuta_idMinuta,
                           (SELECT COUNT(*) FROM t_asistencia a 
                            WHERE a.t_minuta_idMinuta = r.t_minuta_idMinuta 
                            AND a.t_usuario_idUsuario = :idUser 
                            AND a.estadoAsistencia = 'PRESENTE') as ya_marco
                    FROM t_reunion r
                    WHERE r.vigente = 1 
                    AND r.t_minuta_idMinuta IS NOT NULL  
                    AND DATE(r.fechaInicioReunion) = CURDATE()
                    ORDER BY r.fechaInicioReunion DESC
                    LIMIT 1";

            $stmt = $conn->prepare($sql);
            $stmt->execute([':idUser' => $idUsuario]);
            $reunion = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($reunion) {
                // Calculamos minutos transcurridos desde el inicio
                $inicio = new \DateTime($reunion['fechaInicioReunion']);
                $ahora = new \DateTime();
                $diff = $inicio->diff($ahora);
                $minutosTranscurridos = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i;

                // --- NUEVO: CALCULAR HORA LÍMITE (Inicio + 30 min) ---
                $limite = clone $inicio;
                $limite->modify('+30 minutes');
                $horaLimiteStr = $limite->format('H:i'); 
                // -----------------------------------------------------

                echo json_encode([
                    'status' => 'active',
                    'data' => [
                        'idReunion' => $reunion['idReunion'],
                        'nombreReunion' => $reunion['nombreReunion'],
                        't_minuta_idMinuta' => $reunion['t_minuta_idMinuta'],
                        'fechaInicio' => $reunion['fechaInicioReunion'],
                        'minutosTranscurridos' => $minutosTranscurridos,
                        'ya_marco' => ($reunion['ya_marco'] > 0),
                        'horaLimite' => $horaLimiteStr // <--- AGREGAR ESTO AL ARRAY
                    ]
                ]);
            } else {
                echo json_encode(['status' => 'none']);
            }
        } catch (\Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }
    // --- API: Marcar Presente ---
    public function apiMarcar()
    {
        if (!$this->verificarSesion()) $this->jsonResponse(['status' => 'error']);

        $input = json_decode(file_get_contents('php://input'), true);
        $idMinuta = $input['idMinuta'] ?? 0;
        $idReunion = $input['idReunion'] ?? 0;

        if (!$idMinuta) $this->jsonResponse(['status' => 'error', 'message' => 'Datos error']);

        $model = new Minuta();
        if ($model->registrarAutoAsistencia($idMinuta, $_SESSION['idUsuario'], $idReunion)) {
            $this->jsonResponse(['status' => 'success', 'message' => 'Asistencia registrada correctamente.']);
        } else {
            $this->jsonResponse(['status' => 'error', 'message' => 'Error al registrar.']);
        }
    }
}
