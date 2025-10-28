<?php
// views/pages/home.php

// NO necesitas session_start() aquí.
// NO necesitas verificar la sesión aquí (menu.php ya lo hizo).
// NO incluyas menu.php aquí.

?>

<div class="container mt-4">
    <h4>Nuestra querida Región</h4>

    <div id="carouselZonasRegion" class="carousel slide carousel-fade" data-bs-ride="carousel" data-bs-interval="5000">

        <div class="carousel-indicators">
            <button type="button" data-bs-target="#carouselZonasRegion" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Zona 1"></button>
            <button type="button" data-bs-target="#carouselZonasRegion" data-bs-slide-to="1" aria-label="Zona 2"></button>
            <button type="button" data-bs-target="#carouselZonasRegion" data-bs-slide-to="2" aria-label="Zona 3"></button>
            <button type="button" data-bs-target="#carouselZonasRegion" data-bs-slide-to="3" aria-label="Zona 4"></button>
        </div>

        <div class="carousel-inner">
            <div class="carousel-item active">
                <img src="/corevota/public/img/zonas_region/imagen_zona_1.jpg" class="d-block w-100 carousel-image-transparent" style="max-height: 400px; object-fit: cover;" alt="Descripción Zona 1">
                <div class="carousel-caption d-none d-md-block">
                    <h5>PROVINCIA DE VALPARAÍSO</h5>
                </div>
            </div>
            <div class="carousel-item">
                <img src="/corevota/public/img/zonas_region/imagen_zona_2.jpg" class="d-block w-100 carousel-image-transparent" style="max-height: 400px; object-fit: cover;" alt="Descripción Zona 2">
                <div class="carousel-caption d-none d-md-block">
                    <h5>PROVINCIA DE MARGA MARGA</h5>
                </div>
            </div>
            <div class="carousel-item">
                <img src="/corevota/public/img/zonas_region/imagen_zona_3.jpg" class="d-block w-100 carousel-image-transparent" style="max-height: 400px; object-fit: cover;" alt="Descripción Zona 3">
                <div class="carousel-caption d-none d-md-block">
                    <h5>PROVINCIA DE QUILLOTA</h5>
                </div>
            </div>
            <div class="carousel-item">
                <img src="/corevota/public/img/zonas_region/imagen_zona_4.jpg" class="d-block w-100 carousel-image-transparent" style="max-height: 400px; object-fit: cover;" alt="Descripción Zona 4">
                <div class="carousel-caption d-none d-md-block">
                    <h5>PROVINCIA DE SAN ANTONIO</h5>
                </div>
            </div>
        </div>

        <button class="carousel-control-prev" type="button" data-bs-target="#carouselZonasRegion" data-bs-slide="prev">
            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Anterior</span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#carouselZonasRegion" data-bs-slide="next">
            <span class="carousel-control-next-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Siguiente</span>
        </button>
    </div>
</div>

<hr>

