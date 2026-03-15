<?php
ob_start();
session_start();
require_once 'config.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . BASE_URL . '/index.php');
    exit();
}

$timeout = 3600;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
    session_unset();
    session_destroy();
    header('Location: ' . BASE_URL . '/index.php?timeout=1');
    exit();
}
$_SESSION['last_activity'] = time();

require_once 'conexion.php';
require_once 'csrf.php';
include_once 'cookie_tema.php';
require_once 'lib/fantasy_bootstrap.php';
require_once 'lib/league_service.php';

fantasy_ensure_schema($pdo);

$usuarioId = (int)$_SESSION['usuario_id'];
$currentJornada = fantasy_get_current_jornada($pdo);
$selectedJornada = isset($_GET['jornada']) ? max(1, min(38, (int)$_GET['jornada'])) : $currentJornada;

// Auto-sync al entrar: actualiza la jornada actual y la seleccionada (si es distinta)
fantasy_sync_jornada_if_due($pdo, $currentJornada, 90);
if ($selectedJornada !== $currentJornada) {
    fantasy_sync_jornada_if_due($pdo, $selectedJornada, 90);
}

$autoLiveHabilitado = $selectedJornada === $currentJornada;
$autoLiveSegundos = 45;
$flashMessage = trim((string)($_GET['msg'] ?? ''));
$flashType = ($_GET['type'] ?? 'success') === 'error' ? 'error' : 'success';

$stmtLeagues = $pdo->prepare(
    'SELECT l.id, l.nombre, l.codigo_invitacion, l.creador_usuario_id, l.created_at,
            COUNT(lu2.usuario_id) AS miembros
     FROM ligas_usuarios lu
     JOIN ligas l ON l.id = lu.liga_id
     LEFT JOIN ligas_usuarios lu2 ON lu2.liga_id = l.id
     WHERE lu.usuario_id = ?
     GROUP BY l.id, l.nombre, l.codigo_invitacion, l.creador_usuario_id, l.created_at
     ORDER BY l.created_at DESC'
);
$stmtLeagues->execute([$usuarioId]);
$ligas = $stmtLeagues->fetchAll(PDO::FETCH_ASSOC);

$selectedLeagueId = isset($_GET['liga']) ? (int)$_GET['liga'] : (isset($ligas[0]['id']) ? (int)$ligas[0]['id'] : 0);
$ligaSeleccionada = null;
foreach ($ligas as $liga) {
    if ((int)$liga['id'] === $selectedLeagueId) {
        $ligaSeleccionada = $liga;
        break;
    }
}
if ($ligaSeleccionada === null && isset($ligas[0])) {
    $ligaSeleccionada = $ligas[0];
    $selectedLeagueId = (int)$ligaSeleccionada['id'];
}

$miEquipoStmt = $pdo->prepare('SELECT nombre_equipo FROM usuarios_equipos WHERE usuario_id = ? LIMIT 1');
$miEquipoStmt->execute([$usuarioId]);
$miEquipoNombre = (string)($miEquipoStmt->fetchColumn() ?: 'Mi equipo');

$totalLigas = count($ligas);
$miembrosTotales = 0;
foreach ($ligas as $liga) {
    $miembrosTotales += (int)$liga['miembros'];
}

$ranking = [];
$miPosicion = null;
$ultimaSync = null;
$partidosSincronizados = 0;
$miJornada = [];
$miJornadaTotal = 0;

if ($selectedLeagueId > 0) {
    $stmtRanking = $pdo->prepare(
        'SELECT r.id AS usuario_id,
                r.username,
                COALESCE(ue.nombre_equipo, CONCAT("Equipo de ", r.username)) AS nombre_equipo,
                COALESCE(SUM(pj.puntos_jornada), 0) AS puntos_totales,
                COALESCE(MAX(CASE WHEN pj.jornada = ? THEN pj.puntos_jornada END), 0) AS puntos_jornada
         FROM ligas_usuarios lu
         JOIN register r ON r.id = lu.usuario_id
         LEFT JOIN usuarios_equipos ue ON ue.usuario_id = r.id
         LEFT JOIN puntos_jornada pj ON pj.usuario_id = r.id
         WHERE lu.liga_id = ?
         GROUP BY r.id, r.username, ue.nombre_equipo
         ORDER BY puntos_totales DESC, puntos_jornada DESC, r.username ASC'
    );
    $stmtRanking->execute([$selectedJornada, $selectedLeagueId]);
    $ranking = $stmtRanking->fetchAll(PDO::FETCH_ASSOC);

    foreach ($ranking as $index => $fila) {
        if ((int)$fila['usuario_id'] === $usuarioId) {
            $miPosicion = $index + 1;
            break;
        }
    }

    $stmtSync = $pdo->prepare('SELECT MAX(ultima_sync) AS ultima_sync, COUNT(*) AS total FROM partidos WHERE jornada = ? AND ultima_sync IS NOT NULL');
    $stmtSync->execute([$selectedJornada]);
    $syncData = $stmtSync->fetch(PDO::FETCH_ASSOC) ?: [];
    $ultimaSync = $syncData['ultima_sync'] ?? null;
    $partidosSincronizados = (int)($syncData['total'] ?? 0);

    $stmtMiJornada = $pdo->prepare(
        'SELECT j.nombre,
                j.posicion,
                e.nombre AS equipo_nombre,
                COALESCE(f.ha_jugado, 0) AS ha_jugado,
                COALESCE(f.titular, 0) AS titular,
                COALESCE(f.goles, 0) AS goles,
                COALESCE(f.asistencias, 0) AS asistencias,
                COALESCE(f.amarillas, 0) AS amarillas,
                COALESCE(f.rojas, 0) AS rojas,
                COALESCE(f.autogoles, 0) AS autogoles,
                COALESCE(f.porteria_cero, 0) AS porteria_cero,
                COALESCE(f.puntos, 0) AS puntos
         FROM usuarios_jugadores uj
         JOIN jugadores j ON j.id = uj.jugador_id
         LEFT JOIN equipos e ON e.id = j.equipo_id
         LEFT JOIN (
             SELECT f.*
             FROM fantasy_player_match_stats f
             JOIN partidos p2 ON p2.id = f.partido_id
             WHERE p2.jornada = ?
         ) f ON f.jugador_id = j.id
         WHERE uj.usuario_id = ? AND uj.es_titular = TRUE
         ORDER BY FIELD(j.posicion, "Portero", "Defensa", "Centrocampista", "Extremo", "Delantero"), j.nombre'
    );
    $stmtMiJornada->execute([$selectedJornada, $usuarioId]);
    $miJornada = $stmtMiJornada->fetchAll(PDO::FETCH_ASSOC);
    foreach ($miJornada as $fila) {
        $miJornadaTotal += (int)$fila['puntos'];
    }
}

function liga_pill_class(array $fila): string {
    $puntos = (int)$fila['puntos'];
    if ($puntos > 0) {
        return 'liga-pill-positive';
    }
    if ($puntos < 0) {
        return 'liga-pill-negative';
    }
    return 'liga-pill-neutral';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicio - LaLiga Fantasy</title>
    <meta name="description" content="Panel principal de LaLigaFantasy: crea ligas privadas, únete con código y sincroniza puntuaciones.">
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>/images/<?= $clase_tema === 'tema-laliga' ? 'LL_RGB_h_color.png' : 'favicon.png' ?>">
    <link rel="shortcut icon" href="<?= BASE_URL ?>/images/<?= $clase_tema === 'tema-laliga' ? 'LL_RGB_h_color.png' : 'favicon.png' ?>" type="image/x-icon">
    <link rel="stylesheet" href="css/inicio.css">
    <link rel="stylesheet" href="css/cookie_tema.css">
    <link rel="stylesheet" href="css/ligas.css">
</head>
<body class="<?= htmlspecialchars($clase_tema) ?> ligas-page">

<?php $ligaLogoSrc = BASE_URL . '/images/' . ($clase_tema === 'tema-laliga' ? 'LL_RGB_h_color.png' : 'favicon.png'); ?>

<div class="navegacion">
    <nav>
        <a href="<?= BASE_URL ?>/inicio.php" class="nav-active">Inicio</a>
        <a href="<?= BASE_URL ?>/pages/equipos.php">Equipos</a>
        <a href="<?= BASE_URL ?>/pages/calendario.php">Calendario</a>
        <a href="<?= BASE_URL ?>/pages/plantilla.php">Plantilla</a>
        <a href="<?= BASE_URL ?>/pages/mercado.php">Mercado</a>
        <a href="<?= BASE_URL ?>/pages/noticias.php">Noticias</a>
        <a href="<?= BASE_URL ?>/logout.php">Cerrar Sesión</a>
    </nav>
</div>

<div class="ligas-wrapper">
    <?php if ($flashMessage !== ''): ?>
        <div class="liga-flash <?= $flashType ?>"><?= htmlspecialchars($flashMessage) ?></div>
    <?php endif; ?>

    <section class="ligas-hero">
        <div>
            <h1 class="liga-hero-title"><img src="<?= $ligaLogoSrc ?>" alt="LaLiga" class="liga-hero-logo">LaLigaFantasy</h1>
            <p>Crea ligas privadas, invita a tus amigos y actualiza la clasificación con resultados, alineaciones y eventos de LaLiga.</p>
        </div>
        <div class="ligas-stats">
            <div class="liga-stat">
                <strong><?= $totalLigas ?></strong>
                <span>Ligas activas</span>
            </div>
            <div class="liga-stat">
                <strong><?= $miembrosTotales ?></strong>
                <span>Participaciones</span>
            </div>
            <div class="liga-stat">
                <strong>J<?= $selectedJornada ?></strong>
                <span>Jornada seleccionada</span>
            </div>
            <div class="liga-stat">
                <strong><?= $miPosicion !== null ? '#' . $miPosicion : '—' ?></strong>
                <span>Tu posición</span>
            </div>
        </div>
    </section>

    <div class="ligas-grid">
        <section class="liga-card">
            <div class="liga-card-header">
                <div>
                    <h2>Mis ligas</h2>
                    <p class="liga-subtext">Cada liga usa los mismos puntos fantasy, pero la clasificación solo se compara entre sus miembros.</p>
                </div>
                <span class="liga-chip">Privadas</span>
            </div>

            <?php if (!$ligas): ?>
                <p class="liga-empty">Todavía no participas en ninguna liga. Crea una nueva o únete con un código de invitación.</p>
            <?php else: ?>
                <div class="liga-list">
                    <?php foreach ($ligas as $liga): ?>
                        <article class="liga-item <?= (int)$liga['id'] === $selectedLeagueId ? 'active' : '' ?>">
                            <div class="liga-item-top">
                                <div>
                                    <h3><?= htmlspecialchars($liga['nombre']) ?></h3>
                                    <div class="liga-code">Código: <?= htmlspecialchars($liga['codigo_invitacion']) ?></div>
                                </div>
                                <a class="liga-open-link" href="?liga=<?= (int)$liga['id'] ?>&jornada=<?= $selectedJornada ?>">Ver liga</a>
                            </div>
                            <div class="liga-item-bottom">
                                <span class="liga-badge-soft">👥 <?= (int)$liga['miembros'] ?> miembros</span>
                                <span class="liga-badge-soft">📅 <?= htmlspecialchars(date('d/m/Y', strtotime((string)$liga['created_at']))) ?></span>
                                <?php if ((int)$liga['creador_usuario_id'] === $usuarioId): ?>
                                    <form action="<?= BASE_URL ?>/eliminar_liga.php" method="post" onsubmit="return confirm('¿Seguro que quieres eliminar esta liga? Esta acción no se puede deshacer.');">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                        <input type="hidden" name="liga_id" value="<?= (int)$liga['id'] ?>">
                                        <input type="hidden" name="jornada" value="<?= (int)$selectedJornada ?>">
                                        <button type="submit" class="liga-btn-danger">Eliminar liga</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="liga-form-box">
            <h2>Gestionar ligas</h2>
            <p class="liga-subtext">La invitación se genera automáticamente y el creador entra a la liga al instante.</p>
            <div class="liga-form-grid">
                <form action="<?= BASE_URL ?>/crear_liga.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <label for="nombre_liga">Crear liga</label>
                    <input id="nombre_liga" type="text" name="nombre_liga" maxlength="100" placeholder="Ej. Liga TFG 2026" required>
                    <button class="liga-btn" type="submit">Crear liga</button>
                </form>

                <form action="<?= BASE_URL ?>/unirse_liga.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <label for="codigo_liga">Unirse por código</label>
                    <input id="codigo_liga" type="text" name="codigo_liga" maxlength="20" placeholder="ABCDE12345" required>
                    <button class="liga-btn-secondary" type="submit">Unirse</button>
                </form>
            </div>
        </section>
    </div>

    <?php if ($ligaSeleccionada): ?>
        <div class="ligas-grid-bottom">
            <section class="liga-tabla-wrap">
                <div class="liga-box-header">
                    <div>
                        <h2><?= htmlspecialchars($ligaSeleccionada['nombre']) ?></h2>
                        <p class="liga-subtext">Código de invitación: <strong><?= htmlspecialchars($ligaSeleccionada['codigo_invitacion']) ?></strong></p>
                    </div>
                    <span class="liga-chip"><?= (int)$ligaSeleccionada['miembros'] ?> miembros</span>
                </div>

                <?php if (!$ranking): ?>
                    <p class="liga-empty">No hay clasificación disponible todavía.</p>
                <?php else: ?>
                    <table>
                        <thead>
                        <tr>
                            <th>#</th>
                            <th>Usuario</th>
                            <th>Equipo</th>
                            <th>J<?= $selectedJornada ?></th>
                            <th>Total</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($ranking as $index => $fila): ?>
                            <tr>
                                <td><span class="liga-pos <?= $index < 3 ? 'top-' . ($index + 1) : '' ?>">#<?= $index + 1 ?></span></td>
                                <td>
                                    <div class="liga-user-line">
                                        <strong><?= htmlspecialchars($fila['username']) ?></strong>
                                        <?php if ((int)$fila['usuario_id'] === $usuarioId): ?>
                                            <span class="liga-badge-soft">Tu usuario</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($fila['nombre_equipo']) ?></td>
                                <td><span class="<?= ((int)$fila['puntos_jornada'] > 0) ? 'liga-pill-positive' : (((int)$fila['puntos_jornada'] < 0) ? 'liga-pill-negative' : 'liga-pill-neutral') ?>"><?= (int)$fila['puntos_jornada'] ?></span></td>
                                <td><span class="liga-puntos"><?= (int)$fila['puntos_totales'] ?> pts</span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>

            <div style="display:grid;gap:22px;">
                <section class="liga-jornada-box">
                    <div class="liga-box-header">
                        <div>
                            <h2>Puntuación en directo</h2>
                            <p class="liga-subtext">Sincronización automática al entrar: goles, asistencias, tarjetas y alineaciones desde TheSportsDB para recalcular la jornada.</p>
                        </div>
                        <span class="liga-chip-status <?= $ultimaSync ? 'ok' : 'warn' ?>"><?= $ultimaSync ? 'Sincronizada' : 'Pendiente' ?></span>
                    </div>

                    <div class="liga-info-note">
                        <strong>Tu equipo:</strong> <?= htmlspecialchars($miEquipoNombre) ?><br>
                        <span class="liga-sync-meta">Fuente: resultados, timeline y lineups públicos de LaLiga 2025-2026.</span>
                    </div>

                    <form action="<?= BASE_URL ?>/sincronizar_jornada.php" method="post" style="margin-bottom: 14px;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="liga_id" value="<?= (int)$selectedLeagueId ?>">
                        <input type="hidden" name="jornada" value="<?= (int)$selectedJornada ?>">
                        <button class="liga-btn-secondary" type="submit">Forzar actualización</button>
                    </form>

                    <div class="liga-mini-stats">
                        <div class="mini">
                            <strong><?= $partidosSincronizados ?></strong>
                            <span>Partidos con sync</span>
                        </div>
                        <div class="mini">
                            <strong><?= $miJornadaTotal ?></strong>
                            <span>Tus puntos J<?= $selectedJornada ?></span>
                        </div>
                        <div class="mini">
                            <strong><?= $ultimaSync ? date('d/m H:i', strtotime((string)$ultimaSync)) : '—' ?></strong>
                            <span>Última actualización</span>
                        </div>
                    </div>
                </section>

                <section class="liga-reglas">
                    <h2>Reglas de puntuación</h2>
                    <ul>
                        <li>Titular que juega: +2 puntos.</li>
                        <li>Suplente que entra: +1 punto.</li>
                        <li>Gol: portero/defensa +6, centrocampista +5, delantero +4.</li>
                        <li>Asistencia: +3 puntos.</li>
                        <li>Portería a cero para porteros y defensas titulares: +4.</li>
                        <li>Amarilla: -1, roja: -3, autogol: -2.</li>
                    </ul>
                </section>
            </div>
        </div>

        <section class="liga-tabla-wrap liga-mi-jornada" style="margin-top:22px;">
            <div class="liga-box-header">
                <div>
                    <h2>Mi jornada · J<?= $selectedJornada ?></h2>
                    <p class="liga-subtext">Solo puntúan tus jugadores alineados como titulares.</p>
                </div>
                <span class="liga-chip">Total: <?= $miJornadaTotal ?> pts</span>
            </div>

            <?php if (!$miJornada): ?>
                <p class="liga-empty">Aún no tienes una alineación titular preparada.</p>
            <?php else: ?>
                <table>
                    <thead>
                    <tr>
                        <th>Jugador</th>
                        <th>Equipo</th>
                        <th>Eventos</th>
                        <th>Puntos</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($miJornada as $fila): ?>
                        <?php
                        $eventos = [];
                        if ((int)$fila['goles'] > 0) { $eventos[] = 'Goles: ' . (int)$fila['goles']; }
                        if ((int)$fila['asistencias'] > 0) { $eventos[] = 'Asistencias: ' . (int)$fila['asistencias']; }
                        if ((int)$fila['amarillas'] > 0) { $eventos[] = 'Amarillas: ' . (int)$fila['amarillas']; }
                        if ((int)$fila['rojas'] > 0) { $eventos[] = 'Rojas: ' . (int)$fila['rojas']; }
                        if ((int)$fila['autogoles'] > 0) { $eventos[] = 'Autogoles: ' . (int)$fila['autogoles']; }
                        $posicionNormalizada = fantasy_normalize_position((string)$fila['posicion']);
                        $puedePorteriaCero = in_array($posicionNormalizada, ['Portero', 'Defensa'], true) && (int)$fila['titular'] > 0;
                        if ($puedePorteriaCero && (int)$fila['porteria_cero'] > 0) { $eventos[] = 'Portería a cero'; }
                        if (!$eventos) { $eventos[] = (int)$fila['ha_jugado'] ? 'Sin acciones decisivas' : 'Sin jugar'; }
                        ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($fila['nombre']) ?></strong><br>
                                <span class="liga-badge-soft"><?= htmlspecialchars(fantasy_normalize_position((string)$fila['posicion'])) ?></span>
                            </td>
                            <td><?= htmlspecialchars((string)$fila['equipo_nombre']) ?></td>
                            <td><?= htmlspecialchars(implode(' · ', $eventos)) ?></td>
                            <td><span class="<?= liga_pill_class($fila) ?>"><?= (int)$fila['puntos'] ?> pts</span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
    <?php else: ?>
        <section class="liga-card">
            <h2>Aún no hay ligas</h2>
            <p class="liga-empty">Crea tu primera liga para activar la clasificación y la puntuación sincronizada.</p>
        </section>
    <?php endif; ?>
</div>

<div class="widget-temas">
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <button type="submit" name="tema_pref" value="tema-laliga" title="Modo LaLiga">🔴</button>
        <button type="submit" name="tema_pref" value="tema-original" title="Modo Azul (Original)">🔵</button>
    </form>
</div>

<?php if ($autoLiveHabilitado): ?>
<script>
const LIGA_BASE_URL = <?= json_encode(BASE_URL) ?>;
const LIGA_CSRF = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;
const LIGA_JORNADA = <?= (int)$selectedJornada ?>;
const LIGA_INTERVAL_MS = <?= (int)$autoLiveSegundos * 1000 ?>;
let ligaSyncInFlight = false;

function ligaAutoSyncTick() {
    if (ligaSyncInFlight) return;
    ligaSyncInFlight = true;

    fetch(LIGA_BASE_URL + '/live_sync.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ csrf: LIGA_CSRF, jornada: LIGA_JORNADA, cooldown: 90 })
    })
    .then(r => r.json())
    .then(d => {
        if (d && d.ok && d.synced) {
            location.reload();
        }
    })
    .catch(() => {})
    .finally(() => {
        ligaSyncInFlight = false;
    });
}

setInterval(ligaAutoSyncTick, LIGA_INTERVAL_MS);
</script>
<?php endif; ?>

</body>
</html>