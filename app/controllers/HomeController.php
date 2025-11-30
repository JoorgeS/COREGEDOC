<?php

namespace App\Controllers;

use App\Config\Database;
use PDO;

class HomeController
{

    private $db;

    public function __construct()
    {
        // Conexión a la BD
        $database = new Database();
        $this->db = $database->getConnection();
    }

    public function index()
    {
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

        $imagenesZonas = [
            // 1. FIRMA DIGITAL
            [
                'file' => 'public/img/zonas_region/imagen_zona_1.jpg',
                'title' => 'Firma Digital Avanzada',
                'subtitle' => 'Procesos de validación documental más rápidos y seguros.',
                'icon' => 'fas fa-file-signature'
            ],

            // 2. ACCESO A LA INFORMACIÓN
            [
                'file' => 'public/img/zonas_region/imagen_zona_2.jpg',
                'title' => 'Información en Tiempo Real',
                'subtitle' => 'Transparencia y rendición de cuentas inmediata del CORE.',
                'icon' => 'fas fa-chart-line'
            ],

            // 3. PROCESOS SIN PAPEL (Versión final acordada: Participación Activa)
            [
                'file' => 'public/img/zonas_region/imagen_zona_3.jpg',
                'title' => 'Participación Activa',
                'subtitle' => 'Facilitando y optimizando la labor de los Consejeros.',
                'icon' => 'fas fa-hands-helping'
            ],

            // 4. PROYECTOS COMUNITARIOS (MEJORADO)
            [
                'file' => 'public/img/zonas_region/imagen_zona_4.jpg',
                'title' => 'Registro de Proyectos',
                'subtitle' => 'Trazabilidad y seguimiento de proyectos de desarrollo regional.',
                'icon' => 'fas fa-city'
            ],

            // 5. COORDINACIÓN
            [
                'file' => 'public/img/zonas_region/imagen_zona_5.jpg',
                'title' => 'Coordinación Intersectorial',
                'subtitle' => 'Unificando procesos y optimizando la colaboración entre áreas.',
                'icon' => 'fas fa-link'
            ],

            // 6. SEGUIMIENTO DE ACUERDOS
            [
                'file' => 'public/img/zonas_region/imagen_zona_6.jpg',
                'title' => 'Seguimiento de Acuerdos',
                'subtitle' => 'Monitoreo automatizado del avance de compromisos y tareas.',
                'icon' => 'fas fa-chart-bar'
            ],

            // 7. APROBACIÓN DE MINUTAS
            [
                'file' => 'public/img/zonas_region/imagen_zona_7.jpg',
                'title' => 'Aprobación Rápida de Minutas',
                'subtitle' => 'Ciclos de revisión y sanción documental eficientes y cortos.',
                'icon' => 'fas fa-gavel'
            ],

            // 8. DATOS HISTÓRICOS (Versión final acordada: Base de Datos)
            [
                'file' => 'public/img/zonas_region/imagen_zona_8.jpg',
                'title' => 'Base de Datos Documental',
                'subtitle' => 'Acceso y búsqueda rápida a todos los registros históricos.',
                'icon' => 'fas fa-database'
            ],



        ];
        $data['imagenes_zonas'] = $imagenesZonas;

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
