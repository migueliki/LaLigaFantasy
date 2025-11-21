<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'conexion.php';

// Obtener datos del formulario
$usuario = $_POST['usuario'];
$email = $_POST['email'];
$contraseña = $_POST['contraseña'];

// Buscar usuario
$sql = "INSERT INTO register (usuario,email,contraseña) VALUES (?, ?, ?)"; // los ? son parametros que se van a reemplazar despues para prevenir injeccion sql
$stmt = $pdo->prepare($sql); // le esta diciendo que use la conexion $pdo para preparar el insert sql
$stmt->execute([$usuario, $email, $contraseña]); // ejecuta el insert con el parametro usuario y contraseña reemplazando los ? 

echo "Usuario registrado exitosamente";

?>