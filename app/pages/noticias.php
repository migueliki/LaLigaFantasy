<?php
ob_start();
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

//  SISTEMA DE CACHÉ (se renueva cada 7 días)
$cache_dir  = __DIR__ . '/../cache/';
$cache_file = $cache_dir . 'noticias_cache.json';
$cache_ttl  = 7 * 24 * 3600; // 7 días en segundos

// Crear carpeta de caché si no existe
if (!is_dir($cache_dir)) {
    mkdir($cache_dir, 0755, true);
}

// Forzar actualización (robusto para entornos con proxy/cache)
$forzar_actualizacion = isset($_GET['forzar']) && $_GET['forzar'] === '1';
$mensaje_actualizacion = null;

if ($forzar_actualizacion) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

// Incluir DESPUÉS del redirect para evitar output prematuro
include_once '../cookie_tema.php';
require_once '../csrf.php';

function texto_len(string $texto): int {
    return function_exists('mb_strlen') ? mb_strlen($texto, 'UTF-8') : strlen($texto);
}

function texto_sub(string $texto, int $inicio, int $longitud): string {
    return function_exists('mb_substr') ? mb_substr($texto, $inicio, $longitud, 'UTF-8') : substr($texto, $inicio, $longitud);
}

function texto_lowercase(string $texto): string {
    return function_exists('mb_strtolower') ? mb_strtolower($texto, 'UTF-8') : strtolower($texto);
}

function descargar_feed_individual(string $url, float $deadline): ?string {
    if (microtime(true) >= $deadline) {
        return null;
    }

    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT        => 4,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; LaLigaFantasy/1.0)',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_ENCODING       => 'gzip, deflate',
        ]);
        $xml_raw = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($xml_raw && $http_code >= 200 && $http_code < 400) {
            return $xml_raw;
        }
    }

    if (ini_get('allow_url_fopen')) {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 4,
                'header' => "User-Agent: Mozilla/5.0 (compatible; LaLigaFantasy/1.0)\r\n",
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);

        $xml_raw = @file_get_contents($url, false, $context);
        if ($xml_raw !== false) {
            return $xml_raw;
        }
    }

    return null;
}

function descargar_feeds(array $feeds, float $deadline): array {
    $resultados = [];

    if (microtime(true) >= $deadline) {
        return $resultados;
    }

    if (function_exists('curl_multi_init') && function_exists('curl_init')) {
        $mh = curl_multi_init();
        $handles = [];

        foreach ($feeds as $feed) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $feed['url'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 2,
                CURLOPT_TIMEOUT        => 4,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 3,
                CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; LaLigaFantasy/1.0)',
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_ENCODING       => 'gzip, deflate',
            ]);
            curl_multi_add_handle($mh, $ch);
            $handles[$feed['url']] = $ch;
        }

        $running = null;
        do {
            curl_multi_exec($mh, $running);
            if (microtime(true) >= $deadline) {
                break;
            }
            if ($running > 0) {
                curl_multi_select($mh, 1.0);
            }
        } while ($running > 0);

        foreach ($handles as $url => $ch) {
            $contenido = curl_multi_getcontent($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($contenido && $http_code >= 200 && $http_code < 400) {
                $resultados[$url] = $contenido;
            }

            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }
        curl_multi_close($mh);
    }

    foreach ($feeds as $feed) {
        if (microtime(true) >= $deadline) {
            break;
        }
        if (!isset($resultados[$feed['url']])) {
            $fallback = descargar_feed_individual($feed['url'], $deadline);
            if ($fallback) {
                $resultados[$feed['url']] = $fallback;
            }
        }
    }

    return $resultados;
}

$noticias = [];
$ultima_actualizacion = null;

// Cargar caché existente para responder rápido (también en forzar, como respaldo)
if (file_exists($cache_file)) {
    $cached = json_decode(file_get_contents($cache_file), true);
    $noticias            = $cached['noticias']            ?? [];
    $ultima_actualizacion = $cached['ultima_actualizacion'] ?? null;
}

// Solo refrescar remoto si se fuerza manualmente o no hay caché útil
if ($forzar_actualizacion || empty($noticias)) {
 
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

    $deadline = microtime(true) + 8.0;
    $feeds_raw = descargar_feeds($feeds, $deadline);

    foreach ($feeds as $feed) {
        $xml_raw = $feeds_raw[$feed['url']] ?? null;
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
            if (texto_len($descripcion) > 180) {
                $descripcion = texto_sub($descripcion, 0, 177) . '...';
            }

            // Filtrar por palabras clave (título o descripción)
            $texto_lower = texto_lowercase($titulo . ' ' . $descripcion);
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

    if (!empty($noticias)) {
        $ultima_actualizacion = date('d/m/Y H:i');

        // Guardar en caché solo si hay contenido válido
        @file_put_contents($cache_file, json_encode([
            'noticias'            => $noticias,
            'ultima_actualizacion' => $ultima_actualizacion,
        ], JSON_UNESCAPED_UNICODE), LOCK_EX);

        if ($forzar_actualizacion) {
            $mensaje_actualizacion = '✅ Noticias actualizadas correctamente';
        }
    } else {
        // Si fallan todas las fuentes, intentar conservar la caché anterior
        if (file_exists($cache_file)) {
            $cached = json_decode(file_get_contents($cache_file), true);
            $noticias             = $cached['noticias'] ?? [];
            $ultima_actualizacion = $cached['ultima_actualizacion'] ?? null;
        }

        if ($forzar_actualizacion) {
            $mensaje_actualizacion = '⚠️ No se pudieron descargar noticias nuevas en este momento';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Noticias - LaLiga Fantasy</title>
    <meta name="description" content="Últimas noticias de LaLiga, Copa del Rey y Segunda División. Toda la actualidad del fútbol español en un solo lugar.">

    <!-- Open Graph -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://laligafantasy.duckdns.org/pages/noticias.php">
    <meta property="og:title" content="Noticias - LaLiga Fantasy">
    <meta property="og:description" content="Últimas noticias de LaLiga, Copa del Rey y Segunda División. Toda la actualidad del fútbol español en un solo lugar.">
    <meta property="og:image" content="https://laligafantasy.duckdns.org/images/laliga-logo.png">
    <meta property="og:image:type" content="image/png">
    <meta property="og:image:width" content="698">
    <meta property="og:image:height" content="441">
    <meta property="og:site_name" content="LaLiga Fantasy">
    <meta property="og:locale" content="es_ES">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Noticias - LaLiga Fantasy">
    <meta name="twitter:description" content="Últimas noticias de LaLiga, Copa del Rey y Segunda División. Toda la actualidad del fútbol español en un solo lugar.">
    <meta name="twitter:image" content="https://laligafantasy.duckdns.org/images/laliga-logo.png">

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
            <a href="/pages/calendario.php">Calendario</a>
            <a href="/pages/plantilla.php">Plantilla</a>
            <a href="/pages/noticias.php" class="nav-active">Noticias</a>
            <a href="/logout.php">Cerrar Sesión</a>
        </nav>
    </div>

    <!-- CABECERA -->
    <div class="noticias-header">
        <h1>Noticias Fútbol España</h1>
        <p class="noticias-subtitulo">LaLiga · Copa del Rey · Segunda División</p>
        <?php if ($mensaje_actualizacion): ?>
            <p class="noticias-actualizacion"><?php echo htmlspecialchars($mensaje_actualizacion); ?></p>
        <?php endif; ?>
        <?php if ($ultima_actualizacion): ?>
            <p class="noticias-actualizacion">
                🕐 Última actualización: <strong><?php echo htmlspecialchars($ultima_actualizacion); ?></strong>
                <a href="?forzar=1&amp;t=<?php echo time(); ?>" class="btn-refrescar">🔄 Forzar actualización</a>
            </p>
        <?php endif; ?>
    </div>

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
