<?php
// views/pages/reunion_calendario.php

// 1. Recuperación de datos (MVC)
$reuniones = isset($data['reuniones']) && is_array($data['reuniones']) ? $data['reuniones'] : [];
$isEmbedded = $data['isEmbedded'] ?? false;
$serverToday = date('Y-m-d');

// Determinamos la ruta base para assets (ajusta si tu carpeta public está en otro lado)
$baseUrl = 'public/'; 
?>

<?php if ($isEmbedded): ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
        <style>
            /* FIX CRÍTICO IFRAME: Altura al 100% */
            html, body { height: 100%; margin: 0; padding: 0; overflow: hidden; background-color: #ffffff; }
            .btn-navegacion { display: none !important; }
            /* En modo embedded, quitamos márgenes externos del container */
            .container-fluid { padding: 0 !important; margin: 0 !important; height: 100%; }
        </style>
<?php endif; ?>

<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/main.min.css" rel="stylesheet" />

<style>
    /* --- ESTÉTICA DE TU CÓDIGO ANTIGUO --- */
    
    body { background-color: <?php echo $isEmbedded ? '#fff' : '#f8f9fa'; ?>; }

    #fullCalendarContainer {
        position: relative;
        background-color: #fff;
        border-radius: 5px;
        /* Marca de agua y sombras */
        box-shadow: <?php echo $isEmbedded ? 'none' : '0 2px 5px rgba(0,0,0,0.1)'; ?>;
        overflow: hidden; 
        
    
    }

    #fullCalendarContainer {
        position: relative;
        background-color: #fff;
        border-radius: 5px;
        overflow: hidden;
        /* Quitamos el PHP de aquí y lo movemos a clases abajo */
    }

    /* ESTILO PARA MODO EMBEBIDO (IFRAME) */
    .calendar-embedded {
        width: 100%;
        height: 100vh; /* Ocupa todo el iframe */
        margin: 0;
        padding: 10px;
        display: flex;
        flex-direction: column;
        box-shadow: none; /* Movido aquí desde el box-shadow dinámico */
    }

    /* ESTILO PARA MODO NORMAL (DASHBOARD) */
    .calendar-normal {
        width: 98%;
        min-height: 75vh;
        margin: 20px auto;
        padding: 15px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1); /* Movido aquí */
    }

    /* FullCalendar ocupa el espacio restante en flex */
    .fc {
        position: relative;
        z-index: 1;
        flex-grow: 1; 
        <?php if ($isEmbedded): ?> font-size: 0.85rem; <?php endif; ?>
    }

    /* Marca de agua (Logo de fondo) */
    #fullCalendarContainer::before {
        content: "";
        position: absolute;
        top: 50%;
        left: 50%;
        width: 80%; /* Ajustado para ser responsivo */
        height: 80%;
        /* Asegúrate de que esta ruta sea accesible desde el navegador */
        background: url("public/img/logoCore1.png") no-repeat center center;
        background-size: contain;
        opacity: 0.06;
        transform: translate(-50%, -50%);
        z-index: 0;
        pointer-events: none;
    }

    .calendar-title {
        margin-bottom: 20px;
        text-align: center;
        font-weight: bold;
        color: #343a40;
        <?php if ($isEmbedded) echo 'display: none;'; ?>
    }

    /* Personalización de botones (Estilo Gris) */
    .fc-header-toolbar {
        margin-bottom: 1rem !important;
        flex-wrap: wrap;
        gap: 5px;
    }
    .fc-toolbar-title { font-size: 1.25rem; font-weight: 600; color: #212529; text-transform: capitalize; }
    
    .fc-button {
        border: 0 !important;
        font-size: .9rem;
        font-weight: 500;
        border-radius: .5rem !important;
        background-color: #e9ecef !important;
        color: #212529 !important;
        box-shadow: 0 1px 2px rgba(0,0,0,.08);
        transition: all .15s ease-in-out;
    }
    .fc-button:hover { filter: brightness(0.95); }
    .fc-button.fc-button-active, .fc-today-button.fc-button-active {
        background-color: #212529 !important;
        color: #fff !important;
        box-shadow: 0 2px 4px rgba(0,0,0,.2);
    }

    /* Cabecera de días */
    .fc-col-header-cell { background-color: #f1f3f5; color: #495057; }
    .fc .fc-col-header-cell-cushion {
        text-transform: capitalize;
        text-decoration: none !important;
        font-weight: 700 !important;
        color: #495057;
    }
    
    /* Día actual */
    .fc-day-today {
        background-color: rgba(40, 167, 69, 0.1) !important;
        border: 2px solid #28a745 !important;
    }

    /* Eventos */
    .fc-event { border: 0; border-radius: .3rem; font-size: .75rem; font-weight: 500; cursor: pointer; }
    .fc-daygrid-event { background-color: transparent !important; }
    .fc-daygrid-dot-event .fc-event-title { color: inherit !important; font-weight: 600; }
    .fc-event:hover { filter: brightness(0.9); transform: scale(1.01); }
</style>

<?php if ($isEmbedded): ?>
    </head><body>
<?php endif; ?>

<div class="container-fluid">
    
    <?php if (!$isEmbedded): ?>
        <h3 class="calendar-title mt-4">Calendario General de Reuniones Vigentes</h3>
        <div class="d-flex justify-content-center mb-3">
             <a href="index.php?action=reuniones_dashboard" class="btn btn-sm btn-outline-secondary me-2"><i class="fas fa-list"></i> Lista</a>
             <a href="index.php?action=reunion_form" class="btn btn-sm btn-success"><i class="fas fa-plus"></i> Nueva</a>
        </div>
    <?php endif; ?>

    <?php 
    // Determinar qué clase usar según la variable $isEmbedded
    $containerClass = $isEmbedded ? 'calendar-embedded' : 'calendar-normal';
?>

<div id="fullCalendarContainer" class="<?php echo $containerClass; ?>"></div>


</div>

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/locales/es.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Datos PHP
    const reunionesPHP = <?php echo json_encode($reuniones); ?>;
    const isEmbedded = <?php echo json_encode($isEmbedded); ?>;
    const serverToday = '<?php echo $serverToday; ?>';

    // Funciones auxiliares de tu código antiguo
    function isEmpty(val) { return val === null || val === undefined || String(val).trim() === ''; }
    
    function normalizeDateString(str) {
        if (isEmpty(str)) return null;
        const s = String(str).trim();
        if (/^\d{4}-\d{2}-\d{2}$/.test(s)) return s + 'T00:00:00'; // Parche fechas sin hora
        return s.replace(' ', 'T'); // Parche SQL timestamp
    }

    // Mapeo de colores original
    const colorMap = {
        1: '#54a3ff', 2: '#7dd321', 3: '#ffc107', 4: '#dc3545',
        5: '#6f42c1', 6: '#20c997', 7: '#fd7e14', 8: '#6c757d',
        default: '#17a2b8'
    };

    const calendarEvents = reunionesPHP.map(reunion => {
        const comId = reunion.t_comision_idComision ? parseInt(reunion.t_comision_idComision) : 0;
        
        // Determinar color de TEXTO para eventos tipo "dot" (punto)
        // Usamos el mismo color del punto para el texto
        const myColor = colorMap[comId] || colorMap.default;

        return {
            id: reunion.idReunion,
            title: (reunion.nombreComision || '') + ': ' + (reunion.nombreReunion || ''),
            start: normalizeDateString(reunion.fechaInicioReunion),
            end: normalizeDateString(reunion.fechaTerminoReunion),
            // En FullCalendar v6, 'textColor' pinta el texto
            textColor: myColor,
            backgroundColor: 'transparent', // Fondo transparente estilo "dot"
            borderColor: 'transparent',
            // URL corregida al formato MVC nuevo
            url: `index.php?action=reunion_editar&id=${reunion.idReunion}`
        };
    }).filter(e => e.start);

    const calendarEl = document.getElementById('fullCalendarContainer');

    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: 'es',
        firstDay: 1,
        height: isEmbedded ? '100%' : 'auto',
        expandRows: isEmbedded ? true : false,
        
        headerToolbar: {
            left: isEmbedded ? 'prev,next' : 'prev,next today',
            center: 'title',
            right: isEmbedded ? 'dayGridMonth,listMonth' : 'dayGridMonth,timeGridWeek'
        },
        buttonText: { today: 'Hoy', month: 'Mes', week: 'Semana', list: 'Lista' },
        
        events: calendarEvents,
        dayMaxEvents: 3,
        navLinks: true,

        // Manejo de Clics (Soporte iframe)
        eventClick: function(info) {
            info.jsEvent.preventDefault();
            if (info.event.url) {
                if (isEmbedded) {
                    window.parent.location.href = info.event.url;
                } else {
                    window.location.href = info.event.url;
                }
            }
        },

        // Tu lógica para Capitalizar el Título del Mes
        datesSet: function(info) {
            const titleEl = document.querySelector('.fc-toolbar-title');
            if (titleEl) {
                // Capitalizar primera letra con CSS o JS simple
                titleEl.style.textTransform = 'capitalize';
            }
        }
    });

    // Renderizar
    setTimeout(() => {
        calendar.render();
        calendar.updateSize();
        // Ir a la fecha del servidor si es necesario
        if(serverToday) calendar.gotoDate(serverToday);
    }, 100);
});
</script>

<?php if ($isEmbedded): ?>
    </body></html>
<?php endif; ?>