<?php
// Prevenir error de permisos
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../includes/config.php';
session_start();

$conn = getConnection();
$mensaje = '';
$tipo_mensaje = '';
$verificado = false;

if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = trim($_GET['token']);
    
    // Buscar usuario con ese token
    $sql = "SELECT * FROM usuarios WHERE token_verificacion = ? AND verificado = 0";
    $stmt = $conn->prepare($sql);
    
    if ($stmt) {
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            // Verificar la cuenta
            $sql_update = "UPDATE usuarios SET verificado = 1, token_verificacion = NULL WHERE user_id = ?";
            $stmt_update = $conn->prepare($sql_update);
            $stmt_update->bind_param("i", $user['user_id']);
            
            if ($stmt_update->execute()) {
                $verificado = true;
                $mensaje = "¡Cuenta verificada exitosamente!";
                $tipo_mensaje = "success";
            } else {
                $mensaje = "Error al verificar la cuenta.";
                $tipo_mensaje = "danger";
            }
        } else {
            $mensaje = "Token inválido o la cuenta ya fue verificada.";
            $tipo_mensaje = "warning";
        }
    } else {
        $mensaje = "Error en la consulta: " . $conn->error;
        $tipo_mensaje = "danger";
    }
} else {
    $mensaje = "Token no proporcionado.";
    $tipo_mensaje = "danger";
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificar Cuenta - Bike Store</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .verification-container {
            max-width: 500px;
            margin: 0 auto;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        .icon-container {
            font-size: 5rem;
            margin: 30px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="verification-container">
            <div class="card">
                <div class="card-body p-5 text-center">
                    <?php if ($verificado): ?>
                    <div class="icon-container text-success">
                        <i class="bi bi-check-circle-fill"></i>
                    </div>
                    <h2>¡Cuenta Verificada!</h2>
                    <p class="lead">Tu cuenta ha sido verificada exitosamente.</p>
                    <p>Ahora puedes iniciar sesión con tus credenciales.</p>
                    <a href="../login.php" class="btn btn-success btn-lg mt-3">
                        <i class="bi bi-box-arrow-in-right"></i> Ir a Iniciar Sesión
                    </a>
                    <?php else: ?>
                    <div class="icon-container text-warning">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                    </div>
                    <h2>Verificación Fallida</h2>
                    <div class="alert alert-<?php echo $tipo_mensaje; ?>">
                        <?php echo $mensaje; ?>
                    </div>
                    <a href="../login.php" class="btn btn-primary mt-3">
                        <i class="bi bi-arrow-left"></i> Volver al Login
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>