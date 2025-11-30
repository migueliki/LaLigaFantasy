<?php
include('conexion.php');

$busqueda = isset($_GET['nombre']) ? $_GET['nombre'] : '';
$por_pagina = 30;
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$inicio = ($pagina - 1) * $por_pagina;

if ($busqueda != '') {
    $sql_total = "SELECT COUNT(*) FROM jugadores
        JOIN equipos ON jugadores.equipo_id = equipos.id
        WHERE jugadores.nombre LIKE '%$busqueda%'
        OR equipos.nombre LIKE '%$busqueda%'
        OR jugadores.dorsal LIKE '%$busqueda%'";
} else {
    $sql_total = "SELECT COUNT(*) FROM jugadores";
}
$total_resultados = $pdo->query($sql_total)->fetchColumn();
$total_paginas = ceil($total_resultados / $por_pagina);

// Consulta principal con LIMIT y OFFSET
if ($busqueda != '') {
    $sql = "SELECT jugadores.nombre, jugadores.dorsal, equipos.nombre AS equipo
            FROM jugadores
            JOIN equipos ON jugadores.equipo_id = equipos.id
            WHERE jugadores.nombre LIKE '%$busqueda%'
            OR equipos.nombre LIKE '%$busqueda%'
            OR jugadores.dorsal LIKE '%$busqueda%'
            LIMIT $por_pagina OFFSET $inicio";
} else {
    $sql = "SELECT jugadores.nombre, jugadores.dorsal, equipos.nombre AS equipo
            FROM jugadores
            JOIN equipos ON jugadores.equipo_id = equipos.id
            LIMIT $por_pagina OFFSET $inicio";
}
$stmt = $pdo->query($sql);
?>

<form method="GET" action="">
    <input type="text" name="nombre" placeholder="Buscar jugador..." value="<?php echo htmlspecialchars($busqueda); ?>">
    <button type="submit">Buscar</button>
</form>
<hr>

<?php
foreach ($stmt as $row) {
    echo "<h2>Nombre: " . htmlspecialchars($row['nombre']) . "</h2>";
    echo "<p><b>Dorsal: " . htmlspecialchars($row['dorsal']) . "</b> ";
    echo "<b>Equipo: " . htmlspecialchars($row['equipo']) . "</b></p>";
    echo "<hr>";
}

for ($i = 1; $i <= $total_paginas; $i++) {
    $link = "?pagina=$i";
    if ($busqueda != '') {
        $link .= "&nombre=" . urlencode($busqueda);
    }
    echo "<a href='$link'>$i</a>";
}
?>