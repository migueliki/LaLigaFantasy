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

if (!defined('DEFAULT_FORMATION')) {
    define('DEFAULT_FORMATION', '4-3-3');
}

if (!function_exists('normalize_asset_basename')) {
    function normalize_asset_basename(string $value): string {
        $normalized = strtolower($value);
        $normalized = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ñ', 'ü', 'ç', ' ', '-', '.', '\''],
            ['a', 'e', 'i', 'o', 'u', 'n', 'u', 'c', '_', '_', '', ''],
            $normalized
        );

        return preg_replace('/[^a-z0-9_]/', '', $normalized) ?? '';
    }
}

if (!function_exists('image_variant_url')) {
    function image_variant_url(string $folder, string $name, string $fallback): string {
        $basename = normalize_asset_basename($name);
        $relative = "images/{$folder}/{$basename}.png";

        if ($basename !== '' && asset_exists($relative)) {
            return asset_url($relative);
        }

        return asset_url($fallback);
    }
}

if (!function_exists('player_photo_url')) {
    function player_photo_url(string $name): string {
        return image_variant_url('jugadores', $name, 'images/jugadores/soccer-player-silhouette-free-png.png');
    }
}

if (!function_exists('coach_photo_url')) {
    function coach_photo_url(string $name): string {
        return image_variant_url(
            'entrenadores',
            $name,
            'images/entrenadores/silhouette-of-standing-man-with-hands-in-pockets-isolated-on-transparent-background-simple-black-illustration-suitable-for-design-business-or-presentation-concepts-png.png'
        );
    }
}

if (!function_exists('normalize_position_label')) {
    function normalize_position_label(string $position): string {
        $position = trim($position);
        return stripos($position, 'Extremo') !== false ? 'Delantero' : $position;
    }
}

if (!function_exists('app_formations')) {
    function app_formations(): array {
        static $formations = [
            '4-3-3' => ['Portero' => 1, 'Defensa' => 4, 'Centrocampista' => 3, 'Delantero' => 3],
            '4-4-2' => ['Portero' => 1, 'Defensa' => 4, 'Centrocampista' => 4, 'Delantero' => 2],
            '4-2-3-1' => ['Portero' => 1, 'Defensa' => 4, 'Centrocampista' => 5, 'Delantero' => 1],
            '3-5-2' => ['Portero' => 1, 'Defensa' => 3, 'Centrocampista' => 5, 'Delantero' => 2],
            '5-3-2' => ['Portero' => 1, 'Defensa' => 5, 'Centrocampista' => 3, 'Delantero' => 2],
            '4-1-4-1' => ['Portero' => 1, 'Defensa' => 4, 'Centrocampista' => 5, 'Delantero' => 1],
        ];

        return $formations;
    }
}

if (!function_exists('formation_slots')) {
    function formation_slots(?string $formation): array {
        $formations = app_formations();
        return $formations[$formation ?? ''] ?? $formations[DEFAULT_FORMATION];
    }
}

if (!function_exists('formation_total_slots')) {
    function formation_total_slots(?string $formation): int {
        return (int)array_sum(formation_slots($formation));
    }
}

if (!function_exists('normalize_user_lineup_slots')) {
    function normalize_user_lineup_slots(PDO $pdo, int $userId, int $maxSlots): void {
        $stmt = $pdo->prepare(
            "SELECT jugador_id, slot_titular
             FROM usuarios_jugadores
             WHERE usuario_id = ? AND es_titular = TRUE
             ORDER BY (slot_titular IS NULL), slot_titular ASC, jugador_id ASC"
        );
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $nextSlot = 1;
        $updateSlot = $pdo->prepare("UPDATE usuarios_jugadores SET slot_titular = ? WHERE usuario_id = ? AND jugador_id = ?");
        $moveToBench = $pdo->prepare("UPDATE usuarios_jugadores SET es_titular = 0, slot_titular = NULL WHERE usuario_id = ? AND jugador_id = ?");

        foreach ($rows as $row) {
            $playerId = (int)$row['jugador_id'];
            if ($nextSlot <= $maxSlots) {
                $updateSlot->execute([$nextSlot, $userId, $playerId]);
                $nextSlot++;
                continue;
            }

            $moveToBench->execute([$userId, $playerId]);
        }
    }
}

// Alias corto para usar en vistas: echo $base . '/images/...'
$base = BASE_URL;
