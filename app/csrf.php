<?php

if (session_status() === PHP_SESSION_NONE) {
    return session_start();
}

function csrf_get_token() {
    return bin2hex(random_bytes(32));
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = csrf_get_token();
}

function csrf_validate_token($token) {
    return hash_equals($_SESSION['csrf_token'], $token);
}

?>