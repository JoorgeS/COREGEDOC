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

// Capturar la página a cargar
$pagina = $_GET['pagina'] ?? 'home'; // Usar 'home' como default
$id_param = $_GET['id'] ?? null; // Capturamos el ID también
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
    /* ESTILOS (sin cambios respecto a tu versión anterior) */
    :root {
      --sidebar-width: 230px;
      --header-height: 65px;
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

    .sidebar-header-box {
      padding: 15px;
      border-bottom: 1px solid #dee2e6;
      text-align: center;
    }

    .sidebar-logo {
      max-height: 150px;
      width: auto;
      margin-bottom: 10px;
    }

    /* Ajuste: Quitar cursor pointer si el botón ya no hace nada */
    .nav-pills .btn-toggle {
      color: #333;
      font-weight: bold;
      padding: 10px 15px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      /* cursor: default; */
      background: none;
      border: none;
      width: 100%;
      text-align: left;
      border-radius: 0;
    }

    .nav-pills .btn-toggle:hover,
    .nav-pills .btn-toggle:focus {
      background-color: transparent;
      /* Quitar hover si ya no es clickeable */
      outline: none;
      box-shadow: none;
    }

    /* .toggle-icon { display: none; } */
    /* Ocultar icono si se quita del HTML */
    .btn-toggle-nav {
      padding-left: 1.25rem;
    }

    .btn-toggle-nav a {
      padding: 8px 15px;
      color: #495057;
      font-size: 0.9rem;
      text-decoration: none;
      display: block;
      border-radius: 4px;
      margin-bottom: 2px;
    }

    .btn-toggle-nav a:hover,
    .btn-toggle-nav a.active {
      color: #0d6efd;
      background-color: #e7f1ff;
    }

    .btn-toggle-nav a i {
      width: 20px;
      text-align: center;
      margin-right: 8px;
    }

    .link-warning {
      color: #ffc107 !important;
    }

    .link-success {
      color: #198754 !important;
    }

    .link-danger {
      color: #dc3545 !important;
    }

    .sidebar-footer {
      padding: 15px;
      border-top: 1px solid #dee2e6;
      margin-top: auto;
    }

    .core-header {
      height: var(--header-height);
      width: calc(100% - var(--sidebar-width));
      margin-left: var(--sidebar-width);
      position: fixed;
      top: 0;
      right: 0;
      z-index: 1020;
      background-color: #ffffff;
      border-bottom: 1px solid #dee2e6;
      box-shadow: 0 1px 3px rgba(0, 0, 0, .05);
    }

    main {
      margin-top: var(--header-height);
      margin-left: var(--sidebar-width);
      width: calc(100% - var(--sidebar-width));
      height: calc(100vh - var(--header-height));
      overflow-y: auto;
      padding: 1.5rem;
      background-color: #f8f9fa;
    }
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
          <li>
            <button class="btn btn-toggle align-items-center rounded w-100" data-bs-toggle="collapse" data-bs-target="#minutas-collapse" aria-expanded="true">
              <span class="d-flex align-items-center"><i class="fas fa-file-alt fa-fw me-2"></i>Minutas</span>
            </button>
            <div class="collapse show" id="minutas-collapse">
              <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small">
                <li><a href="menu.php?pagina=crear_minuta" class="link-dark d-block rounded py-1"><i class="fas fa-plus fa-fw me-2"></i>Crear Minuta</a></li>
                <li><a href="menu.php?pagina=minutas_pendientes" class="link-warning d-block rounded py-1"><i class="fas fa-clock fa-fw me-2"></i>Minutas Pendientes</a></li>
                <li><a href="menu.php?pagina=minutas_aprobadas" class="link-success d-block rounded py-1"><i class="fas fa-check-circle fa-fw me-2"></i>Minutas Aprobadas</a></li>
              </ul>
            </div>
          </li>
          <li>
            <button class="btn btn-toggle align-items-center rounded w-100" data-bs-toggle="collapse" data-bs-target="#usuarios-collapse" aria-expanded="true">
              <span class="d-flex align-items-center"><i class="fas fa-users fa-fw me-2"></i>Usuarios</span>
            </button>
            <div class="collapse show" id="usuarios-collapse">
              <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small">
                <li><a href="menu.php?pagina=usuarios_listado" class="link-dark d-block rounded py-1"><i class="fas fa-list fa-fw me-2"></i>Revisar listado</a></li>
                <li><a href="menu.php?pagina=usuario_crear" class="link-dark d-block rounded py-1"><i class="fas fa-user-plus fa-fw me-2"></i>Registrar nuevo</a></li>
              </ul>
            </div>
          </li>
          <li>
            <button class="btn btn-toggle align-items-center rounded w-100" data-bs-toggle="collapse" data-bs-target="#comisiones-collapse" aria-expanded="true">
              <span class="d-flex align-items-center"><i class="fas fa-landmark fa-fw me-2"></i>Comisiones</span>
            </button>
            <div class="collapse show" id="comisiones-collapse">
              <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small">
                <li><a href="menu.php?pagina=comision_crear" class="link-dark d-block rounded py-1"><i class="fas fa-plus fa-fw me-2"></i>Registrar nueva</a></li>
                <li><a href="menu.php?pagina=comision_listado" class="link-dark d-block rounded py-1"><i class="fas fa-list fa-fw me-2"></i>Revisar listado</a></li>
              </ul>
            </div>
          </li>
          <li>
            <button class="btn btn-toggle align-items-center rounded w-100" data-bs-toggle="collapse" data-bs-target="#reuniones-collapse" aria-expanded="true">
              <span class="d-flex align-items-center"><i class="fas fa-calendar-check fa-fw me-2"></i>Reuniones</span>
            </button>
            <div class="collapse show" id="reuniones-collapse">
              <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small">
                <li><a href="menu.php?pagina=reunion_crear" class="link-dark d-block rounded py-1"><i class="fas fa-plus fa-fw me-2"></i>Crear Reunión</a></li>
                <li><a href="menu.php?pagina=reunion_listado" class="link-dark d-block rounded py-1"><i class="fas fa-list fa-fw me-2"></i>Listado</a></li>
                <li><a href="menu.php?pagina=reunion_calendario" class="link-dark d-block rounded py-1"><i class="fas fa-calendar-alt fa-fw me-2"></i>Vista Calendario</a></li>
                <li><a href="menu.php?pagina=reunion_autogestion_asistencia" class="link-success d-block rounded py-1 fw-bold"><i class="fas fa-hand-pointer fa-fw me-2"></i>Registrar Mi Asistencia</a></li>
              </ul>
            </div>
          </li>
          <li>
            <button class="btn btn-toggle align-items-center rounded w-100" data-bs-toggle="collapse" data-bs-target="#votaciones-collapse" aria-expanded="true">
              <span class="d-flex align-items-center"><i class="fas fa-list-check fa-fw me-2"></i>Votaciones</span>
            </button>
            <div class="collapse show" id="votaciones-collapse">
              <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small">
                <li><a href="menu.php?pagina=crearVotacion" class="link-dark d-block rounded py-1"><i class="fas fa-plus fa-fw me-2"></i>Registrar nueva</a></li>
                <li><a href="menu.php?pagina=votacion_listado" class="link-dark d-block rounded py-1"><i class="fas fa-list fa-fw me-2"></i>Revisar listado</a></li>
                <li><a href="menu.php?pagina=voto_autogestion" class="link-success d-block rounded py-1 fw-bold"><i class="fas fa-check-to-slot fa-fw me-2"></i>Registrar Votación</a></li>
              </ul>
            </div>
          </li>
        </ul>
      </div>
    </nav>

    <header class="core-header d-flex justify-content-between align-items-center p-3">
      <h6 class="titulo-sistema mb-0 fw-bold text-muted">
        Plataforma Gestión Documental Consejo Regional de Valparaíso
      </h6>
      <div class="d-flex align-items-center gap-3">
        <span class="perfil small text-muted">
          Perfil: <strong><?php echo htmlspecialchars($_SESSION['descPerfil'] ?? 'No definido'); ?></strong>
        </span>
        <div class="dropdown">
          <span class="usuario dropdown-toggle fw-semibold" role="button" data-bs-toggle="dropdown" aria-expanded="false" style="cursor: pointer;">
            <i class="fas fa-user-circle me-1"></i>
            <?php
            if (isset($_SESSION['pNombre']) && isset($_SESSION['aPaterno'])) {
              echo htmlspecialchars($_SESSION['pNombre'] . " " . $_SESSION['aPaterno']);
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
      // Este "router" decide qué archivo cargar basado en la variable $pagina de la URL
      $base_path = __DIR__;
      $controllers_path = __DIR__ . '/../../controllers';

      $routes = [
        'crear_minuta' => ['type' => 'view', 'file' => $base_path . '/crearMinuta.php'],
        'minutas_pendientes' => ['type' => 'controller', 'file' => $controllers_path . '/MinutaController.php', 'params' => ['action' => 'list', 'estado' => 'PENDIENTE']],
        'minutas_aprobadas' => ['type' => 'controller', 'file' => $controllers_path . '/MinutaController.php', 'params' => ['action' => 'list', 'estado' => 'APROBADA']],
        'editar_minuta' => ['type' => 'view', 'file' => $base_path . '/crearMinuta.php'], // Usa la misma vista
        'usuarios_listado' => ['type' => 'view', 'file' => $base_path . '/usuarios_listado.php'],
        'usuario_crear' => ['type' => 'view', 'file' => $base_path . '/usuario_formulario.php', 'params' => ['action' => 'create']],
        'comision_listado' => ['type' => 'controller', 'file' => $controllers_path . '/ComisionController.php', 'params' => ['action' => 'list']],
        'comision_crear' => ['type' => 'controller', 'file' => $controllers_path . '/ComisionController.php', 'params' => ['action' => 'create']],
        'comision_editar' => ['type' => 'controller', 'file' => $controllers_path . '/ComisionController.php', 'params' => ['action' => 'edit']],
        'reunion_crear' => ['type' => 'view', 'file' => $base_path . '/reunion_form.php'],
        'reunion_listado' => ['type' => 'controller', 'file' => $controllers_path . '/ReunionController.php', 'params' => ['action' => 'list']],
        'reunion_calendario' => ['type' => 'view', 'file' => $base_path . '/reunion_calendario.php'],
        'reunion_autogestion_asistencia' => ['type' => 'view', 'file' => $base_path . '/asistencia_autogestion.php'],
        'crearVotacion' => ['type' => 'view', 'file' => $base_path . '/crearVotacion.php'],
        'votacion_crear' => ['type' => 'view', 'file' => $base_path . '/crearVotacion.php'],
        'votacion_listado' => ['type' => 'view', 'file' => $base_path . '/votacion_listado.php'],
        'voto_autogestion' => ['type' => 'view', 'file' => $base_path . '/voto_autogestion.php'],
        'tabla_votacion' => ['type' => 'view', 'file' => $base_path . '/tablaVotacion.php'],
        'perfil_usuario' => ['type' => 'view', 'file' => $base_path . '/perfil_usuario.php'],
        'configuracion_vista' => ['type' => 'view', 'file' => $base_path . '/configuracion_vista.php'],
        'home' => ['type' => 'view', 'file' => $base_path . '/home.php']
      ];

      $route = $routes[$pagina] ?? $routes['home'];

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
      // Asegurar que el ID llegue a las páginas que lo necesitan (editar_minuta, comision_editar)
      if ($id_param !== null) {
        $_GET['id'] = $id_param;
      }

      if (isset($route['file']) && file_exists($route['file'])) {
        include $route['file'];
      } else {
        echo "<div class='alert alert-danger m-3'>Error: El archivo para la página '<strong>" . htmlspecialchars($pagina) . "</strong>' no fue encontrado...</div>";
      }
      ?>
    </main>

  </div>
  <script src="/corevota/public/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

  <script>
    /**
     * Función para aprobar minuta (usada en minutas_listado_general.php)
     */
    function aprobarMinuta(idMinuta) {
      if (!confirm("¿Está seguro de FIRMAR y APROBAR esta minuta? ¡Irreversible!")) {
        return;
      }
      fetch("/corevota/controllers/aprobar_minuta.php", {
          method: "POST",
          headers: {
            "Content-Type": "application/json"
          },
          body: JSON.stringify({
            idMinuta: idMinuta
          })
        })
        .then(res => res.ok ? res.json() : res.text().then(text => Promise.reject(new Error(text))))
        .then(response => {
          if (response.status === 'success') {
            alert("✅ Minuta aprobada.");
            window.location.reload();
          } else {
            alert(`⚠️ Error al aprobar: ${response.message}`);
          }
        })
        .catch(err => alert("Error de red al aprobar:\n" + err.message));
    }

    // --- CÓDIGO JAVASCRIPT PARA MODAL DE ADJUNTOS ---
    const modalAdjuntosElement = document.getElementById('modalAdjuntos');
    let modalAdjuntosInstance = null;
    let listaAdjuntosElement = null;
    if (modalAdjuntosElement) {
      modalAdjuntosInstance = new bootstrap.Modal(modalAdjuntosElement);
      listaAdjuntosElement = document.getElementById('listaDeAdjuntos');
    }

    /**
     * Función para mostrar el modal y cargar los adjuntos (usada en minutas_listado_general.php)
     */
    async function verAdjuntos(idMinuta) {
      console.log("verAdjuntos llamada con id:", idMinuta);
      if (!idMinuta || !modalAdjuntosInstance || !listaAdjuntosElement) {
        console.error("Error: Faltan elementos necesarios para verAdjuntos.");
        if (!modalAdjuntosInstance) console.error("La instancia del Modal no está inicializada.");
        if (!listaAdjuntosElement) console.error("El elemento UL 'listaDeAdjuntos' no se encontró.");
        return;
      }
      console.log("Elementos del modal encontrados, procediendo...");

      listaAdjuntosElement.innerHTML = '<li class="list-group-item text-muted"><i class="fas fa-spinner fa-spin me-2"></i>Cargando...</li>';
      try {
        modalAdjuntosInstance.show();
      } catch (e) {
        console.error("Error al intentar mostrar el modal:", e);
        return;
      }

      try {
        const response = await fetch(`/corevota/controllers/fetch_data.php?action=adjuntos_por_minuta&idMinuta=${idMinuta}`);
        if (!response.ok) {
          const errorText = await response.text();
          throw new Error(`Error de red (${response.status}): ${errorText || 'No se pudo cargar'}`);
        }
        const data = await response.json();

        if (data.status === 'success' && data.data && data.data.length > 0) {
          listaAdjuntosElement.innerHTML = '';
          data.data.forEach(adj => {
            const li = document.createElement('li');
            li.className = 'list-group-item';
            const link = document.createElement('a');
            const isFile = adj.tipoAdjunto === 'file';
            link.href = isFile ? `/corevota/public/${adj.pathAdjunto}` : adj.pathAdjunto;
            link.target = '_blank';
            link.title = adj.pathAdjunto;
            const iconClass = isFile ? 'fas fa-paperclip' : 'fas fa-link';
            const fileName = isFile ? adj.pathAdjunto.split('/').pop() : 'Enlace Externo';
            link.innerHTML = `<i class="${iconClass} me-2"></i> ${fileName}`;
            li.appendChild(link);
            listaAdjuntosElement.appendChild(li);
          });
        } else {
          listaAdjuntosElement.innerHTML = '<li class="list-group-item text-info"><i class="fas fa-info-circle me-2"></i>No se encontraron adjuntos para esta minuta.</li>';
        }
      } catch (error) {
        console.error('Error en fetch verAdjuntos:', error);
        listaAdjuntosElement.innerHTML = `<li class="list-group-item text-danger"><i class="fas fa-exclamation-triangle me-2"></i>Error al cargar: ${error.message}</li>`;
      }
    }
    // --- FIN CÓDIGO MODAL ---

    // CAMBIO: Se eliminó el script que manejaba la rotación del icono del sidebar
  </script>
</body>

</html>