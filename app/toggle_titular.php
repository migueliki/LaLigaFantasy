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

try {
    // Verificar que el jugador pertenece al usuario
    $stmt = $pdo->prepare("SELECT id FROM usuarios_jugadores WHERE usuario_id = ? AND jugador_id = ?");
    $stmt->execute([$uid, $jugadorId]);
    if (!$stmt->fetch()) {
        echo json_encode(['ok'=>false,'mensaje'=>'No tienes ese jugador en tu plantilla']);
        exit();
    }

    // Si va a ser titular, comprobar límite por posición según formación
    if ($esTitular) {
        // Obtener posición del jugador
        $stmtPos = $pdo->prepare("SELECT posicion FROM jugadores WHERE id = ?");
        $stmtPos->execute([$jugadorId]);
        $posRow = $stmtPos->fetch(PDO::FETCH_OBJ);
        $posicion = $posRow ? $posRow->posicion : '';

        // Normalizar (Extremo → Delantero)
        if (stripos($posicion, 'Extremo') !== false) $posicion = 'Delantero';

        // Obtener formación actual
        $stmtForm = $pdo->prepare("SELECT formacion FROM usuarios_equipos WHERE usuario_id = ?");
        $stmtForm->execute([$uid]);
        $eq = $stmtForm->fetch(PDO::FETCH_OBJ);
        $formacion = $eq ? $eq->formacion : '4-3-3';

        $formaciones = [
            '4-3-3'   => ['Portero'=>1,'Defensa'=>4,'Centrocampista'=>3,'Delantero'=>3],
            '4-4-2'   => ['Portero'=>1,'Defensa'=>4,'Centrocampista'=>4,'Delantero'=>2],
            '4-2-3-1' => ['Portero'=>1,'Defensa'=>4,'Centrocampista'=>5,'Delantero'=>1],
            '3-5-2'   => ['Portero'=>1,'Defensa'=>3,'Centrocampista'=>5,'Delantero'=>2],
            '5-3-2'   => ['Portero'=>1,'Defensa'=>5,'Centrocampista'=>3,'Delantero'=>2],
            '4-1-4-1' => ['Portero'=>1,'Defensa'=>4,'Centrocampista'=>5,'Delantero'=>1],
        ];
        $slots = $formaciones[$formacion] ?? $formaciones['4-3-3'];
        $maxPos = $slots[$posicion] ?? 0;

        if ($maxPos > 0) {
            // Contar cuántos titulares de esa posición ya hay
            $stmtCount = $pdo->prepare("
                SELECT COUNT(*) FROM usuarios_jugadores uj
                JOIN jugadores j ON uj.jugador_id = j.id
                WHERE uj.usuario_id = ? AND uj.es_titular = TRUE
                  AND (j.posicion = ? OR (? = 'Delantero' AND j.posicion = 'Extremo'))
                  AND uj.jugador_id != ?
            ");
            $stmtCount->execute([$uid, $posicion, $posicion, $jugadorId]);
            $actuales = (int)$stmtCount->fetchColumn();

            if ($actuales >= $maxPos) {
                $posLabel = [
                    'Portero'=>'porteros','Defensa'=>'defensas',
                    'Centrocampista'=>'centrocampistas','Delantero'=>'delanteros'
                ][$posicion] ?? $posicion;
                echo json_encode([
                    'ok'     => false,
                    'mensaje'=> "Ya tienes {$actuales}/{$maxPos} {$posLabel} titulares en la formación {$formacion}. Saca uno primero."
                ]);
                exit();
            }
        }
    }

    // Actualizar
    $stmt = $pdo->prepare("UPDATE usuarios_jugadores SET es_titular = ? WHERE usuario_id = ? AND jugador_id = ?");
    $stmt->execute([$esTitular ? 1 : 0, $uid, $jugadorId]);

    $msg = $esTitular ? 'Jugador puesto como titular' : 'Jugador enviado al banquillo';
    echo json_encode(['ok'=>true,'mensaje'=>$msg]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'mensaje'=>'Error de base de datos']);
}
