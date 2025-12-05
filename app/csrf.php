<?php
// CSRF sencillo: sesión, token y verificación
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('csrf_get_token')) {
function csrf_get_token(): string {
    $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}
}

if (!function_exists('csrf_validate_token')) {
function csrf_validate_token($token) {
    if (empty($_SESSION['csrf_token'])) return false;
    return hash_equals($_SESSION['csrf_token'], (string)$token);
}
}

if (!function_exists('csrf_echo_input')) {
function csrf_echo_input(): void {
    $t = htmlspecialchars(csrf_get_token(), ENT_QUOTES, 'UTF-8');
    echo '<input type="hidden" name="csrf_token" value="' . $t . '">';
}
}
?>