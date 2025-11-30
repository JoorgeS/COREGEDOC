<?php

namespace App\Controllers;

use App\Config\Database;
use Exception;
use PDO;

class AdjuntoController
{
    private $conn;

    public function __construct()
    {
        $db = new Database();
        $this->conn = $db->getConnection();
        
        if (session_status() === PHP_SESSION_NONE) session_start();
    }

    private function jsonResponse($data) {
        // Limpiar cualquier salida previa accidental
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
        exit;
    }

    // 1. LISTAR ADJUNTOS
    public function apiListar() {
        $idMinuta = $_GET['id'] ?? 0;

        if(!$idMinuta) {
            $this->jsonResponse(['status'=>'error', 'message'=>'Falta ID']);
        }

        try {
            // No incluimos 'asistencia' para que no salga el PDF generado automáticamente
            $sql = "SELECT * FROM t_adjunto 
                    WHERE t_minuta_idMinuta = :id 
                    AND tipoAdjunto != 'asistencia' 
                    ORDER BY idAdjunto DESC";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([':id' => $idMinuta]);
            $lista = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Mapeamos los datos para 'inventar' el campo nombreArchivo que el JS espera
            $data = array_map(function($item) {
                return [
                    'idAdjunto' => $item['idAdjunto'],
                    't_minuta_idMinuta' => $item['t_minuta_idMinuta'],
                    'pathAdjunto' => $item['pathAdjunto'],
                    'tipoAdjunto' => $item['tipoAdjunto'],
                    // Extraemos el nombre visual desde la ruta guardada
                    'nombreArchivo' => basename($item['pathAdjunto']),
                    'fechaSubida' => '' // Campo vacío para que no falle el JS si lo busca
                ];
            }, $lista);

            $this->jsonResponse(['status'=>'success', 'data'=>$data]);
        } catch (Exception $e) {
            $this->jsonResponse(['status'=>'error', 'message'=>$e->getMessage()]);
        }
    }

    // 2. SUBIR ARCHIVOS
    public function apiSubir() {
        // Aumentar memoria para videos grandes
        ini_set('memory_limit', '256M');
        ini_set('max_execution_time', 300);

        $idMinuta = $_POST['idMinuta'] ?? 0;
        
        if(!$idMinuta || empty($_FILES['archivos'])) {
            $this->jsonResponse(['status'=>'error', 'message'=>'Datos incompletos']);
        }

        $uploadDir = __DIR__ . '/../../public/docs/adjuntos/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        // Lista de extensiones permitidas
        $allowed = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'png', 'jpeg', 'txt', 'zip', 'rar', 'mp4', 'avi', 'flv', 'mov', 'mkv'];
        
        $archivos = $_FILES['archivos'];
        $total = count($archivos['name']);
        $errores = [];

        try {
            $this->conn->beginTransaction();

            for($i = 0; $i < $total; $i++) {
                $fileName = $archivos['name'][$i];
                $tmpName  = $archivos['tmp_name'][$i];
                $error    = $archivos['error'][$i];

                if ($error !== UPLOAD_ERR_OK) {
                    $errores[] = "Error al subir $fileName";
                    continue;
                }

                $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                if(!in_array($ext, $allowed)) {
                    $errores[] = "$fileName: Formato no permitido.";
                    continue;
                }

                // Generar nombre único limpiando caracteres raros
                $cleanName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $fileName);
                $nuevoNombre = 'adj_' . $idMinuta . '_' . time() . '_' . $cleanName;
                
                $destinoFisico = $uploadDir . $nuevoNombre;
                $rutaBD = 'public/docs/adjuntos/' . $nuevoNombre;

                if(move_uploaded_file($tmpName, $destinoFisico)) {
                    // INSERT CORREGIDO: Sin nombreArchivo ni fechaSubida
                    $sql = "INSERT INTO t_adjunto (t_minuta_idMinuta, pathAdjunto, tipoAdjunto) 
                            VALUES (:idMinuta, :path, 'file')";
                    $stmt = $this->conn->prepare($sql);
                    $stmt->execute([
                        ':idMinuta' => $idMinuta,
                        ':path' => $rutaBD
                    ]);
                } else {
                    $errores[] = "No se pudo mover el archivo $fileName";
                }
            }

            if (empty($errores)) {
                $this->conn->commit();
                $this->jsonResponse(['status'=>'success', 'message'=>'Archivos subidos']);
            } else {
                $this->conn->commit(); // Guardar los que sí funcionaron
                $this->jsonResponse(['status'=>'warning', 'message'=>'Errores: ' . implode(', ', $errores)]);
            }

        } catch (Exception $e) {
            $this->conn->rollBack();
            $this->jsonResponse(['status'=>'error', 'message'=>'Error BD: ' . $e->getMessage()]);
        }
    }

    // 3. AGREGAR LINK
    public function apiAgregarLink() {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $idMinuta = $input['idMinuta'] ?? 0;
        $url = $input['url'] ?? '';
        // Nota: El campo 'nombre' que viene del JS lo ignoramos porque la BD no tiene donde guardarlo.
        // Se usará la URL como nombre visual.

        if(!$idMinuta || !$url) {
            $this->jsonResponse(['status'=>'error', 'message'=>'Falta la URL']);
        }

        try {
            // INSERT CORREGIDO: Guardamos la URL en pathAdjunto
            $sql = "INSERT INTO t_adjunto (t_minuta_idMinuta, pathAdjunto, tipoAdjunto) 
                    VALUES (:idMinuta, :url, 'link')";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':idMinuta' => $idMinuta,
                ':url' => $url
            ]);
            
            $this->jsonResponse(['status'=>'success']);
        } catch (Exception $e) {
            $this->jsonResponse(['status'=>'error', 'message'=>$e->getMessage()]);
        }
    }

    // 4. ELIMINAR ADJUNTO
    public function apiEliminar() {
        $input = json_decode(file_get_contents('php://input'), true);
        $idAdjunto = $input['idAdjunto'] ?? 0;

        if (!$idAdjunto) {
            $this->jsonResponse(['status'=>'error', 'message'=>'ID inválido']);
        }

        try {
            // Buscar archivo para borrar físico si aplica
            $stmt = $this->conn->prepare("SELECT pathAdjunto, tipoAdjunto FROM t_adjunto WHERE idAdjunto = :id");
            $stmt->execute([':id' => $idAdjunto]);
            $adjunto = $stmt->fetch(PDO::FETCH_ASSOC);

            if($adjunto) {
                // Borrar si es archivo local (tipo 'file' o 'archivo')
                if(($adjunto['tipoAdjunto'] == 'file' || $adjunto['tipoAdjunto'] == 'archivo')) {
                    $rutaFisica = __DIR__ . '/../../' . $adjunto['pathAdjunto'];
                    if(file_exists($rutaFisica)) {
                        unlink($rutaFisica);
                    }
                }

                $stmtDel = $this->conn->prepare("DELETE FROM t_adjunto WHERE idAdjunto = :id");
                $stmtDel->execute([':id' => $idAdjunto]);
                
                $this->jsonResponse(['status'=>'success']);
            } else {
                $this->jsonResponse(['status'=>'error', 'message'=>'No encontrado']);
            }

        } catch (Exception $e) {
            $this->jsonResponse(['status'=>'error', 'message'=>$e->getMessage()]);
        }
    }
}