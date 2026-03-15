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

$jornada = max(1, min(38, (int)($_POST['jornada'] ?? 1)));
$ligaId = max(0, (int)($_POST['liga_id'] ?? 0));

require_once 'conexion.php';
require_once __DIR__ . '/lib/league_service.php';

$result = fantasy_sync_jornada($pdo, $jornada);
$type = $result['ok'] ? 'success' : 'error';
$msg = $result['mensaje'];
if (!empty($result['ok'])) {
    $msg .= ' Partidos: ' . (int)($result['partidos'] ?? 0) . '. Jugadores recalculados: ' . (int)($result['jugadores'] ?? 0) . '. Valores ajustados: ' . (int)($result['valores_ajustados'] ?? 0) . '.';
}

$query = http_build_query([
    'type' => $type,
    'msg' => $msg,
    'liga' => $ligaId,
    'jornada' => $jornada,
]);

header('Location: ' . BASE_URL . '/inicio.php?' . $query);
exit();
