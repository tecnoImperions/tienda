<?php
require_once '../../includes/config.php';
session_start();

if (!isset($_SESSION['user_id']) || empty($_SESSION['carrito'])) {
    header('Location: ../productos/catalogo.php');
    exit();
}

$conn = getConnection();
$usuario_id = $_SESSION['user_id'];
$customer_id = $usuario_id;

// Calcular total del carrito
$total = 0;
foreach ($_SESSION['carrito'] as $item) {
    $total += $item['price'] * $item['quantity'];
}

// Verificar que se haya seleccionado una tienda
if (!isset($_POST['store_id']) || empty($_POST['store_id'])) {
    die("Error: Debe seleccionar una tienda");
}

$store_id = intval($_POST['store_id']);

// Verificar que la tienda exista y esté activa
$sql_check_store = "SELECT * FROM stores WHERE store_id = ? AND estado = 'activa'";
$stmt_check = $conn->prepare($sql_check_store);
$stmt_check->bind_param("i", $store_id);
$stmt_check->execute();
$tienda = $stmt_check->get_result()->fetch_assoc();

if (!$tienda) {
    die("Error: La tienda seleccionada no está disponible");
}

// Verificar que la tienda tenga stock suficiente de TODOS los productos
foreach ($_SESSION['carrito'] as $item) {
    $sql_check_stock = "SELECT quantity FROM stocks WHERE store_id = ? AND product_id = ?";
    $stmt_stock = $conn->prepare($sql_check_stock);
    $stmt_stock->bind_param("ii", $store_id, $item['product_id']);
    $stmt_stock->execute();
    $result_stock = $stmt_stock->get_result();
    $stock = $result_stock->fetch_assoc();
    
    if (!$stock || $stock['quantity'] < $item['quantity']) {
        die("Error: La tienda no tiene stock suficiente de " . $item['product_name']);
    }
}

// Iniciar transacción
$conn->begin_transaction();

try {
    // MODO MANUAL (para el dueño de la cuenta)
    if (isset($_POST['crear_manual'])) {
        // Crear orden manual
        $sql_order = "INSERT INTO Orders (customer_id, usuario_id, estado, total, payment_method, store_id) 
                      VALUES (?, ?, 'pendiente', ?, 'Manual', ?)";
        $stmt = $conn->prepare($sql_order);
        $stmt->bind_param("iidi", $customer_id, $usuario_id, $total, $store_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Error al crear orden: " . $conn->error);
        }
        
        $order_id = $conn->insert_id;
        
        // Insertar items
        $sql_item = "INSERT INTO Order_items (order_id, product_id, quantity, price, discount) 
                     VALUES (?, ?, ?, ?, 0)";
        $stmt_item = $conn->prepare($sql_item);
        
        foreach ($_SESSION['carrito'] as $item) {
            $stmt_item->bind_param("iiid", 
                $order_id, 
                $item['product_id'], 
                $item['quantity'], 
                $item['price']
            );
            
            if (!$stmt_item->execute()) {
                throw new Exception("Error al insertar item");
            }
            
            // DESCONTAR DEL STOCK
            $sql_update_stock = "UPDATE stocks 
                                 SET quantity = quantity - ? 
                                 WHERE store_id = ? AND product_id = ?";
            $stmt_update = $conn->prepare($sql_update_stock);
            $stmt_update->bind_param("iii", $item['quantity'], $store_id, $item['product_id']);
            
            if (!$stmt_update->execute()) {
                throw new Exception("Error al actualizar stock");
            }
        }
        
        // Confirmar transacción
        $conn->commit();
        
        // Limpiar carrito
        $_SESSION['carrito'] = [];
        
        // Redirigir a mis órdenes
        header("Location: mis_ordenes.php?orden_creada=$order_id");
        exit();
    }
    
    // MODO PAYPAL (para otros usuarios)
    if (isset($_POST['payment_data'])) {
        $payment_data = json_decode($_POST['payment_data'], true);
        
        if (!$payment_data) {
            throw new Exception("Error: Datos de pago inválidos");
        }
        
        $payment_id = $payment_data['orderID'];
        $payment_status = $payment_data['status'];
        
        // Crear orden con pago de PayPal
        $sql_order = "INSERT INTO Orders (customer_id, usuario_id, estado, total, payment_method, payment_id, store_id) 
                      VALUES (?, ?, 'en_espera', ?, 'PayPal', ?, ?)";
        $stmt = $conn->prepare($sql_order);
        $stmt->bind_param("iidsi", $customer_id, $usuario_id, $total, $payment_id, $store_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Error al crear orden: " . $conn->error);
        }
        
        $order_id = $conn->insert_id;
        
        // Insertar items
        $sql_item = "INSERT INTO Order_items (order_id, product_id, quantity, price, discount) 
                     VALUES (?, ?, ?, ?, 0)";
        $stmt_item = $conn->prepare($sql_item);
        
        foreach ($_SESSION['carrito'] as $item) {
            $stmt_item->bind_param("iiid", 
                $order_id, 
                $item['product_id'], 
                $item['quantity'], 
                $item['price']
            );
            
            if (!$stmt_item->execute()) {
                throw new Exception("Error al insertar item");
            }
            
            // DESCONTAR DEL STOCK
            $sql_update_stock = "UPDATE stocks 
                                 SET quantity = quantity - ? 
                                 WHERE store_id = ? AND product_id = ?";
            $stmt_update = $conn->prepare($sql_update_stock);
            $stmt_update->bind_param("iii", $item['quantity'], $store_id, $item['product_id']);
            
            if (!$stmt_update->execute()) {
                throw new Exception("Error al actualizar stock");
            }
        }
        
        // Confirmar transacción
        $conn->commit();
        
        // Limpiar carrito
        $_SESSION['carrito'] = [];
        
        // Redirigir con mensaje de éxito
        header("Location: mis_ordenes.php?pago_exitoso=1&orden_id=$order_id");
        exit();
    }
    
} catch (Exception $e) {
    // Revertir transacción en caso de error
    $conn->rollback();
    die("Error al procesar el pago: " . $e->getMessage());
}

$conn->close();
header('Location: catalogo.php');
exit();
?>