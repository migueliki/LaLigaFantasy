<?php
require_once __DIR__ . '/../conexion.php';

$id = (int)($_GET['ID'] ?? 0);
if ($id < 1) { echo 'ID inválido'; exit; }

$stmt = $pdo->prepare('DELETE FROM plantilla WHERE ID = :id');
$stmt->execute([':id' => $id]);

header('Location: ../../pages/plantilla.php'); 
exit;
?>
