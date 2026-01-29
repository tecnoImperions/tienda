<?php
require_once '../../includes/config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

if ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'empleado') {
    header('Location: index.php');
    exit();
}

$conn = getConnection();

// Top 5 productos más vendidos CON STOCKS DISPONIBLES
$sql_top = "SELECT 
                p.product_id,
                p.product_name,
                p.price,
                p.foto,
                p.model_year,
                c.descripcion as categoria,
                SUM(oi.quantity) as total_vendido,
                SUM(oi.subtotal) as ingresos_generados,
                COUNT(DISTINCT o.order_id) as veces_comprado,
                (SELECT COALESCE(SUM(quantity), 0) FROM stocks WHERE product_id = p.product_id) as stock_total
            FROM Order_items oi
            JOIN Productos p ON oi.product_id = p.product_id
            JOIN Orders o ON oi.order_id = o.order_id
            LEFT JOIN Categorias c ON p.category_id = c.category_id
            WHERE o.estado != 'anulado'
            GROUP BY p.product_id
            HAVING stock_total > 0
            ORDER BY total_vendido DESC
            LIMIT 5";
$top_productos = $conn->query($sql_top);

// Todos los productos más vendidos (sin límite de 5)
$sql_all = "SELECT 
                p.product_id,
                p.product_name,
                p.price,
                p.foto,
                SUM(oi.quantity) as total_vendido,
                (SELECT COALESCE(SUM(quantity), 0) FROM stocks WHERE product_id = p.product_id) as stock_total
            FROM Order_items oi
            JOIN Productos p ON oi.product_id = p.product_id
            JOIN Orders o ON oi.order_id = o.order_id
            WHERE o.estado != 'anulado'
            GROUP BY p.product_id
            HAVING stock_total > 0
            ORDER BY total_vendido DESC";
$todos_productos = $conn->query($sql_all);

// Estadísticas generales
$sql_stats = "SELECT 
                COUNT(DISTINCT p.product_id) as productos_vendidos,
                SUM(oi.quantity) as total_unidades,
                COUNT(DISTINCT o.order_id) as total_ordenes
              FROM Order_items oi
              JOIN Productos p ON oi.product_id = p.product_id
              JOIN Orders o ON oi.order_id = o.order_id
              WHERE o.estado != 'anulado'";
$stats = $conn->query($sql_stats)->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Productos Populares - Bike Store</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 60px 0;
            margin-bottom: 40px;
        }
        .product-card {
            transition: all 0.3s;
            height: 100%;
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .product-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .product-img {
            height: 250px;
            object-fit: cover;
            background: #f8f9fa;
        }
        .ranking-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: bold;
            z-index: 10;
        }
        .rank-1 { background: linear-gradient(135deg, #FFD700, #FFA500); color: white; }
        .rank-2 { background: linear-gradient(135deg, #C0C0C0, #808080); color: white; }
        .rank-3 { background: linear-gradient(135deg, #CD7F32, #8B4513); color: white; }
        .rank-4, .rank-5 { background: linear-gradient(135deg, #667eea, #764ba2); color: white; }
        
        .stats-card {
            border-left: 4px solid;
            transition: all 0.3s;
        }
        .stats-card:hover {
            transform: scale(1.05);
        }
        .popular-badge {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-bicycle"></i> Bike Store
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Inicio</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="catalogo.php">Catálogo</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="productos_populares.php">Populares</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="mis_ordenes.php">Mis Órdenes</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i> <?php echo $_SESSION['usuario']; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="logout.php">
                                <i class="bi bi-box-arrow-right"></i> Cerrar Sesión
                            </a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <div class="hero-section">
        <div class="container text-center">
            <h1 class="display-4 mb-3">
                <i class="bi bi-fire"></i> Productos Más Populares
            </h1>
            <p class="lead">Los favoritos de nuestros clientes - ¡No te los pierdas!</p>
            <div class="row mt-4">
                <div class="col-md-4">
                    <h3><?php echo $stats['productos_vendidos']; ?></h3>
                    <p>Productos Diferentes</p>
                </div>
                <div class="col-md-4">
                    <h3><?php echo $stats['total_unidades']; ?></h3>
                    <p>Unidades Vendidas</p>
                </div>
                <div class="col-md-4">
                    <h3><?php echo $stats['total_ordenes']; ?></h3>
                    <p>Órdenes Completadas</p>
                </div>
            </div>
        </div>
    </div>

    <div class="container mb-5">
        <!-- TOP 5 DESTACADOS -->
        <div class="text-center mb-5">
            <h2 class="display-5 mb-3">
                <i class="bi bi-trophy-fill text-warning"></i> Top 5 Más Vendidos
            </h2>
            <p class="text-muted">Las bicicletas que más confían nuestros clientes</p>
        </div>

        <div class="row g-4 mb-5">
            <?php 
            $posicion = 1;
            while($producto = $top_productos->fetch_assoc()): 
            ?>
            <div class="col-lg-4 col-md-6">
                <div class="card product-card">
                    <div class="position-relative">
                        <div class="ranking-badge rank-<?php echo $posicion; ?>">
                            #<?php echo $posicion; ?>
                        </div>
                        <?php if ($producto['foto'] && file_exists($producto['foto'])): ?>
                        <img src="<?php echo $producto['foto']; ?>" class="card-img-top product-img" alt="<?php echo $producto['product_name']; ?>">
                        <?php else: ?>
                        <div class="card-img-top product-img d-flex align-items-center justify-content-center">
                            <i class="bi bi-bicycle" style="font-size: 100px; color: #ccc;"></i>
                        </div>
                        <?php endif; ?>
                        
                        <span class="badge bg-danger position-absolute top-0 end-0 m-2 popular-badge">
                            <i class="bi bi-fire"></i> POPULAR
                        </span>
                    </div>
                    
                    <div class="card-body">
                        <h5 class="card-title"><?php echo $producto['product_name']; ?></h5>
                        
                        <?php if ($producto['categoria']): ?>
                        <p class="text-muted mb-2">
                            <i class="bi bi-tag"></i> <?php echo $producto['categoria']; ?>
                        </p>
                        <?php endif; ?>

                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4 class="text-success mb-0">Bs <?php echo number_format($producto['price'], 2); ?></h4>
                            <span class="badge bg-secondary"><?php echo $producto['model_year']; ?></span>
                        </div>

                        <div class="mb-3">
                            <div class="row text-center">
                                <div class="col-6">
                                    <small class="text-muted">Vendidos</small>
                                    <div><strong class="text-primary"><?php echo $producto['total_vendido']; ?></strong></div>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted">Stock</small>
                                    <div><strong class="text-success"><?php echo $producto['stock_total']; ?></strong></div>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-info mb-3">
                            <small>
                                <i class="bi bi-people-fill"></i> 
                                <strong><?php echo $producto['veces_comprado']; ?></strong> personas compraron este producto
                            </small>
                        </div>

                        <form method="POST" action="catalogo.php" class="d-grid">
                            <input type="hidden" name="product_id" value="<?php echo $producto['product_id']; ?>">
                            <input type="hidden" name="quantity" value="1">
                            <button type="submit" name="agregar_carrito" class="btn btn-primary">
                                <i class="bi bi-cart-plus"></i> Agregar al Carrito
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <?php 
            $posicion++;
            if ($posicion > 5) break;
            endwhile; 
            ?>
        </div>

        <!-- TODOS LOS PRODUCTOS POPULARES -->
        <div class="card shadow mb-5">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="bi bi-list-stars"></i> Todos los Productos Populares</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Producto</th>
                                <th>Precio</th>
                                <th>Vendidos</th>
                                <th>Stock</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $ranking = 1;
                            while($prod = $todos_productos->fetch_assoc()): 
                            ?>
                            <tr>
                                <td>
                                    <span class="badge <?php 
                                        if($ranking <= 3) echo 'bg-warning text-dark';
                                        elseif($ranking <= 5) echo 'bg-info';
                                        else echo 'bg-secondary';
                                    ?>">
                                        #<?php echo $ranking; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <?php if ($prod['foto'] && file_exists($prod['foto'])): ?>
                                        <img src="<?php echo $prod['foto']; ?>" style="width: 40px; height: 40px; object-fit: cover;" class="rounded me-2">
                                        <?php endif; ?>
                                        <strong><?php echo $prod['product_name']; ?></strong>
                                    </div>
                                </td>
                                <td><strong class="text-success">Bs <?php echo number_format($prod['price'], 2); ?></strong></td>
                                <td>
                                    <span class="badge bg-primary">
                                        <i class="bi bi-box"></i> <?php echo $prod['total_vendido']; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $prod['stock_total'] > 10 ? 'success' : ($prod['stock_total'] > 5 ? 'warning' : 'danger'); ?>">
                                        <?php echo $prod['stock_total']; ?> disponibles
                                    </span>
                                </td>
                                <td>
                                    <form method="POST" action="catalogo.php" class="d-inline">
                                        <input type="hidden" name="product_id" value="<?php echo $prod['product_id']; ?>">
                                        <input type="hidden" name="quantity" value="1">
                                        <button type="submit" name="agregar_carrito" class="btn btn-sm btn-primary">
                                            <i class="bi bi-cart-plus"></i> Agregar
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php 
                            $ranking++;
                            endwhile; 
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Call to Action -->
        <div class="card bg-primary text-white text-center shadow-lg">
            <div class="card-body py-5">
                <h3 class="mb-3"><i class="bi bi-shop"></i> ¿Buscas más opciones?</h3>
                <p class="lead">Explora nuestro catálogo completo con todas las bicicletas disponibles</p>
                <a href="catalogo.php" class="btn btn-light btn-lg">
                    <i class="bi bi-grid-3x3"></i> Ver Catálogo Completo
                </a>
            </div>
        </div>
    </div>

    <footer class="bg-dark text-white text-center py-4 mt-5">
        <div class="container">
            <p class="mb-0">&copy; 2025 Bike Store - Los productos más confiados por nuestros clientes</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>