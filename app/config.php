<?php
/**
 * Detecta automáticamente si la app corre en localhost (XAMPP bajo /app/)
 * o en producción (raíz del dominio) y define BASE_URL en consecuencia.
 *
 *  localhost / localhost:3000  →  BASE_URL = '/app'
 *  laligafantasy.site          →  BASE_URL = ''
 */
if (!defined('BASE_URL')) {
    $__host = strtolower(preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? ''));
    define('BASE_URL', in_array($__host, ['localhost', '127.0.0.1', '::1'], true) ? '/app' : '');
    unset($__host);
}

// Alias corto para usar en vistas: echo $base . '/images/...'
$base = BASE_URL;
