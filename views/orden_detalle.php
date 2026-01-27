<?php
require_once '../includes/config.php';
session_start();

if (!isset($_SESSION['user_id']) || !isset($_GET['order_id'])) {
    echo '<div class="alert alert-danger m-4">Acceso no autorizado</div>';
    exit();
}

$conn = getConnection();
$order_id = (int)$_GET['order_id'];

// Verificar que la orden pertenece al usuario
$stmt = $conn->prepare("
    SELECT o.*, s.store_name, s.street, s.city, s.phone
    FROM orders o
    LEFT JOIN stores s ON o.store_id = s.store_id
    WHERE o.order_id = ? AND o.usuario_id = ?
");
$stmt->bind_param("ii", $order_id, $_SESSION['user_id']);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    echo '<div class="alert alert-danger m-4">Orden no encontrada</div>';
    exit();
}

// Obtener items de la orden
$stmt = $conn->prepare("
    SELECT oi.*, p.product_name, p.foto
    FROM order_items oi
    JOIN productos p ON oi.product_id = p.product_id
    WHERE oi.order_id = ?
");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$items = $stmt->get_result();

$status_info = [
    'pendiente' => [
        'icon' => 'hourglass-split',
        'color' => '#ef4444',
        'bg' => '#fee2e2',
        'text' => 'Pendiente',
        'desc' => 'Tu orden está siendo procesada'
    ],
    'en_espera' => [
        'icon' => 'clock-history',
        'color' => '#f59e0b',
        'bg' => '#fef3c7',
        'text' => 'En espera',
        'desc' => 'Estamos verificando tu pago'
    ],
    'confirmado' => [
        'icon' => 'check-circle-fill',
        'color' => '#3b82f6',
        'bg' => '#dbeafe',
        'text' => 'Confirmado',
        'desc' => 'Tu pedido ha sido confirmado'
    ],
    'enviado' => [
        'icon' => 'truck',
        'color' => '#8b5cf6',
        'bg' => '#e0e7ff',
        'text' => 'Enviado',
        'desc' => 'Tu pedido está en camino'
    ],
    'entregado' => [
        'icon' => 'check-circle-fill',
        'color' => '#10b981',
        'bg' => '#d1fae5',
        'text' => 'Entregado',
        'desc' => '¡Tu orden fue entregada!'
    ],
    'anulado' => [
        'icon' => 'x-circle-fill',
        'color' => '#6b7280',
        'bg' => '#f3f4f6',
        'text' => 'Anulado',
        'desc' => 'Esta orden fue cancelada'
    ]
];

$status = $status_info[$order['estado']] ?? $status_info['pendiente'];
?>

<style>
/* === STATUS HERO === */
.status-hero {
    background: linear-gradient(135deg, <?= $status['bg'] ?> 0%, #ffffff 100%);
    padding: 2.5rem 2rem;
    text-align: center;
    border-bottom: 1px solid #e5e7eb;
}

.status-icon-wrapper {
    width: 90px;
    height: 90px;
    margin: 0 auto 1.25rem;
    border-radius: 50%;
    background: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 8px 24px rgba(0,0,0,.1);
}

.status-icon-wrapper i {
    font-size: 3rem;
    color: <?= $status['color'] ?>;
}

.status-title {
    font-size: 1.75rem;
    font-weight: 700;
    color: <?= $status['color'] ?>;
    margin-bottom: 0.5rem;
}

.status-description {
    color: #64748b;
    font-size: 1rem;
}

/* === SECTION === */
.detail-section {
    padding: 2rem;
    border-bottom: 1px solid #f1f5f9;
}

.detail-section:last-child {
    border-bottom: none;
}

.section-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: #0f172a;
    margin-bottom: 1.25rem;
    display: flex;
    align-items: center;
    gap: 0.625rem;
}

.section-title i {
    color: #3b82f6;
    font-size: 1.25rem;
}

/* === INFO ROWS === */
.info-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.875rem 0;
    border-bottom: 1px solid #f1f5f9;
}

.info-row:last-child {
    border-bottom: none;
}

.info-label {
    color: #64748b;
    font-size: 0.95rem;
    font-weight: 500;
}

.info-value {
    color: #0f172a;
    font-weight: 600;
    text-align: right;
    font-size: 0.95rem;
}

/* === STORE CARD === */
.store-highlight {
    background: linear-gradient(135deg, #0f172a 0%, #334155 100%);
    color: #ffffff;
    border-radius: 16px;
    padding: 1.75rem;
}

.store-title {
    font-size: 1.15rem;
    font-weight: 700;
    margin-bottom: 1.25rem;
    display: flex;
    align-items: center;
    gap: 0.625rem;
}

.store-detail-item {
    display: flex;
    align-items: flex-start;
    gap: 0.875rem;
    margin-bottom: 1rem;
    padding: 0.75rem;
    background: rgba(255,255,255,0.1);
    border-radius: 10px;
}

.store-detail-item:last-child {
    margin-bottom: 0;
}

.store-detail-item i {
    font-size: 1.25rem;
    flex-shrink: 0;
    margin-top: 0.125rem;
}

.store-detail-text {
    flex: 1;
}

.store-detail-label {
    font-size: 0.85rem;
    opacity: 0.8;
    margin-bottom: 0.25rem;
}

.store-detail-value {
    font-size: 1rem;
    font-weight: 600;
}

/* === PRODUCT ITEM === */
.product-item {
    display: flex;
    align-items: center;
    gap: 1.25rem;
    padding: 1.25rem;
    background: #f8fafc;
    border-radius: 14px;
    margin-bottom: 1rem;
    transition: all .2s;
}

.product-item:hover {
    background: #e0e7ff;
    transform: translateX(4px);
}

.product-image {
    width: 80px;
    height: 80px;
    flex-shrink: 0;
    border-radius: 12px;
    overflow: hidden;
    background: #ffffff;
    box-shadow: 0 2px 8px rgba(0,0,0,.08);
}

.product-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.product-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #e5e7eb 0%, #f3f4f6 100%);
}

.product-placeholder i {
    font-size: 2.25rem;
    color: #9ca3af;
}

.product-info-content {
    flex: 1;
    min-width: 0;
}

.product-name {
    font-weight: 600;
    color: #0f172a;
    margin-bottom: 0.5rem;
    font-size: 1rem;
}

.product-quantity-info {
    font-size: 0.9rem;
    color: #64748b;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.product-price-section {
    text-align: right;
    flex-shrink: 0;
}

.price-label {
    font-size: 0.8rem;
    color: #94a3b8;
    margin-bottom: 0.25rem;
}

.price-total {
    font-weight: 700;
    color: #0f172a;
    font-size: 1.15rem;
}

/* === TOTAL SECTION === */
.total-highlight {
    background: linear-gradient(135deg, #dbeafe 0%, #e0e7ff 100%);
    border-radius: 16px;
    padding: 2rem;
}

.total-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.total-label {
    font-size: 1.25rem;
    font-weight: 600;
    color: #475569;
}

.total-amount {
    font-size: 2.25rem;
    font-weight: 700;
    color: #3b82f6;
}

/* === PAYMENT BADGE === */
.payment-success {
    background: #d1fae5;
    border-left: 4px solid #10b981;
    border-radius: 10px;
    padding: 1.25rem;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.payment-success i {
    font-size: 2rem;
    color: #10b981;
}

.payment-text {
    flex: 1;
}

.payment-title {
    font-weight: 700;
    color: #065f46;
    margin-bottom: 0.25rem;
}

.payment-desc {
    font-size: 0.9rem;
    color: #047857;
}

/* === ACTION BUTTONS === */
.action-buttons {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.btn-action {
    padding: 1rem;
    border-radius: 12px;
    font-weight: 600;
    border: none;
    transition: all .3s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.625rem;
}

.btn-print {
    background: #3b82f6;
    color: #ffffff;
}

.btn-print:hover {
    background: #2563eb;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

.btn-close-action {
    background: #f1f5f9;
    color: #475569;
}

.btn-close-action:hover {
    background: #e2e8f0;
}

/* === RESPONSIVE === */
@media (max-width: 576px) {
    .status-hero {
        padding: 2rem 1.5rem;
    }

    .status-icon-wrapper {
        width: 75px;
        height: 75px;
    }

    .status-icon-wrapper i {
        font-size: 2.5rem;
    }

    .status-title {
        font-size: 1.5rem;
    }

    .detail-section {
        padding: 1.5rem;
    }

    .product-item {
        gap: 1rem;
        padding: 1rem;
    }

    .product-image {
        width: 70px;
        height: 70px;
    }

    .product-name {
        font-size: 0.95rem;
    }

    .price-total {
        font-size: 1.05rem;
    }

    .total-amount {
        font-size: 2rem;
    }

    .action-buttons {
        grid-template-columns: 1fr;
    }
}
</style>

<!-- STATUS HERO -->
<div class="status-hero">
    <div class="status-icon-wrapper">
        <i class="bi bi-<?= $status['icon'] ?>"></i>
    </div>
    <div class="status-title"><?= $status['text'] ?></div>
    <div class="status-description"><?= $status['desc'] ?></div>
</div>

<!-- INFORMACIÓN DE LA ORDEN -->
<div class="detail-section">
    <div class="section-title">
        <i class="bi bi-info-circle-fill"></i>
        Información de la orden
    </div>
    <div class="info-row">
        <span class="info-label">Número de orden</span>
        <span class="info-value">#<?= $order['order_id'] ?></span>
    </div>
    <div class="info-row">
        <span class="info-label">Fecha de compra</span>
        <span class="info-value"><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></span>
    </div>
    <div class="info-row">
        <span class="info-label">Estado</span>
        <span class="info-value" style="color: <?= $status['color'] ?>">
            <?= $status['text'] ?>
        </span>
    </div>
</div>

<!-- SUCURSAL -->
<div class="detail-section">
    <div class="section-title">
        <i class="bi bi-shop"></i>
        Sucursal de retiro
    </div>
    <div class="store-highlight">
        <div class="store-title">
            <i class="bi bi-geo-alt-fill"></i>
            <?= htmlspecialchars($order['store_name']) ?>
        </div>
        
        <div class="store-detail-item">
            <i class="bi bi-pin-map-fill"></i>
            <div class="store-detail-text">
                <div class="store-detail-label">Dirección</div>
                <div class="store-detail-value">
                    <?= htmlspecialchars($order['street']) ?>, <?= htmlspecialchars($order['city']) ?>
                </div>
            </div>
        </div>
        
        <div class="store-detail-item">
            <i class="bi bi-telephone-fill"></i>
            <div class="store-detail-text">
                <div class="store-detail-label">Teléfono</div>
                <div class="store-detail-value"><?= htmlspecialchars($order['phone']) ?></div>
            </div>
        </div>
    </div>
</div>

<!-- PRODUCTOS -->
<div class="detail-section">
    <div class="section-title">
        <i class="bi bi-bag-fill"></i>
        Productos (<?= $items->num_rows ?>)
    </div>
    
    <?php while ($item = $items->fetch_assoc()): ?>
        <div class="product-item">
            <div class="product-image">
                <?php if (!empty($item['foto'])): ?>
                    <img src="<?= htmlspecialchars($item['foto']) ?>" 
                         alt="<?= htmlspecialchars($item['product_name']) ?>">
                <?php else: ?>
                    <div class="product-placeholder">
                        <i class="bi bi-bicycle"></i>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="product-info-content">
                <div class="product-name"><?= htmlspecialchars($item['product_name']) ?></div>
                <div class="product-quantity-info">
                    <i class="bi bi-x"></i>
                    <span><?= $item['quantity'] ?> unidad<?= $item['quantity'] > 1 ? 'es' : '' ?></span>
                    <span>•</span>
                    <span>Bs <?= number_format($item['price'], 2) ?> c/u</span>
                </div>
            </div>
            
            <div class="product-price-section">
                <div class="price-label">Subtotal</div>
                <div class="price-total">Bs <?= number_format($item['subtotal'], 2) ?></div>
            </div>
        </div>
    <?php endwhile; ?>
</div>

<!-- TOTAL -->
<div class="detail-section">
    <div class="total-highlight">
        <div class="total-row">
            <span class="total-label">Total a pagar</span>
            <span class="total-amount">Bs <?= number_format($order['total'], 2) ?></span>
        </div>
    </div>
</div>

<!-- PAGO -->
<?php if (!empty($order['payment_id'])): ?>
<div class="detail-section">
    <div class="payment-success">
        <i class="bi bi-check-circle-fill"></i>
        <div class="payment-text">
            <div class="payment-title">Comprobante adjuntado</div>
            <div class="payment-desc">Tu comprobante de pago ha sido recibido</div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- BOTONES -->
<div class="detail-section">
    <div class="action-buttons">
        <a href="generar_pdf.php?order_id=<?= $order_id ?>" target="_blank" class="btn btn-action btn-print">
            <i class="bi bi-printer-fill"></i>
            Imprimir
        </a>
        <button class="btn btn-action btn-close-action" data-bs-dismiss="offcanvas">
            <i class="bi bi-x-lg"></i>
            Cerrar
        </button>
    </div>
</div>

<?php $conn->close(); ?>