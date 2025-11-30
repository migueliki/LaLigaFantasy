<?php

// INSERTAR JUGADOR
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = $_POST['nombre'] ?? '';
    $equipo = $_POST['equipo'] ?? '';
    $posicion = $_POST['posicion'] ?? '';
    $nacionalidad = $_POST['nacionalidad'] ?? '';
    $dorsal = $_POST['dorsal'] ?? '';

    $sql = "INSERT INTO plantilla (nombre, equipo, posicion, nacionalidad, dorsal) 
            VALUES (:nombre, :equipo, :posicion, :nacionalidad, :dorsal)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':nombre' => $nombre,
        ':equipo' => $equipo,
        ':posicion' => $posicion,
        ':nacionalidad' => $nacionalidad,
        ':dorsal' => $dorsal
    ]);

    header("Location: plantilla.php");
    exit();
}

?>