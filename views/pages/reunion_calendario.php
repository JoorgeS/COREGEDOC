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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Calendario de Reuniones</title>
    <link href="/corevota/public/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/main.min.css" rel="stylesheet" />

    <style>
        body { background-color: #f8f9fa; }

        #fullCalendarContainer {
            position: relative;
            width: 95%;
            height: 80vh;
            margin: 20px auto;
            background-color: #fff;
            padding: 15px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        #fullCalendarContainer::before {
            content: "";
            position: absolute;
            top: 50%;
            left: 50%;
            width: 1200px;
            height: 1200px;
            background: url("/corevota/public/img/logoCore1.png") no-repeat center center;
            background-size: contain;
            opacity: 0.06;
            transform: translate(-50%, -50%);
            z-index: 0;
            pointer-events: none;
        }

        .fc { position: relative; z-index: 1; }

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

        .fc-toolbar-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #212529;
        }

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
        .fc-button.fc-button-active { background-color: #212529 !important; color: #fff !important; box-shadow: 0 2px 4px rgba(0,0,0,.2); }
        .fc-button-group { display: flex; gap: .5rem; }
        .fc-prev-button, .fc-next-button, .fc-today-button { min-width: 2.5rem; text-align: center; }

        .fc-col-header-cell {
            background-color: #f1f3f5;
            color: #495057;
            font-weight: 500;
            font-size: .8rem;
        }

        .fc-daygrid-day-number {
            font-size: .8rem;
            font-weight: 500;
            color: #495057;
        }

        .fc .fc-col-header-cell-cushion {
            text-transform: capitalize;
        }

        .fc-event, .fc-daygrid-event {
            border-radius: .5rem;
            border: 0;
            font-size: .75rem;
            font-weight: 500;
            padding: .25rem .4rem;
        }

        .fc-event:hover { filter: brightness(0.9); }
    </style>
</head>
<body>

<div class="container mt-4">
    <h3 class="calendar-title">Calendario General de Reuniones Vigentes</h3>
    <div id="fullCalendarContainer"></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const reunionesPHP = <?php echo json_encode($reuniones ?? []); ?>;

    const calendarEvents = reunionesPHP.map(reunion => {
        const colorMap = {
            1: '#54a3ff', 2: '#7dd321', 3: '#ffc107', 4: '#dc3545',
            5: '#6f42c1', 6: '#20c997', 7: '#fd7e14', 8: '#6c757d'
        };
        const commissionId = reunion.t_comision_idComision ? parseInt(reunion.t_comision_idComision) : 1;
        return {
            id: reunion.idReunion,
            title: reunion.nombreComision + ': ' + reunion.nombreReunion,
            start: reunion.fechaInicioReunion,
            end: reunion.fechaTerminoReunion,
            allDay: false,
            backgroundColor: colorMap[commissionId] || '#17a2b8',
            borderColor: colorMap[commissionId] || '#17a2b8',
            url: `/corevota/views/pages/crearReunion.php?action=edit&id=${reunion.idReunion}`
        };
    });

    function capitalizeFirst(s) {
        if (!s) return s;
        return s.charAt(0).toUpperCase() + s.slice(1);
    }

    const calendarEl = document.getElementById('fullCalendarContainer');
    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: 'es',
        firstDay: 1,
        dayHeaderFormat: { weekday: 'long' },

        // 🔹 Mostrar "Octubre 2025" (sin el "de")
        titleFormat: function(info) {
            const d = info.date ? info.date.marker : info.view.currentStart;
            const month = capitalizeFirst(d.toLocaleDateString('es-CL', { month: 'long' }));
            const year  = d.getFullYear();
            return `${month} ${year}`;
        },

        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        buttonText: {
            today: 'Hoy',
            day:   'Día',
            week:  'Semana',
            month: 'Mes'
        },

        height: 'auto',
        contentHeight: 'auto',
        aspectRatio: 2,
        editable: false,
        events: calendarEvents,

        eventClick: function(info) {
            if (info.event.url) {
                window.location.href = info.event.url;
                return false;
            }
        }
    });

    calendar.render();
});
</script>
</body>
</html>
