<?php
require_once '../includes/config.php';
require_once '../includes/auth_guard.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$conn = getConnection();

// Datos de usuario de sesión
$user_id = $_SESSION['user_id'] ?? null;
$usuario = $_SESSION['usuario'] ?? null;
$rol     = $_SESSION['role'] ?? 'usuario';

$es_admin    = ($rol === 'admin');
$es_empleado = ($rol === 'empleado');
$es_usuario  = ($rol === 'usuario');

// ============================
// VALIDAR SESIÓN ACTIVA Y FLAG DE BIENVENIDA
// ============================
$mostrar_login_success = false;

if ($user_id) {
    $stmt = $conn->prepare("SELECT activo, force_logout, session_token FROM usuarios WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $user = $res->fetch_assoc();

    if (!$user || $user['activo'] == 0) {
        session_destroy();
        header("Location: login.php");
        exit();
    }

    if (isset($_SESSION['login_success'])) {
        $mostrar_login_success = true;
        unset($_SESSION['login_success']);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Bike Store</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
html, body {
    height: 100%;
}

body {
    display: flex;
    flex-direction: column;
}

.main-content {
    flex: 1;
}

/* HERO */
.hero{height:420px;background:#000;position:relative}
.hero-slide{position:absolute;inset:0;background-size:contain;background-repeat:no-repeat;background-position:center;opacity:0;transition:1.2s}
.hero-slide.active{opacity:1}
.hero-overlay{position:absolute;inset:0;background:rgba(0,0,0,.55)}
.hero-content{position:relative;z-index:2;color:#fff;padding-top:120px;text-align:center}

.card-hover{
    transition: all 0.3s ease;
}

.card-hover:hover{
    transform:translateY(-5px);
    box-shadow:0 8px 20px rgba(0,0,0,.15);
}

.carousel-item img{
    height:250px;
    object-fit:contain;
}
</style>
</head>

<body>

<?php include '../includes/navbar.php'; ?>

<div class="main-content">

<!-- HERO -->
<div class="hero">
<?php
$imgs = [];
if (is_dir('uploads')) {
    foreach (scandir('uploads') as $f) {
        if (preg_match('/\.(jpg|png|webp)$/i',$f)) {
            $imgs[]='uploads/'.$f;
        }
    }
}
if (!$imgs) $imgs[]='';
foreach ($imgs as $i=>$img):
?>
<div class="hero-slide <?= $i===0?'active':'' ?>" 
     style="<?= $img ? "background-image:url('$img')" : "background:linear-gradient(135deg,#667eea,#764ba2)" ?>">
</div>
<?php endforeach; ?>
<div class="hero-overlay"></div>
<div class="hero-content">
    <h1 class="fw-bold">Bike Store</h1>
    <p>Sistema de Gestión</p>
    <strong><?= htmlspecialchars($usuario) ?></strong>
    <?php if($es_admin): ?>
        <span class="badge bg-warning ms-2">ADMIN</span>
    <?php endif; ?>
</div>
</div>

<div class="container my-5">

<?php if ($es_admin): ?>

<h3 class="mb-4 text-center">Panel Administrativo</h3>
<div class="row g-4">
<?php
$mods=[
    ['Dashboard','dashboard.php','speedometer2','danger'],
    ['Productos','productos.php','box','primary'],
    ['Categorías','categorias.php','tags','success'],
    ['Tiendas','tiendas.php','shop','warning'],
    ['Stocks','stocks.php','boxes','info'],
    ['Usuarios','usuarios.php','people','secondary'],
    ['Clientes','clientes.php','person-badge','dark']
];
foreach($mods as [$t,$l,$i,$c]): ?>
<div class="col-6 col-md-4 col-lg-3">
<div class="card card-hover text-center h-100">
<div class="card-body d-flex flex-column">
<i class="bi bi-<?= $i ?> text-<?= $c ?> fs-1 mb-3"></i>
<h5 class="mt-auto mb-3"><?= $t ?></h5>
<a href="<?= $l ?>" class="btn btn-<?= $c ?> btn-sm mt-auto">Acceder</a>
</div>
</div>
</div>
<?php endforeach; ?>
</div>

<?php elseif ($es_empleado): ?>

<div class="text-center">
<h3>Gestión de Clientes</h3>
<a href="clientes_empleado.php" class="btn btn-info btn-lg mt-3">Ingresar</a>
</div>

<?php else: ?>

<h3 class="mb-4 text-center">🔥 Productos Destacados</h3>

<?php
$q = $conn->query("
    SELECT product_id, product_name AS nombre, price, foto AS imagen
    FROM productos
    ORDER BY product_id DESC
    LIMIT 6
");

$productos = [];
while($p = $q->fetch_assoc()){
    $productos[] = $p;
}

// 3 productos por slide (desktop)
$slides = array_chunk($productos, 3);
?>

<?php if(count($productos) > 0): ?>
<div id="carouselProductos" class="carousel slide" data-bs-ride="carousel">
<div class="carousel-inner">

<?php foreach($slides as $i => $slide): ?>
<div class="carousel-item <?= $i===0 ? 'active' : '' ?>">
<div class="row justify-content-center">

<?php foreach($slide as $p): ?>
<div class="col-12 col-md-4 mb-3">
<div class="card h-100">
<img src="<?= $p['imagen'] ?: 'assets/noimg.png' ?>" class="card-img-top" alt="<?= htmlspecialchars($p['nombre']) ?>">
<div class="card-body text-center">
<h6><?= htmlspecialchars($p['nombre']) ?></h6>
<strong class="text-success">Bs <?= number_format($p['price'],2) ?></strong>
<a href="catalogo.php?producto_id=<?= $p['product_id'] ?>" class="btn btn-outline-primary btn-sm mt-2">
Ver producto
</a>
</div>
</div>
</div>
<?php endforeach; ?>

</div>
</div>
<?php endforeach; ?>

</div>

<button class="carousel-control-prev" type="button" data-bs-target="#carouselProductos" data-bs-slide="prev">
<span class="carousel-control-prev-icon"></span>
</button>

<button class="carousel-control-next" type="button" data-bs-target="#carouselProductos" data-bs-slide="next">
<span class="carousel-control-next-icon"></span>
</button>
</div>

<div class="text-center mt-4">
<a href="catalogo.php" class="btn btn-primary btn-lg">Ver Catálogo Completo</a>
</div>
<?php endif; ?>

<?php endif; ?>

</div>
</div>

<footer class="bg-dark text-white text-center py-3">
&copy; <?= date('Y') ?> Bike Store
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Hero automático
const slides=document.querySelectorAll('.hero-slide');
let idx=0;
if(slides.length>1){
    setInterval(()=>{
        slides[idx].classList.remove('active');
        idx=(idx+1)%slides.length;
        slides[idx].classList.add('active');
    },4000);
}

// Check sesión
setInterval(()=>{
    fetch('../includes/check_session.php')
    .then(r=>r.json())
    .then(d=>{
        if(d.logout){
            Swal.fire({
                title:'Sesión finalizada',
                text:d.reason,
                icon:'warning'
            }).then(()=>location.href='login.php');
        }
    })
},5000);

// Bienvenida
<?php if($mostrar_login_success && !empty($usuario)): ?>
Swal.fire({
    title: '¡Bienvenido, <?= htmlspecialchars($usuario) ?>!',
    text: 'Sesión iniciada correctamente',
    icon: 'success'
});
<?php endif; ?>
</script>

</body>
</html>