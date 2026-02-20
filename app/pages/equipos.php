<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: /app/index.php');
    exit;
}
$timeout = 3600;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
    session_unset(); session_destroy();
    header('Location: /app/index.php?timeout=1');
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

// ── Consulta equipos ──
$equipos = $pdo->query("SELECT * FROM equipos ORDER BY nombre")->fetchAll(PDO::FETCH_OBJ);

// ── Vista detalle si se pasa equipo_id ──
$equipo_sel = null;
$jugadores  = [];
$entrenador = null;

if (isset($_GET['equipo_id']) && is_numeric($_GET['equipo_id'])) {
    $eid = (int)$_GET['equipo_id'];

    $stmt = $pdo->prepare("SELECT * FROM equipos WHERE id = ?");
    $stmt->execute([$eid]);
    $equipo_sel = $stmt->fetch(PDO::FETCH_OBJ);

    if ($equipo_sel) {
        $stmt2 = $pdo->prepare("SELECT * FROM jugadores WHERE equipo_id = ? ORDER BY
            FIELD(posicion,'Portero','Defensa','Centrocampista','Extremo','Delantero'), dorsal");
        $stmt2->execute([$eid]);
        $jugadores = $stmt2->fetchAll(PDO::FETCH_OBJ);

        $stmt3 = $pdo->prepare("SELECT * FROM entrenadores WHERE equipo_id = ?");
        $stmt3->execute([$eid]);
        $entrenador = $stmt3->fetch(PDO::FETCH_OBJ);
    }
}

function foto_jugador(string $nombre): string {
    $n = strtolower($nombre);
    $n = str_replace(['á','é','í','ó','ú','ñ','ü','ç',' ','-','.','\''],
                     ['a','e','i','o','u','n','u','c','_','_','',''], $n);
    $n = preg_replace('/[^a-z0-9_]/', '', $n);
    $path = "/images/jugadores/{$n}.png";
    $real = __DIR__ . '/../../images/jugadores/' . $n . '.png';
    return file_exists($real) ? $path : '/images/jugadores/soccer-player-silhouette-free-png.png';
}

function foto_entrenador(string $nombre): string {
    $n = strtolower($nombre);
    $n = str_replace(['á','é','í','ó','ú','ñ','ü','ç',' ','-','.','\''],
                     ['a','e','i','o','u','n','u','c','_','_','',''], $n);
    $n = preg_replace('/[^a-z0-9_]/', '', $n);
    $path = "/images/entrenadores/{$n}.png";
    $real = __DIR__ . '/../../images/entrenadores/' . $n . '.png';
    return file_exists($real) ? $path : '/images/entrenadores/silhouette-of-standing-man-with-hands-in-pockets-isolated-on-transparent-background-simple-black-illustration-suitable-for-design-business-or-presentation-concepts-png.png';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $equipo_sel ? htmlspecialchars($equipo_sel->nombre) : 'Equipos LaLiga'; ?></title>
    <link rel="icon" type="image/png" href="/images/favicon.png">
    <link rel="shortcut icon" href="/images/favicon.png" type="image/x-icon">
    <link rel="stylesheet" href="/css/inicio.css">
    <link rel="stylesheet" href="/css/equipos.css">
    <link rel="stylesheet" href="/css/cookie_tema.css">
</head>
<body class="<?php echo $clase_tema; ?>">

<!-- NAVEGACIÓN -->
<div class="navegacion">
    <nav>
        <a href="/app/inicio.php">Inicio</a>
        <a href="/app/pages/jugadores.php">Lista de Jugadores</a>
        <a href="/app/pages/plantilla.php">Plantilla</a>
        <a href="/app/pages/equipos.php" class="nav-active"> Equipos</a>
        <a href="/app/pages/noticias.php">Noticias</a>
        <a href="/app/logout.php">Cerrar Sesión</a>
    </nav>
</div>

<?php if (!$equipo_sel): ?>
<!-- ════════════════════════════════════════
     VISTA 1 – LISTA DE LOS 20 EQUIPOS
════════════════════════════════════════ -->
<div class="equipos-header">
    <h1>⚽ Equipos LaLiga</h1>
    <p class="equipos-subtitulo"></p>
</div>

<div class="equipos-grid">
    <?php foreach ($equipos as $eq):
        $archivo = $escudos[$eq->nombre] ?? null;
        $escudo_url = $archivo ? '/images/escudos/' . $archivo : '/images/silueta.svg';
    ?>
    <a href="/app/pages/equipos.php?equipo_id=<?php echo $eq->id; ?>" class="equipo-card">
        <div class="equipo-escudo">
            <img src="<?php echo htmlspecialchars($escudo_url); ?>"
                 alt="<?php echo htmlspecialchars($eq->nombre); ?>"
                 loading="lazy">
        </div>
        <span class="equipo-nombre"><?php echo htmlspecialchars($eq->nombre); ?></span>
        <span class="equipo-ciudad">📍 <?php echo htmlspecialchars($eq->ciudad ?? ''); ?></span>
    </a>
    <?php endforeach; ?>
</div>

<?php else:
    $nombre_eq  = $equipo_sel->nombre;
    $archivo    = $escudos[$nombre_eq] ?? null;
    $escudo_url = $archivo ? '/images/escudos/' . $archivo : '/images/silueta.svg';
?>
<!-- ════════════════════════════════════════
     VISTA 2 – DETALLE DE UN EQUIPO
════════════════════════════════════════ -->
<div class="detalle-header">
    <a href="/app/pages/equipos.php" class="btn-volver">← Volver a equipos</a>
    <div class="detalle-escudo-wrap">
        <img src="<?php echo htmlspecialchars($escudo_url); ?>"
             alt="<?php echo htmlspecialchars($nombre_eq); ?>"
             class="detalle-escudo-img">
        <div>
            <h1 class="detalle-titulo"><?php echo htmlspecialchars($nombre_eq); ?></h1>
            <p class="detalle-info">
                🏟️ <?php echo htmlspecialchars($equipo_sel->estadio ?? ''); ?>
                &nbsp;·&nbsp;
                📍 <?php echo htmlspecialchars($equipo_sel->ciudad ?? ''); ?>
                &nbsp;·&nbsp;
                📅 Fundado en <?php echo htmlspecialchars($equipo_sel->fundacion ?? ''); ?>
            </p>
        </div>
    </div>
</div>

<!-- ── ENTRENADOR ── -->
<?php if ($entrenador):
    $foto_ent = foto_entrenador($entrenador->nombre);
?>
<div class="seccion-titulo">🎽 Entrenador</div>
<div class="entrenador-wrap">

    <div class="persona-card" onclick="abrirModal('modal-ent-<?php echo $entrenador->id; ?>')">
        <div class="persona-foto-wrap">
            <img src="<?php echo $foto_ent; ?>" alt="<?php echo htmlspecialchars($entrenador->nombre); ?>"
                 class="persona-foto" onerror="this.src='/images/entrenadores/silhouette-of-standing-man-with-hands-in-pockets-isolated-on-transparent-background-simple-black-illustration-suitable-for-design-business-or-presentation-concepts-png.png'">
        </div>
        <div class="persona-info">
            <span class="persona-nombre"><?php echo htmlspecialchars($entrenador->nombre); ?></span>
            <span class="persona-badge entrenador-badge">Entrenador</span>
        </div>
    </div>

</div>

<!-- Modal entrenador -->
<div id="modal-ent-<?php echo $entrenador->id; ?>" class="modal-overlay">
    <div class="modal-card">
        <button class="modal-cerrar" onclick="cerrarModal('modal-ent-<?php echo $entrenador->id; ?>')">✕</button>
        <div class="modal-foto-wrap">
            <img src="<?php echo $foto_ent; ?>" alt="<?php echo htmlspecialchars($entrenador->nombre); ?>"
                 class="modal-foto" onerror="this.src='/images/entrenadores/silhouette-of-standing-man-with-hands-in-pockets-isolated-on-transparent-background-simple-black-illustration-suitable-for-design-business-or-presentation-concepts-png.png'">
        </div>
        <h2 class="modal-nombre"><?php echo htmlspecialchars($entrenador->nombre); ?></h2>
        <span class="persona-badge entrenador-badge">Entrenador</span>
        <div class="modal-datos">
            <div class="dato-item">
                <span class="dato-label">🎂 Edad</span>
                <span class="dato-val"><?php echo $entrenador->edad; ?> años</span>
            </div>
            <div class="dato-item">
                <span class="dato-label">🌍 Nacionalidad</span>
                <span class="dato-val"><?php echo htmlspecialchars($entrenador->nacionalidad); ?></span>
            </div>
            <div class="dato-item">
                <span class="dato-label">🧠 Estilo de juego</span>
                <span class="dato-val"><?php echo htmlspecialchars($entrenador->estilo_juego); ?></span>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ── JUGADORES agrupados por posición ── -->
<?php
// Normalizar posiciones: Extremo y variantes → Delantero
function normalizar_posicion(string $pos): string {
    $pos = trim($pos);
    // Cualquier cosa que contenga "Extremo" va a Delanteros
    if (stripos($pos, 'Extremo') !== false) return 'Delantero';
    return $pos;
}

$por_posicion = [];
foreach ($jugadores as $j) {
    $grupo = normalizar_posicion($j->posicion ?? 'Otro');
    $por_posicion[$grupo][] = $j;
}
$orden = ['Portero','Defensa','Centrocampista','Delantero'];
uksort($por_posicion, function($a, $b) use ($orden) {
    $ia = ($p = array_search($a, $orden)) !== false ? $p : 99;
    $ib = ($p = array_search($b, $orden)) !== false ? $p : 99;
    return $ia - $ib;
});
$iconos_pos = ['Portero'=>'🧤','Defensa'=>'🛡️','Centrocampista'=>'⚙️','Delantero'=>'⚽'];
?>

<div class="seccion-titulo">👕 Plantilla (<?php echo count($jugadores); ?> jugadores)</div>

<?php foreach ($por_posicion as $pos => $lista): ?>
<div class="pos-seccion">
    <div class="pos-titulo">
        <?php echo ($iconos_pos[$pos] ?? '👟') . ' ' . htmlspecialchars($pos) . 's'; ?>
    </div>
    <div class="jugadores-grid">
        <?php foreach ($lista as $j):
            $foto_jug = foto_jugador($j->nombre);
            $media    = (int)$j->media_fifa;
            $media_cl = $media >= 85 ? 'media-oro' : ($media >= 75 ? 'media-plata' : 'media-bronce');
        ?>
        <div class="persona-card <?php echo $j->es_titular ? 'es-titular' : ''; ?>"
             onclick="abrirModal('modal-jug-<?php echo $j->id; ?>')">
            <?php if ($j->es_titular): ?>
                <span class="badge-titular">Titular</span>
            <?php endif; ?>
            <?php if ($j->lesional): ?>
                <span class="badge-lesion">🤕</span>
            <?php endif; ?>
            <div class="persona-foto-wrap">
                <img src="<?php echo $foto_jug; ?>" alt="<?php echo htmlspecialchars($j->nombre); ?>"
                     class="persona-foto" onerror="this.src='/images/jugadores/soccer-player-silhouette-free-png.png'">
            </div>
            <div class="persona-info">
                <span class="persona-nombre"><?php echo htmlspecialchars($j->nombre); ?></span>
                <span class="persona-dorsal">#<?php echo $j->dorsal; ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endforeach; ?>

<!-- ════ MODALES JUGADORES (fuera del grid, al final del body) ════ -->
<?php foreach ($jugadores as $j):
    $foto_jug = foto_jugador($j->nombre);
    $media    = (int)$j->media_fifa;
    $media_cl = $media >= 85 ? 'media-oro' : ($media >= 75 ? 'media-plata' : 'media-bronce');
?>
<div id="modal-jug-<?php echo $j->id; ?>" class="modal-overlay">
    <div class="modal-card">
        <button class="modal-cerrar" onclick="cerrarModal('modal-jug-<?php echo $j->id; ?>')">✕</button>
        <div class="modal-foto-wrap">
            <img src="<?php echo $foto_jug; ?>" alt="<?php echo htmlspecialchars($j->nombre); ?>"
                 class="modal-foto" onerror="this.src='/images/jugadores/soccer-player-silhouette-free-png.png'">
        </div>
        <h2 class="modal-nombre"><?php echo htmlspecialchars($j->nombre); ?></h2>
        <div class="modal-badges">
            <span class="persona-badge pos-badge"><?php echo htmlspecialchars($j->posicion); ?></span>
            <?php if ($j->es_titular): ?>
                <span class="persona-badge badge-verde">Titular</span>
            <?php endif; ?>
            <?php if ($j->lesional): ?>
                <span class="persona-badge badge-rojo">🤕 Lesionado</span>
            <?php endif; ?>
        </div>
        <div class="modal-datos">
            <div class="dato-item">
                <span class="dato-label">👕 Dorsal</span>
                <span class="dato-val">#<?php echo $j->dorsal; ?></span>
            </div>
            <div class="dato-item">
                <span class="dato-label">🎂 Edad</span>
                <span class="dato-val"><?php echo $j->edad; ?> años</span>
            </div>
            <div class="dato-item">
                <span class="dato-label">🌍 Nacionalidad</span>
                <span class="dato-val"><?php echo htmlspecialchars($j->nacionalidad); ?></span>
            </div>
            <div class="dato-item">
                <span class="dato-label">⭐ Media FIFA</span>
                <span class="dato-val">
                    <span class="media-badge <?php echo $media_cl; ?>"><?php echo $media; ?></span>
                </span>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>

<?php endif; ?>

<!-- WIDGET TEMAS -->
<div class="widget-temas">
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        <button type="submit" name="tema_pref" value="" title="Modo Azul (Original)">🔵</button>
        <button type="submit" name="tema_pref" value="tema-claro" title="Modo Claro">⚪</button>
    </form>
</div>

<script>
function abrirModal(id) {
    // Cerrar cualquier modal abierto antes
    document.querySelectorAll('.modal-overlay.activo').forEach(function(m) {
        m.classList.remove('activo');
    });
    var el = document.getElementById(id);
    if (el) {
        el.classList.add('activo');
        document.body.style.overflow = 'hidden';
    }
}
function cerrarModal(id) {
    var el = document.getElementById(id);
    if (el) {
        el.classList.remove('activo');
        document.body.style.overflow = '';
    }
}
// Cerrar al hacer click fuera de la tarjeta
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('activo');
        document.body.style.overflow = '';
    }
});
// Cerrar con ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.activo').forEach(function(m) {
            m.classList.remove('activo');
            document.body.style.overflow = '';
        });
    }
});
</script>

</body>
</html>