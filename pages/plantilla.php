<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: /app/index.php'); // o login
    exit;
}
// Control de timeout por inactividad
$timeout = 3600; // 1 hora
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
    session_unset(); session_destroy();
    header('Location: /app/index.php?timeout=1');
    exit;
}
$_SESSION['last_activity'] = time(); // actualizar actividad

require_once '../app/conexion.php';
require_once '../app/csrf.php';
include_once '../app/plantilla/insert.php';

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plantilla</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="shortcut icon" href="../images/favicon.png" type="image/x-icon">
    <script src="https://kit.fontawesome.com/d3b044a253.js" crossorigin="anonymous"></script>
</head>
<body>

<h1>Mi plantilla</h1>

<div class="container-fluid row">

<form class="col-4" method="POST">
    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
<h3>Registrar de jugadores</h3>
<div class="mb-3">
    <label for="exampleInputEmail1" class="form-label">Nombre de jugador</label>
    <input type="text" class="form-control" name="nombre">
</div>

<div class="mb-3">
    <label for="exampleInputEmail1" class="form-label">Equipo</label>
    <input type="text" class="form-control" name="equipo">
</div>

<div class="mb-3">
    <label for="exampleInputEmail1" class="form-label">Posición</label>
    <input type="text" class="form-control" name="posicion">
</div>

<div class="mb-3">
    <label for="exampleInputEmail1" class="form-label">Nacionalidad</label>
    <input type="text" class="form-control" name="nacionalidad">
</div>

<div class="mb-3">
    <label for="exampleInputEmail1" class="form-label">Dorsal</label>
    <input type="number" class="form-control" name="dorsal">
</div>

<button type="submit" class="btn btn-primary">Insertar</button>
</form>

<div class="col-8 p-4">
<table class="table">
<thead>
    <tr>
        <th scope="col">Nombre</th>
        <th scope="col">Equipo</th>
        <th scope="col">Posición</th>
        <th scope="col">Nacionalidad</th>
        <th scope="col">Dorsal</th>
        <th scope="col"></th>
    </tr>
</thead>
<tbody>
    <tr>
    
    <?php 
        $sql = $pdo->query(" SELECT * FROM plantilla ");
        while($datos = $sql->fetch(PDO::FETCH_OBJ))  { ?>
    
    <td><?= $datos->nombre ?></td>
    <td><?= $datos->equipo ?></td>
    <td><?= $datos->posicion ?></td>
    <td><?= $datos->nacionalidad ?></td>
    <td><?= $datos->dorsal ?></td>
    <td>
        <a href="../app/plantilla/delete.php?id=<?= isset($datos->id) ? $datos->id : (isset($datos->ID) ? $datos->ID : '') ?>" class="btn btn-small btn-danger"><i class="fa-solid fa-trash"></i></a>
    </td>
    </tr>


<?php } ?>
        </tbody>
    </table>
</div>


</div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
</body>
</html>