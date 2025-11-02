<?php
// controllers/VotoController.php

require_once __DIR__ . '/../class/class.conectorDB.php';

class VotoController
{
    private PDO $pdo;

    public function __construct()
    {
        $db = new conectorDB();
        $this->pdo = $db->getDatabase();
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /**
     * Registra un voto por usuario y votación.
     * Si ya existe, devuelve status "duplicate".
     */
    public function registrarVoto(int $idVotacion, int $idUsuario, string $opcionVoto): array
    {
        try {
            // 🔹 Normalizar y validar la opción de voto
            $opcion = strtoupper(trim($opcionVoto));
            $validas = ['SI', 'NO', 'ABSTENCION'];
            if (!in_array($opcion, $validas, true)) {
                return ['status' => 'error', 'message' => 'Opción de voto inválida'];
            }

            // 🔹 Verificar si ya existe un voto para este usuario/votación
            $sqlCheck = "SELECT 1 
                         FROM t_voto 
                         WHERE t_usuario_idUsuario = :usr 
                           AND t_votacion_idVotacion = :vot 
                         LIMIT 1";
            $stmt = $this->pdo->prepare($sqlCheck);
            $stmt->execute([
                ':usr' => $idUsuario,
                ':vot' => $idVotacion
            ]);

            if ($stmt->fetchColumn()) {
                return ['status' => 'duplicate', 'message' => 'Ya registraste tu voto.'];
            }

            // 🔹 Insertar nuevo voto (sin fechaRegistro, la BD lo maneja si existe)
            $sqlInsert = "INSERT INTO t_voto (
                              t_votacion_idVotacion,
                              t_usuario_idUsuario,
                              opcionVoto
                          ) VALUES (
                              :vot,
                              :usr,
                              :op
                          )";
            $ins = $this->pdo->prepare($sqlInsert);
            $ins->execute([
                ':vot' => $idVotacion,
                ':usr' => $idUsuario,
                ':op'  => $opcion
            ]);

            return ['status' => 'success', 'message' => 'Voto registrado correctamente'];
        } catch (PDOException $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}
