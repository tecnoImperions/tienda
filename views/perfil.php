<?php
require_once '../includes/config.php';
session_start();

/* ===============================
   VALIDAR SESIÓN
================================ */
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'usuario') {
    header('Location: ../auth/login.php');
    exit();
}

$conn = getConnection();

/* ===============================
   DATOS DEL USUARIO
================================ */
$stmt = $conn->prepare("SELECT * FROM usuarios WHERE user_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    session_destroy();
    header('Location: ../auth/login.php');
    exit();
}

/* ===============================
   ESTADÍSTICAS
================================ */
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) AS total_orders,
        SUM(CASE WHEN estado='entregado' THEN 1 ELSE 0 END) AS delivered,
        COALESCE(SUM(total),0) AS spent
    FROM orders
    WHERE usuario_id = ?
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

/* ===============================
   ACTUALIZAR PERFIL
================================ */
$swal = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = $_POST['email'];
    $password_new = trim($_POST['password_new']);

    if ($password_new !== '') {
        $hash = password_hash($password_new, PASSWORD_DEFAULT);
        $stmt = $conn->prepare(
            "UPDATE usuarios SET email=?, password=? WHERE user_id=?"
        );
        $stmt->bind_param("ssi", $email, $hash, $_SESSION['user_id']);
    } else {
        $stmt = $conn->prepare(
            "UPDATE usuarios SET email=? WHERE user_id=?"
        );
        $stmt->bind_param("si", $email, $_SESSION['user_id']);
    }

    if ($stmt->execute()) {
        $swal = [
            'icon' => 'success',
            'title' => 'Perfil actualizado',
            'text' => 'Tus datos se guardaron correctamente'
        ];

        $stmt = $conn->prepare("SELECT * FROM usuarios WHERE user_id = ?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
    } else {
        $swal = [
            'icon' => 'error',
            'title' => 'Error',
            'text' => 'No se pudo actualizar el perfil'
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mi Perfil</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
body {
    background: #f1f5f9;
    padding-top: 70px;
}

/* PERFIL */
.profile-box {
    background: white;
    border-radius: 14px;
    padding: 1.5rem;
    box-shadow: 0 4px 12px rgba(0,0,0,.08);
}

.avatar {
    width: 90px;
    height: 90px;
    background: #2563eb;
    color: white;
    border-radius: 50%;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size: 2.5rem;
    margin:auto;
}

/* STATS */
.stats {
    display:grid;
    grid-template-columns: repeat(3,1fr);
    margin-top: 1rem;
    gap: 1rem;
}

.stat {
    background:#f8fafc;
    border-radius: 10px;
    padding:1rem;
    text-align:center;
}

.stat h5 {
    margin:0;
    font-weight:700;
}

.stat span {
    font-size:.85rem;
    color:#64748b;
}

/* CARDS */
.card-clean {
    background:white;
    border-radius:14px;
    box-shadow:0 4px 12px rgba(0,0,0,.08);
    padding:1.5rem;
}

.action-btn {
    display:block;
    padding:1rem;
    border-radius:10px;
    border:1px solid #e5e7eb;
    text-decoration:none;
    color:#1e293b;
    font-weight:500;
}

.action-btn:hover {
    background:#f8fafc;
}

@media(max-width:768px){
    .stats {
        grid-template-columns:1fr;
    }
}
</style>
</head>
<body>

<?php include '../includes/navbar.php'; ?>

<div class="container">

    <!-- PERFIL + STATS -->
    <div class="profile-box mb-4 text-center">
        <div class="avatar mb-2">
            <i class="bi bi-person-fill"></i>
        </div>
        <h4 class="fw-bold mb-0"><?= htmlspecialchars($user['usuario']) ?></h4>
        <small class="text-muted"><?= htmlspecialchars($user['email']) ?></small>

        <div class="stats">
            <div class="stat">
                <h5><?= $stats['total_orders'] ?></h5>
                <span>Pedidos</span>
            </div>
            <div class="stat">
                <h5><?= $stats['delivered'] ?></h5>
                <span>Entregados</span>
            </div>
            <div class="stat">
                <h5>Bs <?= number_format($stats['spent'],0) ?></h5>
                <span>Total</span>
            </div>
        </div>
    </div>

    <!-- CONTENIDO -->
    <div class="row g-4">

        <!-- EDITAR PERFIL -->
        <div class="col-12 col-lg-8">
            <div class="card-clean">
                <h5 class="fw-bold mb-3">
                    <i class="bi bi-pencil"></i> Editar Perfil
                </h5>

                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Usuario</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($user['usuario']) ?>" disabled>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" required
                               value="<?= htmlspecialchars($user['email']) ?>">
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Nueva contraseña</label>
                        <input type="password" name="password_new" class="form-control"
                               placeholder="Dejar vacío para no cambiar">
                    </div>

                    <button class="btn btn-primary w-100">
                        Guardar cambios
                    </button>
                </form>
            </div>
        </div>

        <!-- ACCIONES -->
        <div class="col-12 col-lg-4">
            <div class="card-clean">
                <h5 class="fw-bold mb-3">
                    <i class="bi bi-lightning"></i> Acciones
                </h5>

                <div class="d-grid gap-2">
                    <a href="catalogo.php" class="action-btn">
                        <i class="bi bi-grid"></i> Ver catálogo
                    </a>
                    <a href="mis_ordenes.php" class="action-btn">
                        <i class="bi bi-bag-check"></i> Mis compras
                    </a>
                    <a href="auth/logout.php" class="action-btn text-danger">
                        <i class="bi bi-box-arrow-right"></i> Cerrar sesión
                    </a>
                </div>
            </div>
        </div>

    </div>
</div>

<?php if ($swal): ?>
<script>
Swal.fire({
    icon: '<?= $swal['icon'] ?>',
    title: '<?= $swal['title'] ?>',
    text: '<?= $swal['text'] ?>',
    confirmButtonColor: '#2563eb'
});
</script>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>
