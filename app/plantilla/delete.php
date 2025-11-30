<?php
require_once '../app/conexion.php'; 

if (!empty($_GET["ID"])) {
    $ID=$_GET["ID"];
$sql = "DELETE FROM plantilla WHERE ID=$ID";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':ID' => $ID,
    ]);

    if ($sql==1) {
        echo '<div>Jugador eliminado correctamente</div>';
    } else {
        echo '<div>Jugador eliminado correctamente</div>';
    }
}

?>
