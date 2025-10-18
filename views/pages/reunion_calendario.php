<?php
// reunion_calendario.php
// Este archivo debe obtener TODAS las reuniones vigentes para mostrar el calendario.

// Incluir el controlador para acceder al Manager
require_once __DIR__ . '/../../controllers/ReunionController.php'; 

// Lógica de seguridad (asumiendo que está en tu layout)
// if (!isset($_SESSION['idUsuario'])) { header("Location: /corevota/views/pages/login.php"); exit; }

$reuniones = [];
if (class_exists('ReunionManager')) {
    try {
        $manager = new ReunionManager();
        // Usamos una nueva función para obtener todas las reuniones sin paginación
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
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/main.min.css' rel='stylesheet' />
    
    <style>
        #fullCalendarContainer {
            width: 95%; 
            height: 80vh; /* Permite que el calendario sea grande y ocupado */
            margin: 20px auto;
            background-color: #fff;
            padding: 15px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        /* Ajustes para el título */
        .calendar-title {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>

<div class="container mt-4">
    <h3 class="calendar-title">Calendario General de Reuniones Vigentes</h3>
    
    <div id='fullCalendarContainer'></div>
    
</div>

<script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js'></script>
<script>
    // --- LÓGICA DEL CALENDARIO FULLCALENDAR ---
    document.addEventListener('DOMContentLoaded', function() {
        // 1. Extraer datos PHP
        const reunionesPHP = <?php echo json_encode($reuniones ?? []); ?>; 
        
        const calendarEvents = reunionesPHP.map(reunion => {
            // Mapa de colores (puedes expandirlo o basarlo en un campo de la DB)
            const colorMap = {
                1: '#54a3ff', 2: '#7dd321', 3: '#ffc107', 4: '#dc3545', 
                5: '#6f42c1', 6: '#20c997', 7: '#fd7e14', 8: '#6c757d'
            };
            // Aseguramos que la comisión sea un número para el color
            const commissionId = reunion.t_comision_idComision ? parseInt(reunion.t_comision_idComision) : 1;
            
            return {
                id: reunion.idReunion,
                title: reunion.nombreComision + ': ' + reunion.nombreReunion,
                start: reunion.fechaInicioReunion, 
                end: reunion.fechaTerminoReunion,
                allDay: false,
                backgroundColor: colorMap[commissionId] || '#17a2b8',
                borderColor: colorMap[commissionId] || '#17a2b8',
                // Enlace para editar el evento al hacer clic (opcional para visualización)
                url: `/corevota/views/pages/crearReunion.php?action=edit&id=${reunion.idReunion}`
            };
        });
        
        // 2. Inicializar el calendario
        const calendarEl = document.getElementById('fullCalendarContainer');
        const calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            locale: 'es',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },
            // Permite que el calendario ajuste su tamaño al contenedor
            height: 'auto', 
            contentHeight: 'auto',
            aspectRatio: 2, // Hace el calendario más ancho que alto
            
            editable: false,
            eventLimit: true,
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
<script src="/corevota/public/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>