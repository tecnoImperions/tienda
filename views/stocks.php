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

// CREAR o ACTUALIZAR Stock
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['guardar_stock'])) {
    $store_id = intval($_POST['store_id']);
    $product_id = intval($_POST['product_id']);
    $quantity = intval($_POST['quantity']);
    
    // Verificar si ya existe el stock
    $check = $conn->prepare("SELECT stock_id FROM stocks WHERE store_id = ? AND product_id = ?");
    $check->bind_param("ii", $store_id, $product_id);
    $check->execute();
    $result_check = $check->get_result();
    
    if ($result_check->num_rows > 0) {
        // ACTUALIZAR
        $sql = "UPDATE stocks SET quantity = ? WHERE store_id = ? AND product_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iii", $quantity, $store_id, $product_id);
        
        if ($stmt->execute()) {
            $_SESSION['mensaje'] = "Stock actualizado exitosamente";
            $_SESSION['tipo_mensaje'] = "success";
        } else {
            $_SESSION['mensaje'] = "Error al actualizar: " . $conn->error;
            $_SESSION['tipo_mensaje'] = "danger";
        }
    } else {
        // CREAR
        $sql = "INSERT INTO stocks (store_id, product_id, quantity) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iii", $store_id, $product_id, $quantity);
        
        if ($stmt->execute()) {
            $_SESSION['mensaje'] = "Stock creado exitosamente";
            $_SESSION['tipo_mensaje'] = "success";
        } else {
            $_SESSION['mensaje'] = "Error al crear: " . $conn->error;
            $_SESSION['tipo_mensaje'] = "danger";
        }
    }
    
    // Construir URL de redirect con filtros
    $redirect_url = 'stocks.php?';
    $params = [];
    if (isset($_POST['filtro_tienda']) && $_POST['filtro_tienda']) {
        $params[] = 'tienda=' . urlencode($_POST['filtro_tienda']);
    }
    if (isset($_POST['filtro_producto']) && $_POST['filtro_producto']) {
        $params[] = 'producto=' . urlencode($_POST['filtro_producto']);
    }
    if (isset($_POST['filtro_modo']) && $_POST['filtro_modo']) {
        $params[] = 'modo=' . urlencode($_POST['filtro_modo']);
    }
    
    $redirect_url .= implode('&', $params);
    header("Location: " . $redirect_url);
    exit();
}

// ELIMINAR Stock
if (isset($_GET['eliminar'])) {
    $stock_id = intval($_GET['eliminar']);
    
    $sql = "DELETE FROM stocks WHERE stock_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $stock_id);
    
    if ($stmt->execute()) {
        $_SESSION['mensaje'] = "Stock eliminado exitosamente";
        $_SESSION['tipo_mensaje'] = "success";
    } else {
        $_SESSION['mensaje'] = "Error al eliminar: " . $conn->error;
        $_SESSION['tipo_mensaje'] = "danger";
    }
    
    // Redirect manteniendo filtros
    $redirect_url = 'stocks.php?';
    $params = [];
    if (isset($_GET['tienda']) && $_GET['tienda']) {
        $params[] = 'tienda=' . urlencode($_GET['tienda']);
    }
    if (isset($_GET['producto']) && $_GET['producto']) {
        $params[] = 'producto=' . urlencode($_GET['producto']);
    }
    if (isset($_GET['modo']) && $_GET['modo']) {
        $params[] = 'modo=' . urlencode($_GET['modo']);
    }
    
    $redirect_url .= implode('&', $params);
    header("Location: " . $redirect_url);
    exit();
}

// Mostrar mensaje de sesión si existe
if (isset($_SESSION['mensaje'])) {
    $mensaje = $_SESSION['mensaje'];
    $tipo_mensaje = $_SESSION['tipo_mensaje'];
    unset($_SESSION['mensaje']);
    unset($_SESSION['tipo_mensaje']);
}

// Filtros
$filtro_tienda = isset($_GET['tienda']) ? intval($_GET['tienda']) : 0;
$filtro_producto = isset($_GET['producto']) ? intval($_GET['producto']) : 0;
$ver_modo = isset($_GET['modo']) ? $_GET['modo'] : 'lista'; // lista o tabla

// Validar modo
if (!in_array($ver_modo, ['lista', 'tabla'])) {
    $ver_modo = 'lista';
}

// Obtener tiendas activas
$tiendas = $conn->query("SELECT * FROM stores WHERE estado = 'activa' ORDER BY store_name");

// Obtener productos
$productos = $conn->query("SELECT * FROM productos ORDER BY product_name");

// Construir consulta de stocks
$where_clauses = ["s.estado = 'activa'"];
$params = [];
$types = '';

if ($filtro_tienda) {
    $where_clauses[] = "s.store_id = ?";
    $params[] = $filtro_tienda;
    $types .= 'i';
}

if ($filtro_producto) {
    $where_clauses[] = "p.product_id = ?";
    $params[] = $filtro_producto;
    $types .= 'i';
}

$where_sql = implode(' AND ', $where_clauses);

if ($ver_modo == 'tabla') {
    // Modo Tabla: Productos vs Tiendas
    $sql_productos = "SELECT * FROM productos ORDER BY product_name";
    $productos_list = $conn->query($sql_productos);
    
    $sql_tiendas = "SELECT * FROM stores WHERE estado = 'activa' ORDER BY store_name";
    $tiendas_list = $conn->query($sql_tiendas);
} else {
    // Modo Lista: Vista detallada
    $sql = "SELECT 
                st.*,
                s.store_name,
                s.city,
                p.product_name,
                p.price,
                (st.quantity * p.price) as valor_total
            FROM stocks st
            JOIN stores s ON st.store_id = s.store_id
            JOIN productos p ON st.product_id = p.product_id
            WHERE $where_sql
            ORDER BY s.store_name, p.product_name";
    
    if (!empty($params)) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($sql);
    }
}

// Función para construir URL con filtros
function buildUrlWithFilters($base_params = []) {
    global $filtro_tienda, $filtro_producto, $ver_modo;
    
    $params = $base_params;
    if ($filtro_tienda) $params['tienda'] = $filtro_tienda;
    if ($filtro_producto) $params['producto'] = $filtro_producto;
    if ($ver_modo && $ver_modo != 'lista') $params['modo'] = $ver_modo;
    
    if (empty($params)) return '';
    
    return '?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Stocks - Bike Store</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        .stock-table td, .stock-table th {
            text-align: center;
            vertical-align: middle;
        }
        .stock-low {
            background-color: #fff3cd;
            font-weight: bold;
        }
        .stock-out {
            background-color: #f8d7da;
            font-weight: bold;
        }
        .stock-good {
            background-color: #d1e7dd;
        }
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
            <h1>
                <i class="bi bi-boxes"></i> Gestión de Stocks por Tienda
            </h1>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNuevoStock">
                <i class="bi bi-plus-circle"></i> Nuevo Stock
            </button>
        </div>

        <?php if ($mensaje): ?>
        <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($mensaje); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Filtros y Opciones de Vista -->
        <div class="card shadow mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-funnel"></i> Filtros y Opciones</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="stocks.php" class="row g-3" id="filtrosForm">
                    <div class="col-md-3">
                        <label class="form-label">Tienda</label>
                        <select class="form-select" name="tienda">
                            <option value="">Todas las tiendas</option>
                            <?php 
                            $tiendas->data_seek(0);
                            while($t = $tiendas->fetch_assoc()): 
                            ?>
                            <option value="<?php echo $t['store_id']; ?>" <?php echo $filtro_tienda == $t['store_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($t['store_name']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Producto</label>
                        <select class="form-select" name="producto">
                            <option value="">Todos los productos</option>
                            <?php 
                            $productos->data_seek(0);
                            while($p = $productos->fetch_assoc()): 
                            ?>
                            <option value="<?php echo $p['product_id']; ?>" <?php echo $filtro_producto == $p['product_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($p['product_name']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Modo de Vista</label>
                        <select class="form-select" name="modo">
                            <option value="lista" <?php echo $ver_modo == 'lista' ? 'selected' : ''; ?>>📋 Lista Detallada</option>
                            <option value="tabla" <?php echo $ver_modo == 'tabla' ? 'selected' : ''; ?>>📊 Tabla (Productos x Tiendas)</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-search"></i> Buscar
                            </button>
                            <a href="stocks.php" class="btn btn-secondary">
                                <i class="bi bi-x-circle"></i> Limpiar
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($ver_modo == 'tabla'): ?>
        <!-- MODO TABLA: Productos x Tiendas -->
        <div class="card shadow">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="bi bi-grid-3x3"></i> Existencias por Tienda</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered stock-table">
                        <thead class="table-dark">
                            <tr>
                                <th style="min-width: 200px;">Producto</th>
                                <?php 
                                $tiendas_list->data_seek(0);
                                while($tienda = $tiendas_list->fetch_assoc()): 
                                ?>
                                <th><?php echo htmlspecialchars($tienda['store_name']); ?><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($tienda['city']); ?></small>
                                </th>
                                <?php endwhile; ?>
                                <th>TOTAL</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $productos_list->data_seek(0);
                            while($producto = $productos_list->fetch_assoc()): 
                            ?>
                            <tr>
                                <td class="text-start">
                                    <strong><?php echo htmlspecialchars($producto['product_name']); ?></strong><br>
                                    <small class="text-muted">Bs <?php echo number_format($producto['price'], 2); ?></small>
                                </td>
                                <?php
                                $tiendas_list->data_seek(0);
                                $total_producto = 0;
                                while($tienda = $tiendas_list->fetch_assoc()):
                                    $sql_stock = "SELECT quantity FROM stocks WHERE store_id = ? AND product_id = ?";
                                    $stmt_stock = $conn->prepare($sql_stock);
                                    $stmt_stock->bind_param("ii", $tienda['store_id'], $producto['product_id']);
                                    $stmt_stock->execute();
                                    $result_stock = $stmt_stock->get_result();
                                    $stock_data = $result_stock->fetch_assoc();
                                    $cantidad = $stock_data ? intval($stock_data['quantity']) : 0;
                                    $total_producto += $cantidad;
                                    
                                    $class = '';
                                    if ($cantidad == 0) $class = 'stock-out';
                                    elseif ($cantidad < 5) $class = 'stock-low';
                                    elseif ($cantidad >= 10) $class = 'stock-good';
                                ?>
                                <td class="<?php echo $class; ?>">
                                    <?php echo $cantidad; ?>
                                </td>
                                <?php endwhile; ?>
                                <td class="table-primary"><strong><?php echo $total_producto; ?></strong></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">
                    <span class="badge bg-success me-2">■ Stock Bueno (≥10)</span>
                    <span class="badge bg-warning text-dark me-2">■ Stock Bajo (1-4)</span>
                    <span class="badge bg-danger">■ Sin Stock (0)</span>
                </div>
            </div>
        </div>
        <?php else: ?>
        <!-- MODO LISTA: Vista Detallada -->
        <div class="card shadow">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="bi bi-list-ul"></i> Lista de Stocks</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Tienda</th>
                                <th>Producto</th>
                                <th>Precio Unit.</th>
                                <th>Cantidad</th>
                                <th>Valor Total</th>
                                <th>Última Act.</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result->num_rows > 0): ?>
                                <?php while($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $row['stock_id']; ?></td>
                                    <td>
                                        <i class="bi bi-shop text-primary"></i>
                                        <strong><?php echo htmlspecialchars($row['store_name']); ?></strong><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($row['city']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['product_name']); ?></td>
                                    <td>Bs <?php echo number_format($row['price'], 2); ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            if ($row['quantity'] == 0) echo 'danger';
                                            elseif ($row['quantity'] < 5) echo 'warning text-dark';
                                            elseif ($row['quantity'] >= 10) echo 'success';
                                            else echo 'secondary';
                                        ?> fs-6">
                                            <?php echo $row['quantity']; ?>
                                        </span>
                                    </td>
                                    <td><strong class="text-success">Bs <?php echo number_format($row['valor_total'], 2); ?></strong></td>
                                    <td><small><?php echo date('d/m/Y H:i', strtotime($row['updated_at'])); ?></small></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-warning" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#modalEditarStock<?php echo $row['stock_id']; ?>"
                                                    title="Editar">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button class="btn btn-danger btn-eliminar" 
                                               data-stock-id="<?php echo $row['stock_id']; ?>"
                                               data-producto="<?php echo htmlspecialchars($row['product_name']); ?>"
                                               data-tienda="<?php echo htmlspecialchars($row['store_name']); ?>"
                                               title="Eliminar">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>

                                <!-- Modal Editar Stock -->
                                <div class="modal fade" id="modalEditarStock<?php echo $row['stock_id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header bg-warning">
                                                <h5 class="modal-title">Editar Stock</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form method="POST" action="stocks.php<?php echo buildUrlWithFilters(); ?>">
                                                <div class="modal-body">
                                                    <input type="hidden" name="store_id" value="<?php echo $row['store_id']; ?>">
                                                    <input type="hidden" name="product_id" value="<?php echo $row['product_id']; ?>">
                                                    <input type="hidden" name="filtro_tienda" value="<?php echo $filtro_tienda; ?>">
                                                    <input type="hidden" name="filtro_producto" value="<?php echo $filtro_producto; ?>">
                                                    <input type="hidden" name="filtro_modo" value="<?php echo $ver_modo; ?>">
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label">Tienda</label>
                                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($row['store_name']); ?>" disabled>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Producto</label>
                                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($row['product_name']); ?>" disabled>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Cantidad *</label>
                                                        <input type="number" class="form-control" name="quantity" 
                                                               value="<?php echo $row['quantity']; ?>" min="0" required>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                    <button type="submit" name="guardar_stock" class="btn btn-warning">
                                                        <i class="bi bi-save"></i> Actualizar
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted">No hay stocks registrados</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Modal Nuevo Stock -->
    <div class="modal fade" id="modalNuevoStock" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Nuevo Stock</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="stocks.php<?php echo buildUrlWithFilters(); ?>">
                    <div class="modal-body">
                        <input type="hidden" name="filtro_tienda" value="<?php echo $filtro_tienda; ?>">
                        <input type="hidden" name="filtro_producto" value="<?php echo $filtro_producto; ?>">
                        <input type="hidden" name="filtro_modo" value="<?php echo $ver_modo; ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Tienda *</label>
                            <select class="form-select" name="store_id" required>
                                <option value="">Seleccionar...</option>
                                <?php 
                                $tiendas->data_seek(0);
                                while($t = $tiendas->fetch_assoc()): 
                                ?>
                                <option value="<?php echo $t['store_id']; ?>">
                                    <?php echo htmlspecialchars($t['store_name']); ?> - <?php echo htmlspecialchars($t['city']); ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Producto *</label>
                            <select class="form-select" name="product_id" required>
                                <option value="">Seleccionar...</option>
                                <?php 
                                $productos->data_seek(0);
                                while($p = $productos->fetch_assoc()): 
                                ?>
                                <option value="<?php echo $p['product_id']; ?>">
                                    <?php echo htmlspecialchars($p['product_name']); ?> - Bs <?php echo number_format($p['price'], 2); ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Cantidad *</label>
                            <input type="number" class="form-control" name="quantity" min="0" value="0" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" name="guardar_stock" class="btn btn-primary">
                            <i class="bi bi-save"></i> Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    (function() {
        // Mostrar alerta de sesión con SweetAlert2
        <?php if ($mensaje): ?>
        Swal.fire({
            icon: '<?php echo $tipo_mensaje == "success" ? "success" : "error"; ?>',
            title: '<?php echo $tipo_mensaje == "success" ? "¡Éxito!" : "Error"; ?>',
            text: '<?php echo htmlspecialchars($mensaje); ?>',
            timer: 3000,
            timerProgressBar: true,
            showConfirmButton: false,
            toast: true,
            position: 'top-end'
        });
        <?php endif; ?>

        // Confirmación de eliminación con SweetAlert2
        document.querySelectorAll('.btn-eliminar').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const stockId = this.dataset.stockId;
                const producto = this.dataset.producto;
                const tienda = this.dataset.tienda;
                
                Swal.fire({
                    title: '¿Eliminar Stock?',
                    html: `<p>Se eliminará el stock de:</p>
                           <p class="mb-1"><strong>Producto:</strong> ${producto}</p>
                           <p><strong>Tienda:</strong> ${tienda}</p>`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: '<i class="bi bi-trash"></i> Sí, eliminar',
                    cancelButtonText: '<i class="bi bi-x-circle"></i> Cancelar',
                    reverseButtons: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Mostrar loading
                        Swal.fire({
                            title: 'Eliminando...',
                            text: 'Por favor espere',
                            allowOutsideClick: false,
                            allowEscapeKey: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });
                        
                        // Redirigir para eliminar
                        const params = new URLSearchParams(window.location.search);
                        params.set('eliminar', stockId);
                        window.location.href = 'stocks.php?' + params.toString();
                    }
                });
            });
        });

        // Prevenir doble envío de formularios con mejor feedback
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            form.addEventListener('submit', function(e) {
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn && !submitBtn.disabled) {
                    submitBtn.disabled = true;
                    const originalHTML = submitBtn.innerHTML;
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Procesando...';
                    
                    // Mostrar SweetAlert de procesamiento
                    Swal.fire({
                        title: 'Guardando...',
                        text: 'Por favor espere',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                }
            });
        });

        // Restaurar estado de botones en modales
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('hidden.bs.modal', function() {
                const form = this.querySelector('form');
                if (form) {
                    const submitBtn = form.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        if (submitBtn.classList.contains('btn-warning')) {
                            submitBtn.innerHTML = '<i class="bi bi-save"></i> Actualizar';
                        } else if (submitBtn.classList.contains('btn-primary')) {
                            submitBtn.innerHTML = '<i class="bi bi-save"></i> Guardar';
                        }
                    }
                    if (this.id === 'modalNuevoStock') {
                        form.reset();
                    }
                }
            });
        });

        // Gestión del caché del navegador
        window.addEventListener('pageshow', function(event) {
            if (event.persisted) {
                window.location.reload();
            }
        });

        console.log('✅ Sistema de stocks cargado - Modo: <?php echo $ver_modo; ?>');
    })();
    </script>
</body>
</html>
<?php $conn->close(); ?>