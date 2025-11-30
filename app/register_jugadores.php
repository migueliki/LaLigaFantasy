<?php


if (empty($_POST["btnregistrar"])) {

    if (!empty($_POST["nombre"]) && !empty($_POST["equipo"]) && !empty($_POST["posicion"]) && !empty($_POST["nacionalidad"]) && !empty($_POST["dorsal"])) {

        $nombre = $_POST["nombre"];
        $equipo = $_POST["equipo"];
        $posicion = $_POST["posicion"];
        $nacionalidad = $_POST["nacionalidad"];
        $dorsal = $_POST["dorsal"];

        $sql = $conexion->query("INSERT INTO plantilla (nombre, equipo, posicion, nacionalidad, dorsal) VALUES ('$nombre', '$equipo', '$posicion', '$nacionalidad', '$dorsal')");

        if ($sql == true) {
            header("location: plantilla.php");
        } else {
            echo "Error al registrar el jugador.";
        }

    } else {
        echo "Por favor, complete todos los campos.";
    }
}

?>