<?php

declare(strict_types=1);

/**
 * Controlador de Autenticación - SIAMTEL
 * Soporta envío vía AJAX (JSON) y envío tradicional como respaldo,
 * con validaciones backend (CSRF, formato de cédula/usuario, longitud de clave y control de intentos).
 */

use App\Models\Auth;

// 1. Manejo de sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Obtener tipo de acción solicitada
$type = $_REQUEST['type'] ?? 'index';

// 3. Procesar Logout
if ($type === 'logout') {
    authLogout();
}

// 4. Si ya hay usuario autenticado, redirigir al módulo operativo
if (isset($_SESSION['user'])) {
    if (is_ajax()) {
        json([
            'ok'       => true,
            'mensaje'  => 'Sesión ya activa.',
            'redirect' => BASE_URL . '/usuarios',
        ], 200);
    }
    redirect('usuarios');
}

// 5. Procesamiento de Vistas y Peticiones POST
if ($type === 'index') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        authProcessLogin();
    }

    view('auth/login', [
        'titulo' => 'Iniciar Sesión - SIAMTEL',
    ]);
}

// ============================================================================
// Funciones de Respuesta, Validación y Autenticación (Backend)
// ============================================================================

function authRespond(bool $ok, string $mensaje, int $statusCode = 200, array $extra = []): void
{
    if (is_ajax()) {
        unset($_SESSION['old_login_particle'], $_SESSION['old_login_ci'], $_SESSION['error_login']);
        json(array_merge([
            'ok'         => $ok,
            'mensaje'    => $mensaje,
            'csrf_token' => csrf_token(),
        ], $extra), $statusCode);
    }

    if ($ok) {
        redirect($extra['redirect'] ?? 'usuarios');
    }

    $_SESSION['error_login'] = $mensaje;
    redirect('login');
}

function authNormalizeLoginIdentifier(string $particle, string $input): string
{
    $input = trim($input);
    if ($input === '') {
        return '';
    }

    // Si ya incluyó prefijo + dígitos (ej: V-00000000 o v00000000)
    if (preg_match('/^([VEJPG])-?(\d{6,9})$/i', $input, $m)) {
        return strtoupper($m[1]) . '-' . $m[2];
    }

    // Si solo escribió dígitos (cédula), anteponer el prefijo seleccionado
    if (preg_match('/^\d{6,9}$/', $input)) {
        $prefix = in_array($particle, ['V-', 'E-', 'J-', 'G-', 'P-'], true) ? $particle : 'V-';
        return $prefix . $input;
    }

    // Nombre de usuario (ej: admin, dr.rodriguez, jesus)
    return $input;
}

function authValidateLoginInput(string $particle, string $ci, string $password): ?string
{
    $prefijosValidos = ['V-', 'E-', 'J-', 'G-', 'P-'];
    if (!in_array($particle, $prefijosValidos, true)) {
        return 'El prefijo de documento seleccionado no es válido.';
    }

    if ($ci === '' || $password === '') {
        return 'Por favor, complete la cédula/usuario y la contraseña.';
    }

    if (mb_strlen($ci) < 3 || mb_strlen($ci) > 60) {
        return 'La cédula o nombre de usuario debe tener entre 3 y 60 caracteres.';
    }

    if (preg_match('/\s/', $ci)) {
        return 'La cédula o nombre de usuario no puede contener espacios.';
    }

    if (ctype_digit($ci)) {
        if (!preg_match('/^\d{6,9}$/', $ci)) {
            return 'El número de cédula debe tener entre 6 y 9 dígitos.';
        }
    } elseif (preg_match('/^[VEJPG]-?\d+$/i', $ci)) {
        if (!preg_match('/^[VEJPG]-?\d{6,9}$/i', $ci)) {
            return 'La cédula con prefijo debe tener entre 6 y 9 dígitos (ej: V-12345678).';
        }
    } else {
        if (!preg_match('/^[a-zA-Z0-9._-]{3,50}$/', $ci)) {
            return 'El nombre de usuario solo puede contener letras, números, punto (.), guión (-) o guión bajo (_).';
        }
    }

    if (mb_strlen($password) < 4 || mb_strlen($password) > 128) {
        return 'La contraseña debe tener entre 4 y 128 caracteres.';
    }

    return null;
}

function authCheckRateLimit(string $key): ?array
{
    $maxIntentos = 5;
    $bloqueoSegundos = 60;

    $record = $_SESSION['login_attempts'][$key] ?? null;
    if (!is_array($record)) {
        return null;
    }

    $blockedUntil = (int)($record['blocked_until'] ?? 0);
    if ($blockedUntil > time()) {
        $restantes = $blockedUntil - time();
        return [
            'mensaje'  => "Demasiados intentos fallidos. Intente nuevamente en {$restantes} segundos.",
            'segundos' => $restantes,
        ];
    }

    if ($blockedUntil > 0 && $blockedUntil <= time()) {
        unset($_SESSION['login_attempts'][$key]);
    } elseif ((int)($record['count'] ?? 0) >= $maxIntentos) {
        $_SESSION['login_attempts'][$key]['blocked_until'] = time() + $bloqueoSegundos;
        return [
            'mensaje'  => "Demasiados intentos fallidos. Intente nuevamente en {$bloqueoSegundos} segundos.",
            'segundos' => $bloqueoSegundos,
        ];
    }

    return null;
}

function authRegisterFailedAttempt(string $key): void
{
    $maxIntentos = 5;
    $bloqueoSegundos = 60;

    $count = (int)($_SESSION['login_attempts'][$key]['count'] ?? 0) + 1;
    $_SESSION['login_attempts'][$key] = [
        'count'         => $count,
        'blocked_until' => ($count >= $maxIntentos) ? (time() + $bloqueoSegundos) : 0,
    ];
}

function authProcessLogin(): void
{
    $particle = (string)($_POST['particle'] ?? 'V-');
    $ci = trim((string)($_POST['CI'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if (!is_ajax()) {
        $_SESSION['old_login_particle'] = $particle;
        $_SESSION['old_login_ci'] = $ci;
    }

    // 1. Validación de token CSRF
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        authRespond(false, 'La sesión del formulario expiró. Por favor, intente nuevamente.', 403);
    }

    // 2. Validación de formato y campos obligatorios
    $errorValidacion = authValidateLoginInput($particle, $ci, $password);
    if ($errorValidacion !== null) {
        authRespond(false, $errorValidacion, 422);
    }

    $identificador = authNormalizeLoginIdentifier($particle, $ci);
    $rateKey = strtolower($identificador);

    // 3. Validación de límite de intentos fallidos
    $bloqueo = authCheckRateLimit($rateKey);
    if ($bloqueo !== null) {
        authRespond(false, $bloqueo['mensaje'], 429, ['bloqueado_segundos' => $bloqueo['segundos']]);
    }

    if (!class_exists(Auth::class)) {
        authRespond(false, 'Módulo de autenticación no disponible.', 500);
    }

    try {
        $usuarioModel = new Auth();
        $usuarioModel->setCedula($identificador);
        $usuarioModel->setPassword($password);

        $validacion = $usuarioModel->Transaccion(['peticion' => 'sesion']);
        $codigo = (int)($validacion['response']['resultado'] ?? 500);

        if ($codigo === 200) {
            unset(
                $_SESSION['login_attempts'][$rateKey],
                $_SESSION['old_login_particle'],
                $_SESSION['old_login_ci'],
                $_SESSION['error_login'],
                $_SESSION['submodulos'],
                $_SESSION['permisos']
            );

            $datos = $usuarioModel->Transaccion(['peticion' => 'perfil']);

            if ($datos && isset($datos['response']['datos'])) {
                session_regenerate_id(true);
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                $_SESSION['user'] = $datos['response']['datos'];

                authRespond(true, 'Inicio de sesión exitoso. Redirigiendo...', 200, [
                    'redirect' => BASE_URL . '/usuarios',
                    'usuario'  => [
                        'user' => $_SESSION['user']['user'],
                        'rol'  => $_SESSION['user']['rol'],
                    ],
                ]);
            }

            authRespond(false, 'Error al cargar los datos del perfil.', 500);
        } elseif ($codigo === 403) {
            authRespond(false, 'Su cuenta de usuario se encuentra inhabilitada. Contacte al administrador.', 403);
        } elseif ($codigo === 500) {
            authRespond(false, $validacion['response']['mensaje'] ?? 'Error de conexión con la base de datos.', 500);
        } else {
            authRegisterFailedAttempt($rateKey);
            $intentos = (int)($_SESSION['login_attempts'][$rateKey]['count'] ?? 0);
            $restantes = max(0, 5 - $intentos);
            if ($restantes === 0) {
                authRespond(
                    false,
                    'Credenciales incorrectas. Cuenta bloqueada temporalmente por 60 segundos.',
                    429,
                    ['intentos_restantes' => 0, 'bloqueado_segundos' => 60]
                );
            }
            authRespond(
                false,
                "Cédula/usuario o contraseña incorrectos. (Intentos restantes: {$restantes})",
                401,
                ['intentos_restantes' => $restantes]
            );
        }
    } catch (\Throwable $e) {
        error_log('[Auth Error] ' . $e->getMessage());
        authRespond(false, 'Error interno al procesar el inicio de sesión.', 500);
    }
}

function authLogout(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['success_login'] = 'Sesión cerrada correctamente.';
    }
    redirect('login');
}
