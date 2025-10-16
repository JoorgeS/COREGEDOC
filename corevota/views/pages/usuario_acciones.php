<?php
// RUTA CRÍTICA: Desde views/pages/ subimos dos niveles (../../) a la raíz para encontrar Usuario.php
require_once(__DIR__ . '/../../Usuario.php'); 


// Función para sanitizar y obtener datos POST (GARANTIZA NULL para campos vacíos)
function get_post_data($key, $default = null) {
    // 1. Obtiene y sanitiza el valor POST
    $value = filter_input(INPUT_POST, $key, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    
    // 2. Si el valor es una cadena vacía O nulo, devolvemos NULL.
    // Esto asegura que '' que viene de un <select> sin valor se convierta a NULL para la DB.
    if (empty((string)$value)) {
        return null; 
    }
    
    // Si tiene un valor real (ej. '1' o 'Juan'), lo devolvemos limpio
    return trim($value);
}

$action = get_post_data('action');
$usuarioObj = new Usuario();
$resultado = false;
$mensaje = "Error al realizar la operación.";

switch ($action) {
    case 'create':
        // --- REGISTRAR USUARIO (Create) ---
        $contrasena = filter_input(INPUT_POST, 'contrasena', FILTER_DEFAULT);
        
        if (empty($contrasena)) {
            $mensaje = "La contraseña es obligatoria para el registro.";
            break;
        }

        $contrasena_hashed = password_hash($contrasena, PASSWORD_DEFAULT);

        $datos = [
            'pNombre' => get_post_data('pNombre'),
            'sNombre' => get_post_data('sNombre'),
            'aPaterno' => get_post_data('aPaterno'),
            'aMaterno' => get_post_data('aMaterno'),
            'correo' => get_post_data('correo'),
            'contrasena' => $contrasena_hashed, 
            'perfil_id' => get_post_data('perfil_id'),
            'tipoUsuario_id' => get_post_data('tipoUsuario_id'),
            'partido_id' => get_post_data('partido_id'), // ¡SIN DOS PUNTOS EN LA CLAVE!
            'comuna_id' => get_post_data('comuna_id'),   // ¡SIN DOS PUNTOS EN LA CLAVE!
        ];

        // --- INICIO CÓDIGO TEMPORAL DE DEPURACIÓN (LOG TIPO CONSOLE.LOG) ---
        echo "<pre style='background-color: #ffe0b2; border: 1px solid #ff9800; padding: 15px;'>";
        echo "<h2>🔎 LOG: DATOS LISTOS PARA BD</h2>";
        // --- FIN CÓDIGO TEMPORAL DE DEPURACIÓN (LOG TIPO CONSOLE.LOG) ---
        
        $resultado = $usuarioObj->crearUsuario($datos); 
        
        if ($resultado) {
            $mensaje = "Usuario registrado exitosamente.";
        } else {
            $mensaje = "Error al registrar usuario. Verifique si el correo ya existe o si las listas (Perfil, Tipo Usuario, etc.) no están vacías en la base de datos.";
        }
        break;

    case 'edit':
        // --- MODIFICAR USUARIO (Update) ---
        $idUsuario = get_post_data('idUsuario');
        $contrasena_nueva = filter_input(INPUT_POST, 'contrasena', FILTER_DEFAULT);
        $userData = [];

        //$userData = $usuarioObj->obtenerUsuario($idUsuario); 

       // if (!$userData) {
        //     $mensaje = "Error: El usuario a modificar no existe.";
         //    break;
        //}
        
        $contrasena_final = password_hash($contrasena_nueva, PASSWORD_DEFAULT);

        $datos = [
            'idUsuario' => $idUsuario,
            'pNombre' => get_post_data('pNombre'),
            'sNombre' => get_post_data('sNombre'),
            'aPaterno' => get_post_data('aPaterno'),
            'aMaterno' => get_post_data('aMaterno'),
            'correo' => get_post_data('correo'),
            'perfil_id' => get_post_data('perfil_id'),
            'tipoUsuario_id' => get_post_data('tipoUsuario_id'),
            'partido_id' => get_post_data('partido_id'),
            'comuna_id' => get_post_data('comuna_id'),
        ];

        if (!empty($contrasena_nueva)) {

            $datos['contrasena'] = $contrasena_final;
        }


        $resultado = $usuarioObj->modificarUsuario($datos);
        $mensaje = $resultado ? "Usuario modificado exitosamente." : "Error al modificar usuario.";
        break;

    case 'delete':
        // --- ELIMINAR USUARIO (Delete) ---
        $idUsuario = get_post_data('idUsuario');
        $resultado = $usuarioObj->eliminarUsuario($idUsuario);
        
        $mensaje = $resultado ? "Usuario eliminado exitosamente." : "Error al eliminar. Verifique si tiene registros asociados.";
        break;

    default:
        $mensaje = "Acción no válida.";
        break;
}

// Redirección final (Este código no se ejecutará por el die() de arriba)
$status = $resultado ? 'success' : 'error';
header('Location: usuarios_listado.php?status='.$status.'&msg='.urlencode($mensaje));
exit;