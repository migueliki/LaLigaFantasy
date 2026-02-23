<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: /index.php');
    exit();
}
// Control de timeout por inactividad
$timeout = 3600;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
    session_unset(); session_destroy();
    header('Location: /index.php?timeout=1');
    exit();
}
$_SESSION['last_activity'] = time();

include_once '../cookie_tema.php';
require_once '../csrf.php';

//  SISTEMA DE CACHÉ (se renueva cada 7 días)
$cache_dir  = __DIR__ . '/../cache/';
$cache_file = $cache_dir . 'noticias_cache.json';
$cache_ttl  = 7 * 24 * 3600; // 7 días en segundos

// Crear carpeta de caché si no existe
if (!is_dir($cache_dir)) {
    mkdir($cache_dir, 0755, true);
}

$noticias = [];
$ultima_actualizacion = null;

// ¿Existe caché válida?
if (file_exists($cache_file) && (time() - filemtime($cache_file)) < $cache_ttl) {
    $cached = json_decode(file_get_contents($cache_file), true);
    $noticias            = $cached['noticias']            ?? [];
    $ultima_actualizacion = $cached['ultima_actualizacion'] ?? null;
} else {
 
    //  FUENTES RSS de fútbol español
    $feeds = [
        [
            'nombre' => 'Marca',
            'url'    => 'https://www.marca.com/rss/futbol/primera-division.xml',
            'logo'   => '📰',
        ],
        [
            'nombre' => 'AS',
            'url'    => 'https://as.com/rss/tags/laliga.xml',
            'logo'   => '📰',
        ],
        [
            'nombre' => 'Sport',
            'url'    => 'https://www.sport.es/rss/futbol/primera-division.xml',
            'logo'   => '📰',
        ],
        [
            'nombre' => 'Mundo Deportivo',
            'url'    => 'https://www.mundodeportivo.com/rss/futbol/laliga',
            'logo'   => '📰',
        ],
    ];

    // Palabras clave para filtrar noticias de fútbol español
    $keywords = [
        'laliga', 'la liga', 'primera división', 'primera division',
        'segunda división', 'segunda division', 'segunda b',
        'copa del rey', 'supercopa', 'rfef',
        'real madrid', 'barcelona', 'atlético', 'atletico', 'sevilla',
        'valencia', 'betis', 'villarreal', 'athletic', 'osasuna',
        'celta', 'getafe', 'rayo vallecano', 'espanyol', 'girona',
        'mallorca', 'las palmas', 'leganés', 'leganes', 'alaves',
        'valladolid', 'real sociedad', 'espanyol', 'almeria',
        'liga española', 'liga española', 'selección española',
        'seleccion española', 'fútbol español', 'futbol español'
    ];

    libxml_use_internal_errors(true);

    foreach ($feeds as $feed) {
        $context = stream_context_create([
            'http' => [
                'timeout'     => 8,
                'user_agent'  => 'Mozilla/5.0 (compatible; LaLigaFantasy/1.0)',
                'follow_location' => 1,
            ]
        ]);

        $xml_raw = @file_get_contents($feed['url'], false, $context);
        if (!$xml_raw) continue;

        $xml = @simplexml_load_string($xml_raw);
        if (!$xml) continue;

        $items = $xml->channel->item ?? [];

        $count = 0;
        foreach ($items as $item) {
            if ($count >= 6) break; // máximo 6 noticias por fuente

            $titulo      = html_entity_decode((string)$item->title,       ENT_QUOTES, 'UTF-8');
            $descripcion = html_entity_decode((string)$item->description, ENT_QUOTES, 'UTF-8');
            $link        = trim((string)$item->link);
            $pub_date    = (string)($item->pubDate ?? '');

            // Limpiar etiquetas HTML de la descripción
            $descripcion = strip_tags($descripcion);
            // Truncar a 180 caracteres
            if (mb_strlen($descripcion) > 180) {
                $descripcion = mb_substr($descripcion, 0, 177) . '...';
            }

            // Filtrar por palabras clave (título o descripción)
            $texto_lower = mb_strtolower($titulo . ' ' . $descripcion);
            $es_relevante = false;
            foreach ($keywords as $kw) {
                if (strpos($texto_lower, $kw) !== false) {
                    $es_relevante = true;
                    break;
                }
            }

            if (!$es_relevante) continue;

            // Intentar extraer imagen del item
            $imagen = null;
            if (isset($item->enclosure) && (string)$item->enclosure['type'] !== '') {
                $imagen = (string)$item->enclosure['url'];
            } elseif (isset($item->children('media', true)->content)) {
                $imagen = (string)$item->children('media', true)->content->attributes()['url'];
            }

            $noticias[] = [
                'fuente'      => $feed['nombre'],
                'logo'        => $feed['logo'],
                'titulo'      => $titulo,
                'descripcion' => $descripcion,
                'link'        => $link,
                'fecha'       => $pub_date ? date('d/m/Y H:i', strtotime($pub_date)) : '',
                'imagen'      => $imagen,
            ];

            $count++;
        }
    }

    // Ordenar por fecha descendente (más recientes primero)
    usort($noticias, function($a, $b) {
        return strtotime(str_replace('/', '-', $b['fecha'])) - strtotime(str_replace('/', '-', $a['fecha']));
    });

    $ultima_actualizacion = date('d/m/Y H:i');

    // Guardar en caché
    file_put_contents($cache_file, json_encode([
        'noticias'            => $noticias,
        'ultima_actualizacion' => $ultima_actualizacion,
    ], JSON_UNESCAPED_UNICODE));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Noticias</title>
    <link rel="icon" type="image/png" href="/images/favicon.png">
    <link rel="shortcut icon" href="/images/favicon.png" type="image/x-icon">
    <link rel="stylesheet" href="/css/inicio.css">
    <link rel="stylesheet" href="/css/noticias.css">
    <link rel="stylesheet" href="/css/cookie_tema.css">
</head>
<body class="<?php echo $clase_tema; ?>">

    <!-- NAVEGACIÓN -->
    <div class="navegacion">
        <nav>
            <a href="/inicio.php">Inicio</a>
            <a href="/pages/equipos.php">Equipos</a>
            <a href="/pages/plantilla.php">Plantilla</a>
            <a href="/pages/noticias.php" class="nav-active">Noticias</a>
            <a href="/logout.php">Cerrar Sesión</a>
        </nav>
    </div>

    <!-- CABECERA -->
    <div class="noticias-header">
        <h1>Noticias Futbol España</h1>
        <p class="noticias-subtitulo">LaLiga · Copa del Rey · Segunda División</p>
        <?php if ($ultima_actualizacion): ?>
            <p class="noticias-actualizacion">
                🕐 Última actualización: <strong><?php echo htmlspecialchars($ultima_actualizacion); ?></strong>
                <a href="?forzar=1" class="btn-refrescar">🔄 Forzar actualización</a>
            </p>
        <?php endif; ?>
    </div>

    <!-- FORZAR REFRESCO MANUAL -->
    <?php
    if (isset($_GET['forzar']) && $_GET['forzar'] == '1') {
        if (file_exists($cache_file)) {
            unlink($cache_file);
        }
        header('Location: /pages/noticias.php');
        exit();
    }
    ?>

    <!-- GRID DE NOTICIAS -->
    <div class="noticias-container">
        <?php if (empty($noticias)): ?>
            <div class="noticias-vacio">
                <p>⚠️ No se pudieron cargar las noticias en este momento.<br>
                Comprueba la conexión a internet del servidor.</p>
            </div>
        <?php else: ?>
            <div class="noticias-grid">
                <?php foreach ($noticias as $noticia): ?>
                    <article class="noticia-card">
                        <?php if (!empty($noticia['imagen'])): ?>
                            <div class="noticia-imagen">
                                <img src="<?php echo htmlspecialchars($noticia['imagen']); ?>"
                                    alt="<?php echo htmlspecialchars($noticia['titulo']); ?>"
                                    loading="lazy"
                                    onerror="this.parentElement.style.display='none'">
                            </div>
                        <?php endif; ?>
                        <div class="noticia-contenido">
                            <div class="noticia-meta">
                                <span class="noticia-fuente"><?php echo htmlspecialchars($noticia['fuente']); ?></span>
                                <?php if ($noticia['fecha']): ?>
                                    <span class="noticia-fecha">🕐 <?php echo htmlspecialchars($noticia['fecha']); ?></span>
                                <?php endif; ?>
                            </div>
                            <h2 class="noticia-titulo"><?php echo htmlspecialchars($noticia['titulo']); ?></h2>
                            <?php if ($noticia['descripcion']): ?>
                                <p class="noticia-desc"><?php echo htmlspecialchars($noticia['descripcion']); ?></p>
                            <?php endif; ?>
                            <a href="<?php echo htmlspecialchars($noticia['link']); ?>"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="noticia-link">
                                Leer noticia completa →
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
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
