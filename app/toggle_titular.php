<?php
/**
 * API: Cambiar titular/suplente de un jugador en la plantilla del usuario
 * POST JSON { csrf, jugador_id, es_titular }
 * Responde JSON { ok, mensaje }
 */
header('Content-Type: application/json; charset=utf-8');

session_start();
require_once 'config.php';
require_once 'conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok'=>false,'mensaje'=>'Método no permitido']);
    exit();
}

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['ok'=>false,'mensaje'=>'No autenticado']);
    exit();
}

$body = json_decode(file_get_contents('php://input'), true);
if (!$body) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'mensaje'=>'Request inválido']);
    exit();
}

require_once 'csrf.php';
$csrf = $body['csrf'] ?? '';
if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'mensaje'=>'Token CSRF inválido']);
    exit();
}

$uid       = (int)$_SESSION['usuario_id'];
$jugadorId = (int)($body['jugador_id'] ?? 0);
$esTitular = isset($body['es_titular']) ? (bool)$body['es_titular'] : false;

if ($jugadorId <= 0) {
    echo json_encode(['ok'=>false,'mensaje'=>'Jugador inválido']);
    exit();
}

function total_slots_por_formacion(?string $formacion): int {
    $formaciones = [
        '4-3-3'   => ['Portero'=>1,'Defensa'=>4,'Centrocampista'=>3,'Delantero'=>3],
        '4-4-2'   => ['Portero'=>1,'Defensa'=>4,'Centrocampista'=>4,'Delantero'=>2],
        '4-2-3-1' => ['Portero'=>1,'Defensa'=>4,'Centrocampista'=>5,'Delantero'=>1],
        '3-5-2'   => ['Portero'=>1,'Defensa'=>3,'Centrocampista'=>5,'Delantero'=>2],
        '5-3-2'   => ['Portero'=>1,'Defensa'=>5,'Centrocampista'=>3,'Delantero'=>2],
        '4-1-4-1' => ['Portero'=>1,'Defensa'=>4,'Centrocampista'=>5,'Delantero'=>1],
    ];

    $slots = $formaciones[$formacion ?? ''] ?? $formaciones['4-3-3'];
    return (int)array_sum($slots);
}

function normalizar_slots_usuario(PDO $pdo, int $uid, int $maxSlots): void {
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
    } catch (PDOException $e) { }

    $stmtForm = $pdo->prepare("SELECT formacion FROM usuarios_equipos WHERE usuario_id = ?");
    $stmtForm->execute([$uid]);
    $formacion = (string)($stmtForm->fetchColumn() ?: '4-3-3');
    $totalSlots = total_slots_por_formacion($formacion);

    $pdo->beginTransaction();

    // Verificar que el jugador pertenece al usuario
    $stmt = $pdo->prepare("SELECT id FROM usuarios_jugadores WHERE usuario_id = ? AND jugador_id = ?");
    $stmt->execute([$uid, $jugadorId]);
    if (!$stmt->fetch()) {
        $pdo->rollBack();
        echo json_encode(['ok'=>false,'mensaje'=>'No tienes ese jugador en tu plantilla']);
        exit();
    }

    normalizar_slots_usuario($pdo, $uid, $totalSlots);

    if ($esTitular) {
        $stmtUsed = $pdo->prepare("SELECT slot_titular FROM usuarios_jugadores WHERE usuario_id = ? AND es_titular = TRUE AND slot_titular IS NOT NULL ORDER BY slot_titular");
        $stmtUsed->execute([$uid]);
        $usados = array_map('intval', $stmtUsed->fetchAll(PDO::FETCH_COLUMN));

        $slotLibre = null;
        for ($s = 1; $s <= $totalSlots; $s++) {
            if (!in_array($s, $usados, true)) {
                $slotLibre = $s;
                break;
            }
        }

        if ($slotLibre === null) {
            $pdo->rollBack();
            echo json_encode(['ok'=>false,'mensaje'=>'El once titular ya está completo. Arrastra un titular al banquillo o reemplázalo desde el campo.']);
            exit();
        }

        $stmt = $pdo->prepare("UPDATE usuarios_jugadores SET es_titular = 1, slot_titular = ? WHERE usuario_id = ? AND jugador_id = ?");
        $stmt->execute([$slotLibre, $uid, $jugadorId]);
    } else {
        $stmt = $pdo->prepare("UPDATE usuarios_jugadores SET es_titular = 0, slot_titular = NULL WHERE usuario_id = ? AND jugador_id = ?");
        $stmt->execute([$uid, $jugadorId]);
    }

    normalizar_slots_usuario($pdo, $uid, $totalSlots);
    $pdo->commit();

    $msg = $esTitular ? 'Jugador puesto como titular' : 'Jugador enviado al banquillo';
    echo json_encode(['ok'=>true,'mensaje'=>$msg]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['ok'=>false,'mensaje'=>'Error de base de datos']);
}
