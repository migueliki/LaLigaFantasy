<?php
ob_start();
session_start();
require_once '../config.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . BASE_URL . '/index.php');
    exit();
}

$timeout = 3600;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
    session_unset(); session_destroy();
    header('Location: ' . BASE_URL . '/index.php?timeout=1');
    exit();
}
$_SESSION['last_activity'] = time();

require_once '../conexion.php';
require_once '../csrf.php';
include_once '../cookie_tema.php';

// ── Cargar plantilla del usuario ──
$stmt = $pdo->query("SELECT * FROM plantilla ORDER BY
    FIELD(posicion,'Portero','Defensa','Centrocampista','Extremo','Delantero'), dorsal");
$jugadores = $stmt->fetchAll(PDO::FETCH_OBJ);

// ── Agrupar por posición ──
function normalizar_pos_plantilla(string $pos): string {
    $pos = trim($pos);
    if (stripos($pos, 'Extremo') !== false) return 'Delantero';
    return $pos;
}

$por_posicion = [];
foreach ($jugadores as $j) {
    $grupo = normalizar_pos_plantilla($j->posicion ?? 'Otro');
    $por_posicion[$grupo][] = $j;
}

$orden_pos = ['Portero', 'Defensa', 'Centrocampista', 'Delantero'];
uksort($por_posicion, function($a, $b) use ($orden_pos) {
    $ia = ($p = array_search($a, $orden_pos)) !== false ? $p : 99;
    $ib = ($p = array_search($b, $orden_pos)) !== false ? $p : 99;
    return $ia - $ib;
});

$iconos_pos = [
    'Portero'        => '🧤',
    'Defensa'        => '🛡️',
    'Centrocampista' => '⚙️',
    'Delantero'      => '⚽',
];

// ── Función foto jugador ──
function foto_jugador_plantilla(string $nombre): string {
    $n = strtolower($nombre);
    $n = str_replace(['á','é','í','ó','ú','ñ','ü','ç',' ','-','.','\''],
                     ['a','e','i','o','u','n','u','c','_','_','',''], $n);
    $n = preg_replace('/[^a-z0-9_]/', '', $n);
    $path = BASE_URL . "/images/jugadores/{$n}.png";
    $real = __DIR__ . '/../../images/jugadores/' . $n . '.png';
    return file_exists($real) ? $path : BASE_URL . '/images/jugadores/soccer-player-silhouette-free-png.png';
}

// ── Mapa escudo por equipo ──
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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Plantilla - LaLiga Fantasy</title>
    <meta name="description" content="Gestiona tu plantilla de LaLiga Fantasy. Consulta tus jugadores, posiciones y equipos.">

    <!-- Open Graph -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://laligafantasy.site/pages/plantilla.php">
    <meta property="og:title" content="Mi Plantilla - LaLiga Fantasy">
    <meta property="og:description" content="Gestiona tu plantilla de LaLiga Fantasy.">
    <meta property="og:image" content="https://laligafantasy.site/images/laliga-logo.png">
    <meta property="og:site_name" content="LaLiga Fantasy">
    <meta property="og:locale" content="es_ES">

    <link rel="icon" type="image/png" href="<?= BASE_URL ?>/images/favicon.png">
    <link rel="shortcut icon" href="<?= BASE_URL ?>/images/favicon.png" type="image/x-icon">
    <link rel="stylesheet" href="../css/inicio.css">
    <link rel="stylesheet" href="../css/equipos.css">
    <link rel="stylesheet" href="../css/cookie_tema.css">
</head>
<body class="<?php echo $clase_tema; ?>">

<!-- NAVEGACIÓN -->
<div class="navegacion">
    <nav>
        <a href="<?= BASE_URL ?>/inicio.php">Inicio</a>
        <a href="<?= BASE_URL ?>/pages/equipos.php">Equipos</a>
        <a href="<?= BASE_URL ?>/pages/calendario.php">Calendario</a>
        <a href="<?= BASE_URL ?>/pages/plantilla.php" class="nav-active">Plantilla</a>
        <a href="<?= BASE_URL ?>/pages/noticias.php">Noticias</a>
        <a href="<?= BASE_URL ?>/logout.php">Cerrar Sesión</a>
    </nav>
</div>

<!-- CABECERA -->
<div class="equipos-header">
    <h1>
        <img src="<?= BASE_URL ?>/images/favicon.png" alt="LaLiga" style="height:1em;vertical-align:middle;margin-right:8px;">
        Mi Plantilla Fantasy
    </h1>
    <p class="equipos-subtitulo"><?php echo count($jugadores); ?> jugador<?php echo count($jugadores) !== 1 ? 'es' : ''; ?> en tu plantilla</p>
</div>

<?php if (empty($jugadores)): ?>
    <div style="text-align:center;padding:60px 20px;color:var(--texto-secundario,#aaa);">
        <p style="font-size:3rem;margin:0;">📋</p>
        <p style="font-size:1.2rem;margin-top:16px;">Tu plantilla está vacía.</p>
    </div>
<?php else: ?>

    <?php foreach ($por_posicion as $pos => $lista): ?>
    <div class="seccion-titulo">
        <?php echo ($iconos_pos[$pos] ?? '👟') . ' ' . htmlspecialchars($pos) . 's'; ?>
    </div>
    <div class="jugadores-grid">
        <?php foreach ($lista as $j):
            $foto = foto_jugador_plantilla($j->nombre);
            $escudo_archivo = $escudos[$j->equipo] ?? null;
            $escudo_url = $escudo_archivo
                ? BASE_URL . '/images/escudos/' . $escudo_archivo
                : BASE_URL . '/images/favicon.png';
        ?>
        <div class="persona-card" onclick="abrirModal('modal-plant-<?php echo $j->id; ?>')">
            <div class="persona-foto-wrap">
                <img src="<?php echo $foto; ?>"
                     alt="<?php echo htmlspecialchars($j->nombre); ?>"
                     class="persona-foto"
                     onerror="this.src='<?= BASE_URL ?>/images/jugadores/soccer-player-silhouette-free-png.png'">
            </div>
            <div class="persona-info">
                <span class="persona-nombre"><?php echo htmlspecialchars($j->nombre); ?></span>
                <?php if ($j->dorsal): ?>
                    <span class="persona-dorsal">#<?php echo (int)$j->dorsal; ?></span>
                <?php endif; ?>
                <span class="persona-badge pos-badge"><?php echo htmlspecialchars($j->posicion ?? ''); ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>

    <!-- MODALES -->
    <?php foreach ($jugadores as $j):
        $foto = foto_jugador_plantilla($j->nombre);
        $escudo_archivo = $escudos[$j->equipo] ?? null;
        $escudo_url = $escudo_archivo
            ? BASE_URL . '/images/escudos/' . $escudo_archivo
            : BASE_URL . '/images/favicon.png';
    ?>
    <div id="modal-plant-<?php echo $j->id; ?>" class="modal-overlay">
        <div class="modal-card">
            <button class="modal-cerrar" onclick="cerrarModal('modal-plant-<?php echo $j->id; ?>')">✕</button>
            <div class="modal-foto-wrap">
                <img src="<?php echo $foto; ?>"
                     alt="<?php echo htmlspecialchars($j->nombre); ?>"
                     class="modal-foto"
                     onerror="this.src='<?= BASE_URL ?>/images/jugadores/soccer-player-silhouette-free-png.png'">
            </div>
            <h2 class="modal-nombre"><?php echo htmlspecialchars($j->nombre); ?></h2>
            <div class="modal-badges">
                <span class="persona-badge pos-badge"><?php echo htmlspecialchars($j->posicion ?? ''); ?></span>
            </div>
            <div class="modal-datos">
                <?php if ($j->dorsal): ?>
                <div class="dato-item">
                    <span class="dato-label">👕 Dorsal</span>
                    <span class="dato-val">#<?php echo (int)$j->dorsal; ?></span>
                </div>
                <?php endif; ?>
                <div class="dato-item">
                    <span class="dato-label">🏟️ Equipo</span>
                    <span class="dato-val" style="display:flex;align-items:center;gap:6px;">
                        <img src="<?php echo htmlspecialchars($escudo_url); ?>"
                             alt="<?php echo htmlspecialchars($j->equipo); ?>"
                             style="height:1.4em;vertical-align:middle;"
                             onerror="this.style.display='none'">
                        <?php echo htmlspecialchars($j->equipo); ?>
                    </span>
                </div>
                <?php if (!empty($j->nacionalidad)): ?>
                <div class="dato-item">
                    <span class="dato-label">🌍 Nacionalidad</span>
                    <span class="dato-val"><?php echo htmlspecialchars($j->nacionalidad); ?></span>
                </div>
                <?php endif; ?>
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
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('activo');
        document.body.style.overflow = '';
    }
});
</script>

</body>
</html>
