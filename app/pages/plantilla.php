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

$uid = (int)$_SESSION['usuario_id'];

// ── Auto-migrar columna formacion si no existe ──
try {
    $pdo->exec("ALTER TABLE usuarios_equipos ADD COLUMN formacion VARCHAR(20) NOT NULL DEFAULT '4-3-3'");
} catch (PDOException $e) { /* ya existe */ }

try {
    $pdo->exec("ALTER TABLE usuarios_jugadores ADD COLUMN slot_titular TINYINT UNSIGNED NULL DEFAULT NULL");
} catch (PDOException $e) { /* ya existe */ }

// ── Datos del equipo del usuario ──
$stmtEq = $pdo->prepare("SELECT nombre_equipo, saldo, formacion FROM usuarios_equipos WHERE usuario_id = ?");
$stmtEq->execute([$uid]);
$miEquipo = $stmtEq->fetch(PDO::FETCH_OBJ);
$formacionActual = $miEquipo ? ($miEquipo->formacion ?? DEFAULT_FORMATION) : DEFAULT_FORMATION;
$saldo     = $miEquipo ? (float)$miEquipo->saldo : 0;
$nombreEq  = $miEquipo ? $miEquipo->nombre_equipo : 'Mi Equipo';

// ── Jugadores del usuario ──
$stmtJ = $pdo->prepare("
    SELECT j.id, j.nombre, j.posicion, j.dorsal, j.media_fifa,
           e.nombre AS equipo,
        uj.es_titular, uj.es_capitan, uj.precio_compra, m.precio, uj.slot_titular
    FROM usuarios_jugadores uj
    JOIN jugadores j ON uj.jugador_id = j.id
    LEFT JOIN equipos e ON j.equipo_id = e.id
    LEFT JOIN mercado m ON m.jugador_id = j.id
    WHERE uj.usuario_id = ?
    ORDER BY FIELD(j.posicion,'Portero','Defensa','Centrocampista','Extremo','Delantero'), j.nombre
");
$stmtJ->execute([$uid]);
$jugadores = $stmtJ->fetchAll(PDO::FETCH_OBJ);

$formaciones = app_formations();
$slotsFormacion = formation_slots($formacionActual);

// ── Agrupar titulares y suplentes ──
$totalSlots = formation_total_slots($formacionActual);
$lineupBySlot = [];
for ($s = 1; $s <= $totalSlots; $s++) {
    $lineupBySlot[$s] = null;
}

$suplentes = [];
$pendientesTitulares = [];

foreach ($jugadores as $j) {
    $slot = (int)($j->slot_titular ?? 0);
    if ($j->es_titular && $slot >= 1 && $slot <= $totalSlots && $lineupBySlot[$slot] === null) {
        $lineupBySlot[$slot] = $j;
    } elseif ($j->es_titular) {
        $pendientesTitulares[] = $j;
    } else {
        $suplentes[] = $j;
    }
}

if (!empty($pendientesTitulares)) {
    foreach ($pendientesTitulares as $j) {
        $insertado = false;
        for ($s = 1; $s <= $totalSlots; $s++) {
            if ($lineupBySlot[$s] === null) {
                $lineupBySlot[$s] = $j;
                $insertado = true;
                break;
            }
        }
        if (!$insertado) {
            $suplentes[] = $j;
        }
    }
}

$totalJugadores  = count($jugadores);
$totalTitulares  = 0;
for ($s = 1; $s <= $totalSlots; $s++) {
    if ($lineupBySlot[$s] !== null) {
        $totalTitulares++;
    }
}
$totalSuplentes  = count($suplentes);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Plantilla - LaLiga Fantasy</title>
    <meta name="description" content="Gestiona tu plantilla, elige formación y alinea tus jugadores.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://laligafantasy.site/pages/plantilla.php">
    <meta property="og:title" content="Mi Plantilla - LaLiga Fantasy">
    <meta property="og:image" content="https://laligafantasy.site/images/metatag.jpg">
    <meta property="og:site_name" content="LaLiga Fantasy">
    <meta property="og:locale" content="es_ES">
    <link rel="icon" type="image/png" href="<?= theme_logo_url($clase_tema) ?>">
    <link rel="shortcut icon" href="<?= theme_logo_url($clase_tema) ?>" type="image/x-icon">
    <link rel="stylesheet" href="<?= BASE_URL ?>/css/inicio.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/css/equipos.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/css/plantilla.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/css/cookie_tema.css?v=20260315-1">
</head>
<body class="<?= $clase_tema ?>">

<!-- NAVEGACIÓN -->
<div class="navegacion">
    <nav>
        <a href="<?= BASE_URL ?>/inicio.php">Inicio</a>
        <a href="<?= BASE_URL ?>/pages/equipos.php">Equipos</a>
        <a href="<?= BASE_URL ?>/pages/calendario.php">Calendario</a>
        <a href="<?= BASE_URL ?>/pages/plantilla.php" class="nav-active">Plantilla</a>
        <a href="<?= BASE_URL ?>/pages/mercado.php">Mercado</a>
        <a href="<?= BASE_URL ?>/pages/noticias.php">Noticias</a>
        <a href="<?= BASE_URL ?>/logout.php">Cerrar Sesión</a>
    </nav>
</div>

<!-- CABECERA -->
<div class="plantilla-header">
    <h1>
        <img src="<?= theme_logo_url($clase_tema) ?>" onerror="this.onerror=null;this.src='<?= asset_url('images/favicon.png') ?>';" alt="LaLiga" style="height:1em;vertical-align:middle;margin-right:8px;">
        Mi Plantilla Fantasy
    </h1>
    <p class="equipo-nombre"><?= htmlspecialchars($nombreEq) ?></p>
</div>

<!-- STATS -->
<div class="plantilla-stats">
    <div class="pstat">
        <span class="pstat-num"><?= $totalJugadores ?></span>
        <span class="pstat-label">Jugadores</span>
    </div>
    <div class="pstat">
        <span class="pstat-num"><?= $totalTitulares ?></span>
        <span class="pstat-label">Titulares</span>
    </div>
    <div class="pstat">
        <span class="pstat-num"><?= $totalSuplentes ?></span>
        <span class="pstat-label">Suplentes</span>
    </div>
    <div class="pstat">
        <span class="pstat-num pstat-saldo<?= $saldo<5000000?' pstat-saldo--bajo':'' ?>"><?= number_format($saldo/1000000,1) ?>M€</span>
        <span class="pstat-label">Saldo</span>
    </div>
</div>

<?php if ($totalJugadores === 0): ?>
<!-- VACÍO -->
<div class="plantilla-vacia">
    <div class="vacia-icon">📋</div>
    <p>Tu plantilla está vacía.<br>Ve al mercado a fichar jugadores.</p>
    <a href="<?= BASE_URL ?>/pages/mercado.php">🛒 Ir al Mercado</a>
</div>

<?php else: ?>

<!-- SELECTOR FORMACIÓN -->
<div class="formacion-selector">
    <span class="label-form">Formación:</span>
    <?php foreach (array_keys($formaciones) as $f): ?>
    <button class="btn-formacion <?= $f===$formacionActual?'activa':'' ?>"
            onclick="cambiarFormacion('<?= $f ?>')"><?= $f ?></button>
    <?php endforeach; ?>
</div>
<!-- CAMPO -->
<div class="campo-wrap">
<div class="campo">

<?php
// Función helper para renderizar una fila de campo
function renderFilaCampo(array $lineupBySlot, int $slots, string $posLabel, string $silhouette, int &$slotCursor): void {
    echo '<div class="campo-fila">';
    for ($i = 0; $i < $slots; $i++) {
        $slotActual = $slotCursor;
        $j = $lineupBySlot[$slotActual] ?? null;
        if ($j !== null) {
            $foto = player_photo_url($j->nombre);
            $pos  = htmlspecialchars(normalize_position_label($j->posicion ?? ''));
            $cap  = $j->es_capitan ? ' capitan' : '';
            $apellido = explode(' ', $j->nombre);
            $corto = end($apellido);
            echo "<div class=\"campo-jugador{$cap}\" draggable=\"true\" data-slot=\"{$slotActual}\" data-jugador-id=\"{$j->id}\" data-titular=\"1\" onclick=\"toggleTitular({$j->id}, false)\" title=\"Arrastrar para mover de posición o clic para enviar al banquillo\">";
            echo   "<div class=\"cj-foto-wrap\"><img src=\"{$foto}\" alt=\"\" onerror=\"this.src='{$silhouette}'\"></div>";
            echo   "<span class=\"cj-nombre\">" . htmlspecialchars($corto) . "</span>";
            echo   "<span class=\"cj-badge pos-{$pos}\">{$pos}</span>";
            echo "</div>";
        } else {
            echo "<div class=\"campo-slot-vacio\" data-drop-campo=\"1\" data-slot-target=\"{$slotActual}\" title=\"Arrastra un jugador aquí\">";
            echo   "<div class=\"slot-circulo\">+</div>";
            echo   "<span class=\"slot-label\">{$posLabel}</span>";
            echo "</div>";
        }
        $slotCursor++;
    }
    echo '</div>';
}

$sil = htmlspecialchars(BASE_URL . '/images/jugadores/soccer-player-silhouette-free-png.png');

$slotCursor = 1;
renderFilaCampo($lineupBySlot, 1, 'Portero', $sil, $slotCursor);
renderFilaCampo($lineupBySlot, $slotsFormacion['Defensa'], 'Defensa', $sil, $slotCursor);
renderFilaCampo($lineupBySlot, $slotsFormacion['Centrocampista'], 'Centrocampista', $sil, $slotCursor);
renderFilaCampo($lineupBySlot, $slotsFormacion['Delantero'], 'Delantero', $sil, $slotCursor);
?>

</div><!-- /campo -->
</div><!-- /campo-wrap -->

<!-- BANQUILLO -->
<?php if (!empty($suplentes)): ?>
<div class="banquillo-section">
    <div class="banquillo-titulo">🪑 Banquillo (<?= count($suplentes) ?>)</div>
    <div class="banquillo-grid">
        <?php foreach ($suplentes as $j):
            $foto = player_photo_url($j->nombre);
            $posNorm = normalize_position_label($j->posicion ?? '');
        ?>
        <div class="bench-card" draggable="true" data-jugador-id="<?= $j->id ?>" data-titular="0" title="Arrastra al campo o clic en Titular">
            <img src="<?= $foto ?>" alt="" class="bench-foto"
                 onerror="this.src='<?= BASE_URL ?>/images/jugadores/soccer-player-silhouette-free-png.png'">
            <div class="bench-info">
                <div class="bench-nombre"><?= htmlspecialchars($j->nombre) ?></div>
                <span class="bench-pos pos-<?= htmlspecialchars($posNorm) ?>"><?= htmlspecialchars($posNorm) ?></span>
            </div>
            <button class="bench-btn-titular" onclick="toggleTitular(<?= $j->id ?>, true)">▶ Titular</button>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php endif; ?>

<!-- WIDGET TEMAS -->
<div class="widget-temas">
    <button type="button" onclick="__llSetTema('tema-laliga')" title="Modo LaLiga">🔴</button>
    <button type="button" onclick="__llSetTema('tema-original')" title="Modo Azul (Original)">🔵</button>
</div>
<script src="<?= BASE_URL ?>/js/tema.js"></script>

<!-- TOAST -->
<div id="toast-plant"></div>

<script>
const BASE_URL = <?= json_encode(BASE_URL) ?>;
const CSRF     = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;

/* ── TOAST ── */
function toast(msg, esError = false) {
    const t = document.getElementById('toast-plant');
    t.textContent = msg;
    t.classList.toggle('error', esError);
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3000);
}

/* ── CAMBIO FORMACIÓN (guarda y recarga automáticamente) ── */
function cambiarFormacion(f) {
    document.querySelectorAll('.btn-formacion').forEach(b => {
        b.classList.toggle('activa', b.textContent.trim() === f);
        if (b.textContent.trim() === f) b.textContent = '⏳ ' + f;
    });
    fetch(BASE_URL + '/guardar_formacion.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({csrf: CSRF, formacion: f})
    })
    .then(r => r.json())
    .then(d => {
        if (d.ok) {
            location.reload();
        } else {
            toast(d.mensaje || 'Error al guardar', true);
            document.querySelectorAll('.btn-formacion').forEach(b => {
                if (b.textContent.includes(f)) b.textContent = f;
            });
        }
    })
    .catch(() => toast('Error de conexión', true));
}

/* ── TOGGLE TITULAR (click y drag) ── */
function toggleTitular(jugadorId, hacerTitular) {
    fetch(BASE_URL + '/toggle_titular.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({csrf: CSRF, jugador_id: jugadorId, es_titular: hacerTitular})
    })
    .then(r => r.json())
    .then(d => {
        if (d.ok) {
            toast(hacerTitular ? '▶ Titular' : '🪑 Al banquillo');
            setTimeout(() => location.reload(), 700);
        } else {
            toast(d.mensaje || 'Error', true);
        }
    })
    .catch(() => toast('Error de conexión', true));
}

function moverJugador(jugadorId, slotDestino) {
    fetch(BASE_URL + '/mover_jugador.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({csrf: CSRF, jugador_id: jugadorId, slot_destino: slotDestino})
    })
    .then(r => r.json())
    .then(d => {
        if (d.ok) {
            toast('Posición actualizada');
            setTimeout(() => location.reload(), 700);
        } else {
            toast(d.mensaje || 'Error al mover jugador', true);
        }
    })
    .catch(() => toast('Error de conexión', true));
}

/* ── DRAG & DROP ── */
let dragId      = null;  // jugador_id arrastrado
let dragTitular = false; // si era titular
let dragSlot    = null;  // slot actual si era titular
let dragging    = null;  // elemento DOM

document.addEventListener('dragstart', e => {
    const card = e.target.closest('[data-jugador-id]');
    if (!card) return;
    dragId      = parseInt(card.dataset.jugadorId);
    dragTitular = card.dataset.titular === '1';
    dragSlot    = card.dataset.slot ? parseInt(card.dataset.slot) : null;
    dragging    = card;
    card.classList.add('dragging');
    e.dataTransfer.effectAllowed = 'move';
});

document.addEventListener('dragend', e => {
    if (dragging) {
        dragging.classList.remove('dragging', 'drag-ghost');
        dragging = null;
    }
    // Limpiar todos los drop-over
    document.querySelectorAll('.drop-over').forEach(el => el.classList.remove('drop-over'));
});

// ── Hacer que el campo y el banquillo sean drop targets ──
const campo       = document.querySelector('.campo');
const benchGrid   = document.querySelector('.banquillo-grid');
const benchSect   = document.querySelector('.banquillo-section');

function setupDropTarget(el, esCampo) {
    if (!el) return;
    el.addEventListener('dragover', e => {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        el.classList.add('drop-over');
    });
    el.addEventListener('dragleave', e => {
        // Solo quitar si salimos al exterior real
        if (!el.contains(e.relatedTarget)) el.classList.remove('drop-over');
    });
    el.addEventListener('drop', e => {
        e.preventDefault();
        el.classList.remove('drop-over');
        if (dragId === null) return;
        if (esCampo && !dragTitular) {
            // Suplente → campo: hacer titular
            toggleTitular(dragId, true);
        } else if (!esCampo && dragTitular) {
            // Titular → banquillo: hacer suplente
            toggleTitular(dragId, false);
        }
        dragId = null;
    });
}

setupDropTarget(campo,     true);
setupDropTarget(benchGrid, false);
setupDropTarget(benchSect, false);

// Slots del campo (vacíos y ocupados) como drop targets para mover/intercambiar
document.querySelectorAll('[data-slot-target], .campo-jugador[data-slot]').forEach(slot => {
    slot.addEventListener('dragover', e => {
        e.preventDefault();
        slot.classList.add('drop-over');
    });
    slot.addEventListener('dragleave', () => slot.classList.remove('drop-over'));
    slot.addEventListener('drop', e => {
        e.preventDefault();
        slot.classList.remove('drop-over');
        const targetSlot = slot.dataset.slotTarget
            ? parseInt(slot.dataset.slotTarget)
            : (slot.dataset.slot ? parseInt(slot.dataset.slot) : null);

        if (dragId !== null && targetSlot !== null) {
            if (!(dragTitular && dragSlot === targetSlot)) {
                moverJugador(dragId, targetSlot);
            }
            dragId = null;
            dragSlot = null;
        }
    });
});
</script>

</body>
</html>
