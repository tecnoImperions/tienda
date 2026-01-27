<?php
require_once '../includes/config.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'usuario') {
    header('Location: login.php');
    exit();
}

$conn = getConnection();

/* SUCURSAL */
$store_id = $_GET['store'] ?? 1;

// Obtener todas las sucursales
$stores = $conn->query("SELECT * FROM stores WHERE estado = 'activa' ORDER BY store_name");

/* CATEGORÍAS */
$categorias = $conn->query("SELECT * FROM categorias ORDER BY descripcion");
$category_filter = $_GET['category'] ?? null;

/* PRODUCTOS */
$sql = "
SELECT p.*, c.descripcion categoria,
IFNULL(SUM(s.quantity),0) stock_total
FROM productos p
LEFT JOIN categorias c ON p.category_id = c.category_id
LEFT JOIN stocks s ON s.product_id = p.product_id AND s.store_id = ?
";
$params = [$store_id];
$types = "i";

if ($category_filter) {
    $sql .= " WHERE p.category_id = ? ";
    $params[] = $category_filter;
    $types .= "i";
}

$sql .= " GROUP BY p.product_id ORDER BY p.product_name";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$productos = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Catálogo - Bike Store</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    background: #f8f9fa;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    padding-top: 70px;
}

/* HEADER CON SELECTOR DE SUCURSAL */
.top-bar {
    background: white;
    padding: 1rem 0;
    border-bottom: 1px solid #dee2e6;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
}

.store-selector {
    border: 1px solid #dee2e6;
    border-radius: 6px;
    padding: 0.5rem 1rem;
    background: white;
    cursor: pointer;
    transition: 0.2s;
    font-weight: 500;
}

.store-selector:hover, .store-selector:focus {
    border-color: #0d6efd;
}

/* LAYOUT PRINCIPAL */
.main-layout {
    display: flex;
    gap: 1.5rem;
    max-width: 1400px;
    margin: 0 auto;
    padding: 1.5rem;
}

/* SIDEBAR FILTROS - DESKTOP */
.sidebar {
    width: 280px;
    flex-shrink: 0;
}

.filter-card {
    background: white;
    border-radius: 8px;
    border: 1px solid #dee2e6;
    padding: 1.25rem;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    margin-bottom: 1rem;
}

.filter-title {
    font-weight: 600;
    margin-bottom: 1rem;
    color: #212529;
    font-size: 1rem;
}

.search-box {
    position: relative;
    margin-bottom: 1rem;
}

.search-input {
    width: 100%;
    padding: 0.625rem 1rem 0.625rem 2.5rem;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    font-size: 0.95rem;
    transition: 0.2s;
}

.search-input:focus {
    outline: none;
    border-color: #0d6efd;
}

.search-icon {
    position: absolute;
    left: 0.875rem;
    top: 50%;
    transform: translateY(-50%);
    color: #6c757d;
}

.category-item {
    display: block;
    padding: 0.625rem 0.875rem;
    margin: 0.25rem 0;
    border-radius: 6px;
    color: #495057;
    text-decoration: none;
    transition: 0.2s;
    font-size: 0.95rem;
}

.category-item:hover {
    background: #e9ecef;
    color: #212529;
}

.category-item.active {
    background: #0d6efd;
    color: white;
}

/* BOTÓN FLOTANTE FILTROS (MÓVIL) - ESTILO PROFESIONAL */
.filter-btn-mobile {
    position: fixed;
    bottom: 100px;
    right: 20px;

    width: 64px;
    height: 64px;

    background: linear-gradient(135deg, #0d6efd, #0b5ed7);
    color: #fff;

    border: none;
    border-radius: 16px;

    box-shadow: 0 10px 28px rgba(13,110,253,.4);

    display: none;
    align-items: center;
    justify-content: center;

    font-size: 1.75rem;
    cursor: pointer;
    z-index: 1040;

    transition: transform .25s ease, box-shadow .25s ease;
}


.filter-btn-mobile:hover {
    transform: translateY(-4px) scale(1.05);
    box-shadow: 0 12px 32px rgba(13, 110, 253, 0.45);
}

.filter-btn-mobile:active {
    transform: translateY(-2px) scale(1.02);
}

/* OFFCANVAS FILTROS MÓVIL */
.offcanvas-filter {
    width: 280px !important;
}

/* CONTENIDO PRINCIPAL */
.content {
    flex: 1;
    min-width: 0;
}

/* MENSAJE SIN RESULTADOS */
.no-results {
    text-align: center;
    padding: 4rem 2rem;
    color: #6c757d;
}

.no-results i {
    font-size: 4rem;
    color: #dee2e6;
    margin-bottom: 1rem;
}

/* GRID DE PRODUCTOS */
.products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 1.25rem;
}

.product-card {
    background: white;
    border-radius: 8px;
    border: 1px solid #dee2e6;
    overflow: hidden;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    cursor: pointer;
    transition: 0.2s;
}

.product-card:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}

.product-card.disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.product-card.disabled:hover {
    transform: none;
}

.product-img {
    width: 100%;
    height: 200px;
    object-fit: cover;
    background: #f8f9fa;
}

.product-info {
    padding: 1rem;
}

.product-name {
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: #212529;
    font-size: 0.95rem;
}

.product-category {
    color: #6c757d;
    font-size: 0.85rem;
    margin-bottom: 0.5rem;
}

.product-price {
    font-weight: 700;
    font-size: 1.15rem;
    color: #198754;
    margin-bottom: 0.5rem;
}

.stock-badge {
    display: inline-block;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.8rem;
    font-weight: 600;
}

.stock-available {
    background: #d1e7dd;
    color: #0f5132;
}

.stock-out {
    background: #f8d7da;
    color: #842029;
}

/* MODAL PRODUCTO */
.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.6);
    display: none;
    z-index: 1050;
    backdrop-filter: blur(4px);
    animation: fadeIn 0.3s ease-out;
}

.modal-overlay.active {
    display: block;
}

.product-modal {
    position: fixed;
    background: white;
    z-index: 1051;
    overflow: hidden;
}

/* MODAL DESKTOP - Slide desde la derecha como panel lateral */
@media(min-width:768px) {
    .product-modal {
        top: 0;
        right: -500px;
        width: 480px;
        height: 100vh;
        box-shadow: -4px 0 24px rgba(0,0,0,0.15);
        transition: right 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .product-modal.active {
        right: 0;
    }
}

/* MODAL MÓVIL - Slide desde abajo */
@media(max-width:767px) {
    .product-modal {
        left: 0;
        bottom: -100%;
        width: 100%;
        max-height: 85vh;
        border-radius: 20px 20px 0 0;
        box-shadow: 0 -4px 24px rgba(0,0,0,0.15);
        transition: bottom 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .product-modal.active {
        bottom: 0;
    }
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.modal-close {
    position: absolute;
    right: 1.25rem;
    top: 1.25rem;
    background: rgba(255, 255, 255, 0.95);
    border: 1px solid #e5e7eb;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    cursor: pointer;
    z-index: 10;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}

.modal-close:hover {
    background: white;
    border-color: #cbd5e1;
    transform: rotate(90deg);
}

.modal-close i {
    font-size: 1.1rem;
    color: #475569;
}

.modal-img {
    width: 100%;
    height: 320px;
    object-fit: cover;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
}

.modal-content {
    padding: 1.75rem;
    overflow-y: auto;
    max-height: calc(100vh - 320px);
}

@media(max-width:767px) {
    .modal-content {
        max-height: calc(85vh - 280px);
    }
    .modal-img {
        height: 280px;
    }
}

.quantity-selector {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin: 1.25rem 0;
    background: #f8f9fa;
    padding: 0.5rem;
    border-radius: 12px;
    width: fit-content;
}

.quantity-selector button {
    width: 42px;
    height: 42px;
    border: 2px solid #dee2e6;
    background: white;
    border-radius: 10px;
    cursor: pointer;
    font-size: 1.25rem;
    font-weight: 600;
    color: #495057;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
}

.quantity-selector button:hover {
    background: #0d6efd;
    color: white;
    border-color: #0d6efd;
    transform: scale(1.05);
}

.quantity-selector button:active {
    transform: scale(0.95);
}

.quantity-selector input {
    width: 70px;
    text-align: center;
    border: 2px solid #dee2e6;
    border-radius: 10px;
    padding: 0.625rem;
    font-size: 1.1rem;
    font-weight: 600;
    background: white;
}

.btn-add-cart {
    width: 100%;
    padding: 1rem;
    background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%);
    color: white;
    border: none;
    border-radius: 12px;
    font-weight: 600;
    font-size: 1.05rem;
    cursor: pointer;
    transition: all 0.3s;
    box-shadow: 0 4px 12px rgba(13, 110, 253, 0.3);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.btn-add-cart:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(13, 110, 253, 0.4);
}

.btn-add-cart:active {
    transform: translateY(0);
}

.btn-add-cart:disabled {
    background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

/* MODAL SELECTOR DE SUCURSAL */
.store-modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.6);
    display: none;
    z-index: 2000;
    backdrop-filter: blur(4px);
    animation: fadeIn 0.3s ease-out;
}

.store-modal-overlay.active {
    display: flex;
    align-items: center;
    justify-content: center;
}

.store-modal {
    background: white;
    border-radius: 16px;
    width: 90%;
    max-width: 500px;
    max-height: 80vh;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    animation: slideUp 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.store-modal-header {
    padding: 1.5rem;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
}

.store-modal-header h5 {
    margin: 0;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.store-modal-close {
    background: #f1f5f9;
    border: none;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}

.store-modal-close:hover {
    background: #e2e8f0;
    transform: rotate(90deg);
}

.store-modal-body {
    padding: 1.25rem;
    max-height: calc(80vh - 80px);
    overflow-y: auto;
}

.store-modal-body::-webkit-scrollbar {
    width: 6px;
}

.store-modal-body::-webkit-scrollbar-track {
    background: transparent;
}

.store-modal-body::-webkit-scrollbar-thumb {
    background: #d1d5db;
    border-radius: 10px;
}

.store-item {
    padding: 1rem;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    margin-bottom: 0.75rem;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.store-item:hover {
    border-color: #0d6efd;
    background: #f8f9ff;
    transform: translateX(4px);
}

.store-item.active {
    border-color: #0d6efd;
    background: linear-gradient(135deg, #e7f0ff 0%, #f0f7ff 100%);
}

.store-item-icon {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.5rem;
    flex-shrink: 0;
}

.store-item.active .store-item-icon {
    background: linear-gradient(135deg, #198754 0%, #157347 100%);
}

.store-item-info {
    flex: 1;
}

.store-item-name {
    font-weight: 600;
    color: #212529;
    margin-bottom: 0.25rem;
}

.store-item-address {
    font-size: 0.875rem;
    color: #6c757d;
}

.store-item-check {
    font-size: 1.5rem;
    color: #198754;
}

/* RESPONSIVE - MÓVIL */
@media(max-width:991px) {
    .sidebar {
        display: none;
    }
    
    .filter-btn-mobile {
        display: flex;
    }
    
    .main-layout {
        padding: 1rem;
    }
    
    .products-grid {
        grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
        gap: 1rem;
    }
}

@media(max-width:767px) {
    .store-modal {
        width: 95%;
        max-height: 90vh;
    }
}
</style>
</head>

<body>

<!-- TOP BAR CON SELECTOR -->
<div class="top-bar">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between">
            <h5 class="m-0 fw-semibold">Catálogo de Productos</h5>
            <div class="store-selector d-flex align-items-center gap-2" id="storeSelectorBtn">
                <i class="bi bi-shop"></i>
                <span id="currentStoreName">
                    <?php 
                    $stores->data_seek(0);
                    while($s = $stores->fetch_assoc()) {
                        if($s['store_id'] == $store_id) {
                            echo htmlspecialchars($s['store_name']);
                            break;
                        }
                    }
                    ?>
                </span>
                <i class="bi bi-chevron-down"></i>
            </div>
        </div>
    </div>
</div>

<!-- BOTÓN FLOTANTE FILTROS (MÓVIL) -->
<button class="filter-btn-mobile" data-bs-toggle="offcanvas" data-bs-target="#filterOffcanvas">
    <i class="bi bi-funnel-fill"></i>
</button>

<!-- OFFCANVAS FILTROS (MÓVIL) -->
<div class="offcanvas offcanvas-end offcanvas-filter" tabindex="-1" id="filterOffcanvas">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title fw-semibold">Filtros</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body p-3">
        <div class="search-box">
            <i class="bi bi-search search-icon"></i>
            <input type="text" id="searchInputMobile" class="search-input" placeholder="Buscar productos..." onkeyup="filterProducts(this.value)">
        </div>
        
        <div class="filter-title mt-3">Categorías</div>
        <a href="?store=<?= $store_id ?>" class="category-item <?= !$category_filter ? 'active' : '' ?>">
            <i class="bi bi-grid-3x3-gap"></i> Todos los productos
        </a>
        <?php $categorias->data_seek(0); while($c = $categorias->fetch_assoc()): ?>
            <a href="?category=<?= $c['category_id'] ?>&store=<?= $store_id ?>" 
               class="category-item <?= $category_filter == $c['category_id'] ? 'active' : '' ?>">
                <i class="bi bi-tag"></i> <?= htmlspecialchars($c['descripcion']) ?>
            </a>
        <?php endwhile; ?>
    </div>
</div>

<!-- LAYOUT PRINCIPAL -->
<div class="main-layout">
    
    <!-- SIDEBAR FILTROS (DESKTOP) -->
    <aside class="sidebar">
        <!-- BÚSQUEDA -->
        <div class="filter-card">
            <div class="search-box">
                <i class="bi bi-search search-icon"></i>
                <input type="text" id="searchInput" class="search-input" placeholder="Buscar productos..." onkeyup="filterProducts(this.value)">
            </div>
        </div>

        <!-- CATEGORÍAS -->
        <div class="filter-card">
            <div class="filter-title">Categorías</div>
            <a href="?store=<?= $store_id ?>" class="category-item <?= !$category_filter ? 'active' : '' ?>">
                <i class="bi bi-grid-3x3-gap"></i> Todos los productos
            </a>
            <?php $categorias->data_seek(0); while($c = $categorias->fetch_assoc()): ?>
                <a href="?category=<?= $c['category_id'] ?>&store=<?= $store_id ?>" 
                   class="category-item <?= $category_filter == $c['category_id'] ? 'active' : '' ?>">
                    <i class="bi bi-tag"></i> <?= htmlspecialchars($c['descripcion']) ?>
                </a>
            <?php endwhile; ?>
        </div>
    </aside>

    <!-- CONTENIDO PRINCIPAL -->
    <main class="content">
        <div class="products-grid" id="productGrid">
            <?php while($p = $productos->fetch_assoc()): 
                $sin_stock = ($p['stock_total'] <= 0);
            ?>
            <div class="product-card <?= $sin_stock ? 'disabled' : '' ?>" 
                 data-product-id="<?= $p['product_id'] ?>"
                 data-store-id="<?= $store_id ?>"
                 data-stock="<?= $p['stock_total'] ?>"
                 <?= $sin_stock ? '' : 'onclick=\'openModal('.json_encode($p).')\'' ?>>
                <?php if($p['foto'] && file_exists($p['foto'])): ?>
                    <img src="<?= $p['foto'] ?>" class="product-img" alt="<?= htmlspecialchars($p['product_name']) ?>">
                <?php else: ?>
                    <div class="product-img d-flex align-items-center justify-content-center">
                        <i class="bi bi-bicycle" style="font-size:2.5rem;color:#adb5bd;"></i>
                    </div>
                <?php endif; ?>
                
                <div class="product-info">
                    <div class="product-name"><?= htmlspecialchars($p['product_name']) ?></div>
                    <div class="product-category"><?= htmlspecialchars($p['categoria']) ?></div>
                    <div class="product-price">Bs <?= number_format($p['price'], 2) ?></div>
                    <span class="stock-badge <?= $sin_stock ? 'stock-out' : 'stock-available' ?>">
                        <?= $sin_stock ? 'Agotado' : $p['stock_total'].' disponibles' ?>
                    </span>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
        
        <!-- MENSAJE SIN RESULTADOS -->
        <div class="no-results" id="noResults" style="display:none;">
            <i class="bi bi-search"></i>
            <h5>No se encontraron productos</h5>
            <p>Intenta con otros términos de búsqueda</p>
        </div>
    </main>

</div>

<!-- MODAL PRODUCTO -->
<div class="modal-overlay" id="overlay" onclick="closeModal()"></div>
<div class="product-modal" id="productModal">
    <button class="modal-close" onclick="closeModal()">
        <i class="bi bi-x-lg"></i>
    </button>
    <img id="mImg" class="modal-img">
    <div class="modal-content">
        <h5 id="mName" class="fw-bold mb-2"></h5>
        <p id="mCategory" class="text-muted mb-2"></p>
        <h4 id="mPrice" class="text-success fw-bold mb-3"></h4>
        <span class="stock-badge stock-available" id="mStock"></span>
        
        <div class="quantity-selector">
            <button onclick="decreaseQty()">−</button>
            <input type="number" id="modalQuantity" value="1" min="1" readonly>
            <button onclick="increaseQty()">+</button>
        </div>
        
        <button class="btn-add-cart mt-2" onclick="addToCart()">
            <i class="bi bi-cart-plus"></i> Agregar al carrito
        </button>
    </div>
</div>

<!-- MODAL SELECTOR DE SUCURSAL -->
<div class="store-modal-overlay" id="storeModalOverlay" onclick="closeStoreModal()">
    <div class="store-modal" onclick="event.stopPropagation()">
        <div class="store-modal-header">
            <h5><i class="bi bi-shop"></i> Seleccionar Sucursal</h5>
            <button class="store-modal-close" onclick="closeStoreModal()">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="store-modal-body" id="storeList">
            <?php 
            $stores->data_seek(0);
            while($s = $stores->fetch_assoc()): 
            ?>
            <div class="store-item <?= $s['store_id'] == $store_id ? 'active' : '' ?>" 
                 onclick="selectStore(<?= $s['store_id'] ?>)">
                <div class="store-item-icon">
                    <i class="bi bi-shop"></i>
                </div>
                <div class="store-item-info">
                    <div class="store-item-name"><?= htmlspecialchars($s['store_name']) ?></div>
                    <div class="store-item-address"><?= htmlspecialchars($s['direccion'] ?? 'Sin dirección') ?></div>
                </div>
                <?php if($s['store_id'] == $store_id): ?>
                <i class="bi bi-check-circle-fill store-item-check"></i>
                <?php endif; ?>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
</div>

<?php include '../includes/navbar.php'; ?>
<?php include 'bot.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
let currentProduct = null;
let currentStoreId = <?= $store_id ?>;

// ABRIR MODAL SELECTOR DE SUCURSAL
document.getElementById('storeSelectorBtn').addEventListener('click', function() {
    document.getElementById('storeModalOverlay').classList.add('active');
});

// CERRAR MODAL SELECTOR DE SUCURSAL
function closeStoreModal() {
    document.getElementById('storeModalOverlay').classList.remove('active');
}

// SELECCIONAR SUCURSAL
function selectStore(newStoreId) {
    if (newStoreId == currentStoreId) {
        closeStoreModal();
        return;
    }
    
    fetch('check_cart.php')
        .then(r => r.json())
        .then(data => {
            if (data.has_items && data.store_id != newStoreId) {
                closeStoreModal();
                Swal.fire({
                    title: '¿Cambiar de sucursal?',
                    text: 'Esto vaciará tu carrito actual',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#0d6efd',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Sí, cambiar',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        fetch('clear_cart.php', { method: 'POST' })
                            .then(() => {
                                window.location.href = '?store=' + newStoreId + (<?= $category_filter ? "'&category=$category_filter'" : "''" ?>);
                            });
                    }
                });
            } else {
                window.location.href = '?store=' + newStoreId + (<?= $category_filter ? "'&category=$category_filter'" : "''" ?>);
            }
        });
}

function openModal(p) {
    currentProduct = p;
    document.getElementById('mImg').src = p.foto || '';
    document.getElementById('mName').textContent = p.product_name;
    document.getElementById('mCategory').textContent = p.categoria;
    document.getElementById('mPrice').textContent = 'Bs ' + parseFloat(p.price).toFixed(2);
    document.getElementById('mStock').textContent = p.stock_total + ' disponibles';
    document.getElementById('modalQuantity').value = 1;
    document.getElementById('modalQuantity').max = p.stock_total;
    document.getElementById('overlay').classList.add('active');
    document.getElementById('productModal').classList.add('active');
}

function closeModal() {
    document.getElementById('overlay').classList.remove('active');
    document.getElementById('productModal').classList.remove('active');
}

function decreaseQty() {
    const input = document.getElementById('modalQuantity');
    if (input.value > 1) {
        input.value = parseInt(input.value) - 1;
    }
}

function increaseQty() {
    const input = document.getElementById('modalQuantity');
    const max = parseInt(input.max);
    if (parseInt(input.value) < max) {
        input.value = parseInt(input.value) + 1;
    } else {
        Swal.fire({
            icon: 'warning',
            title: 'Stock máximo',
            text: `Solo hay ${max} unidades disponibles`,
            confirmButtonColor: '#0d6efd'
        });
    }
}

function filterProducts(v) {
    v = v.toLowerCase();
    let visibleCount = 0;
    
    document.querySelectorAll('.product-card').forEach(c => {
        const text = c.textContent.toLowerCase();
        if (text.includes(v)) {
            c.style.display = '';
            visibleCount++;
        } else {
            c.style.display = 'none';
        }
    });
    
    const noResults = document.getElementById('noResults');
    const grid = document.getElementById('productGrid');
    
    if (visibleCount === 0) {
        grid.style.display = 'none';
        noResults.style.display = 'block';
    } else {
        grid.style.display = 'grid';
        noResults.style.display = 'none';
    }
}

function addToCart() {
    if (!currentProduct || currentProduct.stock_total <= 0) {
        Swal.fire({
            icon: 'error',
            title: 'Producto no disponible',
            text: 'Este producto no tiene stock',
            confirmButtonColor: '#0d6efd'
        });
        return;
    }

    const cantidad = parseInt(document.getElementById('modalQuantity').value);
    const productId = currentProduct.product_id;

    const formData = new FormData();
    formData.append('ajax_add_cart', '1');
    formData.append('product_id', productId);
    formData.append('cantidad', cantidad);
    formData.append('store_id', currentStoreId);

    fetch('cart_ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            // Actualizar badge usando la función global del navbar
            if (window.updateCartBadge) {
                window.updateCartBadge(data.cart_count);
            }
            
            // ACTUALIZAR EL CONTENIDO DEL OFFCANVAS DEL CARRITO
            const cartContent = document.getElementById('cartContent');
            if (cartContent && data.cart_html) {
                cartContent.innerHTML = data.cart_html;
                
                // Re-adjuntar eventos del carrito
                if (window.attachCartEvents) {
                    window.attachCartEvents();
                }
            }
            
            // Actualizar stock visual del producto en la tarjeta
            const productCard = document.querySelector(`.product-card[data-product-id="${productId}"]`);
            if (productCard && data.stock_restante !== undefined) {
                const stockBadge = productCard.querySelector('.stock-badge');
                const newStock = data.stock_restante;
                
                productCard.dataset.stock = newStock;
                
                if (newStock <= 0) {
                    stockBadge.classList.remove('stock-available');
                    stockBadge.classList.add('stock-out');
                    stockBadge.textContent = 'Agotado';
                    productCard.classList.add('disabled');
                    productCard.onclick = null;
                } else {
                    stockBadge.textContent = newStock + ' disponibles';
                }
            }
            
            // Actualizar stock en el producto actual
            if (data.stock_restante !== undefined) {
                currentProduct.stock_total = data.stock_restante;
                document.getElementById('mStock').textContent = data.stock_restante + ' disponibles';
                document.getElementById('modalQuantity').max = data.stock_restante;
            }

            // Alerta de éxito
            Swal.fire({
                icon: 'success',
                title: '¡Agregado!',
                text: data.message,
                timer: 1500,
                showConfirmButton: false,
                position: 'top-end',
                toast: true
            });

            closeModal();
        } else {
            Swal.fire({
                icon: data.icon || 'error',
                title: data.title || 'Error',
                text: data.message,
                confirmButtonColor: '#0d6efd'
            });
        }
    })
    .catch(() => {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'No se pudo agregar al carrito',
            confirmButtonColor: '#0d6efd'
        });
    });
}

// Cerrar modal con ESC
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        closeModal();
        closeStoreModal();
    }
});

// Sincronizar búsqueda entre desktop y mobile
document.getElementById('searchInput').addEventListener('keyup', function() {
    document.getElementById('searchInputMobile').value = this.value;
});

document.getElementById('searchInputMobile').addEventListener('keyup', function() {
    document.getElementById('searchInput').value = this.value;
});
</script>

</body>
</html>
<?php $conn->close(); ?>