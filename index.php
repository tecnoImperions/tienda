<?php
// Conexión a la base de datos
require_once 'includes/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$conn = getConnection();

// Obtener productos destacados de la base de datos
$query_productos = $conn->query("
    SELECT p.product_id, p.product_name, p.foto, p.price, c.descripcion as categoria
    FROM productos p
    LEFT JOIN categorias c ON p.category_id = c.category_id
    ORDER BY p.product_id DESC
    LIMIT 6
");

$productos = [];
while ($row = $query_productos->fetch_assoc()) {
    $productos[] = $row;
}

// Obtener imágenes para el hero slider
$imgs = [];
if (is_dir('views/uploads')) {
    foreach (scandir('views/uploads') as $f) {
        if (preg_match('/\.(jpg|png|webp)$/i', $f)) {
            $imgs[] = 'views/uploads/' . $f;
        }
    }
}
if (!$imgs) {
    $imgs[] = '';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bike Store - Tu tienda de bicicletas</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #1e40af;
            --accent-color: #f59e0b;
            --dark-color: #1f2937;
            --light-color: #f3f4f6;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            overflow-x: hidden;
        }

        /* Navbar Bootstrap Negro */
        .navbar-dark {
            background-color: #000 !important;
        }

        .navbar-brand {
            font-size: 1.8rem;
            font-weight: 700;
            transition: transform 0.3s ease;
        }

        .navbar-brand:hover {
            transform: scale(1.05);
        }

        .navbar-brand i {
            color: var(--accent-color);
            margin-right: 0.5rem;
        }

        .nav-link {
            font-weight: 500;
            margin: 0 0.5rem;
            transition: all 0.3s ease;
        }

        .btn-login, .btn-register {
            padding: 0.6rem 1.5rem;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
            margin: 0 0.3rem;
        }

        .btn-login {
            background: transparent;
            border: 2px solid white;
            color: white;
        }

        .btn-login:hover {
            background: white;
            color: #000;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255,255,255,0.3);
        }

        .btn-register {
            background: var(--accent-color);
            border: 2px solid var(--accent-color);
            color: white;
        }

        .btn-register:hover {
            background: #d97706;
            border-color: #d97706;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(245, 158, 11, 0.4);
        }

        /* Hero Section */
        .hero-section {
            position: relative;
            min-height: 100vh;
            display: flex;
            align-items: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            overflow: hidden;
            padding: 6rem 0 4rem;
        }

        .hero-bg-slider {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
        }

        .hero-slide {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            transition: opacity 1.5s ease-in-out;
            background-size: cover;
            background-position: center;
        }

        .hero-slide.active {
            opacity: 0.3;
        }

        .hero-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.8), rgba(118, 75, 162, 0.8));
            z-index: 1;
        }

        .hero-content {
            position: relative;
            z-index: 2;
            color: white;
            text-align: center;
        }

        .hero-title {
            font-size: 4rem;
            font-weight: 800;
            margin-bottom: 1.5rem;
            text-shadow: 2px 2px 10px rgba(0,0,0,0.3);
            line-height: 1.2;
        }

        .hero-subtitle {
            font-size: 1.5rem;
            margin-bottom: 2.5rem;
            opacity: 0.95;
            font-weight: 300;
        }

        .hero-buttons .btn {
            padding: 1rem 2.5rem;
            font-size: 1.1rem;
            border-radius: 30px;
            margin: 0.5rem;
            transition: all 0.3s ease;
        }

        .hero-buttons .btn-primary {
            background: var(--accent-color);
            border: none;
            box-shadow: 0 5px 20px rgba(245, 158, 11, 0.4);
        }

        .hero-buttons .btn-primary:hover {
            background: #d97706;
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(245, 158, 11, 0.5);
        }

        .hero-buttons .btn-outline-light {
            border: 2px solid white;
        }

        .hero-buttons .btn-outline-light:hover {
            background: white;
            color: var(--primary-color);
            transform: translateY(-3px);
        }

        /* Features Section */
        .features-section {
            padding: 5rem 0;
            background: var(--light-color);
        }

        .section-title {
            font-size: 2.5rem;
            font-weight: 700;
            text-align: center;
            margin-bottom: 1rem;
            color: var(--dark-color);
        }

        .section-subtitle {
            text-align: center;
            color: #6b7280;
            font-size: 1.1rem;
            margin-bottom: 4rem;
        }

        .feature-card {
            background: white;
            border-radius: 20px;
            padding: 2.5rem;
            height: 100%;
            transition: all 0.3s ease;
            border: 2px solid transparent;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }

        .feature-card:hover {
            transform: translateY(-10px);
            border-color: var(--primary-color);
            box-shadow: 0 15px 40px rgba(37, 99, 235, 0.2);
        }

        .feature-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
            font-size: 2rem;
            color: white;
            transition: all 0.3s ease;
        }

        .feature-card:hover .feature-icon {
            transform: rotate(10deg) scale(1.1);
        }

        .feature-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--dark-color);
        }

        .feature-text {
            color: #6b7280;
            line-height: 1.8;
        }

        /* Products Preview */
        .products-section {
            padding: 5rem 0;
            background: white;
        }

        .product-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            height: 100%;
        }

        .product-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.15);
        }

        .product-image {
            width: 100%;
            height: 280px;
            object-fit: contain;
            background: var(--light-color);
            padding: 1.5rem;
        }

        .product-body {
            padding: 1.5rem;
        }

        .product-category {
            color: var(--primary-color);
            font-size: 0.9rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .product-name {
            font-size: 1.3rem;
            font-weight: 700;
            margin: 0.5rem 0;
            color: var(--dark-color);
        }

        .product-price {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--accent-color);
            margin: 1rem 0;
        }

        /* Stats Section */
        .stats-section {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            padding: 4rem 0;
            color: white;
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            font-size: 3rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            color: var(--accent-color);
        }

        .stat-label {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        /* CTA Section */
        .cta-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 5rem 0;
            color: white;
            text-align: center;
        }

        .cta-title {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 1.5rem;
        }

        .cta-text {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            opacity: 0.95;
        }

        /* Footer */
        .footer {
            background: var(--dark-color);
            color: white;
            padding: 3rem 0 1rem;
        }

        .footer-title {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
        }

        .footer-links {
            list-style: none;
            padding: 0;
        }

        .footer-links li {
            margin-bottom: 0.8rem;
        }

        .footer-links a {
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .footer-links a:hover {
            color: var(--accent-color);
        }

        .social-icons a {
            display: inline-block;
            width: 40px;
            height: 40px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            text-align: center;
            line-height: 40px;
            margin-right: 0.5rem;
            color: white;
            transition: all 0.3s ease;
        }

        .social-icons a:hover {
            background: var(--accent-color);
            transform: translateY(-3px);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .hero-title {
                font-size: 2.5rem;
            }

            .hero-subtitle {
                font-size: 1.2rem;
            }

            .section-title {
                font-size: 2rem;
            }

            .stat-number {
                font-size: 2rem;
            }

            .navbar-brand {
                font-size: 1.5rem;
            }

            .hero-buttons .btn {
                padding: 0.8rem 1.5rem;
                font-size: 1rem;
            }
        }

        /* Scroll to top button */
        .scroll-top {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 50px;
            height: 50px;
            background: var(--accent-color);
            border-radius: 50%;
            display: none;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
            z-index: 1000;
            box-shadow: 0 5px 20px rgba(245, 158, 11, 0.4);
        }

        .scroll-top:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(245, 158, 11, 0.5);
        }

        .scroll-top.show {
            display: flex;
        }
    </style>
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top shadow">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="bi bi-bicycle"></i> Bike Store
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item">
                        <a class="nav-link" href="#inicio">Inicio</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#productos">Productos</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#nosotros">Nosotros</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#contacto">Contacto</a>
                    </li>
                    <li class="nav-item">
                        <a href="views/login.php" class="btn btn-login">Iniciar Sesión</a>
                    </li>
                    <li class="nav-item">
                        <a href="views/login.php" class="btn btn-register">Registrarse</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section" id="inicio">
        <div class="hero-bg-slider">
            <?php foreach ($imgs as $i => $img): ?>
            <div class="hero-slide <?= $i === 0 ? 'active' : '' ?>" style="<?= $img ? "background-image: url('$img')" : "background: linear-gradient(135deg, #667eea 0%, #764ba2 100%)" ?>"></div>
            <?php endforeach; ?>
        </div>
        <div class="hero-overlay"></div>
        
        <div class="container hero-content">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <h1 class="hero-title" data-aos="fade-up">
                        Descubre Tu Bicicleta Perfecta
                    </h1>
                    <p class="hero-subtitle" data-aos="fade-up" data-aos-delay="100">
                        Las mejores bicicletas y accesorios para tu aventura. Calidad, estilo y rendimiento en cada pedaleo.
                    </p>
                    <div class="hero-buttons" data-aos="fade-up" data-aos-delay="200">
                        <a href="views/login.php" class="btn btn-primary btn-lg">
                            <i class="bi bi-person-plus me-2"></i>Comienza Ahora
                        </a>
                        <a href="#productos" class="btn btn-outline-light btn-lg">
                            <i class="bi bi-arrow-down me-2"></i>Ver Productos
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features-section" id="nosotros">
        <div class="container">
            <h2 class="section-title" data-aos="fade-up">¿Por Qué Elegirnos?</h2>
            <p class="section-subtitle" data-aos="fade-up" data-aos-delay="100">
                Más de 10 años brindando la mejor experiencia en ciclismo
            </p>
            
            <div class="row g-4">
                <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="100">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="bi bi-award-fill"></i>
                        </div>
                        <h3 class="feature-title">Calidad Premium</h3>
                        <p class="feature-text">
                            Solo trabajamos con las mejores marcas y productos de alta calidad para garantizar tu satisfacción.
                        </p>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="200">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="bi bi-truck"></i>
                        </div>
                        <h3 class="feature-title">Envío Rápido</h3>
                        <p class="feature-text">
                            Entrega rápida y segura a todo el país. Tu bicicleta llegará en perfectas condiciones.
                        </p>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="300">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="bi bi-headset"></i>
                        </div>
                        <h3 class="feature-title">Soporte 24/7</h3>
                        <p class="feature-text">
                            Nuestro equipo está siempre disponible para ayudarte con cualquier consulta o problema.
                        </p>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="400">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="bi bi-shield-check"></i>
                        </div>
                        <h3 class="feature-title">Garantía Total</h3>
                        <p class="feature-text">
                            Todos nuestros productos cuentan con garantía extendida y política de devolución.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Products Preview -->
    <section class="products-section" id="productos">
        <div class="container">
            <h2 class="section-title" data-aos="fade-up">Productos Destacados</h2>
            <p class="section-subtitle" data-aos="fade-up" data-aos-delay="100">
                Explora nuestra selección de bicicletas y accesorios premium
            </p>
            
            <div class="row g-4">
                <?php 
                $delay = 100;
                foreach ($productos as $producto): 
                ?>
                <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="<?= $delay ?>">
                    <a href="views/login.php" class="text-decoration-none">
                        <div class="product-card">
                            <?php 
                            // Ajustar ruta de imagen: si comienza con ../ la quitamos porque ya estamos en raíz
                            $img_src = $producto['foto'];
                            if (strpos($img_src, '../') === 0) {
                                $img_src = substr($img_src, 3); // Quitar ../
                            }
                            ?>
                            <img src="<?= htmlspecialchars($img_src ?: 'views/assets/noimg.png') ?>" 
                                 alt="<?= htmlspecialchars($producto['product_name']) ?>" 
                                 class="product-image">
                            <div class="product-body">
                                <div class="product-category"><?= htmlspecialchars($producto['categoria'] ?? 'Producto') ?></div>
                                <h3 class="product-name"><?= htmlspecialchars($producto['product_name']) ?></h3>
                                <div class="product-price">Bs <?= number_format($producto['price'], 2) ?></div>
                                <span class="btn btn-primary w-100">Ver Detalles</span>
                            </div>
                        </div>
                    </a>
                </div>
                <?php 
                $delay += 100;
                endforeach; 
                ?>
            </div>
            
            <div class="text-center mt-5" data-aos="fade-up">
                <a href="views/login.php" class="btn btn-primary btn-lg">
                    Ver Catálogo Completo <i class="bi bi-arrow-right ms-2"></i>
                </a>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats-section">
        <div class="container">
            <div class="row g-4">
                <div class="col-6 col-md-3" data-aos="fade-up">
                    <div class="stat-item">
                        <div class="stat-number">5000+</div>
                        <div class="stat-label">Clientes Felices</div>
                    </div>
                </div>
                <div class="col-6 col-md-3" data-aos="fade-up" data-aos-delay="100">
                    <div class="stat-item">
                        <div class="stat-number">200+</div>
                        <div class="stat-label">Productos</div>
                    </div>
                </div>
                <div class="col-6 col-md-3" data-aos="fade-up" data-aos-delay="200">
                    <div class="stat-item">
                        <div class="stat-number">10+</div>
                        <div class="stat-label">Años Experiencia</div>
                    </div>
                </div>
                <div class="col-6 col-md-3" data-aos="fade-up" data-aos-delay="300">
                    <div class="stat-item">
                        <div class="stat-number">24/7</div>
                        <div class="stat-label">Soporte</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section" id="contacto">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8" data-aos="fade-up">
                    <h2 class="cta-title">¿Listo Para Tu Próxima Aventura?</h2>
                    <p class="cta-text">
                        Únete a miles de ciclistas que ya confían en nosotros. Regístrate hoy y obtén un 10% de descuento en tu primera compra.
                    </p>
                    <a href="views/login.php" class="btn btn-light btn-lg">
                        <i class="bi bi-person-plus me-2"></i>Registrarse Ahora
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row g-4">
                <div class="col-md-4">
                    <h3 class="footer-title">
                        <i class="bi bi-bicycle text-warning"></i> Bike Store
                    </h3>
                    <p class="text-white-50">
                        Tu tienda de confianza para bicicletas y accesorios de alta calidad. Más de 10 años brindando las mejores soluciones para ciclistas.
                    </p>
                    <div class="social-icons mt-3">
                        <a href="#"><i class="bi bi-facebook"></i></a>
                        <a href="#"><i class="bi bi-instagram"></i></a>
                        <a href="#"><i class="bi bi-twitter"></i></a>
                        <a href="#"><i class="bi bi-youtube"></i></a>
                    </div>
                </div>
                
                <div class="col-md-2">
                    <h4 class="footer-title">Enlaces</h4>
                    <ul class="footer-links">
                        <li><a href="#inicio">Inicio</a></li>
                        <li><a href="#productos">Productos</a></li>
                        <li><a href="#nosotros">Nosotros</a></li>
                        <li><a href="#contacto">Contacto</a></li>
                    </ul>
                </div>
                
                <div class="col-md-3">
                    <h4 class="footer-title">Categorías</h4>
                    <ul class="footer-links">
                        <li><a href="#">Bicicletas de Ruta</a></li>
                        <li><a href="#">Bicicletas de Montaña</a></li>
                        <li><a href="#">Accesorios</a></li>
                        <li><a href="#">Ropa de Ciclismo</a></li>
                    </ul>
                </div>
                
                <div class="col-md-3">
                    <h4 class="footer-title">Contacto</h4>
                    <ul class="footer-links">
                        <li><i class="bi bi-geo-alt me-2"></i>Av. Principal 100, Ciudad</li>
                        <li><i class="bi bi-telephone me-2"></i>555-1234</li>
                        <li><i class="bi bi-envelope me-2"></i>info@bikestore.com</li>
                        <li><i class="bi bi-clock me-2"></i>Lun-Sab: 9:00 - 18:00</li>
                    </ul>
                </div>
            </div>
            
            <hr class="my-4 bg-white opacity-25">
            
            <div class="text-center text-white-50">
                <p class="mb-0">&copy; <?= date('Y') ?> Bike Store. Todos los derechos reservados.</p>
            </div>
        </div>
    </footer>

    <!-- Scroll to Top Button -->
    <div class="scroll-top" id="scrollTop">
        <i class="bi bi-arrow-up"></i>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- AOS Animation -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    
    <script>
        // Initialize AOS
        AOS.init({
            duration: 1000,
            once: true,
            offset: 100
        });

        // Scroll to top button
        const scrollTop = document.getElementById('scrollTop');
        window.addEventListener('scroll', function() {
            if (window.scrollY > 300) {
                scrollTop.classList.add('show');
            } else {
                scrollTop.classList.remove('show');
            }
        });

        scrollTop.addEventListener('click', function() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });

        // Hero slider
        const slides = document.querySelectorAll('.hero-slide');
        let currentSlide = 0;

        function nextSlide() {
            slides[currentSlide].classList.remove('active');
            currentSlide = (currentSlide + 1) % slides.length;
            slides[currentSlide].classList.add('active');
        }

        setInterval(nextSlide, 5000);

        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    const offsetTop = target.offsetTop - 80;
                    window.scrollTo({
                        top: offsetTop,
                        behavior: 'smooth'
                    });
                    
                    // Close mobile menu
                    const navbarCollapse = document.querySelector('.navbar-collapse');
                    if (navbarCollapse.classList.contains('show')) {
                        const bsCollapse = new bootstrap.Collapse(navbarCollapse);
                        bsCollapse.hide();
                    }
                }
            });
        });

        // Counter animation
        function animateCounter(element, target, duration) {
            let start = 0;
            const increment = target / (duration / 16);
            
            const timer = setInterval(() => {
                start += increment;
                if (start >= target) {
                    element.textContent = target + '+';
                    clearInterval(timer);
                } else {
                    element.textContent = Math.floor(start) + '+';
                }
            }, 16);
        }

        // Trigger counter animation when stats section is visible
        const statsSection = document.querySelector('.stats-section');
        let statsAnimated = false;

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting && !statsAnimated) {
                    statsAnimated = true;
                    document.querySelectorAll('.stat-number').forEach((stat, index) => {
                        const targets = [5000, 200, 10, 24];
                        setTimeout(() => {
                            const numText = stat.textContent.replace('+', '');
                            animateCounter(stat, targets[index], 2000);
                        }, index * 200);
                    });
                }
            });
        });

        observer.observe(statsSection);
    </script>

</body>
</html>