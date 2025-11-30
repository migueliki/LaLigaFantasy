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
$list_pages = ['jugadores','documentacion', 'plantilla'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/css/inicio.css">
    <title>inicio</title>
</head>
<body>


<div class="navegacion">
    <nav>
        <a href="inicio.php?pages=jugadores">Lista de Jugadores</a>
        <a href="inicio.php?pages=documentacion">Documentación</a>
        <a href="inicio.php?pages=plantilla">Plantilla</a>
    </nav>
</div>

<?php

if (in_array($pages, $list_pages)) {
    
    header("Location: ../pages/{$pages}.php");
}
    else {
        http_response_code(404);
        echo "";
    }

?>


</body>
</html>