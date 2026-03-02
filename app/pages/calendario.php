<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: /index.php');
    exit;
}
// Control de timeout por inactividad
$timeout = 3600;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
    session_unset(); session_destroy();
    header('Location: /index.php?timeout=1');
    exit;
}
$_SESSION['last_activity'] = time();

require_once '../conexion.php';
require_once '../csrf.php';
include_once '../cookie_tema.php';

// ── Mapa equipo → archivo de escudo ──
$escudos = [
    'Real Madrid'        => 'realmadrid.png',
    'FC Barcelona'       => 'barsa.png',
    'Atletico de Madrid' => 'atletimadrid.png',
    'Atlético de Madrid' => 'atletimadrid.png',
    'Sevilla FC'         => 'sevillafc.png',
    'Real Betis'         => 'Real_Betis.png',
    'Valencia CF'        => 'valencia-cf-logo-escudo-1.png',
    'Athletic Club'      => 'Athletic_c_de_bilbao.png',
    'Real Sociedad'      => 'Real_Sociedad_de_Futbol_logo.png',
    'Villarreal CF'      => 'villarreal-club-de-futbol-logo-png_seeklogo-243387.png',
    'CA Osasuna'         => 'osasuna-logo-1.png',
    'Levante UD'         => 'levante-ud-logo-png.png',
    'Girona FC'          => 'girona_fc_.png',
    'Elche CF'           => 'Escudo_Elche_CF.png',
    'RCD Espanyol'       => 'espanyol-logo.png',
    'Getafe CF'          => 'Getafe_CF_Logo.png',
    'RCD Mallorca'       => 'RCD_Mallorca.png',
    'RC Celta de Vigo'   => 'celta-de-vigo-logo.png',
    'Deportivo Alavés'   => 'Deportivo_Alaves_logo_(2020).svg.png',
    'Real Oviedo'        => 'oviedo.fc.png',
    'Rayo Vallecano'     => 'rayo-vallecano-logo-png-transparent-png.png',
];

// ════════════════════════════════════════
//  CREAR TABLA Y GENERAR CALENDARIO SI NO EXISTE
// ════════════════════════════════════════

$pdo->exec("CREATE TABLE IF NOT EXISTS partidos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    jornada INT NOT NULL,
    equipo_local_id INT NOT NULL,
    equipo_visitante_id INT NOT NULL,
    fecha_hora DATETIME NOT NULL,
    FOREIGN KEY (equipo_local_id) REFERENCES equipos(id),
    FOREIGN KEY (equipo_visitante_id) REFERENCES equipos(id)
)");

$total = $pdo->query("SELECT COUNT(*) FROM partidos")->fetchColumn();
if ($total == 0) {
    generarCalendarioCompleto($pdo);
}

/**
 * Genera un calendario round-robin completo de 38 jornadas (ida y vuelta)
 * Método de rotación circular para 20 equipos.
 */
function generarCalendarioCompleto($pdo) {
    $equipos_ids = $pdo->query("SELECT id FROM equipos ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
    $n = count($equipos_ids);
    if ($n < 2) return;

    $fixed  = $equipos_ids[$n - 1];
    $others = array_slice($equipos_ids, 0, $n - 1);

    $primera_vuelta = [];

    for ($r = 0; $r < $n - 1; $r++) {
        $jornada = $r + 1;

        $rotated = [];
        for ($i = 0; $i < $n - 1; $i++) {
            $rotated[] = $others[($r + $i) % ($n - 1)];
        }

        if ($r % 2 == 0) {
            $primera_vuelta[] = [$jornada, $rotated[0], $fixed];
        } else {
            $primera_vuelta[] = [$jornada, $fixed, $rotated[0]];
        }

        for ($i = 1; $i < $n / 2; $i++) {
            $home = $rotated[$i];
            $away = $rotated[$n - 2 - $i];
            if ($i % 2 == 0) {
                $primera_vuelta[] = [$jornada, $away, $home];
            } else {
                $primera_vuelta[] = [$jornada, $home, $away];
            }
        }
    }

    $segunda_vuelta = [];
    foreach ($primera_vuelta as $m) {
        $segunda_vuelta[] = [$m[0] + ($n - 1), $m[2], $m[1]];
    }

    $all = array_merge($primera_vuelta, $segunda_vuelta);

    // Fechas base por jornada (sábado de cada semana)
    $fechas_jornada = [];
    $fecha = new DateTime('2025-08-16');

    for ($j = 1; $j <= 38; $j++) {
        if ($j <= 17) {
            $fechas_jornada[$j] = clone $fecha;
            $fecha->modify('+7 days');
        } elseif ($j == 18) {
            $fechas_jornada[$j] = new DateTime('2026-01-03');
        } elseif ($j == 19) {
            $fechas_jornada[$j] = new DateTime('2026-01-10');
        } else {
            if ($j == 20) $fecha = new DateTime('2026-01-17');
            $fechas_jornada[$j] = clone $fecha;
            $fecha->modify('+7 days');
        }
    }

    // Slots horarios: 5 sábado + 5 domingo
    $slots = [
        ['offset' => 0, 'hora' => '14:00'],
        ['offset' => 0, 'hora' => '16:15'],
        ['offset' => 0, 'hora' => '18:30'],
        ['offset' => 0, 'hora' => '21:00'],
        ['offset' => 0, 'hora' => '21:00'],
        ['offset' => 1, 'hora' => '14:00'],
        ['offset' => 1, 'hora' => '16:15'],
        ['offset' => 1, 'hora' => '18:30'],
        ['offset' => 1, 'hora' => '21:00'],
        ['offset' => 1, 'hora' => '21:00'],
    ];

    $stmt = $pdo->prepare("INSERT INTO partidos (jornada, equipo_local_id, equipo_visitante_id, fecha_hora) VALUES (?, ?, ?, ?)");

    $idx_por_jornada = [];
    foreach ($all as $m) {
        $j = $m[0];
        if (!isset($idx_por_jornada[$j])) $idx_por_jornada[$j] = 0;
        $idx = $idx_por_jornada[$j] % count($slots);

        $slot     = $slots[$idx];
        $fecha_dt = clone $fechas_jornada[$j];
        $fecha_dt->modify('+' . $slot['offset'] . ' days');
        $fecha_str = $fecha_dt->format('Y-m-d') . ' ' . $slot['hora'] . ':00';

        $stmt->execute([$j, $m[1], $m[2], $fecha_str]);
        $idx_por_jornada[$j]++;
    }
}

// ════════════════════════════════════════
//  CONSULTAR PARTIDOS DE LA JORNADA
// ════════════════════════════════════════

$jornada_actual = isset($_GET['jornada']) ? max(1, min(38, (int)$_GET['jornada'])) : 1;

$stmt = $pdo->prepare("
    SELECT p.id,
           p.jornada,
           p.fecha_hora,
           el.nombre AS local_nombre,
           ev.nombre AS visitante_nombre
    FROM partidos p
    JOIN equipos el ON p.equipo_local_id  = el.id
    JOIN equipos ev ON p.equipo_visitante_id = ev.id
    WHERE p.jornada = ?
    ORDER BY p.fecha_hora ASC, p.id ASC
");
$stmt->execute([$jornada_actual]);
$partidos = $stmt->fetchAll(PDO::FETCH_OBJ);

// Agrupar partidos por fecha (día)
$partidos_por_dia = [];
$meses_es = [
    1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
    5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
    9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'
];
$dias_es = [
    'Monday' => 'Lunes', 'Tuesday' => 'Martes', 'Wednesday' => 'Miércoles',
    'Thursday' => 'Jueves', 'Friday' => 'Viernes', 'Saturday' => 'Sábado', 'Sunday' => 'Domingo'
];

foreach ($partidos as $p) {
    $dt = new DateTime($p->fecha_hora);
    $dia_key = $dt->format('Y-m-d');
    if (!isset($partidos_por_dia[$dia_key])) {
        $dia_semana = $dias_es[$dt->format('l')] ?? $dt->format('l');
        $num = $dt->format('j');
        $mes = $meses_es[(int)$dt->format('n')];
        $anio = $dt->format('Y');
        $partidos_por_dia[$dia_key] = [
            'label'    => "$dia_semana, $num de $mes de $anio",
            'partidos' => [],
        ];
    }
    $partidos_por_dia[$dia_key]['partidos'][] = $p;
}

// Rango de fechas de la jornada
$fecha_min = !empty($partidos) ? (new DateTime($partidos[0]->fecha_hora))->format('j') : '';
$fecha_max = !empty($partidos) ? (new DateTime(end($partidos)->fecha_hora))->format('j') : '';
$mes_rango = !empty($partidos) ? $meses_es[(int)(new DateTime($partidos[0]->fecha_hora))->format('n')] : '';
$anio_rango = !empty($partidos) ? (new DateTime($partidos[0]->fecha_hora))->format('Y') : '';

if ($fecha_min && $fecha_max && $fecha_min !== $fecha_max) {
    $rango_texto = "$fecha_min - $fecha_max de $mes_rango de $anio_rango";
} elseif ($fecha_min) {
    $rango_texto = "$fecha_min de $mes_rango de $anio_rango";
} else {
    $rango_texto = '';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendario Jornada <?php echo $jornada_actual; ?> - LaLiga Fantasy</title>
    <meta name="description" content="Calendario completo de LaLiga Fantasy. Consulta los partidos de cada jornada, horarios y enfrentamientos.">

    <!-- Open Graph -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://laligafantasy.duckdns.org/pages/calendario.php">
    <meta property="og:title" content="Calendario - LaLiga Fantasy">
    <meta property="og:description" content="Calendario completo de LaLiga Fantasy. Consulta los partidos de cada jornada, horarios y enfrentamientos.">
    <meta property="og:image" content="https://laligafantasy.duckdns.org/images/laliga-logo.png">
    <meta property="og:image:type" content="image/png">
    <meta property="og:image:width" content="698">
    <meta property="og:image:height" content="441">
    <meta property="og:site_name" content="LaLiga Fantasy">
    <meta property="og:locale" content="es_ES">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Calendario Jornada <?php echo $jornada_actual; ?> - LaLiga Fantasy">
    <meta name="twitter:description" content="Calendario completo de LaLiga Fantasy. Consulta los partidos de cada jornada, horarios y enfrentamientos.">
    <meta name="twitter:image" content="https://laligafantasy.duckdns.org/images/laliga-logo.png">

    <link rel="icon" type="image/png" href="/images/favicon.png">
    <link rel="shortcut icon" href="/images/favicon.png" type="image/x-icon">
    <link rel="stylesheet" href="/css/inicio.css">
    <link rel="stylesheet" href="/css/calendario.css">
    <link rel="stylesheet" href="/css/cookie_tema.css">
</head>
<body class="<?php echo $clase_tema; ?>">

<!-- NAVEGACIÓN -->
<div class="navegacion">
    <nav>
        <a href="/inicio.php">Inicio</a>
        <a href="/pages/equipos.php">Equipos</a>
        <a href="/pages/calendario.php" class="nav-active">Calendario</a>
        <a href="/pages/plantilla.php">Plantilla</a>
        <a href="/pages/noticias.php">Noticias</a>
        <a href="/logout.php">Cerrar Sesión</a>
    </nav>
</div>

<!-- CABECERA -->
<div class="calendario-header">
    <h1><img src="/images/favicon.png" alt="LaLiga" style="height:1em;vertical-align:middle;margin-right:8px;">Calendario de Partidos</h1>
    <p class="calendario-subtitulo">Temporada 2025 / 2026</p>
</div>

<!-- PAGINACIÓN JORNADAS 1–38 -->
<div class="jornadas-pagination">
    <a href="?jornada=<?php echo $jornada_actual - 1; ?>"
       class="prev-next <?php echo $jornada_actual <= 1 ? 'disabled' : ''; ?>"
       title="Jornada anterior">◀</a>

    <?php for ($j = 1; $j <= 38; $j++): ?>
        <?php if ($j == $jornada_actual): ?>
            <span class="active"><?php echo $j; ?></span>
        <?php else: ?>
            <a href="?jornada=<?php echo $j; ?>"><?php echo $j; ?></a>
        <?php endif; ?>
    <?php endfor; ?>

    <a href="?jornada=<?php echo $jornada_actual + 1; ?>"
       class="prev-next <?php echo $jornada_actual >= 38 ? 'disabled' : ''; ?>"
       title="Jornada siguiente">▶</a>
</div>

<!-- TÍTULO JORNADA -->
<h2 class="jornada-titulo">⚽ Jornada <?php echo $jornada_actual; ?></h2>
<?php if ($rango_texto): ?>
    <p class="jornada-fecha-rango">📅 <?php echo $rango_texto; ?></p>
<?php endif; ?>

<!-- PARTIDOS -->
<div class="partidos-container">
    <?php if (empty($partidos)): ?>
        <div class="calendario-vacio">
            <p>No hay partidos registrados para esta jornada.</p>
        </div>
    <?php else: ?>
        <?php foreach ($partidos_por_dia as $dia_info): ?>
            <div class="partido-fecha-grupo">
                <div class="partido-fecha-label">
                    <span>📅 <?php echo htmlspecialchars($dia_info['label']); ?></span>
                </div>

                <?php foreach ($dia_info['partidos'] as $p):
                    $hora = (new DateTime($p->fecha_hora))->format('H:i');
                    $esc_local = $escudos[$p->local_nombre] ?? null;
                    $esc_visit = $escudos[$p->visitante_nombre] ?? null;
                    $img_local = $esc_local ? '/images/escudos/' . $esc_local : '/images/favicon.png';
                    $img_visit = $esc_visit ? '/images/escudos/' . $esc_visit : '/images/favicon.png';
                ?>
                <div class="partido-card">
                    <!-- EQUIPO LOCAL -->
                    <div class="partido-local">
                        <span class="partido-equipo-nombre"><?php echo htmlspecialchars($p->local_nombre); ?></span>
                        <div class="partido-escudo">
                            <img src="<?php echo htmlspecialchars($img_local); ?>"
                                 alt="<?php echo htmlspecialchars($p->local_nombre); ?>"
                                 loading="lazy">
                        </div>
                    </div>

                    <!-- HORA / VS -->
                    <div class="partido-vs">
                        <span class="partido-hora"><?php echo $hora; ?></span>
                        <span class="partido-vs-label">vs</span>
                    </div>

                    <!-- EQUIPO VISITANTE -->
                    <div class="partido-visitante">
                        <div class="partido-escudo">
                            <img src="<?php echo htmlspecialchars($img_visit); ?>"
                                 alt="<?php echo htmlspecialchars($p->visitante_nombre); ?>"
                                 loading="lazy">
                        </div>
                        <span class="partido-equipo-nombre"><?php echo htmlspecialchars($p->visitante_nombre); ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- WIDGET DE TEMAS -->
<div class="widget-temas">
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        <button type="submit" name="tema_pref" value="" title="Modo Azul (Original)">🔵</button>
        <button type="submit" name="tema_pref" value="tema-claro" title="Modo Claro">⚪</button>
    </form>
</div>

</body>
</html>