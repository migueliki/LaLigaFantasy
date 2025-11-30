<?php

// ELIMINAR JUGADOR
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ID = $_POST['ID'] ?? '';

    $sql = "DELETE FROM plantilla WHERE ID=$ID";
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

    if ($sql==1) {
        echo '<div>Jugador eliminado de la plantilla</div>'
    } 
    else {
        echo '<div>No se pudo eliminar al jugador de la plantilla</div>'
    }
}

?>
