<?php
// Recuperamos los datos pasados por el controlador
$reuniones = $data['reuniones'] ?? [];
$serverToday = date('Y-m-d');
?>

<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/main.min.css" rel="stylesheet" />
<style>
    #fullCalendarContainer {
        background-color: #fff;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        min-height: 75vh;
    }
    
    .fc-header-toolbar {
        flex-wrap: wrap;
        gap: 10px;
    }

    .fc-event {
        cursor: pointer;
        transition: transform 0.1s;
    }
    .fc-event:hover {
        transform: scale(1.02);
    }
    
    /* Día actual resaltado */
    .fc-day-today {
        background-color: rgba(28, 136, 191, 0.05) !important;
    }

    .calendar-title {
        font-weight: 700;
        color: #333;
        border-left: 5px solid #1C88BF;
        padding-left: 15px;
    }
</style>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="calendar-title m-0">Calendario de Sesiones</h3>
        
        <div>
            <a href="index.php?action=reuniones_dashboard" class="btn btn-outline-secondary me-2">
                <i class="fas fa-list"></i> Ver Lista
            </a>
            <a href="index.php?action=reunion_form" class="btn btn-success">
                <i class="fas fa-plus"></i> Nueva Reunión
            </a>
        </div>
    </div>

    <div id="fullCalendarContainer"></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Datos desde PHP (inyectados limpiamente)
    const reunionesData = <?php echo json_encode($reuniones); ?>;
    const serverToday = '<?php echo $serverToday; ?>';

    // Transformar datos de BD al formato de FullCalendar
    const eventos = reunionesData.map(r => {
        // Colores por comisión (puedes personalizar esto)
        const colores = {
            1: '#0d6efd', // Azul
            2: '#198754', // Verde
            3: '#ffc107', // Amarillo
            4: '#dc3545', // Rojo
            5: '#6610f2', // Morado
            default: '#6c757d' // Gris
        };
        
        const colorEvento = colores[r.t_comision_idComision] || colores.default;
        const esMixta = r.t_comision_idComision_mixta ? ' (Mixta)' : '';

        return {
            id: r.idReunion,
            title: (r.nombreComision || 'Comisión') + esMixta + ': ' + r.nombreReunion,
            start: r.fechaInicioReunion,
            end: r.fechaTerminoReunion, // FullCalendar maneja bien los nulls
            backgroundColor: colorEvento,
            borderColor: colorEvento,
            // Nuevo enlace al editor refactorizado
            url: `index.php?action=reunion_editar&id=${r.idReunion}`
        };
    });

    const calendarEl = document.getElementById('fullCalendarContainer');

    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: 'es',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,listMonth'
        },
        buttonText: {
            today: 'Hoy',
            month: 'Mes',
            week: 'Semana',
            list: 'Lista'
        },
        navLinks: true, 
        editable: false,
        dayMaxEvents: true, 
        events: eventos,
        
        // Al hacer clic, redirigir
        eventClick: function(info) {
            info.jsEvent.preventDefault(); // Evitar comportamiento default del link
            if (info.event.url) {
                window.location.href = info.event.url;
            }
        },
        
        // Tooltip simple al pasar el mouse
        eventMouseEnter: function(info) {
            info.el.title = info.event.title;
        }
    });

    calendar.render();
});
</script>