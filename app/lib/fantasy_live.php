<?php

declare(strict_types=1);

const FANTASY_LALIGA_API_LEAGUE_ID = 4335;
const FANTASY_CURRENT_SEASON = '2025-2026';

function fantasy_live_normalize(string $value): string
{
    $value = trim(mb_strtolower($value, 'UTF-8'));
    $trans = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    if ($trans !== false) {
        $value = $trans;
    }
    $value = str_replace(['.', '\'', '’', '-', '_'], ' ', $value);
    $value = preg_replace('/\b(fc|cf|rcd|ud|de|la|el|club|real|atletico|atletico de|atlético|atletico madrid|madrid|vigo)\b/u', ' $0 ', $value);
    $value = preg_replace('/[^a-z0-9]+/', '', $value);
    return $value ?? '';
}

function fantasy_live_name_tokens(string $value): array
{
    $value = trim(mb_strtolower($value, 'UTF-8'));
    $trans = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    if ($trans !== false) {
        $value = $trans;
    }
    $value = preg_replace('/[^a-z0-9]+/', ' ', $value);
    $value = trim((string)$value);
    if ($value === '') {
        return [];
    }

    return array_values(array_filter(explode(' ', $value), static fn(string $token): bool => $token !== ''));
}

function fantasy_live_team_map(): array
{
    return [
        'Real Madrid' => 'Real Madrid',
        'FC Barcelona' => 'Barcelona',
        'Atlético de Madrid' => 'Atlético Madrid',
        'Atletico de Madrid' => 'Atlético Madrid',
        'Sevilla FC' => 'Sevilla',
        'Real Betis' => 'Real Betis',
        'Valencia CF' => 'Valencia',
        'Athletic Club' => 'Athletic Bilbao',
        'Real Sociedad' => 'Real Sociedad',
        'Villarreal CF' => 'Villarreal',
        'CA Osasuna' => 'Osasuna',
        'Levante UD' => 'Levante',
        'Girona FC' => 'Girona',
        'Elche CF' => 'Elche',
        'RCD Espanyol' => 'Espanyol',
        'Getafe CF' => 'Getafe',
        'RCD Mallorca' => 'Mallorca',
        'RC Celta de Vigo' => 'Celta Vigo',
        'Deportivo Alavés' => 'Deportivo Alavés',
        'Real Oviedo' => 'Real Oviedo',
        'Rayo Vallecano' => 'Rayo Vallecano',
    ];
}

function fantasy_live_api_to_local_team_map(): array
{
    $result = [];
    foreach (fantasy_live_team_map() as $local => $api) {
        $result[fantasy_live_normalize($api)] = $local;
    }

    $result[fantasy_live_normalize('Celta')] = 'RC Celta de Vigo';
    $result[fantasy_live_normalize('Alaves')] = 'Deportivo Alavés';
    $result[fantasy_live_normalize('Atletico Madrid')] = 'Atlético de Madrid';

    return $result;
}

function fantasy_live_player_aliases(): array
{
    return [
        'viniciusjunior' => 'viniciusjr',
        'viniciusjosepaixaodeoliveirajunior' => 'viniciusjr',
        'aurelientchouameni' => 'aurelientchouameni',
        'ardaguler' => 'ardaguler',
        'aguler' => 'ardaguler',
        'ardag' => 'ardaguler',
        'a.guler' => 'ardaguler',
        'marcandreterstegen' => 'marcandreterstegen',
        'robertlewandowski' => 'robertlewandowski',
        'kylianmbappe' => 'kylianmbappe',
        'joangarcia' => 'joangarcia',
        'julianalvarez' => 'julianalvarez',
        'lamineyamal' => 'lamineyamal',
        'inakipena' => 'inakipena',
        'josemariagimenez' => 'josemariagimenez',
        'alexbaena' => 'alexbaena',
    ];
}

function fantasy_live_player_key(string $name): string
{
    $normalized = fantasy_live_normalize($name);
    $aliases = fantasy_live_player_aliases();
    return $aliases[$normalized] ?? $normalized;
}

function fantasy_live_fetch_json(string $url): ?array
{
    $response = false;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'LaLigaFantasyTFG/1.0',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);
        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($response === false || $httpCode >= 400) {
            $response = false;
        }
    }

    if ($response === false) {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 20,
                'header' => "User-Agent: LaLigaFantasyTFG/1.0\r\n",
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);
        $response = @file_get_contents($url, false, $context);
    }

    if ($response === false || $response === '') {
        return null;
    }

    $decoded = json_decode($response, true);
    return is_array($decoded) ? $decoded : null;
}

function fantasy_live_round_events(int $round, string $season = FANTASY_CURRENT_SEASON): array
{
    $url = sprintf(
        'https://www.thesportsdb.com/api/v1/json/123/eventsround.php?id=%d&r=%d&s=%s',
        FANTASY_LALIGA_API_LEAGUE_ID,
        $round,
        rawurlencode($season)
    );

    $data = fantasy_live_fetch_json($url);
    if (is_array($data) && !empty($data['events']) && is_array($data['events'])) {
        return $data['events'];
    }

    $fallbackUrl = sprintf(
        'https://www.thesportsdb.com/api/v1/json/123/eventsseason.php?id=%d&s=%s',
        FANTASY_LALIGA_API_LEAGUE_ID,
        rawurlencode($season)
    );
    $fallback = fantasy_live_fetch_json($fallbackUrl);
    if (!is_array($fallback) || empty($fallback['events']) || !is_array($fallback['events'])) {
        return [];
    }

    return array_values(array_filter(
        $fallback['events'],
        static fn(array $event): bool => (int)($event['intRound'] ?? 0) === $round
    ));
}

function fantasy_live_event_lineup(string $eventId): array
{
    $url = 'https://www.thesportsdb.com/api/v1/json/123/lookuplineup.php?id=' . rawurlencode($eventId);
    $data = fantasy_live_fetch_json($url);
    return is_array($data['lineup'] ?? null) ? $data['lineup'] : [];
}

function fantasy_live_event_timeline(string $eventId): array
{
    $url = 'https://www.thesportsdb.com/api/v1/json/123/lookuptimeline.php?id=' . rawurlencode($eventId);
    $data = fantasy_live_fetch_json($url);
    return is_array($data['timeline'] ?? null) ? $data['timeline'] : [];
}

function fantasy_live_event_stats(string $eventId): array
{
    $url = 'https://www.thesportsdb.com/api/v1/json/123/lookupeventstats.php?id=' . rawurlencode($eventId);
    $data = fantasy_live_fetch_json($url);
    return is_array($data['eventstats'] ?? null) ? $data['eventstats'] : [];
}

function fantasy_live_match_event(array $events, string $localHome, string $localAway): ?array
{
    $teamMap = fantasy_live_team_map();
    $apiHome = fantasy_live_normalize($teamMap[$localHome] ?? $localHome);
    $apiAway = fantasy_live_normalize($teamMap[$localAway] ?? $localAway);

    foreach ($events as $event) {
        $home = fantasy_live_normalize((string)($event['strHomeTeam'] ?? ''));
        $away = fantasy_live_normalize((string)($event['strAwayTeam'] ?? ''));
        if ($home === $apiHome && $away === $apiAway) {
            return $event;
        }
    }

    return null;
}
