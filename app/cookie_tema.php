<?php


//  Si pulsan un botón, guardamos la cookie y recargamos
if (isset($_POST['tema_pref'])) {
    $tema = $_POST['tema_pref']; // 'tema-original' o 'tema-claro'
    
    // Cookie por 30 días en todo el sitio
    setcookie("preferencia_tema", $tema, time() + (86400 * 30), "/");
    
    // Recargar para ver cambios
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit();
}

//  Leemos la cookie. Si no existe, dejamos cadena vacía (usamos nuestro CSS original por defecto)
$clase_tema = isset($_COOKIE['preferencia_tema']) ? $_COOKIE['preferencia_tema'] : '';
?>

