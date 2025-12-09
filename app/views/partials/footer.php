<style>
    /* --- ESTILOS ESPECÍFICOS PARA EL FOOTER --- */
    .footer-social-link {
        color: #6c757d;
        transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        display: inline-block;
    }
    .footer-social-link:hover {
        transform: scale(1.3) rotate(5deg);
    }
    .footer-social-link.facebook:hover { color: #1877F2 !important; }
    .footer-social-link.twitter:hover { color: #000000 !important; }
    .footer-social-link.instagram:hover { color: #E1306C !important; }
    .footer-social-link.youtube:hover { color: #FF0000 !important; }

    .btn-footer-inst {
        background-color: transparent;
        border: 1px solid #dee2e6;
        color: #6c757d;
        font-weight: 500;
        transition: all 0.2s ease-in-out;
        text-align: left;
        width: 100%;
        display: flex;
        align-items: center;
        /* Evita desbordes de texto */
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .btn-footer-inst:hover {
        background-color: #f7931e;
        border-color: #f7931e;
        color: white;
        transform: translateX(5px);
    }
    
    .doc-icon { 
        width: 24px; 
        text-align: center; 
        margin-right: 8px;
    }
</style>

<footer class="bg-white border-top mt-auto footer-core">
    <div class="container-fluid px-4 py-4">
        <div class="row gy-4">
            
            <div class="col-md-6 col-lg-3">
                <div class="d-flex align-items-center mb-3">
                    <h6 class="fw-bold text-primary mb-0 text-uppercase">Acerca del Consejo</h6>
                </div>
                <p class="text-muted small mb-2">
                    La administración superior de la Región de Valparaíso radica en el Gobierno Regional, 
                    que tiene por objetivo el desarrollo social, cultural y económico.
                </p>
            </div>

            <div class="col-md-6 col-lg-2">
                <h6 class="fw-bold text-dark mb-3">Sitios Oficiales</h6>
                <div class="d-flex flex-column gap-2">
                    <a href="https://www.gobiernovalparaiso.cl" target="_blank" class="btn btn-sm btn-footer-inst rounded-pill px-3">
                        <i class="fas fa-building doc-icon"></i>GORE
                    </a>
                    <a href="http://www.corevalparaiso.cl" target="_blank" class="btn btn-sm btn-footer-inst rounded-pill px-3">
                        <i class="fas fa-landmark doc-icon"></i>CCORE
                    </a>
                </div>
            </div>

            <div class="col-md-6 col-lg-3">
                <h6 class="fw-bold text-dark mb-3">Contacto</h6>
                <ul class="list-unstyled small text-muted mb-0">
                    <li class="mb-2">
                        <i class="fas fa-map-marker-alt fa-fw me-2 text-primary"></i> 
                        Condell 1530, piso 3, Valparaíso
                    </li>
                    <li class="mb-2">
                        <i class="fas fa-phone fa-fw me-2 text-primary"></i>
                        (32) 2655260 — (32) 2655262
                    </li>
                    <li class="mb-2">
                        <i class="fas fa-envelope fa-fw me-2 text-primary"></i>
                        contacto@gorevalparaiso.cl
                    </li>
                </ul>
            </div>

            <div class="col-md-6 col-lg-2">
                <h6 class="fw-bold text-dark mb-3">Síguenos</h6>
                <div class="d-flex gap-3 flex-wrap">
                    <a href="https://www.facebook.com/GOREValparaiso/" class="footer-social-link facebook fs-4" title="Facebook">
                        <i class="fab fa-facebook"></i>
                    </a>
                    <a href="https://x.com/GOREValparaiso" class="footer-social-link twitter fs-4" title="X (Twitter)">
                        <i class="fa-solid fa-x"></i> </a>
                    <a href="https://www.instagram.com/gorevalparaiso/?hl=en" class="footer-social-link instagram fs-4" title="Instagram">
                        <i class="fab fa-instagram"></i>
                    </a>
                    <a href="https://www.youtube.com/@gorevalparaiso_" class="footer-social-link youtube fs-4" title="YouTube">
                        <i class="fab fa-youtube"></i>
                    </a>
                </div>
            </div>

            <div class="col-md-6 col-lg-2">
                <h6 class="fw-bold text-dark mb-3">Ayuda</h6>
                <div class="d-flex flex-column gap-2">
                    <a href="public/docs/Reglamento_Interno_CORE.pdf" target="_blank" class="btn btn-sm btn-footer-inst rounded-pill px-3" title="Descargar Reglamento">
                        <i class="fas fa-file-contract text-danger doc-icon"></i>Reglamento
                    </a>
                    <a href="public/docs/Manuales_Coregedoc.pdf" target="_blank" class="btn btn-sm btn-footer-inst rounded-pill px-3" title="Descargar Manuales">
                        <i class="fas fa-book-open text-primary doc-icon"></i>Manuales
                    </a>
                </div>
            </div>

        </div>
    </div>

    <div class="bg-light border-top py-3">
        <div class="container-fluid px-4 d-flex flex-column flex-md-row justify-content-between align-items-center small text-muted">
            <div class="mb-2 mb-md-0">
                &copy; <?php echo date('Y'); ?> <strong>Consejo Regional de Valparaíso</strong>. Todos los derechos reservados.
            </div>
        </div>
    </div>
</footer>