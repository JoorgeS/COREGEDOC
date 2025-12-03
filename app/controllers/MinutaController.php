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
                       r.t_comision_idComision_mixta,
                       r.t_comision_idComision_mixta2,
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
        $minutaInfo['horaInicioReal'] = !empty($minutaInfo['fechaInicioReunion']) ? date('H:i', strtotime($minutaInfo['fechaInicioReunion'])) : (isset($minutaInfo['horaMinuta']) ? date('H:i', strtotime($minutaInfo['horaMinuta'])) : '--:--');
        $minutaInfo['horaTerminoReal'] = !empty($minutaInfo['fechaTerminoReunion']) ? date('H:i', strtotime($minutaInfo['fechaTerminoReunion'])) : 'En curso';

        // 2. COMISIONES Y PRESIDENTES
        $idsComisiones = [];
        if (!empty($minutaInfo['t_comision_idComision'])) $idsComisiones[] = $minutaInfo['t_comision_idComision'];
        if (!empty($minutaInfo['t_comision_idComision_mixta'])) $idsComisiones[] = $minutaInfo['t_comision_idComision_mixta'];
        if (!empty($minutaInfo['t_comision_idComision_mixta2'])) $idsComisiones[] = $minutaInfo['t_comision_idComision_mixta2'];

        $comisionesInfo = [];
        $firmasPresidentes = []; 
        $nombresTexto = []; // Para el encabezado

        if (!empty($idsComisiones)) {
            $inQuery = implode(',', array_fill(0, count($idsComisiones), '?'));
            $sqlC = "SELECT c.nombreComision, u.pNombre, u.aPaterno 
                     FROM t_comision c
                     LEFT JOIN t_usuario u ON c.t_usuario_idPresidente = u.idUsuario
                     WHERE c.idComision IN ($inQuery)";
            $stmtC = $db->prepare($sqlC);
            $stmtC->execute($idsComisiones);
            $resComs = $stmtC->fetchAll(PDO::FETCH_ASSOC);

            foreach ($resComs as $c) {
                $comisionesInfo[] = ['nombre' => $c['nombreComision']];
                if (!empty($c['pNombre'])) {
                    $nombreComp = $c['pNombre'] . ' ' . $c['aPaterno'];
                    $nombresTexto[] = $nombreComp;
                    
                    $firmasPresidentes[] = [
                        'pNombre' => $c['pNombre'],
                        'aPaterno' => $c['aPaterno'],
                        'nombreComision' => $c['nombreComision'],
                        'fechaAprobacion' => $minutaInfo['fechaAprobacion'] ?? $minutaInfo['fechaMinuta']
                    ];
                }
            }
        }
        
        // Cadena para el encabezado PDF (ej: "Juan Pérez / María López")
        $presidentesStr = implode(' / ', array_unique($nombresTexto));

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

        // 5. VOTACIONES
        $idReunion = $minutaInfo['idReunion'] ?? null;
        $sqlV = "SELECT v.*, c.nombreComision as nombreComisionVoto
             FROM t_votacion v
             LEFT JOIN t_comision c ON v.idComision = c.idComision
             WHERE v.t_minuta_idMinuta = :idMin 
             OR (v.t_reunion_idReunion IS NOT NULL AND v.t_reunion_idReunion = :idReu)";

        $stmtV = $db->prepare($sqlV);
        $stmtV->execute([':idMin' => $idMinuta, ':idReu' => $idReunion]);
        $votacionesRaw = $stmtV->fetchAll(PDO::FETCH_ASSOC);
        $votaciones = [];

        foreach ($votacionesRaw as $v) {
            $stmtD = $db->prepare("SELECT u.pNombre, u.aPaterno, vo.opcionVoto 
                                   FROM t_voto vo
                                   JOIN t_usuario u ON vo.t_usuario_idUsuario = u.idUsuario
                                   WHERE vo.t_votacion_idVotacion = :idVot");
            $stmtD->execute([':idVot' => $v['idVotacion']]);
            $detalles = $stmtD->fetchAll(PDO::FETCH_ASSOC);

            $si = 0; $no = 0; $abs = 0;
            $detalleAsistentes = [];
            foreach ($detalles as $d) {
                $nombre = $d['pNombre'] . ' ' . $d['aPaterno'];
                $opcion = $d['opcionVoto'];
                $detalleAsistentes[] = ['nombre' => $nombre, 'voto' => $opcion];
                if ($opcion === 'SI' || $opcion === 'APRUEBO') $si++;
                elseif ($opcion === 'NO' || $opcion === 'RECHAZO') $no++;
                elseif ($opcion === 'ABSTENCION') $abs++;
            }
            
            $resultadoTexto = "SIN RESULTADO";
            if ($si > $no) $resultadoTexto = "APROBADO";
            elseif ($no > $si) $resultadoTexto = "RECHAZADO";
            elseif ($si > 0 && $si == $no) $resultadoTexto = "EMPATE";

            $votaciones[] = [
                'nombreVotacion' => $v['nombreVotacion'],
                'nombreComision' => $v['nombreComisionVoto'] ?? 'General',
                'resultado' => $resultadoTexto,
                'contadores' => ['SI' => $si, 'NO' => $no, 'ABS' => $abs],
                'detalle_asistentes' => $detalleAsistentes
            ];
        }

        return [
            'urlValidacion' => (defined('BASE_URL') ? BASE_URL : 'https://coregedoc.cl') . '/validar?h=' . ($minutaInfo['hashValidacion'] ?? ''),
            'minuta_info' => $minutaInfo,
            'comisiones_info' => $comisionesInfo,
            'presidentes_str' => $presidentesStr, // <--- DATO CLAVE PARA PDF
            'temas' => $temas,
            'asistencia' => $asistencia,
            'votaciones' => $votaciones,
            'firmas_aprobadas' => $firmasPresidentes
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

        // Obtener datos base de la minuta y usuarios relacionados
        $sql = "SELECT m.*, 
                       r.nombreReunion, r.t_comision_idComision_mixta, r.t_comision_idComision_mixta2,
                       u_sec.pNombre as secNombre, u_sec.aPaterno as secApellido
                FROM t_minuta m
                LEFT JOIN t_reunion r ON m.idMinuta = r.t_minuta_idMinuta
                LEFT JOIN t_usuario u_sec ON m.t_usuario_idSecretario = u_sec.idUsuario
                WHERE m.idMinuta = :id";

        $stmt = $conn->prepare($sql);
        $stmt->execute([':id' => $idMinuta]);
        $minuta = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$minuta) {
            echo "Minuta no encontrada.";
            return;
        }

        // --- LÓGICA COMISIONES MIXTAS ---
        $idsComisiones = [];
        if (!empty($minuta['t_comision_idComision'])) $idsComisiones[] = $minuta['t_comision_idComision'];
        if (!empty($minuta['t_comision_idComision_mixta'])) $idsComisiones[] = $minuta['t_comision_idComision_mixta'];
        if (!empty($minuta['t_comision_idComision_mixta2'])) $idsComisiones[] = $minuta['t_comision_idComision_mixta2'];

        $listaComisionesDetalle = [];
        $nombresComisionesStr = [];
        $nombresPresidentesStr = [];

        if (!empty($idsComisiones)) {
            $inQuery = implode(',', array_fill(0, count($idsComisiones), '?'));

            // Traemos nombre comisión y nombre presidente
            $sqlC = "SELECT c.idComision, c.nombreComision, 
                            u.pNombre, u.aPaterno 
                     FROM t_comision c
                     LEFT JOIN t_usuario u ON c.t_usuario_idPresidente = u.idUsuario
                     WHERE c.idComision IN ($inQuery)";

            $stmtC = $conn->prepare($sqlC);
            $stmtC->execute($idsComisiones);
            $resultados = $stmtC->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($resultados as $res) {
                // Para el Select del Modal
                $listaComisionesDetalle[] = [
                    'id' => $res['idComision'],
                    'nombre' => $res['nombreComision']
                ];

                // Para el Header
                $nombresComisionesStr[] = $res['nombreComision'];
                if (!empty($res['pNombre'])) {
                    $nombresPresidentesStr[] = $res['pNombre'] . ' ' . $res['aPaterno'];
                }
            }
        }

        $stringComisiones = implode(' + ', $nombresComisionesStr);
        $stringPresidentes = implode(' + ', array_unique($nombresPresidentesStr));
        // --------------------------------

        $minutaModel = new Minuta();
        $tipoUsuario = $_SESSION['tipoUsuario_id'] ?? 0;
        $esSecretarioTecnico = ($tipoUsuario == 2 || $tipoUsuario == 6);
        $estadoReunion = $minutaModel->verificarEstadoReunion($idMinuta);

        $adjuntosRaw = $minutaModel->getAdjuntosPorMinuta($idMinuta);
        $adjuntosProcesados = [];
        foreach ($adjuntosRaw as $adj) {
            if ($adj['tipoAdjunto'] === 'link' && strpos($adj['pathAdjunto'], '|||') !== false) {
                // Separamos "Nombre Visual|||https://url..."
                list($nombreLink, $urlLink) = explode('|||', $adj['pathAdjunto'], 2);
                $adj['nombreArchivo'] = $nombreLink; // Sobreescribimos para la vista
                $adj['pathAdjunto'] = $urlLink;      // URL limpia
            }
            $adjuntosProcesados[] = $adj;
        }

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
                'presidente_completo' => $stringPresidentes, // Muestra todos los presidentes
                'fecha_formateada' => date('d-m-Y', strtotime($minuta['fechaMinuta'])),
                'hora_formateada' => date('H:i', strtotime($minuta['horaMinuta'])) . ' hrs.'
            ],
            // Enviamos la lista detallada a la vista
            'lista_comisiones_select' => $listaComisionesDetalle,
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

        // Obtenemos ID de usuario para el LOG
        $idUsuarioEditor = $_SESSION['idUsuario'];

        if (!$idMinuta) {
            echo json_encode(['status' => 'error', 'message' => 'Falta ID Minuta']);
            exit;
        }

        $model = new Minuta();

        // PASAMOS $idUsuarioEditor COMO 3er PARAMETRO PARA ACTIVAR LOGS DE CORRECCIÓN
        $temasGuardados = $model->guardarTemas($idMinuta, $temas, $idUsuarioEditor);

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

            // 1. Validar estado reunión (Se mantiene igual)
            $estado = $model->verificarEstadoReunion($idMinuta);
            if ($estado && ($estado['vigente'] == 1 || $estado['asistencia_validada'] == 0)) {
                echo json_encode(['status' => 'error', 'message' => 'Debe finalizar la reunión y validar asistencia primero.']);
                exit;
            }

            // 2. Marcar Feedback como Resuelto y DETECTAR si fue corrección
            $db = new Database();
            $conn = $db->getConnection();
            $stmt = $conn->prepare("UPDATE t_minuta_feedback SET resuelto = 1 WHERE t_minuta_idMinuta = ?");
            $stmt->execute([$idMinuta]);

            // Si rowCount > 0, significa que había observaciones pendientes -> Es un REENVÍO
            $esReenvio = ($stmt->rowCount() > 0);

            // 3. Cambiar estado a PENDIENTE (Lógica de DB se mantiene)
            $model->enviarParaFirma($idMinuta, $_SESSION['idUsuario']);

            // 4. NOTIFICACIONES (NUEVO BLOQUE INTEGRADO)
            // -----------------------------------------------------
            // Obtenemos los correos de la tabla t_aprobacion_minuta, 
            // que ya contiene a todos los presidentes asignados (Principal y Mixtos)
            $sqlCorreos = "SELECT u.correo, u.pNombre, u.aPaterno 
                           FROM t_aprobacion_minuta ap
                           JOIN t_usuario u ON ap.t_usuario_idPresidente = u.idUsuario
                           WHERE ap.t_minuta_idMinuta = :id";

            $stmtC = $conn->prepare($sqlCorreos);
            $stmtC->execute([':id' => $idMinuta]);
            $destinatarios = $stmtC->fetchAll(\PDO::FETCH_ASSOC);

            $mailService = new MailService();

            // Enviar correo a cada presidente
            foreach ($destinatarios as $dest) {
                if (!empty($dest['correo'])) {
                    // Asumimos que tienes un método enviarAvisoFirma en MailService
                    // Si es reenvío, podrías cambiar el asunto internamente en el MailService o pasar un flag
                    // Aquí usamos tu función 'notificarSolicitudFirma' si ya la tienes, o 'enviarAvisoFirma'
                    // Ajusta el nombre de la función según tu MailService real.

                    // Opción A: Si usas notificarSolicitudFirma (que vi en tu código pegado)
                    // $mailService->notificarSolicitudFirma($dest, $esReenvio); 

                    // Opción B: Si usas enviarAvisoFirma (estándar)
                    // Opción B: Usamos el método existente 'notificarFirma'
                    $mailService->notificarFirma($dest['correo'], $dest['pNombre'], $idMinuta);
                }
            }
            // -----------------------------------------------------

            echo json_encode([
                'status' => 'success',
                'message' => "Minuta enviada a firma y notificaciones despachadas."
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

            // =========================================================
            // 1. PREPARACIÓN PARA NOTIFICACIONES (NUEVO BLOQUE)
            // =========================================================
            $mailService = new MailService();
            // Obtenemos correos del ST y todos los presidentes involucrados
            $datosNotif = $minutaModel->getDatosNotificacion($idMinuta);
            $nombreAutor = $_SESSION['pNombre'] . ' ' . $_SESSION['aPaterno'];
            // =========================================================

            if ($nuevoEstado === 'APROBADA') {
                // 1. CONEXIÓN A BASE DE DATOS
                $db = new Database();
                $pdo = $db->getConnection();

                // 2. OBTENER DATOS GENERALES MINUTA
                $sqlM = "SELECT m.*, c.nombreComision, 
                                r.nombreReunion, r.fechaInicioReunion, r.fechaTerminoReunion, r.idReunion
                         FROM t_minuta m
                         LEFT JOIN t_comision c ON m.t_comision_idComision = c.idComision
                         LEFT JOIN t_reunion r ON m.idMinuta = r.t_minuta_idMinuta
                         WHERE m.idMinuta = :id";

                $stmt = $pdo->prepare($sqlM);
                $stmt->execute([':id' => $idMinuta]);
                $minutaInfo = $stmt->fetch(\PDO::FETCH_ASSOC);

                // PROCESAMIENTO DE HORAS
                if (!empty($minutaInfo['fechaInicioReunion'])) {
                    $minutaInfo['horaInicioReal'] = date('H:i', strtotime($minutaInfo['fechaInicioReunion']));
                } else {
                    $minutaInfo['horaInicioReal'] = isset($minutaInfo['horaMinuta']) ? date('H:i', strtotime($minutaInfo['horaMinuta'])) : '--:--';
                }

                if (!empty($minutaInfo['fechaTerminoReunion'])) {
                    $minutaInfo['horaTerminoReal'] = date('H:i', strtotime($minutaInfo['fechaTerminoReunion']));
                } else {
                    $minutaInfo['horaTerminoReal'] = date('H:i');
                }

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

                // 5. ASISTENCIA
                $stmtA = $pdo->prepare("SELECT u.pNombre, u.aPaterno, a.estadoAsistencia
                                        FROM t_asistencia a
                                        JOIN t_usuario u ON a.t_usuario_idUsuario = u.idUsuario
                                        WHERE a.t_minuta_idMinuta = :id
                                        ORDER BY u.aPaterno ASC");
                $stmtA->execute([':id' => $idMinuta]);
                $asistenciaRaw = $stmtA->fetchAll(\PDO::FETCH_ASSOC);
                $asistencia = [];
                foreach ($asistenciaRaw as $row) {
                    $row['estaPresente'] = ($row['estadoAsistencia'] === 'PRESENTE' || $row['estadoAsistencia'] === 'ATRASADO') ? 1 : 0;
                    $asistencia[] = $row;
                }

                // 6. VOTACIONES
                $idReunion = $minutaInfo['idReunion'] ?? null;

                $sqlV = "SELECT v.*, c.nombreComision as nombreComisionVoto
                         FROM t_votacion v
                         LEFT JOIN t_comision c ON v.idComision = c.idComision
                         WHERE v.t_minuta_idMinuta = :idMin 
                         OR (v.t_reunion_idReunion IS NOT NULL AND v.t_reunion_idReunion = :idReu)";

                $stmtV = $pdo->prepare($sqlV);
                $stmtV->execute([':idMin' => $idMinuta, ':idReu' => $idReunion]);
                $votacionesRaw = $stmtV->fetchAll(\PDO::FETCH_ASSOC);

                $votaciones = [];
                foreach ($votacionesRaw as $v) {
                    $stmtD = $pdo->prepare("SELECT u.pNombre, u.aPaterno, vo.opcionVoto 
                                            FROM t_voto vo
                                            JOIN t_usuario u ON vo.t_usuario_idUsuario = u.idUsuario
                                            WHERE vo.t_votacion_idVotacion = :idVot");
                    $stmtD->execute([':idVot' => $v['idVotacion']]);
                    $detalles = $stmtD->fetchAll(\PDO::FETCH_ASSOC);

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

                    $resultadoTexto = "SIN RESULTADO";
                    if ($si > $no) $resultadoTexto = "APROBADO";
                    elseif ($no > $si) $resultadoTexto = "RECHAZADO";
                    elseif ($si > 0 && $si == $no) $resultadoTexto = "EMPATE";

                    $nombreComisionVoto = $v['nombreComisionVoto'] ?? $minutaInfo['nombreComision'] ?? 'General';

                    $votaciones[] = [
                        'nombreVotacion' => $v['nombreVotacion'],
                        'nombreComision' => $nombreComisionVoto,
                        'resultado' => $resultadoTexto,
                        'contadores' => ['SI' => $si, 'NO' => $no, 'ABS' => $abs],
                        'detalle_asistentes' => $detalleAsistentes
                    ];
                }

                // 7. GENERAR PDF
                $hash = hash('sha256', $idMinuta . time() . 'SECRET_SALT_CORE');
                $minutaInfo['hashValidacion'] = $hash;

                $nombreArchivo = 'Minuta_Final_N' . $idMinuta . '_' . date('YmdHis') . '.pdf';
                $rutaRelativa = 'public/docs/minutas_aprobadas/' . $nombreArchivo;
                $rutaAbsoluta = __DIR__ . '/../../' . $rutaRelativa;

                if (!is_dir(dirname($rutaAbsoluta))) {
                    mkdir(dirname($rutaAbsoluta), 0777, true);
                }

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

                    // =====================================================
                    // 2. NOTIFICAR APROBACIÓN FINAL (Si el PDF fue un éxito)
                    // =====================================================
                    $mailService->notificarAprobacionFinal($datosNotif);
                } else {
                    throw new \Exception("Error al generar el archivo PDF físico.");
                }
            } else {
                // =========================================================
                // 3. NOTIFICAR FIRMA PARCIAL (Si no está aprobada aún)
                // =========================================================
                $mailService->notificarFirmaParcial($datosNotif, $nombreAutor);
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
            // Guardamos el feedback (esto ya resetea las firmas en BD)
            $model->guardarFeedback($idMinuta, $_SESSION['idUsuario'], $texto);

            // --- NOTIFICACIÓN CORREO ---
            $mailService = new MailService();
            $datosNotif = $model->getDatosNotificacion($idMinuta);
            $nombreAutor = $_SESSION['pNombre'] . ' ' . $_SESSION['aPaterno'];

            // Enviamos alerta a ST y demás Presidentes
            $mailService->notificarFeedback($datosNotif, $nombreAutor, $texto);
            // ---------------------------

            echo json_encode(['status' => 'success', 'message' => 'Observaciones enviadas y notificadas.']);
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
        try {
            // Aseguramos que pase el idComision
            $res = $model->crearVotacion($input['idMinuta'], $input['nombre'], $input['idComision'] ?? null);
            echo json_encode(['status' => $res ? 'success' : 'error']);
        } catch (\Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
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

    public function apiFiltrarSeguimiento()
    {
        // 1. Headers y Sesión
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json');
        $this->verificarSesion();

        $db = new Database();
        $conn = $db->getConnection();

        try {
            // 2. Recibir Parámetros
            $page     = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit    = 10; // <--- PAGINACIÓN DE 10
            $offset   = ($page - 1) * $limit;

            $desde    = $_GET['desde'] ?? date('Y-m-01');
            $hasta    = $_GET['hasta'] ?? date('Y-m-d');
            $comision = !empty($_GET['comision']) ? $_GET['comision'] : null;
            $keyword  = !empty($_GET['keyword'])  ? trim($_GET['keyword']) : null;
            $orderBy  = $_GET['orderBy'] ?? 'idMinuta';
            $orderDir = $_GET['orderDir'] ?? 'DESC';

            // Validar ordenamiento
            $allowedCols = ['idMinuta', 'fechaMinuta', 'estadoMinuta'];
            if (!in_array($orderBy, $allowedCols)) $orderBy = 'idMinuta';
            $orderDir = (strtoupper($orderDir) === 'ASC') ? 'ASC' : 'DESC';

            // 3. Construcción Dinámica del WHERE y Params
            // Definimos la base del FROM para reutilizarla en el COUNT y en el SELECT
            $sqlFrom = " FROM t_minuta m
                         LEFT JOIN t_comision c ON m.t_comision_idComision = c.idComision
                         LEFT JOIN t_reunion r ON m.idMinuta = r.t_minuta_idMinuta ";

            $whereClauses = ["m.fechaMinuta BETWEEN :desde AND :hasta"];
            $params = [
                ':desde' => $desde, 
                ':hasta' => $hasta . ' 23:59:59'
            ];

            if ($comision) {
                $whereClauses[] = "m.t_comision_idComision = :comision";
                $params[':comision'] = $comision;
            }

            if ($keyword) {
                $whereClauses[] = "(r.nombreReunion LIKE :kw1 OR m.idMinuta LIKE :kw2)";
                $params[':kw1'] = "%$keyword%";
                $params[':kw2'] = "%$keyword%";
            }

            // Unimos los WHERE
            $sqlWhere = " WHERE " . implode(" AND ", $whereClauses);

            // 4. CONSULTA 1: Contar Total de Registros (Para la paginación)
            $sqlCount = "SELECT COUNT(*) as total " . $sqlFrom . $sqlWhere;
            $stmtCount = $conn->prepare($sqlCount);
            $stmtCount->execute($params);
            $totalRecords = $stmtCount->fetchColumn();
            $totalPages = ceil($totalRecords / $limit);

            // 5. CONSULTA 2: Obtener Datos Paginados
            $sqlData = "SELECT 
                            m.idMinuta,
                            c.nombreComision,
                            m.estadoMinuta,
                            m.fechaMinuta as ultima_fecha,
                            r.nombreReunion,
                            -- Campos simulados para historial
                            'Actualización de estado' as ultimo_detalle,
                            'Sistema' as ultimo_usuario
                        " . $sqlFrom . $sqlWhere . " 
                        ORDER BY m.$orderBy $orderDir 
                        LIMIT $limit OFFSET $offset"; // <--- AQUI APLICAMOS LIMIT

            $stmt = $conn->prepare($sqlData);
            $stmt->execute($params);
            $resultados = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // 6. Retornar JSON con metadata de paginación
            echo json_encode([
                'status'     => 'success',
                'data'       => $resultados,
                'total'      => $totalRecords,
                'page'       => $page,
                'totalPages' => $totalPages
            ]);

        } catch (\Exception $e) {
            echo json_encode([
                'status'  => 'error',
                'message' => 'Error: ' . $e->getMessage()
            ]);
        }
        exit;
    }

    public function apiVerAdjuntosMinuta()
    {
        header('Content-Type: application/json');
        $this->verificarSesion();
        $idMinuta = $_GET['id'] ?? 0;

        $model = new Minuta();
        $adjuntosRaw = $model->getAdjuntosPorMinuta($idMinuta);

        // Procesamos para separar nombre de URL
        $adjuntos = [];
        foreach ($adjuntosRaw as $adj) {
            if ($adj['tipoAdjunto'] === 'link' && strpos($adj['pathAdjunto'], '|||') !== false) {
                list($nombre, $url) = explode('|||', $adj['pathAdjunto'], 2);
                $adj['nombreArchivo'] = $nombre;
                $adj['pathAdjunto'] = $url; // Para que el href funcione
            }
            $adjuntos[] = $adj;
        }

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
    // =========================================================================
    //  API - ADJUNTOS (Faltaban estas funciones)
    // =========================================================================

    public function apiSubirAdjunto()
    {
        header('Content-Type: application/json');
        $this->verificarSesion();

        $idMinuta = $_POST['idMinuta'] ?? 0;

        if (!$idMinuta || empty($_FILES['archivos'])) {
            echo json_encode(['status' => 'error', 'message' => 'Faltan datos o archivos.']);
            exit;
        }

        $db = new Database();
        $conn = $db->getConnection();
        $errores = [];

        // Directorio de destino (Ajusta según tu estructura de carpetas)
        $uploadDir = __DIR__ . '/../../public/docs/adjuntos/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        // Procesar múltiples archivos
        $archivos = $_FILES['archivos'];
        // Reorganizar el array de $_FILES si vienen múltiples
        $fileCount = count($archivos['name']);

        for ($i = 0; $i < $fileCount; $i++) {
            $fileName = $archivos['name'][$i];
            $fileTmp  = $archivos['tmp_name'][$i];
            $fileError = $archivos['error'][$i];

            if ($fileError === UPLOAD_ERR_OK) {
                // Generar nombre único para evitar sobrescribir
                $nuevoNombre = 'Adjunto_M' . $idMinuta . '_' . time() . '_' . basename($fileName);
                $destino = $uploadDir . $nuevoNombre;

                // Ruta relativa para guardar en BD
                $pathRelativo = 'public/docs/adjuntos/' . $nuevoNombre;

                if (move_uploaded_file($fileTmp, $destino)) {
                    // Guardar en Base de Datos
                    try {
                        $sql = "INSERT INTO t_adjunto (t_minuta_idMinuta, nombreArchivo, pathAdjunto, tipoAdjunto, fechaSubida) 
                                VALUES (:idMin, :nom, :path, 'archivo', NOW())";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([
                            ':idMin' => $idMinuta,
                            ':nom' => $fileName, // Nombre original para mostrar
                            ':path' => $pathRelativo
                        ]);
                    } catch (\Exception $e) {
                        $errores[] = "Error BD al guardar $fileName";
                    }
                } else {
                    $errores[] = "Error al mover el archivo $fileName";
                }
            } else {
                $errores[] = "Error de subida en archivo $fileName";
            }
        }

        if (empty($errores)) {
            echo json_encode(['status' => 'success', 'message' => 'Archivos subidos correctamente']);
        } else {
            echo json_encode(['status' => 'warning', 'message' => implode(', ', $errores)]);
        }
        exit;
    }

    public function apiGuardarLink()
    {
        header('Content-Type: application/json');
        $this->verificarSesion();
        $input = json_decode(file_get_contents('php://input'), true);

        $idMinuta = $input['idMinuta'] ?? 0;
        $nombre = $input['nombre'] ?? '';
        $url = $input['url'] ?? '';

        if (!$idMinuta || !$url) {
            echo json_encode(['status' => 'error', 'message' => 'Datos incompletos']);
            exit;
        }

        try {
            $db = new Database();
            $conn = $db->getConnection();

            // TRUCO: Como la tabla no tiene campo 'nombreArchivo', guardamos "NOMBRE|||URL" en pathAdjunto.
            // La función 'gestionar' y 'apiVerAdjuntosMinuta' sabrán separarlo.
            $valorGuardar = (!empty($nombre)) ? $nombre . '|||' . $url : $url;

            $sql = "INSERT INTO t_adjunto (t_minuta_idMinuta, pathAdjunto, tipoAdjunto, hash_validacion) 
                    VALUES (:idMin, :path, 'link', NULL)";

            $stmt = $conn->prepare($sql);
            $stmt->execute([
                ':idMin' => $idMinuta,
                ':path' => $valorGuardar // Guardamos "Nombre|||URL"
            ]);

            echo json_encode(['status' => 'success', 'message' => 'Enlace guardado correctamente']);
        } catch (\Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    public function apiEliminarAdjunto()
    {
        header('Content-Type: application/json');
        $this->verificarSesion();
        $input = json_decode(file_get_contents('php://input'), true);
        $idAdjunto = $input['idAdjunto'] ?? 0;

        if (!$idAdjunto) {
            echo json_encode(['status' => 'error', 'message' => 'ID Adjunto requerido']);
            exit;
        }

        try {
            $db = new Database();
            $conn = $db->getConnection();

            // 1. Obtener info para borrar archivo físico si corresponde
            $stmt = $conn->prepare("SELECT pathAdjunto, tipoAdjunto FROM t_adjunto WHERE idAdjunto = :id");
            $stmt->execute([':id' => $idAdjunto]);
            $adjunto = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($adjunto && $adjunto['tipoAdjunto'] !== 'link') {
                $rutaFisica = __DIR__ . '/../../' . $adjunto['pathAdjunto'];
                if (file_exists($rutaFisica)) {
                    unlink($rutaFisica);
                }
            }

            // 2. Borrar de BD
            $stmtDel = $conn->prepare("DELETE FROM t_adjunto WHERE idAdjunto = :id");
            $stmtDel->execute([':id' => $idAdjunto]);

            echo json_encode(['status' => 'success', 'message' => 'Adjunto eliminado']);
        } catch (\Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    // =========================================================================
    //  API - SALA DE VOTACIÓN (Para los Consejeros)
    // =========================================================================

    public function apiVotoCheck()
    {
        header('Content-Type: application/json');
        $this->verificarSesion();

        $idUsuario = $_SESSION['idUsuario'];
        $model = new Minuta(); // O VotacionModel si lo tienes separado

        try {
            // 1. Buscar si hay alguna votación ABIERTA (habilitada = 1)
            // Esta lógica asume que solo puede haber 1 abierta a la vez.
            $db = (new Database())->getConnection();
            $sql = "SELECT idVotacion, nombreVotacion, habilitada 
                    FROM t_votacion 
                    WHERE habilitada = 1 
                    LIMIT 1";
            $stmt = $db->prepare($sql);
            $stmt->execute();
            $votacionActiva = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$votacionActiva) {
                echo json_encode(['status' => 'waiting', 'message' => 'No hay votación activa']);
                exit;
            }

            // 2. Verificar si este usuario YA votó en esa votación
            $sqlVoto = "SELECT opcionVoto FROM t_voto 
                        WHERE t_votacion_idVotacion = :idVot AND t_usuario_idUsuario = :idUser";
            $stmtV = $db->prepare($sqlVoto);
            $stmtV->execute([
                ':idVot' => $votacionActiva['idVotacion'],
                ':idUser' => $idUsuario
            ]);
            $miVoto = $stmtV->fetch(\PDO::FETCH_ASSOC);

            $dataResponse = [
                'idVotacion' => $votacionActiva['idVotacion'],
                'nombreVotacion' => $votacionActiva['nombreVotacion'],
                'ya_voto' => $miVoto ? true : false,
                'opcion_registrada' => $miVoto ? $miVoto['opcionVoto'] : null
            ];

            echo json_encode(['status' => 'active', 'data' => $dataResponse]);
        } catch (\Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    public function apiVotoEmitir()
    {
        header('Content-Type: application/json');
        $this->verificarSesion();

        $input = json_decode(file_get_contents('php://input'), true);
        $idVotacion = $input['idVotacion'] ?? 0;
        $opcion = $input['opcion'] ?? ''; // Aquí llegará 'APRUEBO', 'RECHAZO' o 'ABSTENCION'
        $idUsuario = $_SESSION['idUsuario'];

        if (!$idVotacion || empty($opcion)) {
            echo json_encode(['status' => 'error', 'message' => 'Datos incompletos']);
            exit;
        }

        try {
            $db = (new Database())->getConnection();

            // 1. Verificar que la votación sigue abierta (seguridad)
            $stmtCheck = $db->prepare("SELECT habilitada FROM t_votacion WHERE idVotacion = ?");
            $stmtCheck->execute([$idVotacion]);
            $estado = $stmtCheck->fetchColumn();

            if ($estado != 1) {
                echo json_encode(['status' => 'error', 'message' => 'La votación ya se cerró.']);
                exit;
            }

            // 2. Verificar si ya votó (evitar duplicados)
            $stmtExist = $db->prepare("SELECT idVoto FROM t_voto WHERE t_votacion_idVotacion = ? AND t_usuario_idUsuario = ?");
            $stmtExist->execute([$idVotacion, $idUsuario]);
            if ($stmtExist->rowCount() > 0) {
                // Si ya votó, actualizamos su voto (opcional, o lanzamos error)
                $sqlUpd = "UPDATE t_voto SET opcionVoto = ?, fechaVoto = NOW() WHERE t_votacion_idVotacion = ? AND t_usuario_idUsuario = ?";
                $stmtUpd = $db->prepare($sqlUpd);
                $stmtUpd->execute([$opcion, $idVotacion, $idUsuario]);
            } else {
                // Insertar voto nuevo
                $sqlIns = "INSERT INTO t_voto (t_votacion_idVotacion, t_usuario_idUsuario, opcionVoto, fechaVoto) VALUES (?, ?, ?, NOW())";
                $stmtIns = $db->prepare($sqlIns);
                $stmtIns->execute([$idVotacion, $idUsuario, $opcion]);
            }

            echo json_encode(['status' => 'success', 'message' => 'Voto registrado']);
        } catch (\Exception $e) {
            echo json_encode(['status' => 'error', 'message' => 'Error al guardar voto: ' . $e->getMessage()]);
        }
        exit;
    }

    
}
