<?php
require_once '../includes/config.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

$conn = getConnection();
$editando = false;
$tienda_editar = null;

/* ===============================
   POST (PRG)
================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_tienda'])) {

    $_SESSION['guardado'] = true;

    $store_name = $_POST['store_name'];
    $phone = $_POST['phone'];
    $email = $_POST['email'];
    $street = $_POST['street'];
    $city = $_POST['city'];
    $state = $_POST['state'];
    $estado = $_POST['estado'];

    if (!empty($_POST['store_id'])) {
        $stmt = $conn->prepare("
            UPDATE stores 
            SET store_name=?, phone=?, email=?, street=?, city=?, state=?, estado=? 
            WHERE store_id=?
        ");
        $stmt->bind_param("sssssssi",
            $store_name,$phone,$email,$street,$city,$state,$estado,$_POST['store_id']
        );
        $stmt->execute();

        $_SESSION['swal']=[
            'title'=>'Actualizada',
            'text'=>'Tienda actualizada correctamente',
            'icon'=>'success'
        ];
    } else {
        $stmt = $conn->prepare("
            INSERT INTO stores (store_name, phone, email, street, city, state, estado)
            VALUES (?,?,?,?,?,?,?)
        ");
        $stmt->bind_param("sssssss",
            $store_name,$phone,$email,$street,$city,$state,$estado
        );
        $stmt->execute();

        $_SESSION['swal']=[
            'title'=>'Creada',
            'text'=>'Tienda creada correctamente',
            'icon'=>'success'
        ];
    }

    header("Location: tiendas.php");
    exit();
}

/* ===============================
   CAMBIAR ESTADO
================================ */
if (isset($_GET['cambiar_estado'])) {
    $id = (int)$_GET['cambiar_estado'];

    $row = $conn->query("SELECT estado FROM stores WHERE store_id=$id")->fetch_assoc();
    $nuevo = $row['estado']==='activa'?'anulada':'activa';

    $stmt = $conn->prepare("UPDATE stores SET estado=? WHERE store_id=?");
    $stmt->bind_param("si",$nuevo,$id);
    $stmt->execute();

    $_SESSION['swal']=[
        'title'=>'Estado actualizado',
        'text'=>"La tienda ahora está $nuevo",
        'icon'=>'success'
    ];

    header("Location: tiendas.php");
    exit();
}

/* ===============================
   ELIMINAR
================================ */
if (isset($_POST['eliminar'])) {
    $id = (int)$_POST['eliminar'];

    $check = $conn->query("SELECT COUNT(*) total FROM stocks WHERE store_id=$id")->fetch_assoc();
    if ($check['total']>0) {
        $_SESSION['swal']=[
            'title'=>'No permitido',
            'text'=>'La tienda tiene productos en stock',
            'icon'=>'warning'
        ];
    } else {
        $stmt = $conn->prepare("DELETE FROM stores WHERE store_id=?");
        $stmt->bind_param("i",$id);
        $stmt->execute();

        $_SESSION['swal']=[
            'title'=>'Eliminada',
            'text'=>'Tienda eliminada correctamente',
            'icon'=>'success'
        ];
    }

    header("Location: tiendas.php");
    exit();
}

/* ===============================
   EDITAR
================================ */
if (isset($_GET['editar'])) {
    $editando = true;
    $stmt = $conn->prepare("SELECT * FROM stores WHERE store_id=?");
    $stmt->bind_param("i",$_GET['editar']);
    $stmt->execute();
    $tienda_editar = $stmt->get_result()->fetch_assoc();
}

/* ===============================
   DATOS
================================ */
$tiendas = $conn->query("
    SELECT s.*,
    COUNT(st.stock_id) total_productos,
    COALESCE(SUM(st.quantity),0) total_unidades,
    COALESCE(SUM(st.quantity*p.price),0) valor_inventario
    FROM stores s
    LEFT JOIN stocks st ON s.store_id=st.store_id
    LEFT JOIN productos p ON st.product_id=p.product_id
    GROUP BY s.store_id
    ORDER BY s.estado DESC, s.store_name
");
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Tiendas | Bike Store</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<link href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css" rel="stylesheet">
</head>

<body>
<?php include '../includes/navbar_admin.php'; ?>

<div class="container my-4">
<div class="row">

<!-- FORM -->
<div class="col-lg-4">
<div class="card shadow">
<div class="card-header bg-success text-white">
<?= $editando?'Editar':'Nueva' ?> tienda
</div>
<div class="card-body">
<form method="POST">
<?php if($editando): ?>
<input type="hidden" name="store_id" value="<?= $tienda_editar['store_id'] ?>">
<?php endif; ?>

<input class="form-control mb-2" name="store_name" required placeholder="Nombre"
value="<?= $editando?$tienda_editar['store_name']:'' ?>">

<input class="form-control mb-2" name="phone" placeholder="Teléfono"
value="<?= $editando?$tienda_editar['phone']:'' ?>">

<input class="form-control mb-2" name="email" placeholder="Email"
value="<?= $editando?$tienda_editar['email']:'' ?>">

<input class="form-control mb-2" name="street" placeholder="Dirección"
value="<?= $editando?$tienda_editar['street']:'' ?>">

<input class="form-control mb-2" name="city" placeholder="Ciudad"
value="<?= $editando?$tienda_editar['city']:'' ?>">

<input class="form-control mb-2" name="state" placeholder="Departamento"
value="<?= $editando?$tienda_editar['state']:'' ?>">

<select class="form-select mb-2" name="estado">
<option value="activa" <?= $editando && $tienda_editar['estado']=='activa'?'selected':'' ?>>Activa</option>
<option value="anulada" <?= $editando && $tienda_editar['estado']=='anulada'?'selected':'' ?>>Anulada</option>
</select>

<button class="btn btn-success w-100">Guardar</button>
<?php if($editando): ?>
<a href="tiendas.php" class="btn btn-secondary w-100 mt-2">Cancelar</a>
<?php endif; ?>
</form>
</div>
</div>
</div>

<!-- TABLA -->
<div class="col-lg-8">
<div class="card shadow">
<div class="card-header bg-dark text-white">Tiendas</div>
<div class="card-body">
<table id="tablaTiendas" class="table table-striped nowrap">
<thead>
<tr>
<th>ID</th><th>Nombre</th><th>Productos</th><th>Unidades</th>
<th>Valor</th><th>Estado</th><th>Acciones</th>
</tr>
</thead>
<tbody>
<?php while($t=$tiendas->fetch_assoc()): ?>
<tr>
<td><?= $t['store_id'] ?></td>
<td><?= $t['store_name'] ?></td>
<td><?= $t['total_productos'] ?></td>
<td><?= $t['total_unidades'] ?></td>
<td>Bs <?= number_format($t['valor_inventario'],2) ?></td>
<td>
<span class="badge bg-<?= $t['estado']=='activa'?'success':'danger' ?>">
<?= $t['estado'] ?>
</span>
</td>
<td>
<a href="?editar=<?= $t['store_id'] ?>" class="btn btn-warning btn-sm">
<i class="bi bi-pencil"></i></a>

<a href="?cambiar_estado=<?= $t['store_id'] ?>"
class="btn btn-sm btn-<?= $t['estado']=='activa'?'danger':'success' ?> btn-estado">
<i class="bi bi-power"></i></a>

<form method="POST" class="d-inline">
<input type="hidden" name="eliminar" value="<?= $t['store_id'] ?>">
<button type="button" class="btn btn-danger btn-sm btn-eliminar">
<i class="bi bi-trash"></i>
</button>
</form>
</td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
</div>
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

$('#tablaTiendas').DataTable({
responsive:true,
language:{url:'//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'}
});

$('.btn-eliminar').click(function(){
let form=$(this).closest('form');
Swal.fire({
title:'¿Eliminar tienda?',
text:'No se puede deshacer',
icon:'warning',
showCancelButton:true,
confirmButtonColor:'#d33'
}).then(r=>{if(r.isConfirmed) form.submit();});
});

});
</script>

<?php if(isset($_SESSION['swal'])): ?>
<script>Swal.fire(<?= json_encode($_SESSION['swal']) ?>);</script>
<?php unset($_SESSION['swal'],$_SESSION['guardado']); endif; ?>

</body>
</html>
<?php $conn->close(); ?>
