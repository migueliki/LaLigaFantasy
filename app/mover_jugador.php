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

function total_slots_por_formacion_mv(?string $formacion): int
{
    $formaciones = [
        '4-3-3'   => ['Portero' => 1, 'Defensa' => 4, 'Centrocampista' => 3, 'Delantero' => 3],
        '4-4-2'   => ['Portero' => 1, 'Defensa' => 4, 'Centrocampista' => 4, 'Delantero' => 2],
        '4-2-3-1' => ['Portero' => 1, 'Defensa' => 4, 'Centrocampista' => 5, 'Delantero' => 1],
        '3-5-2'   => ['Portero' => 1, 'Defensa' => 3, 'Centrocampista' => 5, 'Delantero' => 2],
        '5-3-2'   => ['Portero' => 1, 'Defensa' => 5, 'Centrocampista' => 3, 'Delantero' => 2],
        '4-1-4-1' => ['Portero' => 1, 'Defensa' => 4, 'Centrocampista' => 5, 'Delantero' => 1],
    ];

    $slots = $formaciones[$formacion ?? ''] ?? $formaciones['4-3-3'];
    return (int)array_sum($slots);
}

function normalizar_slots_usuario_mv(PDO $pdo, int $uid, int $maxSlots): void
{
    $stmt = $pdo->prepare(
        "SELECT jugador_id, slot_titular
         FROM usuarios_jugadores
         WHERE usuario_id = ? AND es_titular = TRUE
         ORDER BY (slot_titular IS NULL), slot_titular ASC, jugador_id ASC"
    );
    $stmt->execute([$uid]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $slot = 1;
    $updSlot = $pdo->prepare("UPDATE usuarios_jugadores SET slot_titular = ? WHERE usuario_id = ? AND jugador_id = ?");
    $toBench = $pdo->prepare("UPDATE usuarios_jugadores SET es_titular = 0, slot_titular = NULL WHERE usuario_id = ? AND jugador_id = ?");

    foreach ($rows as $row) {
        $jid = (int)$row['jugador_id'];
        if ($slot <= $maxSlots) {
            $updSlot->execute([$slot, $uid, $jid]);
            $slot++;
        } else {
            $toBench->execute([$uid, $jid]);
        }
    }
}

try {
    try {
        $pdo->exec("ALTER TABLE usuarios_jugadores ADD COLUMN slot_titular TINYINT UNSIGNED NULL DEFAULT NULL");
    } catch (PDOException $e) {
    }

    $stmtForm = $pdo->prepare("SELECT formacion FROM usuarios_equipos WHERE usuario_id = ?");
    $stmtForm->execute([$uid]);
    $formacion = (string)($stmtForm->fetchColumn() ?: '4-3-3');
    $totalSlots = total_slots_por_formacion_mv($formacion);

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

    normalizar_slots_usuario_mv($pdo, $uid, $totalSlots);

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

    normalizar_slots_usuario_mv($pdo, $uid, $totalSlots);
    $pdo->commit();

    echo json_encode(['ok' => true, 'mensaje' => 'Posición actualizada']);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'Error de base de datos']);
}
