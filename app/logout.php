<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
$_SESSION = [];
session_unset();
session_destroy();
setcookie(session_name(), '', time()-3600, '/'); // eliminar cookie de sesión
header('Location: /app/index.php');
exit();
?>
