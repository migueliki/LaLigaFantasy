<?php
$intentos = 5;
while ($intentos > 0) {
    try {
        $pdo = new PDO("mysql:host=db;port=3306;dbname=fantasy", "root", "root");
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