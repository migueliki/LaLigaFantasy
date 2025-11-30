<?php
include('conexion.php'); 

$busqueda = isset($_GET['nombre']) ? $_GET['nombre'] : '';

if ($busqueda != '') {
    $sql = "SELECT jugadores.nombre, jugadores.dorsal, equipos.nombre AS equipo
            FROM jugadores
            JOIN equipos ON jugadores.equipo_id = equipos.id
            WHERE jugadores.nombre LIKE '%$busqueda%'
            OR equipos.nombre LIKE '%$busqueda%'
            OR jugadores.dorsal LIKE '%$busqueda%'";
    $stmt = $pdo->query($sql);
} else {
    $sql = "SELECT jugadores.nombre, jugadores.dorsal, equipos.nombre AS equipo
            FROM jugadores
            JOIN equipos ON jugadores.equipo_id = equipos.id";
    $stmt = $pdo->query($sql);
}
?>

<form method="GET" action="">
    <input type="text" name="nombre" placeholder="Buscar jugador..." value="<?php echo $busqueda; ?>">
    <button type="submit">Buscar</button>
</form>
<hr>

<?php

foreach ($stmt as $row) {
    echo "<h2>Nombre: " . $row['nombre'] . "</h2>";
    echo "<p><b>Dorsal: " . $row['dorsal'] . "</b> ";
    echo "<b>Equipo: " . $row['equipo'] . "</b></p>";
    echo "<hr>";
}

?>