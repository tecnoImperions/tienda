<?php
// =============================================
// config.php - Configuración para LOCALHOST
// Zona horaria: Bolivia
// =============================================

// Establecer zona horaria de Bolivia
date_default_timezone_set('America/La_Paz');

// Datos de conexión MySQL (LOCAL)
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'bike_db');

// Crear conexión
function getConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    if ($conn->connect_error) {
        die("Error de conexión a la base de datos: " . $conn->connect_error);
    }

    // Charset recomendado
    $conn->set_charset("utf8mb4");

    // Asegurar que MySQL use hora de Bolivia
    $conn->query("SET time_zone = '-04:00'");

    return $conn;
}
?>
