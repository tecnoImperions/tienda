<?php
// includes/navbar.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$rol = $_SESSION['role'] ?? null;

switch ($rol) {
    case 'admin':
        include __DIR__ . '/navbar_admin.php';
        break;

    case 'empleado':
        include __DIR__ . '/navbar_empleado.php';
        break;

    case 'usuario':
    default:
        include __DIR__ . '/navbar_usuario.php';
        break;
}
