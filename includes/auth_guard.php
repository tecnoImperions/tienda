<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user_id'], $_SESSION['session_token'])) {
    header('Location: expulsar.php?msg=nosession');
    exit;
}

$conn = getConnection();

$stmt = $conn->prepare("
    SELECT activo, session_token, force_logout
    FROM usuarios
    WHERE user_id = ?
    LIMIT 1
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

/* ❌ usuario inexistente */
if (!$user) {
    session_destroy();
    header('Location: expulsar.php?msg=invalid');
    exit;
}

/* 🔒 bloqueado */
if ((int)$user['activo'] === 0) {
    session_destroy();
    header('Location: expulsar.php?msg=bloqueado');
    exit;
}

/* 🚨 expulsión forzada */
if ((int)$user['force_logout'] === 1) {
    session_destroy();
    header('Location: expulsar.php?msg=expulsado');
    exit;
}

/* 🔁 sesión reemplazada */
if ($user['session_token'] !== $_SESSION['session_token']) {
    session_destroy();
    header('Location: expulsar.php?msg=sesion');
    exit;
}
