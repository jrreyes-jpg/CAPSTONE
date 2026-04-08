<?php
// Shared authentication/session helpers for protected pages.

if (!function_exists('auth_start_session')) {
    function auth_start_session(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }
}

if (!function_exists('auth_apply_no_cache_headers')) {
    function auth_apply_no_cache_headers(): void
    {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
    }
}

if (!function_exists('auth_destroy_session')) {
    function auth_destroy_session(): void
    {
        auth_start_session();
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                (bool)$params['secure'],
                (bool)$params['httponly']
            );
        }

        session_destroy();
    }
}

if (!function_exists('auth_redirect_to_login')) {
    function auth_redirect_to_login(array $query = []): void
    {
        $target = '/codesamplecaps/LOGIN/php/login.php';
        if (!empty($query)) {
            $target .= '?' . http_build_query($query);
        }
        header('Location: ' . $target);
        exit();
    }
}

if (!function_exists('auth_enforce_activity_timeout')) {
    function auth_enforce_activity_timeout(int $idleTimeoutSeconds = 900): void
    {
        $userId = $_SESSION['user_id'] ?? null;
        if ($userId === null) {
            return;
        }

        $now = time();
        $lastActivity = (int)($_SESSION['last_activity_at'] ?? $now);

        if (($now - $lastActivity) > $idleTimeoutSeconds) {
            auth_destroy_session();
            auth_redirect_to_login(['timeout' => '1']);
        }

        $_SESSION['last_activity_at'] = $now;
    }
}

if (!function_exists('require_role')) {
    function require_role($role): void
    {
        auth_start_session();
        auth_apply_no_cache_headers();
        auth_enforce_activity_timeout();

        if (!isset($_SESSION['role']) || $_SESSION['role'] !== $role) {
            auth_destroy_session();
            auth_redirect_to_login();
        }
    }
}

if (!function_exists('require_any_role')) {
    function require_any_role(array $roles): void
    {
        auth_start_session();
        auth_apply_no_cache_headers();
        auth_enforce_activity_timeout();

        if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $roles, true)) {
            auth_destroy_session();
            auth_redirect_to_login();
        }
    }
}

if (!function_exists('current_user_id')) {
    function current_user_id()
    {
        return $_SESSION['user_id'] ?? null;
    }
}

if (!function_exists('current_user_role')) {
    function current_user_role()
    {
        return $_SESSION['role'] ?? null;
    }
}
