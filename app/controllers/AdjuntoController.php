<?php
namespace App\Controllers;

use App\Models\Adjunto;

class AdjuntoController
{
    private function jsonResponse($data) {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    public function apiListar() {
        $idMinuta = $_GET['idMinuta'] ?? 0;
        if(!$idMinuta) $this->jsonResponse(['status'=>'error', 'message'=>'Falta ID']);

        $model = new Adjunto();
        $lista = $model->obtenerPorMinuta($idMinuta);
        $this->jsonResponse(['status'=>'success', 'data'=>$lista]);
    }

    public function apiSubir() {
        $idMinuta = $_POST['idMinuta'] ?? 0;
        
        if(!$idMinuta || empty($_FILES['archivo'])) {
            $this->jsonResponse(['status'=>'error', 'message'=>'Datos incompletos']);
        }

        $file = $_FILES['archivo'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'png'];

        if(!in_array($ext, $allowed)) {
            $this->jsonResponse(['status'=>'error', 'message'=>'Tipo de archivo no permitido']);
        }

        // Crear carpeta si no existe: public/uploads/minutas/{id}/
        $uploadDir = __DIR__ . '/../../public/uploads/minutas/' . $idMinuta . '/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        // Nombre único para evitar colisiones
        $fileName = uniqid('adj_') . '.' . $ext;
        $targetPath = $uploadDir . $fileName;
        
        // Ruta relativa para guardar en BD (accesible desde web)
        $webPath = 'public/uploads/minutas/' . $idMinuta . '/' . $fileName;

        if(move_uploaded_file($file['tmp_name'], $targetPath)) {
            $model = new Adjunto();
            $id = $model->guardar($idMinuta, $webPath, 'file');
            // Devolvemos el objeto creado para que el JS lo pinte
            $nuevoAdjunto = $model->obtenerPorId($id);
            $this->jsonResponse(['status'=>'success', 'data'=>$nuevoAdjunto]);
        } else {
            $this->jsonResponse(['status'=>'error', 'message'=>'Error al mover el archivo']);
        }
    }

    public function apiAgregarLink() {
        $idMinuta = $_POST['idMinuta'] ?? 0;
        $url = $_POST['urlLink'] ?? '';

        if(!$idMinuta || !$url) $this->jsonResponse(['status'=>'error', 'message'=>'Datos faltantes']);

        $model = new Adjunto();
        $id = $model->guardar($idMinuta, $url, 'link');
        $nuevoAdjunto = $model->obtenerPorId($id);
        
        $this->jsonResponse(['status'=>'success', 'data'=>$nuevoAdjunto]);
    }

    public function apiEliminar() {
        $idAdjunto = $_POST['idAdjunto'] ?? 0;
        $model = new Adjunto();
        
        // Primero obtenemos info para borrar el archivo físico si corresponde
        $adjunto = $model->obtenerPorId($idAdjunto);
        
        if($adjunto) {
            if($adjunto['tipoAdjunto'] == 'file' && file_exists(__DIR__ . '/../../' . $adjunto['pathAdjunto'])) {
                unlink(__DIR__ . '/../../' . $adjunto['pathAdjunto']);
            }
            $model->eliminar($idAdjunto);
            $this->jsonResponse(['status'=>'success']);
        } else {
            $this->jsonResponse(['status'=>'error', 'message'=>'No encontrado']);
        }
    }
}