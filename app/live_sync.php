<?php
header('Content-Type: application/json; charset=utf-8');

session_start();
require_once 'config.php';
require_once 'conexion.php';
require_once 'csrf.php';
require_once __DIR__ . '/lib/league_service.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido']);
    exit();
}

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'mensaje' => 'No autenticado']);
    exit();
}

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => 'Request inválido']);
    exit();
}

$csrf = (string)($body['csrf'] ?? '');
if (!csrf_validate_token($csrf)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'mensaje' => 'Token CSRF inválido']);
    exit();
}

$jornada = max(1, min(38, (int)($body['jornada'] ?? 1)));
$cooldown = max(30, min(600, (int)($body['cooldown'] ?? 90)));

$result = fantasy_sync_jornada_if_due($pdo, $jornada, $cooldown);

if (!empty($result['ok'])) {
    $stmt = $pdo->prepare('SELECT MAX(ultima_sync) FROM partidos WHERE jornada = ?');
    $stmt->execute([$jornada]);
    $result['ultima_sync'] = $stmt->fetchColumn() ?: null;
}

echo json_encode($result);
