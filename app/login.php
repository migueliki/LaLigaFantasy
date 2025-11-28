<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

include 'conexion.php';

// Obtener datos del formulario
$username = $_POST['username'] ?? null; // si no ponemos nada en el formulario, te los pondrá null
$password = $_POST['password'] ?? null;

if (!$username || !$password) {   // Verificar que se recibieron los datos (no son nulos)
    header("Location: index.php");
    exit();
}

// Buscar usuario
$sql = "SELECT * FROM register WHERE username = ?";
$stmt = $pdo->prepare($sql); // le esta diciendo que use la conexion $pdo para preparar la consulta sql
$stmt->execute([$username]); // ejecuta la consulta con el parametro usuario reemplazando el ?
$datos = $stmt->fetch(); // recupera los datos si existe el usuario

if ($datos && $password === $datos['password']) {
    
    $_SESSION['usuario_id'] = $datos['id'];     
    $_SESSION['usuario'] = $datos['username']; 
    session_regenerate_id(true);  // seguridad extra para evitar hijacking de sesiones

    header("Location: inicio.php"); // Redirigir a inicio.php
    exit();

} else {
    header("Location: index.php"); // Redirigir al formulario en index.php
    exit();
}

?>

