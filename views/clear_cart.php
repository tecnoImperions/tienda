<?php
require_once '../includes/config.php';
session_start();

header('Content-Type: application/json');

// Limpiar el carrito
unset($_SESSION['carrito']);

echo json_encode(['success' => true]);
?>