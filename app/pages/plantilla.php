<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: /index.php'); // o login
    exit;
}
// Control de timeout por inactividad
$timeout = 3600; // 1 hora
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
    session_unset(); session_destroy();
    header('Location: /index.php?timeout=1');
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
    <title>Plantilla - LaLiga Fantasy</title>
    <meta name="description" content="Gestiona tu plantilla de LaLiga Fantasy. Selecciona jugadores, consulta estadísticas y monta tu equipo ideal.">

    <!-- Open Graph -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://laligafantasy.duckdns.org/pages/plantilla.php">
    <meta property="og:title" content="Plantilla - LaLiga Fantasy">
    <meta property="og:description" content="Gestiona tu plantilla de LaLiga Fantasy. Selecciona jugadores, consulta estadísticas y monta tu equipo ideal.">
    <meta property="og:image" content="https://laligafantasy.duckdns.org/images/laliga-logo.png">
    <meta property="og:image:type" content="image/png">
    <meta property="og:image:width" content="698">
    <meta property="og:image:height" content="441">
    <meta property="og:site_name" content="LaLiga Fantasy">
    <meta property="og:locale" content="es_ES">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Plantilla - LaLiga Fantasy">
    <meta name="twitter:description" content="Gestiona tu plantilla de LaLiga Fantasy. Selecciona jugadores, consulta estadísticas y monta tu equipo ideal.">
    <meta name="twitter:image" content="https://laligafantasy.duckdns.org/images/laliga-logo.png">

    <link rel="icon" type="image/png" href="/images/favicon.png">
    <link rel="shortcut icon" href="/images/favicon.png" type="image/x-icon">
</head>
<body>
    <h1>Plantilla</h1>
</body>
</html>