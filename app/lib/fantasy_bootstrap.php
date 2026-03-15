<?php

declare(strict_types=1);

function fantasy_column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare("SHOW COLUMNS FROM `{$table}` LIKE ?");
    $stmt->execute([$column]);
    return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
}

function fantasy_ensure_schema(PDO $pdo): void
{
    static $bootstrapped = false;
    if ($bootstrapped) {
        return;
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS fantasy_player_match_stats (
        id INT AUTO_INCREMENT PRIMARY KEY,
        partido_id INT NOT NULL,
        jugador_id INT NOT NULL,
        api_player_id VARCHAR(32) DEFAULT NULL,
        nombre_api VARCHAR(120) DEFAULT NULL,
        titular BOOLEAN NOT NULL DEFAULT FALSE,
        ha_jugado BOOLEAN NOT NULL DEFAULT FALSE,
        minutos INT DEFAULT NULL,
        goles INT NOT NULL DEFAULT 0,
        asistencias INT NOT NULL DEFAULT 0,
        amarillas INT NOT NULL DEFAULT 0,
        rojas INT NOT NULL DEFAULT 0,
        autogoles INT NOT NULL DEFAULT 0,
        porteria_cero BOOLEAN NOT NULL DEFAULT FALSE,
        puntos INT NOT NULL DEFAULT 0,
        synced_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_partido_jugador (partido_id, jugador_id),
        KEY idx_fantasy_stats_jugador (jugador_id),
        KEY idx_fantasy_stats_partido (partido_id),
        CONSTRAINT fk_fantasy_stats_partido FOREIGN KEY (partido_id) REFERENCES partidos(id) ON DELETE CASCADE,
        CONSTRAINT fk_fantasy_stats_jugador FOREIGN KEY (jugador_id) REFERENCES jugadores(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS fantasy_market_value_jornada (
        jornada INT NOT NULL,
        jugador_id INT NOT NULL,
        delta_valor INT NOT NULL DEFAULT 0,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (jornada, jugador_id),
        KEY idx_market_value_jugador (jugador_id),
        CONSTRAINT fk_market_value_jugador FOREIGN KEY (jugador_id) REFERENCES jugadores(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    if (!fantasy_column_exists($pdo, 'partidos', 'api_event_id')) {
        $pdo->exec("ALTER TABLE partidos ADD COLUMN api_event_id VARCHAR(32) DEFAULT NULL AFTER goles_visitante");
    }
    if (!fantasy_column_exists($pdo, 'partidos', 'estado_partido')) {
        $pdo->exec("ALTER TABLE partidos ADD COLUMN estado_partido VARCHAR(50) NOT NULL DEFAULT 'Programado' AFTER api_event_id");
    }
    if (!fantasy_column_exists($pdo, 'partidos', 'ultima_sync')) {
        $pdo->exec("ALTER TABLE partidos ADD COLUMN ultima_sync DATETIME DEFAULT NULL AFTER estado_partido");
    }

    try {
        $pdo->exec("CREATE UNIQUE INDEX uq_partidos_api_event_id ON partidos(api_event_id)");
    } catch (PDOException $e) {
        // índice ya creado o varios NULL permitidos
    }

    try {
        $pdo->exec("ALTER TABLE usuarios_equipos ADD COLUMN formacion VARCHAR(20) NOT NULL DEFAULT '4-3-3'");
    } catch (PDOException $e) {
        // ya existe
    }

    $bootstrapped = true;
}
