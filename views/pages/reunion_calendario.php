<?php
// reunion_calendario.php
require_once __DIR__ . '/../../controllers/ReunionController.php';

$reuniones = [];
if (class_exists('ReunionManager')) {
    try {
        $manager = new ReunionManager();
        $result = $manager->getReunionesCalendarData();
        if ($result['status'] === 'success') {
            $reuniones = $result['data'];
        }
    } catch (Throwable $e) {
        error_log("Error al cargar datos del calendario: " . $e->getMessage());
    }
}

// Fecha actual del servidor (YYYY-MM-DD)
$serverToday = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Calendario de Reuniones</title>
    <link href="/coregedoc/public/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/main.min.css" rel="stylesheet" />

    <style>
        body { background-color: #f8f9fa; }
        #fullCalendarContainer {
            position: relative;
            width: 95%;
            min-height: 70vh;          /* altura mínima */
            height: auto;              /* altura flexible */
            margin: 20px auto;
            background-color: #fff;
            padding: 15px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            overflow: visible;          /* deja que crezca si necesita más */
            }

            .fc {
            position: relative;
            z-index: 1;
            min-height: 60vh;           /* asegura que tenga espacio base */
            }

        #fullCalendarContainer::before {
            content: "";
            position: absolute;
            top: 50%;
            left: 50%;
            width: 1200px;
            height: 1200px;
            background: url("/coregedoc/public/img/logoCore1.png") no-repeat center center;
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
        }

        .fc-header-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem !important;
        }
        .fc-toolbar-title { font-size: 1.25rem; font-weight: 600; color: #212529; }
        .fc-toolbar-chunk { display: flex; align-items: center; gap: .5rem; }

        .fc-button {
            border: 0 !important;
            font-size: .9rem;
            font-weight: 500;
            border-radius: .5rem !important;
            padding: .5rem .75rem !important;
            background-color: #e9ecef !important;
            color: #212529 !important;
            box-shadow: 0 1px 2px rgba(0,0,0,.08);
            transition: all .15s ease-in-out;
        }
        .fc-button:hover { filter: brightness(0.95); }
        .fc-button.fc-button-active,
        .fc-today-button.fc-button-active {
            background-color: #212529 !important;
            color: #fff !important;
            box-shadow: 0 2px 4px rgba(0,0,0,.2);
        }
        .fc-button-group { display: flex; gap: .5rem; }
        .fc-prev-button, .fc-next-button, .fc-today-button { min-width: 2.5rem; text-align: center; }

        .fc-col-header-cell {
            background-color: #f1f3f5;
            color: #495057;
            font-weight: 500;
            font-size: .8rem;
        }
        /* Días de semana en negrita y sin subrayado */
        .fc .fc-col-header-cell-cushion {
            text-transform: capitalize;
            text-decoration: none !important;
            font-weight: 700 !important;
            color: #495057;
        }

        .fc-daygrid-day-number { font-size: .8rem; font-weight: 500; color: #495057; }

        /* Día actual resaltado (verde) */
            .fc-day-today {
            background-color: rgba(40, 167, 69, 0.2) !important; /* verde translúcido */
            border: 2px solid #28a745 !important; /* verde Bootstrap */
            border-radius: 8px !important;
        }
        
        
        .fc-event, .fc-daygrid-event {
            border-radius: .5rem;
            border: 0;
            font-size: .75rem;
            font-weight: 500;
            padding: .25rem .4rem;
        }
        .fc-event:hover { filter: brightness(0.9); }

        .fc-daygrid-event {
            background-color: transparent !important;
            border: none !important;
            color: #0d6efd !important; /* color del texto (Bootstrap azul) */
            font-weight: 500;
            padding: 0 !important;
            }

            .fc-daygrid-dot-event {
            display: flex !important;
            align-items: center !important;
            }

            /* Hover más sutil */
            .fc-daygrid-event:hover {
            text-decoration: underline;
            filter: brightness(0.9);
        }
    </style>
</head>
<body>

<div class="container mt-4">
    <h3 class="calendar-title">Reuniones Vigentes</h3>
    <div id="fullCalendarContainer"></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Datos desde PHP
    const reunionesPHP = <?php echo json_encode($reuniones ?? []); ?>;
    // Forzamos hora “neutral” para evitar desfaces por TZ
    const serverToday = '<?php echo $serverToday; ?>T12:00:00';

    function isEmpty(val) { return val === null || val === undefined || String(val).trim() === ''; }

    function normalizeDateString(str) {
        if (isEmpty(str)) return null;
        const s = String(str).trim();
        if (/^\d{4}-\d{2}-\d{2}$/.test(s)) return s + 'T00:00:00';
        if (/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}(:\d{2})?$/.test(s)) return s.replace(' ', 'T');
        return s;
    }

    function looksAllDay(str) {
        return false; // fuerza todos los eventos a mostrarse con color sólido
        }


    const calendarEvents = (reunionesPHP || []).map(reunion => {
        const colorMap = {
            1: '#54a3ff', 2: '#7dd321', 3: '#ffc107', 4: '#dc3545',
            5: '#6f42c1', 6: '#20c997', 7: '#fd7e14', 8: '#6c757d'
        };
        const commissionId = reunion.t_comision_idComision ? parseInt(reunion.t_comision_idComision) : 1;

        const startRaw = reunion.fechaInicioReunion;
        const endRaw   = reunion.fechaTerminoReunion;

        const startStr = normalizeDateString(startRaw);
        const endStr   = normalizeDateString(endRaw);
        const allDay = looksAllDay(startRaw) && (isEmpty(endRaw) || looksAllDay(endRaw));
        
        const event = {
            id: reunion.idReunion,
            title: (reunion.nombreComision || '') + (reunion.nombreReunion ? ': ' + reunion.nombreReunion : ''),
            start: startStr,
            allDay: allDay,
            backgroundColor: colorMap[commissionId] || '#17a2b8',
            borderColor: colorMap[commissionId] || '#17a2b8',
            url: `/coregedoc/views/pages/crearReunion.php?action=edit&id=${reunion.idReunion}`
        };
        if (!isEmpty(endStr)) event.end = endStr;

        return event;
    }).filter(e => !isEmpty(e.start));

    const calendarEl = document.getElementById('fullCalendarContainer');

    // Inicializar dentro de try/catch para que, ante cualquier error, no “desaparezca” la vista
    try {
        const calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            locale: 'es',
            firstDay: 1,
            allDayText: 'Todo el día',
            dayHeaderFormat: { weekday: 'long' },
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },
            buttonText: { today: 'Hoy', day: 'Día', week: 'Semana', month: 'Mes' },
            height: 'auto',
            contentHeight: 'auto',
            aspectRatio: 2,
            displayEventTime: false, 
            displayEventEnd: false,
            editable: false,
            events: calendarEvents,
            dayMaxEvents: 5,
            moreLinkText: 'más',
            moreLinkClick: 'popover', 
            moreLinkHint: (num) => `Mostrar ${num} eventos más`,
            initialDate: serverToday,
            now: serverToday,
            navLinks: true, // permite hacer clic en el día

            eventClick: function(info) {
                if (info.event.url) {
                    window.location.href = info.event.url;
                    return false;
                }
            },

            // ✅ Corrige el título del mes
            datesSet: function(info) {
                const start = info.start;
                const end = info.end;
                const mid = new Date(start.getTime() + (end.getTime() - start.getTime()) / 2);
                const mes = mid.toLocaleDateString('es-CL', { month: 'long' });
                const mesCap = mes.charAt(0).toUpperCase() + mes.slice(1);
                const titulo = `${mesCap} ${mid.getFullYear()}`;
                const titleEl = document.querySelector('.fc-toolbar-title');
                if (titleEl) titleEl.textContent = titulo;
            }
        });

        calendar.render();

        // ✅ Forzar vista centrada en “hoy” después de renderizar
        setTimeout(() => {
            calendar.gotoDate(serverToday);
            const todayBtn = document.querySelector('.fc-today-button');
            if (todayBtn) todayBtn.classList.add('fc-button-active');
        }, 300);

    } catch (e) {
        console.error('Error inicializando FullCalendar:', e);
        calendarEl.innerHTML = '<div class="alert alert-danger">No se pudo cargar el calendario.</div>';
    }
});
</script>
</body>
</html>
