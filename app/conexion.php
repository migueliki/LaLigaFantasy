<?php
// Conexión a la base de datos con PDO y manejo de errores
$intentos = 3;
while ($intentos > 0) {
    try {
        $pdo = new PDO("mysql:host=fantasy.c2jk46soeoo2.us-east-1.rds.amazonaws.com;port=3306;dbname=fantasy", "admin", "fantasyASIR25");
        break;
    } catch (PDOException $e) {
        sleep(2); // espera 2 segundos
        $intentos--;
    }
}
if (!isset($pdo)) {
    die("No se pudo conectar a la base de datos.");
}
?> 
