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

if (!isset($_POST['username'], $_POST['email'], $_POST['password'])) {
    header("Location: index.php?register_error=missing_fields");
    exit();
}

$username = trim($_POST['username']);
$email = trim($_POST['email']);
$password = $_POST['password'];

if ($username === '' || $email === '' || $password === '') {
    header("Location: index.php?register_error=missing_fields");
    exit();
}

$stmt = $pdo->prepare("SELECT id FROM register WHERE username = ? LIMIT 1");
$stmt->execute([$username]);
if ($stmt->fetch()) {
    header("Location: index.php?register_error=username_exists");
    exit();
}

$stmt = $pdo->prepare("SELECT id FROM register WHERE email = ? LIMIT 1");
$stmt->execute([$email]);
if ($stmt->fetch()) {
    header("Location: index.php?register_error=email_exists");
    exit();
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);

$sql = "INSERT INTO register (username, password, email) VALUES (?, ?, ?)";
$stmt = $pdo->prepare($sql);
$stmt->execute([$username, $passwordHash, $email]);

header("Location: index.php?register=ok");
exit();