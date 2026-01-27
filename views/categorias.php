<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../includes/config.php';
session_start();

// Seguridad
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$es_admin = ($_SESSION['role'] === 'admin');
$conn = getConnection();

/* ===============================
   MANEJO POST (PRG)
================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $es_admin) {
    $descripcion = trim($_POST['descripcion']);

    if (!empty($_POST['category_id'])) {
        // UPDATE
        $stmt = $conn->prepare("UPDATE categorias SET descripcion=? WHERE category_id=?");
        $stmt->bind_param("si", $descripcion, $_POST['category_id']);
        $stmt->execute();
        $_SESSION['swal'] = [
            'title' => 'Actualizado',
            'text'  => 'Categoría actualizada correctamente',
            'icon'  => 'success'
        ];
    } else {
        // INSERT
        $stmt = $conn->prepare("INSERT INTO categorias (descripcion) VALUES (?)");
        $stmt->bind_param("s", $descripcion);
        $stmt->execute();
        $_SESSION['swal'] = [
            'title' => 'Creado',
            'text'  => 'Categoría creada correctamente',
            'icon'  => 'success'
        ];
    }

    header("Location: categorias.php");
    exit();
}

/* ===============================
   ELIMINAR (ADMIN)
================================ */
if (isset($_POST['eliminar']) && $es_admin) {
    $id = (int)$_POST['eliminar'];

    $check = $conn->query("SELECT COUNT(*) total FROM productos WHERE category_id=$id")->fetch_assoc();
    if ($check['total'] > 0) {
        $_SESSION['swal'] = [
            'title' => 'No permitido',
            'text'  => 'La categoría tiene productos asociados',
            'icon'  => 'warning'
        ];
    } else {
        $stmt = $conn->prepare("DELETE FROM categorias WHERE category_id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $_SESSION['swal'] = [
            'title' => 'Eliminado',
            'text'  => 'Categoría eliminada correctamente',
            'icon'  => 'success'
        ];
    }

    header("Location: categorias.php");
    exit();
}

/* ===============================
   EDITAR
================================ */
$editando = false;
$categoria = null;

if (isset($_GET['editar']) && $es_admin) {
    $editando = true;
    $stmt = $conn->prepare("SELECT * FROM categorias WHERE category_id=?");
    $stmt->bind_param("i", $_GET['editar']);
    $stmt->execute();
    $categoria = $stmt->get_result()->fetch_assoc();
}

/* ===============================
   LISTADO
================================ */
$sql = "SELECT c.*, COUNT(p.product_id) total
        FROM categorias c
        LEFT JOIN productos p ON c.category_id=p.category_id
        GROUP BY c.category_id
        ORDER BY c.category_id DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Categorías | Bike Store</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<link href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css" rel="stylesheet">

<style>
@media (max-width: 768px){
    .btn-sm{margin-bottom:4px}
}
</style>
</head>

<body>
<?php include '../includes/navbar_admin.php'; ?>

<div class="container my-4">
<div class="row">
<?php if($es_admin): ?>
<div class="col-lg-4 mb-3">
<div class="card shadow">
<div class="card-header bg-success text-white">
<?= $editando?'Editar':'Nueva' ?> categoría
</div>
<div class="card-body">
<form method="POST">
<?php if($editando): ?>
<input type="hidden" name="category_id" value="<?= $categoria['category_id'] ?>">
<?php endif; ?>
<input class="form-control mb-3" name="descripcion" required
value="<?= $editando?$categoria['descripcion']:'' ?>"
placeholder="Nombre de categoría">
<button class="btn btn-success w-100">Guardar</button>
<?php if($editando): ?>
<a href="categorias.php" class="btn btn-secondary w-100 mt-2">Cancelar</a>
<?php endif; ?>
</form>
</div>
</div>
</div>
<?php endif; ?>

<div class="<?= $es_admin?'col-lg-8':'col-12' ?>">
<div class="card shadow">
<div class="card-header bg-dark text-white">Listado</div>
<div class="card-body">
<table id="tabla" class="table table-striped nowrap" style="width:100%">
<thead>
<tr>
<th>ID</th>
<th>Descripción</th>
<th>Productos</th>
<th>Fecha</th>
<?php if($es_admin): ?><th>Acciones</th><?php endif; ?>
</tr>
</thead>
<tbody>
<?php while($r=$result->fetch_assoc()): ?>
<tr>
<td><?= $r['category_id'] ?></td>
<td><?= htmlspecialchars($r['descripcion']) ?></td>
<td><span class="badge bg-primary"><?= $r['total'] ?></span></td>
<td><?= date('d/m/Y',strtotime($r['created_at'])) ?></td>
<?php if($es_admin): ?>
<td>
<a href="?editar=<?= $r['category_id'] ?>" class="btn btn-warning btn-sm">
<i class="bi bi-pencil"></i>
</a>
<form method="POST" class="d-inline eliminar-form">
<input type="hidden" name="eliminar" value="<?= $r['category_id'] ?>">
<button type="button" class="btn btn-danger btn-sm btn-eliminar">
<i class="bi bi-trash"></i>
</button>
</form>
</td>
<?php endif; ?>
</tr>
<?php endwhile; ?>
</tbody>
</table>
</div>
</div>
</div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.0/dist/jquery.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>

<script>
$(function(){
$('#tabla').DataTable({
responsive:true,
language:{url:'//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'}
});

$('.btn-eliminar').click(function(){
let form=$(this).closest('form');
Swal.fire({
title:'¿Eliminar?',
text:'Esta acción no se puede deshacer',
icon:'warning',
showCancelButton:true,
confirmButtonText:'Sí, eliminar',
cancelButtonText:'Cancelar'
}).then(r=>{ if(r.isConfirmed) form.submit(); });
});
});
</script>

<?php if(isset($_SESSION['swal'])): ?>
<script>
Swal.fire(<?= json_encode($_SESSION['swal']) ?>);
</script>
<?php unset($_SESSION['swal']); endif; ?>

</body>
</html>
<?php $conn->close(); ?>
