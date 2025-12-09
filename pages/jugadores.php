<?php

session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: /app/index.php'); // o login
    exit();
}
// Control de timeout por inactividad
$timeout = 3600; // 1 hora
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
    session_unset(); session_destroy();
    header('Location: /app/index.php?timeout=1');
    exit();
}
$_SESSION['last_activity'] = time(); // actualizar actividad

include_once '../app/conexion.php';
require_once '../app/listar_jugadores.php';
include_once '../app/cookie_tema.php';
require_once '../app/csrf.php';

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista De Jugadores</title>
    <link rel="shortcut icon" href="../images/favicon.png" type="image/x-icon">
    <link rel="stylesheet" href="/css/jugadores.css">
    <link rel="stylesheet" href="/css/cookie_tema.css">
</head>

<body class="<?php echo $clase_tema; ?>">    

    <div class="widget-temas">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <button type="submit" name="tema_pref" value="" title="Modo Azul (Original)">🔵</button>
            <button type="submit" name="tema_pref" value="tema-claro" title="Modo Claro">⚪</button>
        </form>
    </div>

</body>
</html>