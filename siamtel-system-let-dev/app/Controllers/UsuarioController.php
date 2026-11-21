<?php

declare(strict_types=1);

/**
 * Controlador de Gestión de Usuarios (CRUD Completo con soporte AJAX) - SIAMTEL
 * Gobernación del Estado Lara
 *
 * Exclusivo para el rol ADMIN.
 * Soporta peticiones asíncronas AJAX (respuestas JSON) para:
 *  - GET  /usuarios : consulta filtrada de usuarios en tiempo real (búsqueda, rol, estado)
 *  - POST /usuarios : crear, editar, cambiar contraseña, activar/desactivar y eliminar usuarios
 * Conserva redirección clásica como respaldo si la petición no es AJAX.
 */

use App\Models\Usuario;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Verificar autenticación activa (responde 401 JSON si la llamada es AJAX)
if (!isset($_SESSION['user'])) {
    if (is_ajax()) {
        json([
            'ok'       => false,
            'mensaje'  => 'Su sesión ha expirado. Inicie sesión nuevamente.',
            'redirect' => BASE_URL . '/login',
        ], 401);
    }
    redirect('login');
}

$currentUser = $_SESSION['user'];
$esAdmin = strtoupper((string)($currentUser['rol'] ?? '')) === 'ADMIN';

// 2. Control de acceso exclusivo para el rol ADMIN
if (!$esAdmin) {
    if (is_ajax()) {
        json([
            'ok'      => false,
            'mensaje' => 'Acceso denegado: solo el administrador puede gestionar usuarios.',
        ], 403);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $_SESSION['perfil_error'] = 'Acceso denegado: solo el administrador puede gestionar usuarios.';
        redirect('usuarios');
    }

    view('usuario/perfil', [
        'titulo'      => 'Mi Cuenta - SIAMTEL',
        'currentUser' => $currentUser,
    ]);
}

/**
 * Helper de respuesta unificada para Gestión de Usuarios.
 * - Si la petición es AJAX (header X-Requested-With: XMLHttpRequest o Accept: application/json),
 *   retorna JSON con el estado, mensaje, token CSRF sincronizado y datos adicionales.
 * - Si es un envío tradicional de formulario, guarda mensaje flash en sesión y redirige.
 */
function usuarioRespond(bool $ok, string $mensaje, int $statusCode = 200, array $extra = []): void
{
    if (is_ajax()) {
        json(array_merge([
            'ok'         => $ok,
            'mensaje'    => $mensaje,
            'csrf_token' => csrf_token(),
        ], $extra), $statusCode);
    }

    $_SESSION[$ok ? 'user_success' : 'user_error'] = $mensaje;
    redirect('usuarios');
}

/**
 * Convierte el código interno del modelo (200, 400, 404, 409, 500) a código HTTP válido.
 */
function usuarioHttpStatus(int $resultado): int
{
    return match ($resultado) {
        200 => 200,
        400 => 422,
        403 => 403,
        404 => 404,
        409 => 409,
        default => 500,
    };
}

$usuarioModel = new Usuario();

// ============================================================================
// 3. PROCESAR PETICIONES POST (Operaciones CRUD vía AJAX o POST tradicional)
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validación obligatoria del token CSRF en todas las mutaciones POST
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        usuarioRespond(false, 'La sesión del formulario expiró (token CSRF inválido). Intente nuevamente.', 403);
    }

    $peticion = (string)($_POST['peticion'] ?? $_POST['accion'] ?? '');

    // A) CREAR NUEVO USUARIO (AJAX / POST)
    if ($peticion === 'crear_usuario' || $peticion === 'registrar') {
        $password = (string)($_POST['password'] ?? '');
        $confirm = (string)($_POST['password_confirm'] ?? '');

        if ($confirm === '' || $password !== $confirm) {
            usuarioRespond(false, 'Debe confirmar la contraseña y ambas deben coincidir exactamente.', 422);
        }

        $res = $usuarioModel->Transaccion([
            'peticion' => 'registrar',
            'datos'    => $_POST,
        ]);

        $codigo = (int)($res['response']['resultado'] ?? 500);
        $mensaje = (string)($res['response']['mensaje'] ?? 'Error al registrar usuario.');
        usuarioRespond($codigo === 200, $mensaje, usuarioHttpStatus($codigo), [
            'cedula' => $res['response']['cedula'] ?? null,
        ]);
    }

    // B) EDITAR USUARIO EXISTENTE (AJAX / POST)
    if ($peticion === 'editar_usuario' || $peticion === 'actualizar') {
        $cedula = trim((string)($_POST['cedula'] ?? ''));

        if ($cedula === '') {
            usuarioRespond(false, 'No se recibió la cédula del usuario a editar.', 422);
        }

        if ($cedula === ($currentUser['cedula'] ?? '') && strtoupper(trim((string)($_POST['rol'] ?? ''))) !== 'ADMIN') {
            usuarioRespond(false, 'No puede cambiar su propio rol de Administrador.', 422);
        }

        $res = $usuarioModel->Transaccion([
            'peticion' => 'actualizar',
            'datos'    => $_POST,
        ]);

        $codigo = (int)($res['response']['resultado'] ?? 500);
        $mensaje = (string)($res['response']['mensaje'] ?? 'Error al actualizar usuario.');
        $extra = [];

        // Si el ADMIN editó su propio perfil, sincronizar la sesión y devolver el nuevo nombre para el topbar
        if ($codigo === 200 && $cedula === ($currentUser['cedula'] ?? '')) {
            $nuevoUser = trim((string)($_POST['user'] ?? ''));
            if ($nuevoUser !== '') {
                $_SESSION['user']['user'] = $nuevoUser;
            }
            $nuevoNom = trim((string)($_POST['nombre'] ?? ''));
            $nuevoApe = trim((string)($_POST['apellido'] ?? ''));
            $_SESSION['user']['nombre'] = $nuevoNom;
            $_SESSION['user']['apellido'] = $nuevoApe;
            $_SESSION['user']['nombre_completo'] = trim($nuevoNom . ' ' . $nuevoApe);

            $extra['self_updated'] = true;
            $extra['current_user'] = [
                'user' => $_SESSION['user']['user'],
                'rol'  => $_SESSION['user']['rol'],
            ];
        }

        usuarioRespond($codigo === 200, $mensaje, usuarioHttpStatus($codigo), $extra);
    }

    // C) ACTIVAR / INHABILITAR CUENTA (AJAX / POST)
    if ($peticion === 'toggle_estatus') {
        $cedula = trim((string)($_POST['cedula'] ?? ''));
        $nuevoEstatusRaw = (string)($_POST['nuevo_estatus'] ?? '');

        if ($cedula === '' || !in_array($nuevoEstatusRaw, ['0', '1'], true)) {
            usuarioRespond(false, 'Parámetros inválidos para cambiar el estado de la cuenta.', 422);
        }

        if ($cedula === ($currentUser['cedula'] ?? '')) {
            usuarioRespond(false, 'No puede inhabilitar su propia cuenta activa.', 422);
        }

        $res = $usuarioModel->Transaccion([
            'peticion'      => 'toggle_estatus',
            'cedula'        => $cedula,
            'nuevo_estatus' => (int)$nuevoEstatusRaw,
        ]);

        $codigo = (int)($res['response']['resultado'] ?? 500);
        $mensaje = (string)($res['response']['mensaje'] ?? 'Error al cambiar estatus.');
        usuarioRespond($codigo === 200, $mensaje, usuarioHttpStatus($codigo));
    }

    // D) CAMBIAR CONTRASEÑA (AJAX / POST)
    if ($peticion === 'cambiar_clave') {
        $cedula = trim((string)($_POST['cedula'] ?? ''));
        $nuevaClave = (string)($_POST['nueva_clave'] ?? '');
        $confirmarClave = (string)($_POST['confirmar_clave'] ?? '');

        if ($cedula === '') {
            usuarioRespond(false, 'No se especificó el usuario para el cambio de contraseña.', 422);
        }

        if ($confirmarClave === '' || $nuevaClave !== $confirmarClave) {
            usuarioRespond(false, 'La nueva contraseña y su confirmación no coinciden.', 422);
        }

        $res = $usuarioModel->Transaccion([
            'peticion'    => 'cambiar_clave',
            'cedula'      => $cedula,
            'nueva_clave' => $nuevaClave,
        ]);

        $codigo = (int)($res['response']['resultado'] ?? 500);
        $mensaje = (string)($res['response']['mensaje'] ?? 'Error al actualizar contraseña.');
        usuarioRespond($codigo === 200, $mensaje, usuarioHttpStatus($codigo));
    }

    // E) ELIMINAR CUENTA DE USUARIO (AJAX / POST)
    if ($peticion === 'eliminar_usuario' || $peticion === 'eliminar') {
        $cedula = trim((string)($_POST['cedula'] ?? ''));

        if ($cedula === '') {
            usuarioRespond(false, 'No se especificó la cédula del usuario a eliminar.', 422);
        }

        if ($cedula === ($currentUser['cedula'] ?? '')) {
            usuarioRespond(false, 'No puede eliminar su propia cuenta activa.', 422);
        }

        $res = $usuarioModel->Transaccion([
            'peticion' => 'eliminar',
            'cedula'   => $cedula,
        ]);

        $codigo = (int)($res['response']['resultado'] ?? 500);
        $mensaje = (string)($res['response']['mensaje'] ?? 'Error al eliminar usuario.');
        usuarioRespond($codigo === 200, $mensaje, usuarioHttpStatus($codigo));
    }

    // Acción POST desconocida
    usuarioRespond(false, 'Acción solicitada no reconocida.', 400);
}

// ============================================================================
// 4. CARGAR LISTADO DE USUARIOS (Soporta GET AJAX en JSON y carga inicial HTML)
// ============================================================================
$busqueda = trim((string)($_GET['q'] ?? ''));
$rolFiltro = strtoupper(trim((string)($_GET['rol'] ?? '')));
$estatusFiltro = trim((string)($_GET['estatus'] ?? ''));
if ($estatusFiltro !== '' && !in_array($estatusFiltro, ['0', '1'], true)) {
    $estatusFiltro = '';
}

$usuariosRes = $usuarioModel->Transaccion([
    'peticion' => 'consultar',
    'busqueda' => $busqueda,
    'rol'      => $rolFiltro,
    'estatus'  => $estatusFiltro,
]);
$usuarios = $usuariosRes['response']['datos'] ?? [];

// Si es una petición GET AJAX (búsqueda/filtros en vivo o refresco tras CRUD), devolver solo JSON
if (is_ajax() || (($_GET['ajax'] ?? '') === '1')) {
    json([
        'ok'             => true,
        'usuarios'       => $usuarios,
        'total'          => count($usuarios),
        'current_cedula' => $currentUser['cedula'] ?? '',
        'csrf_token'     => csrf_token(),
    ], 200);
}

$rolesRes = $usuarioModel->Transaccion(['peticion' => 'roles']);
$roles = $rolesRes['response']['datos'] ?? [];

// 5. Renderizar Vista HTML inicial
view('usuario/index', [
    'titulo'        => 'Gestión de Usuarios - SIAMTEL',
    'currentUser'   => $currentUser,
    'usuarios'      => $usuarios,
    'roles'         => $roles,
    'busqueda'      => $busqueda,
    'rolFiltro'     => $rolFiltro,
    'estatusFiltro' => $estatusFiltro,
]);
