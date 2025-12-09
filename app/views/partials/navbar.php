<style>
    .bg-gradient-core-dark {
        /* Fallback para navegadores antiguos */
        background: #000000; 
        
        /* Degradado de 5 colores: Azul -> Verde -> Amarillo -> Gris -> Negro */
        background: linear-gradient(
            90deg, 
            #0071bc 20%,   /* Azul (Inicio) */
            #00a650 40%,   /* Verde */
            #f7931e 60%,   /* Amarillo */
            #6D6A75 80%,   /* Gris */
            #000000 100%   /* Negro (Fin) */
        );
        
        border-bottom: 1px solid #333;
    }
</style>

<nav class="navbar navbar-expand-lg navbar-dark bg-gradient-core-dark shadow fixed-top">
    <div class="container-fluid">
        
        <button class="btn btn-link text-white me-3" id="sidebarToggle">
            <i class="fas fa-bars"></i>
        </button>

        <a class="navbar-brand fw-bold text-white" href="index.php?action=home">
            </i>CORE<span class="text-white-50"> REGIÓN DE VALPARAÍSO</span>
        </a>

        <div class="ms-auto d-flex align-items-center">
            
            <div class="dropdown">
                <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle text-white" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <?php 
                        // Lógica de imagen de perfil segura
                        $imgPerfil = !empty($_SESSION['rutaImagenPerfil']) && file_exists($_SESSION['rutaImagenPerfil']) 
                            ? $_SESSION['rutaImagenPerfil'] 
                            : 'public/img/user_placeholder.png'; 
                    ?>
                    <img src="<?php echo $imgPerfil; ?>" alt="Perfil" width="32" height="32" class="rounded-circle me-2 border border-white" style="object-fit: cover;">
                    
                    <span class="d-none d-md-inline fw-medium">
                        <?php 
                            echo htmlspecialchars(($_SESSION['pNombre'] ?? 'Usuario') . ' ' . ($_SESSION['aPaterno'] ?? '')); 
                        ?>
                    </span>
                </a>
                
               <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="userDropdown">
                    <li>
                        <div class="dropdown-header text-center">
                            <strong><?php echo htmlspecialchars($_SESSION['pNombre'] ?? 'Usuario'); ?></strong><br>
                            <small class="text-muted">
                                <?php echo htmlspecialchars($_SESSION['email'] ?? $_SESSION['correo'] ?? 'Sin correo'); ?>
                            </small>
                        </div>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    
                    <li>
                        <a class="dropdown-item" href="index.php?action=perfil">
                            <i class="fas fa-user fa-fw me-2 text-primary"></i> Ver mi Perfil
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="index.php?action=configuracion">
                            <i class="fas fa-cog fa-fw me-2 text-secondary"></i> Configuración
                        </a>
                    </li>
                    
                    <li><hr class="dropdown-divider"></li>
                    
                    <li>
                        <a class="dropdown-item text-danger" href="index.php?action=logout">
                            <i class="fas fa-sign-out-alt fa-fw me-2"></i> Cerrar Sesión
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</nav>