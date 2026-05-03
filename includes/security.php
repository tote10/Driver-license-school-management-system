<?php

if (!function_exists('csrf_token')) {
    function csrf_token(): string {
        if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_input')) {
    function csrf_input(): void {
        echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
    }
}

if (!function_exists('csrf_validate_request')) {
    function csrf_validate_request(): bool {
        $sessionToken = $_SESSION['csrf_token'] ?? '';
        $requestToken = $_POST['csrf_token'] ?? '';

        if (!is_string($sessionToken) || $sessionToken === '') {
            return false;
        }
        if (!is_string($requestToken) || $requestToken === '') {
            return false;
        }

        return hash_equals($sessionToken, $requestToken);
    }
}
