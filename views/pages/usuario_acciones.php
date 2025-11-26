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

/**
 * Verifica si un correo ya existe en la BD usando el modelo existente.
 * - $excludeId: id de usuario que se debe excluir de la comparación (caso editar).
 */
function correo_existe(Usuario $usuarioObj, string $correo, ?int $excludeId = null): bool {
    $lista = $usuarioObj->listarUsuarios(); // no tocamos el modelo
    if (!is_array($lista)) return false;

    $needle = mb_strtolower($correo, 'UTF-8');
    foreach ($lista as $u) {
        $idU   = (int)($u['idUsuario'] ?? 0);
        $mailU = mb_strtolower((string)($u['correo'] ?? ''), 'UTF-8');
        if ($mailU !== '' && $mailU === $needle) {
            if ($excludeId !== null && $idU === $excludeId) {
                continue; // mismo usuario, permitido
            }
            return true; // duplicado encontrado
        }
    }
    return false;
}

$action     = get_post_data('action');
$usuarioObj = new Usuario();
$resultado  = false;
$mensaje    = "Error al realizar la operación.";

// Base para redirigir SIEMPRE por el menú (con sidebar)
$menuBase = '/coregedoc/views/pages/menu.php';
$destino  = $menuBase . '?pagina=usuarios_listado';

try {
    switch ($action) {
        case 'create':
            // --- VALIDACIÓN DUPLICIDAD CORREO (SERVER-SIDE) ---
            $correoPost = get_post_data('correo');
            if (empty($correoPost)) {
                $resultado = false;
                $mensaje   = "El correo es obligatorio.";
                $destino   = $menuBase . '?pagina=usuario_crear';
                break;
            }
            if (correo_existe($usuarioObj, $correoPost, null)) {
                $resultado = false;
                $mensaje   = 'El correo "' . $correoPost . '" ya está registrado. Usa otro.';
                $destino   = $menuBase . '?pagina=usuario_crear';
                break; // no continuamos con el insert
            }
            // --- REGISTRAR USUARIO ---
            $contrasena = filter_input(INPUT_POST, 'contrasena', FILTER_DEFAULT);
            if (empty($contrasena)) {
                $mensaje = "La contraseña es obligatoria para el registro.";
                $destino = $menuBase . '?pagina=usuario_crear';
                break;
            }

            $datos = [
                'pNombre'        => get_post_data('pNombre'),
                'sNombre'        => get_post_data('sNombre'),
                'aPaterno'       => get_post_data('aPaterno'),
                'aMaterno'       => get_post_data('aMaterno'),
                'correo'         => $correoPost,
                'contrasena'     => password_hash($contrasena, PASSWORD_DEFAULT),
                'perfil_id'      => get_post_data('perfil_id'),
                'tipoUsuario_id' => get_post_data('tipoUsuario_id'),
                'partido_id'     => get_post_data('partido_id'),
                'provincia_id'      => get_post_data('provincia_id'),
            ];

            $resultado = $usuarioObj->crearUsuario($datos);
            $mensaje   = $resultado
                ? "Usuario registrado exitosamente."
                : "Error al registrar usuario. Verifique si el correo ya existe o si las listas (Perfil, Tipo Usuario, etc.) no están vacías.";
            $destino   = $menuBase . '?pagina=usuarios_listado';
            break;

        case 'edit':
            $idUsuario  = (int) (get_post_data('idUsuario') ?? 0);
            $correoPost = get_post_data('correo');

            if ($idUsuario <= 0) {
                $resultado = false;
                $mensaje   = "ID de usuario inválido.";
                $destino   = $menuBase . '?pagina=usuarios_listado';
                break;
            }
            if (empty($correoPost)) {
                $resultado = false;
                $mensaje   = "El correo es obligatorio.";
                $destino   = $menuBase . '?pagina=usuario_editar&id=' . $idUsuario;
                break;
            }
            if (correo_existe($usuarioObj, $correoPost, $idUsuario)) {
                $resultado = false;
                $mensaje   = 'El correo "' . $correoPost . '" ya está registrado en otro usuario.';
                $destino   = $menuBase . '?pagina=usuario_editar&id=' . $idUsuario;
                break;
            }

            $contrasena_nueva = filter_input(INPUT_POST, 'contrasena', FILTER_DEFAULT);

            $datos = [
                'idUsuario'      => $idUsuario,
                'pNombre'        => get_post_data('pNombre'),
                'sNombre'        => get_post_data('sNombre'),
                'aPaterno'       => get_post_data('aPaterno'),
                'aMaterno'       => get_post_data('aMaterno'),
                'correo'         => $correoPost,
                'perfil_id'      => get_post_data('perfil_id'),
                'tipoUsuario_id' => get_post_data('tipoUsuario_id'),
                'partido_id'     => get_post_data('partido_id'),
                'provincia_id'      => get_post_data('provincia_id'),
            ];

            if (!empty($contrasena_nueva)) {
                $datos['contrasena'] = password_hash($contrasena_nueva, PASSWORD_DEFAULT);
            }

            $resultado = $usuarioObj->modificarUsuario($datos);
            $mensaje   = $resultado ? "Usuario modificado exitosamente." : "Error al modificar usuario.";
            $destino   = $menuBase . '?pagina=usuarios_listado';
            break;

        case 'delete':
            // --- BORRADO LÓGICO (Desactivación) ---
            $idUsuario = (int) (get_post_data('idUsuario') ?? 0);

            if ($idUsuario <= 0) {
                $resultado = false;
                $mensaje   = "ID de usuario inválido para dar de baja.";
                break;
            }

           // Usamos directamente eliminarUsuario, que ya realiza el borrado lógico (estado = 0)
            $resultado = $usuarioObj->eliminarUsuario($idUsuario);

            $mensaje = $resultado
                ? "Usuario dado de baja (desactivado) exitosamente."
                : "Error al dar de baja el usuario. Verifique la conexión o si el ID existe.";
            $destino = $menuBase . '?pagina=usuarios_listado';
            break;

        default:
            $mensaje = "Acción no válida.";
            $destino = $menuBase . '?pagina=usuarios_listado';
            break;
    }
} catch (Throwable $e) {
    error_log("Error en usuario_acciones.php: " . $e->getMessage());
    $resultado = false;
    $mensaje   = "Ocurrió un error al procesar la solicitud.";
}

// Redirección final SIEMPRE por menu.php para mantener el sidebar
$status = $resultado ? 'success' : 'error';
header('Location: ' . $destino . '&status=' . $status . '&msg=' . urlencode($mensaje));
exit;
