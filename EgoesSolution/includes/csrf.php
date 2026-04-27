<?php

if (!function_exists('eg_csrf_token')) {
    function eg_csrf_token(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('eg_csrf_validate')) {
    function eg_csrf_validate(?string $token): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $sessionToken = $_SESSION['csrf_token'] ?? '';
        if (!is_string($sessionToken) || $sessionToken === '') {
            return false;
        }
        return is_string($token) && $token !== '' && hash_equals($sessionToken, $token);
    }
}
