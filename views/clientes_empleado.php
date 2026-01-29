<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../includes/config.php';
session_start();

// ============================================
// SEGURIDAD - Verificar autenticación y rol
// ============================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'empleado') {
    header('Location: ../index.php');
    exit();
}

$conn = getConnection();

// ============================================
// CAMBIAR ESTADO DE ORDEN
// ============================================
if (isset($_POST['cambiar_estado'])) {
    $order_id = (int)$_POST['order_id'];
    $nuevo_estado = $_POST['nuevo_estado'];
    
    // Validar que solo sean estados permitidos para empleados
    if (!in_array($nuevo_estado, ['entregado', 'anulado'])) {
        $_SESSION['swal'] = [
            'title' => 'Error',
            'text' => 'No tienes permiso para cambiar a ese estado',
            'icon' => 'error'
        ];
    } else {
        // Verificar que la orden existe
        $stmt = $conn->prepare("SELECT estado FROM orders WHERE order_id = ?");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $orden_actual = $stmt->get_result()->fetch_assoc();
        
        if ($orden_actual) {
            $sql = "UPDATE orders SET estado = ? WHERE order_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $nuevo_estado, $order_id);
            
            if ($stmt->execute()) {
                $_SESSION['swal'] = [
                    'title' => '¡Actualizado!',
                    'text' => 'Estado cambiado a: ' . ucfirst(str_replace('_', ' ', $nuevo_estado)),
                    'icon' => 'success'
                ];
            } else {
                $_SESSION['swal'] = [
                    'title' => 'Error',
                    'text' => 'No se pudo actualizar el estado',
                    'icon' => 'error'
                ];
            }
        } else {
            $_SESSION['swal'] = [
                'title' => 'Error',
                'text' => 'La orden no existe',
                'icon' => 'error'
            ];
        }
    }
    
    header("Location: clientes_empleado.php" . (isset($_GET['ver_cliente']) ? "?ver_cliente=" . (int)$_GET['ver_cliente'] : ""));
    exit();
}

// ============================================
// FILTROS
// ============================================
$filtro_estado = isset($_GET['estado']) ? $_GET['estado'] : '';
$filtro_cliente = isset($_GET['cliente']) ? (int)$_GET['cliente'] : '';
$buscar = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';

// Construir consulta con filtros
$where_clauses = ["u.role = 'usuario'"];
$params = [];
$types = '';

if ($filtro_estado) {
    $where_clauses[] = "o.estado = ?";
    $params[] = $filtro_estado;
    $types .= 's';
}

if ($filtro_cliente) {
    $where_clauses[] = "u.user_id = ?";
    $params[] = $filtro_cliente;
    $types .= 'i';
}

if ($buscar) {
    $where_clauses[] = "(u.usuario LIKE ? OR u.email LIKE ?)";
    $buscar_param = "%$buscar%";
    $params[] = $buscar_param;
    $params[] = $buscar_param;
    $types .= 'ss';
}

$where_sql = implode(' AND ', $where_clauses);

// ============================================
// OBTENER ESTADÍSTICAS DE CLIENTES
// ============================================
$sql_clientes = "SELECT 
                    u.user_id,
                    u.usuario,
                    u.email,
                    u.created_at,
                    COUNT(DISTINCT o.order_id) as total_ordenes,
                    COALESCE(SUM(o.total), 0) as total_gastado,
                    MAX(o.order_date) as ultima_compra,
                    (SELECT COUNT(*) FROM orders WHERE usuario_id = u.user_id AND estado IN ('en_espera', 'confirmado', 'enviado')) as ordenes_pendientes
                FROM usuarios u
                LEFT JOIN orders o ON u.user_id = o.usuario_id
                WHERE $where_sql
                GROUP BY u.user_id
                ORDER BY total_ordenes DESC, total_gastado DESC";

$stmt = $conn->prepare($sql_clientes);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$clientes = $stmt->get_result();

// Obtener todos los clientes para el filtro
$todos_clientes = $conn->query("SELECT user_id, usuario FROM usuarios WHERE role = 'usuario' ORDER BY usuario");

// ============================================
// DETALLE DE ORDEN ESPECÍFICA
// ============================================
$orden_detalle = null;
$items_orden = null;
if (isset($_GET['ver_orden'])) {
    $orden_id = (int)$_GET['ver_orden'];
    
    // Obtener datos de la orden con información del cliente
    $sql_orden = "SELECT o.*, u.usuario, u.email, s.store_name
                  FROM orders o
                  INNER JOIN usuarios u ON o.usuario_id = u.user_id
                  LEFT JOIN stores s ON o.store_id = s.store_id
                  WHERE o.order_id = ?";
    $stmt_orden = $conn->prepare($sql_orden);
    $stmt_orden->bind_param("i", $orden_id);
    $stmt_orden->execute();
    $orden_detalle = $stmt_orden->get_result()->fetch_assoc();
    
    if ($orden_detalle) {
        // Obtener items de la orden
        $sql_items = "SELECT oi.*, p.product_name, p.foto
                      FROM order_items oi
                      INNER JOIN productos p ON oi.product_id = p.product_id
                      WHERE oi.order_id = ?";
        $stmt_items = $conn->prepare($sql_items);
        $stmt_items->bind_param("i", $orden_id);
        $stmt_items->execute();
        $items_orden = $stmt_items->get_result();
    }
}

// ============================================
// DETALLE DE CLIENTE
// ============================================
$cliente_detalle = null;
$ordenes_cliente = null;
$tiene_ordenes = false;
if (isset($_GET['ver_cliente'])) {
    $cliente_id = (int)$_GET['ver_cliente'];
    
    $sql_detalle = "SELECT * FROM usuarios WHERE user_id = ? AND role = 'usuario'";
    $stmt_detalle = $conn->prepare($sql_detalle);
    $stmt_detalle->bind_param("i", $cliente_id);
    $stmt_detalle->execute();
    $cliente_detalle = $stmt_detalle->get_result()->fetch_assoc();
    
    if ($cliente_detalle) {
        // Obtener órdenes ordenadas por fecha descendente (más recientes primero)
        $sql_ordenes = "SELECT * FROM orders WHERE usuario_id = ? ORDER BY order_date DESC";
        $stmt_ordenes = $conn->prepare($sql_ordenes);
        $stmt_ordenes->bind_param("i", $cliente_id);
        $stmt_ordenes->execute();
        $ordenes_cliente = $stmt_ordenes->get_result();
        $tiene_ordenes = $ordenes_cliente->num_rows > 0;
    }
}

// ============================================
// FUNCIONES AUXILIARES
// ============================================
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
        'pendiente' => 'bi-clock-history',
        'en_espera' => 'bi-hourglass-split',
        'confirmado' => 'bi-check-circle',
        'enviado' => 'bi-truck',
        'entregado' => 'bi-check-circle-fill',
        'anulado' => 'bi-x-circle'
    ];
    return $iconos[$estado] ?? 'bi-question-circle';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Clientes - Empleado</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css" rel="stylesheet">
    <style>
        .cliente-card {
            transition: all 0.3s;
            cursor: pointer;
        }
        .cliente-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .stat-card {
            border-left: 4px solid;
        }
        .bg-purple {
            background-color: #6f42c1;
        }
        .estado-badge {
            min-width: 120px;
            display: inline-block;
        }
        .comprobante-preview {
            max-width: 300px;
            max-height: 400px;
            cursor: pointer;
            border-radius: 8px;
            transition: transform 0.2s;
        }
        .comprobante-preview:hover {
            transform: scale(1.02);
        }
        .producto-mini-img {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 5px;
        }
        .modal-comprobante .modal-dialog {
            max-width: 800px;
        }
        .modal-comprobante .modal-body {
            padding: 20px;
            text-align: center;
        }
        .modal-comprobante .modal-body img {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
        }
    </style>
</head>
<body>

<?php include '../includes/navbar.php'; ?>

<div class="container-fluid my-4">

<?php if ($orden_detalle): ?>
<!-- ============================================
     VISTA DETALLADA DE ORDEN
============================================ -->
<div class="mb-3">
    <a href="clientes_empleado.php?ver_cliente=<?= htmlspecialchars($orden_detalle['usuario_id']) ?>" 
       class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Volver al cliente
    </a>
</div>

<div class="card shadow mb-4">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="bi bi-receipt"></i> Detalle de Orden #<?= htmlspecialchars($orden_detalle['order_id']) ?>
        </h5>
        <button class="btn btn-sm btn-warning btn-cambiar-estado" 
                data-order-id="<?= $orden_detalle['order_id'] ?>"
                data-estado-actual="<?= $orden_detalle['estado'] ?>">
            <i class="bi bi-pencil"></i> Cambiar Estado
        </button>
    </div>
    <div class="card-body">
        <div class="row">
            <!-- Columna 1: Info cliente y orden -->
            <div class="col-md-4">
                <h6 class="text-primary mb-3"><i class="bi bi-person"></i> Información del Cliente</h6>
                <table class="table table-sm table-bordered">
                    <tr>
                        <th width="40%">Cliente:</th>
                        <td><?= htmlspecialchars($orden_detalle['usuario']) ?></td>
                    </tr>
                    <tr>
                        <th>Email:</th>
                        <td><?= htmlspecialchars($orden_detalle['email']) ?></td>
                    </tr>
                    <tr>
                        <th>Orden ID:</th>
                        <td><strong>#<?= htmlspecialchars($orden_detalle['order_id']) ?></strong></td>
                    </tr>
                    <tr>
                        <th>Fecha:</th>
                        <td><?= date('d/m/Y H:i', strtotime($orden_detalle['order_date'])) ?></td>
                    </tr>
                    <tr>
                        <th>Tienda:</th>
                        <td><?= $orden_detalle['store_name'] ? htmlspecialchars($orden_detalle['store_name']) : 'No asignada' ?></td>
                    </tr>
                    <tr>
                        <th>Método:</th>
                        <td><?= htmlspecialchars($orden_detalle['payment_method']) ?></td>
                    </tr>
                    <tr>
                        <th>Estado:</th>
                        <td>
                            <span class="badge <?= getBadgeEstado($orden_detalle['estado']) ?>">
                                <i class="bi <?= getIconoEstado($orden_detalle['estado']) ?>"></i>
                                <?= ucfirst(str_replace('_', ' ', $orden_detalle['estado'])) ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th>Total:</th>
                        <td><strong class="text-success fs-5">Bs <?= number_format($orden_detalle['total'], 2) ?></strong></td>
                    </tr>
                </table>

                <h6 class="text-success mb-3 mt-4"><i class="bi bi-file-earmark-check"></i> Comprobante de Pago</h6>
                <div class="text-center">
                    <?php if(!empty($orden_detalle['payment_id']) && file_exists($orden_detalle['payment_id'])): ?>
                    <img src="<?= htmlspecialchars($orden_detalle['payment_id']) ?>" 
                         class="comprobante-preview img-fluid mb-2" 
                         alt="Comprobante"
                         onclick="verComprobanteModal(this.src)">
                    <div class="alert alert-success mb-0">
                        <small><i class="bi bi-check-circle"></i> Click en la imagen para ampliar</small>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i> Sin comprobante
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Columna 2: Productos -->
            <div class="col-md-8">
                <h6 class="text-primary mb-3"><i class="bi bi-box-seam"></i> Productos de la Orden</h6>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 60px;">Imagen</th>
                                <th>Producto</th>
                                <th>Cantidad</th>
                                <th>Precio Unit.</th>
                                <th>Desc.</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $total_calculado = 0;
                            while($item = $items_orden->fetch_assoc()): 
                                $subtotal = $item['quantity'] * $item['price'] * (1 - $item['discount'] / 100);
                                $total_calculado += $subtotal;
                            ?>
                            <tr>
                                <td>
                                    <?php if(!empty($item['foto']) && file_exists($item['foto'])): ?>
                                    <img src="<?= htmlspecialchars($item['foto']) ?>" 
                                         class="producto-mini-img" 
                                         alt="<?= htmlspecialchars($item['product_name']) ?>">
                                    <?php else: ?>
                                    <div class="producto-mini-img bg-light d-flex align-items-center justify-content-center">
                                        <i class="bi bi-image text-muted"></i>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($item['product_name']) ?></td>
                                <td><span class="badge bg-secondary"><?= $item['quantity'] ?></span></td>
                                <td>Bs <?= number_format($item['price'], 2) ?></td>
                                <td>
                                    <?php if($item['discount'] > 0): ?>
                                    <span class="badge bg-danger"><?= $item['discount'] ?>%</span>
                                    <?php else: ?>
                                    -
                                    <?php endif; ?>
                                </td>
                                <td><strong class="text-success">Bs <?= number_format($subtotal, 2) ?></strong></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <td colspan="5" class="text-end"><strong>TOTAL:</strong></td>
                                <td><strong class="text-success fs-5">Bs <?= number_format($orden_detalle['total'], 2) ?></strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php elseif ($cliente_detalle): ?>
<!-- ============================================
     DETALLE DE CLIENTE
============================================ -->
<div class="card shadow mb-4">
    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="bi bi-person-badge"></i> Detalle del Cliente: <?= htmlspecialchars($cliente_detalle['usuario']) ?>
        </h5>
        <a href="clientes_empleado.php" class="btn btn-sm btn-light">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>
    <div class="card-body">
        <div class="row mb-4">
            <div class="col-md-6">
                <h6><i class="bi bi-info-circle"></i> Información del Cliente</h6>
                <table class="table table-sm table-bordered">
                    <tr>
                        <th width="40%">Usuario:</th>
                        <td><?= htmlspecialchars($cliente_detalle['usuario']) ?></td>
                    </tr>
                    <tr>
                        <th>Email:</th>
                        <td><?= htmlspecialchars($cliente_detalle['email']) ?></td>
                    </tr>
                    <tr>
                        <th>Registrado:</th>
                        <td><?= date('d/m/Y H:i', strtotime($cliente_detalle['created_at'])) ?></td>
                    </tr>
                </table>
            </div>
            <div class="col-md-6">
                <h6><i class="bi bi-graph-up"></i> Estadísticas</h6>
                <?php
                $stats_query = $conn->prepare("SELECT 
                    COUNT(*) as total_ordenes,
                    COALESCE(SUM(total), 0) as total_gastado
                    FROM orders WHERE usuario_id = ?");
                $stats_query->bind_param("i", $cliente_detalle['user_id']);
                $stats_query->execute();
                $stats = $stats_query->get_result()->fetch_assoc();
                ?>
                <div class="row">
                    <div class="col-6">
                        <div class="stat-card card border-primary">
                            <div class="card-body text-center">
                                <h3 class="text-primary"><?= $stats['total_ordenes'] ?></h3>
                                <small>Órdenes Totales</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="stat-card card border-success">
                            <div class="card-body text-center">
                                <h3 class="text-success">Bs <?= number_format($stats['total_gastado'], 2) ?></h3>
                                <small>Total Gastado</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <h6><i class="bi bi-receipt"></i> Historial de Pedidos</h6>
        
        <?php if ($tiene_ordenes): ?>
        <div class="table-responsive">
            <table id="tablaOrdenes" class="table table-hover table-striped nowrap" style="width:100%">
                <thead class="table-dark">
                    <tr>
                        <th>Orden #</th>
                        <th>Fecha</th>
                        <th>Total</th>
                        <th>Estado</th>
                        <th>Método Pago</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $ordenes_cliente->data_seek(0);
                    while($orden = $ordenes_cliente->fetch_assoc()): 
                    ?>
                    <tr>
                        <td><strong>#<?= htmlspecialchars($orden['order_id']) ?></strong></td>
                        <td><?= date('d/m/Y H:i', strtotime($orden['order_date'])) ?></td>
                        <td><strong class="text-success">Bs <?= number_format($orden['total'], 2) ?></strong></td>
                        <td>
                            <span class="badge estado-badge <?= getBadgeEstado($orden['estado']) ?>">
                                <?= ucfirst(str_replace('_', ' ', $orden['estado'])) ?>
                            </span>
                        </td>
                        <td>
                            <?= htmlspecialchars($orden['payment_method'] ?? 'Manual') ?>
                        </td>
                        <td class="text-nowrap">
                            <a href="?ver_orden=<?= $orden['order_id'] ?>" 
                               class="btn btn-sm btn-primary"
                               title="Ver detalle completo">
                                <i class="bi bi-eye"></i> Ver Detalle
                            </a>
                            <button class="btn btn-sm btn-warning btn-cambiar-estado" 
                                    data-order-id="<?= $orden['order_id'] ?>"
                                    data-estado-actual="<?= $orden['estado'] ?>">
                                <i class="bi bi-pencil"></i> Cambiar
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i> Este cliente aún no ha realizado ninguna compra.
        </div>
        <?php endif; ?>
    </div>
</div>

<?php else: ?>
<!-- ============================================
     VISTA PRINCIPAL - LISTA DE CLIENTES
============================================ -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="bi bi-people-fill"></i> Gestión de Clientes y Pedidos</h1>
    <span class="badge bg-info fs-6">Panel de Empleado</span>
</div>

<!-- Filtros -->
<div class="card shadow mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="bi bi-funnel"></i> Filtros de Búsqueda</h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Estado de Órdenes</label>
                <select class="form-select" name="estado">
                    <option value="">Todos los estados</option>
                    <option value="en_espera" <?= $filtro_estado == 'en_espera' ? 'selected' : '' ?>>En Espera</option>
                    <option value="confirmado" <?= $filtro_estado == 'confirmado' ? 'selected' : '' ?>>Confirmado</option>
                    <option value="enviado" <?= $filtro_estado == 'enviado' ? 'selected' : '' ?>>Enviado</option>
                    <option value="entregado" <?= $filtro_estado == 'entregado' ? 'selected' : '' ?>>Entregado</option>
                    <option value="anulado" <?= $filtro_estado == 'anulado' ? 'selected' : '' ?>>Anulado</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Cliente Específico</label>
                <select class="form-select" name="cliente">
                    <option value="">Todos los clientes</option>
                    <?php 
                    $todos_clientes->data_seek(0);
                    while($cl = $todos_clientes->fetch_assoc()): 
                    ?>
                    <option value="<?= $cl['user_id'] ?>" <?= $filtro_cliente == $cl['user_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cl['usuario']) ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Buscar</label>
                <input type="text" class="form-control" name="buscar" 
                       placeholder="Usuario o email..." value="<?= htmlspecialchars($buscar) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search"></i> Buscar
                    </button>
                    <a href="clientes_empleado.php" class="btn btn-secondary">
                        <i class="bi bi-x-circle"></i> Limpiar
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Lista de Clientes -->
<div class="card shadow">
    <div class="card-header bg-dark text-white">
        <h5 class="mb-0"><i class="bi bi-list"></i> Lista de Clientes</h5>
    </div>
    <div class="card-body">
        <?php if ($clientes->num_rows > 0): ?>
        <div class="table-responsive">
            <table id="tablaClientes" class="table table-hover align-middle nowrap" style="width:100%">
                <thead class="table-light">
                    <tr>
                        <th>Cliente</th>
                        <th>Email</th>
                        <th>Registro</th>
                        <th>Órdenes</th>
                        <th>Pendientes</th>
                        <th>Total Gastado</th>
                        <th>Última Compra</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($cliente = $clientes->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <i class="bi bi-person-circle"></i>
                            <strong><?= htmlspecialchars($cliente['usuario']) ?></strong>
                        </td>
                        <td><?= htmlspecialchars($cliente['email']) ?></td>
                        <td><?= date('d/m/Y', strtotime($cliente['created_at'])) ?></td>
                        <td>
                            <span class="badge bg-primary"><?= $cliente['total_ordenes'] ?></span>
                        </td>
                        <td>
                            <?php if ($cliente['ordenes_pendientes'] > 0): ?>
                            <span class="badge bg-warning text-dark"><?= $cliente['ordenes_pendientes'] ?></span>
                            <?php else: ?>
                            <span class="badge bg-secondary">0</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong class="text-success">Bs <?= number_format($cliente['total_gastado'], 2) ?></strong>
                        </td>
                        <td>
                            <?= $cliente['ultima_compra'] ? date('d/m/Y', strtotime($cliente['ultima_compra'])) : 'Sin compras' ?>
                        </td>
                        <td class="text-nowrap">
                            <a href="?ver_cliente=<?= $cliente['user_id'] ?>" class="btn btn-sm btn-info">
                                <i class="bi bi-eye"></i> Ver Historial
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="text-center py-5">
            <i class="bi bi-inbox" style="font-size: 64px; color: #ccc;"></i>
            <p class="mt-3 text-muted">No se encontraron clientes con los filtros aplicados</p>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

</div>

<!-- Modal para comprobante en pantalla completa -->
<div class="modal fade modal-comprobante" id="modalComprobante" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-zoom-in"></i> Comprobante de Pago</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <img id="imgModalComprobante" alt="Comprobante">
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.0/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>

<script>
$(function(){
    // DataTable para clientes
    if ($('#tablaClientes').length) {
        $('#tablaClientes').DataTable({
            responsive: true,
            pageLength: 10,
            language: {url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'},
            columnDefs: [
                { responsivePriority: 1, targets: 0 },
                { responsivePriority: 2, targets: -1 }
            ],
            autoWidth: false
        });
    }

    // DataTable para órdenes SOLO SI existe la tabla Y tiene filas de datos
    if ($('#tablaOrdenes').length && $('#tablaOrdenes tbody tr').length > 0) {
        $('#tablaOrdenes').DataTable({
            responsive: true,
            pageLength: 10,
            order: [[1, 'desc']], // Columna de fecha, descendente
            language: {url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'},
            columnDefs: [
                { responsivePriority: 1, targets: 0 },
                { responsivePriority: 2, targets: -1 }
            ],
            autoWidth: false
        });
    }

    // Cambiar estado con SweetAlert (delegación de eventos)
    $(document).on('click', '.btn-cambiar-estado', function() {
        const orderId = $(this).data('order-id');
        const estadoActual = $(this).data('estado-actual');

        Swal.fire({
            title: 'Cambiar Estado',
            html: `
                <div class="text-start">
                    <p><strong>Orden #${orderId}</strong></p>
                    <p>Estado actual: <span class="badge bg-info">${estadoActual.replace('_', ' ')}</span></p>
                    <label class="form-label mt-3">Nuevo Estado:</label>
                    <select id="nuevoEstado" class="form-select">
                        <option value="">Seleccionar...</option>
                        <option value="entregado">📦 Entregado</option>
                        <option value="anulado">❌ Anulado</option>
                    </select>
                    <small class="text-muted">Solo puedes marcar como Entregado o Anulado</small>
                </div>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '<i class="bi bi-check-circle"></i> Actualizar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#0d6efd',
            preConfirm: () => {
                const nuevoEstado = document.getElementById('nuevoEstado').value;
                if (!nuevoEstado) {
                    Swal.showValidationMessage('Debes seleccionar un estado');
                    return false;
                }
                return nuevoEstado;
            }
        }).then((result) => {
            if (result.isConfirmed) {
                // Crear y enviar formulario
                const form = $('<form>', {
                    method: 'POST',
                    action: window.location.href
                });
                
                form.append($('<input>', {
                    type: 'hidden',
                    name: 'order_id',
                    value: orderId
                }));
                
                form.append($('<input>', {
                    type: 'hidden',
                    name: 'nuevo_estado',
                    value: result.value
                }));
                
                form.append($('<input>', {
                    type: 'hidden',
                    name: 'cambiar_estado',
                    value: '1'
                }));
                
                $('body').append(form);
                form.submit();
            }
        });
    });
});

// Función para mostrar comprobante en modal
function verComprobanteModal(src) {
    $('#imgModalComprobante').attr('src', src);
    new bootstrap.Modal('#modalComprobante').show();
}
</script>

<?php if(isset($_SESSION['swal'])): ?>
<script>
Swal.fire(<?= json_encode($_SESSION['swal']) ?>);
</script>
<?php unset($_SESSION['swal']); endif; ?>

</body>
</html>
<?php $conn->close(); ?>