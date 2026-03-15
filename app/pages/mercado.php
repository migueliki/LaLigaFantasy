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

$usuarioId = (int)$_SESSION['usuario_id'];

// ── Saldo del usuario ──
$stmtSaldo = $pdo->prepare("SELECT saldo, nombre_equipo FROM usuarios_equipos WHERE usuario_id = ?");
$stmtSaldo->execute([$usuarioId]);
$equipoUsuario = $stmtSaldo->fetch(PDO::FETCH_OBJ);
$saldo = $equipoUsuario ? (float)$equipoUsuario->saldo : 0;

// ── Jugadores en el mercado (disponibles, no propiedad del usuario) ──
$stmtMercado = $pdo->prepare("
    SELECT j.id, j.nombre, j.posicion, j.media_fifa, j.dorsal, j.lesional,
           e.nombre AS equipo_nombre,
           m.precio, m.disponible
    FROM mercado m
    JOIN jugadores j ON m.jugador_id = j.id
    JOIN equipos e ON j.equipo_id = e.id
    LEFT JOIN usuarios_jugadores uj ON uj.jugador_id = j.id AND uj.usuario_id = ?
    WHERE m.disponible = TRUE AND uj.id IS NULL
    ORDER BY j.posicion, j.nombre
");
$stmtMercado->execute([$usuarioId]);
$jugadoresMercado = $stmtMercado->fetchAll(PDO::FETCH_OBJ);

// ── Mi plantilla (jugadores del usuario) ──
$stmtPlantilla = $pdo->prepare("
    SELECT j.id, j.nombre, j.posicion, j.media_fifa, j.dorsal, j.lesional,
           e.nombre AS equipo_nombre,
           uj.precio_compra,
           m.precio as precio_mercado
    FROM usuarios_jugadores uj
    JOIN jugadores j ON uj.jugador_id = j.id
    JOIN equipos e ON j.equipo_id = e.id
    JOIN mercado m ON m.jugador_id = j.id
    WHERE uj.usuario_id = ?
    ORDER BY j.posicion, j.nombre
");
$stmtPlantilla->execute([$usuarioId]);
$miPlantilla = $stmtPlantilla->fetchAll(PDO::FETCH_OBJ);

// ── Mapa escudos ──
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

function foto_jugador_mercado(string $nombre): string {
    $n = strtolower($nombre);
    $n = str_replace(['á','é','í','ó','ú','ñ','ü','ç',' ','-','.','\''],
                     ['a','e','i','o','u','n','u','c','_','_','',''], $n);
    $n = preg_replace('/[^a-z0-9_]/', '', $n);
    $real = __DIR__ . '/../../images/jugadores/' . $n . '.png';
    return file_exists($real)
        ? BASE_URL . "/images/jugadores/{$n}.png"
        : BASE_URL . '/images/jugadores/soccer-player-silhouette-free-png.png';
}

function escudo_url_mercado(string $equipo, array $escudos): string {
    return isset($escudos[$equipo])
        ? BASE_URL . '/images/escudos/' . $escudos[$equipo]
        : BASE_URL . '/images/favicon.png';
}

function badge_pos(string $pos): string {
    $p = trim($pos);
    $grupos = [
        'Portero'        => 'Portero',
        'Defensa'        => 'Defensa',
        'Centrocampista' => 'Centrocampista',
        'Extremo'        => 'Extremo',
        'Delantero'      => 'Delantero',
    ];
    foreach ($grupos as $key => $label) {
        if (stripos($p, $key) !== false) return $label;
    }
    return $p;
}

function media_clase(int $m): string {
    return $m >= 85 ? 'media-oro' : ($m >= 75 ? 'media-plata' : 'media-bronce');
}

function precio_fmt(float $p): string {
    if ($p >= 1000000) return number_format($p / 1000000, 1, ',', '.') . 'M €';
    if ($p >= 1000)    return number_format($p / 1000, 0, ',', '.') . 'K €';
    return number_format($p, 0, ',', '.') . ' €';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mercado - LaLiga Fantasy</title>
    <meta name="description" content="Compra y vende jugadores en el mercado de LaLiga Fantasy.">

    <meta property="og:type" content="website">
    <meta property="og:title" content="Mercado - LaLiga Fantasy">
    <meta property="og:description" content="Compra y vende jugadores en el mercado de LaLiga Fantasy.">
    <meta property="og:image" content="https://laligafantasy.site/images/laliga-logo.png">
    <meta property="og:site_name" content="LaLiga Fantasy">
    <meta property="og:locale" content="es_ES">

    <link rel="icon" type="image/png" href="<?= BASE_URL ?>/images/<?= $clase_tema === 'tema-laliga' ? 'LL_RGB_h_color.png' : 'favicon.png' ?>">
    <link rel="shortcut icon" href="<?= BASE_URL ?>/images/<?= $clase_tema === 'tema-laliga' ? 'LL_RGB_h_color.png' : 'favicon.png' ?>" type="image/x-icon">
    <link rel="stylesheet" href="<?= BASE_URL ?>/css/inicio.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/css/equipos.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/css/mercado.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/css/cookie_tema.css">
</head>
<body class="<?= htmlspecialchars($clase_tema) ?>">

<!-- NAVEGACIÓN -->
<div class="navegacion">
    <nav>
        <a href="<?= BASE_URL ?>/inicio.php">Inicio</a>
        <a href="<?= BASE_URL ?>/pages/equipos.php">Equipos</a>
        <a href="<?= BASE_URL ?>/pages/calendario.php">Calendario</a>
        <a href="<?= BASE_URL ?>/pages/plantilla.php">Plantilla</a>
        <a href="<?= BASE_URL ?>/pages/mercado.php" class="nav-active">Mercado</a>
        <a href="<?= BASE_URL ?>/pages/noticias.php">Noticias</a>
        <a href="<?= BASE_URL ?>/logout.php">Cerrar Sesión</a>
    </nav>
</div>

<!-- CABECERA -->
<div class="mercado-header">
    <h1>
        <img src="<?= BASE_URL ?>/images/<?= $clase_tema === 'tema-laliga' ? 'LL_RGB_h_color.png' : 'favicon.png' ?>" alt="LaLiga" style="height:1em;vertical-align:middle;margin-right:8px;">
        Mercado
    </h1>
    <p class="mercado-subtitulo">Compra y vende jugadores para tu equipo</p>
</div>

<!-- SALDO -->
<div class="saldo-banner<?= $saldo < 5000000 ? ' saldo-bajo' : '' ?>">
    <span>💰 Saldo disponible:</span>
    <span class="saldo-valor" id="saldo-display"><?= precio_fmt($saldo) ?></span>
    <?php if ($equipoUsuario): ?>
        <span style="color:#8aa0c0;font-size:0.85rem;">&nbsp;·&nbsp;<?= htmlspecialchars($equipoUsuario->nombre_equipo) ?></span>
    <?php endif; ?>
</div>

<!-- TABS -->
<div class="mercado-tabs">
    <button class="tab-btn activo" onclick="cambiarTab('disponibles', this)">🛒 Mercado (<?= count($jugadoresMercado) ?>)</button>
    <button class="tab-btn" onclick="cambiarTab('miplantilla', this)">👕 Mi Plantilla (<?= count($miPlantilla) ?>)</button>
</div>

<!-- FILTROS (mercado) -->
<div class="mercado-filtros" id="filtros-disponibles">
    <input type="text" id="buscador" placeholder="🔍 Buscar jugador o equipo..." oninput="filtrar()">
    <button class="btn-filtro activo" onclick="setPos('', this)">Todos</button>
    <button class="btn-filtro" onclick="setPos('Portero', this)">🧤 Porteros</button>
    <button class="btn-filtro" onclick="setPos('Defensa', this)">🛡️ Defensas</button>
    <button class="btn-filtro" onclick="setPos('Centrocampista', this)">⚙️ Medios</button>
    <button class="btn-filtro" onclick="setPos('Delantero', this)">⚽ Delanteros</button>
</div>

<!-- ═══ SECCIÓN: MERCADO DISPONIBLE ═══ -->
<div id="tab-disponibles">
    <?php if (empty($jugadoresMercado)): ?>
        <div class="mercado-grid"><p class="mercado-vacio">No hay jugadores disponibles en el mercado.</p></div>
    <?php else: ?>
    <div class="mercado-grid" id="grid-disponibles">
        <?php foreach ($jugadoresMercado as $j):
            $foto   = foto_jugador_mercado($j->nombre);
            $escudo = escudo_url_mercado($j->equipo_nombre, $escudos);
            $pos    = badge_pos($j->posicion ?? '');
            $media  = (int)$j->media_fifa;
            $precio = (float)$j->precio;
            $puedeComprar = ($saldo >= $precio);
        ?>
        <div class="mercado-card"
             data-nombre="<?= strtolower(htmlspecialchars($j->nombre)) ?>"
             data-equipo="<?= strtolower(htmlspecialchars($j->equipo_nombre)) ?>"
             data-pos="<?= htmlspecialchars($pos) ?>">

            <?php if ($j->lesional): ?><span class="badge-lesion" style="position:absolute;top:10px;right:10px;font-size:1rem;">🤕</span><?php endif; ?>

            <div class="mercado-foto-wrap">
                <img src="<?= $foto ?>" alt="<?= htmlspecialchars($j->nombre) ?>"
                     class="mercado-foto"
                     onerror="this.src='<?= BASE_URL ?>/images/jugadores/soccer-player-silhouette-free-png.png'">
            </div>

            <span class="badge-pos pos-<?= htmlspecialchars($pos) ?>"><?= htmlspecialchars($pos) ?></span>
            <span class="mercado-nombre"><?= htmlspecialchars($j->nombre) ?></span>
            <span class="mercado-equipo">
                <img src="<?= $escudo ?>" alt="" onerror="this.style.display='none'">
                <?= htmlspecialchars($j->equipo_nombre) ?>
            </span>
            <span class="mercado-media <?= media_clase($media) ?>">⭐ <?= $media ?></span>
            <span class="mercado-precio<?= !$puedeComprar ? ' caro' : '' ?>"><?= precio_fmt($precio) ?></span>

            <button class="btn-comprar"
                    <?= !$puedeComprar ? 'disabled title="Saldo insuficiente"' : '' ?>
                    onclick="comprarJugador(<?= (int)$j->id ?>, this)">
                <?= $puedeComprar ? '🛒 Comprar' : '🔒 Sin saldo' ?>
            </button>
        </div>
        <?php endforeach; ?>
    </div>
    <!-- PAGINADOR MERCADO -->
    <div class="paginador" id="paginador-disponibles"></div>
    <?php endif; ?>
</div>

<!-- ═══ SECCIÓN: MI PLANTILLA ═══ -->
<div id="tab-miplantilla" style="display:none;">
    <?php if (empty($miPlantilla)): ?>
        <div class="mercado-grid"><p class="mercado-vacio">No tienes jugadores en tu plantilla todavía.<br>¡Empieza comprando en el mercado!</p></div>
    <?php else: ?>
    <div class="mercado-grid">
        <?php foreach ($miPlantilla as $j):
            $foto      = foto_jugador_mercado($j->nombre);
            $escudo    = escudo_url_mercado($j->equipo_nombre, $escudos);
            $pos       = badge_pos($j->posicion ?? '');
            $media     = (int)$j->media_fifa;
            $precioVenta = (float)$j->precio_mercado;
            $precioCompra = (float)($j->precio_compra ?? $precioVenta);
            $ganancia  = $precioVenta - $precioCompra;
        ?>
        <div class="mercado-card">
            <?php if ($j->lesional): ?><span style="position:absolute;top:10px;right:10px;font-size:1rem;">🤕</span><?php endif; ?>

            <div class="mercado-foto-wrap">
                <img src="<?= $foto ?>" alt="<?= htmlspecialchars($j->nombre) ?>"
                     class="mercado-foto"
                     onerror="this.src='<?= BASE_URL ?>/images/jugadores/soccer-player-silhouette-free-png.png'">
            </div>

            <span class="badge-pos pos-<?= htmlspecialchars($pos) ?>"><?= htmlspecialchars($pos) ?></span>
            <span class="mercado-nombre"><?= htmlspecialchars($j->nombre) ?></span>
            <span class="mercado-equipo">
                <img src="<?= $escudo ?>" alt="" onerror="this.style.display='none'">
                <?= htmlspecialchars($j->equipo_nombre) ?>
            </span>
            <span class="mercado-media <?= media_clase($media) ?>">⭐ <?= $media ?></span>
            <span class="mercado-precio"><?= precio_fmt($precioVenta) ?></span>

            <?php if ($ganancia >= 0): ?>
                <span style="font-size:0.74rem;color:#4ade80;margin-bottom:6px;">▲ +<?= precio_fmt($ganancia) ?></span>
            <?php else: ?>
                <span style="font-size:0.74rem;color:#f87171;margin-bottom:6px;">▼ <?= precio_fmt($ganancia) ?></span>
            <?php endif; ?>

            <button class="btn-vender" onclick="venderJugador(<?= (int)$j->id ?>, this)">
                💸 Vender
            </button>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- WIDGET TEMAS -->
<div class="widget-temas">
    <button type="button" onclick="__llSetTema('tema-laliga')" title="Modo LaLiga">🔴</button>
    <button type="button" onclick="__llSetTema('tema-original')" title="Modo Azul (Original)">🔵</button>
</div>
<script>
function __llSetTema(t){var e=new Date();e.setTime(e.getTime()+30*24*60*60*1000);document.cookie='preferencia_tema='+t+';expires='+e.toUTCString()+';path=/;SameSite=Lax';location.reload();}
</script>

<!-- TOAST -->
<div id="toast"></div>

<script>
const BASE_URL  = <?= json_encode(BASE_URL) ?>;
const CSRF      = <?= json_encode($_SESSION['csrf_token']) ?>;
let   saldoActual = <?= json_encode($saldo) ?>;

// ─── TABS ───
function cambiarTab(tab, btn) {
    document.getElementById('tab-disponibles').style.display  = tab === 'disponibles'  ? '' : 'none';
    document.getElementById('tab-miplantilla').style.display  = tab === 'miplantilla'  ? '' : 'none';
    document.getElementById('filtros-disponibles').style.display = tab === 'disponibles' ? '' : 'none';
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('activo'));
    btn.classList.add('activo');
}

// ─── PAGINACIÓN ───
const POR_PAGINA = 16;
let paginaActual = 1;
let posActiva = '';

function getCardsFiltradas() {
    const q = document.getElementById('buscador').value.toLowerCase();
    return Array.from(document.querySelectorAll('#grid-disponibles .mercado-card')).filter(card => {
        const nombre = card.dataset.nombre || '';
        const equipo = card.dataset.equipo || '';
        const pos    = card.dataset.pos    || '';
        const matchQ = !q || nombre.includes(q) || equipo.includes(q);
        const matchP = !posActiva || pos.includes(posActiva);
        return matchQ && matchP;
    });
}

function renderPagina(pag) {
    const todas  = getCardsFiltradas();
    const total  = todas.length;
    const pages  = Math.max(1, Math.ceil(total / POR_PAGINA));
    paginaActual = Math.min(Math.max(1, pag), pages);

    const inicio = (paginaActual - 1) * POR_PAGINA;
    const fin    = inicio + POR_PAGINA;

    // Ocultar todas; mostrar solo las de esta página
    document.querySelectorAll('#grid-disponibles .mercado-card').forEach(card => {
        card.style.display = 'none';
    });
    todas.forEach((card, i) => {
        card.style.display = (i >= inicio && i < fin) ? '' : 'none';
    });

    // Render paginador
    const nav = document.getElementById('paginador-disponibles');
    nav.innerHTML = '';
    if (pages <= 1) return;

    const btn = (label, page, disabled, active) => {
        const b = document.createElement('button');
        b.textContent = label;
        b.className   = 'pag-btn' + (active ? ' pag-activo' : '');
        b.disabled    = disabled;
        b.addEventListener('click', () => { renderPagina(page); nav.scrollIntoView({behavior:'smooth', block:'nearest'}); });
        return b;
    };

    nav.appendChild(btn('«', 1, paginaActual === 1, false));
    nav.appendChild(btn('‹', paginaActual - 1, paginaActual === 1, false));

    // Páginas numeradas (máx 7 botones visibles con elipsis)
    let start = Math.max(1, paginaActual - 3);
    let end   = Math.min(pages, start + 6);
    start     = Math.max(1, end - 6);

    if (start > 1) {
        nav.appendChild(btn('1', 1, false, false));
        if (start > 2) { const sp = document.createElement('span'); sp.textContent = '…'; sp.className = 'pag-sep'; nav.appendChild(sp); }
    }
    for (let p = start; p <= end; p++) {
        nav.appendChild(btn(p, p, false, p === paginaActual));
    }
    if (end < pages) {
        if (end < pages - 1) { const sp = document.createElement('span'); sp.textContent = '…'; sp.className = 'pag-sep'; nav.appendChild(sp); }
        nav.appendChild(btn(pages, pages, false, false));
    }

    nav.appendChild(btn('›', paginaActual + 1, paginaActual === pages, false));
    nav.appendChild(btn('»', pages, paginaActual === pages, false));

    // Contador
    const info = document.createElement('span');
    info.className = 'pag-info';
    info.textContent = `Página ${paginaActual} de ${pages} · ${total} jugadores`;
    nav.appendChild(info);
}

function setPos(pos, btn) {
    posActiva = pos;
    document.querySelectorAll('.btn-filtro').forEach(b => b.classList.remove('activo'));
    btn.classList.add('activo');
    filtrar();
}
function filtrar() {
    renderPagina(1);
}

// Inicializar paginación al cargar
document.addEventListener('DOMContentLoaded', () => renderPagina(1));

// ─── TOAST ───
function toast(msg, tipo) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.className   = 'mostrar ' + (tipo || '');
    clearTimeout(t._timer);
    t._timer = setTimeout(() => { t.className = ''; }, 3200);
}

// ─── ACTUALIZAR SALDO EN PANTALLA ───
function actualizarSaldo(nuevoSaldo) {
    saldoActual = nuevoSaldo;
    const fmt = nuevoSaldo >= 1e6
        ? (nuevoSaldo / 1e6).toFixed(1).replace('.', ',') + 'M €'
        : (nuevoSaldo / 1000).toFixed(0).replace('.', ',') + 'K €';
    const el = document.getElementById('saldo-display');
    if (el) el.textContent = fmt;
    // Actualizar botones caro/sin saldo
    document.querySelectorAll('#grid-disponibles .mercado-card').forEach(card => {
        const priceEl = card.querySelector('.mercado-precio');
        const btn     = card.querySelector('.btn-comprar');
        if (!priceEl || !btn) return;
        // extraemos precio numérico del atributo data si lo tenemos; si no, lo dejamos
    });
}

// ─── COMPRAR ───
function comprarJugador(jugadorId, btn) {
    if (btn.disabled) return;
    btn.disabled = true;
    btn.textContent = '⏳ Comprando...';

    fetch(BASE_URL + '/comprar_jugador.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'jugador_id=' + encodeURIComponent(jugadorId) + '&csrf_token=' + encodeURIComponent(CSRF)
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            toast('✅ ' + data.mensaje, 'ok');
            actualizarSaldo(data.saldo_nuevo);
            // Marcar carta como vendida y eliminarla con animación
            const card = btn.closest('.mercado-card');
            card.classList.add('vendido');
            setTimeout(() => { card.remove(); }, 800);
        } else {
            toast('❌ ' + data.mensaje, 'err');
            btn.disabled = false;
            btn.textContent = '🛒 Comprar';
        }
    })
    .catch(() => {
        toast('❌ Error de conexión. Inténtalo de nuevo.', 'err');
        btn.disabled = false;
        btn.textContent = '🛒 Comprar';
    });
}

// ─── VENDER ───
function venderJugador(jugadorId, btn) {
    if (!confirm('¿Estás seguro de que quieres vender a este jugador?')) return;
    btn.disabled = true;
    btn.textContent = '⏳ Vendiendo...';

    fetch(BASE_URL + '/vender_jugador.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'jugador_id=' + encodeURIComponent(jugadorId) + '&csrf_token=' + encodeURIComponent(CSRF)
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            toast('✅ ' + data.mensaje, 'ok');
            actualizarSaldo(data.saldo_nuevo);
            const card = btn.closest('.mercado-card');
            card.classList.add('vendido');
            setTimeout(() => { card.remove(); }, 800);
        } else {
            toast('❌ ' + data.mensaje, 'err');
            btn.disabled = false;
            btn.textContent = '💸 Vender';
        }
    })
    .catch(() => {
        toast('❌ Error de conexión. Inténtalo de nuevo.', 'err');
        btn.disabled = false;
        btn.textContent = '💸 Vender';
    });
}
</script>

</body>
</html>
<?php ob_end_flush(); ?>
