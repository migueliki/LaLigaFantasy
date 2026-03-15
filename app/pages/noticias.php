<?php
ob_start();
session_start();
require_once '../config.php';
@set_time_limit(60);
@ini_set('max_execution_time', '60');
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . BASE_URL . '/index.php');
    exit();
}
// Control de timeout por inactividad
$timeout = 3600;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
    session_unset(); session_destroy();
    header('Location: ' . BASE_URL . '/index.php?timeout=1');
    exit();
}
$_SESSION['last_activity'] = time();

//  SISTEMA DE CACHÉ (se renueva cada 7 días)
$cache_ttl  = 7 * 24 * 3600; // 7 días en segundos
$cache_escribible = false;

function resolver_cache_noticias(): array {
    $dirs_candidatos = [];

    $env_dir = getenv('NEWS_CACHE_DIR');
    if (is_string($env_dir) && trim($env_dir) !== '') {
        $dirs_candidatos[] = rtrim($env_dir, '/\\') . DIRECTORY_SEPARATOR;
    }

    $dirs_candidatos[] = __DIR__ . '/../cache/';
    $dirs_candidatos[] = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'laligafantasy-cache' . DIRECTORY_SEPARATOR;

    foreach ($dirs_candidatos as $dir) {
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            continue;
        }

        if (is_writable($dir)) {
            return [
                'dir' => $dir,
                'file' => $dir . 'noticias_cache.json',
                'writable' => true,
            ];
        }
    }

    $fallback_dir = __DIR__ . '/../cache/';
    return [
        'dir' => $fallback_dir,
        'file' => $fallback_dir . 'noticias_cache.json',
        'writable' => false,
    ];
}

$cache_info = resolver_cache_noticias();
$cache_dir = $cache_info['dir'];
$cache_file = $cache_info['file'];
$cache_escribible = $cache_info['writable'];

// Forzar actualización (robusto para entornos con proxy/cache)
$forzar_actualizacion = isset($_GET['forzar']) && $_GET['forzar'] === '1';
$mensaje_actualizacion = null;
$es_admin_debug = false;
$host_actual = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
$host_base = preg_replace('/:\\d+$/', '', $host_actual);
$es_localhost = in_array($host_base, ['localhost', '127.0.0.1', '::1'], true);

// En producción, apuntar al caché del proyecto y no permitir forzar desde URL
if (!$es_localhost) {
    $cache_file = __DIR__ . '/../cache/noticias_cache.json';
    $cache_dir  = __DIR__ . '/../cache/';
    // Re-evaluar si el directorio es escribible en el servidor de producción
    if (!is_dir($cache_dir)) { @mkdir($cache_dir, 0755, true); }
    $cache_escribible = is_writable($cache_dir);
    $forzar_actualizacion = false; // nunca permitir forzar desde URL en producción
}

if (isset($_SESSION['usuario']) && texto_lowercase((string)$_SESSION['usuario']) === 'admin') {
    $es_admin_debug = true;
}
if (isset($_SESSION['usuario_id']) && (int)$_SESSION['usuario_id'] === 1) {
    $es_admin_debug = true;
}

$modo_debug = $es_admin_debug && $es_localhost && isset($_GET['debug']) && $_GET['debug'] === '1';

// Forzar actualización permitido para cualquier usuario en localhost
if (!$es_localhost) {
    $forzar_actualizacion = false;
}

if ($forzar_actualizacion && $es_localhost) {
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

function diagnosticar_feed(string $url, float $deadline): array {
    $diag = [
        'descargado' => false,
        'http' => null,
        'bytes' => 0,
        'error' => null,
    ];

    if (microtime(true) >= $deadline) {
        $diag['error'] = 'timeout global';
        return $diag;
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

        $contenido = curl_exec($ch);
        $diag['http'] = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $diag['error'] = curl_error($ch) ?: null;
        curl_close($ch);

        if ($contenido !== false && $diag['http'] >= 200 && $diag['http'] < 400) {
            $diag['descargado'] = true;
            $diag['bytes'] = strlen((string)$contenido);
            $diag['error'] = null;
            return $diag;
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

        $contenido = @file_get_contents($url, false, $context);
        if ($contenido !== false) {
            $diag['descargado'] = true;
            $diag['bytes'] = strlen((string)$contenido);
            $diag['error'] = null;
        } elseif ($diag['error'] === null) {
            $diag['error'] = 'file_get_contents falló';
        }
    }

    return $diag;
}

$noticias = [];
$ultima_actualizacion = null;
$cache_expirada = true;
$mensaje_cache = null;
$diagnostico_feeds = [];
$noticias_keys = [];

// Cargar caché existente para responder rápido (también en forzar, como respaldo)
if (file_exists($cache_file)) {
    $cached = json_decode(file_get_contents($cache_file), true);
    $noticias            = array_slice($cached['noticias'] ?? [], 0, 12);
    $ultima_actualizacion = $cached['ultima_actualizacion'] ?? null;

    $ts_cache = (int)($cached['ultima_actualizacion_ts'] ?? 0);
    if ($ts_cache <= 0 && !empty($ultima_actualizacion)) {
        $dt_cache = DateTime::createFromFormat('d/m/Y H:i', $ultima_actualizacion);
        $ts_cache = $dt_cache ? $dt_cache->getTimestamp() : 0;
    }

    if ($ts_cache > 0) {
        $cache_expirada = (time() - $ts_cache) >= $cache_ttl;
    }
}

// Refrescar desde RSS si el caché está vacío, expirado, o se fuerza (solo localhost)
if ($forzar_actualizacion || empty($noticias) || $cache_expirada) {
try {

    // Limpiar caché en memoria para reconstruir desde cero
    $noticias = [];
    $noticias_keys = [];

    //  FUENTES RSS de fútbol español
    $feeds = [
        // ─ LaLiga Primera División ─
        [
            'nombre' => 'Marca',
            'url'    => 'https://www.marca.com/rss/futbol/primera-division.xml',
            'logo'   => '📰',
            'skip_filter' => false,
        ],
        [
            'nombre' => 'AS',
            'url'    => 'https://as.com/rss/tags/laliga.xml',
            'logo'   => '📰',
            'skip_filter' => false,
        ],
        [
            'nombre' => 'Mundo Deportivo',
            'url'    => 'https://www.mundodeportivo.com/rss/futbol/laliga',
            'logo'   => '📰',
            'skip_filter' => false,
        ],
        [
            'nombre' => 'Sport',
            'url'    => 'https://www.sport.es/rss/futbol/primera-division.xml',
            'logo'   => '📰',
            'skip_filter' => false,
        ],
        // ─ Copa del Rey ─
        [
            'nombre' => 'Marca Copa',
            'url'    => 'https://www.marca.com/rss/futbol/copa-del-rey.xml',
            'logo'   => '🏆',
            'skip_filter' => true,
        ],
        [
            'nombre' => 'AS Copa',
            'url'    => 'https://as.com/rss/tags/copa_del_rey.xml',
            'logo'   => '🏆',
            'skip_filter' => true,
        ],
        // ─ Segunda División ─
        [
            'nombre' => 'Marca 2ª',
            'url'    => 'https://www.marca.com/rss/futbol/segunda-division.xml',
            'logo'   => '📊',
            'skip_filter' => true,
        ],
        [
            'nombre' => 'AS 2ª',
            'url'    => 'https://as.com/rss/tags/segunda_division.xml',
            'logo'   => '📊',
            'skip_filter' => true,
        ],
    ];

    // Palabras clave para filtrar noticias de fútbol español
    $keywords = [
        'laliga', 'la liga', 'primera división', 'primera division',
        'segunda división', 'segunda division', 'segunda b', '1ª rfef',
        'copa del rey', 'supercopa', 'rfef', 'eurocopa', 'champions',
        'real madrid', 'barcelona', 'atlético', 'atletico', 'sevilla',
        'valencia', 'betis', 'villarreal', 'athletic', 'osasuna',
        'celta', 'getafe', 'rayo vallecano', 'espanyol', 'girona',
        'mallorca', 'las palmas', 'legánés', 'leganes', 'alaves', 'alavés',
        'valladolid', 'real sociedad', 'almeria', 'almería',
        'sporting', 'oviedo', 'zaragoza', 'elche', 'levante', 'racing',
        'tenerife', 'huesca', 'burgos', 'albacete', 'mirandes', 'miranés',
        'liga española', 'selección española', 'seleccion española',
        'fútbol español', 'futbol español', 'laliga hypermotion',
    ];

    libxml_use_internal_errors(true);

    $deadline = microtime(true) + 12.0;
    $feeds_raw = descargar_feeds($feeds, $deadline);

    if ($modo_debug) {
        foreach ($feeds as $feed) {
            $diagnostico_feeds[$feed['url']] = [
                'fuente' => $feed['nombre'],
                'url' => $feed['url'],
                'descargado' => isset($feeds_raw[$feed['url']]),
                'xml_valido' => false,
                'items_totales' => 0,
                'items_relevantes' => 0,
                'http' => null,
                'bytes' => isset($feeds_raw[$feed['url']]) ? strlen((string)$feeds_raw[$feed['url']]) : 0,
                'error' => null,
            ];

            if (!isset($feeds_raw[$feed['url']])) {
                $extra = diagnosticar_feed($feed['url'], $deadline);
                $diagnostico_feeds[$feed['url']]['http'] = $extra['http'];
                $diagnostico_feeds[$feed['url']]['bytes'] = $extra['bytes'];
                $diagnostico_feeds[$feed['url']]['error'] = $extra['error'];
            }
        }
    }

    foreach ($feeds as $feed) {
        $xml_raw = $feeds_raw[$feed['url']] ?? null;
        if (!$xml_raw) continue;

        $xml = @simplexml_load_string($xml_raw);
        if (!$xml) {
            if ($modo_debug && isset($diagnostico_feeds[$feed['url']])) {
                $diagnostico_feeds[$feed['url']]['xml_valido'] = false;
                $diagnostico_feeds[$feed['url']]['error'] = $diagnostico_feeds[$feed['url']]['error'] ?? 'XML inválido';
            }
            continue;
        }

        if ($modo_debug && isset($diagnostico_feeds[$feed['url']])) {
            $diagnostico_feeds[$feed['url']]['xml_valido'] = true;
        }

        $items = $xml->channel->item ?? [];

        if ($modo_debug && is_iterable($items)) {
            $items = iterator_to_array($items);
            $diagnostico_feeds[$feed['url']]['items_totales'] = count($items);
        }

        $count = 0;
        foreach ($items as $item) {
            if ($count >= 4) break; // máximo 4 noticias por fuente

            $titulo      = html_entity_decode((string)$item->title,       ENT_QUOTES, 'UTF-8');
            $descripcion = html_entity_decode((string)$item->description, ENT_QUOTES, 'UTF-8');
            $link        = trim((string)$item->link);
            $pub_date    = (string)($item->pubDate ?? '');

            $titulo = trim(preg_replace('/\s+/u', ' ', $titulo));
            $link_normalizado = strtolower(trim($link));

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

            if (!$es_relevante && !($feed['skip_filter'] ?? false)) continue;

            // Evitar duplicados entre fuentes o dentro de una misma fuente
            $clave_noticia = $link_normalizado !== ''
                ? 'link:' . $link_normalizado
                : 'titulo:' . texto_lowercase($titulo);

            if (isset($noticias_keys[$clave_noticia])) {
                continue;
            }
            $noticias_keys[$clave_noticia] = true;

            if ($modo_debug && isset($diagnostico_feeds[$feed['url']])) {
                $diagnostico_feeds[$feed['url']]['items_relevantes']++;
            }

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

    // Limitar a 12 noticias en total
    $noticias = array_slice($noticias, 0, 12);

    if (!empty($noticias)) {
        $ts_actualizacion = time();
        $ultima_actualizacion = date('d/m/Y H:i', $ts_actualizacion);

        // Guardar en caché solo si hay contenido válido y la ruta es escribible
        if ($cache_escribible) {
            $guardado_ok = file_put_contents($cache_file, json_encode([
                'noticias'              => $noticias,
                'ultima_actualizacion'  => $ultima_actualizacion,
                'ultima_actualizacion_ts' => $ts_actualizacion,
            ], JSON_UNESCAPED_UNICODE), LOCK_EX);

            if ($guardado_ok === false) {
                $mensaje_cache = '⚠️ El servidor no pudo guardar la caché de noticias';
            }
        } else {
            $mensaje_cache = '⚠️ Caché no escribible en servidor; usando actualización en tiempo real';
        }

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
} catch (\Throwable $e) {
    // Si falla algo inesperado, conservar caché anterior y seguir mostrando la página
    if (file_exists($cache_file)) {
        $cached = json_decode(file_get_contents($cache_file), true);
        $noticias             = array_slice($cached['noticias'] ?? [], 0, 12);
        $ultima_actualizacion = $cached['ultima_actualizacion'] ?? null;
    }
    if ($forzar_actualizacion) {
        $mensaje_actualizacion = '⚠️ Error al obtener noticias nuevas. Mostrando caché anterior.';
    }
}
} // fin if (empty/expirada/forzada)

if ($modo_debug && !$forzar_actualizacion && !empty($noticias) && empty($diagnostico_feeds)) {
    $diagnostico_feeds['_info'] = [
        'fuente' => 'Sistema',
        'url' => 'cache',
        'descargado' => true,
        'xml_valido' => true,
        'items_totales' => count($noticias),
        'items_relevantes' => count($noticias),
        'http' => null,
        'bytes' => 0,
        'error' => 'No se refrescó remoto: caché vigente',
    ];
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

    <link rel="icon" type="image/png" href="<?= theme_logo_url($clase_tema) ?>">
    <link rel="shortcut icon" href="<?= theme_logo_url($clase_tema) ?>" type="image/x-icon">
    <link rel="stylesheet" href="<?= BASE_URL ?>/css/inicio.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/css/noticias.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/css/cookie_tema.css?v=20260315-1">
</head>
<body class="<?php echo $clase_tema; ?>">

    <!-- NAVEGACIÓN -->
    <div class="navegacion">
        <nav>
            <a href="<?= BASE_URL ?>/inicio.php">Inicio</a>
            <a href="<?= BASE_URL ?>/pages/equipos.php">Equipos</a>
            <a href="<?= BASE_URL ?>/pages/calendario.php">Calendario</a>
            <a href="<?= BASE_URL ?>/pages/plantilla.php">Plantilla</a>
            <a href="<?= BASE_URL ?>/pages/mercado.php">Mercado</a>
            <a href="<?= BASE_URL ?>/pages/noticias.php" class="nav-active">Noticias</a>
            <a href="<?= BASE_URL ?>/logout.php">Cerrar Sesión</a>
        </nav>
    </div>

    <!-- CABECERA -->
    <div class="noticias-header">
        <h1>
            <img src="<?= theme_logo_url($clase_tema) ?>" onerror="this.onerror=null;this.src='<?= asset_url('images/favicon.png') ?>';" alt="LaLiga" class="noticias-logo-inline" loading="lazy">
            Noticias Fútbol España
        </h1>
        <p class="noticias-subtitulo">LaLiga · Copa del Rey · Segunda División</p>
        <?php if ($mensaje_actualizacion): ?>
            <p class="noticias-actualizacion"><?php echo htmlspecialchars($mensaje_actualizacion); ?></p>
        <?php endif; ?>
        <?php if ($ultima_actualizacion): ?>
            <p class="noticias-actualizacion">
                🕐 Última actualización: <strong><?php echo htmlspecialchars($ultima_actualizacion); ?></strong>
            </p>
        <?php endif; ?>
        <?php if ($es_localhost): ?>
            <p class="noticias-actualizacion">
                <a href="?forzar=1&amp;t=<?php echo time(); ?>" class="btn-refrescar">🔄 Forzar actualización</a>
            </p>
        <?php endif; ?>
        <?php if ($es_admin_debug && $es_localhost): ?>
            <p class="noticias-actualizacion">
                <a href="?forzar=<?php echo $forzar_actualizacion ? '1' : '0'; ?>&amp;debug=1&amp;t=<?php echo time(); ?>" class="btn-refrescar">🛠 Ver diagnóstico admin</a>
            </p>
        <?php endif; ?>
        <?php if ($mensaje_cache): ?>
            <p class="noticias-actualizacion"><?php echo htmlspecialchars($mensaje_cache); ?></p>
        <?php endif; ?>
    </div>

    <?php if ($modo_debug): ?>
        <div class="noticias-container" style="margin-top:10px;">
            <div class="noticia-card" style="padding:16px;">
                <h2 class="noticia-titulo" style="margin-top:0;">Diagnóstico RSS (solo admin)</h2>
                <?php foreach ($diagnostico_feeds as $diag): ?>
                    <p class="noticias-actualizacion" style="margin:8px 0;">
                        <strong><?php echo htmlspecialchars($diag['fuente']); ?></strong> ·
                        descargado: <?php echo $diag['descargado'] ? 'sí' : 'no'; ?> ·
                        xml: <?php echo $diag['xml_valido'] ? 'ok' : 'fallo'; ?> ·
                        items: <?php echo (int)$diag['items_totales']; ?> ·
                        relevantes: <?php echo (int)$diag['items_relevantes']; ?>
                        <?php if (!empty($diag['http'])): ?> · HTTP: <?php echo (int)$diag['http']; ?><?php endif; ?>
                        <?php if (!empty($diag['error'])): ?> · error: <?php echo htmlspecialchars($diag['error']); ?><?php endif; ?>
                    </p>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

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
        <button type="button" onclick="__llSetTema('tema-laliga')" title="Modo LaLiga">🔴</button>
        <button type="button" onclick="__llSetTema('tema-original')" title="Modo Azul (Original)">🔵</button>
    </div>
    <script>
    function __llSetTema(t){var e=new Date();e.setTime(e.getTime()+30*24*60*60*1000);var sec=location.protocol==='https:'?';Secure':'';document.cookie='preferencia_tema='+t+';expires='+e.toUTCString()+';path=/;SameSite=Lax'+sec;location.reload();}
    </script>

</body>
</html>
