<?php


// SEGURIDAD: Iniciar la sesión de forma segura
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// SEGURIDAD: Redirigir si el usuario no está logueado
if (!isset($_SESSION['idUsuario'])) {
  header("Location: /corevota/views/pages/login.php");
  exit;
}

// ❗️ NUEVO: Capturar la página a cargar
// Si no se especifica 'pagina', cargamos 'crear_minuta' por defecto
$pagina = $_GET['pagina'] ?? 'crear_minuta';
$id_param = $_GET['id'] ?? null; // ❗️ Capturamos el ID también
if (isset($_SESSION['tipoUsuario_id']) && $_SESSION['tipoUsuario_id'] == 1):  endif; ?>
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
    /* VARIABLES DE LAYOUT */
    :root {
      --sidebar-width: 230px;
      --header-height: 65px;
    }

    /* Estilos generales */
    html,
    body {
      height: 100%;
      margin: 0;
      overflow: hidden;
      font-family: Arial, sans-serif;
    }

    .app-container {
      height: 100vh;
      display: flex;
      flex-direction: column;
    }

    /* ===== SIDEBAR IZQUIERDO (Fijo) ===== */
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
      background-color: #f8f9fa;
      /* Fondo gris claro para el sidebar como en la imagen */
    }

    /* Contenedor del logo y título CORE VOTA */
    .sidebar-header-box {
      padding: 15px 15px 5px 15px;
      /* Espaciado superior */
      font-weight: 700;
      font-size: 1rem;
      color: #333;
      border-bottom: 1px solid #dee2e6;

      /* CENTRADO DEL LOGO */
      display: flex;
      justify-content: center;
      /* Centra horizontalmente */
      align-items: center;
      flex-direction: column;
      /* Apila el logo y el texto */
    }

    .sidebar-logo {
      height: 150px;
      margin-right: 5px;
      margin-bottom: 5px;
    }

    /* Estilos de los títulos principales (Hogar, Panel, etc.) */
    .sidebar-section-title {
      font-weight: bold;
      color: #333;
      padding: 8px 15px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-top: 10px;
      cursor: pointer;
    }

    .sidebar-section-title:hover {
      background-color: #e9ecef;
      border-radius: 5px;
    }

    .toggle-icon {
      font-size: 0.8rem;
      transition: transform 0.3s ease;
    }

    /* Estilos para los enlaces anidados (sub-menú) */
    .sidebar-sub-menu {
      padding-left: 15px;
      /* Indentación del submenú */
    }

    .sidebar-sub-menu a {
      display: block;
      padding: 5px 15px;
      color: #555;
      font-size: 0.9rem;
      text-decoration: none;
    }

    .sidebar-sub-menu a:hover {
      color: #000;
      background-color: #e9ecef;
    }

    /* Botón de cerrar sesión en la base */
    .sidebar-footer {
      padding: 15px;
      border-top: 1px solid #dee2e6;
    }


    /* ===== NAVBAR SUPERIOR (Fijo) ===== */
    .core-header {
      width: calc(100% - var(--sidebar-width));
      margin-left: var(--sidebar-width);
      position: fixed;
      top: 0;
      right: 0;
      z-index: 1020;
      /* Mantiene el padding p-3 de Bootstrap */
    }

    /* ===== CONTENIDO PRINCIPAL (Scrollable) ===== */
    main {
      margin-top: var(--header-height);
      margin-left: var(--sidebar-width);
      width: calc(100% - var(--sidebar-width));
      height: calc(100vh - var(--header-height));
      overflow-y: auto;

      /* ❗️ ESTILO MODIFICADO: El padding ahora está aquí */
      padding: 1.5rem;
      /* Ajusta este padding como necesites */

      background-color: #f8f9fa;
    }

    /* ❗️ ELIMINADO EL ESTILO DE IFRAME */

    /* Estilos para los botones de las secciones (Reuniones, Usuarios, Comisiones) */
    .nav-pills .btn-toggle {
      color: #333;
      font-weight: bold;
      padding: 8px 15px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-top: 10px;
      cursor: pointer;
      background: none;
      border: none;
    }

    .nav-pills .btn-toggle:hover {
      background-color: #e9ecef;
      border-radius: 5px;
    }

    /* Estilos para los links dentro de las secciones */
    .btn-toggle-nav a {
      padding: 5px 15px;
      color: #555;
      font-size: 0.9rem;
      text-decoration: none;
    }

    .btn-toggle-nav a:hover {
      color: #000;
      background-color: #e9ecef;
    }
  </style>
</head>

<body>
  <div class="container-fluid app-container">

    <nav class="sidebar d-flex flex-column flex-shrink-0 border-end">

      <div class="sidebar-header-box d-flex align-items-center">
        <img src="/corevota/public/img/logoCore1.png" alt="Logo CORE" class="sidebar-logo">

      </div>

      <div class="flex-grow-1">
        <ul class="nav nav-pills flex-column mb-auto">
          <?php if ($_SESSION['descPerfil'] === 'Administrador' || $_SESSION['descPerfil'] === 'Secrtario Tecnico'): ?>

            <li>
              <button class="btn btn-toggle align-items-center rounded collapsed w-100 text-start"
                data-bs-toggle="collapse" data-bs-target="#minutas-collapse" aria-expanded="true">
                <span class="d-flex align-items-center">
                  <i class="fa-solid fa-file-alt me-2"></i>
                  Minutas
                </span>
                <span class="toggle-icon down">▼</span>
              </button>
              <div class="collapse show" id="minutas-collapse">
                <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small ps-3">

                  <li>
                    <a href="menu.php?pagina=crear_minuta"
                      class="link-dark d-block rounded py-1">Crear Minuta</a>
                  </li>
                  <li>
                    <a href="menu.php?pagina=minutas_pendientes"
                      class="link-warning d-block rounded py-1">
                      Minutas Pendientes
                    </a>
                  </li>
                  <li>
                    <a href="menu.php?pagina=minutas_aprobadas"
                      class="link-success d-block rounded py-1">
                      Minutas Aprobadas
                    </a>
                  </li>

                </ul>
              </div>
            </li>
          <?php endif; ?>

          <li>
            <button class="btn btn-toggle align-items-center rounded collapsed w-100 text-start"
              data-bs-toggle="collapse" data-bs-target="#usuarios-collapse" aria-expanded="true">
              <span class="d-flex align-items-center">
                <i class="fa-solid fa-users me-2"></i>
                Usuarios
              </span>
              <span class="toggle-icon down">▼</span>
            </button>
            <div class="collapse show" id="usuarios-collapse">
              <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small ps-3">

                <li><a href="menu.php?pagina=usuarios_listado"
                    class="link-dark d-block rounded py-1">Revisar listado de usuarios registrados</a></li>
                <li><a href="menu.php?pagina=usuario_crear"
                    class="link-dark d-block rounded py-1">Registrar un nuevo usuario</a></li>

              </ul>
            </div>
          </li>

          <li>
            <button class="btn btn-toggle align-items-center rounded collapsed w-100 text-start"
              data-bs-toggle="collapse" data-bs-target="#comisiones-collapse" aria-expanded="true">
              <span class="d-flex align-items-center">
                <i class="fa-solid fa-landmark me-2"></i>
                Comisiones
              </span>
              <span class="toggle-icon down">▼</span>
            </button>
            <div class="collapse show" id="comisiones-collapse">
              <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small ps-3">

                <li>
                  <a href="menu.php?pagina=comision_crear"
                    class="link-dark d-block rounded py-1">Registrar nueva comisión</a>
                </li>
                <li>
                  <a href="menu.php?pagina=comision_listado"
                    class="link-dark d-block rounded py-1">Revisar listado de comisiones guardadas</a>
                </li>

              </ul>
            </div>
          </li>

          <li>
            <button class="btn btn-toggle align-items-center rounded collapsed w-100 text-start"
              data-bs-toggle="collapse" data-bs-target="#reuniones-collapse" aria-expanded="true">
              <span class="d-flex align-items-center">
                <i class="fa-solid fa-calendar-check me-2"></i>
                Reuniones
              </span>
              <span class="toggle-icon down">▼</span>
            </button>
            <div class="collapse show" id="reuniones-collapse">
              <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small ps-3">

                <li>
                  <a href="menu.php?pagina=reunion_crear"
                    class="link-dark d-block rounded py-1">Crear Reunión</a>
                </li>
                <li>
                  <a href="menu.php?pagina=reunion_listado"
                    class="link-dark d-block rounded py-1">Listado de Reuniones</a>
                </li>
                <li>
                  <a href="menu.php?pagina=reunion_calendario"
                    class="link-dark d-block rounded py-1">Vista Calendario</a>
                </li>

                <li class="nav-item">
              <a href="menu.php?pagina=reunion_autogestion_asistencia" class="nav-link link-dark fw-bold">
                <i class="fa-solid fa-hand-pointer me-2 text-success"></i>
                Registrar Mi Asistencia
              </a>
            </li>

              </ul>

              
            </div>
          </li>
          

        </ul>


      </div>

    </nav>


    <header class="core-header d-flex justify-content-between align-items-center p-3 border-bottom bg-white shadow-sm">
      <h6 class="titulo-sistema mb-0 fw-bold">
        Plataforma Gestión Documental Consejo Regional de Valparaíso
      </h6>

      <div class="d-flex align-items-left gap-3">
        <span class="perfil">
          Perfil:
          <strong>
            <?php echo $_SESSION['descPerfil'] ?? 'No definido'; ?>
          </strong>
        </span>

        <div class="dropdown">
          <span class="usuario dropdown-toggle fw-semibold" data-bs-toggle="dropdown" aria-expanded="false">
            <?php
            if (isset($_SESSION['pNombre']) && isset($_SESSION['aPaterno'])) {
              echo $_SESSION['pNombre'] . " " . $_SESSION['aPaterno'];
            } else {
              echo "Usuario invitado";
            }
            ?>
          </span>
          <ul class="dropdown-menu dropdown-menu-end">

            <li>
              <a class="dropdown-item" href="menu.php?pagina=perfil_usuario">
                Mi perfil
              </a>
            </li>
            <li>
              <a class="dropdown-item" href="menu.php?pagina=configuracion_vista">
                Configuración
              </a>
            </li>
            <li>
              <hr class="dropdown-divider">
            </li>
            <li><a class="dropdown-item text-danger" href="/corevota/logout.php">Cerrar sesión</a></li>
          </ul>
        </div>
      </div>
    </header>

    <main>
      <?php
      // Este "router" decide qué archivo cargar basado en la variable $pagina de la URL
      switch ($pagina) {

        // Casos de Minutas
        case 'crear_minuta':
          include __DIR__ . '/crearMinuta.php';
          break;

        // --- INICIO DE LA CORRECCIÓN ---
        // Se cambió 'MinutaController.php' a 'minutaController.php' (minúscula)
        case 'minutas_pendientes':
          $_GET['action'] = 'list'; // Preparamos las variables para el controller
          $_GET['estado'] = 'PENDIENTE';
          include __DIR__ . '/../../controllers/minutaController.php';
          break;
        case 'minutas_aprobadas':
          $_GET['action'] = 'list'; // Preparamos las variables para el controller
          $_GET['estado'] = 'APROBADA';
          include __DIR__ . '/../../controllers/minutaController.php';
          break;
        // --- FIN DE LA CORRECCIÓN ---

        // Casos de Usuarios
        case 'usuarios_listado':
          include __DIR__ . '/usuarios_listado.php';
          break;
        case 'usuario_crear':
          $_GET['action'] = 'create';
          include __DIR__ . '/usuario_formulario.php';
          break;

        // Casos de Comisiones
        // --- INICIO DE LA CORRECCIÓN ---
        // Se cambió 'ComisionController.php' a 'comisionController.php' (minúscula)
        case 'comision_listado':
          $_GET['action'] = 'list'; // Preparamos para el controller
          include __DIR__ . '/../../controllers/comisionController.php';
          break;
        case 'comision_crear':
          $_GET['action'] = 'create'; // Preparamos para el controller
          include __DIR__ . '/../../controllers/comisionController.php';
          break;
        case 'comision_editar':
          // Pasamos el ID a $_GET para que comisionController lo pueda leer
          if ($id_param) {
            $_GET['action'] = 'edit'; // Preparamos para el controller
            $_GET['id'] = $id_param;
            include __DIR__ . '/../../controllers/comisionController.php';
          } else {
            echo "<div class='alert alert-danger'>Error: Falta el ID de la comisión para editar.</div>";
          }
          break;
        // --- FIN DE LA CORRECCIÓN ---

        // Casos de Reuniones
        case 'reunion_crear':
          include __DIR__ . '/reunion_form.php';
          break;
        case 'reunion_listado':
          $_GET['action'] = 'list';
          include __DIR__ . '/../../controllers/ReunionController.php'; // Este estaba correcto
          break;
        case 'reunion_calendario':
          include __DIR__ . '/reunion_calendario.php';
          break;

        // Casos del Dropdown de Perfil
        case 'perfil_usuario':
          include __DIR__ . '/perfil_usuario.php';
          break;
        case 'configuracion_vista':
          include __DIR__ . '/configuracion_vista.php';
          break;



        // ❗️❗️ NUEVO CASE PARA EDITAR MINUTA ❗️❗️
        case 'editar_minuta':
          // Pasamos el ID a $_GET para que crearMinuta.php lo pueda leer
          if ($id_param) {
            $_GET['id'] = $id_param; // Aseguramos que $_GET['id'] esté disponible
            include __DIR__ . '/crearMinuta.php'; // Incluimos el mismo formulario
          } else {
            echo "<div class='alert alert-danger'>Error: Falta el ID de la minuta para editar.</div>";
          }
          break;
        // ❗️❗️ FIN NUEVO CASE ❗️❗️


        case 'reunion_listado':
          $_GET['action'] = 'list'; // Prepara para el controlador
          include __DIR__ . '/../../controllers/ReunionController.php';
          break;

        case 'reunion_autogestion_asistencia': // Página para Consejeros
          include __DIR__ . '/asistencia_autogestion.php';
          break;

        // Caso por defecto (si 'pagina' no coincide con nada)
        default:
          echo "<div class='alert alert-warning'>Página no encontrada.</div>";
          break;
      }
      ?>
    </main>

  </div>

  <script src="/corevota/public/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>

</html>