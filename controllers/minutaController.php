<?php
// controllers/MinutaController.php

/**
 * Controlador principal para las acciones de Minutas.
 * Este archivo es incluido por menu.php, por lo que NO DEBE usar header()
 * para redireccionar, ya que el HTML ya ha sido enviado.
 * * Se asume que las constantes de ROL (ej. ROL_SECRETARIO_TECNICO)
 * ya están definidas antes de que este script se incluya.
 */

// 1. FORZAR QUE SE MUESTREN LOS ERRORES (Lo mantenemos)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Dejar en 0 para producción, 1 para depurar
// ---------------------------------------------

// Ajustamos la ruta para que sea infalible, subiendo al directorio raíz
require_once __DIR__ . '/../models/minutaModel.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** @var MinutaModel $model */
// Instanciamos el modelo una sola vez
try {
    $model = new MinutaModel();
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Error Crítico: No se pudo cargar el modelo de Minutas. " . $e->getMessage() . "</div>";
    return; // Detiene la ejecución si el modelo falla
}


// 1. Determinar acción y estado (vienen desde menu.php)
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';
$estado_filtro = $_GET['estado'] ?? null; // menu.php nos da esto

switch ($action) {

    case 'list':
        // Si 'estado' no vino por la URL, ponemos 'PENDIENTE' por defecto.
        if ($estado_filtro === null) {
            $estado_filtro = 'PENDIENTE';
        }

        // --- INICIO BLOQUE DE SEGURIDAD POR ROL ---
        $tipoUsuario = $_SESSION['tipoUsuario_id'] ?? 0;

        // REGLA 1: La lista 'PENDIENTE' es SÓLO para el Secretario (o Admin).
        if (
            $estado_filtro == 'PENDIENTE' &&
            (!defined('ROL_SECRETARIO_TECNICO') || !defined('ROL_ADMINISTRADOR') || // Check si existen
                ($tipoUsuario != ROL_SECRETARIO_TECNICO && $tipoUsuario != ROL_ADMINISTRADOR))
        ) {

            // Mostramos un error en la página.
            echo "<div class='container-fluid mt-4'>";
            echo "  <div class='alert alert-danger text-center'>";
            echo "      <h4 class='alert-heading'><i class='fas fa-exclamation-triangle'></i> Acceso Denegado</h4>";
            echo "      <p>No tiene los permisos necesarios para acceder a esta sección.</p>";
            echo "  </div>";
            echo "</div>";

            // Salimos del switch
            break;
        }
        // --- FIN BLOQUE DE SEGURIDAD ---


        // 2. LÓGICA DE FECHAS POR DEFECTO
        $today = date('Y-m-d'); // Obtenemos la fecha de hoy
        $startDate = $_GET['startDate'] ?? date('Y-m-01');
        $endDate = $_GET['endDate'] ?? date('Y-m-d');
        $themeName = $_GET['themeName'] ?? '';

        // 3. Validar estado y llamar al Modelo
        if ($estado_filtro !== 'PENDIENTE' && $estado_filtro !== 'APROBADA') {
            $minutas = []; // Estado inválido, no buscar nada
        } else {
            // Llamar al modelo con todos los parámetros
            $minutas = $model->getMinutasByEstado($estado_filtro, $startDate, $endDate, $themeName);
        }

        // 4. Preparar variables para la Vista (con nombres limpios)
        $estadoActual = $estado_filtro;
        $currentStartDate = $startDate;
        $currentEndDate = $endDate;
        $currentThemeName = $themeName;
        // $minutas ya está definida por el modelo

        // 5. Incluir la Vista (Paso final)
        include __DIR__ . '/../views/pages/minutas_listado_general.php';
        break; // Fin case 'list'

    case 'view':
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
             $_SESSION['error'] = 'ID de Tema no válido.';
             $targetUrl = 'menu.php?pagina=minutas_pendientes';
             echo "<script>window.location.href = '{$targetUrl}';</script>";
             exit;
        }
        
        $tema = $model->getTemaById($id);
        
        if (!$tema) {
            $_SESSION['error'] = 'Tema no encontrado.';
            $targetUrl = 'menu.php?pagina=minutas_pendientes';
            echo "<script>window.location.href = '{$targetUrl}';</script>";
            exit;
        }
        
        include __DIR__ . '/../views/pages/minuta_detalle.php';
        break;


    case 'edit':
        // Se asume que la lógica para definir $minutaEncontrada
        // está al inicio de la vista 'editar_minuta.php' o similar
        // y que este 'case' se usa para cargar esa página.
        // El snippet provisto solo tenía el chequeo de error:
        
        /* // Ejemplo de cómo estaría la lógica real en la vista
         $idMinuta = (int)($_GET['id'] ?? 0);
         $minutaEncontrada = $model->getMinutaParaEditar($idMinuta); // <-- Función de ejemplo
        */
        
        // Esta lógica de error probablemente deba ir DENTRO de la vista
        // que este 'case' está cargando, no aquí.
        if (!empty($minutaEncontrada) && !$minutaEncontrada) { // Asumiendo que $minutaEncontrada se define antes
            $_SESSION['error'] = 'Minuta no encontrada o ya está en proceso.';
            $targetUrl = 'menu.php?pagina=minutas_pendientes';
            echo "<script>window.location.href = '{$targetUrl}';</script>";
            exit;
        }
        
        // Aquí se incluiría la vista de edición
        // include __DIR__ . '/../views/pages/editar_minuta_vista.php';
        break;


    case 'update':
        // Se asume que $data se define con los datos de $_POST
        $id = (int)($_POST['idTema'] ?? 0);
        $data = $_POST; // Ejemplo, aquí deberías sanear y preparar tus datos

        $targetUrl = 'menu.php?pagina=minutas_pendientes';
        
        if ($id > 0) {
            if ($model->updateTema($id, $data)) {
                $_SESSION['success'] = 'Actualizado con éxito.';
            } else {
                $_SESSION['error'] = 'Error al actualizar.';
            }
        } else {
             $_SESSION['error'] = 'ID de Tema no válido para actualizar.';
        }

        // Redirección JS
        echo "<script>window.location.href = '{$targetUrl}';</script>";
        exit;
        break;

    // ---
    // --- LÓGICA DE SEGUIMIENTO RESTAURADA ---
    // ---
    case 'seguimiento_general':
        
        // 1. Obtener el ID de la minuta desde la URL
        $idMinuta = (int)($_GET['id'] ?? 0);

        if ($idMinuta <= 0) {
            $_SESSION['error'] = 'ID de minuta no válido para seguimiento.';
            $targetUrl = 'menu.php?pagina=minutas_pendientes';
            echo "<script>window.location.href = '{$targetUrl}';</script>";
            exit;
        }

        // 2. Obtener los datos del modelo
        // La vista de trazabilidad necesita las variables $minuta y $seguimiento
        
        // *** ¡ATENCIÓN! ***
        // Estoy ASUMIENDO los nombres de estas funciones de tu modelo.
        // Si tu modelo usa nombres diferentes, ¡DEBES CAMBIARLOS AQUÍ!
        $minuta = $model->getMinutaById($idMinuta); // ASUNCIÓN 1: Esta función existe
        $seguimiento = $model->getSeguimiento($idMinuta); // ASUNCIÓN 2: Esta función existe

        if (!$minuta) {
            $_SESSION['error'] = 'Minuta no encontrada (ID: ' . $idMinuta . ').';
            $targetUrl = 'menu.php?pagina=minutas_pendientes';
            echo "<script>window.location.href = '{$targetUrl}';</script>";
            exit;
        }

        // 3. Incluir la vista de Trazabilidad
        
        // *** ¡ATENCIÓN! ***
        // Este es el archivo PHP de la vista de trazabilidad que me mostraste.
        // Si tu archivo se llama diferente, ¡DEBES CAMBIARLO AQUÍ!
        $vistaSeguimiento = __DIR__ . '/../views/pages/seguimiento_minuta.php'; // ASUNCIÓN 3: El archivo se llama así

        if (file_exists($vistaSeguimiento)) {
             include $vistaSeguimiento;
        } else {
             echo "<div class='alert alert-danger'>Error: No se encontró el archivo de vista de seguimiento. ('$vistaSeguimiento')</div>";
        }
        break;
    // ---
    // --- FIN DE LA LÓGICA RESTAURADA ---
    // ---

    default:
        // Redirigir a una página por defecto si la acción no se reconoce
        $targetUrl = 'menu.php?pagina=minutas_pendientes';
        echo "<script>window.location.href = '{$targetUrl}';</script>";
        exit;
}