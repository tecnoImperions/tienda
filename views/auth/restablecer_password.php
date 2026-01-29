<?php
require_once '../../includes/config.php';
session_start();

$conn = getConnection();
$mensaje = '';
$tipo_mensaje = '';
$token_valido = false;
$usuario_data = null;

if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    // Verificar token y que no haya expirado
    $sql = "SELECT * FROM usuarios WHERE token_recuperacion = ? AND token_expiracion > NOW()";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $token_valido = true;
        $usuario_data = $result->fetch_assoc();
    } else {
        $mensaje = "El enlace ha expirado o es inválido.";
        $tipo_mensaje = "danger";
    }
}

// Procesar cambio de contraseña
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['restablecer'])) {
    $token = $_POST['token'];
    $nueva_password = $_POST['nueva_password'];
    $confirmar_password = $_POST['confirmar_password'];
    
    if ($nueva_password !== $confirmar_password) {
        $mensaje = "Las contraseñas no coinciden.";
        $tipo_mensaje = "danger";
        $token_valido = true; // Mantener el formulario visible
    } else {
        $password_hash = password_hash($nueva_password, PASSWORD_DEFAULT);
        
        // Actualizar contraseña y limpiar token
        $sql_update = "UPDATE usuarios SET password = ?, token_recuperacion = NULL, token_expiracion = NULL WHERE token_recuperacion = ?";
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bind_param("ss", $password_hash, $token);
        
        if ($stmt_update->execute()) {
            $mensaje = "Contraseña restablecida exitosamente. Ahora puedes iniciar sesión.";
            $tipo_mensaje = "success";
            $token_valido = false;
        } else {
            $mensaje = "Error al restablecer la contraseña.";
            $tipo_mensaje = "danger";
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restablecer Contraseña - Bike Store</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .reset-container {
            max-width: 500px;
            margin: 0 auto;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        .icon-container {
            font-size: 4rem;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="reset-container">
            <div class="card">
                <div class="card-body p-5">
                    <div class="text-center">
                        <div class="icon-container text-danger">
                            <i class="bi bi-shield-lock"></i>
                        </div>
                        <h2>Restablecer Contraseña</h2>
                    </div>

                    <?php if ($mensaje): ?>
                    <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
                        <?php echo $mensaje; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>

                    <?php if ($token_valido): ?>
                    <form method="POST" action="">
                        <input type="hidden" name="token" value="<?php echo htmlspecialchars($_GET['token']); ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">
                                <i class="bi bi-person"></i> Usuario
                            </label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($usuario_data['usuario']); ?>" disabled>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">
                                <i class="bi bi-lock"></i> Nueva Contraseña *
                            </label>
                            <input type="password" class="form-control" name="nueva_password" 
                                   placeholder="Ingresa tu nueva contraseña" minlength="6" required>
                            <small class="text-muted">Mínimo 6 caracteres</small>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">
                                <i class="bi bi-lock-fill"></i> Confirmar Nueva Contraseña *
                            </label>
                            <input type="password" class="form-control" name="confirmar_password" 
                                   placeholder="Confirma tu nueva contraseña" minlength="6" required>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" name="restablecer" class="btn btn-danger btn-lg">
                                <i class="bi bi-check-circle"></i> Restablecer Contraseña
                            </button>
                            <a href="../login.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left"></i> Cancelar
                            </a>
                        </div>
                    </form>
                    <?php else: ?>
                    <div class="text-center">
                        <?php if ($tipo_mensaje == 'success'): ?>
                        <a href="../login.php" class="btn btn-success btn-lg">
                            <i class="bi bi-box-arrow-in-right"></i> Ir a Iniciar Sesión
                        </a>
                        <?php else: ?>
                        <a href="../login.php" class="btn btn-primary">
                            <i class="bi bi-arrow-left"></i> Volver al Login
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>