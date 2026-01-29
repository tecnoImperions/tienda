<?php
session_start();
session_unset();
session_destroy();

$msg = $_GET['msg'] ?? 'expulsado';

session_start();

if ($msg === 'bloqueado') {
    $_SESSION['swal'] = [
        'title' => 'Cuenta bloqueada',
        'text'  => 'Tu cuenta ha sido bloqueada por el administrador',
        'icon'  => 'error'
    ];
} else {
    $_SESSION['swal'] = [
        'title' => 'Sesión finalizada',
        'text'  => 'Tu sesión fue cerrada porque se inició en otro dispositivo',
        'icon'  => 'warning'
    ];
}

header("Location: login.php");
exit();
