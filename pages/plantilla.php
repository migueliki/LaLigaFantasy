<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../app/conexion.php';

include '../app/plantilla/insert.php';
include '../app/plantilla/modify.php';
include '../app/plantilla/delete.php';

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plantilla</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <script src="https://kit.fontawesome.com/d3b044a253.js" crossorigin="anonymous"></script>
</head>
<body>

<h1>Mi plantilla</h1>

<div class="container-fluid row">

<form class="col-4" method="POST">
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
        <a href="plantilla.php?ID=<?= $datos->ID ?>" class="btn btn-warning"><i class="fa-solid fa-pen-to-square"></i></a>
        <a href="delete.php?ID=<?= $datos->ID ?>" class="btn btn-small btn-danger"><i class="fa-solid fa-trash"></i></a>
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