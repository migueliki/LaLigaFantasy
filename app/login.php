<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die("Error: token CSRF inválido");
}
include 'cookie_tema.php';
include 'conexion.php';

$username = $_POST['username'] ?? null; 
$password = $_POST['password'] ?? null;

// Buscar usuario
$sql = "SELECT * FROM register WHERE username = ?";
$stmt = $pdo->prepare($sql); // con este metodo evitamos inyecciones SQL
$stmt->execute([$username]); 
$datos = $stmt->fetch(); 

if ($datos && password_verify($password, $datos['password'])) { 
    
    session_regenerate_id(true);// evitar hijacking de sesiones
    $_SESSION['usuario_id'] = $datos['id'];     
    $_SESSION['usuario'] = $datos['username']; 
    $_SESSION['login_time'] = time();
    $_SESSION['last_activity'] = time();  

    header("Location: inicio.php"); 
    exit();

} else {
    header("Location: index.php?error=login_fallido");
    exit();
}

?>
