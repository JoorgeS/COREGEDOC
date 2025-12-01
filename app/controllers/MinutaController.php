<?php

namespace App\Controllers;

use App\Models\Minuta;
use App\Models\Comision;
use App\Services\MailService;
use App\Services\PdfService;
use App\Config\Database;
use PDO;

class MinutaController
{
    // =========================================================================
    //  VISTAS Y NAVEGACIÓN
    // =========================================================================

    public function index()
    {
        header('Location: index.php?action=minutas_dashboard');
    }

    public function dashboard()
    {
        $this->verificarSesion();
        $data = [
            'usuario' => [
                'nombre' => $_SESSION['pNombre'] ?? '',
                'apellido' => $_SESSION['aPaterno'] ?? '',
                'rol' => $_SESSION['tipoUsuario_id'] ?? 0
            ],
            'pagina_actual' => 'minutas_dashboard'
        ];
        $childView = __DIR__ . '/../views/minutas/dashboard.php';
        require_once __DIR__ . '/../views/layouts/main.php';
    }

    public function pendientes()
    {
        $this->verificarSesion();
        $rol = $_SESSION['tipoUsuario_id'] ?? 0;
        $idUsuario = $_SESSION['idUsuario'];

        if ($rol == 3 || $rol == 1) {
            // ... (Lógica de presidente se mantiene igual) ...
            $model = new Minuta();
            $pendientes = $model->getPendientesPresidente($idUsuario);
            $data = [
                'usuario' => ['nombre' => $_SESSION['pNombre'] ?? '', 'apellido' => $_SESSION['aPaterno'] ?? '', 'rol' => $rol],
                'pagina_actual' => 'minutas_pendientes',
                'minutas' => $pendientes
            ];
            $childView = __DIR__ . '/../views/minutas/pendientes_presidente.php';
        } else {
            // --- VISTA SECRETARIO (ACTUALIZADA) ---
            // Necesitamos las comisiones para el ComboBox
            $comisionModel = new Comision();
            $listaComisiones = $comisionModel->listarTodas();

            $data = [
                'usuario' => ['nombre' => $_SESSION['pNombre'] ?? '', 'apellido' => $_SESSION['aPaterno'] ?? '', 'rol' => $rol],
                'pagina_actual' => 'minutas_pendientes',
                'minutas' => [],
                'comisiones' => $listaComisiones // <--- AGREGADO
            ];

            $childView = __DIR__ . '/../views/minutas/pendientes.php';
        }

        require_once __DIR__ . '/../views/layouts/main.php';
    }

    public function verBorrador()
    {
        $idMinuta = $_GET['id'] ?? null;
        if (!$idMinuta) die("ID requerido");

        $datosCompletos = $this->obtenerDatosParaPdf($idMinuta);

        if (!$datosCompletos) die("Minuta no encontrada");

        $pdfService = new PdfService();
        $nombreArchivo = "Borrador_Minuta_{$idMinuta}.pdf";
        $rutaTemporal = sys_get_temp_dir() . '/' . $nombreArchivo;

        if ($pdfService->generarPdfBorrador($datosCompletos, $rutaTemporal)) {
            header("Content-type: application/pdf");
            header("Content-Disposition: inline; filename={$nombreArchivo}");
            readfile($rutaTemporal);
            unlink($rutaTemporal);
            exit;
        } else {
            echo "Error al generar el PDF.";
        }
    }
    private function obtenerDatosParaPdf($idMinuta)
    {
        $db = (new \App\Config\Database())->getConnection();

        // 1. MINUTA Y REUNIÓN
        $sql = "SELECT m.*, 
                       r.idReunion, 
                       r.nombreReunion, 
                       r.fechaInicioReunion,
                       r.fechaTerminoReunion,
                       u1.pNombre as pNomPres, u1.aPaterno as aPatPres,
                       u2.pNombre as pNomSec, u2.aPaterno as aPatSec
                FROM t_minuta m
                LEFT JOIN t_reunion r ON m.idMinuta = r.t_minuta_idMinuta
                LEFT JOIN t_usuario u1 ON m.t_usuario_idPresidente = u1.idUsuario
                LEFT JOIN t_usuario u2 ON m.t_usuario_idSecretario = u2.idUsuario
                WHERE m.idMinuta = :id";

        $stmt = $db->prepare($sql);
        $stmt->execute([':id' => $idMinuta]);
        $minutaInfo = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$minutaInfo) return null;

        // PROCESAMIENTO DE HORAS
        if (!empty($minutaInfo['fechaInicioReunion'])) {
            $minutaInfo['horaInicioReal'] = date('H:i', strtotime($minutaInfo['fechaInicioReunion']));
        } else {
            $minutaInfo['horaInicioReal'] = isset($minutaInfo['horaMinuta']) ? date('H:i', strtotime($minutaInfo['horaMinuta'])) : '--:--';
        }

        if (!empty($minutaInfo['fechaTerminoReunion'])) {
            $minutaInfo['horaTerminoReal'] = date('H:i', strtotime($minutaInfo['fechaTerminoReunion']));
        } else {
            $minutaInfo['horaTerminoReal'] = 'En curso';
        }

        // 2. COMISIONES
        $comisionesInfo = [];
        if (!empty($minutaInfo['t_comision_idComision'])) {
            $stmtC = $db->prepare("SELECT nombreComision as nombre FROM t_comision WHERE idComision = :id");
            $stmtC->execute([':id' => $minutaInfo['t_comision_idComision']]);
            $com = $stmtC->fetch(PDO::FETCH_ASSOC);
            if ($com) $comisionesInfo[] = $com;
        }

        // 3. TEMAS
        $stmtT = $db->prepare("SELECT * FROM t_tema WHERE t_minuta_idMinuta = :id ORDER BY idTema ASC");
        $stmtT->execute([':id' => $idMinuta]);
        $temas = $stmtT->fetchAll(PDO::FETCH_ASSOC);

        // 4. ASISTENCIA
        $stmtA = $db->prepare("SELECT u.pNombre, u.aPaterno, a.estadoAsistencia
                                   FROM t_asistencia a
                                   JOIN t_usuario u ON a.t_usuario_idUsuario = u.idUsuario
                                   WHERE a.t_minuta_idMinuta = :id
                                   ORDER BY u.aPaterno ASC");
        $stmtA->execute([':id' => $idMinuta]);
        $asistenciaRaw = $stmtA->fetchAll(PDO::FETCH_ASSOC);
        $asistencia = [];
        foreach ($asistenciaRaw as $row) {
            $row['estaPresente'] = ($row['estadoAsistencia'] === 'PRESENTE' || $row['estadoAsistencia'] === 'ATRASADO') ? 1 : 0;
            $asistencia[] = $row;
        }

        // B. VOTACIONES (Con la corrección de columnas aplicada)
        $idReunion = $minutaInfo['idReunion'] ?? null;
        $sqlV = "SELECT * FROM t_votacion 
                     WHERE t_minuta_idMinuta = :idMin 
                     OR (t_reunion_idReunion IS NOT NULL AND t_reunion_idReunion = :idReu)";

        $stmtV = $db->prepare($sqlV);
        $stmtV->execute([':idMin' => $idMinuta, ':idReu' => $idReunion]);
        $votacionesRaw = $stmtV->fetchAll(PDO::FETCH_ASSOC);

        $votaciones = [];
        foreach ($votacionesRaw as $v) {
            // CORRECCIÓN CRÍTICA: Usamos t_votacion_idVotacion y t_usuario_idUsuario
            $stmtD = $db->prepare("SELECT u.pNombre, u.aPaterno, vo.opcionVoto 
                                       FROM t_voto vo
                                       JOIN t_usuario u ON vo.t_usuario_idUsuario = u.idUsuario
                                       WHERE vo.t_votacion_idVotacion = :idVot");
            $stmtD->execute([':idVot' => $v['idVotacion']]);
            $detalles = $stmtD->fetchAll(PDO::FETCH_ASSOC);

            // Recalcular contadores para asegurar consistencia
            $si = 0;
            $no = 0;
            $abs = 0;
            $detalleAsistentes = [];

            foreach ($detalles as $d) {
                $nombre = $d['pNombre'] . ' ' . $d['aPaterno'];
                $opcion = $d['opcionVoto'];
                $detalleAsistentes[] = ['nombre' => $nombre, 'voto' => $opcion];

                if ($opcion === 'SI' || $opcion === 'APRUEBO') $si++;
                elseif ($opcion === 'NO' || $opcion === 'RECHAZO') $no++;
                elseif ($opcion === 'ABSTENCION') $abs++;
            }

            // Determinar resultado texto
            $resultadoTexto = "SIN RESULTADO";
            if ($si > $no) $resultadoTexto = "APROBADO";
            elseif ($no > $si) $resultadoTexto = "RECHAZADO";
            elseif ($si > 0 && $si == $no) $resultadoTexto = "EMPATE";

            $votaciones[] = [
                'nombreVotacion' => $v['nombreVotacion'],
                'resultado' => $resultadoTexto,
                'contadores' => ['SI' => $si, 'NO' => $no, 'ABS' => $abs],
                'detalle_asistentes' => $detalleAsistentes
            ];
        }

        // 5. VOTACIONES Y DETALLES (CORREGIDO)
        $idReunion = $minutaInfo['idReunion'] ?? null;

        // Búsqueda ampliada: Por ID Minuta O Por ID Reunión
        $sqlV = "SELECT * FROM t_votacion 
                 WHERE t_minuta_idMinuta = :idMin 
                 OR (t_reunion_idReunion IS NOT NULL AND t_reunion_idReunion = :idReu)";

        $stmtV = $db->prepare($sqlV);
        $stmtV->execute([':idMin' => $idMinuta, ':idReu' => $idReunion]);
        $votacionesRaw = $stmtV->fetchAll(PDO::FETCH_ASSOC);

        $votaciones = [];
        foreach ($votacionesRaw as $v) {
            // Detalle de votos usando ids correctos
            $stmtD = $db->prepare("SELECT u.pNombre, u.aPaterno, vo.opcionVoto 
                                   FROM t_voto vo
                                   JOIN t_usuario u ON vo.t_usuario_idUsuario = u.idUsuario
                                   WHERE vo.t_votacion_idVotacion = :idVot");

            $stmtD->execute([':idVot' => $v['idVotacion']]);
            $detalles = $stmtD->fetchAll(PDO::FETCH_ASSOC);

            $si = 0;
            $no = 0;
            $abs = 0;
            $detalleAsistentes = [];

            foreach ($detalles as $d) {
                $nombre = $d['pNombre'] . ' ' . $d['aPaterno'];
                $opcion = $d['opcionVoto'];

                $detalleAsistentes[] = ['nombre' => $nombre, 'voto' => $opcion];

                if ($opcion === 'SI') $si++;
                elseif ($opcion === 'NO') $no++;
                elseif ($opcion === 'ABSTENCION') $abs++;
            }

            $resultadoTexto = "SIN RESULTADO";
            if ($si > $no) $resultadoTexto = "APROBADO";
            elseif ($no > $si) $resultadoTexto = "RECHAZADO";
            elseif ($si > 0 && $si == $no) $resultadoTexto = "EMPATE";

            $votaciones[] = [
                'nombreVotacion' => $v['nombreVotacion'],
                'resultado' => $resultadoTexto,
                'contadores' => ['SI' => $si, 'NO' => $no, 'ABS' => $abs],
                'detalle_asistentes' => $detalleAsistentes
            ];
        }

        // 6. FIRMAS
        $firmas = [];
        if (!empty($minutaInfo['pNomPres'])) {
            $firmas[] = [
                'pNombre' => $minutaInfo['pNomPres'],
                'aPaterno' => $minutaInfo['aPatPres'],
                'fechaAprobacion' => $minutaInfo['fechaMinuta']
            ];
        }

        return [
            'urlValidacion' => 'https://coregedoc.cl/validar?h=' . ($minutaInfo['hashValidacion'] ?? ''),
            'minuta_info' => $minutaInfo,
            'comisiones_info' => $comisionesInfo,
            'temas' => $temas,
            'asistencia' => $asistencia,
            'votaciones' => $votaciones,
            'firmas_aprobadas' => $firmas
        ];
    }
    public function aprobadas()
    {
        $this->verificarSesion();
        $comisionModel = new Comision();
        $listaComisiones = $comisionModel->listarTodas();

        $data = [
            'usuario' => [
                'nombre' => $_SESSION['pNombre'] ?? '',
                'apellido' => $_SESSION['aPaterno'] ?? '',
                'rol' => $_SESSION['tipoUsuario_id'] ?? 0
            ],
            'pagina_actual' => 'minutas_aprobadas',
            'comisiones' => $listaComisiones
        ];
        $childView = __DIR__ . '/../views/minutas/aprobadas.php';
        require_once __DIR__ . '/../views/layouts/main.php';
    }

    public function gestionar()
    {
        $this->verificarSesion();
        $idMinuta = $_GET['id'] ?? 0;
        if (!$idMinuta) {
            header('Location: index.php?action=minutas_dashboard');
            exit();
        }

        $db = new Database();
        $conn = $db->getConnection();

        // Obtener datos completos de la minuta
        $sql = "SELECT m.*, 
                       r.nombreReunion, r.t_comision_idComision_mixta, r.t_comision_idComision_mixta2,
                       c.nombreComision,
                       u_sec.pNombre as secNombre, u_sec.aPaterno as secApellido,
                       u_pres.pNombre as presNombre, u_pres.aPaterno as presApellido
                FROM t_minuta m
                LEFT JOIN t_reunion r ON m.idMinuta = r.t_minuta_idMinuta
                LEFT JOIN t_comision c ON m.t_comision_idComision = c.idComision
                LEFT JOIN t_usuario u_sec ON m.t_usuario_idSecretario = u_sec.idUsuario
                LEFT JOIN t_usuario u_pres ON m.t_usuario_idPresidente = u_pres.idUsuario
                WHERE m.idMinuta = :id";

        $stmt = $conn->prepare($sql);
        $stmt->execute([':id' => $idMinuta]);
        $minuta = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$minuta) {
            echo "Minuta no encontrada.";
            return;
        }

        // Comisiones Mixtas
        $nombresComisiones = [$minuta['nombreComision']];
        if (!empty($minuta['t_comision_idComision_mixta'])) {
            $stmtC2 = $conn->prepare("SELECT nombreComision FROM t_comision WHERE idComision = ?");
            $stmtC2->execute([$minuta['t_comision_idComision_mixta']]);
            if ($c2 = $stmtC2->fetchColumn()) $nombresComisiones[] = $c2;
        }
        if (!empty($minuta['t_comision_idComision_mixta2'])) {
            $stmtC3 = $conn->prepare("SELECT nombreComision FROM t_comision WHERE idComision = ?");
            $stmtC3->execute([$minuta['t_comision_idComision_mixta2']]);
            if ($c3 = $stmtC3->fetchColumn()) $nombresComisiones[] = $c3;
        }
        $stringComisiones = implode(' + ', $nombresComisiones);

        $minutaModel = new Minuta();
        $tipoUsuario = $_SESSION['tipoUsuario_id'] ?? 0;
        $esSecretarioTecnico = ($tipoUsuario == 2 || $tipoUsuario == 6);
        $estadoReunion = $minutaModel->verificarEstadoReunion($idMinuta);

        $data = [
            'usuario' => [
                'nombre' => $_SESSION['pNombre'] ?? '',
                'apellido' => $_SESSION['aPaterno'] ?? '',
                'rol' => $tipoUsuario
            ],
            'minuta' => $minuta,
            'header_info' => [
                'comisiones_str' => $stringComisiones,
                'nombre_reunion' => $minuta['nombreReunion'] ?? 'Sin Reunión Asignada',
                'secretario_completo' => ($minuta['secNombre'] ?? '') . ' ' . ($minuta['secApellido'] ?? ''),
                'presidente_completo' => ($minuta['presNombre'] ?? '') . ' ' . ($minuta['presApellido'] ?? ''),
                'fecha_formateada' => date('d-m-Y', strtotime($minuta['fechaMinuta'])),
                'hora_formateada' => date('H:i', strtotime($minuta['horaMinuta'])) . ' hrs.'
            ],
            'temas' => $minutaModel->getTemas($idMinuta),
            'asistencia' => $minutaModel->getAsistenciaData($idMinuta),
            'adjuntos' => $minutaModel->getAdjuntosPorMinuta($idMinuta),
            'permisos' => [
                'esSecretario' => $esSecretarioTecnico
            ],
            'estado_reunion' => $estadoReunion,
            'pagina_actual' => 'minuta_gestionar'
        ];

        $childView = __DIR__ . '/../views/minutas/editar.php';
        require_once __DIR__ . '/../views/layouts/main.php';
    }

    // =========================================================================
    //  API - ASISTENCIA
    // =========================================================================

    public function apiGuardarAsistencia()
    {
        header('Content-Type: application/json');
        $this->verificarSesion();
        $input = json_decode(file_get_contents('php://input'), true);
        $idMinuta = $input['idMinuta'] ?? 0;
        $asistencia = $input['asistencia'] ?? [];

        if (!$idMinuta) {
            echo json_encode(['status' => 'error', 'message' => 'Falta ID Minuta']);
            exit;
        }

        $model = new Minuta();
        if ($model->guardarAsistencia($idMinuta, $asistencia)) {
            echo json_encode(['status' => 'success', 'message' => 'Asistencia guardada correctamente']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Error en BD al guardar asistencia']);
        }
        exit;
    }

    public function apiGetAsistencia()
    {
        header('Content-Type: application/json');
        $this->verificarSesion();
        $idMinuta = $_GET['id'] ?? 0;

        $model = new Minuta();
        $data = $model->getAsistenciaDetallada($idMinuta);

        echo json_encode(['status' => 'success', 'data' => $data]);
        exit;
    }

    public function apiAlternarAsistencia()
    {
        header('Content-Type: application/json');
        $this->verificarSesion();

        if (!in_array($_SESSION['tipoUsuario_id'], [2, 6])) {
            echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $idMinuta = $input['idMinuta'];
        $idUsuario = $input['idUsuario'];
        $nuevoEstado = $input['estado'];

        $model = new Minuta();
        $res = $model->alternarAsistencia($idMinuta, $idUsuario, $nuevoEstado);

        echo json_encode(['status' => $res ? 'success' : 'error']);
        exit;
    }

    public function apiValidarAsistencia()
    {
        header('Content-Type: application/json');
        $this->verificarSesion();
        $input = json_decode(file_get_contents('php://input'), true);
        $idMinuta = $input['idMinuta'] ?? 0;

        if (!$idMinuta) {
            echo json_encode(['status' => 'error', 'message' => 'Falta ID Minuta']);
            exit;
        }

        try {
            $minutaModel = new Minuta();
            $minuta = $minutaModel->getMinutaById($idMinuta);

            $minutaModel->marcarAsistenciaValidada($idMinuta);

            echo json_encode(['status' => 'success', 'message' => 'Asistencia validada y enviada a Gestión.']);
        } catch (\Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    // =========================================================================
    //  API - GESTIÓN MINUTA (Temas, Finalizar, Aprobar)
    // =========================================================================

    public function apiGuardarBorrador()
    {
        header('Content-Type: application/json');
        $this->verificarSesion();
        $input = json_decode(file_get_contents('php://input'), true);
        $idMinuta = $input['idMinuta'] ?? 0;
        $temas = $input['temas'] ?? [];

        if (!$idMinuta) {
            echo json_encode(['status' => 'error', 'message' => 'Falta ID Minuta']);
            exit;
        }

        $model = new Minuta();
        $temasGuardados = $model->guardarTemas($idMinuta, $temas);

        if (isset($input['asistencia'])) {
            $model->guardarAsistencia($idMinuta, $input['asistencia']);
        }

        if ($temasGuardados) {
            echo json_encode(['status' => 'success', 'message' => 'Borrador guardado correctamente']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'No se pudieron guardar los temas']);
        }
        exit;
    }

    public function apiFinalizarReunion()
    {
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json');

        $this->verificarSesion();
        $input = json_decode(file_get_contents('php://input'), true);
        $idMinuta = $input['idMinuta'] ?? 0;

        if (!$idMinuta) {
            echo json_encode(['status' => 'error', 'message' => 'ID no proporcionado']);
            exit;
        }

        try {
            $model = new Minuta();

            // 1. Cerrar Reunión en BD
            $model->cerrarReunionDB($idMinuta);

            // 2. Marcar asistencia validada
            $model->marcarAsistenciaValidada($idMinuta);

            // 3. PREPARACIÓN DE DATOS QR Y HASH (NUEVO)
            // ---------------------------------------------------------
            $pdfService = new PdfService();

            // Generamos un Hash único para este documento de asistencia
            $hashAsistencia = hash('sha256', 'ASISTENCIA_MINUTA_' . $idMinuta . '_' . time());

            // URL de validación (Ajusta 'tu-dominio.com' o la ruta local)
            // Ejemplo: http://localhost/coregedoc/validar.php?h=...
            $baseUrl = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
            $urlValidacion = $baseUrl . "/public/validar.php?hash=" . $hashAsistencia;

            // Generamos la imagen QR en Base64
            $qrBase64 = $pdfService->generarQrBase64($urlValidacion);
            // ---------------------------------------------------------

            // 4. GENERACIÓN PDF ASISTENCIA
            $nombreArchivoDB = 'public/docs/asistencia/Asistencia_Minuta_' . $idMinuta . '.pdf'; // Nombre genérico o el mismo que usas abajo

            // ¡OJO! Asegúrate de usar la misma ruta relativa que usas para guardar el archivo físico abajo
            // En tu código original definías:
            $nombreArchivo = 'Asistencia_Minuta_' . $idMinuta . '_' . date('Ymd_His') . '.pdf';
            $rutaRelativa = 'public/docs/asistencia/' . $nombreArchivo;

            // Guardamos en BD
            $model->registrarDocumentoAsistencia($idMinuta, $rutaRelativa, $hashAsistencia);
            $rutaFisica = __DIR__ . '/../../' . $rutaRelativa;

            if (!is_dir(dirname($rutaFisica))) mkdir(dirname($rutaFisica), 0777, true);

            $rutaGenerador = __DIR__ . '/generar_pdf_asistencia.php';

            if (file_exists($rutaGenerador)) {
                require_once $rutaGenerador;

                // Pasamos los nuevos parámetros a la función (QR, Hash, URL)
                $exitoPDF = generarPdfAsistencia(
                    $idMinuta,
                    $rutaFisica,
                    (new Database())->getConnection(),
                    $_SESSION['idUsuario'],
                    __DIR__ . '/../../',
                    $qrBase64,      // <--- NUEVO
                    $hashAsistencia, // <--- NUEVO
                    $urlValidacion   // <--- NUEVO
                );

                if (!$exitoPDF) throw new \Exception("La función de PDF retornó falso.");

                // Opcional: Aquí deberías guardar el $hashAsistencia en la BD 
                // (ej: en t_adjunto o una tabla de documentos_validos) para que el validador funcione.

            } else {
                file_put_contents($rutaFisica, "FALTA ARCHIVO GENERADOR PDF.");
            }

            // 5. Obtener datos para el correo
            $info = $model->obtenerDatosReunion($idMinuta);
            $info['idMinuta'] = $idMinuta;

            // 6. ENVIAR CORREO
            $mailService = new MailService();
            $enviado = $mailService->enviarAsistencia(
                'genesis.contreras.vargas@gmail.com',
                $rutaFisica,
                $info
            );

            if ($enviado) {
                echo json_encode(['status' => 'success', 'message' => 'Reunión finalizada. PDF con QR generado y enviado.']);
            } else {
                echo json_encode(['status' => 'warning', 'message' => 'Reunión finalizada, PDF generado, pero falló el correo.']);
            }
        } catch (\Exception $e) {
            echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
        }
        exit;
    }



    public function apiEnviarAprobacion()
    {
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json');
        $this->verificarSesion();
        $input = json_decode(file_get_contents('php://input'), true);
        $idMinuta = $input['idMinuta'] ?? 0;

        try {
            $model = new Minuta();

            // Validar estado reunión
            $estado = $model->verificarEstadoReunion($idMinuta);
            if ($estado && ($estado['vigente'] == 1 || $estado['asistencia_validada'] == 0)) {
                echo json_encode(['status' => 'error', 'message' => 'Debe finalizar la reunión y validar asistencia primero.']);
                exit;
            }

            // --- NUEVO: MARCAR FEEDBACK COMO RESUELTO ---
            $db = new Database();
            $conn = $db->getConnection();
            $conn->prepare("UPDATE t_minuta_feedback SET resuelto = 1 WHERE t_minuta_idMinuta = ?")->execute([$idMinuta]);
            // --------------------------------------------

            // 1. Cambiar estado a PENDIENTE
            $model->enviarParaFirma($idMinuta, $_SESSION['idUsuario']);

            // 2. Notificar a los Presidentes
            $presidentes = $model->getCorreosPresidentes($idMinuta);
            $mailService = new MailService();
            $correosEnviados = 0;

            foreach ($presidentes as $presi) {
                if (!empty($presi['correo'])) {
                    $mailService->notificarFirma($presi['correo'], $presi['pNombre'], $idMinuta);
                    $correosEnviados++;
                }
            }

            echo json_encode([
                'status' => 'success',
                'message' => "Minuta reenviada a firma correctamente."
            ]);
        } catch (\Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }
    public function apiFirmarMinuta()
    {
        header('Content-Type: application/json');
        $this->verificarSesion();
        $input = json_decode(file_get_contents('php://input'), true);
        $idMinuta = $input['idMinuta'] ?? 0;

        if (!$idMinuta) {
            echo json_encode(['status' => 'error', 'message' => 'ID Minuta no proporcionado']);
            exit;
        }

        try {
            $minutaModel = new Minuta();
            $resultado = $minutaModel->firmarMinuta($idMinuta, $_SESSION['idUsuario']);
            $nuevoEstado = $resultado['estado_nuevo'];
            if ($nuevoEstado === 'APROBADA') {
                // 1. CONEXIÓN A BASE DE DATOS (Primero que todo)
                $db = new Database();
                $pdo = $db->getConnection();

                // 2. OBTENER DATOS GENERALES MINUTA
                // Importante: Agregamos r.idReunion para poder buscar las votaciones
                $sqlM = "SELECT m.*, c.nombreComision, r.nombreReunion, r.fechaInicioReunion, r.idReunion
                         FROM t_minuta m
                         LEFT JOIN t_comision c ON m.t_comision_idComision = c.idComision
                         LEFT JOIN t_reunion r ON m.idMinuta = r.t_minuta_idMinuta
                         WHERE m.idMinuta = :id";
                $stmt = $pdo->prepare($sqlM);
                $stmt->execute([':id' => $idMinuta]);
                $minutaInfo = $stmt->fetch(\PDO::FETCH_ASSOC);

                // 3. OBTENER TEMAS
                $stmtT = $pdo->prepare("SELECT * FROM t_tema WHERE t_minuta_idMinuta = :id ORDER BY idTema ASC");
                $stmtT->execute([':id' => $idMinuta]);
                $temas = $stmtT->fetchAll(\PDO::FETCH_ASSOC);

                // 4. OBTENER FIRMAS
                $sqlF = "SELECT u.pNombre, u.aPaterno, am.fechaAprobacion
                         FROM t_aprobacion_minuta am
                         JOIN t_usuario u ON am.t_usuario_idPresidente = u.idUsuario
                         WHERE am.t_minuta_idMinuta = :id AND am.estado_firma = 'FIRMADO'";
                $stmtF = $pdo->prepare($sqlF);
                $stmtF->execute([':id' => $idMinuta]);
                $firmas = $stmtF->fetchAll(\PDO::FETCH_ASSOC);

                // ---------------------------------------------------------
                // 5. NUEVO BLOQUE: OBTENER ASISTENCIA Y VOTACIONES
                // ---------------------------------------------------------

                // A. ASISTENCIA
                $stmtA = $pdo->prepare("SELECT u.pNombre, u.aPaterno, a.estadoAsistencia
                                       FROM t_asistencia a
                                       JOIN t_usuario u ON a.t_usuario_idUsuario = u.idUsuario
                                       WHERE a.t_minuta_idMinuta = :id
                                       ORDER BY u.aPaterno ASC");
                $stmtA->execute([':id' => $idMinuta]);
                $asistenciaRaw = $stmtA->fetchAll(\PDO::FETCH_ASSOC);
                $asistencia = [];
                foreach ($asistenciaRaw as $row) {
                    // Marcamos como presente si dice PRESENTE o ATRASADO
                    $row['estaPresente'] = ($row['estadoAsistencia'] === 'PRESENTE' || $row['estadoAsistencia'] === 'ATRASADO') ? 1 : 0;
                    $asistencia[] = $row;
                }

                // B. VOTACIONES (Con corrección de nombres de columnas)
                $idReunion = $minutaInfo['idReunion'] ?? null;
                $sqlV = "SELECT * FROM t_votacion 
                         WHERE t_minuta_idMinuta = :idMin 
                         OR (t_reunion_idReunion IS NOT NULL AND t_reunion_idReunion = :idReu)";

                $stmtV = $pdo->prepare($sqlV);
                $stmtV->execute([':idMin' => $idMinuta, ':idReu' => $idReunion]);
                $votacionesRaw = $stmtV->fetchAll(\PDO::FETCH_ASSOC);

                $votaciones = [];
                foreach ($votacionesRaw as $v) {
                    // Consultamos el detalle usando las columnas correctas: t_votacion_idVotacion y t_usuario_idUsuario
                    $stmtD = $pdo->prepare("SELECT u.pNombre, u.aPaterno, vo.opcionVoto 
                                           FROM t_voto vo
                                           JOIN t_usuario u ON vo.t_usuario_idUsuario = u.idUsuario
                                           WHERE vo.t_votacion_idVotacion = :idVot");
                    $stmtD->execute([':idVot' => $v['idVotacion']]);
                    $detalles = $stmtD->fetchAll(\PDO::FETCH_ASSOC);

                    // Recalcular contadores manualmente para asegurar datos en el PDF
                    $si = 0;
                    $no = 0;
                    $abs = 0;
                    $detalleAsistentes = [];

                    foreach ($detalles as $d) {
                        $nombre = $d['pNombre'] . ' ' . $d['aPaterno'];
                        $opcion = $d['opcionVoto'];
                        $detalleAsistentes[] = ['nombre' => $nombre, 'voto' => $opcion];

                        if ($opcion === 'SI' || $opcion === 'APRUEBO') $si++;
                        elseif ($opcion === 'NO' || $opcion === 'RECHAZO') $no++;
                        elseif ($opcion === 'ABSTENCION' || $opcion === 'ABS') $abs++;
                    }

                    // Determinar resultado texto
                    $resultadoTexto = "SIN RESULTADO";
                    if ($si > $no) $resultadoTexto = "APROBADO";
                    elseif ($no > $si) $resultadoTexto = "RECHAZADO";
                    elseif ($si > 0 && $si == $no) $resultadoTexto = "EMPATE";

                    $votaciones[] = [
                        'nombreVotacion' => $v['nombreVotacion'],
                        'resultado' => $resultadoTexto,
                        'contadores' => ['SI' => $si, 'NO' => $no, 'ABS' => $abs],
                        'detalle_asistentes' => $detalleAsistentes
                    ];
                }
                // ---------------------------------------------------------

                // 6. GENERAR PDF
                $hash = hash('sha256', $idMinuta . time() . 'SECRET_SALT_CORE');
                $minutaInfo['hashValidacion'] = $hash;

                $nombreArchivo = 'Minuta_Final_N' . $idMinuta . '_' . date('YmdHis') . '.pdf';
                $rutaRelativa = 'public/docs/minutas_aprobadas/' . $nombreArchivo;
                $rutaAbsoluta = __DIR__ . '/../../' . $rutaRelativa;

                if (!is_dir(dirname($rutaAbsoluta))) {
                    mkdir(dirname($rutaAbsoluta), 0777, true);
                }

                // Aquí las variables $asistencia y $votaciones YA EXISTEN porque las creamos en el paso 5
                $datosParaPdf = [
                    'minuta_info' => $minutaInfo,
                    'temas' => $temas,
                    'firmas_aprobadas' => $firmas,
                    'asistencia' => $asistencia,
                    'votaciones' => $votaciones,
                    'comisiones_info' => [
                        'com1' => ['nombre' => $minutaInfo['nombreComision'] ?? 'Comisión']
                    ],
                    'urlValidacion' => (defined('BASE_URL') ? BASE_URL : 'http://localhost/coregedoc') . "/index.php?action=validar&hash=" . $hash
                ];

                $pdfService = new PdfService();
                $exitoPDF = $pdfService->generarPdfFinal($datosParaPdf, $rutaAbsoluta);

                if ($exitoPDF) {
                    $minutaModel->actualizarPathArchivo($idMinuta, $rutaRelativa);
                    $minutaModel->actualizarHash($idMinuta, $hash);
                } else {
                    throw new \Exception("Error al generar el archivo PDF físico.");
                }
            }
            echo json_encode([
                'status' => 'success',
                'message' => 'Firma registrada correctamente.',
                'nuevo_estado' => $nuevoEstado
            ]);
        } catch (\Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    public function apiEnviarFeedback()
    {
        header('Content-Type: application/json');
        $this->verificarSesion();
        $input = json_decode(file_get_contents('php://input'), true);
        $idMinuta = $input['idMinuta'] ?? 0;
        $texto = $input['feedback'] ?? '';

        if (empty($texto)) {
            echo json_encode(['status' => 'error', 'message' => 'El comentario no puede estar vacío.']);
            exit;
        }

        try {
            $model = new Minuta();
            $model->guardarFeedback($idMinuta, $_SESSION['idUsuario'], $texto);
            echo json_encode(['status' => 'success', 'message' => 'Observaciones enviadas al Secretario Técnico.']);
        } catch (\Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    // =========================================================================
    //  API - VOTACIONES
    // =========================================================================

    public function apiCrearVotacion()
    {
        header('Content-Type: application/json');
        $this->verificarSesion();
        $input = json_decode(file_get_contents('php://input'), true);

        if (empty($input['nombre'])) {
            echo json_encode(['status' => 'error', 'message' => 'Nombre obligatorio']);
            exit;
        }

        $model = new Minuta();
        $res = $model->crearVotacion($input['idMinuta'], $input['nombre'], $input['idComision'] ?? null);
        echo json_encode(['status' => $res ? 'success' : 'error']);
        exit;
    }

    public function apiCerrarVotacion()
    {
        header('Content-Type: application/json');
        $this->verificarSesion();
        $input = json_decode(file_get_contents('php://input'), true);

        $model = new Minuta();
        $res = $model->cerrarVotacion($input['idVotacion']);
        echo json_encode(['status' => $res ? 'success' : 'error']);
        exit;
    }

    public function apiGetVotaciones()
    {
        header('Content-Type: application/json');
        $this->verificarSesion();
        $idMinuta = $_GET['id'] ?? 0;

        $model = new Minuta();
        $votaciones = $model->getResultadosVotacion($idMinuta);
        echo json_encode(['status' => 'success', 'data' => $votaciones]);
        exit;
    }

    public function apiGetDetalleVoto()
    {
        header('Content-Type: application/json');
        $this->verificarSesion();
        $idVotacion = $_GET['id'] ?? 0;

        $model = new Minuta();
        $detalle = $model->getDetalleVotos($idVotacion);
        echo json_encode(['status' => 'success', 'data' => $detalle]);
        exit;
    }

    // =========================================================================
    //  API - LISTADOS Y FILTROS
    // =========================================================================

    public function apiFiltrarAprobadas()
    {
        header('Content-Type: application/json');
        $this->verificarSesion();
        $db = new Database();
        $conn = $db->getConnection();

        try {
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = 10;
            $offset = ($page - 1) * $limit;
            $ordenColumna = isset($_GET['orderBy']) ? $_GET['orderBy'] : 'fechaMinuta';
            $ordenDireccion = isset($_GET['orderDir']) && strtoupper($_GET['orderDir']) === 'ASC' ? 'ASC' : 'DESC';

            $columnasPermitidas = ['idMinuta', 'fechaMinuta', 'nombreReunion', 'nombreComision'];
            if (!in_array($ordenColumna, $columnasPermitidas)) $ordenColumna = 'fechaMinuta';

            $sqlOrderBy = "ORDER BY m.{$ordenColumna} {$ordenDireccion}";
            $params = [];
            $whereConditions = ["m.estadoMinuta = 'APROBADA'"];

            if (!empty($_GET['desde'])) {
                $whereConditions[] = "m.fechaMinuta >= :desde";
                $params[':desde'] = $_GET['desde'];
            }
            if (!empty($_GET['hasta'])) {
                $whereConditions[] = "m.fechaMinuta <= :hasta";
                $params[':hasta'] = $_GET['hasta'];
            }
            if (!empty($_GET['comision'])) {
                $whereConditions[] = "m.t_comision_idComision = :comision";
                $params[':comision'] = $_GET['comision'];
            }
            if (!empty($_GET['q'])) {
                $term = $_GET['q'];
                $whereConditions[] = "(r.nombreReunion LIKE :q1 OR c.nombreComision LIKE :q2 OR m.idMinuta LIKE :q_exact)";
                $params[':q1'] = "%$term%";
                $params[':q2'] = "%$term%";
                $params[':q_exact'] = "$term";
            }

            $sqlWhere = "WHERE " . implode(" AND ", $whereConditions);

            // Total
            $sqlCount = "SELECT COUNT(DISTINCT m.idMinuta) FROM t_minuta m
                         LEFT JOIN t_reunion r ON r.t_minuta_idMinuta = m.idMinuta
                         LEFT JOIN t_comision c ON m.t_comision_idComision = c.idComision
                         $sqlWhere";
            $stmtCount = $conn->prepare($sqlCount);
            $stmtCount->execute($params);
            $totalRecords = $stmtCount->fetchColumn();
            $totalPages = ceil($totalRecords / $limit);

            // Data
            $sqlData = "SELECT m.idMinuta, m.fechaMinuta AS fecha, m.pathArchivo, r.nombreReunion, c.nombreComision,
                        (SELECT COUNT(*) FROM t_adjunto a WHERE a.t_minuta_idMinuta = m.idMinuta) AS numAdjuntos
                        FROM t_minuta m
                        LEFT JOIN t_reunion r ON r.t_minuta_idMinuta = m.idMinuta
                        LEFT JOIN t_comision c ON m.t_comision_idComision = c.idComision
                        $sqlWhere
                        GROUP BY m.idMinuta
                        {$sqlOrderBy}
                        LIMIT $limit OFFSET $offset";

            $stmt = $conn->prepare($sqlData);
            $stmt->execute($params);
            $data = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            echo json_encode([
                'status' => 'success',
                'data' => $data,
                'total' => $totalRecords,
                'page' => $page,
                'totalPages' => $totalPages
            ]);
        } catch (\Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    public function apiFiltrarPendientes()
    {
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json; charset=utf-8');
        $this->verificarSesion();
        $db = new Database();
        $conn = $db->getConnection();

        try {
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = 10;
            $offset = ($page - 1) * $limit;

            // Filtro Base
            $whereConditions = ["UPPER(m.estadoMinuta) IN ('PENDIENTE', 'BORRADOR', 'REQUIERE_REVISION')"];
            $params = [];

            // 1. Fechas
            if (!empty($_GET['desde'])) {
                $whereConditions[] = "m.fechaMinuta >= :desde";
                $params[':desde'] = $_GET['desde'];
            }
            if (!empty($_GET['hasta'])) {
                $whereConditions[] = "m.fechaMinuta <= :hasta";
                $params[':hasta'] = $_GET['hasta'];
            }

            // 2. Comisión (ComboBox)
            if (!empty($_GET['comisionId'])) {
                $whereConditions[] = "m.t_comision_idComision = :comisionId";
                $params[':comisionId'] = $_GET['comisionId'];
            }

            // 3. Buscador Inteligente (Reunión, Tema u Objetivo)
            if (!empty($_GET['q'])) {
                $term = "%" . $_GET['q'] . "%";
                $whereConditions[] = "(
                    r.nombreReunion LIKE :q1 OR 
                    m.idMinuta LIKE :q2 OR
                    EXISTS (SELECT 1 FROM t_tema t WHERE t.t_minuta_idMinuta = m.idMinuta AND (t.nombreTema LIKE :q3 OR t.objetivo LIKE :q4))
                )";
                $params[':q1'] = $term;
                $params[':q2'] = $term;
                $params[':q3'] = $term;
                $params[':q4'] = $term;
            }

            $sqlWhere = "WHERE " . implode(" AND ", $whereConditions);

            // Contar Total
            $sqlCount = "SELECT COUNT(DISTINCT m.idMinuta) FROM t_minuta m 
                         LEFT JOIN t_reunion r ON m.idMinuta = r.t_minuta_idMinuta 
                         $sqlWhere";
            $stmtCount = $conn->prepare($sqlCount);
            $stmtCount->execute($params);
            $totalRecords = $stmtCount->fetchColumn();
            $totalPages = ceil($totalRecords / $limit);

            // Datos Finales (Columnas solicitadas)
            $sqlData = "SELECT m.idMinuta, m.fechaMinuta AS fechaCreacion, m.estadoMinuta,
                        COALESCE(r.nombreReunion, 'Sin Reunión') as nombreReunion, -- <--- AGREGADO
                        COALESCE(c.nombreComision, 'Sin Comisión') as nombreComision,
                        COALESCE(up.pNombre, '') as presidenteNombre, COALESCE(up.aPaterno, '') as presidenteApellido,
                        (SELECT GROUP_CONCAT(nombreTema SEPARATOR ' || ') FROM t_tema WHERE t_minuta_idMinuta = m.idMinuta) AS listaTemas, -- <--- TODOS LOS TEMAS
                        (SELECT COUNT(*) FROM t_adjunto WHERE t_minuta_idMinuta = m.idMinuta) AS numAdjuntos,
                        (SELECT COUNT(*) FROM t_minuta_feedback WHERE t_minuta_idMinuta = m.idMinuta AND resuelto = 0) as tieneFeedback
                        FROM t_minuta m
                        LEFT JOIN t_reunion r ON m.idMinuta = r.t_minuta_idMinuta
                        LEFT JOIN t_comision c ON m.t_comision_idComision = c.idComision
                        LEFT JOIN t_usuario up ON c.t_usuario_idPresidente = up.idUsuario
                        $sqlWhere
                        ORDER BY m.idMinuta DESC LIMIT $limit OFFSET $offset";

            $stmt = $conn->prepare($sqlData);
            $stmt->execute($params);
            $data = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            array_walk_recursive($data, function (&$item) {
                if (is_string($item)) $item = mb_convert_encoding($item, 'UTF-8', 'UTF-8');
            });

            echo json_encode([
                'status' => 'success',
                'data' => $data,
                'total' => $totalRecords,
                'page' => $page,
                'totalPages' => $totalPages
            ]);
        } catch (\Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }
    public function apiVerAdjuntosMinuta()
    {
        header('Content-Type: application/json');
        $this->verificarSesion();
        $idMinuta = $_GET['id'] ?? 0;
        $model = new Minuta();
        $adjuntos = $model->getAdjuntosPorMinuta($idMinuta);
        echo json_encode(['status' => 'success', 'data' => $adjuntos]);
        exit;
    }


    public function verArchivoAdjunto()
    {
        $this->verificarSesion();
        $id = $_GET['id'] ?? 0;
        if (!$id) die("ID no especificado");

        $model = new Minuta();
        $adjunto = $model->getAdjuntoPorId($id);
        if (!$adjunto) die("Archivo no encontrado.");

        $rutaFisica = __DIR__ . '/../../' . $adjunto['pathAdjunto'];
        $rutaFisica = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rutaFisica);

        if (!file_exists($rutaFisica)) {
            $info = pathinfo($rutaFisica);
            $rutaSinExt = $info['dirname'] . DIRECTORY_SEPARATOR . $info['filename'];
            if (file_exists($rutaSinExt)) $rutaFisica = $rutaSinExt;
            else die("Error 404: Archivo físico no existe.");
        }

        $mime = mime_content_type($rutaFisica) ?: 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline; filename="' . basename($adjunto['pathAdjunto']) . '"');
        readfile($rutaFisica);
        exit;
    }

    public function verHistorial()
    {
        $this->verificarSesion();
        $idMinuta = $_GET['id'] ?? 0;
        if (!$idMinuta) {
            header('Location: index.php?action=minutas_dashboard');
            exit();
        }

        $model = new Minuta();
        $minuta = $model->getMinutaById($idMinuta);
        $historial = $model->getSeguimiento($idMinuta);

        if (!$minuta) {
            echo "Minuta no encontrada.";
            return;
        }

        $data = [
            'usuario' => [
                'nombre' => $_SESSION['pNombre'] ?? '',
                'apellido' => $_SESSION['aPaterno'] ?? '',
                'rol' => $_SESSION['tipoUsuario_id'] ?? 0
            ],
            'pagina_actual' => 'minuta_ver_historial',
            'minuta' => $minuta,
            'seguimiento' => $historial
        ];

        $childView = __DIR__ . '/../views/minutas/historial.php';
        require_once __DIR__ . '/../views/layouts/main.php';
    }

    public function seguimientoGeneral()
    {
        $this->verificarSesion();
        $minutaModel = new Minuta();
        $comisionModel = new Comision();

        $filters = [
            'comisionId' => $_GET['comisionId'] ?? null,
            'startDate'  => $_GET['startDate'] ?? null,
            'endDate'    => $_GET['endDate'] ?? null,
            'idMinuta'   => $_GET['idMinuta'] ?? null,
            'keyword'    => $_GET['keyword'] ?? null
        ];

        $data = [
            'usuario' => [
                'nombre' => $_SESSION['pNombre'] ?? '',
                'apellido' => $_SESSION['aPaterno'] ?? '',
                'rol' => $_SESSION['tipoUsuario_id'] ?? 0
            ],
            'pagina_actual' => 'seguimiento_general',
            'minutas' => $minutaModel->getSeguimientoGeneral($filters),
            'comisiones' => $comisionModel->listarTodas(),
            'filtros_activos' => $filters
        ];

        $childView = __DIR__ . '/../views/minutas/seguimiento_general.php';
        require_once __DIR__ . '/../views/layouts/main.php';
    }

    // =========================================================================
    //  HELPERS
    // =========================================================================

    private function verificarSesion()
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['idUsuario'])) {
            if (strpos($_GET['action'] ?? '', 'api_') === 0) {
                if (ob_get_length()) ob_clean();
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => 'Sesión expirada', 'redirect' => 'login']);
                exit;
            }
            header('Location: index.php?action=login');
            exit();
        }
    }

    // --- NUEVA FUNCIÓN: LEER FEEDBACK ---
    public function apiVerFeedback()
    {
        header('Content-Type: application/json');
        $this->verificarSesion();
        $idMinuta = $_GET['id'] ?? 0;

        try {
            $db = new Database();
            $conn = $db->getConnection();

            // Traemos el último feedback pendiente
            $sql = "SELECT f.textoFeedback, f.fechaFeedback, u.pNombre, u.aPaterno
                    FROM t_minuta_feedback f
                    JOIN t_usuario u ON f.t_usuario_idPresidente = u.idUsuario
                    WHERE f.t_minuta_idMinuta = :id AND f.resuelto = 0
                    ORDER BY f.idFeedback DESC LIMIT 1";

            $stmt = $conn->prepare($sql);
            $stmt->execute([':id' => $idMinuta]);
            $feedback = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($feedback) {
                $feedback['fechaFeedback'] = date('d/m/Y H:i', strtotime($feedback['fechaFeedback']));
                echo json_encode(['status' => 'success', 'data' => $feedback]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'No hay observaciones pendientes.']);
            }
        } catch (\Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    public function apiIniciarReunion()
    {
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json');
        $this->verificarSesion();

        $input = json_decode(file_get_contents('php://input'), true);
        $idMinuta = $input['idMinuta'] ?? 0;

        if (!$idMinuta) {
            echo json_encode(['status' => 'error', 'message' => 'ID inválido']);
            exit;
        }

        try {
            $model = new Minuta();
            // Activamos la reunión
            $model->iniciarReunionDB($idMinuta);
            echo json_encode(['status' => 'success', 'message' => 'Reunión Habilitada. Los consejeros ya pueden registrar asistencia.']);
        } catch (\Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }
}
