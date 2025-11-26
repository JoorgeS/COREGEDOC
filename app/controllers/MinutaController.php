<?php

namespace App\Controllers;

// No necesitamos el modelo todavía para el dashboard simple, 
// pero lo dejamos listo para cuando listemos las minutas.
use App\Models\Minuta;
use App\Models\Comision;
use App\Services\MailService;

class MinutaController
{


    public function index()
    {
        echo "Aquí irá el listado de minutas (Próximo paso)";
    }

    public function dashboard()
    {
        // ... (código del dashboard que ya tienes) ...
        // Asegúrate de copiar todo lo que tenías o dejarlo tal cual.
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['idUsuario'])) {
            header('Location: index.php?action=login');
            exit();
        }

        $data = [
            'usuario' => ['nombre' => $_SESSION['pNombre'] ?? '', 'apellido' => $_SESSION['aPaterno'] ?? '', 'rol' => $_SESSION['tipoUsuario_id'] ?? 0],
            'pagina_actual' => 'minutas_dashboard'
        ];
        $childView = __DIR__ . '/../views/minutas/dashboard.php';
        require_once __DIR__ . '/../views/layouts/main.php';
    }

    // --- NUEVO MÉTODO: Listar Pendientes ---
    public function pendientes()
    {
        $this->verificarSesion(); // Helper simple para no repetir código

        $minutaModel = new Minuta();
        // Traemos las que NO están aprobadas (Pendientes, Borradores, etc.)
        $listaMinutas = $minutaModel->getMinutasByEstado('PENDIENTE');

        $data = [
            'usuario' => ['nombre' => $_SESSION['pNombre'], 'apellido' => $_SESSION['aPaterno'], 'rol' => $_SESSION['tipoUsuario_id']],
            'pagina_actual' => 'minutas_pendientes',
            'minutas' => $listaMinutas
        ];

        $childView = __DIR__ . '/../views/minutas/pendientes.php';
        require_once __DIR__ . '/../views/layouts/main.php';
    }

    // --- NUEVO MÉTODO: Listar Aprobadas ---
    public function aprobadas()
    {
        $this->verificarSesion();

        $minutaModel = new Minuta();
        $listaMinutas = $minutaModel->getMinutasByEstado('APROBADA');

        $data = [
            'usuario' => ['nombre' => $_SESSION['pNombre'], 'apellido' => $_SESSION['aPaterno'], 'rol' => $_SESSION['tipoUsuario_id']],
            'pagina_actual' => 'minutas_aprobadas',
            'minutas' => $listaMinutas
        ];

        $childView = __DIR__ . '/../views/minutas/aprobadas.php';
        require_once __DIR__ . '/../views/layouts/main.php';
    }

    // Helper privado para verificar sesión
    private function verificarSesion()
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['idUsuario'])) {
            header('Location: index.php?action=login');
            exit();
        }
    }

    public function gestionar()
    {
        $this->verificarSesion();

        $idMinuta = $_GET['id'] ?? 0;
        if (!$idMinuta) {
            header('Location: index.php?action=minutas_dashboard');
            exit();
        }

        $minutaModel = new Minuta();
        $minuta = $minutaModel->getMinutaById($idMinuta);

        if (!$minuta) {
            echo "Minuta no encontrada.";
            return;
        }

        // --- LÓGICA DE PERMISOS (Porteada de tu código anterior) ---
        $idUsuarioLogueado = $_SESSION['idUsuario'];
        $tipoUsuario = $_SESSION['tipoUsuario_id'];
        $esSecretarioTecnico = ($tipoUsuario == 2); // O usa la constante ROL_SECRETARIO_TECNICO

        $estadoFirma = $minutaModel->getEstadoFirma($idMinuta, $idUsuarioLogueado);

        $esPresidenteFirmante = ($estadoFirma !== false);
        $haFirmado = ($estadoFirma === 'FIRMADO'); // Ajusta según tus valores reales en BD
        $haEnviadoFeedback = ($estadoFirma === 'REQUIERE_REVISION');

        $estadoMinuta = $minuta['estadoMinuta'];

        // Determinar si es solo lectura
        $esSoloLectura = true;
        if ($esSecretarioTecnico && $estadoMinuta !== 'APROBADA') {
            $esSoloLectura = false;
        } elseif ($esPresidenteFirmante && !$haFirmado && !$haEnviadoFeedback && $estadoMinuta !== 'APROBADA') {
            // Si es presidente y le toca firmar, no es solo lectura (puede poner feedback)
            // Aunque técnicamente los campos de texto sí lo son, pero el botón de acción no.
            // Para simplificar la vista, pasaremos variables específicas.
        }


        $data = [
            'usuario' => ['rol' => $tipoUsuario],
            'minuta' => $minuta,
            'temas' => $minutaModel->getTemas($idMinuta),
            'asistencia' => $minutaModel->getAsistenciaData($idMinuta),
            'permisos' => [
                'esSecretario' => $esSecretarioTecnico,
                'esPresidente' => $esPresidenteFirmante,
                'esSoloLectura' => $esSoloLectura,
                'haFirmado' => $haFirmado,
                'haEnviadoFeedback' => $haEnviadoFeedback,
                'estadoFirma' => $estadoFirma
            ],
            'pagina_actual' => 'minuta_gestionar'
        ];

        $childView = __DIR__ . '/../views/minutas/editar.php';
        require_once __DIR__ . '/../views/layouts/main.php';
    }

    public function apiGuardarAsistencia()
    {
        header('Content-Type: application/json');
        $this->verificarSesion();

        // Leer el body JSON que envía el Javascript
        $input = json_decode(file_get_contents('php://input'), true);

        $idMinuta = $input['idMinuta'] ?? 0;
        $asistencia = $input['asistencia'] ?? []; // Array de IDs

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
        exit; // Importante detener aquí para no renderizar nada más
    }

    // --- API: Guardar Borrador Completo (Temas) ---
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

        // Guardamos temas
        $temasGuardados = $model->guardarTemas($idMinuta, $temas);

        // Opcional: También podrías guardar la asistencia aquí si viene en el paquete
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


    public function apiEnviarAprobacion()
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
            $model = new Minuta();
            // Opcional: Guardar borrador una última vez antes de enviar (puedes llamar a guardarTemas aquí si quieres)

            // Ejecutar envío
            $model->enviarParaFirma($idMinuta, $_SESSION['idUsuario']);

            // Aquí iría la lógica de envío de CORREO (PHPMailer)
            // Por ahora lo dejamos pendiente para no complicar este paso.

            echo json_encode(['status' => 'success', 'message' => 'Minuta enviada a firma correctamente.']);
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

        try {
            $model = new Minuta();
            $resultado = $model->firmarMinuta($idMinuta, $_SESSION['idUsuario']);

            // --- NUEVO: SI SE APROBÓ FINALMENTE, GENERAR PDF ---
            if ($resultado['estado_nuevo'] === 'APROBADA') {
                
                // 1. Definir rutas
                $nombreArchivo = 'Minuta_Final_N' . $idMinuta . '_' . date('Ymd') . '.pdf';
                $rutaFisica = __DIR__ . '/../../public/docs/minutas_aprobadas/' . $nombreArchivo;
                $rutaWeb = 'public/docs/minutas_aprobadas/' . $nombreArchivo;

                // 2. Cargar dependencias de PDF (puedes mover esto a un helper)
                require_once __DIR__ . '/generar_pdf_borrador.php'; // Usamos el mismo generador por ahora
                
                // 3. Generar el archivo (Aquí usamos un truco: llamamos a la lógica de generación)
                // Para hacerlo bien y rápido, te recomiendo encapsular la lógica de 'generar_pdf_borrador.php'
                // en una función que acepte un parámetro '$guardarEnRuta'.
                
                // Por ahora, simplemente actualizamos la BD con la ruta futura
                // y dejamos que el link "Ver PDF Final" apunte a un script que lo genere al vuelo si no existe,
                // o mejor aún:
                
                // Lógica simplificada para este paso:
                // Guardamos la ruta en la BD
                $model->actualizarPathArchivo($idMinuta, $rutaWeb);
            }

            echo json_encode([
                'status' => 'success', 
                'message' => 'Minuta firmada y finalizada.', 
                'nuevo_estado' => $resultado['estado_nuevo']
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
            'usuario' => ['nombre' => $_SESSION['pNombre'], 'apellido' => $_SESSION['aPaterno'], 'rol' => $_SESSION['tipoUsuario_id']],
            'pagina_actual' => 'minuta_ver_historial', // Para iluminar el menú si quieres
            'minuta' => $minuta,
            'seguimiento' => $historial
        ];

        // Cargamos la vista dentro del layout
        $childView = __DIR__ . '/../views/minutas/historial.php';
        require_once __DIR__ . '/../views/layouts/main.php';
    }

    public function seguimientoGeneral()
    {
        $this->verificarSesion();

        // Seguridad: Solo Admin
        if ($_SESSION['tipoUsuario_id'] != ROL_ADMINISTRADOR) {
            header('Location: index.php?action=minutas_dashboard');
            exit();
        }

        $minutaModel = new Minuta();
        $comisionModel = new Comision();

        // Capturar Filtros
        $filters = [
            'comisionId' => $_GET['comisionId'] ?? null,
            'startDate'  => $_GET['startDate'] ?? null,
            'endDate'    => $_GET['endDate'] ?? null,
            'idMinuta'   => $_GET['idMinuta'] ?? null,
            'keyword'    => $_GET['keyword'] ?? null
        ];

        $data = [
            'usuario' => ['nombre' => $_SESSION['pNombre'], 'apellido' => $_SESSION['aPaterno'], 'rol' => $_SESSION['tipoUsuario_id']],
            'pagina_actual' => 'seguimiento_general',
            'minutas' => $minutaModel->getSeguimientoGeneral($filters),
            'comisiones' => $comisionModel->listarTodas(),
            'filtros_activos' => $filters // Para repoblar el formulario
        ];

        $childView = __DIR__ . '/../views/minutas/seguimiento_general.php';
        require_once __DIR__ . '/../views/layouts/main.php';
    }

    public function apiValidarAsistencia() {
        header('Content-Type: application/json');
        $this->verificarSesion();
        
        $input = json_decode(file_get_contents('php://input'), true);
        $idMinuta = $input['idMinuta'] ?? 0;

        if (!$idMinuta) {
            echo json_encode(['status'=>'error', 'message'=>'Falta ID Minuta']); 
            exit;
        }

        try {
            $minutaModel = new Minuta();
            $minuta = $minutaModel->getMinutaById($idMinuta); // Asumiendo que trae nombreReunion y Comision

            // 1. Generar PDF
            $nombreArchivo = 'Asistencia_Minuta_' . $idMinuta . '.pdf';
            $rutaFisica = __DIR__ . '/../../public/docs/minutas_finales/' . $nombreArchivo;
            
            // Asegurar carpeta
            if (!is_dir(dirname($rutaFisica))) mkdir(dirname($rutaFisica), 0777, true);

           
            
            $idSecretario = $_SESSION['idUsuario'];
            $rootPath = __DIR__ . '/../../';
            require_once __DIR__ . '/generar_pdf_asistencia.php';

           $pdfGenerado = generarPdfAsistencia(
                $idMinuta, 
                $rutaFisica, 
                (new \App\Config\Database())->getConnection(),
                $idSecretario, // 4to argumento
                $rootPath      // 5to argumento
            );
            
            if (!$pdfGenerado) throw new \Exception("Error al generar el PDF de asistencia.");

            // 2. Enviar Correo
            $mailService = new MailService();
            
            // Datos extra para el cuerpo del correo (ajusta según lo que traiga tu getMinutaById)
            $datosCorreo = [
                'idMinuta' => $idMinuta,
                'nombreReunion' => $minuta['nombreReunion'] ?? 'Reunión Ordinaria', // Asegúrate que tu modelo traiga esto o haz un JOIN
                'nombreComision' => $minuta['nombreComision'] ?? 'Comisión',
                'fecha' => date('d/m/Y')
            ];

            $enviado = $mailService->enviarAsistencia('genesis.contreras.vargas@gmail.com', $rutaFisica, $datosCorreo);

            if (!$enviado) throw new \Exception("El PDF se generó, pero falló el envío del correo.");

            // 3. Actualizar BD (Marcar como validada)
            // Necesitas agregar una columna 'asistencia_validada' TINYINT en t_minuta si no existe,
            // O usar un campo existente. Asumiremos que agregaste el campo o usas uno lógico.
            $minutaModel->marcarAsistenciaValidada($idMinuta);

            echo json_encode(['status'=>'success', 'message'=>'Asistencia validada y enviada a Gestión.']);

        } catch (\Exception $e) {
            echo json_encode(['status'=>'error', 'message'=>$e->getMessage()]);
        }
        exit;
    }


}
