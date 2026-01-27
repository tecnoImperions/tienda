<?php
require_once '../includes/config.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'empleado') {
    header('Location: ../index.php');
    exit();
}

$conn = getConnection();

// CAMBIAR ESTADO DE ORDEN (solo entregado y anulado)
if (isset($_POST['cambiar_estado'])) {
    $order_id = $_POST['order_id'];
    $nuevo_estado = $_POST['nuevo_estado'];
    
    // Validar que solo sean estados permitidos
    if (!in_array($nuevo_estado, ['entregado', 'anulado'])) {
        $_SESSION['swal'] = [
            'title' => 'Error',
            'text' => 'No tienes permiso para cambiar a ese estado',
            'icon' => 'error'
        ];
    } else {
        $sql = "UPDATE orders SET estado = ? WHERE order_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $nuevo_estado, $order_id);
        
        if ($stmt->execute()) {
            $_SESSION['swal'] = [
                'title' => '¡Actualizado!',
                'text' => 'Estado cambiado a: ' . ucfirst($nuevo_estado),
                'icon' => 'success'
            ];
        } else {
            $_SESSION['swal'] = [
                'title' => 'Error',
                'text' => 'No se pudo actualizar el estado',
                'icon' => 'error'
            ];
        }
    }
    
    header("Location: clientes_empleado.php" . (isset($_GET['ver_cliente']) ? "?ver_cliente=" . $_GET['ver_cliente'] : ""));
    exit();
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

// Detalle de cliente
$cliente_detalle = null;
$ordenes_cliente = null;
if (isset($_GET['ver_cliente'])) {
    $cliente_id = $_GET['ver_cliente'];
    
    $sql_detalle = "SELECT * FROM usuarios WHERE user_id = ?";
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
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="../index.php">
                <i class="bi bi-bicycle"></i> Bike Store
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../index.php">
                            <i class="bi bi-house"></i> Inicio
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="clientes_empleado.php">
                            <i class="bi bi-people"></i> Clientes
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i> <?php echo $_SESSION['usuario']; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><span class="dropdown-item-text">
                                <small class="text-muted"><i class="bi bi-briefcase"></i> Empleado</small>
                            </span></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../../api/auth/logout.php">
                                <i class="bi bi-box-arrow-right"></i> Cerrar Sesión
                            </a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid my-4">
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
                    <?php if(isset($_GET['ver_cliente'])): ?>
                    <input type="hidden" name="ver_cliente" value="<?php echo $_GET['ver_cliente']; ?>">
                    <?php endif; ?>
                    
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
                            <?php while($cl = $todos_clientes->fetch_assoc()): ?>
                            <option value="<?php echo $cl['user_id']; ?>" <?php echo $filtro_cliente == $cl['user_id'] ? 'selected' : ''; ?>>
                                <?php echo $cl['usuario']; ?>
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
                            <a href="clientes_empleado.php" class="btn btn-secondary">
                                <i class="bi bi-x-circle"></i> Limpiar
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($cliente_detalle): ?>
        <!-- Detalle de Cliente -->
        <div class="card shadow mb-4">
            <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="bi bi-person-badge"></i> Detalle del Cliente: <?php echo htmlspecialchars($cliente_detalle['usuario']); ?>
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
                            FROM orders WHERE usuario_id = " . $cliente_detalle['user_id'])->fetch_assoc();
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
                    <table id="tablaOrdenes" class="table table-hover table-striped nowrap">
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
                            <?php if ($ordenes_cliente->num_rows > 0): ?>
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
                                        <button class="btn btn-sm btn-warning btn-cambiar-estado" 
                                                data-order-id="<?php echo $orden['order_id']; ?>"
                                                data-estado-actual="<?php echo $orden['estado']; ?>">
                                            <i class="bi bi-pencil"></i> Cambiar
                                        </button>
                                        <a href="ver_orden_detalle.php?order_id=<?php echo $orden['order_id']; ?>" 
                                           class="btn btn-sm btn-info" target="_blank">
                                            <i class="bi bi-eye"></i> Ver
                                        </a>
                                    </td>
                                </tr>
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
                    <table id="tablaClientes" class="table table-hover align-middle nowrap">
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
                                    <a href="?ver_cliente=<?php echo $cliente['user_id']; ?>" class="btn btn-sm btn-info">
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

    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.0/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>

    <script>
    $(function(){
        // DataTable para clientes
        $('#tablaClientes').DataTable({
            responsive: true,
            pageLength: 10,
            language: {url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'}
        });

        // DataTable para órdenes
        $('#tablaOrdenes').DataTable({
            responsive: true,
            pageLength: 5,
            order: [[0, 'desc']],
            language: {url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'}
        });

        // Cambiar estado con SweetAlert
        $('.btn-cambiar-estado').on('click', function() {
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
                        action: ''
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
    </script>

    <?php if(isset($_SESSION['swal'])): ?>
    <script>
    Swal.fire(<?= json_encode($_SESSION['swal']) ?>);
    </script>
    <?php unset($_SESSION['swal']); endif; ?>

</body>
</html>
<?php $conn->close(); ?>