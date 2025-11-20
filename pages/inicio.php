<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$pages = $_GET ['pages'] ?? 'inicio';
$list_pages = ['inicio', 'lista_jugadores'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicio</title>

</head>
<body>
    

<h1>ESTAS EN INICIO</h1>

<a href="?pages=lista_jugadores">ir a lista de jugadores</a> |


<?php

if (in_array($pages, $list_pages)) {
    include "pages/{$pages}.php";
} else {
    include "pages/inicio.php";
}

?>


</body>
</html>