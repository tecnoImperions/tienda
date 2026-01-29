<?php
require_once '../includes/config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$es_admin = ($_SESSION['role'] === 'admin');
$conn = getConnection();

/* ===============================
   UPLOAD
================================ */
$upload_dir = '../uploads/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

/* ===============================
   POST (PRG)
================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $es_admin) {

    $_SESSION['guardado'] = true; // 🔑 CLAVE

    /* ===== ELIMINAR ===== */
    if (isset($_POST['eliminar'])) {
        $id = (int)$_POST['eliminar'];

        $foto = $conn->query("SELECT foto FROM productos WHERE product_id=$id")->fetch_assoc();
        $stmt = $conn->prepare("DELETE FROM productos WHERE product_id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();

        if (!empty($foto['foto']) && file_exists($foto['foto'])) {
            unlink($foto['foto']);
        }

        $_SESSION['swal'] = [
            'title'=>'Eliminado',
            'text'=>'Producto eliminado correctamente',
            'icon'=>'success'
        ];
        header("Location: productos.php");
        exit();
    }

    /* ===== CREAR / EDITAR ===== */
    $nombre = trim($_POST['product_name']);
    $anio   = $_POST['model_year'];
    $precio = $_POST['price'];
    $cat    = $_POST['category_id'] ?: null;
    $foto   = $_POST['foto_anterior'] ?? '';

    if (!empty($_FILES['foto']['name'])) {
        $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','webp','gif'])) {
            $nuevo = uniqid().'.'.$ext;
            move_uploaded_file($_FILES['foto']['tmp_name'], $upload_dir.$nuevo);
            if ($foto && file_exists($foto)) unlink($foto);
            $foto = $upload_dir.$nuevo;
        }
    }

    if (!empty($_POST['product_id'])) {
        $stmt = $conn->prepare("UPDATE productos 
            SET product_name=?, foto=?, model_year=?, price=?, category_id=?
            WHERE product_id=?");
        $stmt->bind_param("ssiddi",$nombre,$foto,$anio,$precio,$cat,$_POST['product_id']);
        $stmt->execute();

        $_SESSION['swal']=[
            'title'=>'Actualizado',
            'text'=>'Producto actualizado correctamente',
            'icon'=>'success'
        ];
    } else {
        $stmt = $conn->prepare("INSERT INTO productos
            (product_name,foto,model_year,price,category_id)
            VALUES (?,?,?,?,?)");
        $stmt->bind_param("ssidi",$nombre,$foto,$anio,$precio,$cat);
        $stmt->execute();

        $_SESSION['swal']=[
            'title'=>'Creado',
            'text'=>'Producto registrado correctamente',
            'icon'=>'success'
        ];
    }

    header("Location: productos.php");
    exit();
}

/* ===============================
   EDITAR
================================ */
$editando = false;
$producto = null;

if (isset($_GET['editar']) && $es_admin) {
    $editando = true;
    $_SESSION['en_edicion'] = true; // 🔑 CLAVE

    $stmt = $conn->prepare("SELECT * FROM productos WHERE product_id=?");
    $stmt->bind_param("i", $_GET['editar']);
    $stmt->execute();
    $producto = $stmt->get_result()->fetch_assoc();
}

/* ===============================
   DATOS
================================ */
$productos = $conn->query("
    SELECT p.*, c.descripcion categoria
    FROM productos p
    LEFT JOIN categorias c ON p.category_id=c.category_id
    ORDER BY p.product_id DESC
");

$categorias = $conn->query("SELECT * FROM categorias ORDER BY descripcion");
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Productos | Bike Store</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<link href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css" rel="stylesheet">

<style>
.product-img{width:60px;height:60px;object-fit:cover;border-radius:8px;cursor:pointer}
.modal-img{max-width:100%;border-radius:12px}
</style>
</head>

<body>


<?php include '../includes/navbar_admin.php'; ?>

<div class="container my-4">
<div class="row">

<?php if($es_admin): ?>
<div class="col-lg-4 mb-3">
<div class="card shadow">
<div class="card-header bg-primary text-white">
<?= $editando?'Editar':'Nuevo' ?> producto
</div>
<div class="card-body">
<form method="POST" enctype="multipart/form-data">
<?php if($editando): ?>
<input type="hidden" name="product_id" value="<?= $producto['product_id'] ?>">
<input type="hidden" name="foto_anterior" value="<?= $producto['foto'] ?>">
<?php endif; ?>

<input class="form-control mb-2" name="product_name" required
value="<?= $editando?$producto['product_name']:'' ?>" placeholder="Nombre">

<input type="number" class="form-control mb-2" name="model_year"
value="<?= $editando?$producto['model_year']:date('Y') ?>">

<input type="number" step="0.01" class="form-control mb-2" name="price" required
value="<?= $editando?$producto['price']:'' ?>" placeholder="Precio Bs">

<select class="form-select mb-2" name="category_id">
<option value="">Sin categoría</option>
<?php while($c=$categorias->fetch_assoc()): ?>
<option value="<?= $c['category_id'] ?>"
<?= $editando && $producto['category_id']==$c['category_id']?'selected':'' ?>>
<?= $c['descripcion'] ?>
</option>
<?php endwhile; ?>
</select>

<input type="file" class="form-control mb-2" name="foto" accept="image/*">

<button class="btn btn-primary w-100">Guardar</button>
<?php if($editando): ?>
<a href="productos.php" class="btn btn-secondary w-100 mt-2">Cancelar</a>
<?php endif; ?>
</form>
</div>
</div>
</div>
<?php endif; ?>

<div class="<?= $es_admin?'col-lg-8':'col-12' ?>">
<div class="card shadow">
<div class="card-header bg-primary text-white">Listado de productos</div>
<div class="card-body">
<table id="tabla" class="table table-striped nowrap">
<thead>
<tr>
<th>ID</th>
<th>Foto</th>
<th>Nombre</th>
<th>Año</th>
<th>Precio</th>
<th>Categoría</th>
<?php if($es_admin): ?><th>Acciones</th><?php endif; ?>
</tr>
</thead>
<tbody>
<?php while($p=$productos->fetch_assoc()): ?>
<tr>
<td><?= $p['product_id'] ?></td>
<td>
<?php if($p['foto'] && file_exists($p['foto'])): ?>
<img src="<?= $p['foto'] ?>" class="product-img view-img">
<?php endif; ?>
</td>
<td><?= htmlspecialchars($p['product_name']) ?></td>
<td><?= $p['model_year'] ?></td>
<td><strong>Bs <?= number_format($p['price'],2) ?></strong></td>
<td><?= $p['categoria'] ?></td>
<?php if($es_admin): ?>
<td>
<a href="?editar=<?= $p['product_id'] ?>" class="btn btn-warning btn-sm">
<i class="bi bi-pencil"></i>
</a>
<form method="POST" class="d-inline">
<input type="hidden" name="eliminar" value="<?= $p['product_id'] ?>">
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

<!-- MODAL IMAGEN -->
<div class="modal fade" id="imgModal" tabindex="-1">
<div class="modal-dialog modal-dialog-centered modal-lg">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title">Vista previa</h5>
<button class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body text-center">
<img id="modalImage" class="modal-img">
</div>
</div>
</div>
</div>


<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.0/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>

<script>
$(function(){

$('#tabla').DataTable({
responsive:true,
pageLength: 5,
language:{url:'//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'}
});

/* MODAL IMAGEN */
$('.view-img').click(function(){
$('#modalImage').attr('src', $(this).attr('src'));
new bootstrap.Modal('#imgModal').show();
});

/* SALIR DE EDICIÓN (BACK o CANCELAR) */
<?php if(isset($_SESSION['en_edicion']) && empty($_SESSION['guardado'])): ?>
history.replaceState(null,null,'productos.php');
window.addEventListener('popstate',()=>{
Swal.fire({
title:'Has salido del modo edición',
text:'Los cambios no guardados se descartaron',
icon:'info',
confirmButtonText:'Entendido'
});
});
<?php unset($_SESSION['en_edicion']); endif; ?>

});

/* ELIMINAR CON SWEETALERT */
$('.btn-eliminar').on('click', function () {

    let form = $(this).closest('form');

    Swal.fire({
        title: '¿Eliminar producto?',
        text: 'Esta acción no se puede deshacer',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            form.submit();
        }
    });
});

</script>

<?php if(isset($_SESSION['swal'])): ?>
<script>
Swal.fire(<?= json_encode($_SESSION['swal']) ?>);
</script>
<?php unset($_SESSION['swal'],$_SESSION['guardado']); endif; ?>

</body>
</html>
<?php $conn->close(); ?>