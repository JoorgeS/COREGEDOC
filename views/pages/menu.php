<?php
// views/pages/menu.php

// SEGURIDAD: Iniciar la sesión de forma segura
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// SEGURIDAD: Redirigir si el usuario no está logueado
if (!isset($_SESSION['idUsuario'])) {
    header("Location: /corevota/views/pages/login.php");
    exit;
}

// --- ¡NUEVO! CAPTURAR ROL DE USUARIO ---
// Asegúrate de que loginController.php esté guardando 'tipoUsuario_id' en la sesión
$tipoUsuario = $_SESSION['tipoUsuario_id'] ?? 0; // 0 = default/invitado

// --- ¡NUEVO! DEFINIR CONSTANTES DE ROL (según tu BBDD t_tipousuario) ---
define('ROL_CONSEJERO', 1);
define('ROL_SECRETARIO_TECNICO', 2);
define('ROL_PRESIDENTE_COMISION', 3);
define('ROL_ADMINISTRADOR', 6);
// (Puedes añadir los otros roles si los necesitas)

// --- Lógica para Saludo y Fecha ---
$nombreUsuario = 'Invitado'; // .

// Capturar la página a cargar
$paginaActual = $_GET['pagina'] ?? 'home'; // Usar 'home' como default
$id_param = $_GET['id'] ?? null; // Capturamos el ID también

// --- Lógica para Saludo y Fecha ---
$nombreUsuario = 'Invitado'; // Valor por defecto si no se encuentra el nombre
if (isset($_SESSION['pNombre'])) { // Usamos pNombre como en tu código original
    $nombreUsuario = htmlspecialchars($_SESSION['pNombre']);
}

// Determina el saludo según la hora del servidor
$horaActual = date('H'); // Obtiene la hora en formato 24h (00-23)
$saludo = '';
if ($horaActual < 12) {
    $saludo = 'Buenos días';
} elseif ($horaActual < 19) { // Puedes ajustar este límite para "Buenas tardes"
    $saludo = 'Buenas tardes';
} else {
    $saludo = 'Buenas noches';
}

// Obtiene la fecha actual en español
setlocale(LC_TIME, 'es_ES.UTF-8', 'Spanish_Spain.1252');
$fechaActual = strftime('%A, %d de %B de %Y');
// --- Fin Lógica Saludo y Fecha ---


/*
============================================================
/   MODIFICADO: LÓGICA PARA EL SIDEBAR ACTIVO
============================================================
*/
// Define los grupos de páginas para el estado 'activo'
$gruposPaginas = [
    // La página principal 'home'
    'home' => ['home'],

    // Todas las páginas que activarán el enlace 'Minutas'
    'minutas' => [
        'minutas_dashboard',
        'minutas_pendientes',
        'minutas_aprobadas',
        'crear_minuta',
        'editar_minuta'
    ],

    // Todas las páginas que activarán el enlace 'Usuarios'
    'usuarios' => [
        'usuarios_dashboard',
        'usuarios_listado',
        'usuario_crear'
    ],

    // Todas las páginas que activarán el enlace 'Comisiones'
    'comisiones' => [
        'comisiones_dashboard',
        'comision_listado',
        'comision_crear',
        'comision_editar'
    ],

    // Todas las páginas que activarán el enlace 'Reuniones'
    'reuniones' => [
        'reuniones_dashboard',
        'reunion_listado',
        'reunion_calendario',
        'reunion_autogestion_asistencia',
        'historial_asistencia',
        'reunion_crear',
        'reunion_editar'
    ],

    // Todas las páginas que activarán el enlace 'Votaciones'
    'votaciones' => [
        'votaciones_dashboard',
        'votacion_listado',
        'historial_votacion',
        'voto_autogestion',
        'crearVotacion',
        'votacion_crear',
        'tabla_votacion'
    ]
];

/**
 * Función helper para verificar si un enlace del menú debe estar activo.
 * Compara la página actual con los grupos definidos.
 */
function esActivo($grupo, $paginaActual, $gruposPaginas)
{
    if (isset($gruposPaginas[$grupo])) {
        return in_array($paginaActual, $gruposPaginas[$grupo]);
    }
    return false;
}
// --- FIN LÓGICA SIDEBAR ---

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>CORE Vota - Menú Principal</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="/corevota/public/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="/corevota/public/css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
        :root {
            --sidebar-width: 300px;
            --header-height: 65px;
            /* MODIFICADO: Define el color azul del CORE */
            --core-blue: #1C88BF;
            /* Este es un azul oscuro profesional, ajústalo al HEX exacto si lo tienes */
        }

        html,
        body {
            height: 100%;
            margin: 0;
            overflow: hidden;
            font-family: Arial, sans-serif;
            background-color: #f8f9fa;
        }

        .app-container {
            height: 100vh;
        }

        nav.sidebar {
            width: var(--sidebar-width);
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 1030;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.05);
            overflow-y: auto;
            padding: 0 !important;
            background-color: #ffffff;
            border-right: 1px solid #dee2e6;
        }

        /* AJUSTES PARA CENTRAR Y AUMENTAR EL LOGO */
        .sidebar-header-box {
            /* Aumentamos el padding superior e inferior para espacio */
            padding: 20px 10px;
            border-bottom: 1px solid #dee2e6;
            text-align: center;
            /* Asegura el centrado horizontal del contenido */
        }

        .sidebar-logo {
            /* Nuevo tamaño: Más ancho y centrado */
            max-width: 300px;
            /* Permitimos que ocupe hasta 180px de los 230px de la barra */
            height: auto;
            margin: 0 auto;
            /* Centra el bloque de la imagen (aunque ya está en el div centrado) */
            display: block;
            /* Necesario para que `margin: auto` funcione correctamente */
            /* Eliminado margin-bottom que hacía que se viera desplazado */
        }


        /* ============================================================
        /   MODIFICADO: NUEVOS ESTILOS PARA EL SIDEBAR SIMPLIFICADO
        ============================================================
        */
        .sidebar .nav-link {
            color: #333;
            /* Color de texto normal */
            font-weight: 500;
            /* Un poco más grueso que el normal */
            padding: 12px 15px;
            border-radius: 6px;
            margin-bottom: 4px;
            /* Espacio entre ítems */
            display: flex;
            align-items: center;
            text-decoration: none;
        }

        .sidebar .nav-link i {
            width: 25px;
            /* Ancho fijo para alinear iconos */
            text-align: center;
            margin-right: 10px;
            font-size: 0.9rem;
        }

        .sidebar .nav-link:hover {
            background-color: #e7f1ff;
            /* Hover sutil (azul claro) */
            color: var(--core-blue);
        }

        .sidebar .nav-link.active {
            background-color: var(--core-blue);
            /* Fondo azul oscuro cuando está activo */
            color: #ffffff;
            /* Texto blanco cuando está activo */
        }

        /* --- FIN NUEVOS ESTILOS SIDEBAR --- */


        .sidebar-footer {
            padding: 0;
            /* SIN padding horizontal ni vertical */
            border-top: 1px solid #dee2e6;
            background-color: #ffffff;
        }


        /* ============================================================
        /   MODIFICADO: ESTILOS PARA EL HEADER (NAVBAR) AZUL
        ============================================================
        */
        .core-header {
            height: var(--header-height);
            width: calc(100% - var(--sidebar-width));
            margin-left: var(--sidebar-width);
            position: fixed;
            top: 0;
            right: 0;
            z-index: 1020;
            background-color: var(--core-blue);
            /* Color azul de fondo */
            color: #ffffff;
            /* Color de texto principal blanco */
            border-bottom: 1px solid #dee2e6;
            box-shadow: 0 1px 3px rgba(0, 0, 0, .05);
        }

        /* Ajustar colores del texto dentro del header */
        .core-header .titulo-sistema {
            color: #ffffff !important;
            /* Sobreescribir .text-muted */
            font-weight: 500;
        }

        .core-header .perfil {
            color: #e0e0e0;
            /* Un gris claro para el texto "Perfil:" */
        }

        .core-header .perfil strong {
            color: #ffffff;
            /* Blanco para el nombre del perfil */
        }

        .core-header .usuario {
            color: #ffffff;
            /* Blanco para el nombre de usuario */
        }

        .core-header .dropdown-toggle::after {
            color: #ffffff;
            /* Flecha del dropdown blanca */
        }

        /* --- FIN ESTILOS HEADER --- */


        /* ============================================================
        /   NUEVO: ESTILOS PARA DASHBOARD CARDS (TARJETAS)
        ============================================================
        */
        .dashboard-card {
            display: block;
            padding: 2rem 1.5rem;
            text-decoration: none;
            color: #333;
            background-color: #ffffff;
            border-radius: 0.5rem;
            border: 1px solid #dee2e6;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, .075);
            transition: all 0.2s ease-in-out;
            text-align: center;
            height: 100%;
        }

        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, .15);
            color: var(--core-blue);
        }

        .dashboard-card i {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: var(--core-blue);
        }

        .dashboard-card h5 {
            font-weight: 600;
        }

        /* --- FIN ESTILOS DASHBOARD CARDS --- */


        main {
            margin-top: var(--header-height);
            margin-left: var(--sidebar-width);
            width: calc(100% - var(--sidebar-width));
            height: calc(100vh - var(--header-height));
            overflow-y: auto;
            padding: 1.5rem;
            background-color: #f8f9fa;
        }

        /* --- ESTILOS PARA BOTÓN CERRAR SESIÓN --- */
        .sidebar-footer {
            padding: 1rem 0.5rem;
            /* Padding similar al px-2 de la lista */
            border-top: 1px solid #dee2e6;
            background-color: #ffffff;
            /* Asegura fondo blanco por si acaso */
        }

        .nav-link-logout {
            background-color: #dc3545 !important;
            /* Rojo 'danger' */
            color: #ffffff !important;
            /* Texto blanco */
            font-weight: bold;
        }

        .nav-link-logout:hover {
            background-color: #bb2d3b !important;
            /* Rojo más oscuro en hover */
        }

        /* --- FIN ESTILOS BOTÓN --- */
    </style>
</head>

<body>
    <div class="app-container">

        <nav class="sidebar d-flex flex-column flex-shrink-0">
            <div class="sidebar-header-box">
                <img src="/corevota/public/img/logoCore1.png" alt="Logo CORE" class="sidebar-logo">
            </div>
            <div class="flex-grow-1 overflow-auto">

                <ul class="nav nav-pills flex-column mb-auto px-2">

                    <li class="nav-item">
                        <a href="menu.php?pagina=home" class="nav-link <?php echo esActivo('home', $paginaActual, $gruposPaginas) ? 'active' : ''; ?>">
                            <i class="fas fa-home fa-fw"></i>
                            Inicio
                        </a>
                    </li>

                    <?php if ($tipoUsuario == ROL_ADMINISTRADOR || $tipoUsuario == ROL_SECRETARIO_TECNICO || $tipoUsuario == ROL_PRESIDENTE_COMISION) : ?>
                        <li class="nav-item">
                            <a href="menu.php?pagina=minutas_dashboard" class="nav-link <?php echo esActivo('minutas', $paginaActual, $gruposPaginas) ? 'active' : ''; ?>">
                                <i class="fas fa-file-alt fa-fw"></i>
                                Minutas
                            </a>
                        </li>
                    <?php elseif ($tipoUsuario == ROL_CONSEJERO) : ?>
                        <li class="nav-item">
                            <a href="menu.php?pagina=minutas_aprobadas" class="nav-link <?php echo esActivo('minutas', $paginaActual, $gruposPaginas) ? 'active' : ''; ?>">
                                <i class="fas fa-file-check fa-fw"></i>
                                Minutas Aprobadas
                            </a>
                        </li>
                    <?php endif; ?>


                    <?php if ($tipoUsuario == ROL_ADMINISTRADOR) : ?>
                        <li class="nav-item">
                            <a href="menu.php?pagina=usuarios_dashboard" class="nav-link <?php echo esActivo('usuarios', $paginaActual, $gruposPaginas) ? 'active' : ''; ?>">
                                <i class="fas fa-users fa-fw"></i>
                                Usuarios
                            </a>
                        </li>
                    <?php endif; ?>


                    <?php if ($tipoUsuario == ROL_ADMINISTRADOR || $tipoUsuario == ROL_SECRETARIO_TECNICO) : ?>
                        <li class="nav-item">
                            <a href="menu.php?pagina=comisiones_dashboard" class="nav-link <?php echo esActivo('comisiones', $paginaActual, $gruposPaginas) ? 'active' : ''; ?>">
                                <i class="fas fa-landmark fa-fw"></i>
                                Comisiones
                            </a>
                        </li>
                    <?php endif; ?>


                    <?php if ($tipoUsuario == ROL_ADMINISTRADOR || $tipoUsuario == ROL_SECRETARIO_TECNICO) : ?>
                        <li class="nav-item">
                            <a href="menu.php?pagina=reuniones_dashboard" class="nav-link <?php echo esActivo('reuniones', $paginaActual, $gruposPaginas) ? 'active' : ''; ?>">
                                <i class="fas fa-calendar-plus fa-fw"></i>
                                Gestión Reuniones
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php if ($tipoUsuario == ROL_CONSEJERO || $tipoUsuario == ROL_PRESIDENTE_COMISION) : ?>
                        <li class="nav-item">
                            <a href="menu.php?pagina=reunion_autogestion_asistencia" class="nav-link <?php echo esActivo('reuniones', $paginaActual, $gruposPaginas) ? 'active' : ''; ?>">
                                <i class="fas fa-hand-paper fa-fw"></i>
                                Registrar Asistencia
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php if ($tipoUsuario != ROL_SECRETARIO_TECNICO) : ?>
                        <li class="nav-item">
                            <a href="menu.php?pagina=reunion_calendario" class="nav-link <?php echo esActivo('reuniones', $paginaActual, $gruposPaginas) ? 'active' : ''; ?>">
                                <i class="fas fa-calendar-alt fa-fw"></i>
                                Calendario Reuniones
                            </a>
                        </li>
                    <?php endif; ?>


                    <?php if ($tipoUsuario == ROL_ADMINISTRADOR || $tipoUsuario == ROL_SECRETARIO_TECNICO) : ?>
                        <li class="nav-item">
                            <a href="menu.php?pagina=votaciones_dashboard" class="nav-link <?php echo esActivo('votaciones', $paginaActual, $gruposPaginas) ? 'active' : ''; ?>">
                                <i class="fas fa-tasks fa-fw"></i>
                                Gestión Votaciones
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php if ($tipoUsuario == ROL_CONSEJERO || $tipoUsuario == ROL_PRESIDENTE_COMISION) : ?>
                        <li class="nav-item">
                            <a href="menu.php?pagina=voto_autogestion" class="nav-link <?php echo esActivo('votaciones', $paginaActual, $gruposPaginas) ? 'active' : ''; ?>">
                                <i class="fas fa-vote-yea fa-fw"></i>
                                Votar ahora
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="menu.php?pagina=historial_votacion" class="nav-link <?php echo esActivo('votaciones', $paginaActual, $gruposPaginas) ? 'active' : ''; ?>">
                                <i class="fas fa-history fa-fw"></i>
                                Historial Votación
                            </a>
                        </li>
                    <?php endif; ?>


                    <li class="nav-item mt-auto">
                        <a href="/corevota/logout.php" class="nav-link nav-link-logout">
                            <i class="fas fa-sign-out-alt fa-fw"></i>
                            Cerrar Sesión
                        </a>
                    </li>

                </ul>
            </div>

        </nav>

        <header class="core-header d-flex justify-content-between align-items-center p-3">
            <h6 class="titulo-sistema mb-0 fw-bold text-muted">
                Gestión Documental de Consejo Regional de Valparaíso
            </h6>
            <div class="d-flex align-items-center gap-3">
                <div class="dropdown">
                    <span class="usuario dropdown-toggle fw-semibold" role="button" data-bs-toggle="dropdown" aria-expanded="false" style="cursor: pointer;">
                        <i class="fas fa-user-circle me-1"></i>
                        <?php
                        // Nombre y Apellido del Usuario (sin cambios)
                        if (isset($_SESSION['pNombre']) && isset($_SESSION['aPaterno'])) {
                            echo htmlspecialchars($_SESSION['pNombre'] . " " . htmlspecialchars($_SESSION['aPaterno']));
                        } else {
                            echo "Usuario invitado";
                        }
                        ?>
                    </span>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="menu.php?pagina=perfil_usuario"><i class="fas fa-id-card fa-fw me-2"></i>Mi perfil</a></li>
                        <li><a class="dropdown-item" href="menu.php?pagina=configuracion_vista"><i class="fas fa-cog fa-fw me-2"></i>Configuración</a></li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li><a class="dropdown-item text-danger" href="/corevota/logout.php"><i class="fas fa-sign-out-alt fa-fw me-2"></i>Cerrar sesión</a></li>
                    </ul>
                </div>
            </div>
        </header>

        <main>
            <?php
            // --- Bloque para mostrar mensajes de sesión (SIN CAMBIOS) ---
            $success_msg = $_SESSION['success'] ?? null;
            $error_msg = $_SESSION['error'] ?? null;
            unset($_SESSION['success'], $_SESSION['error']);
            if ($success_msg): ?>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        var modalElement = document.getElementById('confirmacionModal');
                        if (modalElement) {
                            var successModal = new bootstrap.Modal(modalElement);
                            document.getElementById('confirmacionModalMessage').textContent = <?php echo json_encode($success_msg); ?>;
                            successModal.show();
                        }
                    });
                </script>
            <?php endif; ?>
            <?php if ($error_msg): ?>
                <div class="alert alert-danger alert-dismissible fade show mx-3" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i> <?php echo htmlspecialchars($error_msg); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <?php // --- FIN Bloque mensajes --- 
            ?>

            <?php
            // --- Mostrar Saludo, Fecha y Temperatura SÓLO en la página 'home' (SIN CAMBIOS) ---
            if ($paginaActual === 'home') :
            ?>
                <div class="container-fluid mb-4">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h2 class="display-6"><?php echo $saludo . ' ' . $nombreUsuario; ?></h2>
                            <p class="lead text-muted">Hoy es <?php echo ucfirst($fechaActual); ?></p>
                        </div>
                        <div class="col-md-4 text-md-end">
                            <p id="temperatura-actual" class="fs-5 text-muted">Cargando clima...</p>
                        </div>
                    </div>
                    <hr>
                </div>
            <?php
            endif;
            // --- Fin sección Saludo ---
            ?>
            <?php
            // --- Router para cargar el contenido de la página solicitada ---
            $base_path = __DIR__;
            $controllers_path = __DIR__ . '/../../controllers';

            // --- INICIO: AJUSTE PARA RUTA DE MINUTAS PENDIENTES ---
            // (Esto asume que $tipoUsuario y ROL_PRESIDENTE_COMISION están definidos más arriba en este archivo)

            // 1. Por defecto, la ruta apunta a la vista del Secretario Técnico
            $rutaMinutasPendientes = [
                'type' => 'controller',
                'file' => $controllers_path . '/MinutaController.php', //
                'params' => ['action' => 'list', 'estado' => 'PENDIENTE']
            ];

            // 2. PERO, si el usuario es un Presidente, apuntamos a su vista de firma personal
            if ($tipoUsuario == ROL_PRESIDENTE_COMISION) {
                $rutaMinutasPendientes = [
                    'type' => 'view',
                    'file' => $base_path . '/minutaPendiente.php' //
                ];
            }
            // --- FIN: AJUSTE PARA RUTA DE MINUTAS PENDIENTES ---


            /* ============================================================
        /   MODIFICADO: RUTAS DEL ROUTER (VERSIÓN FINAL)
        ============================================================
        */
            // ¡ESTE BLOQUE HA SIDO ACTUALIZADO!
            $routes = [
                // --- VISTAS PRINCIPALES / DASHBOARDS (Ahora apuntan a las nuevas vistas) ---
                'home' => ['type' => 'view', 'file' => $base_path . '/home.php'],
                'minutas_dashboard' => ['type' => 'view', 'file' => $base_path . '/minutas_dashboard.php'],
                'usuarios_dashboard' => ['type' => 'view', 'file' => $base_path . '/usuarios_dashboard.php'],
                'comisiones_dashboard' => ['type' => 'view', 'file' => $base_path . '/comisiones_dashboard.php'],
                'reuniones_dashboard' => ['type' => 'view', 'file' => $base_path . '/reuniones_dashboard.php'],
                'votaciones_dashboard' => ['type' => 'view', 'file' => $base_path . '/votaciones_dashboard.php'],

                // --- VISTAS SECUNDARIAS (las que estaban antes) ---
                'crear_minuta' => ['type' => 'view', 'file' => $base_path . '/crearMinuta.php'],

                // --- LÍNEA MODIFICADA ---
                'minutas_pendientes' => $rutaMinutasPendientes, // Ahora usa la variable que definimos arriba
                // --- FIN LÍNEA MODIFICADA ---

                'minutas_aprobadas' => ['type' => 'controller', 'file' => $controllers_path . '/MinutaController.php', 'params' => ['action' => 'list', 'estado' => 'APROBADA']],
                'editar_minuta' => ['type' => 'view', 'file' => $base_path . '/crearMinuta.php'],
                'usuarios_listado' => ['type' => 'view', 'file' => $base_path . '/usuarios_listado.php'],
                'usuario_crear' => ['type' => 'view', 'file' => $base_path . '/usuario_formulario.php', 'params' => ['action' => 'create']],
                'comision_listado' => ['type' => 'controller', 'file' => $controllers_path . '/ComisionController.php', 'params' => ['action' => 'list']],
                'comision_crear' => ['type' => 'controller', 'file' => $controllers_path . '/ComisionController.php', 'params' => ['action' => 'create']],
                'comision_editar' => ['type' => 'controller', 'file' => $controllers_path . '/ComisionController.php', 'params' => ['action' => 'edit']],
                'reunion_crear' => ['type' => 'view', 'file' => $base_path . '/reunion_form.php'],
                'reunion_editar' => ['type' => 'controller', 'file' => $controllers_path . '/ReunionController.php', 'params' => ['action' => 'edit']],
                'reunion_listado' => ['type' => 'controller', 'file' => $controllers_path . '/ReunionController.php', 'params' => ['action' => 'list']],
                'reunion_calendario' => ['type' => 'view', 'file' => $base_path . '/reunion_calendario.php'],
                'reunion_autogestion_asistencia' => ['type' => 'view', 'file' => $base_path . '/asistencia_autogestion.php'],
                'historial_asistencia' => ['type' => 'view', 'file' => $base_path . '/historial_asistencia.php'],
                'crearVotacion' => ['type' => 'view', 'file' => $base_path . '/crearVotacion.php'],
                'votacion_crear' => ['type' => 'view', 'file' => $base_path . '/crearVotacion.php'],
                'votacion_listado' => ['type' => 'view', 'file' => $base_path . '/votacion_listado.php'],
                'historial_votacion' => ['type' => 'view', 'file' => $base_path . '/historial_votacion.php'],
                'voto_autogestion' => ['type' => 'view', 'file' => $base_path . '/voto_autogestion.php'],
                'tabla_votacion' => ['type' => 'view', 'file' => $base_path . '/tablaVotacion.php'],

                // --- VISTAS DE PERFIL Y CIERRE ---
                'perfil_usuario' => ['type' => 'view', 'file' => $base_path . '/perfil_usuario.php'],
                'configuracion_vista' => ['type' => 'view', 'file' => $base_path . '/configuracion_vista.php']
            ];
            // --- FIN BLOQUE ROUTER ACTUALIZADO ---


            // --- Lógica del Router (SIN CAMBIOS) ---
            $route = $routes[$paginaActual] ?? $routes['home'];

            if (!empty($route['params'])) {
                foreach ($route['params'] as $key => $value) {
                    if (!isset($_GET[$key])) {
                        $_GET[$key] = $value;
                    }
                    if ($key === 'action' && !isset($_REQUEST['action'])) {
                        $_REQUEST['action'] = $value;
                    }
                }
            }
            if ($id_param !== null) {
                $_GET['id'] = $id_param;
            }

            if (isset($route['file']) && file_exists($route['file'])) {
                include $route['file'];
            } else {
                if ($paginaActual !== 'home') {
                    echo "<div class='alert alert-danger m-3'>Error: El archivo para la página '<strong>" . htmlspecialchars($paginaActual) . "</strong>' no fue encontrado.</div>";
                } elseif (!isset($route['file']) || !file_exists($route['file'])) {
                    echo "<p>Contenido principal del home no encontrado.</p>";
                }
            }
            // --- Fin Router ---
            ?>
        </main>
    </div>

    <script src="/corevota/public/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

    <script>
        /**
         * Función para mostrar los adjuntos de una minuta en un modal.
         */
        function verAdjuntos(minutaId) {
            // 1. Obtener referencias a los elementos del DOM
            const modalElement = document.getElementById('modalAdjuntos');
            if (!modalElement) {
                console.error('El modal #modalAdjuntos no existe en el DOM.');
                return;
            }
            const modal = new bootstrap.Modal(modalElement);
            const listaUl = document.getElementById('listaDeAdjuntos');

            // 2. Poner el modal en estado de "Cargando..." y mostrarlo
            listaUl.innerHTML = '<li class="list-group-item text-muted">Cargando...</li>';
            modal.show();

            // 3. Usar fetch para llamar a tu nuevo archivo PHP
            fetch('/corevota/controllers/obtener_adjuntos.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        idMinuta: minutaId
                    })
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Error en la respuesta del servidor.');
                    }
                    return response.json();
                })
                .then(data => {
                    // 4. Procesar la respuesta y construir la lista
                    if (data.status === 'success' && data.adjuntos.length > 0) {
                        listaUl.innerHTML = ''; // Limpiar el "Cargando..."

                        data.adjuntos.forEach(adjunto => {
                            const li = document.createElement('li');
                            li.className = 'list-group-item d-flex justify-content-between align-items-center';

                            // Crear el enlace
                            const a = document.createElement('a');
                            a.href = adjunto.pathArchivo; // Asume que la ruta es web-accessible
                            a.textContent = adjunto.nombreArchivo;
                            a.target = '_blank'; // Abrir en pestaña nueva

                            // Añadir un ícono de descarga
                            const icon = document.createElement('i');
                            icon.className = 'fas fa-download text-primary';

                            li.appendChild(a);
                            li.appendChild(icon);
                            listaUl.appendChild(li);
                        });

                    } else if (data.adjuntos.length === 0) {
                        listaUl.innerHTML = '<li class="list-group-item text-muted">No se encontraron adjuntos para esta minuta.</li>';
                    } else {
                        // Si el servidor devolvió un error
                        throw new Error(data.message || 'No se pudieron cargar los adjuntos.');
                    }
                })
                .catch(error => {
                    // 5. Manejar cualquier error de red o del fetch
                    console.error('Error en verAdjuntos:', error);
                    listaUl.innerHTML = `<li class="list-group-item text-danger"><b>Error:</b> ${error.message}</li>`;
                });
        }



        // --- Manejador para AÑADIR LINK ---
        formLink.addEventListener('submit', (e) => {
            e.preventDefault();

            const formData = new FormData();
            formData.append('idMinuta', idMinuta);
            formData.append('tipoAdjunto', 'link');
            formData.append('urlLink', document.getElementById('inputUrlLink').value);

            const btnLink = document.getElementById('btnAgregarLink');
            btnLink.disabled = true;
            btnLink.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Añadiendo...';

            fetch('/corevota/controllers/agregar_adjunto.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        const listaUI = document.getElementById('listaAdjuntosExistentes');
                        // Quitar "No hay adjuntos" si es el primero
                        if (listaUI.querySelector('.text-muted')) {
                            listaUI.innerHTML = '';
                        }
                        const li = crearItemAdjuntoUI(data.nuevoAdjunto);
                        listaUI.appendChild(li);
                        formLink.reset(); // Limpiar el formulario
                    } else {
                        alert('Error al añadir link: ' + data.message);
                    }
                })
                .catch(error => alert('Error de red: ' + error.message))
                .finally(() => {
                    btnLink.disabled = false;
                    btnLink.innerHTML = '<i class="fas fa-link me-2"></i>Añadir';
                });
        });



        // Función para aprobar minuta
        function aprobarMinuta(idMinuta) {
            if (confirm('¿Está seguro de que desea firmar y aprobar esta minuta? Esta acción es irreversible.')) {
                console.log("Aprobando minuta: " + idMinuta);

                fetch('/corevota/controllers/aprobar_minuta.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            idMinuta: idMinuta
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            alert('Minuta aprobada con éxito. La página se recargará.');
                            window.location.reload();
                        } else {
                            alert('Error al aprobar: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error en fetch al aprobar:', error);
                        alert('Error de conexión al intentar aprobar la minuta.');
                    });
            }
        }
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Solo ejecuta el fetch si el elemento existe (es decir, si estamos en 'home')
            const tempElement = document.getElementById('temperatura-actual');
            if (tempElement) {
                const apiKey = '71852032dae024a5eb1702b278bd88fa'; // <-- ¡IMPORTANTE: Reemplaza con tu clave API de OpenWeatherMap!
                const ciudad = 'La Calera'; // La ciudad de tu región
                const pais = 'CL'; // Código de país (Chile)
                const url = `https://api.openweathermap.org/data/2.5/weather?q=${ciudad},${pais}&appid=${apiKey}&units=metric&lang=es`;

                fetch(url)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.main && data.main.temp && data.weather && data.weather[0]) {
                            const temperatura = Math.round(data.main.temp);
                            const descripcion = data.weather[0].description;
                            const iconCode = data.weather[0].icon;

                            let iconoFa = 'fas fa-cloud-sun';
                            if (iconCode.includes('01')) iconoFa = 'fas fa-sun';
                            else if (iconCode.includes('02')) iconoFa = 'fas fa-cloud-sun';
                            else if (iconCode.includes('03') || iconCode.includes('04')) iconoFa = 'fas fa-cloud';
                            else if (iconCode.includes('09') || iconCode.includes('10')) iconoFa = 'fas fa-cloud-showers-heavy';
                            else if (iconCode.includes('11')) iconoFa = 'fas fa-bolt';
                            else if (iconCode.includes('13')) iconoFa = 'fas fa-snowflake';
                            else if (iconCode.includes('50')) iconoFa = 'fas fa-smog';

                            tempElement.innerHTML = `<i class="${iconoFa} me-2"></i> ${temperatura}°C, ${descripcion}`;

                        } else {
                            tempElement.textContent = 'Clima no disponible';
                        }
                    })
                    .catch(error => {
                        console.error('Error al obtener datos del clima:', error);
                        tempElement.textContent = 'Error al cargar clima';
                    });
            }
        });
    </script>

    <div class="modal fade" id="confirmacionModal" tabindex="-1" aria-labelledby="confirmacionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="confirmacionModalLabel"><i class="fas fa-check-circle me-2"></i>Operación Exitosa</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="confirmacionModalMessage">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Aceptar</button>
                </div>
            </div>
        </div>
    </div>
</body>

</html>