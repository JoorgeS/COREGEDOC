<?php
// app/views/minutas/dashboard.php

// Recuperamos el rol desde la data
$tipoUsuario = $data['usuario']['rol'];

// Definición de IDs de roles
$ROL_CONSEJERO = 1;
$ROL_SECRETARIO = 2;
$ROL_PRESIDENTE = 3;
$ROL_ADMIN = 6;

// Paleta de Colores Institucional (Variables PHP para fácil uso inline si se requiere)
$c_naranja_osc = '#e87b00';
$c_naranja_claro = '#f7931e';
$c_verde = '#00a650';
$c_azul = '#0071bc';
$c_negro = '#000000';
$c_gris = '#808080';
?>

<style>
    /* --- PALETA INSTITUCIONAL --- */
    :root {
        --inst-azul: #0071bc;
        --inst-verde: #00a650;
        --inst-naranja: #f7931e;
        --inst-naranja-osc: #e87b00;
        --inst-gris: #808080;
        --inst-negro: #000000;
    }

    /* Textos */
    .text-inst-azul { color: var(--inst-azul) !important; }
    .text-inst-naranja { color: var(--inst-naranja) !important; }
    .text-inst-verde { color: var(--inst-verde) !important; }
    .text-inst-gris { color: var(--inst-gris) !important; }

    /* Tarjetas Institucionales */
    .dashboard-card {
        background: #fff;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        text-align: center;
        padding: 2rem;
        box-shadow: 0 4px 6px rgba(0,0,0,0.05);
    }

    .dashboard-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 12px 24px rgba(0,0,0,0.15);
        border-color: transparent;
    }

    /* Barras laterales de color */
    .card-pendiente { border-left: 6px solid var(--inst-naranja); }
    .card-aprobada { border-left: 6px solid var(--inst-verde); }
    .card-seguimiento { border-left: 6px solid var(--inst-azul); }

    /* Iconos con círculos */
    .icon-circle {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 1.5rem;
        transition: transform 0.3s ease;
    }

    .dashboard-card:hover .icon-circle {
        transform: scale(1.1);
    }

    /* Colores de fondos sutiles para iconos */
    .bg-icon-naranja { background-color: rgba(247, 147, 30, 0.1); color: var(--inst-naranja); }
    .bg-icon-verde { background-color: rgba(0, 166, 80, 0.1); color: var(--inst-verde); }
    .bg-icon-azul { background-color: rgba(0, 113, 188, 0.1); color: var(--inst-azul); }

    /* Botones Institucionales */
    .btn-inst {
        border-radius: 50px;
        padding: 8px 25px;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.85rem;
        letter-spacing: 0.5px;
        transition: all 0.3s;
        margin-top: auto;
    }

    .btn-inst-naranja {
        background-color: transparent;
        border: 2px solid var(--inst-naranja);
        color: var(--inst-naranja);
    }
    .btn-inst-naranja:hover {
        background-color: var(--inst-naranja);
        color: white;
    }

    .btn-inst-verde {
        background-color: transparent;
        border: 2px solid var(--inst-verde);
        color: var(--inst-verde);
    }
    .btn-inst-verde:hover {
        background-color: var(--inst-verde);
        color: white;
    }

    .btn-inst-azul {
        background-color: transparent;
        border: 2px solid var(--inst-azul);
        color: var(--inst-azul);
    }
    .btn-inst-azul:hover {
        background-color: var(--inst-azul);
        color: white;
    }
    
    /* Botón Volver */
    .btn-inst-volver {
        color: var(--inst-gris);
        border: 2px solid var(--inst-gris);
        background: transparent;
        border-radius: 50px;
        font-weight: 600;
        padding: 6px 20px;
        font-size: 0.85rem;
        text-decoration: none;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
    }
    .btn-inst-volver:hover {
        background: var(--inst-gris);
        color: white;
        transform: translateX(-3px);
    }

    /* Badge de Rol */
    .badge-rol {
        background-color: var(--inst-azul);
        color: white;
        font-weight: 500;
        padding: 8px 15px;
        border-radius: 4px;
        font-size: 0.9rem;
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    }
</style>

<div class="container-fluid py-5">
    
    <!-- Encabezado Institucional -->
    <div class="row mb-5 align-items-center">
        <div class="col-md-8">
            <h2 class="fw-bold mb-1" style="color: var(--inst-negro);">
                <i class="fas fa-folder-open me-2 text-inst-azul"></i> Panel de Gestión Documental
            </h2>
            <p class="text-inst-gris mb-0 fs-5">Consejo Regional de Valparaíso</p>
        </div>
        <div class="col-md-4 text-md-end mt-3 mt-md-0">
            <div class="d-flex flex-column align-items-end gap-2">
               
                
                <!-- Botón Volver -->
                <a href="index.php?action=home" class="btn-inst-volver">
                    <i class="fas fa-arrow-left me-2"></i> Volver
                </a>
            </div>
        </div>
    </div>

    <!-- Grid de Opciones -->
    <div class="row g-4 justify-content-center">
        
        <!-- TARJETA 1: MINUTAS PENDIENTES (Naranja) -->
        <!-- Lógica de Permisos: NO visible para Consejeros (ID 1) -->
        <?php if (in_array($tipoUsuario, [$ROL_SECRETARIO, $ROL_PRESIDENTE, $ROL_ADMIN])): ?>
            <div class="col-md-6 col-xl-4">
                <a href="index.php?action=minutas_pendientes" class="text-decoration-none">
                    <div class="dashboard-card card-pendiente">
                        <div class="icon-circle bg-icon-naranja">
                            <i class="fas fa-file-signature fa-2x"></i>
                        </div>
                        <h4 class="fw-bold text-inst-negro mb-3">Minutas Pendientes</h4>
                        <p class="text-inst-gris mb-4">
                            <?php if($tipoUsuario == $ROL_SECRETARIO): ?>
                                Gestión de borradores, control de versiones y envío a firma.
                            <?php else: ?>
                                Revisión y firma electrónica de actas asignadas a su comisión.
                            <?php endif; ?>
                        </p>
                        <button class="btn btn-inst btn-inst-naranja">
                            Gestionar <i class="fas fa-arrow-right ms-2"></i>
                        </button>
                    </div>
                </a>
            </div>
        <?php endif; ?>

        <!-- TARJETA 2: MINUTAS APROBADAS (Verde) -->
        <!-- Visible para TODOS -->
        <div class="col-md-6 col-xl-4">
            <a href="index.php?action=minutas_aprobadas" class="text-decoration-none">
                <div class="dashboard-card card-aprobada">
                    <div class="icon-circle bg-icon-verde">
                        <i class="fas fa-archive fa-2x"></i>
                    </div>
                    <h4 class="fw-bold text-inst-negro mb-3">Histórico de Minutas</h4>
                    <p class="text-inst-gris mb-4">
                        Repositorio oficial de minutas aprobadas. Búsqueda y descarga de documentos firmados.
                    </p>
                    <button class="btn btn-inst btn-inst-verde">
                        Consultar <i class="fas fa-search ms-2"></i>
                    </button>
                </div>
            </a>
        </div>

        <!-- TARJETA 3: SEGUIMIENTO (Azul) -->
        <!-- Exclusivo Administrador -->
        <?php if ($tipoUsuario == $ROL_ADMIN): ?>
            <div class="col-md-6 col-xl-4">
                <a href="index.php?action=seguimiento_general" class="text-decoration-none">
                    <div class="dashboard-card card-seguimiento">
                        <div class="icon-circle bg-icon-azul">
                            <i class="fas fa-chart-line fa-2x"></i>
                        </div>
                        <h4 class="fw-bold text-inst-negro mb-3">Monitor de Gestión</h4>
                        <p class="text-inst-gris mb-4">
                            Tablero de control global, métricas de rendimiento y auditoría de procesos.
                        </p>
                        <button class="btn btn-inst btn-inst-azul">
                            Ver Reportes <i class="fas fa-chart-pie ms-2"></i>
                        </button>
                    </div>
                </a>
            </div>
        <?php endif; ?>

    </div>
</div>