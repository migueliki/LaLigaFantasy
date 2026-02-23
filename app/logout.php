<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

$_SESSION = [];
session_unset();
session_destroy();
setcookie(session_name(), '', time()-3600, '/'); // eliminar cookie de sesión
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cerrando sesión...</title>
    
    <meta http-equiv="refresh" content="3;url=/index.php">

    <style>
        body {
            font-family: sans-serif;
            background-color: #0d1b2a; /* Mismo color oscuro que el login */
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .mensaje {
            text-align: center;
            padding: 20px;
            background-color: #1b263b;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.5);
        }
        a { color: #4cc9f0; text-decoration: none; }
    </style>
</head>
<body>

    <div class="mensaje">
        <h2>Cerrando sesión...</h2>
        <p>¡Esperamos verte pronto!</p>
        <p><small>Serás redirigido en unos segundos.</small></p>
        <a href="/app/index.php">Haz clic aquí si no te redirige</a>
    </div>

</body>
</html>