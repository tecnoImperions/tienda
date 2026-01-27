<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ===============================
// ROLES
// ===============================
$rol = $_SESSION['role'] ?? null;
$es_admin    = ($rol === 'admin');
$es_empleado = ($rol === 'empleado');
$es_usuario  = ($rol === 'usuario');

// ===============================
// PROCESAR ACTUALIZACIONES DEL CARRITO (AJAX)
// ===============================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    require_once '../includes/config.php';
    $conn = getConnection();
    
    if ($_POST['action'] === 'update' && isset($_POST['product_id'], $_POST['cantidad'])) {
        $product_id = (int)$_POST['product_id'];
        $cantidad = (int)$_POST['cantidad'];
        
        if (isset($_SESSION['carrito'][$product_id])) {
            $store_id = $_SESSION['carrito'][$product_id]['store_id'];
            
            // Verificar stock disponible
            $stmt = $conn->prepare("SELECT quantity FROM stocks WHERE product_id = ? AND store_id = ?");
            $stmt->bind_param("ii", $product_id, $store_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $stock = $result->fetch_assoc();
                $stock_disponible = (int)$stock['quantity'];
                
                if ($cantidad > $stock_disponible) {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => false,
                        'message' => "Solo hay {$stock_disponible} unidades disponibles"
                    ]);
                    exit();
                }
                
                if ($cantidad > 0) {
                    $_SESSION['carrito'][$product_id]['cantidad'] = $cantidad;
                } else {
                    unset($_SESSION['carrito'][$product_id]);
                }
            }
        }
        exit();
    }
    
    if ($_POST['action'] === 'remove' && isset($_POST['product_id'])) {
        $product_id = (int)$_POST['product_id'];
        unset($_SESSION['carrito'][$product_id]);
        exit();
    }
    
    // NUEVA ACCIÓN: Recargar contenido del carrito
    if ($_POST['action'] === 'reload_cart') {
        ob_start();
        if (!empty($_SESSION['carrito'])) {
            $total = 0;
            foreach ($_SESSION['carrito'] as $id => $item):
                $cantidad = (int)$item['cantidad'];
                $precio   = (float)$item['price'];
                $subtotal = $cantidad * $precio;
                $total += $subtotal;
            ?>
            <div class="list-group-item d-flex align-items-center justify-content-between border-bottom p-3" data-id="<?= $id ?>" data-price="<?= $precio ?>">
                <div class="d-flex align-items-center" style="flex:2">
                    <?php if (!empty($item['foto'])): ?>
                        <img src="<?= htmlspecialchars($item['foto']) ?>" class="rounded me-2" style="width:60px; height:60px; object-fit:cover;">
                    <?php else: ?>
                        <div class="rounded me-2 d-flex align-items-center justify-content-center bg-light" style="width:60px; height:60px;">
                            <i class="bi bi-bicycle fs-4 text-muted"></i>
                        </div>
                    <?php endif; ?>
                    <div>
                        <div class="fw-semibold" style="font-size:0.9rem;"><?= htmlspecialchars($item['product_name']) ?></div>
                        <?php if(!empty($item['category'])): ?>
                            <small class="text-muted"><?= htmlspecialchars($item['category']) ?></small>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="d-flex align-items-center" style="flex:1">
                    <button class="btn btn-sm btn-outline-secondary me-1 minus-btn" style="border-radius:8px;">−</button>
                    <input type="number" class="form-control form-control-sm quantity-input text-center" value="<?= $cantidad ?>" min="1" style="width:50px;border-radius:8px;" readonly>
                    <button class="btn btn-sm btn-outline-secondary ms-1 plus-btn" style="border-radius:8px;">+</button>
                </div>

                <div style="flex:0.3;text-align:right;">
                    <button class="btn btn-sm btn-danger remove-btn" style="border-radius:8px;"><i class="bi bi-trash"></i></button>
                </div>
            </div>
            <?php endforeach;
            $html = ob_get_clean();
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'html' => $html,
                'total' => number_format($total, 2)
            ]);
        } else {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'html' => '<div class="text-center p-5">
                    <i class="bi bi-cart-x" style="font-size:4rem;color:#cbd5e1;"></i>
                    <p class="text-muted mt-3">Tu carrito está vacío</p>
                    <a href="catalogo.php" class="btn btn-primary mt-2" style="border-radius:12px;">
                        <i class="bi bi-grid"></i> Ver productos
                    </a>
                </div>',
                'total' => '0.00'
            ]);
        }
        exit();
    }
}

// ===============================
// CONTADOR CARRITO
// ===============================
$carrito_count = 0;
if (!empty($_SESSION['carrito']) && is_array($_SESSION['carrito'])) {
    foreach ($_SESSION['carrito'] as $item) {
        $carrito_count += (int)($item['cantidad'] ?? 0);
    }
}
?>

<!-- ===============================
 NAVBAR SUPERIOR
================================ -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
    <div class="container d-flex align-items-center">
        <a class="navbar-brand d-flex align-items-center" href="index.php">
            <i class="bi bi-bicycle me-1"></i> Bike Store
        </a>

        <div class="ms-auto d-flex align-items-center position-relative">
            <?php if ($es_usuario): ?>
            <a href="#" class="position-relative me-2 d-flex align-items-center carrito-fijo" data-bs-toggle="offcanvas" data-bs-target="#cartOffcanvas">
                <i class="bi bi-cart3 fs-4 text-white"></i>
                <span class="badge bg-danger position-absolute top-0 start-100 translate-middle rounded-pill" 
                      style="font-size:0.65rem;<?= $carrito_count > 0 ? '' : 'display:none;' ?>" 
                      id="cartBadge">
                    <?= $carrito_count ?>
                </span>
            </a>
            <?php endif; ?>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
        </div>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto align-items-lg-center">
                <li class="nav-item"><a class="nav-link" href="index.php">Inicio</a></li>

                <?php if ($es_usuario): ?>
                    <li class="nav-item"><a class="nav-link" href="catalogo.php">Productos</a></li>
                    <li class="nav-item"><a class="nav-link" href="mis_ordenes.php">Compras</a></li>
                    <li class="nav-item"><a class="nav-link" href="perfil.php">Perfil</a></li>
                <?php endif; ?>

                <?php if ($es_empleado): ?>
                    <li class="nav-item"><a class="nav-link" href="clientes_empleado.php">Clientes</a></li>
                <?php endif; ?>

                <?php if ($es_admin): ?>
                    <li class="nav-item"><a class="nav-link" href="productos.php">Productos</a></li>
                    <li class="nav-item"><a class="nav-link" href="usuarios.php">Usuarios</a></li>
                <?php endif; ?>

                <?php if (isset($_SESSION['usuario'])): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle"></i>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li class="dropdown-item-text text-muted small"><?= ucfirst($rol) ?></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="auth/logout.php">
                                <i class="bi bi-box-arrow-right"></i> Cerrar sesión
                            </a>
                        </li>
                    </ul>
                </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<!-- ===============================
 OFFCANVAS CARRITO FULLSCREEN IZQUIERDA
================================ -->
<div class="offcanvas offcanvas-start" tabindex="-1" id="cartOffcanvas">
  <div class="offcanvas-header bg-dark text-white">
    <h5 class="offcanvas-title"><i class="bi bi-cart3"></i> Mi Carrito</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
  </div>

  <div class="offcanvas-body p-0">
    <div id="cartContent">
    <?php if (!empty($_SESSION['carrito'])): ?>
    <div class="list-group list-group-flush" id="cartList">
        <?php $total = 0; ?>
        <?php foreach ($_SESSION['carrito'] as $id => $item):
            $cantidad = (int)$item['cantidad'];
            $precio   = (float)$item['price'];
            $subtotal = $cantidad * $precio;
            $total += $subtotal;
        ?>
        <div class="list-group-item d-flex align-items-center justify-content-between border-bottom p-3" data-id="<?= $id ?>" data-price="<?= $precio ?>">
            <!-- IMG / NOMBRE / CATEGORÍA -->
            <div class="d-flex align-items-center" style="flex:2">
                <?php if (!empty($item['foto'])): ?>
                    <img src="<?= htmlspecialchars($item['foto']) ?>" class="rounded me-2" style="width:60px; height:60px; object-fit:cover;">
                <?php else: ?>
                    <div class="rounded me-2 d-flex align-items-center justify-content-center bg-light" style="width:60px; height:60px;">
                        <i class="bi bi-bicycle fs-4 text-muted"></i>
                    </div>
                <?php endif; ?>
                <div>
                    <div class="fw-semibold" style="font-size:0.9rem;"><?= htmlspecialchars($item['product_name']) ?></div>
                    <?php if(!empty($item['category'])): ?>
                        <small class="text-muted"><?= htmlspecialchars($item['category']) ?></small>
                    <?php endif; ?>
                </div>
            </div>

            <!-- CANTIDAD - / INPUT + -->
            <div class="d-flex align-items-center" style="flex:1">
                <button class="btn btn-sm btn-outline-secondary me-1 minus-btn" style="border-radius:8px;">−</button>
                <input type="number" class="form-control form-control-sm quantity-input text-center" value="<?= $cantidad ?>" min="1" style="width:50px;border-radius:8px;" readonly>
                <button class="btn btn-sm btn-outline-secondary ms-1 plus-btn" style="border-radius:8px;">+</button>
            </div>

            <!-- ELIMINAR -->
            <div style="flex:0.3;text-align:right;">
                <button class="btn btn-sm btn-danger remove-btn" style="border-radius:8px;"><i class="bi bi-trash"></i></button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="p-3 border-top d-flex justify-content-between fw-bold bg-light">
        <span>Subtotal:</span>
        <span id="cartTotal">Bs <?= number_format($total,2) ?></span>
    </div>

    <div class="p-3 d-grid gap-2">
        <a href="checkout.php" class="btn btn-primary" style="border-radius:12px;">
            <i class="bi bi-credit-card"></i> Finalizar compra
        </a>
        <button class="btn btn-outline-secondary" data-bs-dismiss="offcanvas" style="border-radius:12px;">
            Seguir comprando
        </button>
    </div>

    <?php else: ?>
        <div class="text-center p-5">
            <i class="bi bi-cart-x" style="font-size:4rem;color:#cbd5e1;"></i>
            <p class="text-muted mt-3">Tu carrito está vacío</p>
            <a href="catalogo.php" class="btn btn-primary mt-2" style="border-radius:12px;">
                <i class="bi bi-grid"></i> Ver productos
            </a>
        </div>
    <?php endif; ?>
    </div>
  </div>
</div>

<!-- ===============================
 NAVBAR MOBILE
================================ -->
<?php if ($es_usuario): ?>
<style>
.mobile-nav {
    height: 64px;
    background: #111;
    border-top: 1px solid rgba(255,255,255,.1);
}
.mobile-nav a { 
    color: #aaa; 
    text-decoration:none; 
    font-size:12px;
    transition: 0.2s;
}
.mobile-nav a.active, .mobile-nav a:hover { 
    color: #0d6efd; 
}
.mobile-nav i { 
    font-size:22px; 
    display:block; 
}
.carrito-fijo { 
    position: fixed !important; 
    top:10px; 
    right:70px; 
    z-index:1055; 
}

/* OFFCANVAS FULLSCREEN MÓVIL CON SOMBRA */
@media (max-width: 992px) {
    #cartOffcanvas {
        width: 100% !important;
        height: 100% !important;
        box-shadow: 0 0 15px rgba(0,0,0,0.5);
    }
    #cartOffcanvas .offcanvas-body {
        overflow-y: auto;
    }
}

/* Mejoras visuales */
.list-group-item {
    transition: background 0.2s;
}
.list-group-item:hover {
    background: #f8fafc;
}
</style>

<nav class="mobile-nav fixed-bottom d-lg-none">
    <div class="d-flex justify-content-around align-items-center h-100 text-center">
        <a href="index.php" class="<?= basename($_SERVER['PHP_SELF'])==='index.php'?'active':'' ?>">
            <i class="bi bi-house"></i>Inicio
        </a>
        <a href="catalogo.php" class="<?= basename($_SERVER['PHP_SELF'])==='catalogo.php'?'active':'' ?>">
            <i class="bi bi-grid"></i>Productos
        </a>
        <a href="mis_ordenes.php" class="<?= basename($_SERVER['PHP_SELF'])==='mis_ordenes.php'?'active fw-semibold':'' ?>">
            <i class="bi bi-bag-check-fill"></i>Compras
        </a>
        <a href="perfil.php" class="<?= basename($_SERVER['PHP_SELF'])==='perfil.php'?'active':'' ?>">
            <i class="bi bi-person"></i>Perfil
        </a>
    </div>
</nav>
<?php endif; ?>

<!-- ===============================
 JAVASCRIPT PARA ACTUALIZAR EL CARRITO CON SWEETALERT2
================================ -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Función global para actualizar el badge del carrito
window.updateCartBadge = function(count) {
    const badge = document.getElementById('cartBadge');
    if (badge) {
        badge.textContent = count;
        badge.style.display = count > 0 ? '' : 'none';
    }
};

// Función global para recargar el contenido del carrito
window.reloadCartOffcanvas = function() {
    const formData = new URLSearchParams();
    formData.append('action', 'reload_cart');
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            document.getElementById('cartContent').innerHTML = data.html;
            
            // Re-adjuntar eventos después de recargar
            attachCartEvents();
        }
    })
    .catch(err => {
        console.error('Error recargando carrito:', err);
    });
};

function attachCartEvents() {
    const cartList = document.getElementById('cartList');
    if (!cartList) return;
    
    const totalElem = document.getElementById('cartTotal');
    const badge = document.getElementById('cartBadge');

    function updateTotal() {
        let total = 0;
        let count = 0;
        
        cartList.querySelectorAll('.list-group-item').forEach(item => {
            const qty = parseInt(item.querySelector('.quantity-input').value);
            const price = parseFloat(item.dataset.price);
            total += qty * price;
            count += qty;
        });
        
        totalElem.innerText = 'Bs ' + total.toFixed(2);
        
        if (badge) {
            badge.innerText = count;
            badge.style.display = count > 0 ? '' : 'none';
        }
    }

    function sendUpdate(id, action, qty=1) {
        const formData = new URLSearchParams();
        formData.append('action', action);
        formData.append('product_id', id);
        if(action==='update') formData.append('cantidad', qty);

        fetch(window.location.href, {
            method:'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data && data.success === false) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Stock insuficiente',
                    text: data.message,
                    confirmButtonColor: '#0d6efd'
                });
                // Revertir cambio
                const row = cartList.querySelector(`[data-id="${id}"]`);
                if (row) {
                    const input = row.querySelector('.quantity-input');
                    input.value = parseInt(input.value) - 1;
                }
            } else {
                updateTotal();
            }
        })
        .catch(() => {
            updateTotal();
        });
    }

    // Botón menos
    cartList.querySelectorAll('.minus-btn').forEach(btn=>{
        btn.addEventListener('click', function(){
            const row = this.closest('.list-group-item');
            const input = row.querySelector('.quantity-input');
            let qty = parseInt(input.value);
            if(qty>1) { 
                qty--; 
                input.value = qty; 
                sendUpdate(row.dataset.id,'update',qty); 
            }
        });
    });

    // Botón más
    cartList.querySelectorAll('.plus-btn').forEach(btn=>{
        btn.addEventListener('click', function(){
            const row = this.closest('.list-group-item');
            const input = row.querySelector('.quantity-input');
            let qty = parseInt(input.value)+1;
            input.value = qty; 
            sendUpdate(row.dataset.id,'update',qty);
        });
    });

    // Botón eliminar
    cartList.querySelectorAll('.remove-btn').forEach(btn=>{
        btn.addEventListener('click', function(){
            const row = this.closest('.list-group-item');
            
            Swal.fire({
                title: '¿Eliminar producto?',
                text: '¿Estás seguro de eliminar este producto del carrito?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    sendUpdate(row.dataset.id,'remove');
                    row.remove();
                    updateTotal();
                    
                    // Si no quedan productos, recargar
                    if(cartList.querySelectorAll('.list-group-item').length === 0) {
                        location.reload();
                    }
                    
                    Swal.fire({
                        icon: 'success',
                        title: 'Eliminado',
                        text: 'Producto eliminado del carrito',
                        timer: 1500,
                        showConfirmButton: false
                    });
                }
            });
        });
    });
}

// Inicializar eventos al cargar
document.addEventListener('DOMContentLoaded', attachCartEvents);
</script>