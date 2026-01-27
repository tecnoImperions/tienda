-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Servidor: sql100.infinityfree.com
-- Tiempo de generación: 27-01-2026 a las 13:48:46
-- Versión del servidor: 11.4.9-MariaDB
-- Versión de PHP: 7.2.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `if0_40982212_bike_db`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `activity_log`
--

CREATE TABLE `activity_log` (
  `activity_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `session_id` int(11) DEFAULT NULL,
  `action_type` varchar(50) NOT NULL,
  `table_name` varchar(100) DEFAULT NULL,
  `record_id` int(11) DEFAULT NULL,
  `description` text NOT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL
) ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `categorias`
--

CREATE TABLE `categorias` (
  `category_id` int(11) NOT NULL,
  `descripcion` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `categorias`
--

INSERT INTO `categorias` (`category_id`, `descripcion`, `created_at`) VALUES
(1, 'Carambola !!!   %#Y/%/U 😍😍😍😍', '2026-01-24 12:47:49'),
(2, 'Bicicletas de Montaña', '2026-01-24 12:47:49'),
(3, 'Accesorios', '2026-01-24 12:47:49'),
(4, 'Ropa de Ciclismo', '2026-01-24 12:47:49');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `orders`
--

CREATE TABLE `orders` (
  `order_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `order_date` timestamp NULL DEFAULT current_timestamp(),
  `usuario_id` int(11) NOT NULL,
  `store_id` int(11) DEFAULT NULL,
  `estado` varchar(20) DEFAULT 'pendiente',
  `total` decimal(10,2) DEFAULT 0.00,
  `payment_method` varchar(50) DEFAULT 'Manual',
  `payment_id` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ;

--
-- Volcado de datos para la tabla `orders`
--

INSERT INTO `orders` (`order_id`, `customer_id`, `order_date`, `usuario_id`, `store_id`, `estado`, `total`, `payment_method`, `payment_id`, `created_at`) VALUES
(1, 2, '2026-01-24 13:06:29', 2, 1, 'en_espera', '1900.00', 'QR - Transferencia', '../uploads/comprobantes/comprobante_2_1769259989.jpg', '2026-01-24 13:06:29'),
(2, 2, '2026-01-24 21:49:47', 2, 1, 'confirmado', '1140.00', 'QR - Transferencia', '../uploads/comprobantes/comprobante_2_1769291387.jpg', '2026-01-24 21:49:47'),
(3, 2, '2026-01-25 02:14:00', 2, 1, 'entregado', '1500.00', 'QR - Transferencia', '../uploads/comprobantes/comprobante_2_1769307240.jpg', '2026-01-25 02:14:00'),
(4, 4, '2026-01-25 02:23:20', 4, 3, 'en_espera', '950.00', 'QR - Transferencia', '../uploads/comprobantes/comp_4_1769307800.jpg', '2026-01-25 02:23:20'),
(5, 2, '2026-01-25 02:48:26', 2, 1, 'entregado', '1100.00', 'QR - Transferencia', '../uploads/comprobantes/comprobante_2_1769309306.jpg', '2026-01-25 02:48:26'),
(6, 2, '2026-01-25 04:01:02', 2, 1, 'en_espera', '950.00', 'QR - Transferencia', '../uploads/comprobantes/comprobante_2_1769313662.jpg', '2026-01-25 04:01:02'),
(7, 2, '2026-01-25 12:49:14', 2, 1, 'en_espera', '950.00', 'QR - Transferencia', '../uploads/comprobantes/comprobante_2_1769345354.jpg', '2026-01-25 12:49:14'),
(8, 2, '2026-01-25 12:49:37', 2, 1, 'en_espera', '1100.00', 'QR - Transferencia', '../uploads/comprobantes/comprobante_2_1769345377.jpg', '2026-01-25 12:49:37'),
(9, 2, '2026-01-25 13:05:53', 2, 1, 'entregado', '950.00', 'QR - Transferencia', '../uploads/comprobantes/comprobante_2_1769346353.jpg', '2026-01-25 13:05:53'),
(10, 2, '2026-01-25 13:14:20', 2, 1, 'en_espera', '1100.00', 'QR - Transferencia', '../uploads/comprobantes/comprobante_2_1769346860.jpg', '2026-01-25 13:14:20'),
(11, 2, '2026-01-25 13:15:55', 2, 1, 'en_espera', '1100.00', 'QR - Transferencia', '../uploads/comprobantes/comprobante_2_1769346955.png', '2026-01-25 13:15:55'),
(12, 2, '2026-01-26 11:38:22', 2, 1, 'entregado', '1500.00', 'QR - Transferencia', '../uploads/comprobantes/comprobante_2_1769427502.jpg', '2026-01-26 11:38:22'),
(13, 2, '2026-01-26 12:38:46', 2, 1, 'en_espera', '70.00', 'QR - Transferencia', '../uploads/comprobantes/comprobante_2_1769431126.png', '2026-01-26 12:38:46'),
(14, 2, '2026-01-26 12:40:08', 2, 1, 'entregado', '50.00', 'QR - Transferencia', '../uploads/comprobantes/comprobante_2_1769431208.jpg', '2026-01-26 12:40:08'),
(15, 2, '2026-01-26 12:50:20', 2, 1, 'anulado', '500.00', 'QR - Transferencia', '../uploads/comprobantes/comprobante_2_1769431820.jpg', '2026-01-26 12:50:20'),
(16, 2, '2026-01-26 13:09:23', 2, 1, 'en_espera', '110.00', 'QR - Transferencia', '../uploads/comprobantes/comprobante_2_1769432963.png', '2026-01-26 13:09:23');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `order_items`
--

CREATE TABLE `order_items` (
  `order_item_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `price` decimal(10,2) NOT NULL,
  `discount` decimal(5,2) DEFAULT 0.00,
  `subtotal` decimal(10,2) GENERATED ALWAYS AS (`quantity` * `price` * (1 - `discount` / 100)) STORED,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `order_items`
--

INSERT INTO `order_items` (`order_item_id`, `order_id`, `product_id`, `quantity`, `price`, `discount`, `created_at`) VALUES
(1, 1, 3, 2, '950.00', '0.00', '2026-01-24 13:06:29'),
(2, 2, 3, 1, '950.00', '0.00', '2026-01-24 21:49:47'),
(3, 2, 9, 1, '50.00', '0.00', '2026-01-24 21:49:47'),
(4, 2, 8, 2, '50.00', '0.00', '2026-01-24 21:49:47'),
(5, 2, 7, 1, '40.00', '0.00', '2026-01-24 21:49:47'),
(6, 3, 2, 1, '1500.00', '0.00', '2026-01-25 02:14:00'),
(7, 4, 3, 1, '950.00', '0.00', '2026-01-25 02:23:20'),
(8, 5, 4, 1, '1100.00', '0.00', '2026-01-25 02:48:26'),
(9, 6, 3, 1, '950.00', '0.00', '2026-01-25 04:01:02'),
(10, 7, 3, 1, '950.00', '0.00', '2026-01-25 12:49:14'),
(11, 8, 4, 1, '1100.00', '0.00', '2026-01-25 12:49:37'),
(12, 9, 3, 1, '950.00', '0.00', '2026-01-25 13:05:53'),
(13, 10, 4, 1, '1100.00', '0.00', '2026-01-25 13:14:20'),
(14, 11, 4, 1, '1100.00', '0.00', '2026-01-25 13:15:55'),
(15, 12, 2, 1, '1500.00', '0.00', '2026-01-26 11:38:22'),
(16, 13, 10, 1, '70.00', '0.00', '2026-01-26 12:38:46'),
(17, 14, 9, 1, '50.00', '0.00', '2026-01-26 12:40:08'),
(18, 15, 6, 20, '25.00', '0.00', '2026-01-26 12:50:20'),
(19, 16, 10, 1, '70.00', '0.00', '2026-01-26 13:09:23'),
(20, 16, 7, 1, '40.00', '0.00', '2026-01-26 13:09:23');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `productos`
--

CREATE TABLE `productos` (
  `product_id` int(11) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `foto` varchar(500) DEFAULT NULL,
  `model_year` int(11) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `productos`
--

INSERT INTO `productos` (`product_id`, `product_name`, `foto`, `model_year`, `price`, `category_id`, `created_at`) VALUES
(1, 'Bicicleta Ruta SpeedX', '../uploads/6974c1b2ad76e.jpg', 2023, '1200.00', 1, '2026-01-24 12:47:49'),
(2, 'Bicicleta Ruta Aero', '../uploads/6974c1bdbee2e.png', 2022, '1500.00', 1, '2026-01-24 12:47:49'),
(3, 'Bicicleta Montaña Rocker', '../uploads/6974c1c6e6d62.jpg', 2023, '950.00', 2, '2026-01-24 12:47:49'),
(4, 'Bicicleta Montaña Trail', '../uploads/6974c1d3e9b47.jpg', 2022, '1100.00', 2, '2026-01-24 12:47:49'),
(5, 'Casco Ciclismo Pro', '../uploads/6974c2310a062.png', 2023, '75.00', 3, '2026-01-24 12:47:49'),
(6, 'Guantes Ciclismo', '../uploads/6974c238f2e2e.jpg', 2023, '25.00', 3, '2026-01-24 12:47:49'),
(7, 'Luces LED Bicicleta', '../uploads/6974c24238e81.jpg', 2023, '40.00', 3, '2026-01-24 12:47:49'),
(8, 'Maillot Ciclismo Hombre', '../uploads/6974c293665b8.jpg', 2023, '50.00', 4, '2026-01-24 12:47:49'),
(9, 'Maillot Ciclismo Mujer', '../uploads/6974c29b5b563.jpg', 2023, '50.00', 4, '2026-01-24 12:47:49'),
(10, 'Culotte Ciclismo', '../uploads/6974c2a498d18.jpg', 2023, '70.00', 4, '2026-01-24 12:47:49');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `purchase_history`
--

CREATE TABLE `purchase_history` (
  `history_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `action_type` varchar(50) NOT NULL,
  `previous_status` varchar(20) DEFAULT NULL,
  `new_status` varchar(20) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `store_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `purchase_history`
--

INSERT INTO `purchase_history` (`history_id`, `user_id`, `order_id`, `action_type`, `previous_status`, `new_status`, `amount`, `store_id`, `description`, `ip_address`, `created_at`) VALUES
(1, 2, 1, 'CREACION', NULL, 'en_espera', '1900.00', 1, 'Orden creada con comprobante de pago', NULL, '2026-01-24 13:06:29'),
(2, 2, 2, 'CREACION', NULL, 'en_espera', '1140.00', 1, 'Orden creada con comprobante de pago', NULL, '2026-01-24 21:49:47'),
(3, 2, 3, 'CREACION', NULL, 'en_espera', '1500.00', 1, 'Orden creada con comprobante de pago', NULL, '2026-01-25 02:14:00'),
(4, 2, 5, 'CREACION', NULL, 'en_espera', '1100.00', 1, 'Orden creada con comprobante de pago', NULL, '2026-01-25 02:48:26'),
(5, 2, 6, 'CREACION', NULL, 'en_espera', '950.00', 1, 'Orden creada con comprobante de pago', NULL, '2026-01-25 04:01:02'),
(6, 2, 7, 'CREACION', NULL, 'en_espera', '950.00', 1, 'Orden creada con comprobante de pago', NULL, '2026-01-25 12:49:14'),
(7, 2, 8, 'CREACION', NULL, 'en_espera', '1100.00', 1, 'Orden creada con comprobante de pago', NULL, '2026-01-25 12:49:37'),
(8, 2, 9, 'CREACION', NULL, 'en_espera', '950.00', 1, 'Orden creada con comprobante de pago', NULL, '2026-01-25 13:05:53'),
(9, 2, 10, 'CREACION', NULL, 'en_espera', '1100.00', 1, 'Orden creada con comprobante de pago', NULL, '2026-01-25 13:14:20'),
(10, 2, 11, 'CREACION', NULL, 'en_espera', '1100.00', 1, 'Orden creada con comprobante de pago', NULL, '2026-01-25 13:15:55'),
(11, 2, 12, 'CREACION', NULL, 'en_espera', '1500.00', 1, 'Orden creada con comprobante de pago', NULL, '2026-01-26 11:38:22'),
(12, 2, 13, 'CREACION', NULL, 'en_espera', '70.00', 1, 'Orden creada con comprobante de pago', NULL, '2026-01-26 12:38:46'),
(13, 2, 14, 'CREACION', NULL, 'en_espera', '50.00', 1, 'Orden creada con comprobante de pago', NULL, '2026-01-26 12:40:08'),
(14, 2, 15, 'CREACION', NULL, 'en_espera', '500.00', 1, 'Orden creada con comprobante de pago', NULL, '2026-01-26 12:50:20'),
(15, 2, 16, 'CREACION', NULL, 'en_espera', '110.00', 1, 'Orden creada con comprobante de pago', NULL, '2026-01-26 13:09:23');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `stocks`
--

CREATE TABLE `stocks` (
  `stock_id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `stocks`
--

INSERT INTO `stocks` (`stock_id`, `store_id`, `product_id`, `quantity`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 5, '2026-01-24 12:47:49', '2026-01-24 12:47:49'),
(2, 1, 2, 1, '2026-01-24 12:47:49', '2026-01-26 11:38:22'),
(3, 1, 3, 0, '2026-01-24 12:47:49', '2026-01-25 13:05:53'),
(4, 1, 4, 0, '2026-01-24 12:47:49', '2026-01-25 13:15:55'),
(5, 1, 5, 15, '2026-01-24 12:47:49', '2026-01-24 12:47:49'),
(6, 1, 6, 0, '2026-01-24 12:47:49', '2026-01-26 12:50:20'),
(7, 1, 7, 10, '2026-01-24 12:47:49', '2026-01-26 13:09:23'),
(8, 1, 8, 8, '2026-01-24 12:47:49', '2026-01-24 21:49:47'),
(9, 1, 9, 8, '2026-01-24 12:47:49', '2026-01-26 12:40:08'),
(10, 1, 10, 6, '2026-01-24 12:47:49', '2026-01-26 13:09:23'),
(11, 2, 1, 3, '2026-01-24 12:47:49', '2026-01-24 12:47:49'),
(12, 2, 2, 5, '2026-01-24 12:47:49', '2026-01-25 03:20:01'),
(13, 2, 3, 6, '2026-01-24 12:47:49', '2026-01-25 03:19:20'),
(14, 2, 4, 3, '2026-01-24 12:47:49', '2026-01-24 12:47:49'),
(15, 2, 5, 10, '2026-01-24 12:47:49', '2026-01-24 12:47:49'),
(16, 2, 6, 15, '2026-01-24 12:47:49', '2026-01-24 12:47:49'),
(17, 2, 7, 10, '2026-01-24 12:47:49', '2026-01-24 12:47:49'),
(18, 2, 8, 7, '2026-01-24 12:47:49', '2026-01-24 12:47:49'),
(19, 2, 9, 7, '2026-01-24 12:47:49', '2026-01-24 12:47:49'),
(20, 2, 10, 5, '2026-01-24 12:47:49', '2026-01-24 12:47:49'),
(21, 3, 1, 2, '2026-01-24 12:47:49', '2026-01-24 12:47:49'),
(22, 3, 2, 2, '2026-01-24 12:47:49', '2026-01-24 12:47:49'),
(23, 3, 3, 3, '2026-01-24 12:47:49', '2026-01-25 02:23:20'),
(24, 3, 4, 2, '2026-01-24 12:47:49', '2026-01-24 12:47:49'),
(25, 3, 5, 8, '2026-01-24 12:47:49', '2026-01-24 12:47:49'),
(26, 3, 6, 10, '2026-01-24 12:47:49', '2026-01-24 12:47:49'),
(27, 3, 7, 8, '2026-01-24 12:47:49', '2026-01-24 12:47:49'),
(28, 3, 8, 5, '2026-01-24 12:47:49', '2026-01-24 12:47:49'),
(29, 3, 9, 5, '2026-01-24 12:47:49', '2026-01-24 12:47:49'),
(30, 3, 10, 4, '2026-01-24 12:47:49', '2026-01-24 12:47:49');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `stores`
--

CREATE TABLE `stores` (
  `store_id` int(11) NOT NULL,
  `store_name` varchar(255) NOT NULL,
  `phone` varchar(25) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `street` varchar(255) DEFAULT NULL,
  `city` varchar(255) DEFAULT NULL,
  `state` varchar(255) DEFAULT NULL,
  `estado` varchar(20) DEFAULT 'activa',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ;

--
-- Volcado de datos para la tabla `stores`
--

INSERT INTO `stores` (`store_id`, `store_name`, `phone`, `email`, `street`, `city`, `state`, `estado`, `created_at`) VALUES
(1, 'Tienda Central Bike', '555-1234', 'central@bikestore.com', 'Av. Principal 100', 'Ciudad', 'Estado', 'activa', '2026-01-24 12:47:49'),
(2, 'Sucursal Norte Bike', '555-5678', 'norte@bikestore.com', 'Calle Norte 50', 'Ciudad', 'Estado', 'activa', '2026-01-24 12:47:49'),
(3, 'Sucursal Sur Bike', '555-9012', 'sur@bikestore.com', 'Calle Sur 75', 'Ciudad', 'Estado', 'activa', '2026-01-24 12:47:49');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `user_sessions`
--

CREATE TABLE `user_sessions` (
  `session_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `login_time` timestamp NULL DEFAULT current_timestamp(),
  `logout_time` timestamp NULL DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `device_type` varchar(50) DEFAULT NULL,
  `browser` varchar(100) DEFAULT NULL,
  `operating_system` varchar(100) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `user_id` int(11) NOT NULL,
  `usuario` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `role` varchar(20) DEFAULT 'usuario',
  `verificado` tinyint(1) DEFAULT 0,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `session_token` varchar(64) DEFAULT NULL,
  `force_logout` tinyint(1) DEFAULT 0,
  `token_verificacion` varchar(100) DEFAULT NULL,
  `token_recuperacion` varchar(100) DEFAULT NULL,
  `token_expiracion` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`user_id`, `usuario`, `password`, `email`, `role`, `verificado`, `activo`, `session_token`, `force_logout`, `token_verificacion`, `token_recuperacion`, `token_expiracion`, `created_at`) VALUES
(2, 'Juan', '$2y$10$DoKZ86emFJdaN3A3sa5CTOzheaugpjvm42lCXnxz4PWykZCQnVE9i', 'juanrevollo001@gmail.com', 'usuario', 1, 0, 'bfb596f9490660baf4c142625cd3bbbf9066bc3bfe9deaae4fda3147051670c2', 0, NULL, NULL, NULL, '2026-01-24 12:42:53'),
(3, 'admin', '$2y$10$njWYBBUbF3vGLDvEYDkieuXuTn8WrDgnZ9A9PZ0vCsMV6nkLTUZgq', 'revotrading76@gmail.com', 'admin', 1, 1, 'c6d142ffd7cbf4304a63dd5d2cf1338f5bd66c765f90c974446992a262e0b112', 0, NULL, NULL, NULL, '2026-01-24 12:48:45'),
(4, 'Andres', '$2y$10$mH5x3h3zDCTnPKRxRckpluOvSK6xkg.S.JQd53j8t.uBRdTUVuuTC', 'borleone101@gmail.com', 'empleado', 1, 1, '3ac9489c4857be5eeddf89d0b4fe737ad5fe0135bb138888dffc637dd08512c1', 0, NULL, NULL, NULL, '2026-01-24 22:10:31'),
(5, 'lan', '$2y$10$y/eVYGkxe1XvtMt27yuWHuPBFfR086ahOBP//i6ctQfw8Ug.fTcNu', 'gofokow582@ixunbo.com', 'usuario', 1, 1, 'ca17598e8f7ca03f111f55399d8c86f5e438dfb1cbc6f93529294d86aef6a285', 0, NULL, NULL, NULL, '2026-01-26 11:45:00');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `categorias`
--
ALTER TABLE `categorias`
  ADD PRIMARY KEY (`category_id`),
  ADD KEY `idx_categorias_descripcion` (`descripcion`);

--
-- Indices de la tabla `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`order_id`),
  ADD KEY `fk_orders_usuario` (`usuario_id`),
  ADD KEY `fk_orders_store` (`store_id`);

--
-- Indices de la tabla `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`order_item_id`),
  ADD KEY `fk_items_order` (`order_id`),
  ADD KEY `fk_items_product` (`product_id`);

--
-- Indices de la tabla `productos`
--
ALTER TABLE `productos`
  ADD PRIMARY KEY (`product_id`),
  ADD KEY `idx_productos_name` (`product_name`),
  ADD KEY `idx_productos_category` (`category_id`),
  ADD KEY `idx_productos_price` (`price`);

--
-- Indices de la tabla `purchase_history`
--
ALTER TABLE `purchase_history`
  ADD PRIMARY KEY (`history_id`),
  ADD KEY `fk_history_user` (`user_id`),
  ADD KEY `fk_history_order` (`order_id`),
  ADD KEY `fk_history_store` (`store_id`);

--
-- Indices de la tabla `stocks`
--
ALTER TABLE `stocks`
  ADD PRIMARY KEY (`stock_id`),
  ADD UNIQUE KEY `unique_store_product` (`store_id`,`product_id`),
  ADD KEY `fk_stocks_product` (`product_id`);

--
-- Indices de la tabla `stores`
--
ALTER TABLE `stores`
  ADD PRIMARY KEY (`store_id`),
  ADD KEY `idx_stores_name` (`store_name`),
  ADD KEY `idx_stores_estado` (`estado`);

--
-- Indices de la tabla `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`session_id`),
  ADD KEY `fk_sessions_user` (`user_id`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `usuario` (`usuario`),
  ADD KEY `idx_usuarios_usuario` (`usuario`),
  ADD KEY `idx_usuarios_email` (`email`),
  ADD KEY `idx_usuarios_role` (`role`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `activity_log`
--
ALTER TABLE `activity_log`
  MODIFY `activity_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `categorias`
--
ALTER TABLE `categorias`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `order_items`
--
ALTER TABLE `order_items`
  MODIFY `order_item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT de la tabla `productos`
--
ALTER TABLE `productos`
  MODIFY `product_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de la tabla `purchase_history`
--
ALTER TABLE `purchase_history`
  MODIFY `history_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT de la tabla `stocks`
--
ALTER TABLE `stocks`
  MODIFY `stock_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT de la tabla `stores`
--
ALTER TABLE `stores`
  MODIFY `store_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `user_sessions`
--
ALTER TABLE `user_sessions`
  MODIFY `session_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `fk_orders_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`store_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_orders_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`user_id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `fk_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_items_product` FOREIGN KEY (`product_id`) REFERENCES `productos` (`product_id`);

--
-- Filtros para la tabla `productos`
--
ALTER TABLE `productos`
  ADD CONSTRAINT `fk_productos_categoria` FOREIGN KEY (`category_id`) REFERENCES `categorias` (`category_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `purchase_history`
--
ALTER TABLE `purchase_history`
  ADD CONSTRAINT `fk_history_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_history_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`store_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_history_user` FOREIGN KEY (`user_id`) REFERENCES `usuarios` (`user_id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `stocks`
--
ALTER TABLE `stocks`
  ADD CONSTRAINT `fk_stocks_product` FOREIGN KEY (`product_id`) REFERENCES `productos` (`product_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_stocks_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`store_id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD CONSTRAINT `fk_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `usuarios` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
