<?php

$include = include ('conexion.php');

if ($include) {
    // Conexión exitosa
    $consulta = "SELECT
    jugadores.id,
    jugadores.nombre,
    jugadores.edad,
    jugadores.nacionalidad,
    equipos.nombre AS nombre_equipo,  
    jugadores.dorsal
FROM jugadores
JOIN equipos ON jugadores.equipo_id = equipos.id;";
    $resultado = $pdo->query($consulta);
    if ($resultado) {
        while ($row = $resultado->fetch()) {
            $nombre = $row['nombre'];
            $dorsal = $row['dorsal'];
            $equipo = $row['nombre_equipo'];
        ?>
        <div>
            <h2>Nombre: <?php echo $nombre; ?></h2>
            <p>
                <b>Dorsal: <?php echo $dorsal; ?></b>
                <b>Equipo: <?php echo $equipo; ?></b>
            </p>
            <hr>
        </div>
        <?php
        }
    }

} else {
    // Error en la conexión
    echo "Error al conectar a la base de datos.";
}

?>