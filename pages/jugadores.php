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
</head>
<body>
    

<?php include '../app/listar_jugadores.php';
?>

</body>
</html>