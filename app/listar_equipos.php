<?php

$include = include ('conexion.php');



if ($include) {
    // Conexión exitosa
    $consulta = "SELECT nombre FROM fantasy.equipos;";
    $resultado = $pdo->query($consulta);
    if ($resultado) {
        while ($row = $resultado->fetch()) {
            $equipo = $row['nombre'];
        ?>
        <div>
            <h2>Equipo: <?php echo $equipo; ?></h2>
            <button href="">Ver jugadores</button>
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