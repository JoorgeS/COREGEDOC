<?php
/**
 * guardar_tema.php
 * Guarda un tema y su acuerdo asociado en la base de datos.
 * Tablas involucradas:
 *  - t_tema(nombreTema, objetivo, compromiso, observacion)
 *  - t_acuerdo(descAcuerdo, idTema)
 */

require_once __DIR__ . '/../cfg/config.php';

// Mostrar errores para depuración
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

class GuardarTema extends BaseConexion {
    private $db;

    public function __construct() {
        $this->db = $this->conectar();
    }

    /**
     * Guarda un tema y su acuerdo relacionado
     */
    public function guardarTema($nombreTema, $objetivo, $compromiso, $observacion, $acuerdo) {
        try {
            // Validación básica
            if (empty($nombreTema) && empty($objetivo) && empty($acuerdo)) {
                throw new Exception("Faltan datos obligatorios: nombreTema, objetivo o acuerdo");
            }

            // Insertar en t_tema
            $sqlTema = "INSERT INTO t_tema (nombreTema, objetivo, compromiso, observacion)
                        VALUES (:nombreTema, :objetivo, :compromiso, :observacion)";
            $stmt = $this->db->prepare($sqlTema);
            $stmt->execute([
                ":nombreTema" => $nombreTema,
                ":objetivo" => $objetivo,
                ":compromiso" => $compromiso,
                ":observacion" => $observacion
            ]);

            // Obtener el ID del nuevo tema
            $idTema = $this->db->lastInsertId();

            // Insertar en t_acuerdo vinculado con el tema
            $sqlAcuerdo = "INSERT INTO t_acuerdo (descAcuerdo, idTema)
                           VALUES (:acuerdo, :idTema)";
            $stmt2 = $this->db->prepare($sqlAcuerdo);
            $stmt2->execute([
                ":acuerdo" => $acuerdo,
                ":idTema" => $idTema
            ]);

            return [
                "status" => "success",
                "message" => "Tema y acuerdo guardados correctamente",
                "idTema" => $idTema
            ];
        } catch (Exception $e) {
            return [
                "status" => "error",
                "message" => $e->getMessage()
            ];
        }
    }
}

// === Entrada principal ===

// Leer datos JSON desde el frontend
$input = json_decode(file_get_contents("php://input"), true);

if ($input) {
    $nombreTema   = trim($input["nombreTema"] ?? "");
    $objetivo     = trim($input["objetivo"] ?? "");
    $compromiso   = trim($input["compromiso"] ?? "");
    $observacion  = trim($input["observacion"] ?? "");
    $acuerdo      = trim($input["acuerdo"] ?? "");

    $guardar = new GuardarTema();
    echo json_encode($guardar->guardarTema(
        $nombreTema,
        $objetivo,
        $compromiso,
        $observacion,
        $acuerdo
    ));
} else {
    echo json_encode([
        "status" => "error",
        "message" => "No se recibió información del cliente"
    ]);
}
?>
