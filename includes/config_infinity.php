<?php
// =============================================
// config.php - Configuración para INFINITYFREE
// Zona horaria: Bolivia
// =============================================

// Establecer zona horaria de Bolivia
date_default_timezone_set('America/La_Paz');

// Datos de conexión MySQL (INFINITYFREE)
define('DB_HOST', 'sql100.infinityfree.com');
define('DB_USER', 'if0_40982212');
define('DB_PASS', 'QH55S9A9UDc');
define('DB_NAME', 'if0_40982212_bike_db'); // <-- asegúrate de que sea el nombre exacto

// Crear conexión
function getConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, 3306);

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