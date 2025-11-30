<div class="container-fluid mt-4">
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="text-dark border-bottom pb-2">
                <i class="fas fa-tasks text-primary me-2"></i> Gestión de Reuniones
            </h2>
            <p class="text-muted">Seleccione una opción para gestionar las sesiones del Consejo.</p>
        </div>
    </div>

    <div class="row g-4">
        
        <div class="col-md-4">
            <div class="card h-100 shadow-sm border-0 hover-card">
                <div class="card-body text-center py-5">
                    <div class="mb-3 text-primary">
                        <i class="far fa-calendar-alt fa-4x"></i>
                    </div>
                    <h4 class="card-title fw-bold">Calendario Visual</h4>
                    <p class="card-text text-muted">Vea la programación mensual de reuniones en formato calendario.</p>
                    <a href="index.php?action=reunion_calendario" class="btn btn-outline-primary stretched-link">
                        Ver Calendario
                    </a>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card h-100 shadow-sm border-0 hover-card">
                <div class="card-body text-center py-5">
                    <div class="mb-3 text-success">
                        <i class="fas fa-list-ul fa-4x"></i>
                    </div>
                    <h4 class="card-title fw-bold">Listado de Reuniones</h4>
                    <p class="card-text text-muted">Revise, edite, elimine o <b>inicie</b> las reuniones programadas.</p>
                    <a href="index.php?action=reunion_listado" class="btn btn-outline-success stretched-link">
                        Ir al Listado
                    </a>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card h-100 shadow-sm border-0 hover-card">
                <div class="card-body text-center py-5">
                    <div class="mb-3 text-warning">
                        <i class="fas fa-plus-circle fa-4x"></i>
                    </div>
                    <h4 class="card-title fw-bold">Programar Reunión</h4>
                    <p class="card-text text-muted">Cree una nueva citación para Comisiones o Pleno.</p>
                    <a href="index.php?action=reunion_form" class="btn btn-outline-warning text-dark stretched-link">
                        Nueva Reunión
                    </a>
                </div>
            </div>
        </div>

    </div>
</div>

<style>
    /* Efecto Hover para que se sienta interactivo */
    .hover-card { transition: transform 0.2s; cursor: pointer; }
    .hover-card:hover { transform: translateY(-5px); box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important; }
</style>