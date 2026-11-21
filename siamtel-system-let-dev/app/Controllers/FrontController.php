<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;

class FrontController
{
    private string $dir;
    private string $url;

    public function __construct()
    {
        $this->dir = __DIR__ . '/';
        $this->resolveRoute();
        $this->dispatch();
    }

    private function resolveRoute(): void
    {
        // 1. Obtener ruta desde parámetro query (?url=... o ?page=...) o URL amigable
        $route = $_REQUEST['url'] ?? $_GET['page'] ?? null;

        if (empty($route)) {
            $requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
            $path = trim($requestUri, '/');
            if (!empty($path) && $path !== 'index.php') {
                $segments = explode('/', $path);
                $route = $segments[0];
            }
        }

        $route = strtolower(trim((string)$route));

        // 2. Manejo de acción de cierre de sesión
        if ($route === 'logout' || (isset($_REQUEST['type']) && $_REQUEST['type'] === 'logout')) {
            $this->destroySession('Sesión cerrada correctamente.');
            redirect('login');
            return;
        }

        // 3. Control de acceso según sesión
        if (!isset($_SESSION['user'])) {
            // Si una petición AJAX llega a un módulo protegido sin sesión activa, responder 401 en JSON
            if (is_ajax() && $route !== '' && $route !== 'login' && $route !== 'auth') {
                json([
                    'ok'       => false,
                    'mensaje'  => 'Su sesión ha expirado. Inicie sesión nuevamente.',
                    'redirect' => BASE_URL . '/login',
                ], 401);
            }
            $this->url = 'Auth';
            return;
        }

        // 4. Verificar que la cuenta en sesión siga activa en BD y sincronizar su rol actual
        $this->syncActiveSessionUser();
        if (!isset($_SESSION['user'])) {
            $this->url = 'Auth';
            return;
        }

        // 5. Única funcionalidad operativa del sistema: Gestión de Usuarios (redirigir cualquier otra ruta a /usuarios)
        if ($route !== 'usuarios' && $route !== 'usuario') {
            if (is_ajax() && ($route === 'login' || $route === 'auth')) {
                json([
                    'ok'       => true,
                    'mensaje'  => 'Sesión ya activa. Redirigiendo...',
                    'redirect' => BASE_URL . '/usuarios',
                ], 200);
            }
            redirect('usuarios');
            return;
        }

        $this->url = 'Usuario';
    }

    private function syncActiveSessionUser(): void
    {
        $cedula = $_SESSION['user']['cedula'] ?? '';
        if ($cedula === '') {
            $this->destroySession('Sesión inválida. Inicie sesión nuevamente.', true);
            return;
        }

        try {
            $pdo = Database::getConnection();
            if ($pdo === null) {
                return;
            }

            $stmt = $pdo->prepare(
                "SELECT u.cedula, u.user, u.rol, u.estatus, p.nombre, p.apellido, p.correo
                 FROM Usuario u
                 LEFT JOIN persona p ON u.cedula = p.cedula
                 WHERE u.cedula = :cedula LIMIT 1"
            );
            $stmt->execute([':cedula' => $cedula]);
            $row = $stmt->fetch();

            if (!$row) {
                $this->destroySession('Su cuenta de usuario ya no existe en el sistema.', true);
                if (is_ajax()) {
                    json([
                        'ok'       => false,
                        'mensaje'  => 'Su cuenta de usuario ya no existe en el sistema.',
                        'redirect' => BASE_URL . '/login',
                    ], 401);
                }
                redirect('login');
                return;
            }

            if ((int)$row['estatus'] !== 1) {
                $this->destroySession('Su cuenta ha sido inhabilitada por un administrador.', true);
                if (is_ajax()) {
                    json([
                        'ok'       => false,
                        'mensaje'  => 'Su cuenta ha sido inhabilitada por un administrador.',
                        'redirect' => BASE_URL . '/login',
                    ], 401);
                }
                redirect('login');
                return;
            }

            $nombreCompleto = trim(((string)($row['nombre'] ?? '')) . ' ' . ((string)($row['apellido'] ?? '')));
            $_SESSION['user']['user'] = $row['user'];
            $_SESSION['user']['rol'] = $row['rol'];
            $_SESSION['user']['estatus'] = (int)$row['estatus'];
            $_SESSION['user']['nombre'] = $row['nombre'] ?? '';
            $_SESSION['user']['apellido'] = $row['apellido'] ?? '';
            $_SESSION['user']['nombre_completo'] = $nombreCompleto !== '' ? $nombreCompleto : $row['user'];
            $_SESSION['user']['correo'] = $row['correo'] ?? '';
        } catch (\Throwable $e) {
            error_log('[Session Sync Error] ' . $e->getMessage());
        }
    }

    private function destroySession(string $mensaje = '', bool $esError = false): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_regenerate_id(true);
            if ($mensaje !== '') {
                $_SESSION[$esError ? 'error_login' : 'success_login'] = $mensaje;
            }
        }
    }

    private function dispatch(): void
    {
        $controllerName = ucfirst(strtolower($this->url)) . 'Controller.php';
        $controllerPath = $this->dir . $controllerName;

        if (file_exists($controllerPath)) {
            require_once $controllerPath;
        } else {
            redirect('usuarios');
        }
    }
}
