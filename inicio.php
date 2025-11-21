<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$public = $_GET['public'] ?? 'inicio';
$list_pages = ['lista_jugadores','documentacion'];
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
        <a href="inicio.php?public=lista_jugadores">Lista de Jugadores</a>
        <a href="inicio.php?public=documentacion">Documentacion</a>
    </nav>
</div>

<?php

if (in_array($public, $list_pages)) {
    
    header("Location: public/{$public}.php");
}
    else {
        http_response_code(404);
        echo "";
    }

?>



</body>
</html>