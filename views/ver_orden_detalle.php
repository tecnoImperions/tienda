<?php
require_once '../includes/config.php';
session_start();

if (!isset($_SESSION['user_id']) || !isset($_GET['order_id'])) {
    header('Location: index.php');
    exit();
}

$conn = getConnection();
$order_id = $_GET['order_id'];
$es_admin = ($_SESSION['role'] == 'admin');

// Obtener datos de la orden CON TIENDA
$sql = "SELECT o.*, u.usuario, u.email, s.store_name, s.phone as store_phone, s.email as store_email, 
               s.street, s.city, s.state 
        FROM orders o
        JOIN usuarios u ON o.usuario_id = u.user_id
        LEFT JOIN stores s ON o.store_id = s.store_id
        WHERE o.order_id = ?";

if (!$es_admin) {
    $sql .= " AND o.usuario_id = ?";
}

$stmt = $conn->prepare($sql);

if (!$es_admin) {
    $stmt->bind_param("ii", $order_id, $_SESSION['user_id']);
} else {
    $stmt->bind_param("i", $order_id);
}

$stmt->execute();
$orden = $stmt->get_result()->fetch_assoc();

if (!$orden) {
    header('Location: index.php');
    exit();
}

// Obtener items de la orden
$sql_items = "SELECT oi.*, p.product_name, p.foto 
              FROM order_items oi
              JOIN productos p ON oi.product_id = p.product_id
              WHERE oi.order_id = ?";
$stmt_items = $conn->prepare($sql_items);
$stmt_items->bind_param("i", $order_id);
$stmt_items->execute();
$items = $stmt_items->get_result();

function getBadgeEstado($estado) {
    $badges = [
        'pendiente' => 'bg-warning text-dark',
        'en_espera' => 'bg-info',
        'confirmado' => 'bg-primary',
        'enviado' => 'bg-purple',
        'entregado' => 'bg-success',
        'anulado' => 'bg-danger'
    ];
    return $badges[$estado] ?? 'bg-secondary';
}

function getIconoEstado($estado) {
    $iconos = [
        'pendiente' => 'hourglass-split',
        'en_espera' => 'clock',
        'confirmado' => 'check-circle',
        'enviado' => 'truck',
        'entregado' => 'box-seam',
        'anulado' => 'x-circle'
    ];
    return $iconos[$estado] ?? 'circle';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle Orden #<?php echo $order_id; ?> - Bike Store</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .bg-purple {
            background-color: #6f42c1;
        }
        .order-timeline {
            position: relative;
            padding-left: 30px;
        }
        .order-timeline::before {
            content: '';
            position: absolute;
            left: 10px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #dee2e6;
        }
        .timeline-item {
            position: relative;
            padding-bottom: 20px;
        }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -24px;
            top: 5px;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: #fff;
            border: 3px solid #dee2e6;
        }
        .timeline-item.active::before {
            border-color: #0d6efd;
            background: #0d6efd;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            .card {
                border: 1px solid #000 !important;
            }
        }
        .store-info-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
        }
        .store-info-box h5 {
            border-bottom: 2px solid rgba(255,255,255,0.3);
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4 no-print">
                    <h2><i class="bi bi-receipt"></i> Detalle de Orden #<?php echo $order_id; ?></h2>
                    <div>
                        <button onclick="window.print()" class="btn btn-primary">
                            <i class="bi bi-printer"></i> Imprimir
                        </button>
                        <button onclick="window.close()" class="btn btn-secondary">
                            <i class="bi bi-x"></i> Cerrar
                        </button>
                    </div>
                </div>

                <!-- INFORMACIÓN DE LA TIENDA -->
                <?php if ($orden['store_name']): ?>
                <div class="store-info-box">
                    <h5><i class="bi bi-shop"></i> Información de la Tienda de Retiro</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <p class="mb-2">
                                <i class="bi bi-building"></i> 
                                <strong>Tienda:</strong> <?php echo $orden['store_name']; ?>
                            </p>
                            <p class="mb-2">
                                <i class="bi bi-geo-alt-fill"></i> 
                                <strong>Dirección:</strong><br>
                                <?php echo $orden['street']; ?><br>
                                <?php echo $orden['city']; ?>, <?php echo $orden['state']; ?>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <?php if ($orden['store_phone']): ?>
                            <p class="mb-2">
                                <i class="bi bi-telephone-fill"></i> 
                                <strong>Teléfono:</strong> <?php echo $orden['store_phone']; ?>
                            </p>
                            <?php endif; ?>
                            <?php if ($orden['store_email']): ?>
                            <p class="mb-2">
                                <i class="bi bi-envelope-fill"></i> 
                                <strong>Email:</strong> <?php echo $orden['store_email']; ?>
                            </p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="alert alert-light mt-3 mb-0">
                        <i class="bi bi-info-circle"></i> 
                        <strong>Nota:</strong> Presenta este comprobante al retirar tu pedido en la tienda.
                    </div>
                </div>
                <?php endif; ?>

                <!-- Información de la orden -->
                <div class="card shadow mb-4">
                    <div class="card-header bg-dark text-white">
                        <div class="row">
                            <div class="col-md-6">
                                <h4 class="mb-0">Orden #<?php echo $order_id; ?></h4>
                            </div>
                            <div class="col-md-6 text-end">
                                <span class="badge fs-5 <?php echo getBadgeEstado($orden['estado']); ?>">
                                    <i class="bi bi-<?php echo getIconoEstado($orden['estado']); ?>"></i>
                                    <?php echo ucfirst(str_replace('_', ' ', $orden['estado'])); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6><i class="bi bi-person"></i> Información del Cliente</h6>
                                <table class="table table-sm">
                                    <tr>
                                        <th>Cliente:</th>
                                        <td><?php echo $orden['usuario']; ?></td>
                                    </tr>
                                    <tr>
                                        <th>Email:</th>
                                        <td><?php echo $orden['email']; ?></td>
                                    </tr>
                                    <tr>
                                        <th>ID Cliente:</th>
                                        <td>#<?php echo $orden['customer_id']; ?></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h6><i class="bi bi-calendar"></i> Información del Pedido</h6>
                                <table class="table table-sm">
                                    <tr>
                                        <th>Fecha:</th>
                                        <td><?php echo date('d/m/Y H:i:s', strtotime($orden['order_date'])); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Método de Pago:</th>
                                        <td>
                                            <?php 
                                            $metodo = isset($orden['payment_method']) ? $orden['payment_method'] : 'Manual';
                                            echo '<i class="bi bi-credit-card"></i> ' . $metodo;
                                            ?>
                                        </td>
                                    </tr>
                                    <?php if (isset($orden['payment_id']) && $orden['payment_id']): ?>
                                    <tr>
                                        <th>ID Transacción:</th>
                                        <td><small><?php echo $orden['payment_id']; ?></small></td>
                                    </tr>
                                    <?php endif; ?>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Timeline del pedido -->
                <div class="card shadow mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="bi bi-clock-history"></i> Estado del Pedido</h5>
                    </div>
                    <div class="card-body">
                        <div class="order-timeline">
                            <div class="timeline-item <?php echo in_array($orden['estado'], ['pendiente', 'en_espera', 'confirmado', 'enviado', 'entregado']) ? 'active' : ''; ?>">
                                <h6>⏳ Pedido Recibido</h6>
                                <p class="text-muted mb-0">
                                    <small><?php echo date('d/m/Y H:i', strtotime($orden['created_at'])); ?></small>
                                </p>
                            </div>
                            <div class="timeline-item <?php echo in_array($orden['estado'], ['confirmado', 'enviado', 'entregado']) ? 'active' : ''; ?>">
                                <h6>✅ Confirmado</h6>
                                <p class="text-muted mb-0"><small>Orden verificada y en proceso</small></p>
                            </div>
                            <div class="timeline-item <?php echo in_array($orden['estado'], ['enviado', 'entregado']) ? 'active' : ''; ?>">
                                <h6>📦 Preparado para Retiro</h6>
                                <p class="text-muted mb-0"><small>Tu pedido está listo en la tienda</small></p>
                            </div>
                            <div class="timeline-item <?php echo $orden['estado'] == 'entregado' ? 'active' : ''; ?>">
                                <h6>✔️ Entregado</h6>
                                <p class="text-muted mb-0"><small>Pedido completado</small></p>
                            </div>
                            <?php if ($orden['estado'] == 'anulado'): ?>
                            <div class="timeline-item active">
                                <h6>❌ Anulado</h6>
                                <p class="text-muted mb-0"><small>Este pedido fue cancelado</small></p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Productos -->
                <div class="card shadow mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="bi bi-cart-check"></i> Productos del Pedido</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 80px;">Imagen</th>
                                        <th>Producto</th>
                                        <th>Precio Unit.</th>
                                        <th>Cantidad</th>
                                        <th>Descuento</th>
                                        <th>Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($item = $items->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <?php if ($item['foto'] && file_exists($item['foto'])): ?>
                                            <img src="<?php echo $item['foto']; ?>" class="img-thumbnail" style="width: 60px; height: 60px; object-fit: cover;">
                                            <?php else: ?>
                                            <div class="bg-light p-2 text-center" style="width: 60px; height: 60px;">
                                                <i class="bi bi-bicycle" style="font-size: 30px;"></i>
                                            </div>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong><?php echo $item['product_name']; ?></strong></td>
                                        <td>Bs <?php echo number_format($item['price'], 2); ?></td>
                                        <td><span class="badge bg-secondary"><?php echo $item['quantity']; ?></span></td>
                                        <td><?php echo $item['discount']; ?>%</td>
                                        <td><strong class="text-success">Bs <?php echo number_format($item['subtotal'], 2); ?></strong></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <td colspan="5" class="text-end"><h5 class="mb-0">TOTAL:</h5></td>
                                        <td><h4 class="text-success mb-0">Bs <?php echo number_format($orden['total'], 2); ?></h4></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Instrucciones de Retiro -->
                <?php if ($orden['store_name'] && $orden['estado'] != 'anulado'): ?>
                <div class="card shadow mb-4">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0"><i class="bi bi-exclamation-triangle"></i> Instrucciones de Retiro</h5>
                    </div>
                    <div class="card-body">
                        <ol>
                            <li><strong>Dirígete a la tienda:</strong> <?php echo $orden['store_name']; ?> - <?php echo $orden['street']; ?>, <?php echo $orden['city']; ?></li>
                            <li><strong>Presenta este comprobante</strong> (impreso o en tu celular)</li>
                            <li><strong>Menciona el número de orden:</strong> #<?php echo $order_id; ?></li>
                            <li><strong>Lleva tu identificación</strong> para confirmar tu identidad</li>
                            <li><strong>Horario de atención:</strong> Lunes a Viernes 9:00 - 18:00, Sábados 9:00 - 13:00</li>
                        </ol>
                        <div class="alert alert-info mb-0">
                            <i class="bi bi-telephone"></i> 
                            <strong>¿Tienes dudas?</strong> Llama al <?php echo $orden['store_phone']; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Footer -->
                <div class="text-center text-muted no-print">
                    <p>Gracias por tu compra en Bike Store</p>
                    <p><small>Para cualquier consulta, contacta a nuestro servicio al cliente</small></p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>