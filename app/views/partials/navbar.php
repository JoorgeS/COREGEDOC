<header class="core-header d-flex justify-content-between align-items-center px-4 py-3 text-white shadow-sm">
    <div class="d-flex align-items-center">
        <button class="btn btn-link text-white me-3 d-md-none" id="sidebarToggle">
            <i class="fas fa-bars fa-lg"></i>
        </button>
        <h6 class="m-0 fw-bold text-uppercase letter-spacing-1">Gestor Documental Consejo de la región de valparaíso</h6>
    </div>
    
    <div class="d-flex align-items-center gap-3">
        <div class="d-flex flex-column text-end lh-1">
            <span class="fw-bold small"><?php echo htmlspecialchars($nombreUsuario); ?></span>
            <span class="small text-white-50" style="font-size: 0.75rem;">
                <?php echo ($tipoUsuario == ROL_ADMINISTRADOR) ? 'Administrador' : 'Usuario'; ?>
            </span>
        </div>
        <div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
            <i class="fas fa-user"></i>
        </div>
    </div>
</header>