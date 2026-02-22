<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'csrf.php';

if (isset($_GET['error']) && $_GET['error'] == 'login_fallido') {
        echo '<p style="color: red; font-weight: bold;">Usuario o contraseña incorrectos.</p>';
    }

include 'cookie_tema.php'




// Obtener la ruta de la URL (ej. /registro, /inicio, etc.)
$request = $_SERVER['REQUEST_URI'];
// Eliminar parámetros de consulta (todo después de ?)
$request = strtok($request, '?');
// Eliminar la barra inicial
$request = ltrim($request, '/');

// Si la ruta está vacía, cargar inicio.php
if ($request === '') {
    $request = 'inicio';
}

// Mapear rutas amigables a archivos reales (inglés/español)
$routes = [
    'inicio'   => 'inicio.php',
    'registro' => 'register.php',
    'login'    => 'login.php',
    'logout'   => 'logout.php',
    // Añade más rutas según necesites
];

if (isset($routes[$request])) {
    // Incluir el archivo correspondiente
    include $routes[$request];
} else {
    // Si no existe, mostrar 404
    header("HTTP/1.0 404 Not Found");
    echo "Página no encontrada";
}
exit;





?>

<!DOCTYPE html>
<html lang="es">
<head>
    <!-- <meta charset="UTF-8"> -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicio De Sesión</title>
    <link rel="stylesheet" href="/css/index.css">
    <link rel="stylesheet" href="/css/cookie_tema.css">
    <link rel="shortcut icon" href="../images/favicon.png" type="image/x-icon">

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
