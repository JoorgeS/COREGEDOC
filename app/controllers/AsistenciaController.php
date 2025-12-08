<?php

namespace App\Controllers;

use App\Models\Minuta;
use App\Models\Comision; // <--- AGREGADO: Necesario para cargar el filtro de comisiones

class AsistenciaController
{
    private function jsonResponse($data)
    {
        if (ob_get_length()) ob_clean(); // Limpia cualquier error/warning previo
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    private function verificarSesion()
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['idUsuario'])) {
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
        
        // 1. Cargar Comisiones para el Filtro
        $comisionModel = new Comision();
        $comisiones = $comisionModel->listarTodas();

        // 2. Pasamos las comisiones a la vista
        $data = [
            'usuario' => ['nombre' => $_SESSION['pNombre'], 'apellido' => $_SESSION['aPaterno'], 'rol' => $_SESSION['tipoUsuario_id']],
            'pagina_actual' => 'sala_reuniones',
            'historial' => [], // Se cargará por AJAX para optimizar
            'comisiones' => $comisiones, // <--- ESTO FALTABA PARA EL SELECT
            'proximas' => []
        ];

        $childView = __DIR__ . '/../views/asistencia/sala.php';
        require_once __DIR__ . '/../views/layouts/main.php';
    }

    // --- API: Verificar si hay reunión activa ---
    public function apiCheck()
    {
        $this->verificarSesion();
        
        // LIMPIEZA CRÍTICA DE BUFFER
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json');

        $db = new \App\Config\Database();
        $conn = $db->getConnection();
        $idUsuario = $_SESSION['idUsuario'];

        try {
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
                $inicio = new \DateTime($reunion['fechaInicioReunion']);
                $ahora = new \DateTime();
                $diff = $inicio->diff($ahora);
                $minutosTranscurridos = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i;
                
                $limite = clone $inicio;
                $limite->modify('+30 minutes');
                $horaLimiteStr = $limite->format('H:i'); 

                echo json_encode([
                    'status' => 'active',
                    'data' => [
                        'idReunion' => $reunion['idReunion'],
                        'nombreReunion' => $reunion['nombreReunion'],
                        't_minuta_idMinuta' => $reunion['t_minuta_idMinuta'],
                        'fechaInicio' => $reunion['fechaInicioReunion'],
                        'minutosTranscurridos' => $minutosTranscurridos,
                        'ya_marco' => ($reunion['ya_marco'] > 0),
                        'horaLimite' => $horaLimiteStr
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
    
    // --- API: Historial Filtrado (CORREGIDO) ---
    public function apiHistorial() {
        // 1. Limpieza de buffer para evitar errores de JSON
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json');
        
        // 2. Verificar sesión
        if (!$this->verificarSesion()) {
            echo json_encode(['status' => 'error', 'message' => 'Sesión no iniciada']);
            exit;
        }

        $idUsuario = $_SESSION['idUsuario'];
        $modelo = new Minuta();
        
        $filtros = [
            'desde' => $_GET['desde'] ?? null,
            'hasta' => $_GET['hasta'] ?? null,
            'comision' => $_GET['comision'] ?? null,
            'q' => $_GET['q'] ?? null
        ];
        
        $page = (int)($_GET['page'] ?? 1);
        $limit = (int)($_GET['limit'] ?? 10);
        $offset = ($page - 1) * $limit;

        try {
            $resultado = $modelo->getHistorialAsistenciaPersonalFiltrado($idUsuario, $filtros, $limit, $offset);
            echo json_encode($resultado);
        } catch (\Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }
}