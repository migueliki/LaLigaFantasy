<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'csrf.php';

if (!csrf_validate_token($_POST['csrf_token'] ?? '')) {
    http_response_code(400);
    die("Error: token CSRF inválido");
}

require_once 'conexion.php';
include 'cookie_tema.php';

if (isset($_POST['username'], $_POST['email'], $_POST['password'])) {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = $_POST['password'];

    $passwordHash = password_hash($password, PASSWORD_DEFAULT); // Hashear la contraseña
    
    $sql = "INSERT INTO register (username, email, password) VALUES (?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$username, $email, $passwordHash]);

    header("Location: index.php");
    exit();

} else {
    echo "Faltan datos del formulario.";
}