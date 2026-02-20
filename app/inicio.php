<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if(isset($_SESSION['usuario_id'])) {
    // El usuario ya ha iniciado sesión
} else {
    header("Location: index.php");
    exit();
}

$pages = $_GET['pages'] ?? 'inicio';
$list_pages = ['jugadores', 'plantilla', 'cerrar_sesion'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/css/inicio.css">
    <link rel="stylesheet" href="/css/cookie_tema.css">
    <link rel="icon" type="image/png" href="/images/favicon.png">
    <link rel="shortcut icon" href="/images/favicon.png" type="image/x-icon">

    <?php include 'cookie_tema.php'; ?>
    <title>inicio</title>
</head>
<body>

<div class="navegacion">
    <nav>
        <a href="/app/inicio.php">🏠 Inicio</a>
        <a href="/app/pages/jugadores.php">Lista de Jugadores</a>
        <a href="/app/pages/plantilla.php">Plantilla</a>
        <a href="/app/pages/noticias.php">📰 Noticias</a>
        <a href="/app/logout.php">Cerrar Sesión</a>
    </nav>
</div>

<?php

if (in_array($pages, $list_pages)) {
    header("Location: /app/pages/{$pages}.php");
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
            <button type="submit" name="tema_pref" value="tema-claro" title="Modo Claro">⚪</button>
        </form>
    </div>

    </body>
</html>