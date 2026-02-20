<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

require_once '../conexion.php';
require_once '../csrf.php';
include_once '../cookie_tema.php';

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plantilla</title>
    <link rel="icon" type="image/png" href="/images/favicon.png">
    <link rel="shortcut icon" href="/images/favicon.png" type="image/x-icon">
</head>
<body>
    <h1>Plantilla</h1>
</body>
</html>