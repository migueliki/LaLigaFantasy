<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);


?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicio De Sesión</title>
    <link rel="stylesheet" href="/css/index.css">
</head>
<body>

<h1>Registro de Jugadores</h1>

<div class="formulario">
    <form action="post">
        <input type="text" name="nombre" placeholder="nombre"><br>
        <input type="text" name="apellido" placeholder="apellidos"><br>
        <input type="text" name="equipo favorito" placeholder="equipo favorito"><br>
        <input type="submit" value="Iniciar Sesión">
    </form>
</div>




</body>
</html>