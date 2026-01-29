<?php
require_once '../includes/config.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'usuario') {
    header('Location: ../auth/login.php');
    exit();
}

$conn = getConnection();

// Filtros
$estado_filter = $_GET['estado'] ?? '';

// Construir query con filtros
$where_clauses = ["o.usuario_id = ?"];
$params = [$_SESSION['user_id']];
$types = "i";

if ($estado_filter && $estado_filter !== 'todos') {
    $where_clauses[] = "o.estado = ?";
    $params[] = $estado_filter;
    $types .= "s";
}

$where_sql = implode(" AND ", $where_clauses);

$stmt = $conn->prepare("
    SELECT o.*, s.store_name, s.city,
           COUNT(oi.order_item_id) AS total_items
    FROM orders o
    LEFT JOIN stores s ON o.store_id = s.store_id
    LEFT JOIN order_items oi ON o.order_id = oi.order_id
    WHERE $where_sql
    GROUP BY o.order_id
    ORDER BY o.created_at DESC
");
$stmt->bind_param($types, ...$params);
$stmt->execute();
$orders = $stmt->get_result();

// Obtener estadísticas
$stats_stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN estado = 'entregado' THEN 1 ELSE 0 END) as entregados,
        SUM(CASE WHEN estado IN ('pendiente', 'en_espera', 'confirmado', 'enviado') THEN 1 ELSE 0 END) as en_proceso
    FROM orders 
    WHERE usuario_id = ?
");
$stats_stmt->bind_param("i", $_SESSION['user_id']);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mis Compras - Bike Store</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<style>
body {
    background: #f1f5f9;
    padding-top: 70px;
    padding-bottom: 40px;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

/* === LAYOUT PRINCIPAL === */
.main-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 1rem;
}

.layout-wrapper {
    display: flex;
    gap: 1.5rem;
}

/* === SIDEBAR FILTROS (DESKTOP) === */
.sidebar-filters {
    width: 260px;
    flex-shrink: 0;
    position: sticky;
    top: 85px;
    height: fit-content;
}

.filter-card {
    background: #fff;
    border-radius: 14px;
    padding: 1.25rem;
    box-shadow: 0 1px 3px rgba(0,0,0,.1);
}

.filter-title {
    font-size: 1rem;
    font-weight: 700;
    color: #0f172a;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.filter-item {
    display: block;
    padding: 0.75rem 1rem;
    margin-bottom: 0.5rem;
    border-radius: 10px;
    background: #f8fafc;
    color: #475569;
    text-decoration: none;
    font-size: 0.9rem;
    font-weight: 500;
    transition: all .2s;
    border: 2px solid transparent;
}

.filter-item:hover {
    background: #e0e7ff;
    color: #3b82f6;
}

.filter-item.active {
    background: #3b82f6;
    color: #fff;
    border-color: #3b82f6;
}

.filter-item i {
    margin-right: 0.625rem;
}

/* === CONTENT AREA === */
.content-area {
    flex: 1;
    min-width: 0;
}

/* === HEADER === */
.page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1.5rem;
}

.page-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: #0f172a;
    margin: 0;
}

/* === STATS (SOLO VISUALES) === */
.stats-display {
    display: flex;
    gap: 0.75rem;
    margin-bottom: 1.5rem;
    overflow-x: auto;
    padding-bottom: 0.5rem;
}

.stat-chip {
    background: #fff;
    border-radius: 12px;
    padding: 0.75rem 1rem;
    display: flex;
    align-items: center;
    gap: 0.625rem;
    white-space: nowrap;
    box-shadow: 0 1px 3px rgba(0,0,0,.1);
    pointer-events: none; /* NO CLICKEABLE */
    user-select: none;
}

.stat-icon {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
}

.stat-info {
    display: flex;
    flex-direction: column;
}

.stat-number {
    font-size: 1.25rem;
    font-weight: 700;
    line-height: 1;
    color: #0f172a;
}

.stat-label {
    font-size: 0.75rem;
    color: #64748b;
    font-weight: 500;
}

/* === BOTÓN FLOTANTE (SOLO MÓVIL) === */
.filter-btn-float {
    position: fixed;
    bottom: 90px;
    right: 20px;
    width: 64px;
    height: 64px;
    background: #3b82f6;
    color: #fff;
    border: none;
    border-radius: 16px;
    box-shadow: 0 8px 20px rgba(59, 130, 246, 0.4);
    display: none; /* SOLO MÓVIL */
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    z-index: 1040;
    transition: all .3s;
}

.filter-btn-float:hover {
    background: #2563eb;
    transform: scale(1.05);
}

.filter-count {
    position: absolute;
    top: -6px;
    right: -6px;
    background: #ef4444;
    color: #fff;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
    font-weight: 700;
}

/* === GRID DE ÓRDENES (3 COLUMNAS EN DESKTOP) === */
.orders-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1rem;
}

/* === ORDEN CARD === */
.order-card {
    background: #fff;
    border-radius: 14px;
    padding: 1rem;
    box-shadow: 0 1px 3px rgba(0,0,0,.1);
    border: 2px solid transparent;
    transition: all .2s;
}

.order-card:hover {
    border-color: #3b82f6;
    box-shadow: 0 4px 12px rgba(0,0,0,.1);
    transform: translateY(-2px);
}

.order-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.75rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid #f1f5f9;
}

.order-id {
    font-size: 1rem;
    font-weight: 700;
    color: #0f172a;
}

.order-date {
    font-size: 0.75rem;
    color: #64748b;
    margin-top: 0.25rem;
}

.status-badge {
    padding: 0.375rem 0.75rem;
    border-radius: 16px;
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.status-pendiente { background: #fee2e2; color: #991b1b; }
.status-en_espera { background: #fef3c7; color: #92400e; }
.status-confirmado { background: #dbeafe; color: #1e40af; }
.status-enviado { background: #e0e7ff; color: #4338ca; }
.status-entregado { background: #d1fae5; color: #065f46; }
.status-anulado { background: #f3f4f6; color: #374151; }

/* === INFO === */
.order-info {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.75rem;
    margin-bottom: 0.75rem;
}

.info-box {
    text-align: center;
    padding: 0.625rem;
    background: #f8fafc;
    border-radius: 8px;
}

.info-box i {
    display: block;
    font-size: 1.1rem;
    color: #3b82f6;
    margin-bottom: 0.25rem;
}

.info-box-value {
    font-size: 0.85rem;
    font-weight: 600;
    color: #0f172a;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.info-box-label {
    font-size: 0.7rem;
    color: #64748b;
}

/* === BOTÓN VER === */
.btn-ver {
    width: 100%;
    background: #0f172a;
    color: #fff;
    border: none;
    border-radius: 10px;
    padding: 0.75rem;
    font-weight: 600;
    font-size: 0.9rem;
    transition: all .2s;
}

.btn-ver:hover {
    background: #1e293b;
    transform: translateY(-2px);
}

/* === EMPTY STATE === */
.empty-state {
    grid-column: 1 / -1;
    text-align: center;
    padding: 3rem 1.5rem;
    background: #fff;
    border-radius: 14px;
}

.empty-state i {
    font-size: 4rem;
    color: #e5e7eb;
    margin-bottom: 1rem;
}

.empty-state h5 {
    color: #475569;
    margin-bottom: 0.75rem;
}

.empty-state p {
    color: #94a3b8;
    margin-bottom: 1.5rem;
    font-size: 0.9rem;
}

/* === OFFCANVAS (SOLO MÓVIL) === */
.offcanvas-filters {
    width: 75% !important;
    max-width: 300px !important;
}

.offcanvas-detail {
    width: 500px !important;
}

/* === RESPONSIVE === */
@media (max-width: 991px) {
    .sidebar-filters {
        display: none; /* OCULTAR EN MÓVIL */
    }

    .filter-btn-float {
        display: flex; /* MOSTRAR BOTÓN EN MÓVIL */
    }

    .layout-wrapper {
        display: block;
    }

    .orders-grid {
        grid-template-columns: 1fr; /* 1 COLUMNA EN MÓVIL */
    }
}

@media (max-width: 767px) {
    .main-container {
        padding: 0.75rem;
    }

    .page-title {
        font-size: 1.25rem;
    }

    .stats-display {
        gap: 0.5rem;
    }

    .stat-chip {
        padding: 0.625rem 0.875rem;
    }

    .stat-icon {
        width: 32px;
        height: 32px;
        font-size: 1rem;
    }

    .stat-number {
        font-size: 1.1rem;
    }

    .order-card {
        padding: 0.875rem;
    }

    .order-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }

    .filter-btn-float {
        bottom: 80px;
        right: 16px;
        width: 60px;
        height: 60px;
        font-size: 1.375rem;
    }

    .offcanvas-detail {
        width: 100% !important;
    }
}

@media (min-width: 992px) and (max-width: 1199px) {
    .orders-grid {
        grid-template-columns: repeat(2, 1fr); /* 2 COLUMNAS EN TABLET */
    }

    .sidebar-filters {
        width: 240px;
    }
}

@media (min-width: 1200px) {
    .orders-grid {
        grid-template-columns: repeat(3, 1fr); /* 3 COLUMNAS EN DESKTOP */
    }
}
</style>
</head>

<body>
<?php include '../includes/navbar.php'; ?>

<div class="main-container">
    <div class="layout-wrapper">
        
        <!-- SIDEBAR FILTROS (SOLO DESKTOP) -->
        <aside class="sidebar-filters">
            <div class="filter-card">
                <div class="filter-title">
                    <i class="bi bi-funnel-fill"></i>Filtros
                </div>
                
                <a href="?" class="filter-item <?= !$estado_filter ? 'active' : '' ?>">
                    <i class="bi bi-grid-3x3-gap-fill"></i>Todas
                </a>
                <a href="?estado=pendiente" class="filter-item <?= $estado_filter === 'pendiente' ? 'active' : '' ?>">
                    <i class="bi bi-hourglass-split"></i>Pendientes
                </a>
                <a href="?estado=en_espera" class="filter-item <?= $estado_filter === 'en_espera' ? 'active' : '' ?>">
                    <i class="bi bi-clock"></i>En espera
                </a>
                <a href="?estado=confirmado" class="filter-item <?= $estado_filter === 'confirmado' ? 'active' : '' ?>">
                    <i class="bi bi-check-circle"></i>Confirmados
                </a>
                <a href="?estado=enviado" class="filter-item <?= $estado_filter === 'enviado' ? 'active' : '' ?>">
                    <i class="bi bi-truck"></i>Enviados
                </a>
                <a href="?estado=entregado" class="filter-item <?= $estado_filter === 'entregado' ? 'active' : '' ?>">
                    <i class="bi bi-check-circle-fill"></i>Entregados
                </a>
                <a href="?estado=anulado" class="filter-item <?= $estado_filter === 'anulado' ? 'active' : '' ?>">
                    <i class="bi bi-x-circle"></i>Anulados
                </a>
            </div>
        </aside>

        <!-- CONTENT AREA -->
        <div class="content-area">
            <!-- HEADER -->
            <div class="page-header">
                <h1 class="page-title">
                    <i class="bi bi-bag-check-fill me-2"></i>Mis Compras
                </h1>
            </div>

            <!-- STATS (SOLO VISUALES) -->
            <div class="stats-display">
                <div class="stat-chip">
                    <div class="stat-icon" style="background: #dbeafe;">
                        <i class="bi bi-receipt" style="color: #1e40af;"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-number"><?= $stats['total'] ?></div>
                        <div class="stat-label">Órdenes</div>
                    </div>
                </div>
                
                <div class="stat-chip">
                    <div class="stat-icon" style="background: #d1fae5;">
                        <i class="bi bi-check-circle" style="color: #065f46;"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-number"><?= $stats['entregados'] ?></div>
                        <div class="stat-label">Entregados</div>
                    </div>
                </div>
                
                <div class="stat-chip">
                    <div class="stat-icon" style="background: #fef3c7;">
                        <i class="bi bi-clock" style="color: #92400e;"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-number"><?= $stats['en_proceso'] ?></div>
                        <div class="stat-label">En proceso</div>
                    </div>
                </div>
            </div>

            <!-- GRID DE ÓRDENES -->
            <div class="orders-grid">
                <?php if ($orders->num_rows > 0): ?>
                    <?php while ($o = $orders->fetch_assoc()): ?>
                        <div class="order-card">
                            <div class="order-header">
                                <div>
                                    <div class="order-id">#<?= $o['order_id'] ?></div>
                                    <div class="order-date">
                                        <i class="bi bi-calendar3"></i>
                                        <?= date('d/m/Y H:i', strtotime($o['created_at'])) ?>
                                    </div>
                                </div>
                                <span class="status-badge status-<?= $o['estado'] ?>">
                                    <?= ucfirst(str_replace('_',' ',$o['estado'])) ?>
                                </span>
                            </div>

                            <div class="order-info">
                                <div class="info-box">
                                    <i class="bi bi-geo-alt-fill"></i>
                                    <div class="info-box-value" title="<?= htmlspecialchars($o['store_name']) ?>">
                                        <?= htmlspecialchars($o['store_name']) ?>
                                    </div>
                                    <div class="info-box-label">Sucursal</div>
                                </div>
                                <div class="info-box">
                                    <i class="bi bi-bag-fill"></i>
                                    <div class="info-box-value"><?= $o['total_items'] ?></div>
                                    <div class="info-box-label">Productos</div>
                                </div>
                                <div class="info-box">
                                    <i class="bi bi-cash-stack"></i>
                                    <div class="info-box-value">Bs <?= number_format($o['total'], 2) ?></div>
                                    <div class="info-box-label">Total</div>
                                </div>
                            </div>

                            <button class="btn btn-ver" onclick="verDetalle(<?= $o['order_id'] ?>)">
                                <i class="bi bi-eye me-2"></i>Ver detalle
                            </button>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="bi bi-bag-x"></i>
                        <h5>No se encontraron compras</h5>
                        <p>
                            <?php if ($estado_filter): ?>
                                Intenta ajustar tus filtros
                            <?php else: ?>
                                Aún no has realizado ninguna compra
                            <?php endif; ?>
                        </p>
                        <a href="catalogo.php" class="btn btn-primary">
                            <i class="bi bi-shop me-2"></i>Ver productos
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- BOTÓN FLOTANTE (SOLO MÓVIL) -->
<button class="filter-btn-float" data-bs-toggle="offcanvas" data-bs-target="#filterOffcanvas">
    <i class="bi bi-funnel-fill"></i>
    <?php if($estado_filter): ?>
        <span class="filter-count">1</span>
    <?php endif; ?>
</button>

<!-- OFFCANVAS FILTROS (SOLO MÓVIL) -->
<div class="offcanvas offcanvas-end offcanvas-filters" tabindex="-1" id="filterOffcanvas">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title fw-bold">
            <i class="bi bi-funnel me-2"></i>Filtros
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body">
        <a href="?" class="filter-item <?= !$estado_filter ? 'active' : '' ?>">
            <i class="bi bi-grid-3x3-gap-fill"></i>Todas
        </a>
        <a href="?estado=pendiente" class="filter-item <?= $estado_filter === 'pendiente' ? 'active' : '' ?>">
            <i class="bi bi-hourglass-split"></i>Pendientes
        </a>
        <a href="?estado=en_espera" class="filter-item <?= $estado_filter === 'en_espera' ? 'active' : '' ?>">
            <i class="bi bi-clock"></i>En espera
        </a>
        <a href="?estado=confirmado" class="filter-item <?= $estado_filter === 'confirmado' ? 'active' : '' ?>">
            <i class="bi bi-check-circle"></i>Confirmados
        </a>
        <a href="?estado=enviado" class="filter-item <?= $estado_filter === 'enviado' ? 'active' : '' ?>">
            <i class="bi bi-truck"></i>Enviados
        </a>
        <a href="?estado=entregado" class="filter-item <?= $estado_filter === 'entregado' ? 'active' : '' ?>">
            <i class="bi bi-check-circle-fill"></i>Entregados
        </a>
        <a href="?estado=anulado" class="filter-item <?= $estado_filter === 'anulado' ? 'active' : '' ?>">
            <i class="bi bi-x-circle"></i>Anulados
        </a>
    </div>
</div>

<!-- OFFCANVAS DETALLE -->
<div class="offcanvas offcanvas-end offcanvas-detail" tabindex="-1" id="detalleOffcanvas">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title fw-bold">
            <i class="bi bi-receipt me-2"></i>Detalle
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body p-0" id="detalleContent">
        <div class="text-center py-5">
            <div class="spinner-border text-primary"></div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function verDetalle(id) {
    const offcanvas = new bootstrap.Offcanvas(document.getElementById('detalleOffcanvas'));
    const content = document.getElementById('detalleContent');
    
    content.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary"></div></div>';
    offcanvas.show();
    
    fetch('orden_detalle.php?order_id=' + id)
        .then(r => r.text())
        .then(html => content.innerHTML = html)
        .catch(() => content.innerHTML = '<div class="alert alert-danger m-3">Error al cargar</div>');
}
</script>

</body>
</html>
<?php $conn->close(); ?>