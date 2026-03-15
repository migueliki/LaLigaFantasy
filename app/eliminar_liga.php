<?php
session_start();
require_once 'config.php';
require_once 'csrf.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . BASE_URL . '/index.php');
    exit();
}

$jornada = max(1, min(38, (int)($_POST['jornada'] ?? $_GET['jornada'] ?? 1)));

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_validate_token($_POST['csrf_token'] ?? '')) {
    $query = http_build_query([
        'type' => 'error',
        'msg' => 'Solicitud no válida.',
        'jornada' => $jornada,
    ]);
    header('Location: ' . BASE_URL . '/inicio.php?' . $query);
    exit();
}

require_once 'conexion.php';
require_once __DIR__ . '/lib/league_service.php';

$leagueId = max(0, (int)($_POST['liga_id'] ?? 0));
$result = fantasy_delete_league($pdo, (int)$_SESSION['usuario_id'], $leagueId);

$query = http_build_query([
    'type' => $result['ok'] ? 'success' : 'error',
    'msg' => $result['mensaje'],
    'jornada' => $jornada,
]);

header('Location: ' . BASE_URL . '/inicio.php?' . $query);
exit();
