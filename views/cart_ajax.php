<?php
require_once '../includes/config.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'usuario') {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_add_cart'])) {
    $conn = getConnection();
    
    $product_id = (int)$_POST['product_id'];
    $cantidad = (int)$_POST['cantidad'];
    $store_id = (int)$_POST['store_id'];
    
    if ($cantidad <= 0) {
        echo json_encode(['success' => false, 'message' => 'Cantidad inválida']);
        exit();
    }
    
    // Verificar que el producto existe y obtener sus datos
    $stmt = $conn->prepare("
        SELECT p.*, c.descripcion as categoria 
        FROM productos p 
        LEFT JOIN categorias c ON p.category_id = c.category_id 
        WHERE p.product_id = ?
    ");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $producto = $stmt->get_result()->fetch_assoc();
    
    if (!$producto) {
        echo json_encode(['success' => false, 'message' => 'Producto no encontrado']);
        exit();
    }
    
    // Verificar stock disponible en la tienda
    $stmt = $conn->prepare("SELECT quantity FROM stocks WHERE product_id = ? AND store_id = ?");
    $stmt->bind_param("ii", $product_id, $store_id);
    $stmt->execute();
    $stock_result = $stmt->get_result()->fetch_assoc();
    
    if (!$stock_result) {
        echo json_encode(['success' => false, 'message' => 'Stock no disponible']);
        exit();
    }
    
    $stock_disponible = (int)$stock_result['quantity'];
    
    // Calcular cantidad ya en carrito
    $cantidad_en_carrito = 0;
    if (isset($_SESSION['carrito'][$product_id])) {
        $cantidad_en_carrito = (int)$_SESSION['carrito'][$product_id]['cantidad'];
    }
    
    // Verificar si hay stock suficiente
    $nueva_cantidad = $cantidad_en_carrito + $cantidad;
    
    if ($nueva_cantidad > $stock_disponible) {
        $disponible_para_agregar = $stock_disponible - $cantidad_en_carrito;
        
        if ($disponible_para_agregar <= 0) {
            echo json_encode([
                'success' => false,
                'icon' => 'warning',
                'title' => 'Stock agotado',
                'message' => 'Ya tienes todo el stock disponible en tu carrito'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'icon' => 'warning',
                'title' => 'Stock insuficiente',
                'message' => "Solo puedes agregar {$disponible_para_agregar} unidad(es) más de este producto"
            ]);
        }
        exit();
    }
    
    // Agregar o actualizar en el carrito
    if (!isset($_SESSION['carrito'])) {
        $_SESSION['carrito'] = [];
    }
    
    if (isset($_SESSION['carrito'][$product_id])) {
        $_SESSION['carrito'][$product_id]['cantidad'] += $cantidad;
    } else {
        $_SESSION['carrito'][$product_id] = [
            'product_id' => $product_id,
            'product_name' => $producto['product_name'],
            'price' => $producto['price'],
            'cantidad' => $cantidad,
            'foto' => $producto['foto'],
            'category' => $producto['categoria'],
            'store_id' => $store_id
        ];
    }
    
    // Calcular total de items en el carrito
    $cart_count = 0;
    foreach ($_SESSION['carrito'] as $item) {
        $cart_count += (int)$item['cantidad'];
    }
    
    // Calcular stock restante después de agregar al carrito
    $stock_restante = $stock_disponible - $_SESSION['carrito'][$product_id]['cantidad'];
    
    // *** NUEVA FUNCIONALIDAD: Generar HTML actualizado del carrito ***
    ob_start();
    $total = 0;
    foreach ($_SESSION['carrito'] as $id => $item):
        $cant = (int)$item['cantidad'];
        $precio = (float)$item['price'];
        $subtotal = $cant * $precio;
        $total += $subtotal;
    ?>
    <div class="list-group-item d-flex align-items-center justify-content-between border-bottom p-3" data-id="<?= $id ?>" data-price="<?= $precio ?>">
        <div class="d-flex align-items-center" style="flex:2">
            <?php if (!empty($item['foto'])): ?>
                <img src="<?= htmlspecialchars($item['foto']) ?>" class="rounded me-2" style="width:60px; height:60px; object-fit:cover;">
            <?php else: ?>
                <div class="rounded me-2 d-flex align-items-center justify-content-center bg-light" style="width:60px; height:60px;">
                    <i class="bi bi-bicycle fs-4 text-muted"></i>
                </div>
            <?php endif; ?>
            <div>
                <div class="fw-semibold" style="font-size:0.9rem;"><?= htmlspecialchars($item['product_name']) ?></div>
                <?php if(!empty($item['category'])): ?>
                    <small class="text-muted"><?= htmlspecialchars($item['category']) ?></small>
                <?php endif; ?>
            </div>
        </div>

        <div class="d-flex align-items-center" style="flex:1">
            <button class="btn btn-sm btn-outline-secondary me-1 minus-btn" style="border-radius:8px;">−</button>
            <input type="number" class="form-control form-control-sm quantity-input text-center" value="<?= $cant ?>" min="1" style="width:50px;border-radius:8px;" readonly>
            <button class="btn btn-sm btn-outline-secondary ms-1 plus-btn" style="border-radius:8px;">+</button>
        </div>

        <div style="flex:0.3;text-align:right;">
            <button class="btn btn-sm btn-danger remove-btn" style="border-radius:8px;"><i class="bi bi-trash"></i></button>
        </div>
    </div>
    <?php endforeach;
    $cart_html = ob_get_clean();
    
    $full_cart_html = '
    <div class="list-group list-group-flush" id="cartList">
        ' . $cart_html . '
    </div>
    <div class="p-3 border-top d-flex justify-content-between fw-bold bg-light">
        <span>Subtotal:</span>
        <span id="cartTotal">Bs ' . number_format($total, 2) . '</span>
    </div>
    <div class="p-3 d-grid gap-2">
        <a href="checkout.php" class="btn btn-primary" style="border-radius:12px;">
            <i class="bi bi-credit-card"></i> Finalizar compra
        </a>
        <button class="btn btn-outline-secondary" data-bs-dismiss="offcanvas" style="border-radius:12px;">
            Seguir comprando
        </button>
    </div>';
    
    echo json_encode([
        'success' => true,
        'message' => 'Producto agregado al carrito',
        'cart_count' => $cart_count,
        'stock_restante' => $stock_restante,
        'cart_html' => $full_cart_html
    ]);
    
    $conn->close();
    exit();
}

echo json_encode(['success' => false, 'message' => 'Petición inválida']);