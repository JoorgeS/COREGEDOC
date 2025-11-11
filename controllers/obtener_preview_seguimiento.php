<?php
// controllers/obtener_preview_seguimiento.php

session_start();
if (!isset($_SESSION['idUsuario'])) {
    http_response_code(403); // Prohibido
    echo '<div class="alert alert-danger">Acceso denegado. Su sesión ha expirado.</div>';
    exit;
}

// 1. Incluir dependencias
require_once __DIR__ . '/../cfg/config.php';
require_once __DIR__ . '/../class/class.conectorDB.php';
require_once __DIR__ . '/../models/minutaModel.php';

$idMinuta = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$idMinuta || $idMinuta <= 0) {
    http_response_code(400); // Solicitud incorrecta
    echo '<p class="alert alert-danger">ID de minuta no válido.</p>';
    exit;
}

/**
 * ==================================================================
 * FUNCIÓN DE ICONOS (MODIFICADA)
 * ==================================================================
 * La he ajustado para que coincida con los colores e iconos
 * de tu segunda imagen (Amarillo para feedback, Verde para firma, etc.)
 */
function getIconForAction($accion) {
    // Convertir a minúsculas y sin acentos para comparar
    $accionSimple = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $accion));

    // 1. Creada / Enviada (Azul)
    if (strpos($accionSimple, 'minuta creada') !== false || strpos($accionSimple, 'enviada para aprobacion') !== false) {
        return ['icon' => 'fas fa-pen', 'theme' => 'primary']; // Icono de lápiz, color primario
    }

    // 2. Feedback Recibido (Amarillo)
    if (strpos($accionSimple, 'feedback') !== false && strpos($accionSimple, 'aplicado') === false) {
        return ['icon' => 'fas fa-comments', 'theme' => 'warning']; // Icono de chat, color amarillo
    }

    // 3. Feedback Aplicado / Reenviado (Azul-Info)
    // (Asumo que "aplicado feedback" es un estado que registras)
    if (strpos($accionSimple, 'aplicado') !== false || strpos($accionSimple, 'reenviado') !== false) {
        return ['icon' => 'fas fa-user-check', 'theme' => 'info']; // Icono de chequeo, color info
    }

    // 4. Firma Final / Aprobada (Verde)
    if (strpos($accionSimple, 'firma final') !== false || strpos($accionSimple, 'minuta aprobada') !== false) {
        return ['icon' => 'fas fa-check-circle', 'theme' => 'success']; // Icono de check, color verde
    }

    // 5. PDF Generado (Oscuro)
    if (strpos($accionSimple, 'pdf') !== false || strpos($accionSimple, 'generado') !== false) {
        return ['icon' => 'fas fa-file-pdf', 'theme' => 'dark']; // Icono de PDF, color oscuro
    }
    
    // Icono por defecto (Azul)
    return ['icon' => 'fas fa-info-circle', 'theme' => 'primary'];
}


try {
    // 2. Crear instancia del modelo
    $model = new MinutaModel();

    // 3. Obtener los datos
    $historial = $model->getSeguimiento($idMinuta);
    $feedbacks = $model->getFeedbackDeMinuta($idMinuta);

    // 4. Unificar ambos arrays en una sola línea de tiempo
    $timelineItems = [];

    foreach ($historial as $item) {
        $timelineItems[] = [
            'fecha' => strtotime($item['fecha_hora']),
            'tipo' => 'accion',
            'datos' => $item
        ];
    }

    // Añadimos 'Requiere Revisión' como un 'feedback' para que tenga el estilo correcto
    foreach ($feedbacks as $fb) {
         $timelineItems[] = [
            'fecha' => strtotime($fb['fecha_feedback']),
            'tipo' => 'feedback',
            'datos' => $fb
        ];
    }

    // 5. Ordenar el array unificado por fecha (ascendente)
    usort($timelineItems, function($a, $b) {
        return $a['fecha'] <=> $b['fecha'];
    });

    // 6. Construir el HTML
    
    // ==================================================================
    // INICIO: NUEVO CSS PARA STEPPER HORIZONTAL
    // ==================================================================
    $html = '
    <style>
        /* Contenedor principal que permite scroll horizontal */
        .stepper-container {
            overflow-x: auto; /* Permite scroll horizontal si los pasos no caben */
            padding: 40px 20px 20px 20px; /* Espacio para los iconos arriba */
            background: #fdfdff; /* Un fondo muy claro */
            min-height: 250px; /* Altura mínima para que se vea bien */
        }
    
        /* El wrapper que alinea los pasos */
        .stepper-wrapper {
            display: flex;
            flex-wrap: nowrap; /* Evita que los items bajen */
            position: relative;
            /* Ancho mínimo basado en la cantidad de pasos */
            min-width: ' . (count($timelineItems) * 200) . 'px; 
            margin-bottom: 20px;
        }
    
        /* La línea gris que conecta todo */
        .stepper-wrapper::before {
            content: "";
            position: absolute;
            top: 11px; /* Ajustado para el centro del icono */
            left: 20px; /* Margen para que no empiece en el borde */
            right: 20px; /* Margen para que no termine en el borde */
            height: 2px;
            background-color: #e9ecef;
            z-index: 1; /* Detrás de los iconos */
        }
    
        /* Cada item individual del stepper */
        .stepper-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            flex: 1; /* Ocupa el espacio disponible */
            min-width: 180px; /* Ancho mínimo de cada caja */
            position: relative;
            z-index: 2; /* Para estar sobre la línea */
            padding: 0 10px;
        }
    
        /* El ícono circular */
        .stepper-icon {
            width: 25px;
            height: 25px;
            border-radius: 50%;
            background-color: #fff; /* Fondo blanco para tapar la línea */
            display: flex;
            align-items: center;
            justify-content: center;
            border-width: 3px;
            border-style: solid;
            font-size: 0.8rem;
        }
    
        /* La caja de contenido (como en tu imagen) */
        .stepper-content {
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: .375rem;
            padding: 1rem;
            margin-top: 15px;
            width: 100%;
            text-align: left;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
    
        .stepper-content p {
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
            font-weight: bold;
            color: #343a40;
        }
        .stepper-content small {
            font-size: 0.8rem;
            color: #6c757d;
            display: block; /* Para que se alinee bien */
            margin-bottom: 3px;
            line-height: 1.4;
        }
        .stepper-content .time {
            font-style: italic;
        }
        
        .stepper-content small i {
            width: 15px; /* Alinea los iconos */
            text-align: center;
            margin-right: 2px;
        }
    

        .stepper-icon.theme-primary { border-color: var(--bs-primary); color: var(--bs-primary); }
        .stepper-icon.theme-info    { border-color: var(--bs-info);    color: var(--bs-info); }
        .stepper-icon.theme-success { border-color: var(--bs-success); color: var(--bs-success); }
        .stepper-icon.theme-warning { border-color: var(--bs-warning); color: var(--bs-warning); }
        .stepper-icon.theme-danger  { border-color: var(--bs-danger);  color: var(--bs-danger); }
        .stepper-icon.theme-secondary { border-color: var(--bs-secondary); color: var(--bs-secondary); }
        .stepper-icon.theme-dark { border-color: var(--bs-dark); color: var(--bs-dark); }
        
    </style>
    ';
    // ==================================================================
    // FIN: NUEVO CSS
    // ==================================================================

    // Contenedor general que permite el scroll horizontal
    $html .= '<div class="stepper-container">';
    if (empty($timelineItems)) {
        $html .= '<div class="alert alert-info text-center m-3">No hay historial de seguimiento registrado para esta minuta.</div>';
    } else {
        // Wrapper que alinea los items con flex
        $html .= '<div class="stepper-wrapper">';
        
        // ==================================================================
        // INICIO: NUEVO BUCLE HTML
        // ==================================================================
        
        // NO INVERTIMOS el array. Queremos orden cronológico (de izq. a der.)
        foreach ($timelineItems as $item) {
            $html .= '<div class="stepper-item">';
            
            if ($item['tipo'] === 'accion') {
                $accion = htmlspecialchars($item['datos']['accion']);
                $iconInfo = getIconForAction($accion); // ej: ['icon' => 'fas fa-pen', 'theme' => 'primary']

                $theme = $iconInfo['theme'];
                $iconClass = $iconInfo['icon'];

                // --- Icono ---
                $html .= '<div class="stepper-icon theme-' . $theme . '"><i class="' . $iconClass . '"></i></div>';
                
                // --- Contenido ---
                $html .= '<div class="stepper-content">';
                $html .= '  <p>' . $accion . '</p>';
                // Mostramos el detalle solo si es diferente a la acción
                if ($accion != $item['datos']['detalle']) {
                    $html .= '  <small class="text-muted">' . htmlspecialchars($item['datos']['detalle']) . '</small>';
                }
                $html .= '  <small class="time"><i class="fas fa-clock"></i> ' . htmlspecialchars(date('d-m-Y H:i', $item['fecha'])) . '</small>';
                $html .= '  <small><i class="fas fa-user"></i> ' . htmlspecialchars($item['datos']['usuario_nombre']) . '</small>';
                $html .= '</div>';

            } else { // tipo 'feedback'
                // Asignamos el tema 'warning' (amarillo) para el feedback
                $titulo = "Revisión Solicitada";
                $iconInfo = getIconForAction('Feedback'); // Debería devolver 'warning'
                
                $theme = $iconInfo['theme'];
                $iconClass = $iconInfo['icon'];
                
                // --- Icono ---
                $html .= '<div class="stepper-icon theme-' . $theme . '"><i class="' . $iconClass . '"></i></div>';
                
                // --- Contenido ---
                $html .= '<div class="stepper-content">';
                $html .= '  <p>' . $titulo . '</p>';
                $html .= '  <small class="text-muted fst-italic">"' . nl2br(htmlspecialchars($item['datos']['feedback'])) . '"</small>';
                $html .= '  <small class="time"><i class="fas fa-clock"></i> ' . htmlspecialchars(date('d-m-Y H:i', $item['fecha'])) . '</small>';
                $html .= '  <small><i class="fas fa-user"></i> ' . htmlspecialchars($item['datos']['nombreUsuario']) . '</small>';
                $html .= '</div>';
            }
            
            $html .= '</div>'; // Fin .stepper-item
        }
        
        // ==================================================================
        // FIN: NUEVO BUCLE HTML
        // ==================================================================
        
        $html .= '</div>'; // Fin .stepper-wrapper
    }
    $html .= '</div>'; // Fin .stepper-container

    // 7. Devolver el HTML
    echo $html;

} catch (Exception $e) {
    // Manejo de errores de base de datos
    http_response_code(500);
    error_log("Error en obtener_preview_seguimiento.php: " );
    echo '<div class="alert alert-danger">Error fatal al cargar el seguimiento. Contacte a soporte.</div>';
}
?>