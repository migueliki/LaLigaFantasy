<?php
// Procesar los datos del formulario
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = $_POST['nombre'];
    $email = $_POST['email'];
    $contraseña = $_POST['contraseña'];
    $equipo_favorito = $_POST['equipo_favorito'];
    
    // Aquí puedes validar, guardar en base de datos, etc.
    
    // Redirigir a inicio.php
    header("Location: inicio.php");
    exit(); // Importante: terminar la ejecución después de header
}
?>

