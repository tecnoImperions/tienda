<?php
require_once '../includes/config.php';
session_start();

header('Content-Type: application/json');

$response = [
    'has_items' => false,
    'store_id' => null,
    'count' => 0
];

if (!empty($_SESSION['carrito'])) {
    $response['has_items'] = true;
    $response['count'] = count($_SESSION['carrito']);
    
    // Obtener la sucursal del primer item
    foreach ($_SESSION['carrito'] as $item) {
        if (isset($item['store_id'])) {
            $response['store_id'] = $item['store_id'];
            break;
        }
    }
}

echo json_encode($response);
?>