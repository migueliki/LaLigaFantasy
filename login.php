<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$pdo = new PDO("mysql:host=localhost;dbname=mi_base", "mi_usuario", "mi_contraseña"); // conexion a la base de datos

// Obtener datos del formulario
$usuario = $_POST['usuario'];
$email = $_POST['email'];
$contraseña = $_POST['contraseña'];
$equipo_favorito = $_POST['equipo_favorito'];

// Buscar usuario
$sql = "SELECT * FROM usuarios WHERE usuario = ?";
$stmt = $pdo->prepare($sql); // le esta diciendo que use la conexion $pdo para preparar la consulta sql
$stmt->execute([$usuario]); // ejecuta la consulta con el parametro usuario reemplazando el ?
$datos = $stmt->fetch(); // recupera los datos si existe el usuario

if ($datos && $contraseña === $datos['contraseña']) {
    
    $sql2 = "INSERT INTO login_registro (usuario, contraseña) VALUES (?, ?)";
    $stmt2 = $pdo->prepare($sql2);
    $stmt2->execute([$usuario, $contraseña]);

    echo "Bienvenido, $usuario";
    header("Location: inicio.php"); // Redirigir a inicio.php
    exit(); 

} else {
    echo "Usuario o contraseña incorrectos";
    header("Location: index.php"); // Redirigir al formulario de inicio de sesión para volver a intentarlo
    exit(); 
}


?>

