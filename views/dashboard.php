<?php
require_once '../includes/config.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: index.php');
    exit();
}

$conn = getConnection();

// CAPTURAR FILTROS
$filtro_fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : date('Y-m-01');
$filtro_fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : date('Y-m-d');
$filtro_estado = isset($_GET['estado']) ? $_GET['estado'] : '';
$filtro_tienda = isset($_GET['tienda']) ? $_GET['tienda'] : '';
$filtro_categoria = isset($_GET['categoria']) ? $_GET['categoria'] : '';

// Construir condiciones WHERE
$where_conditions = ["o.estado != 'anulado'"];
$where_params = [];

if (!empty($filtro_fecha_inicio) && !empty($filtro_fecha_fin)) {
    $where_conditions[] = "o.order_date BETWEEN '$filtro_fecha_inicio 00:00:00' AND '$filtro_fecha_fin 23:59:59'";
}

if (!empty($filtro_estado)) {
    $where_conditions[] = "o.estado = '$filtro_estado'";
}

if (!empty($filtro_tienda)) {
    $where_conditions[] = "o.store_id = $filtro_tienda";
}

$where_clause = implode(' AND ', $where_conditions);

// ESTADÍSTICAS GENERALES CON FILTROS
$stats = [];

// Total de ventas filtradas
$sql_ventas = "SELECT 
                COUNT(*) as total_ordenes,
                SUM(o.total) as total_ventas,
                AVG(o.total) as promedio_venta
               FROM orders o
               WHERE $where_clause";
$result_ventas = $conn->query($sql_ventas);
$stats['ventas'] = $result_ventas->fetch_assoc();

// Comparación con período anterior
$dias_rango = (strtotime($filtro_fecha_fin) - strtotime($filtro_fecha_inicio)) / 86400;
$fecha_inicio_anterior = date('Y-m-d', strtotime($filtro_fecha_inicio . " -$dias_rango days"));
$fecha_fin_anterior = date('Y-m-d', strtotime($filtro_fecha_inicio . " -1 day"));

$sql_anterior = "SELECT SUM(total) as total_anterior 
                 FROM orders 
                 WHERE order_date BETWEEN '$fecha_inicio_anterior' AND '$fecha_fin_anterior 23:59:59'
                 AND estado != 'anulado'";
$result_anterior = $conn->query($sql_anterior);
$anterior = $result_anterior->fetch_assoc();
$stats['crecimiento'] = 0;
if ($anterior['total_anterior'] > 0) {
    $stats['crecimiento'] = (($stats['ventas']['total_ventas'] - $anterior['total_anterior']) / $anterior['total_anterior']) * 100;
}

// Estadísticas de productos con filtro de categoría
$sql_productos = "SELECT 
                    COUNT(DISTINCT p.product_id) as total_productos,
                    SUM(CASE WHEN p.price > 10000 THEN 1 ELSE 0 END) as productos_premium
                  FROM productos p";
if (!empty($filtro_categoria)) {
    $sql_productos .= " WHERE p.category_id = $filtro_categoria";
}
$result_productos = $conn->query($sql_productos);
$stats['productos'] = $result_productos->fetch_assoc();

// Estadísticas de clientes únicos en el período
$sql_clientes = "SELECT 
                    COUNT(DISTINCT o.usuario_id) as clientes_periodo,
                    COUNT(DISTINCT CASE WHEN u.role = 'usuario' THEN u.user_id END) as total_clientes
                 FROM orders o
                 JOIN usuarios u ON o.usuario_id = u.user_id
                 WHERE $where_clause";
$result_clientes = $conn->query($sql_clientes);
$stats['clientes'] = $result_clientes->fetch_assoc();

// Estadísticas de tiendas
$sql_tiendas = "SELECT 
                    COUNT(DISTINCT o.store_id) as tiendas_con_ventas,
                    COUNT(DISTINCT s.store_id) as total_tiendas
                FROM stores s
                LEFT JOIN orders o ON s.store_id = o.store_id AND $where_clause";
$result_tiendas = $conn->query($sql_tiendas);
$stats['tiendas'] = $result_tiendas->fetch_assoc();

// Órdenes por estado (filtradas)
$sql_estados = "SELECT 
                    o.estado,
                    COUNT(*) as cantidad
                FROM orders o
                WHERE $where_clause";
if (!empty($filtro_estado)) {
    $sql_estados = "SELECT 
                        o.estado,
                        COUNT(*) as cantidad
                    FROM orders o
                    WHERE o.order_date BETWEEN '$filtro_fecha_inicio' AND '$filtro_fecha_fin 23:59:59'
                    AND o.estado != 'anulado'
                    GROUP BY o.estado
                    ORDER BY cantidad DESC";
} else {
    $sql_estados .= " GROUP BY o.estado ORDER BY cantidad DESC";
}
$ordenes_estados = $conn->query($sql_estados);

// Top 5 productos más vendidos (con filtros)
$sql_top_productos = "SELECT 
                        p.product_name,
                        p.price,
                        SUM(oi.quantity) as total_vendido,
                        SUM(oi.subtotal) as ingresos_totales
                      FROM order_items oi
                      JOIN productos p ON oi.product_id = p.product_id
                      JOIN orders o ON oi.order_id = o.order_id
                      WHERE $where_clause";
if (!empty($filtro_categoria)) {
    $sql_top_productos .= " AND p.category_id = $filtro_categoria";
}
$sql_top_productos .= " GROUP BY p.product_id
                        ORDER BY total_vendido DESC
                        LIMIT 5";
$top_productos = $conn->query($sql_top_productos);

// Top 5 clientes (con filtros)
$sql_top_clientes = "SELECT 
                        u.usuario,
                        u.email,
                        COUNT(o.order_id) as total_ordenes,
                        SUM(o.total) as total_gastado
                     FROM usuarios u
                     JOIN orders o ON u.user_id = o.usuario_id
                     WHERE $where_clause
                     GROUP BY u.user_id
                     ORDER BY total_gastado DESC
                     LIMIT 5";
$top_clientes = $conn->query($sql_top_clientes);

// Últimas órdenes (con filtros)
$sql_ultimas = "SELECT 
                    o.order_id,
                    o.order_date,
                    o.total,
                    o.estado,
                    u.usuario,
                    s.store_name
                FROM orders o
                JOIN usuarios u ON o.usuario_id = u.user_id
                LEFT JOIN stores s ON o.store_id = s.store_id
                WHERE $where_clause
                ORDER BY o.order_date DESC
                LIMIT 10";
$ultimas_ordenes = $conn->query($sql_ultimas);

// Ventas por día en el rango seleccionado
$sql_ventas_dia = "SELECT 
                    DATE(order_date) as dia,
                    COUNT(*) as ordenes,
                    SUM(total) as ventas
                   FROM orders o
                   WHERE $where_clause
                   GROUP BY dia
                   ORDER BY dia ASC";
$ventas_por_dia = $conn->query($sql_ventas_dia);
$dias_data = [];
while ($row = $ventas_por_dia->fetch_assoc()) {
    $dias_data[] = $row;
}

// Obtener listas para los filtros
$sql_tiendas_list = "SELECT store_id, store_name FROM stores ORDER BY store_name";
$tiendas_list = $conn->query($sql_tiendas_list);

$sql_categorias_list = "SELECT category_id, descripcion FROM categorias ORDER BY descripcion";
$categorias_list = $conn->query($sql_categorias_list);

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
    <title>Dashboard - Bike Store</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .stat-card {
            border-left: 4px solid;
            transition: all 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .stat-icon {
            font-size: 3rem;
            opacity: 0.8;
        }
        .bg-purple {
            background-color: #6f42c1;
        }
        .chart-container {
            position: relative;
            height: 300px;
        }
        .badge-growth-positive {
            background-color: #28a745;
        }
        .badge-growth-negative {
            background-color: #dc3545;
        }

        /* ESTILOS MEJORADOS PARA FILTROS */
        .filter-panel {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px;
            padding: 0;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        
        .filter-header {
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
            padding: 20px 25px;
            border-bottom: 1px solid rgba(255,255,255,0.2);
        }
        
        .filter-header h5 {
            color: white;
            margin: 0;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .filter-body {
            background: white;
            padding: 25px;
        }
        
        .filter-group {
            margin-bottom: 20px;
        }
        
        .filter-group label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .filter-group label i {
            color: #667eea;
            font-size: 1.1rem;
        }
        
        .filter-group .form-control,
        .filter-group .form-select {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 10px 15px;
            transition: all 0.3s;
            font-size: 0.95rem;
        }
        
        .filter-group .form-control:focus,
        .filter-group .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.15);
        }
        
        .filter-actions {
            background: #f8f9fa;
            padding: 20px 25px;
            border-top: 1px solid #dee2e6;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .btn-filter {
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
            border: none;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-filter:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .btn-apply {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-clear {
            background: #6c757d;
            color: white;
        }
        
        .btn-export {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
        }
        
        .btn-toggle-filters {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 600;
            margin-bottom: 20px;
            display: none;
        }
        
        .active-filter-badge {
            background: #667eea;
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-right: 8px;
            margin-bottom: 8px;
        }
        
        .active-filter-badge i {
            cursor: pointer;
        }
        
        .filter-quick-presets {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        
        .preset-btn {
            padding: 8px 16px;
            border: 2px solid #667eea;
            background: white;
            color: #667eea;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .preset-btn:hover {
            background: #667eea;
            color: white;
        }

        @media (max-width: 768px) {
            .btn-toggle-filters {
                display: block;
                width: 100%;
            }
            
            .filter-body {
                padding: 15px;
            }
            
            .filter-actions {
                padding: 15px;
            }
            
            .btn-filter {
                flex: 1;
                justify-content: center;
            }
        }
    </style>
</head>
<body class="bg-light">
<?php include '../includes/navbar_admin.php'; ?>

    <div class="container-fluid my-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1><i class="bi bi-speedometer2"></i> Dashboard Administrativo</h1>
                <p class="text-muted">Panel de control y estadísticas con filtros avanzados</p>
            </div>
            <div class="text-end">
                <small class="text-muted">Última actualización: <?php echo date('d/m/Y H:i'); ?></small>
            </div>
        </div>

        <!-- SECCIÓN DE FILTROS MEJORADA -->
        <div class="filter-panel">
            <div class="filter-header">
                <h5>
                    <i class="bi bi-sliders"></i>
                    Filtros Avanzados de Análisis
                </h5>
            </div>
            
            <div class="filter-body">
                <!-- Presets rápidos -->
                <div class="filter-quick-presets">
                    <button type="button" class="preset-btn" onclick="aplicarPreset('hoy')">
                        <i class="bi bi-calendar-day"></i> Hoy
                    </button>
                    <button type="button" class="preset-btn" onclick="aplicarPreset('semana')">
                        <i class="bi bi-calendar-week"></i> Esta Semana
                    </button>
                    <button type="button" class="preset-btn" onclick="aplicarPreset('mes')">
                        <i class="bi bi-calendar-month"></i> Este Mes
                    </button>
                    <button type="button" class="preset-btn" onclick="aplicarPreset('trimestre')">
                        <i class="bi bi-calendar3"></i> Trimestre
                    </button>
                    <button type="button" class="preset-btn" onclick="aplicarPreset('año')">
                        <i class="bi bi-calendar-event"></i> Este Año
                    </button>
                </div>

                <!-- Filtros activos -->
                <div id="activeFilers" class="mb-3"></div>

                <form method="GET" action="dashboard.php" id="filterForm">
                    <div class="row g-4">
                        <!-- Fecha Inicio -->
                        <div class="col-md-6 col-lg-3">
                            <div class="filter-group">
                                <label>
                                    <i class="bi bi-calendar-check"></i>
                                    Fecha Inicio
                                </label>
                                <input type="date" 
                                       class="form-control" 
                                       name="fecha_inicio" 
                                       id="fecha_inicio"
                                       value="<?php echo $filtro_fecha_inicio; ?>">
                            </div>
                        </div>

                        <!-- Fecha Fin -->
                        <div class="col-md-6 col-lg-3">
                            <div class="filter-group">
                                <label>
                                    <i class="bi bi-calendar-x"></i>
                                    Fecha Fin
                                </label>
                                <input type="date" 
                                       class="form-control" 
                                       name="fecha_fin" 
                                       id="fecha_fin"
                                       value="<?php echo $filtro_fecha_fin; ?>">
                            </div>
                        </div>

                        <!-- Estado -->
                        <div class="col-md-6 col-lg-2">
                            <div class="filter-group">
                                <label>
                                    <i class="bi bi-flag"></i>
                                    Estado
                                </label>
                                <select class="form-select" name="estado" id="estado">
                                    <option value="">Todos los estados</option>
                                    <option value="pendiente" <?php echo $filtro_estado == 'pendiente' ? 'selected' : ''; ?>>
                                        Pendiente
                                    </option>
                                    <option value="en_espera" <?php echo $filtro_estado == 'en_espera' ? 'selected' : ''; ?>>
                                        En Espera
                                    </option>
                                    <option value="confirmado" <?php echo $filtro_estado == 'confirmado' ? 'selected' : ''; ?>>
                                        Confirmado
                                    </option>
                                    <option value="enviado" <?php echo $filtro_estado == 'enviado' ? 'selected' : ''; ?>>
                                        Enviado
                                    </option>
                                    <option value="entregado" <?php echo $filtro_estado == 'entregado' ? 'selected' : ''; ?>>
                                        Entregado
                                    </option>
                                </select>
                            </div>
                        </div>

                        <!-- Tienda -->
                        <div class="col-md-6 col-lg-2">
                            <div class="filter-group">
                                <label>
                                    <i class="bi bi-shop"></i>
                                    Tienda
                                </label>
                                <select class="form-select" name="tienda" id="tienda">
                                    <option value="">Todas las tiendas</option>
                                    <?php while($tienda = $tiendas_list->fetch_assoc()): ?>
                                    <option value="<?php echo $tienda['store_id']; ?>" 
                                            <?php echo $filtro_tienda == $tienda['store_id'] ? 'selected' : ''; ?>>
                                        <?php echo $tienda['store_name']; ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Categoría -->
                        <div class="col-md-6 col-lg-2">
                            <div class="filter-group">
                                <label>
                                    <i class="bi bi-tags"></i>
                                    Categoría
                                </label>
                                <select class="form-select" name="categoria" id="categoria">
                                    <option value="">Todas las categorías</option>
                                    <?php while($cat = $categorias_list->fetch_assoc()): ?>
                                    <option value="<?php echo $cat['category_id']; ?>" 
                                            <?php echo $filtro_categoria == $cat['category_id'] ? 'selected' : ''; ?>>
                                        <?php echo $cat['descripcion']; ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <div class="filter-actions">
                <button type="submit" form="filterForm" class="btn btn-filter btn-apply">
                    <i class="bi bi-search"></i>
                    Aplicar Filtros
                </button>
                <a href="dashboard.php" class="btn btn-filter btn-clear">
                    <i class="bi bi-x-circle"></i>
                    Limpiar Todo
                </a>
                <button type="button" class="btn btn-filter btn-export" onclick="exportarDatos()">
                    <i class="bi bi-download"></i>
                    Exportar Datos
                </button>
            </div>
        </div>

        <!-- TARJETAS DE ESTADÍSTICAS -->
        <div class="row g-4 mb-4">
            <!-- Ventas Totales -->
            <div class="col-xl-3 col-md-6">
                <div class="card stat-card border-success shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="text-muted mb-2">VENTAS TOTALES</h6>
                                <h3 class="text-success mb-0">Bs <?php echo number_format($stats['ventas']['total_ventas'] ?? 0, 2); ?></h3>
                                <small class="text-muted"><?php echo $stats['ventas']['total_ordenes']; ?> órdenes</small>
                                <?php if ($stats['crecimiento'] != 0): ?>
                                <div class="mt-2">
                                    <span class="badge <?php echo $stats['crecimiento'] > 0 ? 'badge-growth-positive' : 'badge-growth-negative'; ?>">
                                        <i class="bi bi-<?php echo $stats['crecimiento'] > 0 ? 'arrow-up' : 'arrow-down'; ?>"></i>
                                        <?php echo number_format(abs($stats['crecimiento']), 1); ?>%
                                    </span>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="text-success stat-icon">
                                <i class="bi bi-currency-dollar"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Promedio de Venta -->
            <div class="col-xl-3 col-md-6">
                <div class="card stat-card border-primary shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="text-muted mb-2">PROMEDIO VENTA</h6>
                                <h3 class="text-primary mb-0">Bs <?php echo number_format($stats['ventas']['promedio_venta'] ?? 0, 2); ?></h3>
                                <small class="text-muted">Por orden</small>
                            </div>
                            <div class="text-primary stat-icon">
                                <i class="bi bi-graph-up-arrow"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Clientes -->
            <div class="col-xl-3 col-md-6">
                <div class="card stat-card border-info shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="text-muted mb-2">CLIENTES ACTIVOS</h6>
                                <h3 class="text-info mb-0"><?php echo $stats['clientes']['clientes_periodo']; ?></h3>
                                <small class="text-muted">En el período</small>
                            </div>
                            <div class="text-info stat-icon">
                                <i class="bi bi-people"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Productos -->
            <div class="col-xl-3 col-md-6">
                <div class="card stat-card border-warning shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="text-muted mb-2">PRODUCTOS</h6>
                                <h3 class="text-warning mb-0"><?php echo $stats['productos']['total_productos']; ?></h3>
                                <small class="text-muted"><?php echo $stats['productos']['productos_premium']; ?> premium</small>
                            </div>
                            <div class="text-warning stat-icon">
                                <i class="bi bi-box-seam"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <!-- Gráfico de Ventas -->
            <div class="col-xl-8">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-graph-up"></i> Tendencia de Ventas</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="ventasChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Órdenes por Estado -->
            <div class="col-xl-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="bi bi-pie-chart"></i> Distribución por Estado</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="estadosChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Top Productos -->
            <div class="col-xl-6">
                <div class="card shadow-sm">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="bi bi-trophy"></i> Top 5 Productos Más Vendidos</h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group">
                            <?php 
                            $posicion = 1;
                            if ($top_productos->num_rows > 0):
                                while($producto = $top_productos->fetch_assoc()): 
                            ?>
                            <div class="list-group-item">
                                <div class="d-flex align-items-center">
                                    <div class="me-3">
                                        <h2 class="mb-0 text-muted"><?php echo $posicion; ?></h2>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1"><?php echo $producto['product_name']; ?></h6>
                                        <div class="d-flex justify-content-between">
                                            <small class="text-muted">
                                                <i class="bi bi-box"></i> <?php echo $producto['total_vendido']; ?> vendidos
                                            </small>
                                            <strong class="text-success">Bs <?php echo number_format($producto['ingresos_totales'], 2); ?></strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php 
                                $posicion++;
                                endwhile;
                            else:
                            ?>
                            <div class="alert alert-info mb-0">
                                <i class="bi bi-info-circle"></i> No hay datos disponibles con los filtros seleccionados
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Top Clientes -->
            <div class="col-xl-6">
                <div class="card shadow-sm">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0"><i class="bi bi-star"></i> Top 5 Mejores Clientes</h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group">
                            <?php 
                            $posicion = 1;
                            if ($top_clientes->num_rows > 0):
                                while($cliente = $top_clientes->fetch_assoc()): 
                            ?>
                            <div class="list-group-item">
                                <div class="d-flex align-items-center">
                                    <div class="me-3">
                                        <h2 class="mb-0 text-muted"><?php echo $posicion; ?></h2>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1"><?php echo $cliente['usuario']; ?></h6>
                                        <small class="text-muted"><?php echo $cliente['email']; ?></small>
                                        <div class="d-flex justify-content-between mt-1">
                                            <small class="text-muted">
                                                <i class="bi bi-bag"></i> <?php echo $cliente['total_ordenes']; ?> compras
                                            </small>
                                            <strong class="text-success">Bs <?php echo number_format($cliente['total_gastado'], 2); ?></strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php 
                                $posicion++;
                                endwhile;
                            else:
                            ?>
                            <div class="alert alert-info mb-0">
                                <i class="bi bi-info-circle"></i> No hay datos disponibles con los filtros seleccionados
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Últimas Órdenes -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0"><i class="bi bi-clock-history"></i> Órdenes Recientes</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Orden #</th>
                                        <th>Cliente</th>
                                        <th>Tienda</th>
                                        <th>Fecha</th>
                                        <th>Total</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    if ($ultimas_ordenes->num_rows > 0):
                                        while($orden = $ultimas_ordenes->fetch_assoc()): 
                                    ?>
                                    <tr>
                                        <td><strong>#<?php echo $orden['order_id']; ?></strong></td>
                                        <td><?php echo $orden['usuario']; ?></td>
                                        <td><?php echo $orden['store_name'] ?? 'Sin tienda'; ?></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($orden['order_date'])); ?></td>
                                        <td><strong class="text-success">Bs <?php echo number_format($orden['total'], 2); ?></strong></td>
                                        <td>
                                            <span class="badge <?php echo getBadgeEstado($orden['estado']); ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $orden['estado'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="ver_orden_detalle.php?order_id=<?php echo $orden['order_id']; ?>" 
                                               class="btn btn-sm btn-info" target="_blank">
                                                <i class="bi bi-eye"></i> Ver
                                            </a>
                                        </td>
                                    </tr>
                                    <?php 
                                        endwhile;
                                    else:
                                    ?>
                                    <tr>
                                        <td colspan="7" class="text-center">
                                            <div class="alert alert-info mb-0">
                                                <i class="bi bi-info-circle"></i> No hay órdenes con los filtros seleccionados
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Gráfico de Ventas
        const ventasCtx = document.getElementById('ventasChart').getContext('2d');
        new Chart(ventasCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($dias_data, 'dia')); ?>,
                datasets: [{
                    label: 'Ventas (Bs)',
                    data: <?php echo json_encode(array_column($dias_data, 'ventas')); ?>,
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'Bs ' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });

        // Gráfico de Estados
        const estadosCtx = document.getElementById('estadosChart').getContext('2d');
        new Chart(estadosCtx, {
            type: 'doughnut',
            data: {
                labels: [
                    <?php 
                    $ordenes_estados->data_seek(0);
                    $estados_labels = [];
                    while($estado = $ordenes_estados->fetch_assoc()) {
                        $estados_labels[] = "'" . ucfirst(str_replace('_', ' ', $estado['estado'])) . "'";
                    }
                    echo implode(',', $estados_labels);
                    ?>
                ],
                datasets: [{
                    data: [
                        <?php 
                        $ordenes_estados->data_seek(0);
                        $estados_data = [];
                        while($estado = $ordenes_estados->fetch_assoc()) {
                            $estados_data[] = $estado['cantidad'];
                        }
                        echo implode(',', $estados_data);
                        ?>
                    ],
                    backgroundColor: [
                        '#ffc107',
                        '#17a2b8',
                        '#0d6efd',
                        '#6f42c1',
                        '#28a745',
                        '#dc3545'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Función para exportar datos
        function exportarDatos() {
            const params = new URLSearchParams(window.location.search);
            alert('Función de exportación: Descargará un CSV con los datos filtrados\n\n' +
                  'Filtros aplicados:\n' +
                  'Fecha: ' + (params.get('fecha_inicio') || 'Todas') + ' - ' + (params.get('fecha_fin') || 'Todas') + '\n' +
                  'Estado: ' + (params.get('estado') || 'Todos') + '\n' +
                  'Tienda: ' + (params.get('tienda') || 'Todas') + '\n' +
                  'Categoría: ' + (params.get('categoria') || 'Todas'));
            
            // Aquí puedes implementar la exportación real a CSV/Excel
            // window.location.href = 'exportar.php?' + params.toString();
        }

        // Función para aplicar presets de fecha
        function aplicarPreset(tipo) {
            const hoy = new Date();
            let fechaInicio, fechaFin;
            
            switch(tipo) {
                case 'hoy':
                    fechaInicio = fechaFin = hoy.toISOString().split('T')[0];
                    break;
                    
                case 'semana':
                    const primerDiaSemana = new Date(hoy);
                    primerDiaSemana.setDate(hoy.getDate() - hoy.getDay());
                    fechaInicio = primerDiaSemana.toISOString().split('T')[0];
                    fechaFin = hoy.toISOString().split('T')[0];
                    break;
                    
                case 'mes':
                    fechaInicio = new Date(hoy.getFullYear(), hoy.getMonth(), 1).toISOString().split('T')[0];
                    fechaFin = hoy.toISOString().split('T')[0];
                    break;
                    
                case 'trimestre':
                    const mesActual = hoy.getMonth();
                    const primerMesTrimestre = mesActual - (mesActual % 3);
                    fechaInicio = new Date(hoy.getFullYear(), primerMesTrimestre, 1).toISOString().split('T')[0];
                    fechaFin = hoy.toISOString().split('T')[0];
                    break;
                    
                case 'año':
                    fechaInicio = new Date(hoy.getFullYear(), 0, 1).toISOString().split('T')[0];
                    fechaFin = hoy.toISOString().split('T')[0];
                    break;
            }
            
            document.getElementById('fecha_inicio').value = fechaInicio;
            document.getElementById('fecha_fin').value = fechaFin;
            
            // Opcional: auto-submit
            // document.getElementById('filterForm').submit();
        }

        // Mostrar filtros activos
        function mostrarFiltrosActivos() {
            const params = new URLSearchParams(window.location.search);
            const container = document.getElementById('activeFilers');
            container.innerHTML = '';
            
            const filtros = {
                'fecha_inicio': 'Desde',
                'fecha_fin': 'Hasta',
                'estado': 'Estado',
                'tienda': 'Tienda',
                'categoria': 'Categoría'
            };
            
            for (let [key, label] of Object.entries(filtros)) {
                const valor = params.get(key);
                if (valor) {
                    const badge = document.createElement('span');
                    badge.className = 'active-filter-badge';
                    badge.innerHTML = `
                        <strong>${label}:</strong> ${valor}
                        <i class="bi bi-x-circle" onclick="eliminarFiltro('${key}')"></i>
                    `;
                    container.appendChild(badge);
                }
            }
        }

        function eliminarFiltro(key) {
            const params = new URLSearchParams(window.location.search);
            params.delete(key);
            window.location.href = 'dashboard.php?' + params.toString();
        }

        // Ejecutar al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            mostrarFiltrosActivos();
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>