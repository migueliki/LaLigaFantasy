<?php
session_start();
require_once '../config.php';
if (!isset($_SESSION['usuario_id'])) { header('Location: ' . BASE_URL . '/index.php'); exit; }

$timeout = 3600;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
    session_unset(); session_destroy();
    header('Location: ' . BASE_URL . '/index.php?timeout=1'); exit;
}
$_SESSION['last_activity'] = time();

require_once '../conexion.php';
require_once '../csrf.php';
include_once '../cookie_tema.php';
require_once '../lib/fantasy_bootstrap.php';
require_once '../lib/league_service.php';

$mensajeActualizacion = trim((string)($_GET['msg'] ?? ''));
$tipoActualizacion = ($_GET['type'] ?? 'success') === 'error' ? 'error' : 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_resultados'])) {
    $jornadaActualizar = max(1, min(38, (int)($_POST['jornada'] ?? 1)));

    if (!csrf_validate_token($_POST['csrf_token'] ?? '')) {
        $query = http_build_query([
            'jornada' => $jornadaActualizar,
            'type' => 'error',
            'msg' => 'Solicitud no válida.',
        ]);
        header('Location: ' . BASE_URL . '/pages/calendario.php?' . $query);
        exit;
    }

    fantasy_ensure_schema($pdo);
    $resultadoSync = fantasy_sync_jornada($pdo, $jornadaActualizar);
    $query = http_build_query([
        'jornada' => $jornadaActualizar,
        'type' => $resultadoSync['ok'] ? 'success' : 'error',
        'msg' => $resultadoSync['ok']
            ? $resultadoSync['mensaje'] . ' Partidos: ' . (int)($resultadoSync['partidos'] ?? 0) . '. Jugadores recalculados: ' . (int)($resultadoSync['jugadores'] ?? 0) . '. Valores ajustados: ' . (int)($resultadoSync['valores_ajustados'] ?? 0) . '.'
            : $resultadoSync['mensaje'],
    ]);
    header('Location: ' . BASE_URL . '/pages/calendario.php?' . $query);
    exit;
}

$escudos = [
    'Real Madrid'=>'realmadrid.png','FC Barcelona'=>'barsa.png',
    'Atletico de Madrid'=>'atletimadrid.png','Atlético de Madrid'=>'atletimadrid.png',
    'Sevilla FC'=>'sevillafc.png','Real Betis'=>'Real_Betis.png',
    'Valencia CF'=>'valencia-cf-logo-escudo-1.png','Athletic Club'=>'Athletic_c_de_bilbao.png',
    'Real Sociedad'=>'Real_Sociedad_de_Futbol_logo.png',
    'Villarreal CF'=>'villarreal-club-de-futbol-logo-png_seeklogo-243387.png',
    'CA Osasuna'=>'osasuna-logo-1.png','Levante UD'=>'levante-ud-logo-png.png',
    'Girona FC'=>'girona_fc_.png','Elche CF'=>'Escudo_Elche_CF.png',
    'RCD Espanyol'=>'espanyol-logo.png','Getafe CF'=>'Getafe_CF_Logo.png',
    'RCD Mallorca'=>'RCD_Mallorca.png','RC Celta de Vigo'=>'celta-de-vigo-logo.png',
    'Deportivo Alavés'=>'Deportivo_Alaves_logo_(2020).svg.png',
    'Real Oviedo'=>'oviedo.fc.png',
    'Rayo Vallecano'=>'rayo-vallecano-logo-png-transparent-png.png',
];

// Migración: recrear tabla si falta columna goles
try {
    $cols = $pdo->query("SHOW COLUMNS FROM partidos LIKE 'goles_local'")->fetchAll();
    if (empty($cols)) $pdo->exec("DROP TABLE IF EXISTS partidos");
} catch (PDOException $e) {}

$pdo->exec("CREATE TABLE IF NOT EXISTS partidos (
    id INT PRIMARY KEY AUTO_INCREMENT, jornada INT NOT NULL,
    equipo_local_id INT NOT NULL, equipo_visitante_id INT NOT NULL,
    fecha_hora DATETIME NOT NULL, goles_local INT DEFAULT NULL, goles_visitante INT DEFAULT NULL,
    FOREIGN KEY (equipo_local_id) REFERENCES equipos(id),
    FOREIGN KEY (equipo_visitante_id) REFERENCES equipos(id)
)");

// Verificar si los datos reales están cargados (J1: Girona 1-3 Rayo)
$needsReload = false;
$countPartidos = (int)$pdo->query("SELECT COUNT(*) FROM partidos")->fetchColumn();
if ($countPartidos == 0) {
    $needsReload = true;
} else {
    $chk = $pdo->query("SELECT p.goles_local, p.goles_visitante, el.nombre AS ln
        FROM partidos p JOIN equipos el ON p.equipo_local_id=el.id
        WHERE p.jornada=1 ORDER BY p.id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if (!$chk || $chk['ln'] !== 'Girona FC' || (int)($chk['goles_local']??-1) !== 1 || (int)($chk['goles_visitante']??-1) !== 3) {
        $pdo->exec("DELETE FROM partidos");
        try { $pdo->exec("ALTER TABLE partidos AUTO_INCREMENT = 1"); } catch(Exception $e) {}
        $needsReload = true;
    }
}
if ($needsReload) generarCalendario($pdo);

function generarCalendario($pdo) {
    // Mapa de códigos cortos a nombres en BD
    $map = [
        'RMA'=>'Real Madrid','BAR'=>'FC Barcelona','ATM'=>'Atlético de Madrid',
        'SEV'=>'Sevilla FC','BET'=>'Real Betis','VAL'=>'Valencia CF',
        'ATH'=>'Athletic Club','RSO'=>'Real Sociedad','VIL'=>'Villarreal CF',
        'OSA'=>'CA Osasuna','LEV'=>'Levante UD','GIR'=>'Girona FC',
        'ELC'=>'Elche CF','ESP'=>'RCD Espanyol','GET'=>'Getafe CF',
        'MAL'=>'RCD Mallorca','CEL'=>'RC Celta de Vigo','ALA'=>'Deportivo Alavés',
        'OVI'=>'Real Oviedo','RAY'=>'Rayo Vallecano'
    ];

    // Obtener IDs de equipos
    $ids = [];
    foreach ($pdo->query("SELECT id, nombre FROM equipos") as $r) $ids[$r['nombre']] = $r['id'];

    // Fechas base por jornada (sábado de cada jornada)
    $fechas = [
        1=>'2025-08-16',2=>'2025-08-23',3=>'2025-08-30',4=>'2025-09-13',
        5=>'2025-09-20',6=>'2025-09-27',7=>'2025-10-04',8=>'2025-10-18',
        9=>'2025-10-25',10=>'2025-11-01',11=>'2025-11-08',12=>'2025-11-22',
        13=>'2025-11-29',14=>'2025-12-06',15=>'2025-12-13',16=>'2025-12-20',
        17=>'2026-01-03',18=>'2026-01-10',19=>'2026-01-17',20=>'2026-01-24',
        21=>'2026-01-31',22=>'2026-02-07',23=>'2026-02-14',24=>'2026-02-21',
        25=>'2026-02-25',26=>'2026-02-28'
    ];
    $slots = ['14:00','16:15','18:30','21:00','21:00','14:00','16:15','18:30','21:00','21:00'];
    $dayOff = [0,0,0,0,0,1,1,1,1,1];

    // ====== RESULTADOS REALES - LaLiga 2025/2026 (fuente: marca.com) ======
    // Formato: [local, visitante, goles_local, goles_visitante]
    $played = [
        1 => [['GIR','RAY',1,3],['VIL','OVI',2,0],['MAL','BAR',0,3],['ALA','LEV',2,1],['VAL','RSO',1,1],
              ['CEL','GET',0,2],['ATH','SEV',3,2],['ESP','ATM',2,1],['ELC','BET',1,1],['RMA','OSA',1,0]],
        2 => [['BET','ALA',1,0],['MAL','CEL',1,1],['ATM','ELC',1,1],['LEV','BAR',2,3],['OSA','VAL',1,0],
              ['RSO','ESP',2,2],['VIL','GIR',5,0],['OVI','RMA',0,3],['ATH','RAY',1,0],['SEV','GET',1,2]],
        3 => [['ELC','LEV',2,0],['VAL','GET',3,0],['ALA','ATM',1,1],['OVI','RSO',1,0],['GIR','SEV',0,2],
              ['RMA','MAL',2,1],['CEL','VIL',1,1],['BET','ATH',1,2],['ESP','OSA',1,0],['RAY','BAR',1,1]],
        4 => [['SEV','ELC',2,2],['GET','OVI',2,0],['RSO','RMA',1,2],['ATH','ALA',0,1],['ATM','VIL',2,0],
              ['CEL','GIR',1,1],['LEV','BET',2,2],['OSA','RAY',2,0],['BAR','VAL',6,0],['ESP','MAL',3,2]],
        5 => [['BET','RSO',3,1],['GIR','LEV',0,4],['RMA','ESP',2,0],['ALA','SEV',1,2],['VIL','OSA',2,1],
              ['VAL','ATH',2,0],['RAY','CEL',1,1],['MAL','ATM',1,1],['ELC','OVI',1,0],['BAR','GET',3,0]],
        6 => [['CEL','BET',1,1],['ATH','GIR',1,1],['ESP','VAL',2,2],['LEV','RMA',1,4],['SEV','VIL',1,2],
              ['GET','ALA',1,1],['ATM','RAY',3,2],['RSO','MAL',1,0],['OSA','ELC',1,1],['OVI','BAR',1,3]],
        7 => [['GIR','ESP',0,0],['GET','LEV',1,1],['ATM','RMA',5,2],['MAL','ALA',1,0],['VIL','ATH',1,0],
              ['RAY','SEV',0,1],['ELC','CEL',2,1],['BAR','RSO',2,1],['BET','OSA',2,0],['VAL','OVI',1,2]],
        8 => [['OSA','GET',2,1],['OVI','LEV',0,2],['GIR','VAL',2,1],['ATH','MAL',2,1],['RMA','VIL',3,1],
              ['ALA','ELC',3,1],['SEV','BAR',4,1],['ESP','BET',1,2],['RSO','RAY',0,1],['CEL','ATM',1,1]],
        9 => [['OVI','ESP',0,2],['SEV','MAL',1,3],['BAR','GIR',2,1],['VIL','BET',2,2],['ATM','OSA',1,0],
              ['ELC','ATH',0,0],['CEL','RSO',1,1],['LEV','RAY',0,3],['GET','RMA',0,1],['ALA','VAL',0,0]],
        10 => [['RSO','SEV',2,1],['GIR','OVI',3,3],['ESP','ELC',1,0],['ATH','GET',0,1],['VAL','VIL',0,2],
               ['MAL','LEV',1,1],['RMA','BAR',2,1],['OSA','CEL',2,3],['RAY','ALA',1,0],['BET','ATM',0,2]],
        11 => [['GET','GIR',2,1],['VIL','RAY',4,0],['ATM','SEV',3,0],['RSO','ATH',3,2],['RMA','VAL',4,0],
               ['LEV','CEL',1,2],['ALA','ESP',2,1],['BAR','ELC',3,1],['BET','MAL',3,0],['OVI','OSA',0,0]],
        12 => [['ELC','RSO',1,1],['GIR','ALA',1,0],['SEV','OSA',1,0],['ATM','LEV',3,1],['ESP','VIL',0,2],
               ['ATH','OVI',1,0],['RAY','RMA',0,0],['MAL','GET',1,0],['VAL','BET',1,1],['CEL','BAR',2,4]],
        13 => [['VAL','LEV',1,0],['ALA','CEL',0,1],['BAR','ATH',4,0],['OSA','RSO',1,3],['VIL','MAL',2,1],
               ['OVI','RAY',0,0],['BET','GIR',1,1],['GET','ATM',0,1],['ELC','RMA',2,2],['ESP','SEV',2,1]],
        14 => [['GET','ELC',1,0],['MAL','OSA',2,2],['BAR','ALA',3,1],['LEV','ATH',0,2],['ATM','OVI',2,0],
               ['RSO','VIL',2,3],['SEV','BET',0,2],['CEL','ESP',0,1],['GIR','RMA',1,1],['RAY','VAL',1,1]],
        15 => [['OVI','MAL',0,0],['VIL','GET',2,0],['ALA','RSO',1,0],['BET','BAR',3,5],['ATH','ATM',1,0],
               ['ELC','GIR',3,0],['VAL','SEV',1,1],['ESP','RAY',1,0],['RMA','CEL',0,2],['OSA','LEV',2,0]],
        16 => [['RSO','GIR',1,2],['ATM','VAL',2,1],['MAL','ELC',3,1],['BAR','OSA',2,0],['GET','ESP',0,1],
               ['SEV','OVI',4,0],['CEL','ATH',2,0],['ALA','RMA',1,2],['RAY','BET',0,0],['LEV','VIL',0,1]],
        17 => [['VAL','MAL',1,1],['OVI','CEL',0,0],['LEV','RSO',1,1],['OSA','ALA',3,0],['RMA','SEV',2,0],
               ['GIR','ATM',0,3],['VIL','BAR',0,2],['ELC','RAY',4,0],['BET','GET',4,0],['ATH','ESP',1,2]],
        18 => [['RAY','GET',1,1],['CEL','VAL',4,1],['OSA','ATH',1,1],['ELC','VIL',1,3],['ESP','BAR',0,2],
               ['SEV','LEV',0,3],['RMA','BET',5,1],['ALA','OVI',1,1],['MAL','GIR',1,2],['RSO','ATM',1,1]],
        19 => [['BAR','ATM',3,1],['ATH','RMA',0,3],['GET','RSO',1,2],['OVI','BET',1,1],['VIL','ALA',3,1],
               ['GIR','OSA',1,0],['VAL','ELC',1,1],['RAY','MAL',2,1],['LEV','ESP',1,1],['SEV','CEL',0,1]],
        20 => [['ESP','GIR',0,2],['RMA','LEV',2,0],['MAL','ATH',3,2],['OSA','OVI',3,2],['BET','VIL',2,0],
               ['GET','VAL',0,1],['ATM','ALA',1,0],['CEL','RAY',3,0],['RSO','BAR',2,1],['ELC','SEV',2,2]],
        21 => [['LEV','ELC',3,2],['RAY','OSA',1,3],['VAL','ESP',3,2],['SEV','ATH',2,1],['VIL','RMA',0,2],
               ['ATM','MAL',3,0],['BAR','OVI',3,0],['RSO','CEL',3,1],['ALA','BET',2,1],['GIR','GET',1,1]],
        22 => [['ESP','ALA',1,2],['OVI','GIR',1,0],['OSA','VIL',2,2],['LEV','ATM',0,0],['ELC','BAR',1,3],
               ['RMA','RAY',2,1],['BET','VAL',2,1],['GET','CEL',0,0],['ATH','RSO',1,1],['MAL','SEV',4,1]],
        23 => [['CEL','OSA',1,2],['BAR','MAL',3,0],['RSO','ELC',3,1],['ALA','GET',0,2],['ATH','LEV',4,2],
               ['SEV','GIR',1,1],['ATM','BET',0,1],['VAL','RMA',0,2],['VIL','ESP',4,1],['RAY','OVI',3,0]],
        24 => [['ELC','OSA',0,0],['ESP','CEL',2,2],['GET','VIL',2,1],['SEV','ALA',1,1],['RMA','RSO',4,1],
               ['OVI','ATH',1,2],['RAY','ATM',3,0],['LEV','VAL',0,2],['MAL','BET',1,2],['GIR','BAR',2,1]],
        25 => [['ATH','ELC',2,1],['RSO','OVI',3,3],['BET','RAY',1,1],['OSA','RMA',2,1],['ATM','ESP',4,2],
               ['GET','SEV',0,1],['BAR','LEV',3,0],['CEL','MAL',2,0],['VIL','VAL',2,1],['ALA','GIR',2,2]],
        26 => [['LEV','ALA',2,0],['RAY','ATH',1,1],['BAR','VIL',4,1],['MAL','RSO',0,1],['OVI','ATM',0,1],
               ['ELC','ESP',2,2],['VAL','OSA',1,0],['BET','SEV',2,2],['GIR','CEL',1,2],['RMA','GET',0,1]],
    ];

    // ====== JORNADAS FUTURAS con fechas reales (fuente: marca.com) ======
    // Formato: [local, visitante, 'YYYY-MM-DD HH:MM:SS']
    $future = [
        27 => [['CEL','RMA','2026-03-06 21:00:00'],['OSA','MAL','2026-03-07 14:00:00'],['LEV','GIR','2026-03-07 16:15:00'],
               ['ATM','RSO','2026-03-07 18:30:00'],['ATH','BAR','2026-03-07 21:00:00'],['VIL','ELC','2026-03-08 14:00:00'],
               ['GET','BET','2026-03-08 16:15:00'],['SEV','RAY','2026-03-08 18:30:00'],['VAL','ALA','2026-03-08 21:00:00'],
               ['ESP','OVI','2026-03-09 21:00:00']],
        28 => [['ALA','VIL','2026-03-13 21:00:00'],['GIR','ATH','2026-03-14 14:00:00'],['ATM','GET','2026-03-14 16:15:00'],
               ['OVI','VAL','2026-03-14 18:30:00'],['RMA','ELC','2026-03-14 21:00:00'],['MAL','ESP','2026-03-15 14:00:00'],
               ['BAR','SEV','2026-03-15 16:15:00'],['BET','CEL','2026-03-15 18:30:00'],['RSO','OSA','2026-03-15 21:00:00'],
               ['RAY','LEV','2026-03-16 21:00:00']],
        29 => [['VIL','RSO','2026-03-20 21:00:00'],['ELC','MAL','2026-03-21 14:00:00'],['ESP','GET','2026-03-21 16:15:00'],
               ['LEV','OVI','2026-03-21 18:30:00'],['OSA','GIR','2026-03-21 18:30:00'],['SEV','VAL','2026-03-21 21:00:00'],
               ['BAR','RAY','2026-03-22 14:00:00'],['CEL','ALA','2026-03-22 16:15:00'],['ATH','BET','2026-03-22 18:30:00'],
               ['RMA','ATM','2026-03-22 21:00:00']],
        30 => [['ALA','OSA','2026-04-05 17:00:00'],['ATM','BAR','2026-04-05 17:00:00'],['GET','ATH','2026-04-05 17:00:00'],
               ['GIR','VIL','2026-04-05 17:00:00'],['MAL','RMA','2026-04-05 17:00:00'],['RAY','ELC','2026-04-05 17:00:00'],
               ['BET','ESP','2026-04-05 17:00:00'],['OVI','SEV','2026-04-05 17:00:00'],['RSO','LEV','2026-04-05 17:00:00'],
               ['VAL','CEL','2026-04-05 17:00:00']],
        31 => [['ATH','VIL','2026-04-12 17:00:00'],['BAR','ESP','2026-04-12 17:00:00'],['CEL','OVI','2026-04-12 17:00:00'],
               ['ELC','VAL','2026-04-12 17:00:00'],['LEV','GET','2026-04-12 17:00:00'],['MAL','RAY','2026-04-12 17:00:00'],
               ['OSA','BET','2026-04-12 17:00:00'],['RMA','GIR','2026-04-12 17:00:00'],['RSO','ALA','2026-04-12 17:00:00'],
               ['SEV','ATM','2026-04-12 17:00:00']],
        32 => [['ALA','MAL','2026-04-26 17:00:00'],['ATM','ATH','2026-04-26 17:00:00'],['ESP','LEV','2026-04-26 17:00:00'],
               ['GET','BAR','2026-04-26 17:00:00'],['OSA','SEV','2026-04-26 17:00:00'],['RAY','RSO','2026-04-26 17:00:00'],
               ['BET','RMA','2026-04-26 17:00:00'],['OVI','ELC','2026-04-26 17:00:00'],['VAL','GIR','2026-04-26 17:00:00'],
               ['VIL','CEL','2026-04-26 17:00:00']],
        33 => [['ATH','OSA','2026-04-22 21:00:00'],['BAR','CEL','2026-04-22 21:00:00'],['ELC','ATM','2026-04-22 21:00:00'],
               ['GIR','BET','2026-04-22 21:00:00'],['LEV','SEV','2026-04-22 21:00:00'],['MAL','VAL','2026-04-22 21:00:00'],
               ['RAY','ESP','2026-04-22 21:00:00'],['RMA','ALA','2026-04-22 21:00:00'],['OVI','VIL','2026-04-22 21:00:00'],
               ['RSO','GET','2026-04-22 21:00:00']],
        34 => [['ALA','ATH','2026-05-03 17:00:00'],['CEL','ELC','2026-05-03 17:00:00'],['ESP','RMA','2026-05-03 17:00:00'],
               ['GET','RAY','2026-05-03 17:00:00'],['GIR','MAL','2026-05-03 17:00:00'],['OSA','BAR','2026-05-03 17:00:00'],
               ['BET','OVI','2026-05-03 17:00:00'],['SEV','RSO','2026-05-03 17:00:00'],['VAL','ATM','2026-05-03 17:00:00'],
               ['VIL','LEV','2026-05-03 17:00:00']],
        35 => [['ATH','VAL','2026-05-10 17:00:00'],['ATM','CEL','2026-05-10 17:00:00'],['BAR','RMA','2026-05-10 17:00:00'],
               ['ELC','ALA','2026-05-10 17:00:00'],['LEV','OSA','2026-05-10 17:00:00'],['MAL','VIL','2026-05-10 17:00:00'],
               ['RAY','GIR','2026-05-10 17:00:00'],['OVI','GET','2026-05-10 17:00:00'],['RSO','BET','2026-05-10 17:00:00'],
               ['SEV','ESP','2026-05-10 17:00:00']],
        36 => [['ALA','BAR','2026-05-13 21:00:00'],['CEL','LEV','2026-05-13 21:00:00'],['ESP','ATH','2026-05-13 21:00:00'],
               ['GET','MAL','2026-05-13 21:00:00'],['GIR','RSO','2026-05-13 21:00:00'],['OSA','ATM','2026-05-13 21:00:00'],
               ['BET','ELC','2026-05-13 21:00:00'],['RMA','OVI','2026-05-13 21:00:00'],['VAL','RAY','2026-05-13 21:00:00'],
               ['VIL','SEV','2026-05-13 21:00:00']],
        37 => [['ATH','CEL','2026-05-17 17:00:00'],['ATM','GIR','2026-05-17 17:00:00'],['BAR','BET','2026-05-17 17:00:00'],
               ['ELC','GET','2026-05-17 17:00:00'],['LEV','MAL','2026-05-17 17:00:00'],['OSA','ESP','2026-05-17 17:00:00'],
               ['RAY','VIL','2026-05-17 17:00:00'],['OVI','ALA','2026-05-17 17:00:00'],['RSO','VAL','2026-05-17 17:00:00'],
               ['SEV','RMA','2026-05-17 17:00:00']],
        38 => [['ALA','RAY','2026-05-24 17:00:00'],['CEL','SEV','2026-05-24 17:00:00'],['ESP','RSO','2026-05-24 17:00:00'],
               ['GET','OSA','2026-05-24 17:00:00'],['GIR','ELC','2026-05-24 17:00:00'],['MAL','OVI','2026-05-24 17:00:00'],
               ['BET','LEV','2026-05-24 17:00:00'],['RMA','ATH','2026-05-24 17:00:00'],['VAL','BAR','2026-05-24 17:00:00'],
               ['VIL','ATM','2026-05-24 17:00:00']],
    ];

    $ins = $pdo->prepare("INSERT INTO partidos (jornada,equipo_local_id,equipo_visitante_id,fecha_hora,goles_local,goles_visitante) VALUES(?,?,?,?,?,?)");

    // Insertar partidos jugados (J1-J26) con resultados reales
    foreach ($played as $j => $matches) {
        $base = new DateTime($fechas[$j]);
        foreach ($matches as $i => $m) {
            $dt = clone $base;
            $dt->modify("+{$dayOff[$i]} days");
            $dateStr = $dt->format('Y-m-d') . ' ' . $slots[$i] . ':00';
            $localId = $ids[$map[$m[0]]] ?? null;
            $awayId = $ids[$map[$m[1]]] ?? null;
            if ($localId && $awayId) $ins->execute([$j, $localId, $awayId, $dateStr, $m[2], $m[3]]);
        }
    }

    // Insertar partidos futuros (J27-J38) sin resultados
    foreach ($future as $j => $matches) {
        foreach ($matches as $m) {
            $localId = $ids[$map[$m[0]]] ?? null;
            $awayId = $ids[$map[$m[1]]] ?? null;
            if ($localId && $awayId) $ins->execute([$j, $localId, $awayId, $m[2], null, null]);
        }
    }
}

// Consultar jornada actual
if (!isset($_GET['jornada'])) {
    $auto = $pdo->prepare("SELECT jornada FROM partidos WHERE DATE(fecha_hora)>=? ORDER BY fecha_hora LIMIT 1");
    $auto->execute([date('Y-m-d')]);
    $jornada = (int)$auto->fetchColumn() ?: 1;
} else {
    $jornada = max(1, min(38, (int)$_GET['jornada']));
}

$stmt = $pdo->prepare("SELECT p.*, el.nombre AS local_nombre, ev.nombre AS visitante_nombre
    FROM partidos p JOIN equipos el ON p.equipo_local_id=el.id JOIN equipos ev ON p.equipo_visitante_id=ev.id
    WHERE p.jornada=? ORDER BY p.fecha_hora, p.id");
$stmt->execute([$jornada]);
$partidos = $stmt->fetchAll(PDO::FETCH_OBJ);

function calendario_estado_en_directo(string $estadoPartido): bool {
    $estado = strtolower(trim($estadoPartido));
    if ($estado === '') {
        return false;
    }

    $tokensFinalizado = [
        'finished',
        'match finished',
        'full time',
        'ft',
        'aet',
        'after extra time',
        'pen',
        'after penalties',
        'finalizado',
        'final',
        'ended',
    ];
    foreach ($tokensFinalizado as $token) {
        if (str_contains($estado, $token)) {
            return false;
        }
    }

    $tokensDirecto = [
        'live',
        'in play',
        '1h',
        '2h',
        '1st half',
        '2nd half',
        'halftime',
        'ht',
        'extra time',
        'penalty',
    ];
    foreach ($tokensDirecto as $token) {
        if (str_contains($estado, $token)) {
            return true;
        }
    }

    return false;
}

$hayPartidoEnDirecto = false;
foreach ($partidos as $partidoCheck) {
    $estadoCheck = strtolower((string)($partidoCheck->estado_partido ?? ''));
    $enDirectoCheck = $partidoCheck->goles_local !== null && calendario_estado_en_directo($estadoCheck);
    if ($enDirectoCheck) {
        $hayPartidoEnDirecto = true;
        break;
    }
}

$autoLiveHabilitado = true;
$autoLiveSegundos = $hayPartidoEnDirecto ? 45 : 120;

// Agrupar por día
$dias_es = ['Monday'=>'Lunes','Tuesday'=>'Martes','Wednesday'=>'Miércoles','Thursday'=>'Jueves',
            'Friday'=>'Viernes','Saturday'=>'Sábado','Sunday'=>'Domingo'];
$meses_es = [1=>'enero',2=>'febrero',3=>'marzo',4=>'abril',5=>'mayo',6=>'junio',
             7=>'julio',8=>'agosto',9=>'septiembre',10=>'octubre',11=>'noviembre',12=>'diciembre'];
$hoy_str = date('Y-m-d');
$grupos = [];
foreach ($partidos as $p) {
    $dt = new DateTime($p->fecha_hora);
    $k = $dt->format('Y-m-d');
    if (!isset($grupos[$k])) {
        $grupos[$k] = [
            'label' => $dias_es[$dt->format('l')].', '.$dt->format('j').' de '.$meses_es[(int)$dt->format('n')].' de '.$dt->format('Y'),
            'partidos' => []
        ];
    }
    $grupos[$k]['partidos'][] = $p;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendario J<?= $jornada ?> - LaLiga Fantasy</title>
    <link rel="icon" type="image/png" href="<?= theme_logo_url($clase_tema) ?>">
    <link rel="stylesheet" href="<?= BASE_URL ?>/css/inicio.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/css/calendario.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/css/cookie_tema.css?v=20260315-1">
</head>
<body class="<?= $clase_tema ?>">

<div class="navegacion"><nav>
    <a href="<?= BASE_URL ?>/inicio.php">Inicio</a>
    <a href="<?= BASE_URL ?>/pages/equipos.php">Equipos</a>
    <a href="<?= BASE_URL ?>/pages/calendario.php" class="nav-active">Calendario</a>
    <a href="<?= BASE_URL ?>/pages/plantilla.php">Plantilla</a>
    <a href="<?= BASE_URL ?>/pages/mercado.php">Mercado</a>
    <a href="<?= BASE_URL ?>/pages/noticias.php">Noticias</a>
    <a href="<?= BASE_URL ?>/logout.php">Cerrar Sesión</a>
</nav></div>

<div class="calendario-header">
    <h1><img src="<?= theme_logo_url($clase_tema) ?>" onerror="this.onerror=null;this.src='<?= asset_url('images/favicon.png') ?>';" alt="" style="height:1em;vertical-align:middle;margin-right:8px">Calendario de Partidos</h1>
    <p class="calendario-subtitulo">Temporada 2025 / 2026</p>
    <form method="post" class="calendario-actualizar-form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="jornada" value="<?= $jornada ?>">
        <button type="submit" name="actualizar_resultados" value="1" class="calendario-btn-actualizar">Actualizar resultados</button>
    </form>
    <?php if ($mensajeActualizacion !== ''): ?>
        <p class="calendario-actualizacion-msg <?= $tipoActualizacion ?>"><?= htmlspecialchars($mensajeActualizacion) ?></p>
    <?php endif; ?>
</div>

<div class="jornadas-pagination">
    <a href="?jornada=<?= $jornada-1 ?>" class="prev-next<?= $jornada<=1?' disabled':'' ?>">◀</a>
    <?php for($j=1;$j<=38;$j++) echo $j==$jornada ? "<span class=\"active\">$j</span>" : "<a href=\"?jornada=$j\">$j</a>"; ?>
    <a href="?jornada=<?= $jornada+1 ?>" class="prev-next<?= $jornada>=38?' disabled':'' ?>">▶</a>
</div>

<h2 class="jornada-titulo">Jornada <?= $jornada ?></h2>

<div class="partidos-container">
<?php if (empty($partidos)): ?>
    <p class="calendario-vacio">No hay partidos para esta jornada.</p>
<?php else: foreach ($grupos as $dia): ?>
    <div class="partido-fecha-grupo">
        <div class="partido-fecha-label"><span><?= htmlspecialchars($dia['label']) ?></span></div>
        <?php foreach ($dia['partidos'] as $p):
            $dt = new DateTime($p->fecha_hora);
            $estado = strtolower((string)($p->estado_partido ?? ''));
            $enDirecto = $p->goles_local !== null && calendario_estado_en_directo($estado);
            $jugado = $p->goles_local !== null && !$enDirecto;
            $hoy = $dt->format('Y-m-d') === $hoy_str;
            $eL = isset($escudos[$p->local_nombre]) ? BASE_URL . '/images/escudos/'.$escudos[$p->local_nombre] : BASE_URL . '/images/favicon.png';
            $eV = isset($escudos[$p->visitante_nombre]) ? BASE_URL . '/images/escudos/'.$escudos[$p->visitante_nombre] : BASE_URL . '/images/favicon.png';
        ?>
        <div class="partido-card<?= $jugado?' jugado':($enDirecto?' hoy':($hoy?' hoy':'')) ?>">
            <div class="partido-local">
                <span class="partido-equipo-nombre"><?= htmlspecialchars($p->local_nombre) ?></span>
                <div class="partido-escudo"><img src="<?= $eL ?>" alt="" loading="lazy"></div>
            </div>
            <div class="partido-vs">
            <?php if ($jugado): ?>
                <span class="partido-resultado"><?= $p->goles_local ?> - <?= $p->goles_visitante ?></span>
                <span class="partido-badge finalizado">Finalizado</span>
            <?php elseif ($enDirecto): ?>
                <span class="partido-resultado"><?= $p->goles_local ?> - <?= $p->goles_visitante ?></span>
                <span class="partido-badge badge-hoy">En directo</span>
            <?php elseif ($hoy): ?>
                <span class="partido-hora"><?= $dt->format('H:i') ?></span>
                <span class="partido-badge badge-hoy">Hoy</span>
            <?php else: ?>
                <span class="partido-hora"><?= $dt->format('H:i') ?></span>
                <span class="partido-vs-label">vs</span>
            <?php endif; ?>
            </div>
            <div class="partido-visitante">
                <div class="partido-escudo"><img src="<?= $eV ?>" alt="" loading="lazy"></div>
                <span class="partido-equipo-nombre"><?= htmlspecialchars($p->visitante_nombre) ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endforeach; endif; ?>
</div>

<div class="widget-temas">
    <button type="button" onclick="__llSetTema('tema-laliga')" title="Modo LaLiga">🔴</button>
    <button type="button" onclick="__llSetTema('tema-original')" title="Modo Azul">🔵</button>
</div>
<script src="<?= BASE_URL ?>/js/tema.js"></script>

<?php if ($autoLiveHabilitado): ?>
<script>
const CAL_BASE_URL = <?= json_encode(BASE_URL) ?>;
const CAL_CSRF = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;
const CAL_JORNADA = <?= (int)$jornada ?>;
const CAL_INTERVAL_MS = <?= (int)$autoLiveSegundos * 1000 ?>;
let calSyncInFlight = false;

function calendarioAutoSyncTick() {
    if (calSyncInFlight) return;
    calSyncInFlight = true;

    fetch(CAL_BASE_URL + '/live_sync.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ csrf: CAL_CSRF, jornada: CAL_JORNADA, cooldown: 90 })
    })
    .then(r => r.json())
    .then(d => {
        if (d && d.ok && d.synced) {
            location.reload();
        }
    })
    .catch(() => {})
    .finally(() => {
        calSyncInFlight = false;
    });
}

setInterval(calendarioAutoSyncTick, CAL_INTERVAL_MS);
</script>
<?php endif; ?>

</body>
</html>
