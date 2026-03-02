<?php
session_start();
if (!isset($_SESSION['usuario_id'])) { header('Location: /index.php'); exit; }

$timeout = 3600;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
    session_unset(); session_destroy();
    header('Location: /index.php?timeout=1'); exit;
}
$_SESSION['last_activity'] = time();

require_once '../conexion.php';
require_once '../csrf.php';
include_once '../cookie_tema.php';

$escudos = [
    'Real Madrid'=>'realmadrid.png','FC Barcelona'=>'barsa.png',
    'Atletico de Madrid'=>'atletimadrid.png','Atlético de Madrid'=>'atletimadrid.png',
    'Sevilla FC'=>'sevillafc.png','Real Betis'=>'Real_Betis.png',
    'Valencia CF'=>'valencia-cf-logo-escudo-1.png','Athletic Club'=>'Athletic_c_de_bilbao.png',
    'Real Sociedad'=>'Real_Sociedad_de_Futbol_logo.png',
    'Villarreal CF'=>'villarreal-club-de-futbol-logo-png_seeklogo-243387.png',
    'CA Osasuna'=>'osasuna-logo-1.png','Levante UD'=>'levante-ud-logo-png.png',
    'Girona FC'=>'girona_fc_.png','Elche CF'=>'Escudo_Elche_CF.png',
    'RCD Espanyol'=>'espanyol-logo.png','Getafe CF'=>'Getafe_CF_Logo.png',
    'RCD Mallorca'=>'RCD_Mallorca.png','RC Celta de Vigo'=>'celta-de-vigo-logo.png',
    'Deportivo Alavés'=>'Deportivo_Alaves_logo_(2020).svg.png',
    'Real Oviedo'=>'oviedo.fc.png',
    'Rayo Vallecano'=>'rayo-vallecano-logo-png-transparent-png.png',
];

// Migración: recrear tabla si falta columna goles
try {
    $cols = $pdo->query("SHOW COLUMNS FROM partidos LIKE 'goles_local'")->fetchAll();
    if (empty($cols)) $pdo->exec("DROP TABLE IF EXISTS partidos");
} catch (PDOException $e) {}

$pdo->exec("CREATE TABLE IF NOT EXISTS partidos (
    id INT PRIMARY KEY AUTO_INCREMENT, jornada INT NOT NULL,
    equipo_local_id INT NOT NULL, equipo_visitante_id INT NOT NULL,
    fecha_hora DATETIME NOT NULL, goles_local INT DEFAULT NULL, goles_visitante INT DEFAULT NULL,
    FOREIGN KEY (equipo_local_id) REFERENCES equipos(id),
    FOREIGN KEY (equipo_visitante_id) REFERENCES equipos(id)
)");

if ($pdo->query("SELECT COUNT(*) FROM partidos")->fetchColumn() == 0) generarCalendario($pdo);
generarResultados($pdo);

function generarCalendario($pdo) {
    $ids = $pdo->query("SELECT id FROM equipos ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
    $n = count($ids);
    if ($n < 2) return;

    $id_rm = $pdo->query("SELECT id FROM equipos WHERE nombre='Real Madrid'")->fetchColumn();
    $id_ge = $pdo->query("SELECT id FROM equipos WHERE nombre='Getafe CF'")->fetchColumn();

    $fixed = $ids[$n-1];
    $others = array_slice($ids, 0, $n-1);

    // Round-Robin primera vuelta
    $matches = [];
    for ($r = 0; $r < $n-1; $r++) {
        $j = $r + 1;
        $rot = [];
        for ($i = 0; $i < $n-1; $i++) $rot[] = $others[($r+$i) % ($n-1)];
        $matches[] = $r%2==0 ? [$j,$rot[0],$fixed] : [$j,$fixed,$rot[0]];
        for ($i = 1; $i < $n/2; $i++) {
            $h = $rot[$i]; $a = $rot[$n-2-$i];
            $matches[] = $i%2==0 ? [$j,$a,$h] : [$j,$h,$a];
        }
    }

    // Segunda vuelta (invertir local/visitante)
    $v2 = [];
    foreach ($matches as $m) $v2[] = [$m[0]+($n-1), $m[2], $m[1]];
    $all = array_merge($matches, $v2);

    // Forzar Real Madrid vs Getafe en Jornada 26
    if ($id_rm && $id_ge) {
        $jr = null;
        foreach ($all as $m)
            if ($m[0]>=20 && $m[0]<=38 && (($m[1]==$id_rm && $m[2]==$id_ge) || ($m[1]==$id_ge && $m[2]==$id_rm)))
                { $jr = $m[0]; break; }
        if ($jr && $jr != 26) {
            foreach ($all as &$m) { if ($m[0]==$jr) $m[0]=26; elseif ($m[0]==26) $m[0]=$jr; }
            unset($m);
        }
        foreach ($all as &$m) {
            if ($m[0]==26 && $m[1]==$id_ge && $m[2]==$id_rm) [$m[1],$m[2]] = [$id_rm,$id_ge];
        }
        unset($m);
    }

    // Fechas por jornada (Temporada 2025/2026)
    $fechas = [
        1=>'2025-08-16',2=>'2025-08-23',3=>'2025-08-30',4=>'2025-09-13',
        5=>'2025-09-20',6=>'2025-09-27',7=>'2025-10-04',8=>'2025-10-18',
        9=>'2025-10-25',10=>'2025-11-01',11=>'2025-11-08',12=>'2025-11-22',
        13=>'2025-11-29',14=>'2025-12-06',15=>'2025-12-13',16=>'2025-12-20',
        17=>'2026-01-03',18=>'2026-01-10',19=>'2026-01-17',20=>'2026-01-24',
        21=>'2026-01-31',22=>'2026-02-07',23=>'2026-02-14',24=>'2026-02-21',
        25=>'2026-02-25',26=>'2026-02-28',27=>'2026-03-07',28=>'2026-03-14',
        29=>'2026-03-28',30=>'2026-04-04',31=>'2026-04-11',32=>'2026-04-18',
        33=>'2026-04-25',34=>'2026-05-02',35=>'2026-05-09',36=>'2026-05-16',
        37=>'2026-05-23',38=>'2026-05-30',
    ];

    $slots = [[0,'14:00'],[0,'16:15'],[0,'18:30'],[0,'21:00'],[0,'21:00'],
              [1,'14:00'],[1,'16:15'],[1,'18:30'],[1,'21:00'],[1,'21:00']];

    $ins = $pdo->prepare("INSERT INTO partidos (jornada,equipo_local_id,equipo_visitante_id,fecha_hora) VALUES(?,?,?,?)");
    $ci = [];
    foreach ($all as $m) {
        $j = $m[0]; $si = ($ci[$j] ?? 0) % count($slots); $s = $slots[$si];
        $dt = new DateTime($fechas[$j] ?? '2026-06-01');
        $dt->modify("+{$s[0]} days");
        $ins->execute([$j, $m[1], $m[2], $dt->format('Y-m-d')." {$s[1]}:00"]);
        $ci[$j] = ($ci[$j] ?? 0) + 1;
    }

    if ($id_rm && $id_ge)
        $pdo->prepare("UPDATE partidos SET fecha_hora='2026-03-02 21:00:00' WHERE jornada=26 AND equipo_local_id=? AND equipo_visitante_id=?")
            ->execute([$id_rm, $id_ge]);
}

function generarResultados($pdo) {
    $now = date('Y-m-d H:i:s');
    $pends = $pdo->query("SELECT id FROM partidos WHERE fecha_hora<'$now' AND goles_local IS NULL")->fetchAll(PDO::FETCH_COLUMN);
    if (empty($pends)) return;

    $g = [0,0,0,1,1,1,1,1,2,2,2,2,3,3,3,4,5];
    $u = $pdo->prepare("UPDATE partidos SET goles_local=?,goles_visitante=? WHERE id=?");
    foreach ($pends as $id) {
        mt_srand((int)$id * 7919 + 42);
        $u->execute([$g[mt_rand(0,16)], $g[mt_rand(0,16)], $id]);
    }
    mt_srand();
}

// Consultar jornada actual
if (!isset($_GET['jornada'])) {
    $auto = $pdo->prepare("SELECT jornada FROM partidos WHERE DATE(fecha_hora)>=? ORDER BY fecha_hora LIMIT 1");
    $auto->execute([date('Y-m-d')]);
    $jornada = (int)$auto->fetchColumn() ?: 1;
} else {
    $jornada = max(1, min(38, (int)$_GET['jornada']));
}

$stmt = $pdo->prepare("SELECT p.*, el.nombre AS local_nombre, ev.nombre AS visitante_nombre
    FROM partidos p JOIN equipos el ON p.equipo_local_id=el.id JOIN equipos ev ON p.equipo_visitante_id=ev.id
    WHERE p.jornada=? ORDER BY p.fecha_hora, p.id");
$stmt->execute([$jornada]);
$partidos = $stmt->fetchAll(PDO::FETCH_OBJ);

// Agrupar por día
$dias_es = ['Monday'=>'Lunes','Tuesday'=>'Martes','Wednesday'=>'Miércoles','Thursday'=>'Jueves',
            'Friday'=>'Viernes','Saturday'=>'Sábado','Sunday'=>'Domingo'];
$meses_es = [1=>'enero',2=>'febrero',3=>'marzo',4=>'abril',5=>'mayo',6=>'junio',
             7=>'julio',8=>'agosto',9=>'septiembre',10=>'octubre',11=>'noviembre',12=>'diciembre'];
$hoy_str = date('Y-m-d');
$grupos = [];
foreach ($partidos as $p) {
    $dt = new DateTime($p->fecha_hora);
    $k = $dt->format('Y-m-d');
    if (!isset($grupos[$k])) {
        $grupos[$k] = [
            'label' => $dias_es[$dt->format('l')].', '.$dt->format('j').' de '.$meses_es[(int)$dt->format('n')].' de '.$dt->format('Y'),
            'partidos' => []
        ];
    }
    $grupos[$k]['partidos'][] = $p;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendario J<?= $jornada ?> - LaLiga Fantasy</title>
    <link rel="icon" type="image/png" href="/images/favicon.png">
    <link rel="stylesheet" href="/css/inicio.css">
    <link rel="stylesheet" href="/css/calendario.css">
    <link rel="stylesheet" href="/css/cookie_tema.css">
</head>
<body class="<?= $clase_tema ?>">

<div class="navegacion"><nav>
    <a href="/inicio.php">Inicio</a>
    <a href="/pages/equipos.php">Equipos</a>
    <a href="/pages/calendario.php" class="nav-active">Calendario</a>
    <a href="/pages/plantilla.php">Plantilla</a>
    <a href="/pages/noticias.php">Noticias</a>
    <a href="/logout.php">Cerrar Sesión</a>
</nav></div>

<div class="calendario-header">
    <h1><img src="/images/favicon.png" alt="" style="height:1em;vertical-align:middle;margin-right:8px">Calendario de Partidos</h1>
    <p class="calendario-subtitulo">Temporada 2025 / 2026</p>
</div>

<div class="jornadas-pagination">
    <a href="?jornada=<?= $jornada-1 ?>" class="prev-next<?= $jornada<=1?' disabled':'' ?>">◀</a>
    <?php for($j=1;$j<=38;$j++) echo $j==$jornada ? "<span class=\"active\">$j</span>" : "<a href=\"?jornada=$j\">$j</a>"; ?>
    <a href="?jornada=<?= $jornada+1 ?>" class="prev-next<?= $jornada>=38?' disabled':'' ?>">▶</a>
</div>

<h2 class="jornada-titulo">⚽ Jornada <?= $jornada ?></h2>

<div class="partidos-container">
<?php if (empty($partidos)): ?>
    <p class="calendario-vacio">No hay partidos para esta jornada.</p>
<?php else: foreach ($grupos as $dia): ?>
    <div class="partido-fecha-grupo">
        <div class="partido-fecha-label"><span>📅 <?= htmlspecialchars($dia['label']) ?></span></div>
        <?php foreach ($dia['partidos'] as $p):
            $dt = new DateTime($p->fecha_hora);
            $jugado = $p->goles_local !== null;
            $hoy = $dt->format('Y-m-d') === $hoy_str;
            $eL = isset($escudos[$p->local_nombre]) ? '/images/escudos/'.$escudos[$p->local_nombre] : '/images/favicon.png';
            $eV = isset($escudos[$p->visitante_nombre]) ? '/images/escudos/'.$escudos[$p->visitante_nombre] : '/images/favicon.png';
        ?>
        <div class="partido-card<?= $jugado?' jugado':($hoy?' hoy':'') ?>">
            <div class="partido-local">
                <span class="partido-equipo-nombre"><?= htmlspecialchars($p->local_nombre) ?></span>
                <div class="partido-escudo"><img src="<?= $eL ?>" alt="" loading="lazy"></div>
            </div>
            <div class="partido-vs">
            <?php if ($jugado): ?>
                <span class="partido-resultado"><?= $p->goles_local ?> - <?= $p->goles_visitante ?></span>
                <span class="partido-badge finalizado">Finalizado</span>
            <?php elseif ($hoy): ?>
                <span class="partido-hora"><?= $dt->format('H:i') ?></span>
                <span class="partido-badge badge-hoy">Hoy</span>
            <?php else: ?>
                <span class="partido-hora"><?= $dt->format('H:i') ?></span>
                <span class="partido-vs-label">vs</span>
            <?php endif; ?>
            </div>
            <div class="partido-visitante">
                <div class="partido-escudo"><img src="<?= $eV ?>" alt="" loading="lazy"></div>
                <span class="partido-equipo-nombre"><?= htmlspecialchars($p->visitante_nombre) ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endforeach; endif; ?>
</div>

<div class="widget-temas"><form method="POST">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <button type="submit" name="tema_pref" value="" title="Modo Azul">🔵</button>
    <button type="submit" name="tema_pref" value="tema-claro" title="Modo Claro">⚪</button>
</form></div>

</body>
</html>
