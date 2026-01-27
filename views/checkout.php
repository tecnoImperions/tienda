<?php
require_once '../includes/config.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'usuario') {
    header('Location: ../auth/login.php');
    exit();
}

if (empty($_SESSION['carrito'])) {
    header('Location: ../views/catalogo.php');
    exit();
}

$conn = getConnection();

// Detectar sucursal del carrito
$store_id = null;
foreach ($_SESSION['carrito'] as $item) {
    if (isset($item['store_id'])) {
        $store_id = $item['store_id'];
        break;
    }
}

// Si no hay sucursal, usar la primera activa
if (!$store_id) {
    $store_result = $conn->query("SELECT store_id FROM stores WHERE estado = 'activa' LIMIT 1");
    if ($row = $store_result->fetch_assoc()) {
        $store_id = $row['store_id'];
    }
}

// PROCESAR PAGO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmar_pago'])) {
    $payment_method = 'QR - Transferencia';
    
    // Validar comprobante
    if (!isset($_FILES['comprobante']) || $_FILES['comprobante']['error'] !== UPLOAD_ERR_OK) {
        $error = "Por favor, sube el comprobante de pago.";
    } else {
        // Validar tipo de archivo
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
        $file_type = $_FILES['comprobante']['type'];
        
        if (!in_array($file_type, $allowed_types)) {
            $error = "Tipo de archivo no permitido. Solo JPG, PNG o PDF.";
        } elseif ($_FILES['comprobante']['size'] > 5 * 1024 * 1024) {
            $error = "El archivo es demasiado grande. Máximo 5MB.";
        } else {
            // Subir comprobante
            $upload_dir = '../uploads/comprobantes/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            
            $extension = pathinfo($_FILES['comprobante']['name'], PATHINFO_EXTENSION);
            $filename = 'comprobante_' . $_SESSION['user_id'] . '_' . time() . '.' . $extension;
            $filepath = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['comprobante']['tmp_name'], $filepath)) {
                // Calcular total
                $total = 0;
                foreach ($_SESSION['carrito'] as $item) {
                    $total += $item['cantidad'] * $item['price'];
                }
                
                // Verificar stock antes de crear orden
                $stock_ok = true;
                foreach ($_SESSION['carrito'] as $product_id => $item) {
                    $stmt = $conn->prepare("SELECT quantity FROM stocks WHERE product_id = ? AND store_id = ?");
                    $stmt->bind_param("ii", $product_id, $store_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows > 0) {
                        $stock = $result->fetch_assoc();
                        if ($stock['quantity'] < $item['cantidad']) {
                            $stock_ok = false;
                            $error = "Stock insuficiente para algunos productos. Por favor, actualiza tu carrito.";
                            break;
                        }
                    } else {
                        $stock_ok = false;
                        $error = "Producto no disponible en la sucursal seleccionada.";
                        break;
                    }
                }
                
                if ($stock_ok) {
                    // Crear orden
                    $stmt = $conn->prepare("INSERT INTO orders (customer_id, usuario_id, store_id, estado, total, payment_method, payment_id) VALUES (?, ?, ?, 'en_espera', ?, ?, ?)");
                    $customer_id = $_SESSION['user_id'];
                    $stmt->bind_param("iiidss", $customer_id, $_SESSION['user_id'], $store_id, $total, $payment_method, $filepath);
                    
                    if ($stmt->execute()) {
                        $order_id = $stmt->insert_id;
                        
                        // Insertar items
                        $stmt_item = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
                        
                        foreach ($_SESSION['carrito'] as $product_id => $item) {
                            $stmt_item->bind_param("iiid", $order_id, $product_id, $item['cantidad'], $item['price']);
                            $stmt_item->execute();
                            
                            // Reducir stock
                            $stmt_stock = $conn->prepare("UPDATE stocks SET quantity = quantity - ? WHERE product_id = ? AND store_id = ?");
                            $stmt_stock->bind_param("iii", $item['cantidad'], $product_id, $store_id);
                            $stmt_stock->execute();
                        }
                        
                        // Limpiar carrito
                        unset($_SESSION['carrito']);
                        
                        // Registrar en historial
                        $stmt_history = $conn->prepare("INSERT INTO purchase_history (user_id, order_id, action_type, new_status, amount, store_id, description) VALUES (?, ?, 'CREACION', 'en_espera', ?, ?, 'Orden creada con comprobante de pago')");
                        $stmt_history->bind_param("iidi", $_SESSION['user_id'], $order_id, $total, $store_id);
                        $stmt_history->execute();
                        
                        $_SESSION['success_message'] = "¡Orden creada exitosamente! Verificaremos tu pago pronto.";
                        header('Location: mis_ordenes.php');
                        exit();
                    }
                }
            } else {
                $error = "Error al subir el comprobante. Intenta nuevamente.";
            }
        }
    }
}

// Calcular total del carrito
$total = 0;
$item_count = 0;
foreach ($_SESSION['carrito'] as $item) {
    $total += $item['cantidad'] * $item['price'];
    $item_count += $item['cantidad'];
}

// Obtener información de la sucursal
$stmt = $conn->prepare("SELECT * FROM stores WHERE store_id = ? AND estado = 'activa'");
$stmt->bind_param("i", $store_id);
$stmt->execute();
$store = $stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Finalizar Compra - Bike Store</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<style>
body {
    background: linear-gradient(135deg, #f5f7fa 0%, #e8ecf1 100%);
    min-height: 100vh;
}

body.checkout-page {
    padding-top: 70px;
    padding-bottom: 30px;
}

.checkout-wrapper {
    max-width: 1400px;
    margin: 0 auto;
    padding: 1.5rem 1rem;
}

.page-header {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.page-header h1 {
    font-size: 1.75rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0;
}

.breadcrumb-custom {
    background: transparent;
    padding: 0;
    margin: 0.5rem 0 0 0;
    font-size: 0.875rem;
}

.breadcrumb-custom a {
    color: #0d6efd;
    text-decoration: none;
}

/* Layout en dos columnas para desktop */
.checkout-grid {
    display: grid;
    grid-template-columns: 1fr 420px;
    gap: 2rem;
}

@media (max-width: 992px) {
    .checkout-grid {
        grid-template-columns: 1fr;
    }
}

.card {
    border: none;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.08);
    margin-bottom: 1.5rem;
    background: white;
    overflow: hidden;
}

.card-header {
    background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%);
    color: white;
    border: none;
    padding: 1rem 1.5rem;
    font-weight: 600;
    font-size: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.product-list {
    max-height: 400px;
    overflow-y: auto;
}

.product-item {
    display: flex;
    align-items: center;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #f1f5f9;
    transition: background 0.2s;
}

.product-item:hover {
    background: #f8fafc;
}

.product-item:last-child {
    border-bottom: none;
}

.product-img {
    width: 64px;
    height: 64px;
    object-fit: cover;
    border-radius: 8px;
    margin-right: 1rem;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
}

.summary-box {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    position: sticky;
    top: 90px;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    padding: 0.75rem 0;
    border-bottom: 1px solid #f1f5f9;
}

.summary-total {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    padding: 1rem;
    border-radius: 8px;
    margin-top: 1rem;
}

.qr-section {
    text-align: center;
    padding: 2rem 1.5rem;
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    border-radius: 8px;
}

.qr-image {
    max-width: 240px;
    margin: 1.5rem auto;
    padding: 1.5rem;
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 16px rgba(0,0,0,0.1);
}

.bank-info {
    background: white;
    padding: 1.25rem;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    text-align: left;
    margin-top: 1rem;
}

.bank-info-row {
    display: flex;
    justify-content: space-between;
    padding: 0.5rem 0;
    border-bottom: 1px solid #f1f5f9;
}

.bank-info-row:last-child {
    border-bottom: none;
}

.upload-area {
    border: 3px dashed #cbd5e1;
    border-radius: 12px;
    padding: 3rem 2rem;
    text-align: center;
    background: #f8fafc;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
}

.upload-area:hover {
    border-color: #0d6efd;
    background: #eff6ff;
    transform: translateY(-2px);
}

.upload-area.dragover {
    border-color: #0d6efd;
    background: #dbeafe;
    transform: scale(1.02);
}

.upload-area.has-file {
    border-color: #10b981;
    background: #f0fdf4;
}

.file-preview {
    max-width: 100%;
    max-height: 300px;
    margin: 1rem auto;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.btn-primary {
    background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%);
    border: none;
    border-radius: 8px;
    padding: 0.875rem 2rem;
    font-weight: 600;
    transition: all 0.3s;
}

.btn-primary:hover {
    background: linear-gradient(135deg, #0b5ed7 0%, #0a58ca 100%);
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(13,110,253,0.3);
}

.btn-outline-secondary {
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    padding: 0.875rem 2rem;
    font-weight: 600;
    color: #64748b;
    transition: all 0.3s;
}

.btn-outline-secondary:hover {
    background: #f8fafc;
    border-color: #cbd5e1;
    color: #475569;
}

.store-badge {
    background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
    border-left: 4px solid #0d6efd;
    padding: 1.25rem;
    border-radius: 8px;
}

.alert {
    border: none;
    border-radius: 8px;
    padding: 1rem 1.25rem;
}

.security-notice {
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    border-left: 4px solid #f59e0b;
    padding: 1rem 1.25rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
}

.step-indicator {
    display: flex;
    justify-content: space-between;
    margin-bottom: 2rem;
    position: relative;
}

.step {
    flex: 1;
    text-align: center;
    position: relative;
}

.step-circle {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #e2e8f0;
    color: #94a3b8;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 0.5rem;
    font-weight: 600;
    z-index: 2;
    position: relative;
}

.step.active .step-circle {
    background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%);
    color: white;
    box-shadow: 0 4px 12px rgba(13,110,253,0.3);
}

.step-line {
    position: absolute;
    top: 20px;
    left: 50%;
    right: -50%;
    height: 2px;
    background: #e2e8f0;
    z-index: 1;
}

.step:last-child .step-line {
    display: none;
}

@media (max-width: 768px) {
    body {
        padding-bottom: 80px;
    }
    
    .checkout-wrapper {
        padding: 1rem;
    }
    
    .page-header h1 {
        font-size: 1.5rem;
    }
    
    .summary-box {
        position: static;
    }
    
    .product-list {
        max-height: none;
    }
    
    .qr-section {
        padding: 1.5rem 1rem;
    }
    
    .upload-area {
        padding: 2rem 1rem;
    }
    
    .step-indicator {
        font-size: 0.75rem;
    }
    
    .step-circle {
        width: 32px;
        height: 32px;
        font-size: 0.875rem;
    }
}

/* Animaciones */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.card {
    animation: fadeIn 0.4s ease-out;
}

.upload-icon {
    font-size: 3rem;
    color: #0d6efd;
    margin-bottom: 1rem;
    transition: transform 0.3s;
}

.upload-area:hover .upload-icon {
    transform: scale(1.1);
}
</style>
</head>
<body class="checkout-page">

<?php include '../includes/navbar.php'; ?>

<div class="checkout-wrapper">
    <!-- Header -->
    <div class="page-header">
        <h1><i class="bi bi-credit-card-2-front"></i> Finalizar Compra</h1>
        <nav class="breadcrumb-custom">
            <a href="catalogo.php">Productos</a>
            <span class="mx-2">/</span>
            <span class="text-muted">Checkout</span>
        </nav>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle-fill"></i> <strong>Error:</strong> <?= $error ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Aviso de seguridad -->
    <div class="security-notice">
        <div class="d-flex align-items-start">
            <i class="bi bi-shield-check fs-4 me-3"></i>
            <div>
                <strong>Compra segura</strong>
                <p class="mb-0 small mt-1">Tu pedido será procesado solo después de verificar el comprobante de pago. No envíes dinero a cuentas no oficiales.</p>
            </div>
        </div>
    </div>

    <!-- Indicador de pasos -->
    <div class="step-indicator">
        <div class="step active">
            <div class="step-circle">1</div>
            <div>Revisar</div>
            <div class="step-line"></div>
        </div>
        <div class="step active">
            <div class="step-circle">2</div>
            <div>Pagar</div>
            <div class="step-line"></div>
        </div>
        <div class="step">
            <div class="step-circle">3</div>
            <div>Confirmar</div>
        </div>
    </div>

    <form method="POST" enctype="multipart/form-data" id="paymentForm">
        <div class="checkout-grid">
            <!-- Columna izquierda: Detalles -->
            <div>
                <!-- Productos -->
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-bag-check-fill"></i>
                        <span>Resumen del pedido (<?= $item_count ?> <?= $item_count == 1 ? 'producto' : 'productos' ?>)</span>
                    </div>
                    <div class="product-list">
                        <?php foreach ($_SESSION['carrito'] as $item): 
                            $subtotal = $item['cantidad'] * $item['price'];
                        ?>
                        <div class="product-item">
                            <?php if (!empty($item['foto'])): ?>
                                <img src="<?= htmlspecialchars($item['foto']) ?>" class="product-img" alt="Producto">
                            <?php else: ?>
                                <div class="product-img d-flex align-items-center justify-content-center">
                                    <i class="bi bi-bicycle fs-4 text-muted"></i>
                                </div>
                            <?php endif; ?>
                            <div class="flex-grow-1">
                                <div class="fw-semibold"><?= htmlspecialchars($item['product_name']) ?></div>
                                <small class="text-muted">Cantidad: <?= $item['cantidad'] ?> × Bs <?= number_format($item['price'], 2) ?></small>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold">Bs <?= number_format($subtotal, 2) ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Sucursal -->
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-geo-alt-fill"></i>
                        <span>Punto de retiro</span>
                    </div>
                    <div class="card-body">
                        <div class="store-badge">
                            <h6 class="fw-bold mb-2">
                                <i class="bi bi-shop"></i> <?= htmlspecialchars($store['store_name']) ?>
                            </h6>
                            <div class="mb-1">
                                <i class="bi bi-pin-map-fill text-primary"></i> 
                                <?= htmlspecialchars($store['street']) ?>, <?= htmlspecialchars($store['city']) ?>
                            </div>
                            <div>
                                <i class="bi bi-telephone-fill text-primary"></i> 
                                <?= htmlspecialchars($store['phone']) ?>
                            </div>
                        </div>
                        <div class="alert alert-info mt-3 mb-0">
                            <i class="bi bi-info-circle-fill"></i> Tu pedido estará listo para recoger en 24-48 horas después de verificar el pago.
                        </div>
                    </div>
                </div>

                <!-- Código QR y pago -->
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-qr-code-scan"></i>
                        <span>Realiza tu transferencia</span>
                    </div>
                    <div class="card-body">
                        <div class="qr-section">
                            <h6 class="fw-bold mb-3">Escanea el código QR</h6>
                            
                            <div class="qr-image">
                                <img src="../assets/qr-pago.png" alt="QR de Pago" class="img-fluid" 
                                     onerror="this.src='https://via.placeholder.com/240x240/0d6efd/ffffff?text=QR+Pago'">
                            </div>
                            
                            <div class="alert alert-warning mb-3">
                                <strong>Monto exacto a transferir:</strong><br>
                                <span class="fs-4 fw-bold text-success">Bs <?= number_format($total, 2) ?></span>
                            </div>
                            
                            <div class="bank-info">
                                <div class="fw-bold mb-2 pb-2 border-bottom">
                                    <i class="bi bi-bank"></i> Datos bancarios
                                </div>
                                <div class="bank-info-row">
                                    <span class="text-muted">Banco:</span>
                                    <strong>Banco Nacional de Bolivia</strong>
                                </div>
                                <div class="bank-info-row">
                                    <span class="text-muted">Nro. Cuenta:</span>
                                    <strong>1234567890</strong>
                                </div>
                                <div class="bank-info-row">
                                    <span class="text-muted">Titular:</span>
                                    <strong>Bike Store S.R.L.</strong>
                                </div>
                                <div class="bank-info-row">
                                    <span class="text-muted">NIT:</span>
                                    <strong>123456789</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Subir comprobante -->
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-cloud-arrow-up-fill"></i>
                        <span>Comprobante de pago (Obligatorio)</span>
                    </div>
                    <div class="card-body">
                        <div class="upload-area" id="uploadArea">
                            <i class="bi bi-cloud-arrow-up-fill upload-icon"></i>
                            <h5 class="fw-bold mb-2">Arrastra tu comprobante aquí</h5>
                            <p class="text-muted mb-0">o haz clic para seleccionar archivo</p>
                            <p class="small text-muted mt-2">JPG, PNG o PDF - Máximo 5MB</p>
                            <input type="file" name="comprobante" id="comprobante" accept="image/*,.pdf" required hidden>
                        </div>
                        
                        <div id="previewContainer" class="mt-3" style="display:none;">
                            <div class="alert alert-success">
                                <i class="bi bi-check-circle-fill"></i> <strong>Archivo cargado:</strong>
                                <div id="fileName" class="mt-1 small"></div>
                            </div>
                            <img id="imagePreview" class="file-preview" style="display:none;">
                        </div>

                        <div class="alert alert-warning mt-3 mb-0">
                            <i class="bi bi-exclamation-triangle-fill"></i> <strong>Importante:</strong> Asegúrate de que el comprobante muestre claramente el monto, fecha y hora de la transferencia.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Columna derecha: Resumen sticky -->
            <div>
                <div class="summary-box">
                    <h5 class="fw-bold mb-3">Resumen de compra</h5>
                    
                    <div class="summary-row">
                        <span class="text-muted">Subtotal (<?= $item_count ?> items)</span>
                        <span class="fw-semibold">Bs <?= number_format($total, 2) ?></span>
                    </div>
                    
                    <div class="summary-row">
                        <span class="text-muted">Envío</span>
                        <span class="fw-semibold text-success">GRATIS</span>
                    </div>
                    
                    <div class="summary-total">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="small">Total a pagar</div>
                                <h3 class="m-0 fw-bold">Bs <?= number_format($total, 2) ?></h3>
                            </div>
                            <i class="bi bi-cash-coin fs-1"></i>
                        </div>
                    </div>

                    <div class="d-grid gap-2 mt-3">
                        <button type="submit" name="confirmar_pago" class="btn btn-primary btn-lg" id="confirmBtn" disabled>
                            <i class="bi bi-shield-check"></i> Confirmar pedido
                        </button>
                        <a href="catalogo.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Seguir comprando
                        </a>
                    </div>

                    <div class="text-center mt-3">
                        <small class="text-muted">
                            <i class="bi bi-lock-fill"></i> Transacción segura
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
const uploadArea = document.getElementById('uploadArea');
const fileInput = document.getElementById('comprobante');
const previewContainer = document.getElementById('previewContainer');
const imagePreview = document.getElementById('imagePreview');
const fileName = document.getElementById('fileName');
const confirmBtn = document.getElementById('confirmBtn');
const paymentForm = document.getElementById('paymentForm');

// Click en área de carga
uploadArea.addEventListener('click', () => fileInput.click());

// Drag & Drop
uploadArea.addEventListener('dragover', (e) => {
    e.preventDefault();
    uploadArea.classList.add('dragover');
});

uploadArea.addEventListener('dragleave', () => {
    uploadArea.classList.remove('dragover');
});

uploadArea.addEventListener('drop', (e) => {
    e.preventDefault();
    uploadArea.classList.remove('dragover');
    const files = e.dataTransfer.files;
    if (files.length > 0) {
        fileInput.files = files;
        handleFileSelect();
    }
});

// Cambio de archivo
fileInput.addEventListener('change', handleFileSelect);

function handleFileSelect() {
    const file = fileInput.files[0];
    if (!file) return;

    // Validar tamaño
    if (file.size > 5 * 1024 * 1024) {
        Swal.fire({
            icon: 'error',
            title: 'Archivo muy grande',
            text: 'El archivo no debe superar los 5MB',
            confirmButtonColor: '#0d6efd'
        });
        fileInput.value = '';
        return;
    }

    // Validar tipo
    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
    if (!allowedTypes.includes(file.type)) {
        Swal.fire({
            icon: 'error',
            title: 'Tipo no permitido',
            text: 'Solo se permiten archivos JPG, PNG o PDF',
            confirmButtonColor: '#0d6efd'
        });
        fileInput.value = '';
        return;
    }

    // Mostrar preview
    fileName.innerHTML = `<strong>${file.name}</strong> (${(file.size / 1024 / 1024).toFixed(2)} MB)`;
    previewContainer.style.display = 'block';
    uploadArea.classList.add('has-file');
    confirmBtn.disabled = false;
    
    if (file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = (e) => {
            imagePreview.src = e.target.result;
            imagePreview.style.display = 'block';
        };
        reader.readAsDataURL(file);
    } else {
        imagePreview.style.display = 'none';
    }
}

// Validación al enviar
paymentForm.addEventListener('submit', (e) => {
    if (!fileInput.files.length) {
        e.preventDefault();
        Swal.fire({
            icon: 'warning',
            title: 'Comprobante requerido',
            text: 'Por favor, sube tu comprobante de pago para continuar',
            confirmButtonColor: '#0d6efd'
        });
        return false;
    }
});
</script>

</body>
</html>
<?php $conn->close(); ?>