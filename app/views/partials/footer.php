<style>
    /* --- ESTILOS GENERALES Y LIMPIEZA --- */
    .footer-core {
        background-color: #ffffff;
        font-family: system-ui, -apple-system, sans-serif;
    }

    /* Títulos con la línea naranja */
    .footer-heading {
        color: #003366;
        font-weight: 700;
        font-size: 0.9rem;
        letter-spacing: 0.5px;
        margin-bottom: 1.2rem;
        text-transform: uppercase;
        position: relative;
    }

    .footer-heading::after {
        content: '';
        display: block;
        width: 30px;
        height: 2px;
        background: #f7931e;
        margin-top: 6px;
    }

    /* Texto descriptivo */
    .footer-text {
        color: #6c757d;
        line-height: 1.6;
        font-size: 0.85rem;
        text-align: left;
    }

    /* Botones de Enlaces */
    .btn-footer-link {
        display: flex;
        align-items: center;
        background-color: #f8f9fa;
        color: #495057;
        border: 1px solid transparent;
        padding: 6px 12px;
        font-size: 0.85rem;
        font-weight: 500;
        transition: all 0.3s ease;
        width: 100%;
        margin-bottom: 8px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .btn-footer-link:hover {
        background-color: #0071bc;
        color: #ffffff;
        transform: translateX(3px);
    }

    .btn-footer-link i {
        margin-right: 10px;
        width: 16px;
        text-align: center;
    }

    /* Lista de contacto */
    .contact-list li {
        display: flex;
        margin-bottom: 12px;
        font-size: 0.85rem;
        color: #6c757d;
        line-height: 1.4;
    }

    /* Iconos naranja de contacto */
    .contact-icon {
        color: #f7931e;
        margin-right: 10px;
        min-width: 16px;
        margin-top: 3px;
        flex-shrink: 0; 
    }

    /* --- REDES SOCIALES (Horizontal) --- */
    .social-grid {
        display: flex;
        flex-direction: row;
        gap: 10px;
    }

    .social-btn {
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        background-color: #f1f3f5;
        color: #495057;
        transition: all 0.3s ease;
        text-decoration: none;
        font-size: 1.1rem;
        flex-shrink: 0;
    }

    .social-btn:hover {
        color: white !important;
        transform: translateY(-3px);
    }

    /* Colores hover marcas */
    .social-btn.facebook:hover { background-color: #1877F2; }
    .social-btn.x:hover { background-color: #000000; }
    .social-btn.instagram:hover { background-color: #E1306C; }
    .social-btn.youtube:hover { background-color: #FF0000; }

    /* --- LOGO LATERAL AJUSTADO --- */
    .footer-crest-side {
        width: 65px; /* Tamaño equilibrado */
        height: auto;
        margin-left: 15px; /* Separación del texto */
        /* Eliminamos align-self para controlarlo con flexbox en el HTML */
    }
</style>

<footer class="border-top mt-auto footer-core">
    <div class="container-fluid px-5 py-5">
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-5 gy-5 gx-4">

            <div class="col">
                <h6 class="footer-heading">Acerca del Consejo</h6>
                <p class="footer-text">
                    Es el órgano colegiado que representa a la ciudadanía, aprobando inversiones en infraestructura, salud y educación, además de fiscalizar al Gobernador Regional.
                </p>
            </div>

            <div class="col">
                <h6 class="footer-heading">Sitios Oficiales</h6>
                <div class="d-flex flex-column">
                    <a href="https://www.gobiernovalparaiso.cl" target="_blank" class="btn btn-sm btn-footer-link rounded-pill">
                        <i class="fas fa-building"></i> GORE
                    </a>
                    <a href="http://www.corevalparaiso.cl" target="_blank" class="btn btn-sm btn-footer-link rounded-pill">
                        <i class="fas fa-landmark"></i> CORE
                    </a>
                </div>
            </div>

            <div class="col">
                <h6 class="footer-heading">Síguenos</h6>
                <div class="social-grid">
                    <a href="https://www.facebook.com/GOREValparaiso/" class="social-btn facebook" title="Facebook" target="_blank">
                        <i class="fab fa-facebook-f"></i>
                    </a>
                    <a href="https://x.com/GOREValparaiso" class="social-btn x" title="X" target="_blank">
                        <i class="fa-solid fa-x"></i>
                    </a>
                    <a href="https://www.instagram.com/gorevalparaiso/?hl=en" class="social-btn instagram" title="Instagram" target="_blank">
                        <i class="fab fa-instagram"></i>
                    </a>
                    <a href="https://www.youtube.com/@gorevalparaiso_" class="social-btn youtube" title="YouTube" target="_blank">
                        <i class="fab fa-youtube"></i>
                    </a>
                </div>
            </div>

            <div class="col">
                <h6 class="footer-heading">Ayuda</h6>
                <div class="d-flex flex-column">
                    <a href="public/docs/Reglamento_Interno_CORE.pdf" target="_blank" class="btn btn-sm btn-footer-link rounded-pill">
                        <i class="fas fa-file-pdf text-danger"></i> Reglamento
                    </a>
                    <a href="public/docs/Manuales_Coregedoc.pdf" target="_blank" class="btn btn-sm btn-footer-link rounded-pill">
                        <i class="fas fa-book-open text-primary"></i> Manuales
                    </a>
                </div>
            </div>

            <div class="col">
                <h6 class="footer-heading">Contacto</h6>
                
                <div class="d-flex align-items-center justify-content-between">
                    
                    <ul class="list-unstyled contact-list mb-0">
                        <li>
                            <i class="fas fa-map-marker-alt contact-icon"></i>
                            <span>Condell 1530, Piso 3,<br>Valparaíso</span>
                        </li>
                        <li>
                            <i class="fas fa-phone contact-icon"></i>
                            <span>(32) 2655260</span>
                        </li>
                        <li>
                            <i class="fas fa-envelope contact-icon"></i>
                            <span style="word-break: break-all;">contacto@gorevalparaiso.cl</span>
                        </li>
                    </ul>

                   
                </div>
            </div>

        </div>
    </div>

    <div class="bg-light border-top py-3">
        <div class="container-fluid px-5 d-flex flex-column flex-md-row justify-content-between align-items-center small text-muted">
            <div class="mb-2 mb-md-0">
                &copy; <?php echo date('Y'); ?> <strong>CORE Valparaíso</strong>.
            </div>

        </div>
    </div>
</footer>