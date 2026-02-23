<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'csrf.php';

if (isset($_GET['error']) && $_GET['error'] == 'login_fallido') {
        echo '<p style="color: red; font-weight: bold;">Usuario o contraseña incorrectos.</p>';
    }

include 'cookie_tema.php'

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LaLiga Fantasy - Inicio De Sesión</title>
    <meta name="description" content="Gestiona tu equipo de fantasía de LaLiga. Consulta equipos, jugadores, plantillas y noticias del fútbol español.">

    <!-- Open Graph (WhatsApp, Facebook, Telegram, etc.) -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://laligafantasy.duckdns.org/">
    <meta property="og:title" content="LaLiga Fantasy">
    <meta property="og:description" content="Gestiona tu equipo de fantasía de LaLiga. Consulta equipos, jugadores, plantillas y noticias del fútbol español.">
    <meta property="og:image" content="https://laligafantasy.duckdns.org/images/og-image.png">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:site_name" content="LaLiga Fantasy">
    <meta property="og:locale" content="es_ES">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="LaLiga Fantasy">
    <meta name="twitter:description" content="Gestiona tu equipo de fantasía de LaLiga. Consulta equipos, jugadores, plantillas y noticias del fútbol español.">
    <meta name="twitter:image" content="https://laligafantasy.duckdns.org/images/og-image.png">

    <link rel="stylesheet" href="/css/index.css">
    <link rel="stylesheet" href="/css/cookie_tema.css">
    <link rel="shortcut icon" href="/images/favicon.png" type="image/x-icon">
</head>
<body>

<h1>Iniciar Sesión</h1>

<div class="formulario">
    <form action="login.php" method="post">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        <input type="text" name="username" placeholder="nombre" maxlength="20" required><br>
        <input type="password" name="password" placeholder="contraseña" maxlength="20" required><br>
        <button type="submit">Iniciar Sesión</button>
    </form>
</div>

<h1>Registrar usuario</h1>

<div class="formulario">
    <form action="register.php" method="post">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        <input type="text" name="username" placeholder="nombre" maxlength="20" required><br>
        <input type="email" name="email" placeholder="email" maxlength="25" required><br>
        <input type="password" name="password" placeholder="contraseña" maxlength="20" required><br>
        <button type="submit">Registrar</button>
    </form>
</div>

</body>

<body class="<?php echo $clase_tema; ?>">    

    <div class="widget-temas">
        <form method="POST">
            <button type="submit" name="tema_pref" value="" title="Modo Azul (Original)">🔵</button>
            <button type="submit" name="tema_pref" value="tema-claro" title="Modo Claro">⚪</button>
        </form>
    </div>

    </body>
</html>
