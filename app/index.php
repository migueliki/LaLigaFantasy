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
    <form action="login.php" method="post">
        <input type="text" name="username" placeholder="nombre" required><br>
        <input type="password" name="password" placeholder="contraseña" required><br>
        <button type="submit">Iniciar Sesión</button>
    </form>
</div>


<h1>Registrar usuario </h1>

<div class="formulario">
    <form action="register.php" method="post">
        <input type="text" name="username" placeholder="nombre" required><br>
        <input type="email" name="email" placeholder="email" required><br>
        <input type="password" name="password" placeholder="contraseña" required><br>
        <button type="submit">Registrar</button>
    </form>
</div>


</body>
</html>