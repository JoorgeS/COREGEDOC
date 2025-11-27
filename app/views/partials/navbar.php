<header class="core-header bg-gradient-core-dark d-flex justify-content-between align-items-center px-4 py-3 shadow-sm">
    
    <div class="d-flex align-items-center">
        <button class="btn btn-link text-white me-3 d-md-none p-0 border-0" id="sidebarToggle">
            <i class="fas fa-bars fa-lg"></i>
        </button>
        <div class="d-flex flex-column">
            <h6 class="m-0 fw-bold text-uppercase letter-spacing-1 d-none d-sm-block">Gestor Documental CORE Valparaíso</h6>
            <h6 class="m-0 fw-bold text-uppercase d-block d-sm-none">COREGEDOC</h6>
        </div>
    </div>
    
    <div class="d-flex align-items-center gap-3">
        <div class="d-none d-md-flex flex-column text-end lh-1">
            <span class="fw-bold small"><?php echo isset($nombreUsuario) ? htmlspecialchars($nombreUsuario) : 'Usuario'; ?></span>
            <span class="small text-white-50" style="font-size: 0.75rem;">
                <?php 
                // Match para roles
                echo match($tipoUsuario ?? 0) {
                    6 => 'Administrador',
                    2 => 'Secretario Téc.',
                    3 => 'Pdte. Comisión',
                    1 => 'Consejero',
                    default => 'Usuario'
                }; 
                ?>
            </span>
        </div>
        
        <div class="rounded-circle bg-white text-dark d-flex align-items-center justify-content-center shadow-sm" style="width: 40px; height: 40px;">
            <i class="fas fa-user text-dark"></i>
        </div>
    </div>
</header>