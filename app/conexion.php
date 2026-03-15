<?php
// Conexión a la base de datos con PDO y manejo de errores
$hostActual = strtolower((string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
$hostBase = preg_replace('/:\\d+$/', '', $hostActual);
$esLocalhost = in_array($hostBase, ['localhost', '127.0.0.1', '::1'], true);

$dbHost = $esLocalhost
    ? 'localhost'
    : 'fantasy.c2jk46soeoo2.us-east-1.rds.amazonaws.com';

$intentos = 3;
while ($intentos > 0) {
    try {
        $pdo = new PDO("mysql:host={$dbHost};port=3306;dbname=fantasy", "admin", "fantasyASIR25");
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
