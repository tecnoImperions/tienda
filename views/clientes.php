<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../includes/config.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../index.php');
    exit();
}

$conn = getConnection();
$mensaje = '';
$tipo_mensaje = '';

// CAMBIAR ESTADO DE ORDEN
if (isset($_POST['cambiar_estado'])) {
    $order_id = $_POST['order_id'];
    $nuevo_estado = $_POST['nuevo_estado'];
    
    $sql = "UPDATE orders SET estado = ? WHERE order_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $nuevo_estado, $order_id);
    
    if ($stmt->execute()) {
        // Guardar mensaje en sesión para mostrarlo después del redirect
        $_SESSION['mensaje'] = "Estado actualizado a: " . ucfirst(str_replace('_', ' ', $nuevo_estado));
        $_SESSION['tipo_mensaje'] = "success";
        
        // Redirect para evitar reenvío de formulario
        $redirect_url = 'clientes.php';
        if (isset($_GET['ver_cliente'])) {
            $redirect_url .= '?ver_cliente=' . $_GET['ver_cliente'];
        }
        header("Location: $redirect_url");
        exit();
    } else {
        $mensaje = "Error al actualizar estado";
        $tipo_mensaje = "danger";
    }
}

// Mostrar mensaje de sesión si existe
if (isset($_SESSION['mensaje'])) {
    $mensaje = $_SESSION['mensaje'];
    $tipo_mensaje = $_SESSION['tipo_mensaje'];
    unset($_SESSION['mensaje']);
    unset($_SESSION['tipo_mensaje']);
}

// Filtros
$filtro_estado = isset($_GET['estado']) ? $_GET['estado'] : '';
$filtro_cliente = isset($_GET['cliente']) ? $_GET['cliente'] : '';
$buscar = isset($_GET['buscar']) ? $_GET['buscar'] : '';

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

// Obtener estadísticas de clientes
$sql_clientes = "SELECT 
                    u.user_id,
                    u.usuario,
                    u.email,
                    u.created_at,
                    COUNT(DISTINCT o.order_id) as total_ordenes,
                    COALESCE(SUM(o.total), 0) as total_gastado,
                    MAX(o.order_date) as ultima_compra,
                    (SELECT COUNT(*) FROM orders WHERE usuario_id = u.user_id AND estado = 'en_espera') as ordenes_pendientes
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

// Detalle de cliente
$cliente_detalle = null;
$ordenes_cliente = null;
if (isset($_GET['ver_cliente'])) {
    $cliente_id = intval($_GET['ver_cliente']);
    
    $sql_detalle = "SELECT * FROM usuarios WHERE user_id = ? AND role = 'usuario'";
    $stmt_detalle = $conn->prepare($sql_detalle);
    $stmt_detalle->bind_param("i", $cliente_id);
    $stmt_detalle->execute();
    $cliente_detalle = $stmt_detalle->get_result()->fetch_assoc();
    
    if ($cliente_detalle) {
        $sql_ordenes = "SELECT * FROM orders WHERE usuario_id = ? ORDER BY order_date DESC";
        $stmt_ordenes = $conn->prepare($sql_ordenes);
        $stmt_ordenes->bind_param("i", $cliente_id);
        $stmt_ordenes->execute();
        $ordenes_cliente = $stmt_ordenes->get_result();
    }
}

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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Clientes - Bike Store</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
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
        /* Evitar parpadeos en navegación */
        body {
            opacity: 1;
            transition: opacity 0.2s;
        }
        body.loading {
            opacity: 0.7;
        }
    </style>
</head>
<body>
<?php include '../includes/navbar_admin.php'; ?>

    <div class="container-fluid my-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="bi bi-people-fill"></i> Gestión de Clientes y Pedidos</h1>
            <span class="badge bg-warning text-dark">Panel Administrativo</span>
        </div>

        <?php if ($mensaje): ?>
        <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
            <?php echo $mensaje; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Filtros -->
        <div class="card shadow mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-funnel"></i> Filtros de Búsqueda</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="clientes.php" class="row g-3" id="filtrosForm">
                    <div class="col-md-3">
                        <label class="form-label">Estado de Órdenes</label>
                        <select class="form-select" name="estado">
                            <option value="">Todos los estados</option>
                            <option value="en_espera" <?php echo $filtro_estado == 'en_espera' ? 'selected' : ''; ?>>En Espera</option>
                            <option value="confirmado" <?php echo $filtro_estado == 'confirmado' ? 'selected' : ''; ?>>Confirmado</option>
                            <option value="enviado" <?php echo $filtro_estado == 'enviado' ? 'selected' : ''; ?>>Enviado</option>
                            <option value="entregado" <?php echo $filtro_estado == 'entregado' ? 'selected' : ''; ?>>Entregado</option>
                            <option value="anulado" <?php echo $filtro_estado == 'anulado' ? 'selected' : ''; ?>>Anulado</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Cliente Específico</label>
                        <select class="form-select" name="cliente">
                            <option value="">Todos los clientes</option>
                            <?php 
                            $todos_clientes->data_seek(0); // Reset pointer
                            while($cl = $todos_clientes->fetch_assoc()): 
                            ?>
                            <option value="<?php echo $cl['user_id']; ?>" <?php echo $filtro_cliente == $cl['user_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cl['usuario']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Buscar</label>
                        <input type="text" class="form-control" name="buscar" 
                               placeholder="Usuario o email..." value="<?php echo htmlspecialchars($buscar); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-search"></i> Buscar
                            </button>
                            <a href="clientes.php" class="btn btn-secondary">
                                <i class="bi bi-x-circle"></i> Limpiar
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($cliente_detalle): ?>
        <!-- Detalle de Cliente -->
        <div class="card shadow mb-4" id="detalleCliente">
            <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="bi bi-person-badge"></i> Detalle del Cliente: <?php echo htmlspecialchars($cliente_detalle['usuario']); ?>
                </h5>
                <a href="clientes.php<?php 
                    // Mantener filtros al cerrar
                    $params = [];
                    if ($filtro_estado) $params[] = 'estado=' . urlencode($filtro_estado);
                    if ($filtro_cliente) $params[] = 'cliente=' . urlencode($filtro_cliente);
                    if ($buscar) $params[] = 'buscar=' . urlencode($buscar);
                    echo $params ? '?' . implode('&', $params) : '';
                ?>" class="btn btn-sm btn-light">
                    <i class="bi bi-x"></i> Cerrar
                </a>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6><i class="bi bi-info-circle"></i> Información del Cliente</h6>
                        <table class="table table-sm">
                            <tr>
                                <th>Usuario:</th>
                                <td><?php echo htmlspecialchars($cliente_detalle['usuario']); ?></td>
                            </tr>
                            <tr>
                                <th>Email:</th>
                                <td><?php echo htmlspecialchars($cliente_detalle['email']); ?></td>
                            </tr>
                            <tr>
                                <th>Registrado:</th>
                                <td><?php echo date('d/m/Y H:i', strtotime($cliente_detalle['created_at'])); ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6><i class="bi bi-graph-up"></i> Estadísticas</h6>
                        <?php
                        $stats = $conn->query("SELECT 
                            COUNT(*) as total_ordenes,
                            COALESCE(SUM(total), 0) as total_gastado
                            FROM orders WHERE usuario_id = " . intval($cliente_detalle['user_id']))->fetch_assoc();
                        ?>
                        <div class="row">
                            <div class="col-6">
                                <div class="stat-card card border-primary">
                                    <div class="card-body text-center">
                                        <h3 class="text-primary"><?php echo $stats['total_ordenes']; ?></h3>
                                        <small>Órdenes Totales</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="stat-card card border-success">
                                    <div class="card-body text-center">
                                        <h3 class="text-success">Bs <?php echo number_format($stats['total_gastado'], 2); ?></h3>
                                        <small>Total Gastado</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <h6><i class="bi bi-receipt"></i> Historial de Pedidos</h6>
                <div class="table-responsive">
                    <table class="table table-hover">
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
                            <?php if ($ordenes_cliente && $ordenes_cliente->num_rows > 0): ?>
                                <?php while($orden = $ordenes_cliente->fetch_assoc()): ?>
                                <tr>
                                    <td><strong>#<?php echo $orden['order_id']; ?></strong></td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($orden['order_date'])); ?></td>
                                    <td><strong class="text-success">Bs <?php echo number_format($orden['total'], 2); ?></strong></td>
                                    <td>
                                        <span class="badge estado-badge <?php echo getBadgeEstado($orden['estado']); ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $orden['estado'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        $metodo = isset($orden['payment_method']) ? $orden['payment_method'] : 'Manual';
                                        echo htmlspecialchars($metodo);
                                        ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" 
                                                data-bs-target="#modalCambiarEstado<?php echo $orden['order_id']; ?>">
                                            <i class="bi bi-pencil"></i> Cambiar Estado
                                        </button>
                                        <a href="ver_orden_detalle.php?order_id=<?php echo $orden['order_id']; ?>" 
                                           class="btn btn-sm btn-info" target="_blank">
                                            <i class="bi bi-eye"></i> Ver Detalle
                                        </a>
                                    </td>
                                </tr>

                                <!-- Modal Cambiar Estado -->
                                <div class="modal fade" id="modalCambiarEstado<?php echo $orden['order_id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header bg-primary text-white">
                                                <h5 class="modal-title">Cambiar Estado - Orden #<?php echo $orden['order_id']; ?></h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form method="POST" action="clientes.php?ver_cliente=<?php echo $cliente_detalle['user_id']; ?>">
                                                <div class="modal-body">
                                                    <input type="hidden" name="order_id" value="<?php echo $orden['order_id']; ?>">
                                                    <div class="mb-3">
                                                        <label class="form-label">Estado Actual:</label>
                                                        <p><span class="badge <?php echo getBadgeEstado($orden['estado']); ?> fs-6">
                                                            <?php echo ucfirst(str_replace('_', ' ', $orden['estado'])); ?>
                                                        </span></p>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Nuevo Estado:</label>
                                                        <select class="form-select" name="nuevo_estado" required>
                                                            <option value="">Seleccionar...</option>
                                                            <option value="en_espera">⏳ En Espera</option>
                                                            <option value="confirmado">✅ Confirmado</option>
                                                            <option value="enviado">🚚 Enviado</option>
                                                            <option value="entregado">📦 Entregado</option>
                                                            <option value="anulado">❌ Anulado</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                    <button type="submit" name="cambiar_estado" class="btn btn-primary">
                                                        <i class="bi bi-check-circle"></i> Actualizar Estado
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted">No hay órdenes registradas</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Lista de Clientes -->
        <div class="card shadow">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="bi bi-list"></i> Lista de Clientes</h5>
            </div>
            <div class="card-body">
                <?php if ($clientes->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
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
                                    <strong><?php echo htmlspecialchars($cliente['usuario']); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($cliente['email']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($cliente['created_at'])); ?></td>
                                <td>
                                    <span class="badge bg-primary"><?php echo $cliente['total_ordenes']; ?></span>
                                </td>
                                <td>
                                    <?php if ($cliente['ordenes_pendientes'] > 0): ?>
                                    <span class="badge bg-warning text-dark"><?php echo $cliente['ordenes_pendientes']; ?></span>
                                    <?php else: ?>
                                    <span class="badge bg-secondary">0</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong class="text-success">Bs <?php echo number_format($cliente['total_gastado'], 2); ?></strong>
                                </td>
                                <td>
                                    <?php echo $cliente['ultima_compra'] ? date('d/m/Y', strtotime($cliente['ultima_compra'])) : 'Sin compras'; ?>
                                </td>
                                <td>
                                    <a href="clientes.php?ver_cliente=<?php echo $cliente['user_id']; ?><?php
                                        // Mantener filtros
                                        if ($filtro_estado) echo '&estado=' . urlencode($filtro_estado);
                                        if ($filtro_cliente) echo '&cliente=' . urlencode($filtro_cliente);
                                        if ($buscar) echo '&buscar=' . urlencode($buscar);
                                    ?>" class="btn btn-sm btn-info">
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
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Mejorar navegación del navegador
    (function() {
        // Scroll al detalle del cliente si existe
        <?php if ($cliente_detalle): ?>
        const detalleCliente = document.getElementById('detalleCliente');
        if (detalleCliente) {
            setTimeout(() => {
                detalleCliente.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 100);
        }
        <?php endif; ?>

        // Auto-cerrar alertas después de 5 segundos
        const alertas = document.querySelectorAll('.alert');
        alertas.forEach(alerta => {
            setTimeout(() => {
                const bsAlert = new bootstrap.Alert(alerta);
                bsAlert.close();
            }, 5000);
        });

        // Prevenir doble envío de formularios
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            form.addEventListener('submit', function(e) {
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Procesando...';
                }
            });
        });

        // Mejorar experiencia con modales
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('hidden.bs.modal', function() {
                const form = this.querySelector('form');
                if (form) {
                    const submitBtn = form.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<i class="bi bi-check-circle"></i> Actualizar Estado';
                    }
                }
            });
        });

        // Gestión del estado del historial del navegador
        window.addEventListener('pageshow', function(event) {
            if (event.persisted) {
                // Página cargada desde caché (botón atrás)
                window.location.reload();
            }
        });
    })();
    </script>
</body>
</html>
<?php $conn->close(); ?>