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

if (!function_exists('asset_exists')) {
    function asset_exists(string $path): bool {
        $normalized = '/' . ltrim($path, '/');
        $docRoot = rtrim(str_replace('\\', '/', (string)($_SERVER['DOCUMENT_ROOT'] ?? '')), '/');

        $absoluteCandidates = [
            __DIR__ . $normalized,
            dirname(__DIR__) . $normalized,
        ];

        if ($docRoot !== '') {
            $absoluteCandidates[] = $docRoot . $normalized;
            $absoluteCandidates[] = $docRoot . '/app' . $normalized;
        }

        foreach ($absoluteCandidates as $absolute) {
            if (@is_file($absolute)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('theme_logo_url')) {
    function theme_logo_url(string $tema): string {
        if ($tema === 'tema-laliga') {
            $logoCandidates = [
                'images/LL_RGB_h_color.png',
                'images/ll_rgb_h_color.png',
                'images/LL_RGB_H_COLOR.png',
                'images/LL_RGB_h_color.PNG',
            ];

            foreach ($logoCandidates as $candidate) {
                if (asset_exists($candidate)) {
                    return asset_url($candidate);
                }
            }
        }

        return asset_url('images/favicon.png');
    }
}

// Alias corto para usar en vistas: echo $base . '/images/...'
$base = BASE_URL;
