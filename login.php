<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'conexion.php';

// Obtener datos del formulario
$usuario = $_POST['usuario'];
$contraseña = $_POST['contraseña'];

// Buscar usuario
$sql = "SELECT * FROM register WHERE usuario = ?";
$stmt = $pdo->prepare($sql); // le esta diciendo que use la conexion $pdo para preparar la consulta sql
$stmt->execute([$usuario]); // ejecuta la consulta con el parametro usuario reemplazando el ?
$datos = $stmt->fetch(); // recupera los datos si existe el usuario

if ($datos && $contraseña === $datos['contraseña']) {
    echo "Bienvenido, $usuario";
    header("Location: inicio.php"); // Redirigir a inicio.php
    exit(); 

} else {
    echo "Usuario o contraseña incorrectos";
    header("Location: index.php"); // Redirigir al formulario de inicio de sesión para volver a intentarlo
    exit(); 
}

?>

