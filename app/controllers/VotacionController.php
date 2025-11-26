<?php
namespace App\Controllers;

use App\Models\Votacion;
use App\Models\Minuta;

class VotacionController
{
    // Helper para JSON responses
    private function jsonResponse($data) {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    // API: Crear nueva votación
    public function apiCrear() {
        $input = json_decode(file_get_contents('php://input'), true);
        $idMinuta = $input['idMinuta'] ?? 0;
        $nombre = $input['nombre'] ?? '';

        if (!$idMinuta || !$nombre) {
            $this->jsonResponse(['status' => 'error', 'message' => 'Datos incompletos']);
        }

       try {
            // 2. Recuperamos el ID de la comisión real desde la Minuta
            $minutaModel = new Minuta();
            $datosMinuta = $minutaModel->getMinutaById($idMinuta);
            
            // Si no encuentra la comisión, usamos NULL (nunca 0)
            $idComision = $datosMinuta['t_comision_idComision'] ?? null; 

            $model = new Votacion();
            $model->crear([
                'nombre' => $nombre,
                'idMinuta' => $idMinuta,
                'idComision' => $idComision // <--- 3. Pasamos el ID real
            ]);
            $this->jsonResponse(['status' => 'success', 'message' => 'Votación creada']);
        } catch (\Exception $e) {
            $this->jsonResponse(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    // API: Listar votaciones (para el panel de control)
    public function apiListar() {
        $idMinuta = $_GET['idMinuta'] ?? 0;
        $model = new Votacion();
        $lista = $model->listarPorMinuta($idMinuta);
        $this->jsonResponse(['status' => 'success', 'data' => $lista]);
    }

    // API: Cambiar estado (Habilitar/Cerrar)
    public function apiCambiarEstado() {
        $input = json_decode(file_get_contents('php://input'), true);
        $idVotacion = $input['idVotacion'] ?? 0;
        $estado = $input['estado'] ?? 0; // 1 o 0

        $model = new Votacion();
        $model->cambiarEstado($idVotacion, $estado);
        $this->jsonResponse(['status' => 'success', 'message' => 'Estado actualizado']);
    }

    // API: Obtener resultados en tiempo real
    public function apiResultados() {
        $idMinuta = $_GET['idMinuta'] ?? 0;
        $model = new Votacion();
        $datos = $model->obtenerResultados($idMinuta);
        $this->jsonResponse(['status' => 'success', 'data' => $datos]);
    }

    public function sala()
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['idUsuario'])) { header('Location: index.php?action=login'); exit(); }

        $model = new Votacion();
        
        $data = [
            'usuario' => ['nombre' => $_SESSION['pNombre'] ?? '', 'apellido' => $_SESSION['aPaterno'] ?? '', 'rol' => $_SESSION['tipoUsuario_id'] ?? 0],
            'pagina_actual' => 'sala_votaciones',
            'historial_personal' => $model->getHistorialVotosPersonal($_SESSION['idUsuario']),
            'resultados_generales' => $model->getResultadosHistoricos()
        ];

        $childView = __DIR__ . '/../views/votacion/sala.php';
        require_once __DIR__ . '/../views/layouts/main.php';
    }

    // --- API: Polling para el Consejero (¿Hay algo activo?) ---
    public function apiCheckActive()
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $idUsuario = $_SESSION['idUsuario'] ?? 0;

        if (!$idUsuario) {
            $this->jsonResponse(['status' => 'error', 'message' => 'Sesión perdida']);
        }

        $model = new Votacion();
        $votacion = $model->obtenerVotacionActiva($idUsuario);

        if ($votacion) {
            $this->jsonResponse(['status' => 'active', 'data' => $votacion]);
        } else {
            $this->jsonResponse(['status' => 'waiting', 'message' => 'Esperando votación...']);
        }
    }

    // --- API: Emitir Voto ---
    public function apiEmitirVoto()
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $input = json_decode(file_get_contents('php://input'), true);
        
        $idVotacion = $input['idVotacion'] ?? 0;
        $opcion = $input['opcion'] ?? ''; // SI, NO, ABSTENCION
        $idUsuario = $_SESSION['idUsuario'] ?? 0;

        if (!$idVotacion || !$opcion || !$idUsuario) {
            $this->jsonResponse(['status' => 'error', 'message' => 'Datos inválidos']);
        }

        try {
            $model = new Votacion();
            $model->registrarVoto($idVotacion, $idUsuario, $opcion);
            $this->jsonResponse(['status' => 'success', 'message' => 'Voto registrado correctamente']);
        } catch (\Exception $e) {
            $this->jsonResponse(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }
}