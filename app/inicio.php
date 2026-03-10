<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'config.php';

if(isset($_SESSION['usuario_id'])) {
    // El usuario ya ha iniciado sesión
} else {
    header("Location: index.php");
    exit();
}

$pages = $_GET['pages'] ?? 'inicio';
$list_pages = ['equipos', 'plantilla', 'noticias', 'cerrar_sesion'];
include_once 'cookie_tema.php';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LaLiga Fantasy - Inicio</title>
    <meta name="description" content="Panel principal de LaLiga Fantasy. Gestiona tu equipo, consulta estadísticas y sigue las noticias del fútbol español.">

    <!-- Open Graph -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://laligafantasy.duckdns.org/inicio.php">
    <meta property="og:title" content="LaLiga Fantasy - Inicio">
    <meta property="og:description" content="Panel principal de LaLiga Fantasy. Gestiona tu equipo, consulta estadísticas y sigue las noticias del fútbol español.">
    <meta property="og:image" content="https://laligafantasy.duckdns.org/images/laliga-logo.png">
    <meta property="og:image:type" content="image/png">
    <meta property="og:image:width" content="698">
    <meta property="og:image:height" content="441">
    <meta property="og:site_name" content="LaLiga Fantasy">
    <meta property="og:locale" content="es_ES">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="LaLiga Fantasy - Inicio">
    <meta name="twitter:description" content="Panel principal de LaLiga Fantasy. Gestiona tu equipo, consulta estadísticas y sigue las noticias del fútbol español.">
    <meta name="twitter:image" content="https://laligafantasy.duckdns.org/images/laliga-logo.png">

    <link rel="stylesheet" href="css/inicio.css">
    <link rel="stylesheet" href="css/cookie_tema.css">
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>/images/<?= $clase_tema === 'tema-laliga' ? 'LL_RGB_h_color.png' : 'favicon.png' ?>">
    <link rel="shortcut icon" href="<?= BASE_URL ?>/images/<?= $clase_tema === 'tema-laliga' ? 'LL_RGB_h_color.png' : 'favicon.png' ?>" type="image/x-icon">
</head>
<body>

<div class="navegacion">
    <nav>
        <a href="<?= BASE_URL ?>/inicio.php">Inicio</a>
        <a href="<?= BASE_URL ?>/pages/equipos.php">Equipos</a>
        <a href="<?= BASE_URL ?>/pages/calendario.php">Calendario</a>
        <a href="<?= BASE_URL ?>/pages/plantilla.php">Plantilla</a>
        <a href="<?= BASE_URL ?>/pages/mercado.php">Mercado</a>
        <a href="<?= BASE_URL ?>/pages/noticias.php">Noticias</a>
        <a href="<?= BASE_URL ?>/logout.php">Cerrar Sesión</a>
    </nav>
</div>

<?php

if (in_array($pages, $list_pages)) {
    header("Location: " . BASE_URL . "/pages/{$pages}.php");
}
    else {
        http_response_code(404);
        echo "";
    }
?>

</body>
<body class="<?php echo $clase_tema; ?>">    

    <div class="widget-temas">
        <form method="POST">
            <button type="submit" name="tema_pref" value="" title="Modo Azul (Original)">🔵</button>
            <button type="submit" name="tema_pref" value="tema-laliga" title="Modo LaLiga">🔴</button>
        </form>
    </div>

    </body>
</html>