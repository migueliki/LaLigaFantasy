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

<h1>Iniciar Sesión</h1>

<div class="formulario">
    <form action="procesar_form.php" method="post">
        <input type="text" name="nombre" placeholder="nombre"><br>
        <input type="email" name="email" placeholder="email"><br>
        <input type="password" name="contraseña" placeholder="contraseña"><br>
        <input type="text" name="equipo_favorito" placeholder="equipo favorito"><br>
        <button type="submit">Enviar</button>
    </form>
</div>




</body>
</html>