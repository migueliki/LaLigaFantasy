<?php
$intentos = 3;
while ($intentos > 0) {
    try {
        $pdo = new PDO("mysql:host=localhost;port=3306;dbname=fantasy", "root", "");
        break;
    } catch (PDOException $e) {
        echo "Intento fallido: " . $e->getMessage() . "<br>";
        sleep(2);
        $intentos--;
    }
}
if (!isset($pdo)) {
    die(" No se pudo conectar a la base de datos.");
}
?>