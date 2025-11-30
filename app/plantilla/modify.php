<?php
require_once '../app/conexion.php';

if (isset($_GET['editar'])): ?>
    <form method="POST" action="modify.php">
        <h3>Editar jugador</h3>
        <input type="hidden" name="nombre_original" value="<?= $_GET['editar'] ?>">
        
        <div class="mb-3">
            <label>Nombre original</label>
            <input type="text" class="form-control" name="nombre_original">
        </div> 
        
        <div class="mb-3">
            <label>Nuevo nombre</label>
            <input type="text" class="form-control" name="nombre">
        </div>
        
        <div class="mb-3">
            <label>Equipo</label>
            <input type="text" class="form-control" name="equipo">
        </div>
        
        <div class="mb-3">
            <label>Posición</label>
            <input type="text" class="form-control" name="posicion">
        </div>
        
        <div class="mb-3">
            <label>Nacionalidad</label>
            <input type="text" class="form-control" name="nacionalidad">
        </div>
        
        <div class="mb-3">
            <label>Dorsal</label>
            <input type="number" class="form-control" name="dorsal">
        </div>

        <button type="submit" class="btn btn-success">Actualizar</button>
    </form>
<?php endif; 

// Validación en el server
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $original = $_POST['nombre_original'];
    $nombre = $_POST['nombre'];
    $equipo = $_POST['equipo'];
    $posicion = $_POST['posicion'];
    $nacionalidad = $_POST['nacionalidad'];
    $dorsal = $_POST['dorsal'];

    $stmt = $pdo->prepare("UPDATE plantilla SET nombre = ?, equipo = ?, posicion = ?, nacionalidad = ?, dorsal = ? WHERE nombre = ?");
    $stmt->execute([$nombre, $equipo, $posicion, $nacionalidad, $dorsal, $original]);

    header("Location: plantilla.php");
    exit;
}
?>