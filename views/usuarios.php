<?php
require_once '../includes/config.php';
session_start();

/* ===============================
   SEGURIDAD
================================ */
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

if ($_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit();
}

$conn = getConnection();

/* ===============================
   CREAR / ACTUALIZAR (PRG)
================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $usuario = trim($_POST['usuario']);
    $email   = trim($_POST['email']);
    $role    = $_POST['role'];

    /* ===== ACTUALIZAR ===== */
    if (!empty($_POST['user_id'])) {

        $user_id = (int)$_POST['user_id'];

        if ($user_id === 1) {
            $_SESSION['swal'] = [
                'title' => 'Protegido',
                'text'  => 'El administrador principal no puede modificarse',
                'icon'  => 'warning'
            ];
            header("Location: usuarios.php");
            exit();
        }

        if (!empty($_POST['password'])) {
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt = $conn->prepare(
                "UPDATE usuarios SET usuario=?, password=?, email=?, role=? WHERE user_id=?"
            );
            $stmt->bind_param("ssssi", $usuario, $password, $email, $role, $user_id);
        } else {
            $stmt = $conn->prepare(
                "UPDATE usuarios SET usuario=?, email=?, role=? WHERE user_id=?"
            );
            $stmt->bind_param("sssi", $usuario, $email, $role, $user_id);
        }

        $stmt->execute();

        $_SESSION['swal'] = [
            'title' => 'Actualizado',
            'text'  => 'Usuario actualizado correctamente',
            'icon'  => 'success'
        ];
        $_SESSION['guardado'] = true;
        header("Location: usuarios.php");
        exit();
    }

    /* ===== CREAR ===== */
    if (empty($_POST['password'])) {
        $_SESSION['swal'] = [
            'title' => 'Error',
            'text'  => 'La contraseña es obligatoria',
            'icon'  => 'error'
        ];
        header("Location: usuarios.php");
        exit();
    }

    $password   = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $verificado = ($role === 'empleado') ? 1 : 0;

    $stmt = $conn->prepare(
        "INSERT INTO usuarios (usuario,password,email,role,verificado,activo)
         VALUES (?,?,?,?,?,1)"
    );
    $stmt->bind_param("ssssi", $usuario, $password, $email, $role, $verificado);

    if ($stmt->execute()) {
        $_SESSION['swal'] = [
            'title' => 'Creado',
            'text'  => 'Usuario registrado correctamente',
            'icon'  => 'success'
        ];
    } else {
        $_SESSION['swal'] = [
            'title' => 'Error',
            'text'  => 'El usuario ya existe',
            'icon'  => 'error'
        ];
    }

    $_SESSION['guardado'] = true;
    header("Location: usuarios.php");
    exit();
}

/* ===============================
   BLOQUEAR / DESBLOQUEAR
================================ */
if (isset($_GET['toggle'])) {

    $id = (int)$_GET['toggle'];

    if ($id === 1 || $id === $_SESSION['user_id']) {
        $_SESSION['swal'] = [
            'title' => 'Acción no permitida',
            'text'  => 'Este usuario está protegido',
            'icon'  => 'warning'
        ];
        header("Location: usuarios.php");
        exit();
    }

    $stmt = $conn->prepare(
        "UPDATE usuarios SET activo = NOT activo WHERE user_id=?"
    );
    $stmt->bind_param("i", $id);
    $stmt->execute();

    $_SESSION['swal'] = [
        'title' => 'Estado actualizado',
        'text'  => 'El estado del usuario fue modificado',
        'icon'  => 'success'
    ];
    header("Location: usuarios.php");
    exit();
}

/* ===============================
   ELIMINAR
================================ */
if (isset($_GET['eliminar'])) {

    $id = (int)$_GET['eliminar'];

    if ($id === 1 || $id === $_SESSION['user_id']) {
        $_SESSION['swal'] = [
            'title' => 'Acción no permitida',
            'text'  => 'Este usuario está protegido',
            'icon'  => 'warning'
        ];
        header("Location: usuarios.php");
        exit();
    }

    $stmt = $conn->prepare("DELETE FROM usuarios WHERE user_id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    $_SESSION['swal'] = [
        'title' => 'Eliminado',
        'text'  => 'Usuario eliminado correctamente',
        'icon'  => 'success'
    ];
    header("Location: usuarios.php");
    exit();
}

/* ===============================
   EDITAR
================================ */
$editando = false;
$usuario_editar = null;

if (isset($_GET['editar'])) {
    $editando = true;
    $_SESSION['en_edicion'] = true; // <-- para limpiar URL y back
    $stmt = $conn->prepare("SELECT * FROM usuarios WHERE user_id=?");
    $stmt->bind_param("i", $_GET['editar']);
    $stmt->execute();
    $usuario_editar = $stmt->get_result()->fetch_assoc();
}

/* ===============================
   LISTADO
================================ */
$usuarios = $conn->query("
    SELECT * FROM usuarios
    ORDER BY
        CASE
            WHEN role='admin' THEN 1
            WHEN role='empleado' THEN 2
            ELSE 3
        END, user_id DESC
");
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Usuarios | Bike Store</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<link href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css" rel="stylesheet">

<style>
#tablaUsuarios thead{
    background:linear-gradient(90deg,#0d6efd,#084298);
    color:white;
}
</style>
</head>

<body>
<?php include '../includes/navbar_admin.php'; ?>
<div class="container my-4">
<div class="row">

<!-- FORM -->
<div class="col-lg-4 mb-3">
<div class="card shadow">
<div class="card-header bg-primary text-white">
<?= $editando?'Editar':'Nuevo' ?> usuario
</div>
<div class="card-body">
<form method="POST">

<?php if($editando): ?>
<input type="hidden" name="user_id" value="<?= $usuario_editar['user_id'] ?>">
<?php endif; ?>

<input class="form-control mb-2" name="usuario" required
value="<?= $editando?$usuario_editar['usuario']:'' ?>" placeholder="Usuario">

<input type="password" class="form-control mb-2" name="password"
placeholder="<?= $editando?'Nueva contraseña (opcional)':'Contraseña *' ?>"
<?= $editando?'':'required' ?>>

<input type="email" class="form-control mb-2" name="email" required
value="<?= $editando?$usuario_editar['email']:'' ?>" placeholder="Email">

<select class="form-select mb-2" name="role" required>
<option value="usuario" <?= $editando && $usuario_editar['role']=='usuario'?'selected':'' ?>>Usuario</option>
<option value="empleado" <?= $editando && $usuario_editar['role']=='empleado'?'selected':'' ?>>Empleado</option>
<option value="admin" <?= $editando && $usuario_editar['role']=='admin'?'selected':'' ?>>Admin</option>
</select>

<button class="btn btn-primary w-100">Guardar</button>

<?php if($editando): ?>
<a href="usuarios.php" class="btn btn-secondary w-100 mt-2">Cancelar</a>
<?php endif; ?>

</form>
</div>
</div>
</div>

<!-- TABLA -->
<div class="col-lg-8">
<div class="card shadow">
<div class="card-header bg-dark text-white">Listado de usuarios</div>
<div class="card-body">
<table id="tablaUsuarios" class="table table-striped table-hover nowrap">
<thead>
<tr>
<th>ID</th>
<th>Usuario</th>
<th>Email</th>
<th>Rol</th>
<th>Estado</th>
<th>Registro</th>
<th>Acciones</th>
</tr>
</thead>
<tbody>
<?php while($u=$usuarios->fetch_assoc()): ?>
<tr>
<td><?= $u['user_id'] ?></td>
<td><strong><?= htmlspecialchars($u['usuario']) ?></strong></td>
<td><?= $u['email'] ?></td>
<td><?= ucfirst($u['role']) ?></td>
<td>
<?= $u['activo']
?'<span class="badge bg-success">Activo</span>'
:'<span class="badge bg-danger">Bloqueado</span>' ?>
</td>
<td><?= date('d/m/Y H:i',strtotime($u['created_at'])) ?></td>
<td>

<?php if($u['user_id']==1): ?>
<button class="btn btn-secondary btn-sm" disabled>
<i class="bi bi-lock-fill"></i>
</button>

<?php elseif($u['user_id']!=$_SESSION['user_id']): ?>

<a href="?editar=<?= $u['user_id'] ?>" class="btn btn-warning btn-sm">
<i class="bi bi-pencil"></i>
</a>

<button class="btn btn-sm <?= $u['activo']?'btn-dark':'btn-success' ?> btn-toggle"
data-id="<?= $u['user_id'] ?>"
data-estado="<?= $u['activo'] ?>">
<i class="bi <?= $u['activo']?'bi-person-slash':'bi-person-check' ?>"></i>
</button>

<button class="btn btn-danger btn-sm btn-eliminar"
data-id="<?= $u['user_id'] ?>">
<i class="bi bi-trash"></i>
</button>

<?php endif; ?>

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

$('#tablaUsuarios').DataTable({
responsive:true,
language:{url:'//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'}
});

/* ===============================
   ELIMINAR
================================ */
$('.btn-eliminar').click(function(){
let id=$(this).data('id');
Swal.fire({
title:'¿Eliminar usuario?',
icon:'warning',
showCancelButton:true,
confirmButtonColor:'#d33',
confirmButtonText:'Eliminar'
}).then(r=>{
if(r.isConfirmed) location='?eliminar='+id;
});
});

/* ===============================
   BLOQUEAR / DESBLOQUEAR
================================ */
$('.btn-toggle').click(function(){
let id=$(this).data('id');
let activo=$(this).data('estado');

Swal.fire({
title: activo ? '¿Bloquear usuario?' : '¿Desbloquear usuario?',
icon:'question',
showCancelButton:true,
confirmButtonText:'Confirmar'
}).then(r=>{
if(r.isConfirmed) location='?toggle='+id;
});
});

/* ===============================
   SALIR DE EDICIÓN (BACK / URL)
================================ */
<?php if(isset($_SESSION['en_edicion']) && empty($_SESSION['guardado'])): ?>
history.replaceState(null,null,'usuarios.php');
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
</script>

<?php if(isset($_SESSION['swal'])): ?>
<script>
Swal.fire(<?= json_encode($_SESSION['swal']) ?>);
</script>
<?php unset($_SESSION['swal'], $_SESSION['guardado']); endif; ?>

</body>
</html>
<?php $conn->close(); ?>
