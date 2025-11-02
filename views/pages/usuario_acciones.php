<?php
// views/pages/usuario_acciones.php
// RUTA CRÍTICA: Desde views/pages/ subimos dos niveles (../../) a la raíz para encontrar Usuario.php
require_once(__DIR__ . '/../../Usuario.php');

// ⚠️ IMPORTANTE: No hacer echo/print/var_dump antes de los header().

// Helper: sanitiza POST y devuelve null si viene vacío
function get_post_data($key, $default = null) {
    $value = filter_input(INPUT_POST, $key, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    if (empty((string)$value)) {
        return null;
    }
    return trim($value);
}

$action     = get_post_data('action');
$usuarioObj = new Usuario();
$resultado  = false;
$mensaje    = "Error al realizar la operación.";

// Base para redirigir SIEMPRE por el menú (con sidebar)
$menuBase = '/corevota/views/pages/menu.php';
$destino  = $menuBase . '?pagina=usuarios_listado';

try {
    switch ($action) {
        case 'create':
            // --- REGISTRAR USUARIO ---
            $contrasena = filter_input(INPUT_POST, 'contrasena', FILTER_DEFAULT);
            if (empty($contrasena)) {
                $mensaje = "La contraseña es obligatoria para el registro.";
                break;
            }

            $datos = [
                'pNombre'        => get_post_data('pNombre'),
                'sNombre'        => get_post_data('sNombre'),
                'aPaterno'       => get_post_data('aPaterno'),
                'aMaterno'       => get_post_data('aMaterno'),
                'correo'         => get_post_data('correo'),
                'contrasena'     => password_hash($contrasena, PASSWORD_DEFAULT),
                'perfil_id'      => get_post_data('perfil_id'),
                'tipoUsuario_id' => get_post_data('tipoUsuario_id'),
                'partido_id'     => get_post_data('partido_id'),
                'comuna_id'      => get_post_data('comuna_id'),
            ];

            $resultado = $usuarioObj->crearUsuario($datos);
            $mensaje   = $resultado
                ? "Usuario registrado exitosamente."
                : "Error al registrar usuario. Verifique si el correo ya existe o si las listas (Perfil, Tipo Usuario, etc.) no están vacías.";
            break;

        case 'edit':
            // --- MODIFICAR USUARIO ---
            $idUsuario        = get_post_data('idUsuario');
            $contrasena_nueva = filter_input(INPUT_POST, 'contrasena', FILTER_DEFAULT);

            $datos = [
                'idUsuario'      => $idUsuario,
                'pNombre'        => get_post_data('pNombre'),
                'sNombre'        => get_post_data('sNombre'),
                'aPaterno'       => get_post_data('aPaterno'),
                'aMaterno'       => get_post_data('aMaterno'),
                'correo'         => get_post_data('correo'),
                'perfil_id'      => get_post_data('perfil_id'),
                'tipoUsuario_id' => get_post_data('tipoUsuario_id'),
                'partido_id'     => get_post_data('partido_id'),
                'comuna_id'      => get_post_data('comuna_id'),
            ];

            // Solo actualizar contraseña si el usuario ingresó una nueva
            if (!empty($contrasena_nueva)) {
                $datos['contrasena'] = password_hash($contrasena_nueva, PASSWORD_DEFAULT);
            }

            $resultado = $usuarioObj->modificarUsuario($datos);
            $mensaje   = $resultado ? "Usuario modificado exitosamente." : "Error al modificar usuario.";
            break;

        case 'delete':
            // --- ELIMINAR USUARIO ---
            $idUsuario = get_post_data('idUsuario');
            $resultado = $usuarioObj->eliminarUsuario($idUsuario);
            $mensaje   = $resultado
                ? "Usuario eliminado exitosamente."
                : "Error al eliminar. Verifique si tiene registros asociados.";
            break;

        default:
            $mensaje = "Acción no válida.";
            break;
    }
} catch (Throwable $e) {
    // error_log($e->getMessage()); // opcional
    $resultado = false;
    $mensaje   = "Ocurrió un error al procesar la solicitud.";
}

// Redirección final SIEMPRE por menu.php para mantener el sidebar
$status = $resultado ? 'success' : 'error';
header('Location: ' . $destino . '&status=' . $status . '&msg=' . urlencode($mensaje));
exit;
