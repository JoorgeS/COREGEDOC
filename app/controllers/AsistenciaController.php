<?php

namespace App\Controllers;

use App\Models\Minuta;
use App\Models\Comision;
use App\Models\Reunion;
use App\Services\PdfService;

class AsistenciaController
{
    private function jsonResponse($data)
    {
        if (ob_get_length()) ob_clean(); // Limpia cualquier error/warning previo
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    private function verificarSesion()
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['idUsuario'])) {
            return false;
        }
        return true;
    }

    // --- VISTA: Sala de Autogestión ---
    public function sala()
    {
        if (!$this->verificarSesion()) {
            header('Location: index.php?action=login');
            exit();
        }

        $minutaModel = new Minuta();

        // 1. Cargar Comisiones para el Filtro
        $comisionModel = new Comision();
        $comisiones = $comisionModel->listarTodas();

        // 2. Pasamos las comisiones a la vista
        $data = [
            'usuario' => ['nombre' => $_SESSION['pNombre'], 'apellido' => $_SESSION['aPaterno'], 'rol' => $_SESSION['tipoUsuario_id']],
            'pagina_actual' => 'sala_reuniones',
            'historial' => [], // Se cargará por AJAX para optimizar
            'comisiones' => $comisiones, // <--- ESTO FALTABA PARA EL SELECT
            'proximas' => []
        ];

        $childView = __DIR__ . '/../views/asistencia/sala.php';
        require_once __DIR__ . '/../views/layouts/main.php';
    }

    // --- API: Verificar si hay reunión activa ---
    public function apiCheck()
    {
        $this->verificarSesion();

        // LIMPIEZA CRÍTICA DE BUFFER
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json');

        $db = new \App\Config\Database();
        $conn = $db->getConnection();
        $idUsuario = $_SESSION['idUsuario'];

        try {
            $sql = "SELECT r.idReunion, r.nombreReunion, r.fechaInicioReunion, r.t_minuta_idMinuta,
                           (SELECT COUNT(*) FROM t_asistencia a 
                            WHERE a.t_minuta_idMinuta = r.t_minuta_idMinuta 
                            AND a.t_usuario_idUsuario = :idUser 
                            AND a.estadoAsistencia = 'PRESENTE') as ya_marco
                    FROM t_reunion r
                    WHERE r.vigente = 1 
                    AND r.t_minuta_idMinuta IS NOT NULL  
                    AND DATE(r.fechaInicioReunion) = CURDATE()
                    ORDER BY r.fechaInicioReunion DESC
                    LIMIT 1";

            $stmt = $conn->prepare($sql);
            $stmt->execute([':idUser' => $idUsuario]);
            $reunion = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($reunion) {
                $inicio = new \DateTime($reunion['fechaInicioReunion']);
                $ahora = new \DateTime();
                $diff = $inicio->diff($ahora);
                $minutosTranscurridos = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i;

                $limite = clone $inicio;
                $limite->modify('+30 minutes');
                $horaLimiteStr = $limite->format('H:i');

                echo json_encode([
                    'status' => 'active',
                    'data' => [
                        'idReunion' => $reunion['idReunion'],
                        'nombreReunion' => $reunion['nombreReunion'],
                        't_minuta_idMinuta' => $reunion['t_minuta_idMinuta'],
                        'fechaInicio' => $reunion['fechaInicioReunion'],
                        'minutosTranscurridos' => $minutosTranscurridos,
                        'ya_marco' => ($reunion['ya_marco'] > 0),
                        'horaLimite' => $horaLimiteStr
                    ]
                ]);
            } else {
                echo json_encode(['status' => 'none']);
            }
        } catch (\Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    // --- API: Marcar Presente ---
    public function apiMarcar()
    {
        if (!$this->verificarSesion()) $this->jsonResponse(['status' => 'error']);

        $input = json_decode(file_get_contents('php://input'), true);
        $idMinuta = $input['idMinuta'] ?? 0;
        $idReunion = $input['idReunion'] ?? 0;

        if (!$idMinuta) $this->jsonResponse(['status' => 'error', 'message' => 'Datos error']);

        $model = new Minuta();
        if ($model->registrarAutoAsistencia($idMinuta, $_SESSION['idUsuario'], $idReunion)) {
            $this->jsonResponse(['status' => 'success', 'message' => 'Asistencia registrada correctamente.']);
        } else {
            $this->jsonResponse(['status' => 'error', 'message' => 'Error al registrar.']);
        }
    }

    // --- API: Historial Filtrado (CORREGIDO) ---
    public function apiHistorial()
    {
        // 1. Limpieza de buffer para evitar errores de JSON
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json');

        // 2. Verificar sesión
        if (!$this->verificarSesion()) {
            echo json_encode(['status' => 'error', 'message' => 'Sesión no iniciada']);
            exit;
        }

        $idUsuario = $_SESSION['idUsuario'];
        $modelo = new Minuta();

        $filtros = [
            'desde' => $_GET['desde'] ?? null,
            'hasta' => $_GET['hasta'] ?? null,
            'comision' => $_GET['comision'] ?? null,
            'q' => $_GET['q'] ?? null
        ];

        $page = (int)($_GET['page'] ?? 1);
        $limit = (int)($_GET['limit'] ?? 10);
        $offset = ($page - 1) * $limit;

        try {
            $resultado = $modelo->getHistorialAsistenciaPersonalFiltrado($idUsuario, $filtros, $limit, $offset);
            echo json_encode($resultado);
        } catch (\Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    public function reporte()
    {
        $this->verificarSesion();
        if ($_SESSION['tipoUsuario_id'] != 6) { header('Location: index.php?action=home'); exit; }

        $comisionModel = new Comision();
        $data = [
            'usuario' => ['nombre' => $_SESSION['pNombre'], 'apellido' => $_SESSION['aPaterno'], 'rol' => $_SESSION['tipoUsuario_id']],
            'pagina_actual' => 'reporte_asistencia',
            'comisiones' => $comisionModel->listarTodas()
        ];

        $childView = __DIR__ . '/../views/asistencia/reporte.php';
        require_once __DIR__ . '/../views/layouts/main.php';
    }

  // ============================================================
    // 📊 LÓGICA COMPARTIDA (CON PAGINACIÓN)
    // ============================================================
    private function obtenerDatosReporteConsolidado($filtros, $paginacion = null)
    {
        $minutaModel = new \App\Models\Minuta();
        
        // 1. Obtener lista de reuniones
        if ($paginacion) {
            // Si es para la vista (API), usamos paginación
            $limit = $paginacion['limit'];
            $offset = $paginacion['offset'];
            $listaReuniones = $minutaModel->getListaMinutasPorFiltro($filtros['desde'], $filtros['hasta'], $filtros['idComision'], $limit, $offset);
            $totalRegistros = $minutaModel->contarMinutasPorFiltro($filtros['desde'], $filtros['hasta'], $filtros['idComision']);
        } else {
            // Si es para el PDF, traemos todo (sin límite)
            $listaReuniones = $minutaModel->getListaMinutasPorFiltro($filtros['desde'], $filtros['hasta'], $filtros['idComision']);
            $totalRegistros = count($listaReuniones);
        }
        
        $datosProcesados = [];
        $dias = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
        $meses = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

        // 2. Bucle de Procesamiento
        foreach ($listaReuniones as $reu) {
            $idMinuta = $reu['t_minuta_idMinuta'];
            
            // Reutilizamos tu lógica detallada
            $detalle = $minutaModel->getAsistenciaDetallada($idMinuta); 
            $asistentesRaw = $detalle['asistentes'] ?? [];

            $listaPresentes = [];
            foreach ($asistentesRaw as $p) {
                if ($p['estaPresente'] == 1) {
                    
                    $hora = '--:--';
                    if (!empty($p['fechaRegistroAsistencia'])) {
                        $hora = date('H:i', strtotime($p['fechaRegistroAsistencia']));
                    }

                    $origen = 'Secretario Técnico';
                    if (isset($p['origenAsistencia']) && in_array($p['origenAsistencia'], ['AUTOGESTION', 'AUTOREGISTRO', 'APP'])) {
                        $origen = 'Autogestión';
                    } elseif (isset($p['origenAsistencia']) && $p['origenAsistencia'] === 'SISTEMA') {
                        $origen = 'Automático';
                    }

                    $esAtrasado = (isset($p['estado_visual']) && $p['estado_visual'] === 'atrasado');
                    $estadoFinal = $esAtrasado ? 'ATRASADO' : 'PRESENTE';

                    $listaPresentes[] = [
                        'nombre' => $p['pNombre'] . ' ' . $p['aPaterno'],
                        'origen' => $origen,
                        'hora'   => $hora,
                        'atrasado' => $esAtrasado,
                        'estado' => $estadoFinal // Clave extra para lógica de vista
                    ];
                }
            }

            $ts = strtotime($reu['fechaInicioReunion']);
            $fechaTexto = $dias[date('w', $ts)] . ", " . date('d', $ts) . " de " . $meses[date('n', $ts)] . " de " . date('Y', $ts);
            
            $comisiones = array_filter([$reu['com1'], $reu['com2'], $reu['com3']]);
            $comisionStr = implode(' + ', $comisiones);

            $datosProcesados[] = [
                'titulo' => mb_strtoupper($reu['nombreReunion']),
                'comision' => mb_strtoupper($comisionStr),
                'fecha_texto' => mb_strtoupper($fechaTexto),
                'hora_inicio' => date('H:i', $ts),
                'asistentes' => $listaPresentes
            ];
        }

        return ['data' => $datosProcesados, 'total' => $totalRegistros];
    }

    // --- API JSON (Ahora soporta Paginación) ---
    public function apiGetReporte()
    {
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json');
        $this->verificarSesion();

        if ($_SESSION['tipoUsuario_id'] != 6) {
            echo json_encode(['status' => 'error', 'message' => 'Acceso denegado']); exit;
        }

        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = 10; // 10 registros por página
        $offset = ($page - 1) * $limit;

        $filtros = [
            'desde' => $_GET['desde'] ?? date('Y-m-01'),
            'hasta' => $_GET['hasta'] ?? date('Y-m-t'),
            'idComision' => $_GET['comision'] ?? null
        ];

        try {
            // Pasamos los parámetros de paginación
            $resultado = $this->obtenerDatosReporteConsolidado($filtros, ['limit' => $limit, 'offset' => $offset]);
            
            $data = $resultado['data'];
            $total = $resultado['total'];
            $totalPages = ceil($total / $limit);

            echo json_encode([
                'status' => 'success', 
                'data' => $data, 
                'total' => $total,
                'pages' => $totalPages,
                'current_page' => $page
            ]);
        } catch (\Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }
    
    // --- IMPORTANTE: Actualiza generarPdfReporte para que NO use paginación ---
    public function generarPdfReporte()
    {
        if (ob_get_length()) ob_clean();
        $this->verificarSesion();
        if ($_SESSION['tipoUsuario_id'] != 6) die('Acceso denegado');

        $filtros = [
            'desde' => $_GET['desde'] ?? date('Y-m-01'),
            'hasta' => $_GET['hasta'] ?? date('Y-m-t'),
            'idComision' => $_GET['comision'] ?? null
        ];

        // Llamamos SIN paginación (segundo argumento null)
        $resultado = $this->obtenerDatosReporteConsolidado($filtros, null);
        $data = $resultado['data'];

        $dataPdf = [
            'titulo' => 'REPORTE CONSOLIDADO DE ASISTENCIA',
            'rango' => 'Periodo: ' . date('d/m/Y', strtotime($filtros['desde'])) . ' al ' . date('d/m/Y', strtotime($filtros['hasta'])),
            'registros' => $data,
            'generado_por' => $_SESSION['pNombre'] . ' ' . $_SESSION['aPaterno'],
            'urlValidacion' => (defined('BASE_URL') ? BASE_URL : 'https://coregedoc.cl')
        ];

        $pdfService = new \App\Services\PdfService();
        $nombreArchivo = 'Reporte_Mensual_' . date('YmdHis') . '.pdf';
        $rutaTemp = sys_get_temp_dir() . '/' . $nombreArchivo;

        if ($pdfService->generarPdfReporteAsistencia($dataPdf, $rutaTemp)) {
            header("Content-type: application/pdf");
            header("Content-Disposition: inline; filename={$nombreArchivo}");
            readfile($rutaTemp);
            unlink($rutaTemp);
        } else {
            echo "Error al generar PDF.";
        }
        exit;
    }

    

}
