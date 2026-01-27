<?php
header('Content-Type: application/json');
session_start();
require_once 'config.php';

$response = ['logout' => false];

if (!isset($_SESSION['user_id'], $_SESSION['session_token'])) {
    echo json_encode(['logout' => true, 'reason' => 'nosession']);
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

if (
    !$user ||
    (int)$user['activo'] === 0 ||
    (int)$user['force_logout'] === 1 ||
    $user['session_token'] !== $_SESSION['session_token']
) {
    echo json_encode([
        'logout' => true,
        'reason' => !$user ? 'invalid' :
                    ((int)$user['activo'] === 0 ? 'bloqueado' :
                    ((int)$user['force_logout'] === 1 ? 'expulsado' : 'sesion'))
    ]);
    exit;
}

echo json_encode($response);
