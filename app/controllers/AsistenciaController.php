<?php
namespace App\Controllers;

use App\Models\Minuta;

class AsistenciaController
{
    private function jsonResponse($data) {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    private function verificarSesion() {
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
        if (!$this->verificarSesion()) { header('Location: index.php?action=login'); exit(); }

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
        if (!$this->verificarSesion()) $this->jsonResponse(['status'=>'error', 'message'=>'Sesión perdida']);

        $model = new Minuta();
        $reunion = $model->obtenerReunionActivaParaAsistencia($_SESSION['idUsuario']);

        if ($reunion) {
            $this->jsonResponse(['status' => 'active', 'data' => $reunion]);
        } else {
            $this->jsonResponse(['status' => 'waiting']);
        }
    }

    // --- API: Marcar Presente ---
    public function apiMarcar()
    {
        if (!$this->verificarSesion()) $this->jsonResponse(['status'=>'error']);
        
        $input = json_decode(file_get_contents('php://input'), true);
        $idMinuta = $input['idMinuta'] ?? 0;
        $idReunion = $input['idReunion'] ?? 0;

        if (!$idMinuta) $this->jsonResponse(['status'=>'error', 'message'=>'Datos error']);

        $model = new Minuta();
        if ($model->registrarAutoAsistencia($idMinuta, $_SESSION['idUsuario'], $idReunion)) {
            $this->jsonResponse(['status' => 'success', 'message' => 'Asistencia registrada correctamente.']);
        } else {
            $this->jsonResponse(['status' => 'error', 'message' => 'Error al registrar.']);
        }
    }
}