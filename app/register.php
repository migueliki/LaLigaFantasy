<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'conexion.php';

if (isset($_POST['username'], $_POST['email'], $_POST['password'])) {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = $_POST['password'];

    $passwordHash = password_hash($password, PASSWORD_DEFAULT); // Hashear la contraseña
    
    $sql = "INSERT INTO register (username, email, password) VALUES (?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$username, $email, $password]);

    header("Location: index.php");
    exit();
} else {
    echo "Faltan datos del formulario.";
}