<?php
header('Content-Type: application/json; charset=utf-8');

session_start();
require_once 'config.php';
require_once 'conexion.php';

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
if (!$body) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => 'Request inválido']);
    exit();
}

require_once 'csrf.php';
$csrf = $body['csrf'] ?? '';
if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'mensaje' => 'Token CSRF inválido']);
    exit();
}

$uid = (int)$_SESSION['usuario_id'];
$jugadorId = (int)($body['jugador_id'] ?? 0);
$slotDestino = (int)($body['slot_destino'] ?? 0);

if ($jugadorId <= 0 || $slotDestino <= 0) {
    echo json_encode(['ok' => false, 'mensaje' => 'Datos de movimiento inválidos']);
    exit();
}

try {
    try {
        $pdo->exec("ALTER TABLE usuarios_jugadores ADD COLUMN slot_titular TINYINT UNSIGNED NULL DEFAULT NULL");
    } catch (PDOException $e) {
    }

    $stmtForm = $pdo->prepare("SELECT formacion FROM usuarios_equipos WHERE usuario_id = ?");
    $stmtForm->execute([$uid]);
    $formacion = (string)($stmtForm->fetchColumn() ?: DEFAULT_FORMATION);
    $totalSlots = formation_total_slots($formacion);

    if ($slotDestino > $totalSlots) {
        echo json_encode(['ok' => false, 'mensaje' => 'Ese hueco no existe en la formación actual']);
        exit();
    }

    $pdo->beginTransaction();

    $stmtOwn = $pdo->prepare("SELECT es_titular, slot_titular FROM usuarios_jugadores WHERE usuario_id = ? AND jugador_id = ? LIMIT 1");
    $stmtOwn->execute([$uid, $jugadorId]);
    $origen = $stmtOwn->fetch(PDO::FETCH_ASSOC);
    if (!$origen) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'mensaje' => 'No tienes ese jugador en tu plantilla']);
        exit();
    }

    normalize_user_lineup_slots($pdo, $uid, $totalSlots);

    $stmtOwn->execute([$uid, $jugadorId]);
    $origen = $stmtOwn->fetch(PDO::FETCH_ASSOC);
    $esTitularOrigen = (int)($origen['es_titular'] ?? 0) === 1;
    $slotOrigen = (int)($origen['slot_titular'] ?? 0);

    $stmtTarget = $pdo->prepare("SELECT jugador_id FROM usuarios_jugadores WHERE usuario_id = ? AND es_titular = 1 AND slot_titular = ? LIMIT 1");
    $stmtTarget->execute([$uid, $slotDestino]);
    $jugadorEnDestino = (int)($stmtTarget->fetchColumn() ?: 0);

    $updTitularSlot = $pdo->prepare("UPDATE usuarios_jugadores SET es_titular = 1, slot_titular = ? WHERE usuario_id = ? AND jugador_id = ?");
    $updSoloSlot = $pdo->prepare("UPDATE usuarios_jugadores SET slot_titular = ? WHERE usuario_id = ? AND jugador_id = ?");
    $toBench = $pdo->prepare("UPDATE usuarios_jugadores SET es_titular = 0, slot_titular = NULL WHERE usuario_id = ? AND jugador_id = ?");

    if ($esTitularOrigen) {
        if ($slotOrigen === $slotDestino) {
            $pdo->commit();
            echo json_encode(['ok' => true, 'mensaje' => 'Sin cambios']);
            exit();
        }

        if ($jugadorEnDestino > 0 && $jugadorEnDestino !== $jugadorId) {
            $updSoloSlot->execute([$slotOrigen, $uid, $jugadorEnDestino]);
        }
        $updSoloSlot->execute([$slotDestino, $uid, $jugadorId]);
    } else {
        if ($jugadorEnDestino > 0) {
            $toBench->execute([$uid, $jugadorEnDestino]);
        }
        $updTitularSlot->execute([$slotDestino, $uid, $jugadorId]);
    }

    normalize_user_lineup_slots($pdo, $uid, $totalSlots);
    $pdo->commit();

    echo json_encode(['ok' => true, 'mensaje' => 'Posición actualizada']);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'Error de base de datos']);
}
