<?php
require_once __DIR__ . '/../conexion.php';
include __DIR__ . '/../csrf.php';

if (!csrf_validate_token($_POST['csrf_token'] ?? '')) { http_response_code(400); exit('CSRF inválido'); }

$id = (int)($_GET['ID'] ?? 0);
if ($id < 1) { echo 'ID inválido'; exit; }

$stmt = $pdo->prepare('DELETE FROM plantilla WHERE ID = :id');
$stmt->execute([':id' => $id]);

header('Location: ../../pages/plantilla.php'); 
exit;
?>
