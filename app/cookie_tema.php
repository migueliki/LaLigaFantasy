<?php

//  Si pulsan un botón, guardamos la cookie y recargamos
if (isset($_POST['tema_pref'])) {
    $tema = (string)($_POST['tema_pref'] ?? 'tema-laliga'); // 'tema-original' o 'tema-laliga'
    if (!in_array($tema, ['tema-original', 'tema-laliga'], true)) {
        $tema = 'tema-laliga';
    }
    
    // Cookie por 30 días en todo el sitio
    setcookie("preferencia_tema", $tema, time() + (86400 * 30), "/");
    
    // Recargar para ver cambios
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit();
}

//  Leemos la cookie. Si no existe, usamos tema LaLiga (rojo) por defecto
$clase_tema = (string)($_COOKIE['preferencia_tema'] ?? 'tema-laliga');
if (!in_array($clase_tema, ['tema-original', 'tema-laliga'], true)) {
    $clase_tema = 'tema-laliga';
}
?>

