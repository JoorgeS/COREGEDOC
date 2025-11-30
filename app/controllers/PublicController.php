<?php

namespace App\Controllers;

use App\Models\Minuta;
use App\Config\Database;
use PDO;

class PublicController
{
    public function validarDocumento()
    {
        $hash = $_GET['hash'] ?? '';
        $resultado = null;
        $error = null;

        if (!empty($hash)) {
            $db = new Database();
            $conn = $db->getConnection();

            // 1. Buscar en Minutas
            $sql = "SELECT idMinuta, fechaMinuta, estadoMinuta, pathArchivo, 'Minuta de Reunión' as tipoDoc 
                    FROM t_minuta WHERE hashValidacion = :h LIMIT 1";
            $stmt = $conn->prepare($sql);
            $stmt->execute([':h' => $hash]);
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

            // 2. Si no encuentra, buscar en Adjuntos (si implementas hash en adjuntos)
            if (!$resultado) {
                $sqlAdj = "SELECT idAdjunto, pathAdjunto as pathArchivo, 'Documento Adjunto' as tipoDoc 
                           FROM t_adjunto WHERE hash_validacion = :h LIMIT 1"; // Asegúrate de tener esta col
                $stmtAdj = $conn->prepare($sqlAdj);
                $stmtAdj->execute([':h' => $hash]);
                $resAdj = $stmtAdj->fetch(PDO::FETCH_ASSOC);
                
                if ($resAdj) {
                    $resultado = $resAdj;
                    $resultado['estadoMinuta'] = 'Verificado';
                    $resultado['fechaMinuta'] = 'N/A';
                }
            }
        }

        // Renderizar vista (sin layout completo de admin, solo plantilla limpia)
        require_once __DIR__ . '/../../public/validar.php';
    }
}