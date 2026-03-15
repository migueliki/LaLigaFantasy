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

if (!function_exists('asset_url')) {
    function asset_url(string $path): string {
        $normalized = '/' . ltrim($path, '/');
        $candidates = [];

        $baseCandidate = rtrim((string)BASE_URL, '/') . $normalized;
        $candidates[] = $baseCandidate;
        $candidates[] = $normalized;
        $candidates[] = '/app' . $normalized;

        $docRoot = rtrim(str_replace('\\', '/', (string)($_SERVER['DOCUMENT_ROOT'] ?? '')), '/');
        if ($docRoot !== '') {
            foreach ($candidates as $candidate) {
                if (@is_file($docRoot . $candidate)) {
                    return $candidate;
                }
            }
        }

        return $baseCandidate;
    }
}

// Alias corto para usar en vistas: echo $base . '/images/...'
$base = BASE_URL;
