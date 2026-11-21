<?php

declare(strict_types=1);

/**
 * Funciones Auxiliares Globales para Vistas, Respuestas y Seguridad CSRF
 */

if (!function_exists('view')) {
    /**
     * Renderiza una vista y le pasa datos asociativos como variables locales.
     *
     * @param string $viewPath Ruta de la vista relativa a app/Views (ej: 'auth/login')
     * @param array $data Variables que se extraerán en la vista
     */
    function view(string $viewPath, array $data = []): void
    {
        extract($data);

        $cleanPath = ltrim($viewPath, '/');
        $fullPath = BASE_PATH . '/app/Views/' . $cleanPath . '.php';

        if (!file_exists($fullPath)) {
            $altPath = BASE_PATH . '/app/Views/' . basename($cleanPath) . '.php';
            if (file_exists($altPath)) {
                $fullPath = $altPath;
            } else {
                http_response_code(404);
                echo "<h1>Error 404: Vista no encontrada</h1>";
                echo "<p>No se pudo localizar el archivo: <code>app/Views/{$cleanPath}.php</code></p>";
                exit();
            }
        }

        require_once $fullPath;
        exit();
    }
}

if (!function_exists('redirect')) {
    /**
     * Redirige el navegador a una ruta interna o externa.
     */
    function redirect(string $path): void
    {
        $url = (str_starts_with($path, 'http://') || str_starts_with($path, 'https://'))
            ? $path
            : rtrim(BASE_URL, '/') . '/' . ltrim($path, '/');

        header('Location: ' . $url);
        exit();
    }
}

if (!function_exists('json')) {
    /**
     * Emite una respuesta en formato JSON.
     */
    function json(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
        exit();
    }
}

if (!function_exists('csrf_token')) {
    /**
     * Genera o retorna el token CSRF activo en la sesión.
     */
    function csrf_token(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_field')) {
    /**
     * Retorna el input hidden con el token CSRF para formularios HTML.
     */
    function csrf_field(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
    }
}

if (!function_exists('csrf_verify')) {
    /**
     * Verifica de forma segura que el token CSRF recibido coincida con el de sesión.
     */
    function csrf_verify(?string $token): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $sessionToken = $_SESSION['csrf_token'] ?? '';
        if (!is_string($sessionToken) || $sessionToken === '' || !is_string($token) || $token === '') {
            return false;
        }
        return hash_equals($sessionToken, $token);
    }
}

if (!function_exists('is_ajax')) {
    /**
     * Detecta si la petición HTTP actual fue enviada mediante AJAX / Fetch.
     */
    function is_ajax(): bool
    {
        $requestedWith = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
        $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
        return $requestedWith === 'xmlhttprequest' || str_contains($accept, 'application/json');
    }
}
