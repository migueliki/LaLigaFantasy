<?php
session_start();
require_once 'config.php';
require_once 'csrf.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . BASE_URL . '/index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_validate_token($_POST['csrf_token'] ?? '')) {
    header('Location: ' . BASE_URL . '/inicio.php?type=error&msg=' . urlencode('Solicitud no válida.'));
    exit();
}

require_once 'conexion.php';
require_once __DIR__ . '/lib/league_service.php';

$result = fantasy_create_league($pdo, (int)$_SESSION['usuario_id'], (string)($_POST['nombre_liga'] ?? ''));
$type = $result['ok'] ? 'success' : 'error';
$query = http_build_query([
    'type' => $type,
    'msg' => $result['ok']
        ? $result['mensaje'] . ' Código: ' . ($result['codigo'] ?? '')
        : $result['mensaje'],
    'liga' => $result['liga_id'] ?? null,
]);

header('Location: ' . BASE_URL . '/inicio.php?' . $query);
exit();
