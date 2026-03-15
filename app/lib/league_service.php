<?php

declare(strict_types=1);

require_once __DIR__ . '/fantasy_bootstrap.php';
require_once __DIR__ . '/fantasy_live.php';

function fantasy_generate_invite_code(PDO $pdo): string
{
    do {
        $code = strtoupper(substr(bin2hex(random_bytes(6)), 0, 10));
        $stmt = $pdo->prepare('SELECT id FROM ligas WHERE codigo_invitacion = ? LIMIT 1');
        $stmt->execute([$code]);
    } while ($stmt->fetch(PDO::FETCH_ASSOC));

    return $code;
}

function fantasy_create_league(PDO $pdo, int $userId, string $name): array
{
    fantasy_ensure_schema($pdo);

    $name = trim($name);
    if ($name === '') {
        return ['ok' => false, 'mensaje' => 'El nombre de la liga es obligatorio.'];
    }
    if (mb_strlen($name, 'UTF-8') > 100) {
        return ['ok' => false, 'mensaje' => 'El nombre de la liga es demasiado largo.'];
    }

    try {
        $pdo->beginTransaction();
        $code = fantasy_generate_invite_code($pdo);

        $stmt = $pdo->prepare('INSERT INTO ligas (nombre, codigo_invitacion, creador_usuario_id) VALUES (?, ?, ?)');
        $stmt->execute([$name, $code, $userId]);
        $ligaId = (int)$pdo->lastInsertId();

        $stmt = $pdo->prepare('INSERT INTO ligas_usuarios (liga_id, usuario_id) VALUES (?, ?)');
        $stmt->execute([$ligaId, $userId]);

        $pdo->commit();
        return ['ok' => true, 'mensaje' => 'Liga creada correctamente.', 'liga_id' => $ligaId, 'codigo' => $code];
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['ok' => false, 'mensaje' => 'No se pudo crear la liga.'];
    }
}

function fantasy_join_league(PDO $pdo, int $userId, string $code): array
{
    fantasy_ensure_schema($pdo);

    $code = strtoupper(trim($code));
    if ($code === '') {
        return ['ok' => false, 'mensaje' => 'El código de invitación es obligatorio.'];
    }

    $stmt = $pdo->prepare('SELECT id, nombre FROM ligas WHERE codigo_invitacion = ? LIMIT 1');
    $stmt->execute([$code]);
    $liga = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$liga) {
        return ['ok' => false, 'mensaje' => 'No existe ninguna liga con ese código.'];
    }

    $stmt = $pdo->prepare('SELECT id FROM ligas_usuarios WHERE liga_id = ? AND usuario_id = ? LIMIT 1');
    $stmt->execute([(int)$liga['id'], $userId]);
    if ($stmt->fetch(PDO::FETCH_ASSOC)) {
        return ['ok' => false, 'mensaje' => 'Ya formas parte de esa liga.'];
    }

    try {
        $stmt = $pdo->prepare('INSERT INTO ligas_usuarios (liga_id, usuario_id) VALUES (?, ?)');
        $stmt->execute([(int)$liga['id'], $userId]);
        return ['ok' => true, 'mensaje' => 'Te has unido a la liga ' . $liga['nombre'] . '.', 'liga_id' => (int)$liga['id']];
    } catch (PDOException $e) {
        return ['ok' => false, 'mensaje' => 'No se pudo completar la unión a la liga.'];
    }
}

function fantasy_delete_league(PDO $pdo, int $userId, int $leagueId): array
{
    fantasy_ensure_schema($pdo);

    if ($leagueId <= 0) {
        return ['ok' => false, 'mensaje' => 'Liga no válida.'];
    }

    $stmt = $pdo->prepare('SELECT id, nombre, creador_usuario_id FROM ligas WHERE id = ? LIMIT 1');
    $stmt->execute([$leagueId]);
    $liga = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$liga) {
        return ['ok' => false, 'mensaje' => 'La liga ya no existe.'];
    }

    if ((int)$liga['creador_usuario_id'] !== $userId) {
        return ['ok' => false, 'mensaje' => 'Solo el creador puede eliminar esta liga.'];
    }

    try {
        $pdo->beginTransaction();
        $delete = $pdo->prepare('DELETE FROM ligas WHERE id = ? AND creador_usuario_id = ?');
        $delete->execute([$leagueId, $userId]);

        if ($delete->rowCount() < 1) {
            $pdo->rollBack();
            return ['ok' => false, 'mensaje' => 'No se pudo eliminar la liga.'];
        }

        $pdo->commit();
        return ['ok' => true, 'mensaje' => 'Liga eliminada correctamente: ' . (string)$liga['nombre'] . '.'];
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['ok' => false, 'mensaje' => 'No se pudo eliminar la liga.'];
    }
}

function fantasy_get_current_jornada(PDO $pdo): int
{
    $stmt = $pdo->prepare('SELECT jornada FROM partidos WHERE DATE(fecha_hora) >= ? ORDER BY fecha_hora ASC LIMIT 1');
    $stmt->execute([date('Y-m-d')]);
    $jornada = (int)$stmt->fetchColumn();

    if ($jornada > 0) {
        return $jornada;
    }

    return (int)$pdo->query('SELECT COALESCE(MAX(jornada), 1) FROM partidos')->fetchColumn();
}

function fantasy_normalize_position(string $position): string
{
    $position = trim($position);
    if (stripos($position, 'Extremo') !== false) {
        return 'Delantero';
    }
    return $position;
}

function fantasy_points_for_player(array $stat, string $position): int
{
    $position = fantasy_normalize_position($position);
    $goles = (int)($stat['goles'] ?? 0);
    $asistencias = (int)($stat['asistencias'] ?? 0);
    $amarillas = (int)($stat['amarillas'] ?? 0);
    $rojas = (int)($stat['rojas'] ?? 0);
    $autogoles = (int)($stat['autogoles'] ?? 0);
    $porteriaCero = !empty($stat['porteria_cero']);

    $goalPoints = [
        'Portero' => 6,
        'Defensa' => 6,
        'Centrocampista' => 5,
        'Delantero' => 4,
    ];

    $points = 0;
    if (!empty($stat['ha_jugado'])) {
        $points += !empty($stat['titular']) ? 2 : 1;
    }

    $points += $goles * ($goalPoints[$position] ?? 4);
    $points += $asistencias * 3;
    $points -= $amarillas;
    $points -= $rojas * 3;
    $points -= $autogoles * 2;

    if ($porteriaCero && in_array($position, ['Portero', 'Defensa'], true) && !empty($stat['titular'])) {
        $points += 4;
    }

    $soloAmarillas = $amarillas > 0
        && $goles === 0
        && $asistencias === 0
        && $rojas === 0
        && $autogoles === 0
        && !$porteriaCero;

    if ($soloAmarillas) {
        $points = min($points, -$amarillas);
    }

    return $points;
}

function fantasy_resolve_local_player(string $apiName, string $localTeam, array $playersByTeam): ?array
{
    $teamKey = fantasy_live_normalize($localTeam);
    if (!isset($playersByTeam[$teamKey])) {
        return null;
    }

    $needle = fantasy_live_player_key($apiName);
    $teamPlayers = $playersByTeam[$teamKey];

    if (isset($teamPlayers[$needle])) {
        return $teamPlayers[$needle];
    }

    $best = null;
    $bestScore = PHP_INT_MAX;
    foreach ($teamPlayers as $candidateKey => $player) {
        if ($candidateKey === '') {
            continue;
        }

        if (str_contains($candidateKey, $needle) || str_contains($needle, $candidateKey)) {
            return $player;
        }

        $apiTokens = fantasy_live_name_tokens($apiName);
        $candidateTokens = fantasy_live_name_tokens((string)($player['nombre'] ?? ''));
        if (!empty($apiTokens) && !empty($candidateTokens)) {
            $apiLast = $apiTokens[count($apiTokens) - 1];
            $candidateLast = $candidateTokens[count($candidateTokens) - 1];
            if ($apiLast !== '' && $apiLast === $candidateLast) {
                $apiFirst = $apiTokens[0];
                $candidateFirst = $candidateTokens[0];
                if ($apiFirst === $candidateFirst) {
                    return $player;
                }
                if (strlen($apiFirst) === 1 && str_starts_with($candidateFirst, $apiFirst)) {
                    return $player;
                }
            }
        }

        $distance = levenshtein($needle, $candidateKey);
        if ($distance < $bestScore) {
            $bestScore = $distance;
            $best = $player;
        }
    }

    return $bestScore <= 3 ? $best : null;
}

function fantasy_build_match_player_lookup(PDO $pdo, string $localHome, string $localAway): array
{
    $stmt = $pdo->prepare(
        'SELECT j.id, j.nombre, j.posicion, e.nombre AS equipo_nombre
         FROM jugadores j
         JOIN equipos e ON e.id = j.equipo_id
         WHERE e.nombre IN (?, ?)' 
    );
    $stmt->execute([$localHome, $localAway]);

    $lookup = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $teamKey = fantasy_live_normalize($row['equipo_nombre']);
        $playerKey = fantasy_live_player_key((string)$row['nombre']);
        $lookup[$teamKey][$playerKey] = $row;
    }

    return $lookup;
}

function fantasy_mark_substitution_participants(array &$statsMap, array $event, array $apiToLocalTeams, array $playersByTeam): void
{
    $teamName = (string)($event['strTeam'] ?? '');
    $localTeam = $apiToLocalTeams[fantasy_live_normalize($teamName)] ?? null;
    if ($localTeam === null) {
        return;
    }

    $outgoing = fantasy_resolve_local_player((string)($event['strPlayer'] ?? ''), $localTeam, $playersByTeam);
    if ($outgoing) {
        $playerId = (int)$outgoing['id'];
        if (!isset($statsMap[$playerId])) {
            $statsMap[$playerId] = [
                'jugador_id' => $playerId,
                'api_player_id' => null,
                'nombre_api' => (string)($event['strPlayer'] ?? ''),
                'titular' => false,
                'ha_jugado' => true,
                'goles' => 0,
                'asistencias' => 0,
                'amarillas' => 0,
                'rojas' => 0,
                'autogoles' => 0,
                'porteria_cero' => false,
                'posicion' => (string)($outgoing['posicion'] ?? ''),
            ];
        }
        $statsMap[$playerId]['ha_jugado'] = true;
    }

    $incomingName = trim((string)($event['strAssist'] ?? ''));
    if ($incomingName !== '' && $incomingName !== '0') {
        $incoming = fantasy_resolve_local_player($incomingName, $localTeam, $playersByTeam);
        if ($incoming) {
            $playerId = (int)$incoming['id'];
            if (!isset($statsMap[$playerId])) {
                $statsMap[$playerId] = [
                    'jugador_id' => $playerId,
                    'api_player_id' => null,
                    'nombre_api' => $incomingName,
                    'titular' => false,
                    'ha_jugado' => true,
                    'goles' => 0,
                    'asistencias' => 0,
                    'amarillas' => 0,
                    'rojas' => 0,
                    'autogoles' => 0,
                    'porteria_cero' => false,
                    'posicion' => (string)$incoming['posicion'],
                ];
            }
            $statsMap[$playerId]['ha_jugado'] = true;
        }
    }
}

function fantasy_apply_timeline_to_stats(array &$statsMap, array $timeline, array $apiToLocalTeams, array $playersByTeam): void
{
    foreach ($timeline as $event) {
        $teamName = (string)($event['strTeam'] ?? '');
        $localTeam = $apiToLocalTeams[fantasy_live_normalize($teamName)] ?? null;
        if ($localTeam === null) {
            continue;
        }

        $timelineType = mb_strtolower((string)($event['strTimeline'] ?? ''), 'UTF-8');
        $timelineDetail = mb_strtolower((string)($event['strTimelineDetail'] ?? ''), 'UTF-8');

        if ($timelineType === 'subst') {
            fantasy_mark_substitution_participants($statsMap, $event, $apiToLocalTeams, $playersByTeam);
            continue;
        }

        $player = fantasy_resolve_local_player((string)($event['strPlayer'] ?? ''), $localTeam, $playersByTeam);
        if (!$player) {
            continue;
        }

        $playerId = (int)$player['id'];
        if (!isset($statsMap[$playerId])) {
            $statsMap[$playerId] = [
                'jugador_id' => $playerId,
                'api_player_id' => (string)($event['idPlayer'] ?? ''),
                'nombre_api' => (string)($event['strPlayer'] ?? ''),
                'titular' => false,
                'ha_jugado' => true,
                'goles' => 0,
                'asistencias' => 0,
                'amarillas' => 0,
                'rojas' => 0,
                'autogoles' => 0,
                'porteria_cero' => false,
                'posicion' => (string)$player['posicion'],
            ];
        }

        $statsMap[$playerId]['ha_jugado'] = true;
        $statsMap[$playerId]['api_player_id'] = (string)($event['idPlayer'] ?? ($statsMap[$playerId]['api_player_id'] ?? ''));
        $statsMap[$playerId]['nombre_api'] = (string)($event['strPlayer'] ?? ($statsMap[$playerId]['nombre_api'] ?? ''));

        if ($timelineType === 'goal') {
            if (str_contains($timelineDetail, 'own')) {
                $statsMap[$playerId]['autogoles']++;
            } else {
                $statsMap[$playerId]['goles']++;
                $assistName = trim((string)($event['strAssist'] ?? ''));
                if ($assistName !== '' && $assistName !== '0') {
                    $assist = fantasy_resolve_local_player($assistName, $localTeam, $playersByTeam);
                    if ($assist) {
                        $assistId = (int)$assist['id'];
                        if (!isset($statsMap[$assistId])) {
                            $statsMap[$assistId] = [
                                'jugador_id' => $assistId,
                                'api_player_id' => (string)($event['idAssist'] ?? ''),
                                'nombre_api' => $assistName,
                                'titular' => false,
                                'ha_jugado' => true,
                                'goles' => 0,
                                'asistencias' => 0,
                                'amarillas' => 0,
                                'rojas' => 0,
                                'autogoles' => 0,
                                'porteria_cero' => false,
                                'posicion' => (string)$assist['posicion'],
                            ];
                        }
                        $statsMap[$assistId]['ha_jugado'] = true;
                        $statsMap[$assistId]['asistencias']++;
                    }
                }
            }
            continue;
        }

        if ($timelineType === 'card') {
            if (str_contains($timelineDetail, 'yellow')) {
                $statsMap[$playerId]['amarillas']++;
            } elseif (str_contains($timelineDetail, 'red')) {
                $statsMap[$playerId]['rojas']++;
            }
        }
    }
}

function fantasy_recalculate_jornada_points(PDO $pdo, int $jornada): void
{
    $stmtUsers = $pdo->query('SELECT id FROM register');
    $userIds = $stmtUsers->fetchAll(PDO::FETCH_COLUMN);

    $stmtScore = $pdo->prepare(
        'SELECT COALESCE(SUM(f.puntos), 0)
         FROM usuarios_jugadores uj
         JOIN fantasy_player_match_stats f ON f.jugador_id = uj.jugador_id
         JOIN partidos p ON p.id = f.partido_id
         WHERE uj.usuario_id = ?
           AND uj.es_titular = TRUE
           AND p.jornada = ?'
    );

    $stmtUpsert = $pdo->prepare(
        'INSERT INTO puntos_jornada (usuario_id, jornada, puntos_jornada, puntos_totales)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE puntos_jornada = VALUES(puntos_jornada), puntos_totales = VALUES(puntos_totales)'
    );

    $stmtTotal = $pdo->prepare('SELECT COALESCE(SUM(puntos_jornada), 0) FROM puntos_jornada WHERE usuario_id = ?');

    foreach ($userIds as $userId) {
        $userId = (int)$userId;
        $stmtScore->execute([$userId, $jornada]);
        $jornadaPoints = (int)$stmtScore->fetchColumn();

        $stmtTotal->execute([$userId]);
        $currentTotal = (int)$stmtTotal->fetchColumn();

        $existing = $pdo->prepare('SELECT puntos_jornada FROM puntos_jornada WHERE usuario_id = ? AND jornada = ? LIMIT 1');
        $existing->execute([$userId, $jornada]);
        $previous = (int)($existing->fetchColumn() ?: 0);
        $newTotal = $currentTotal - $previous + $jornadaPoints;

        $stmtUpsert->execute([$userId, $jornada, $jornadaPoints, $newTotal]);
    }
}

function fantasy_market_delta_for_stat(array $stat): int
{
    $puntos = (int)($stat['puntos'] ?? 0);
    $goles = (int)($stat['goles'] ?? 0);
    $asistencias = (int)($stat['asistencias'] ?? 0);
    $amarillas = (int)($stat['amarillas'] ?? 0);
    $rojas = (int)($stat['rojas'] ?? 0);
    $autogoles = (int)($stat['autogoles'] ?? 0);
    $porteriaCero = !empty($stat['porteria_cero']) ? 1 : 0;

    $delta = 0;
    $delta += $puntos * 90000;
    $delta += $goles * 40000;
    $delta += $asistencias * 30000;
    $delta += $porteriaCero * 25000;
    $delta -= $amarillas * 15000;
    $delta -= $rojas * 50000;
    $delta -= $autogoles * 40000;

    if ($delta > 1200000) {
        return 1200000;
    }
    if ($delta < -1200000) {
        return -1200000;
    }

    return $delta;
}

function fantasy_recalculate_market_values(PDO $pdo, int $jornada): int
{
    $pdo->exec(
        "INSERT INTO mercado (jugador_id, precio, disponible)
         SELECT j.id,
                CASE
                    WHEN j.media_fifa >= 90 THEN 22000000
                    WHEN j.media_fifa >= 85 THEN 15000000
                    WHEN j.media_fifa >= 80 THEN 9000000
                    WHEN j.media_fifa >= 75 THEN 5500000
                    ELSE 3000000
                END AS precio,
                TRUE
         FROM jugadores j
         LEFT JOIN mercado m ON m.jugador_id = j.id
         WHERE m.jugador_id IS NULL"
    );

    $stmtStats = $pdo->prepare(
        'SELECT f.jugador_id,
                COALESCE(SUM(f.goles), 0) AS goles,
                COALESCE(SUM(f.asistencias), 0) AS asistencias,
                COALESCE(SUM(f.amarillas), 0) AS amarillas,
                COALESCE(SUM(f.rojas), 0) AS rojas,
                COALESCE(SUM(f.autogoles), 0) AS autogoles,
                MAX(f.porteria_cero) AS porteria_cero,
                COALESCE(SUM(f.puntos), 0) AS puntos
         FROM fantasy_player_match_stats f
         JOIN partidos p ON p.id = f.partido_id
         WHERE p.jornada = ?
         GROUP BY f.jugador_id'
    );
    $stmtStats->execute([$jornada]);

    $proposed = [];
    while ($row = $stmtStats->fetch(PDO::FETCH_ASSOC)) {
        $playerId = (int)$row['jugador_id'];
        $proposed[$playerId] = fantasy_market_delta_for_stat($row);
    }

    $stmtExisting = $pdo->prepare('SELECT jugador_id, delta_valor FROM fantasy_market_value_jornada WHERE jornada = ?');
    $stmtExisting->execute([$jornada]);
    $existing = [];
    while ($row = $stmtExisting->fetch(PDO::FETCH_ASSOC)) {
        $existing[(int)$row['jugador_id']] = (int)$row['delta_valor'];
    }

    $updatePrice = $pdo->prepare('UPDATE mercado SET precio = GREATEST(500000, precio + ?) WHERE jugador_id = ?');
    $upsertDelta = $pdo->prepare(
        'INSERT INTO fantasy_market_value_jornada (jornada, jugador_id, delta_valor)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE delta_valor = VALUES(delta_valor), updated_at = NOW()'
    );
    $deleteDelta = $pdo->prepare('DELETE FROM fantasy_market_value_jornada WHERE jornada = ? AND jugador_id = ?');

    $adjusted = 0;
    $allPlayerIds = array_unique(array_merge(array_keys($proposed), array_keys($existing)));

    foreach ($allPlayerIds as $playerId) {
        $newDelta = (int)($proposed[$playerId] ?? 0);
        $oldDelta = (int)($existing[$playerId] ?? 0);
        $diff = $newDelta - $oldDelta;

        if ($diff !== 0) {
            $updatePrice->execute([$diff, (int)$playerId]);
            $adjusted++;
        }

        if ($newDelta === 0) {
            if (isset($existing[$playerId])) {
                $deleteDelta->execute([$jornada, (int)$playerId]);
            }
            continue;
        }

        $upsertDelta->execute([$jornada, (int)$playerId, $newDelta]);
    }

    return $adjusted;
}

function fantasy_sync_jornada(PDO $pdo, int $jornada): array
{
    fantasy_ensure_schema($pdo);

    $events = fantasy_live_round_events($jornada);
    if ($events === []) {
        return ['ok' => false, 'mensaje' => 'No hay cambios nuevos en esta jornada. Los datos ya están sincronizados o no hay feed en directo disponible ahora mismo.'];
    }

    $stmtMatches = $pdo->prepare(
        'SELECT p.id, p.jornada, el.nombre AS local_nombre, ev.nombre AS visitante_nombre
         FROM partidos p
         JOIN equipos el ON el.id = p.equipo_local_id
         JOIN equipos ev ON ev.id = p.equipo_visitante_id
         WHERE p.jornada = ?
         ORDER BY p.fecha_hora, p.id'
    );
    $stmtMatches->execute([$jornada]);
    $matches = $stmtMatches->fetchAll(PDO::FETCH_ASSOC);

    if (!$matches) {
        return ['ok' => false, 'mensaje' => 'No hay partidos cargados para esa jornada.'];
    }

    $apiToLocalTeams = fantasy_live_api_to_local_team_map();
    $updateMatch = $pdo->prepare(
        'UPDATE partidos
         SET goles_local = ?, goles_visitante = ?, api_event_id = ?, estado_partido = ?, ultima_sync = NOW()
         WHERE id = ?'
    );
    $deleteStats = $pdo->prepare('DELETE FROM fantasy_player_match_stats WHERE partido_id = ?');
    $insertStats = $pdo->prepare(
        'INSERT INTO fantasy_player_match_stats
         (partido_id, jugador_id, api_player_id, nombre_api, titular, ha_jugado, minutos, goles, asistencias, amarillas, rojas, autogoles, porteria_cero, puntos, synced_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
    );

    $syncedMatches = 0;
    $syncedPlayers = 0;
    $adjustedMarketValues = 0;

    try {
        $pdo->beginTransaction();

        foreach ($matches as $match) {
            $event = fantasy_live_match_event($events, (string)$match['local_nombre'], (string)$match['visitante_nombre']);
            if ($event === null) {
                continue;
            }

            $eventId = (string)($event['idEvent'] ?? '');
            $homeGoals = isset($event['intHomeScore']) && $event['intHomeScore'] !== '' ? (int)$event['intHomeScore'] : null;
            $awayGoals = isset($event['intAwayScore']) && $event['intAwayScore'] !== '' ? (int)$event['intAwayScore'] : null;
            $status = (string)($event['strStatus'] ?? 'Programado');

            $updateMatch->execute([$homeGoals, $awayGoals, $eventId !== '' ? $eventId : null, $status !== '' ? $status : 'Programado', (int)$match['id']]);

            $deleteStats->execute([(int)$match['id']]);

            if ($eventId === '') {
                continue;
            }

            $playersByTeam = fantasy_build_match_player_lookup($pdo, (string)$match['local_nombre'], (string)$match['visitante_nombre']);
            $lineup = fantasy_live_event_lineup($eventId);
            $timeline = fantasy_live_event_timeline($eventId);

            $statsMap = [];
            foreach ($lineup as $row) {
                $apiTeam = (string)($row['strTeam'] ?? '');
                $localTeam = $apiToLocalTeams[fantasy_live_normalize($apiTeam)] ?? null;
                if ($localTeam === null) {
                    continue;
                }

                $player = fantasy_resolve_local_player((string)($row['strPlayer'] ?? ''), $localTeam, $playersByTeam);
                if (!$player) {
                    continue;
                }

                $playerId = (int)$player['id'];
                $isStarter = (($row['strSubstitute'] ?? 'No') !== 'Yes');
                $statsMap[$playerId] = [
                    'jugador_id' => $playerId,
                    'api_player_id' => (string)($row['idPlayer'] ?? ''),
                    'nombre_api' => (string)($row['strPlayer'] ?? ''),
                    'titular' => $isStarter,
                    'ha_jugado' => $isStarter,
                    'goles' => 0,
                    'asistencias' => 0,
                    'amarillas' => 0,
                    'rojas' => 0,
                    'autogoles' => 0,
                    'porteria_cero' => false,
                    'posicion' => (string)$player['posicion'],
                ];
            }

            fantasy_apply_timeline_to_stats($statsMap, $timeline, $apiToLocalTeams, $playersByTeam);

            foreach ($statsMap as $playerId => &$stat) {
                $stat += [
                    'jugador_id' => (int)$playerId,
                    'api_player_id' => null,
                    'nombre_api' => null,
                    'titular' => false,
                    'ha_jugado' => false,
                    'goles' => 0,
                    'asistencias' => 0,
                    'amarillas' => 0,
                    'rojas' => 0,
                    'autogoles' => 0,
                    'porteria_cero' => false,
                    'posicion' => '',
                ];

                $teamName = null;
                foreach ($playersByTeam as $teamPlayers) {
                    foreach ($teamPlayers as $candidate) {
                        if ((int)$candidate['id'] === (int)$playerId) {
                            $teamName = (string)$candidate['equipo_nombre'];
                            if ((string)$stat['posicion'] === '' && isset($candidate['posicion'])) {
                                $stat['posicion'] = (string)$candidate['posicion'];
                            }
                            break 2;
                        }
                    }
                }

                $isHomeTeam = $teamName === $match['local_nombre'];
                $conceded = $isHomeTeam ? ($awayGoals ?? null) : ($homeGoals ?? null);
                $posicion = fantasy_normalize_position((string)($stat['posicion'] ?? ''));
                $eligiblePorteriaCero = in_array($posicion, ['Portero', 'Defensa'], true) && !empty($stat['titular']);
                $stat['porteria_cero'] = $eligiblePorteriaCero && $conceded === 0 && !empty($stat['ha_jugado']);
                $stat['puntos'] = fantasy_points_for_player($stat, (string)($stat['posicion'] ?? ''));

                $insertStats->execute([
                    (int)$match['id'],
                    (int)$playerId,
                    $stat['api_player_id'] !== '' ? $stat['api_player_id'] : null,
                    $stat['nombre_api'] !== '' ? $stat['nombre_api'] : null,
                    !empty($stat['titular']) ? 1 : 0,
                    !empty($stat['ha_jugado']) ? 1 : 0,
                    !empty($stat['ha_jugado']) ? 90 : null,
                    (int)$stat['goles'],
                    (int)$stat['asistencias'],
                    (int)$stat['amarillas'],
                    (int)$stat['rojas'],
                    (int)$stat['autogoles'],
                    !empty($stat['porteria_cero']) ? 1 : 0,
                    (int)$stat['puntos'],
                ]);
                $syncedPlayers++;
            }
            unset($stat);

            $syncedMatches++;
        }

        fantasy_recalculate_jornada_points($pdo, $jornada);
        $adjustedMarketValues = fantasy_recalculate_market_values($pdo, $jornada);
        $pdo->commit();

        return [
            'ok' => true,
            'mensaje' => 'Jornada sincronizada correctamente.',
            'partidos' => $syncedMatches,
            'jugadores' => $syncedPlayers,
            'valores_ajustados' => $adjustedMarketValues,
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        return ['ok' => false, 'mensaje' => 'No se pudo sincronizar la jornada.'];
    }
}

function fantasy_sync_jornada_if_due(PDO $pdo, int $jornada, int $cooldownSeconds = 90): array
{
    fantasy_ensure_schema($pdo);

    $stmt = $pdo->prepare('SELECT MAX(ultima_sync) FROM partidos WHERE jornada = ?');
    $stmt->execute([$jornada]);
    $lastSync = $stmt->fetchColumn();

    if ($lastSync) {
        $elapsed = time() - strtotime((string)$lastSync);
        if ($elapsed >= 0 && $elapsed < $cooldownSeconds) {
            return [
                'ok' => true,
                'synced' => false,
                'mensaje' => 'Cooldown activo.',
                'ultima_sync' => $lastSync,
                'cooldown_restante' => max(0, $cooldownSeconds - $elapsed),
            ];
        }
    }

    $result = fantasy_sync_jornada($pdo, $jornada);
    if (!isset($result['synced'])) {
        $result['synced'] = !empty($result['ok']);
    }
    return $result;
}
