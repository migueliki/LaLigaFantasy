<?php
include('conexion.php'); 

// 2. Recogemos el texto que el usuario escribió en el buscador
$busqueda = isset($_GET['nombre']) ? $_GET['nombre'] : '';

// 3. Creamos la consulta SQL
if ($busqueda != '') {
    // Si hay búsqueda, filtramos con LIKE
    $sql = "SELECT jugadores.nombre, jugadores.dorsal, equipos.nombre AS equipo
            FROM jugadores
            JOIN equipos ON jugadores.equipo_id = equipos.id
            WHERE jugadores.nombre LIKE '%$busqueda%'
            OR equipos.nombre LIKE '%$busqueda%'
            OR jugadores.dorsal LIKE '%$busqueda%'";
    $stmt = $pdo->query($sql);
} else {
    // Si no hay búsqueda, mostramos todos
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

// 5. Mostrar resultados
foreach ($stmt as $row) {
    echo "<h2>Nombre: " . $row['nombre'] . "</h2>";
    echo "<p><b>Dorsal: " . $row['dorsal'] . "</b> ";
    echo "<b>Equipo: " . $row['equipo'] . "</b></p>";
    echo "<hr>";
}

?>