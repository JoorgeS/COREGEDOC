<nav class="sidebar d-flex flex-column flex-shrink-0 bg-white border-end" id="sidebar-wrapper">

    <div class="sidebar-heading text-center py-3 border-bottom">
        <img src="public/img/logoCore1.png" alt="Logo CORE" class="img-fluid" style="max-width: 250px; height: auto;">
    </div>

    <div class="flex-grow-1 overflow-auto custom-scrollbar">
        <ul class="nav nav-pills flex-column mb-auto p-3 gap-1">

            <li class="nav-item">
                <a href="index.php?action=home" class="nav-link <?php echo esActivo('home', $paginaActual) ? 'active' : ''; ?>">
                    <i class="fas fa-home fa-fw me-2"></i> Inicio
                </a>
            </li>

            <?php

            $rolesGestion = [ROL_ADMINISTRADOR, ROL_SECRETARIO_TECNICO, ROL_PRESIDENTE_COMISION];


            $rolesAvanzados = [ROL_ADMINISTRADOR];
            ?>

            <?php if (in_array($tipoUsuario, $rolesGestion)): ?>

                <li class="nav-item mt-3 mb-1 text-muted small fw-bold text-uppercase px-2">Gestión</li>

                <li class="nav-item">
                    <a href="index.php?action=minutas_dashboard" class="nav-link <?php echo esActivo('minutas', $paginaActual) ? 'active' : ''; ?>">
                        <i class="fas fa-file-alt fa-fw me-2"></i> Gestión Minutas
                    </a>
                </li>

                <?php if (in_array($tipoUsuario, [ROL_ADMINISTRADOR, ROL_SECRETARIO_TECNICO])): ?>
                    <li class="nav-item">
                        <a href="index.php?action=reuniones_dashboard"
                            class="nav-link <?php echo ($paginaActual == 'reuniones_menu') ? 'active' : ''; ?>">
                            <i class="fas fa-briefcase fa-fw me-2"></i> Gestión Reuniones
                        </a>
                    </li>
                <?php endif; ?>

                <?php

                if (in_array($tipoUsuario, $rolesAvanzados)):
                ?>
                    <li class="nav-item mt-3 mb-1 text-muted small fw-bold text-uppercase px-2">Administración</li>

                    <li class="nav-item">
                        <a href="index.php?action=usuarios_dashboard" class="nav-link <?php echo ($paginaActual == 'usuarios_dashboard') ? 'active' : ''; ?>">
                            <i class="fas fa-users-cog fa-fw me-2"></i> Usuarios
                        </a>
                    </li>

                    <li class="nav-item">
                        <a href="index.php?action=comisiones_dashboard" class="nav-link <?php echo ($paginaActual == 'comisiones_dashboard') ? 'active' : ''; ?>">
                            <i class="fas fa-sitemap fa-fw me-2"></i> Comisiones
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link <?php echo ($paginaActual == 'reporte_asistencia') ? 'active' : ''; ?>" href="index.php?action=reporte_asistencia">
                            <i class="fas fa-file-contract fa-fw me-2"></i> Reportes Asistencia
                        </a>
                    </li>


                <?php endif; ?>

            <?php endif; ?>



            <?php

            if (in_array($tipoUsuario, [1, 3, 7])):
            ?>
                <li class="nav-item mt-3 mb-1 text-muted small fw-bold text-uppercase px-2">Sala Virtual</li>

                <li class="nav-item">
                    <a href="index.php?action=asistencia_sala" class="nav-link <?php echo ($paginaActual == 'sala_reuniones') ? 'active' : ''; ?>">
                        <i class="fa-solid fa-chalkboard-user fa-fw me-2"></i>Sala de Reuniones
                    </a>
                </li>

                <li class="nav-item">
                    <a href="index.php?action=voto_autogestion" class="nav-link <?php echo ($paginaActual == 'sala_votaciones') ? 'active' : ''; ?>">
                        <i class="fa-solid fa-person-booth me-2"></i>Sala de Votaciones
                    </a>
                </li>
            <?php endif; ?>



            <li class="nav-item mt-3 mb-1 text-muted small fw-bold text-uppercase px-2">Enlaces Externos</li>

            <li class="nav-item">
                <a href="https://accounts.google.com/v3/signin/identifier?continue=https%3A%2F%2Fmail.google.com%2Fmail%2F&dsh=S740133944%3A1764181666302041&hd=gorevalparaiso.gob.cl&osid=1&sacu=1&service=mail&flowName=GlifWebSignIn&flowEntry=AddSession"
                    target="_blank"
                    class="nav-link text-truncate"
                    title="Ir al Correo Institucional">
                    <i class="fas fa-envelope fa-fw me-2 text-danger"></i> Correo Institucional
                </a>
            </li>

        </ul>
    </div>

    <div class="sidebar-footer border-top p-3">
        <a href="index.php?action=logout" class="nav-link nav-link-logout text-danger fw-bold d-flex align-items-center">
            <i class="fas fa-sign-out-alt fa-fw me-2"></i> Cerrar Sesión
        </a>
    </div>
</nav>