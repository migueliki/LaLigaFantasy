<?php
/**
 * API endpoint: comprar jugador del mercado
 * POST  jugador_id, csrf_token
 * Responde JSON { ok, mensaje, saldo_nuevo }
 */
header('Content-Type: application/json; charset=utf-8');

session_start();
require_once 'csrf.php';
require_once 'config.php';

function jsonErr(string $msg, int $code = 200): never {
    http_response_code($code);
    echo json_encode(['ok' => false, 'mensaje' => $msg]);
    exit();
}
function jsonOk(string $msg, float $saldo): never {
    echo json_encode(['ok' => true, 'mensaje' => $msg, 'saldo_nuevo' => $saldo]);
    exit();
}

// ── Autenticación ──
if (!isset($_SESSION['usuario_id'])) {
    jsonErr('No autenticado.', 401);
}

// ── CSRF ──
if (!csrf_validate_token($_POST['csrf_token'] ?? '')) {
    jsonErr('Token CSRF inválido.', 403);
}

$jugadorId = (int)($_POST['jugador_id'] ?? 0);
if ($jugadorId <= 0) jsonErr('Jugador no válido.');

require_once 'conexion.php';

$usuarioId = (int)$_SESSION['usuario_id'];

try {
    $pdo->beginTransaction();

    // ── Bloquear fila del mercado ──
    $stmtM = $pdo->prepare(
        "SELECT m.id, m.precio, m.disponible FROM mercado m WHERE m.jugador_id = ? AND m.disponible = TRUE FOR UPDATE"
    );
    $stmtM->execute([$jugadorId]);
    $mercado = $stmtM->fetch(PDO::FETCH_OBJ);

    if (!$mercado) {
        $pdo->rollBack();
        jsonErr('Este jugador ya no está disponible en el mercado.');
    }

    $precio = (float)$mercado->precio;

    // ── Verificar que no sea ya propietario ──
    $stmtProp = $pdo->prepare("SELECT id FROM usuarios_jugadores WHERE usuario_id = ? AND jugador_id = ? LIMIT 1");
    $stmtProp->execute([$usuarioId, $jugadorId]);
    if ($stmtProp->fetch()) {
        $pdo->rollBack();
        jsonErr('Ya tienes a este jugador en tu plantilla.');
    }

    // ── Bloquear y comprobar saldo ──
    $stmtEq = $pdo->prepare("SELECT id, saldo FROM usuarios_equipos WHERE usuario_id = ? FOR UPDATE");
    $stmtEq->execute([$usuarioId]);
    $equipo = $stmtEq->fetch(PDO::FETCH_OBJ);

    if (!$equipo) {
        $pdo->rollBack();
        jsonErr('No tienes un equipo registrado.');
    }

    $saldo = (float)$equipo->saldo;
    if ($saldo < $precio) {
        $pdo->rollBack();
        jsonErr('Saldo insuficiente. Necesitas ' . number_format($precio, 0, ',', '.') . ' € y tienes ' . number_format($saldo, 0, ',', '.') . ' €.');
    }

    // ── Descontar saldo ──
    $nuevoSaldo = $saldo - $precio;
    $pdo->prepare("UPDATE usuarios_equipos SET saldo = ? WHERE usuario_id = ?")
        ->execute([$nuevoSaldo, $usuarioId]);

    // ── Asignar jugador al usuario ──
    $pdo->prepare("INSERT INTO usuarios_jugadores (usuario_id, jugador_id, precio_compra) VALUES (?, ?, ?)")
        ->execute([$usuarioId, $jugadorId, $precio]);

    // ── Marcar jugador como no disponible en mercado ──
    $pdo->prepare("UPDATE mercado SET disponible = FALSE WHERE jugador_id = ?")
        ->execute([$jugadorId]);

    // ── Registrar transacción ──
    $pdo->prepare(
        "INSERT INTO transacciones (usuario_id, jugador_id, tipo, precio) VALUES (?, ?, 'compra', ?)"
    )->execute([$usuarioId, $jugadorId, $precio]);

    $pdo->commit();

    // Obtener nombre del jugador para el mensaje
    $nombre = $pdo->prepare("SELECT nombre FROM jugadores WHERE id = ?");
    $nombre->execute([$jugadorId]);
    $jugador = $nombre->fetch(PDO::FETCH_OBJ);

    jsonOk('Has comprado a ' . ($jugador->nombre ?? 'el jugador') . ' por ' . number_format($precio, 0, ',', '.') . ' €.', $nuevoSaldo);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    jsonErr('Error de base de datos. Inténtalo de nuevo.');
}
