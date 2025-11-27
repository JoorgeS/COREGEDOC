<?php

namespace App\Controllers;

use App\Config\Database;
use PDO;

class HomeController {
    
    private $db;

    public function __construct() {
        // Conexión a la BD
        $database = new Database();
        $this->db = $database->getConnection();
    }

    public function index() {
        // 1. Verificar sesión (Seguridad básica)
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
// Comenta o borra temporalmente esta redirección para probar
        /*
        if (!isset($_SESSION['idUsuario'])) {
            header('Location: index.php?action=login');
            exit();
        }
        */
        // 2. Preparar variables de usuario
        $idUsuarioLogueado = $_SESSION['idUsuario'];
        $tipoUsuario = $_SESSION['tipoUsuario_id'];
        
        // Cargar constantes si no están cargadas
        require_once __DIR__ . '/../config/Constants.php';

        // 3. Inicializar datos para la vista
        $data = [
            'tareas_pendientes' => [],
            'actividad_reciente' => [],
            'proximas_reuniones' => [],
            'minutas_recientes_aprobadas' => [],
            'usuario' => [
                'nombre' => $_SESSION['pNombre'] ?? '',
                'apellido' => $_SESSION['aPaterno'] ?? '',
                'rol' => $tipoUsuario
            ],
            'pagina_actual' => 'home' // Para activar el menú
        ];

        // --- INICIO: LÓGICA DE VISTA REFACTORIZADA (MOVIDA DESDE home.php) ---

        // A. Definir un saludo según la hora (Lógica movida)
        $hora = date('G');
        if ($hora >= 5 && $hora < 12) {
            $saludo = "Buenos días";
        } elseif ($hora >= 12 && $hora < 19) {
            $saludo = "Buenas tardes";
        } else {
            $saludo = "Buenas noches";
        }
        $data['saludo'] = $saludo;
        
        // B. Definir el array de imágenes de zonas a mostrar en el carrusel (Datos movidos)
        $imagenesZonas = [
            // Mensaje 1: Enfoque en la eficiencia de la gestión documental
            ['file' => 'public/img/zonas_region/imagen_zona_1.jpg', 'title' => 'Decisiones ágiles, impacto regional', 'icon' => 'fas fa-gavel'],
            // Mensaje 2: Enfoque en la transparencia y rendición de cuentas
            ['file' => 'public/img/zonas_region/imagen_zona_2.jpg', 'title' => 'Acceso a la información en tiempo real', 'icon' => 'fas fa-eye'],
            // Mensaje 3: Enfoque en la modernización y procesos sin papel
            ['file' => 'public/img/zonas_region/imagen_zona_3.jpg', 'title' => 'Compromiso digital, eficiencia ecológica', 'icon' => 'fas fa-leaf'],
            // Mensaje 4: Enfoque en el desarrollo y la inversión territorial
            ['file' => 'public/img/zonas_region/imagen_zona_4.jpg', 'title' => 'Impulsando proyectos comunitarios', 'icon' => 'fas fa-city'],
            // Mensaje 5: Enfoque en la colaboración entre comisiones
            ['file' => 'public/img/zonas_region/imagen_zona_5.jpg', 'title' => 'Coordinación intersectorial eficaz', 'icon' => 'fas fa-sitemap'],
            // Mensaje 6: Enfoque en el seguimiento de acuerdos y promesas
            ['file' => 'public/img/zonas_region/imagen_zona_6.jpg', 'title' => 'Acuerdos controlados, promesas cumplidas', 'icon' => 'fas fa-check-double'],
            // Mensaje 7: Enfoque en la participación y el consejero
            ['file' => 'public/img/zonas_region/imagen_zona_7.jpg', 'title' => 'Eficiencia y eficacia en las aprobaciones de minutas', 'icon' => 'fas fa-user-tie'],
            // Mensaje 8: Enfoque en la historia y la memoria institucional
            ['file' => 'public/img/zonas_region/imagen_zona_8.jpg', 'title' => 'Disponibilidad de datos históricos', 'icon' => 'fas fa-scroll'],
        ];
        $data['imagenes_zonas'] = $imagenesZonas;

        // --- FIN: LÓGICA DE VISTA REFACTORIZADA ---

        // --- LOGICA DE NEGOCIO (Consulta a BD) ---

        try {
            // A. Tareas para Presidente (Firmas pendientes)
            if ($tipoUsuario == ROL_PRESIDENTE_COMISION) {
                $sql = "SELECT COUNT(DISTINCT m.idMinuta)
                        FROM t_aprobacion_minuta am
                        JOIN t_minuta m ON am.t_minuta_idMinuta = m.idMinuta
                        WHERE am.t_usuario_idPresidente = :idUsuario
                        AND am.estado_firma = 'PENDIENTE'
                        AND m.estadoMinuta NOT IN ('APROBADA', 'BORRADOR')";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([':idUsuario' => $idUsuarioLogueado]);
                $conteo = $stmt->fetchColumn();

                if ($conteo > 0) {
                    $s = $conteo > 1 ? 's' : '';
                    $data['tareas_pendientes'][] = [
                        'texto' => "Tienes <strong>{$conteo} minuta{$s}</strong> esperando tu firma.",
                        'link'  => "index.php?action=minutaPendiente", // Ruta actualizada
                        'icono' => "fa-file-signature",
                        'color' => "danger"
                    ];
                }
            }

            // B. Tareas para Consejero/Presidente (Votos pendientes)
            if ($tipoUsuario == ROL_CONSEJERO || $tipoUsuario == ROL_PRESIDENTE_COMISION) {
                $sql = "SELECT COUNT(v.idVotacion) 
                        FROM t_votacion v
                        WHERE v.habilitada = 1
                        AND NOT EXISTS (
                            SELECT 1 FROM t_voto 
                            WHERE t_votacion_idVotacion = v.idVotacion 
                            AND t_usuario_idUsuario = :idUsuario
                        )";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([':idUsuario' => $idUsuarioLogueado]);
                $conteo = $stmt->fetchColumn();

                if ($conteo > 0) {
                    $s = $conteo > 1 ? 'es' : '';
                    $data['tareas_pendientes'][] = [
                        'texto' => "Tienes <strong>{$conteo} votacion{$s} activa{$s}</strong> pendiente{$s}.",
                        'link'  => "index.php?action=voto_autogestion",
                        'icono' => "fa-vote-yea",
                        'color' => "primary"
                    ];
                }
            }

            // C. Tareas Secretario Técnico
            if ($tipoUsuario == ROL_SECRETARIO_TECNICO) {
                // Feedback
                $stmt = $this->db->query("SELECT COUNT(*) FROM t_minuta WHERE estadoMinuta = 'REQUIERE_REVISION'");
                $conteo = $stmt->fetchColumn();
                if ($conteo > 0) {
                    $s = $conteo > 1 ? 's' : '';
                    $data['tareas_pendientes'][] = [
                        'texto' => "Hay <strong>{$conteo} minuta{$s}</strong> que requiere{$s} tu revisión.",
                        'link'  => "index.php?action=minutas_pendientes",
                        'icono' => "fa-comment-dots",
                        'color' => "danger"
                    ];
                }
                // Borradores
                $stmt = $this->db->query("SELECT COUNT(*) FROM t_minuta WHERE estadoMinuta = 'BORRADOR'");
                $conteo = $stmt->fetchColumn();
                if ($conteo > 0) {
                    $s = $conteo > 1 ? 's' : '';
                    $data['tareas_pendientes'][] = [
                        'texto' => "Tienes <strong>{$conteo} minuta{$s} en borrador</strong>.",
                        'link'  => "index.php?action=minutas_pendientes",
                        'icono' => "fa-pencil-alt",
                        'color' => "info"
                    ];
                }
            }

            // D. Actividad Reciente / Minutas
            if ($tipoUsuario == ROL_CONSEJERO) {
                $sql = "SELECT m.idMinuta, m.fechaAprobacion, m.pathArchivo, r.nombreReunion
                        FROM t_minuta m
                        LEFT JOIN t_reunion r ON m.idMinuta = r.t_minuta_idMinuta
                        WHERE m.estadoMinuta = 'APROBADA'
                        AND m.fechaAprobacion >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                        ORDER BY m.fechaAprobacion DESC LIMIT 5";
                $data['minutas_recientes_aprobadas'] = $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $sql = "SELECT s.fecha_hora, s.accion, s.detalle, 
                        COALESCE(TRIM(CONCAT(u.pNombre, ' ', u.aPaterno)), 'Sistema') as usuario_nombre
                        FROM t_minuta_seguimiento s
                        LEFT JOIN t_usuario u ON s.t_usuario_idUsuario = u.idUsuario
                        ORDER BY s.fecha_hora DESC LIMIT 5";
                $data['actividad_reciente'] = $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            }

            // E. Próximas Reuniones
            $sql = "SELECT r.idReunion, r.nombreReunion, r.fechaInicioReunion, c.nombreComision
                    FROM t_reunion r
                    LEFT JOIN t_comision c ON r.t_comision_idComision = c.idComision
                    WHERE r.fechaInicioReunion >= NOW()
                    ORDER BY r.fechaInicioReunion ASC LIMIT 3";
            $data['proximas_reuniones'] = $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        } catch (\Exception $e) {
            // En un sistema real, loguear el error
            // error_log($e->getMessage());
        }

        // 4. Cargar la Vista dentro del Layout
        // Pasamos $data a las vistas para que puedan usar las variables
        
        // Definimos qué vista interna se cargará
        $childView = __DIR__ . '/../views/home.php'; 
        
        // Cargamos el layout principal, que a su vez incluirá $childView
        require_once __DIR__ . '/../views/layouts/main.php';
    }
}