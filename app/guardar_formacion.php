<?php
/**
 * API: Guardar formación elegida por el usuario
 * POST JSON { csrf, formacion }
 * Responde JSON { ok, mensaje }
 */
header('Content-Type: application/json; charset=utf-8');

session_start();
require_once 'config.php';
require_once 'conexion.php';

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok'=>false,'mensaje'=>'Método no permitido']);
    exit();
}

// Sesión
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['ok'=>false,'mensaje'=>'No autenticado']);
    exit();
}

// Leer body JSON
$body = json_decode(file_get_contents('php://input'), true);
if (!$body) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'mensaje'=>'Request inválido']);
    exit();
}

// CSRF
require_once 'csrf.php';
$csrf = $body['csrf'] ?? '';
if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'mensaje'=>'Token CSRF inválido']);
    exit();
}

$formacionesValidas = array_keys(app_formations());
$formacion = trim($body['formacion'] ?? '');

if (!in_array($formacion, $formacionesValidas, true)) {
    echo json_encode(['ok'=>false,'mensaje'=>'Formación no válida']);
    exit();
}

$uid = (int)$_SESSION['usuario_id'];

try {
    $stmt = $pdo->prepare("UPDATE usuarios_equipos SET formacion = ? WHERE usuario_id = ?");
    $stmt->execute([$formacion, $uid]);

    echo json_encode(['ok'=>true,'mensaje'=>'Formación guardada: ' . $formacion]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'mensaje'=>'Error al guardar en la base de datos']);
}
