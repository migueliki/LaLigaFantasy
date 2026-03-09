<?php
/**
 * API endpoint: vender jugador de vuelta al mercado
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

    // ── Verificar que el usuario sea propietario ──
    $stmtProp = $pdo->prepare(
        "SELECT uj.id, uj.precio_compra FROM usuarios_jugadores uj WHERE uj.usuario_id = ? AND uj.jugador_id = ? FOR UPDATE"
    );
    $stmtProp->execute([$usuarioId, $jugadorId]);
    $propiedad = $stmtProp->fetch(PDO::FETCH_OBJ);

    if (!$propiedad) {
        $pdo->rollBack();
        jsonErr('No eres propietario de este jugador.');
    }

    // ── Obtener precio de venta del mercado ──
    $stmtM = $pdo->prepare("SELECT precio FROM mercado WHERE jugador_id = ? LIMIT 1");
    $stmtM->execute([$jugadorId]);
    $mercadoRow = $stmtM->fetch(PDO::FETCH_OBJ);

    if (!$mercadoRow) {
        $pdo->rollBack();
        jsonErr('El jugador no tiene precio de mercado asignado.');
    }

    $precioVenta = (float)$mercadoRow->precio;

    // ── Sumar saldo al usuario ──
    $stmtEq = $pdo->prepare("SELECT saldo FROM usuarios_equipos WHERE usuario_id = ? FOR UPDATE");
    $stmtEq->execute([$usuarioId]);
    $equipo = $stmtEq->fetch(PDO::FETCH_OBJ);

    if (!$equipo) {
        $pdo->rollBack();
        jsonErr('No tienes un equipo registrado.');
    }

    $nuevoSaldo = (float)$equipo->saldo + $precioVenta;
    $pdo->prepare("UPDATE usuarios_equipos SET saldo = ? WHERE usuario_id = ?")
        ->execute([$nuevoSaldo, $usuarioId]);

    // ── Quitar jugador de la plantilla del usuario ──
    $pdo->prepare("DELETE FROM usuarios_jugadores WHERE usuario_id = ? AND jugador_id = ?")
        ->execute([$usuarioId, $jugadorId]);

    // ── Volver a poner disponible en mercado ──
    $pdo->prepare("UPDATE mercado SET disponible = TRUE WHERE jugador_id = ?")
        ->execute([$jugadorId]);

    // ── Registrar transacción ──
    $pdo->prepare(
        "INSERT INTO transacciones (usuario_id, jugador_id, tipo, precio) VALUES (?, ?, 'venta', ?)"
    )->execute([$usuarioId, $jugadorId, $precioVenta]);

    $pdo->commit();

    $nombre = $pdo->prepare("SELECT nombre FROM jugadores WHERE id = ?");
    $nombre->execute([$jugadorId]);
    $jugador = $nombre->fetch(PDO::FETCH_OBJ);

    jsonOk('Has vendido a ' . ($jugador->nombre ?? 'el jugador') . ' por ' . number_format($precioVenta, 0, ',', '.') . ' €.', $nuevoSaldo);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    jsonErr('Error de base de datos. Inténtalo de nuevo.');
}
