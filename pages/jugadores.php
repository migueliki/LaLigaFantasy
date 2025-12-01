<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: /app/index.php'); // o login
    exit;
}
// Control de timeout por inactividad
$timeout = 3600; // 1 hora
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
    session_unset(); session_destroy();
    header('Location: /app/index.php?timeout=1');
    exit;
}
$_SESSION['last_activity'] = time(); // actualizar actividad



?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista De Jugadores</title>
    <link rel="stylesheet" href="/css/jugadores.css">
    <link rel="stylesheet" href="/css/cookie_tema.css">
    
</head>
<body>
    

<?php include '../app/listar_jugadores.php';
include '../app/cookie_tema.php';
?>

<body class="<?php echo $clase_tema; ?>">    

    <div class="widget-temas">
        <form method="POST">
            <button type="submit" name="tema_pref" value="" title="Modo Azul (Original)">🔵</button>
            <button type="submit" name="tema_pref" value="tema-claro" title="Modo Claro">⚪</button>
        </form>
    </div>

    </body>

</body>

</html>