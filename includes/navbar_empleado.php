<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'empleado') {
    header('Location: auth/login.php');
    exit();
}

$usuario = $_SESSION['usuario'] ?? 'Empleado';
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow">
    <div class="container-fluid">

        <!-- LOGO -->
        <a class="navbar-brand fw-bold" href="empleado_inicio.php">
            <i class="bi bi-bicycle"></i> Bike Store
        </a>

        <!-- TOGGLER -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarEmpleado">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarEmpleado">

            <!-- MENÚ IZQUIERDO -->
            <ul class="navbar-nav me-auto">

                <li class="nav-item">
                    <a class="nav-link" href="empleado_inicio.php">
                        <i class="bi bi-house-door"></i> Inicio
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="clientes_empleado.php">
                        <i class="bi bi-people-fill"></i> Clientes
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="ordenes_empleado.php">
                        <i class="bi bi-bag-check"></i> Órdenes
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="stock_empleado.php">
                        <i class="bi bi-box-seam"></i> Stock
                    </a>
                </li>

            </ul>

            <!-- USUARIO -->
            <ul class="navbar-nav ms-auto">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        <i class="bi bi-person-badge"></i>
                        <?= htmlspecialchars($usuario) ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li class="dropdown-item-text">
                            Rol: <strong>EMPLEADO</strong>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item text-danger" href="auth/logout.php">
                                <i class="bi bi-box-arrow-right"></i> Cerrar sesión
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>

        </div>
    </div>
</nav>
