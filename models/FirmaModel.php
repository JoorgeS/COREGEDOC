<?php
require_once(__DIR__ . '/../class/class.conectorDB.php');

class FirmaModel extends conectorDB
{
    /**
     * Registra una nueva firma electrónica en la tabla t_firma
     *
     * @param int $idUsuario       ID del usuario que firma
     * @param int $idComision      ID de la comisión asociada a la minuta
     * @param int $idTipoUsuario   Tipo de usuario (1 = presidente, 2 = secretario, etc.)
     * @param int $idMinuta        ID de la minuta firmada
     * @return bool                true si la inserción fue exitosa, false si falló
     */
    public function registrarFirma($idUsuario, $idComision, $idTipoUsuario, $idMinuta)
    {
        try {
            $pdo = $this->getDatabase();

            // Validación básica
            if (empty($idUsuario) || empty($idComision) || empty($idMinuta)) {
                error_log("⚠️ registrarFirma: parámetros incompletos (Usuario: $idUsuario, Comisión: $idComision, Minuta: $idMinuta)");
                return false;
            }

            // 💡 fechaGuardado es TIME — si se cambia a DATETIME, usa NOW()
            $sql = "INSERT INTO t_firma 
                    (descFirma, idTipoUsuario, fechaGuardado, idUsuario, idComision)
                    VALUES 
                    (:desc, :tipo, CURTIME(), :usuario, :comision)";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':desc'     => 'Firma electrónica registrada al aprobar minuta ' . $idMinuta,
                ':tipo'     => (int)$idTipoUsuario,
                ':usuario'  => (int)$idUsuario,
                ':comision' => (int)$idComision
            ]);

            if ($stmt->rowCount() > 0) {
                error_log("✅ Firma registrada correctamente: usuario=$idUsuario | comisión=$idComision | minuta=$idMinuta");
                return true;
            } else {
                error_log("⚠️ No se insertó ninguna fila en t_firma (usuario: $idUsuario, minuta: $idMinuta)");
                return false;
            }
        } catch (Throwable $e) {
            error_log("❌ Error al registrar firma en BD: " . $e->getMessage());
            return false;
        }
    }
}
