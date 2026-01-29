<?php
require_once '../includes/config.php';

// Verificar si PHPMailer existe antes de incluirlo
$phpmailer_disponible = false;
if (file_exists('../includes/PHPMailer/PHPMailer.php')) {
    require_once '../includes/email_config.php';
    $phpmailer_disponible = true;
}

session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$conn = getConnection();
$mensaje = '';
$tipo_mensaje = '';

// ==========================================
// FUNCIONES DE VALIDACIÓN Y SEGURIDAD
// ==========================================
function validarUsuario($usuario) {
    // Solo letras, números, guión bajo y guión medio. Entre 3 y 30 caracteres
    if (!preg_match('/^[a-zA-Z0-9_-]{3,30}$/', $usuario)) {
        return "El usuario solo puede contener letras, números, guiones y debe tener entre 3 y 30 caracteres";
    }
    return true;
}

function validarEmail($email) {
    // Validar formato de email y longitud máxima
    if (strlen($email) > 100) {
        return "El email es demasiado largo";
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return "El formato del email no es válido";
    }
    return true;
}

function validarPassword($password) {
    // Mínimo 6 caracteres, máximo 72 (límite de bcrypt)
    if (strlen($password) < 6) {
        return "La contraseña debe tener al menos 6 caracteres";
    }
    if (strlen($password) > 72) {
        return "La contraseña es demasiado larga (máximo 72 caracteres)";
    }
    // Evitar caracteres especiales problemáticos
    if (preg_match('/[<>\"\'&]/', $password)) {
        return "La contraseña contiene caracteres no permitidos";
    }
    return true;
}

function limpiarInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// ==========================================
// PROCESAR LOGIN
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $usuario = limpiarInput($_POST['usuario']);
    $password = $_POST['password']; // No limpiar password con htmlspecialchars

    if (empty($usuario) || empty($password)) {
        $mensaje = "Por favor completa todos los campos";
        $tipo_mensaje = "warning";
    } else {
        // Validar usuario
        $validacion = validarUsuario($usuario);
        if ($validacion !== true) {
            $mensaje = $validacion;
            $tipo_mensaje = "danger";
        } else {
            $sql = "SELECT * FROM usuarios WHERE usuario = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $usuario);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();

                // Verificar contraseña
                if (password_verify($password, $user['password'])) {

                    // -----------------------------
                    // CASOS DE BLOQUEO / EXPULSIÓN
                    // -----------------------------
                    if (isset($user['activo']) && (int)$user['activo'] === 0) {
                        $mensaje = "Tu cuenta está desactivada. Contacta al administrador.";
                        $tipo_mensaje = "danger";
                    } elseif (isset($user['verificado']) && (int)$user['verificado'] === 0) {
                        $mensaje = "Debes verificar tu cuenta. Revisa tu correo electrónico.";
                        $tipo_mensaje = "warning";
                    } else {
                        // -----------------------------
                        // LOGIN CORRECTO
                        // -----------------------------

                        // generar token de sesión único
                        $session_token = bin2hex(random_bytes(32));

                        // Marcar cualquier otra sesión del mismo usuario para cerrar (force_logout)
                        $upd_old = $conn->prepare("
                            UPDATE usuarios
                            SET force_logout = 1
                            WHERE user_id = ? AND session_token IS NOT NULL
                        ");
                        $upd_old->bind_param("i", $user['user_id']);
                        $upd_old->execute();

                        // Guardar token en esta sesión y resetear force_logout
                        $upd = $conn->prepare("
                            UPDATE usuarios
                            SET session_token = ?, force_logout = 0
                            WHERE user_id = ?
                        ");
                        $upd->bind_param("si", $session_token, $user['user_id']);
                        $upd->execute();

                        // Crear sesión
                        $_SESSION['user_id'] = $user['user_id'];
                        $_SESSION['usuario'] = $user['usuario'];
                        $_SESSION['email']   = $user['email'];
                        $_SESSION['role']    = $user['role'];
                        $_SESSION['session_token'] = $session_token;

                        // FLAG PARA SWEETALERT EN INDEX
                        $_SESSION['login_success'] = true;

                        header('Location: index.php');
                        exit();
                    }

                } else {
                    $mensaje = "Contraseña incorrecta";
                    $tipo_mensaje = "danger";
                }
            } else {
                $mensaje = "Usuario no encontrado";
                $tipo_mensaje = "danger";
            }
        }
    }
}

// ==========================================
// PROCESAR REGISTRO
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register'])) {
    $usuario = limpiarInput($_POST['reg_usuario']);
    $email = limpiarInput($_POST['reg_email']);
    $password = $_POST['reg_password'];
    $confirm_password = $_POST['reg_confirm_password'];

    // Validaciones
    $errores = [];
    
    $validacion_usuario = validarUsuario($usuario);
    if ($validacion_usuario !== true) {
        $errores[] = $validacion_usuario;
    }
    
    $validacion_email = validarEmail($email);
    if ($validacion_email !== true) {
        $errores[] = $validacion_email;
    }
    
    $validacion_password = validarPassword($password);
    if ($validacion_password !== true) {
        $errores[] = $validacion_password;
    }

    if ($password !== $confirm_password) {
        $errores[] = "Las contraseñas no coinciden";
    }

    if (!empty($errores)) {
        $mensaje = implode(". ", $errores);
        $tipo_mensaje = "danger";
    } else {
        // Verificar si el usuario ya existe
        $check_user = $conn->prepare("SELECT user_id FROM usuarios WHERE usuario = ? OR email = ?");
        $check_user->bind_param("ss", $usuario, $email);
        $check_user->execute();
        $result_check = $check_user->get_result();

        if ($result_check->num_rows > 0) {
            $mensaje = "El usuario o email ya existe";
            $tipo_mensaje = "danger";
        } else {
            $password_hash = password_hash($password, PASSWORD_BCRYPT);

            // Verificar si existen las columnas de verificación
            $columnas_result = $conn->query("SHOW COLUMNS FROM usuarios LIKE 'verificado'");

            if ($columnas_result->num_rows > 0 && $phpmailer_disponible) {
                // Sistema con verificación por email
                $token_verificacion = bin2hex(random_bytes(32));

                $sql = "INSERT INTO usuarios (usuario, password, email, role, verificado, activo, force_logout, token_verificacion) 
                        VALUES (?, ?, ?, 'usuario', 0, 1, 0, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssss", $usuario, $password_hash, $email, $token_verificacion);

                if ($stmt->execute()) {
                    try {
                        if (enviarEmailVerificacion($email, $usuario, $token_verificacion)) {
                            $mensaje = "Registro exitoso. Revisa tu correo para verificar tu cuenta.";
                            $tipo_mensaje = "success";
                        } else {
                            $conn->query("UPDATE usuarios SET verificado = 1, token_verificacion = NULL WHERE usuario = '$usuario'");
                            $mensaje = "Registro exitoso. Ya puedes iniciar sesión.";
                            $tipo_mensaje = "success";
                        }
                    } catch (Exception $e) {
                        $conn->query("UPDATE usuarios SET verificado = 1, token_verificacion = NULL WHERE usuario = '$usuario'");
                        $mensaje = "Registro exitoso. Ya puedes iniciar sesión.";
                        $tipo_mensaje = "success";
                    }
                } else {
                    $mensaje = "Error al registrar. Intenta nuevamente.";
                    $tipo_mensaje = "danger";
                }
            } else {
                // Sistema sin verificación
                $sql = "INSERT INTO usuarios (usuario, password, email, role, activo, force_logout) 
                        VALUES (?, ?, ?, 'usuario', 1, 0)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sss", $usuario, $password_hash, $email);

                if ($stmt->execute()) {
                    $mensaje = "Registro exitoso. Ya puedes iniciar sesión.";
                    $tipo_mensaje = "success";
                } else {
                    $mensaje = "Error al registrar. Intenta nuevamente.";
                    $tipo_mensaje = "danger";
                }
            }
        }
    }
}

// ==========================================
// PROCESAR RECUPERACIÓN DE CONTRASEÑA
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['recuperar'])) {
    if (!$phpmailer_disponible) {
        $mensaje = "El sistema de recuperación de contraseña no está disponible. Contacta al administrador.";
        $tipo_mensaje = "warning";
    } else {
        $email = limpiarInput($_POST['recovery_email']);
        
        $validacion_email = validarEmail($email);
        if ($validacion_email !== true) {
            $mensaje = $validacion_email;
            $tipo_mensaje = "danger";
        } else {
            $sql = "SELECT * FROM usuarios WHERE email = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();

                $columnas_result = $conn->query("SHOW COLUMNS FROM usuarios LIKE 'token_recuperacion'");

                if ($columnas_result->num_rows > 0) {
                    $token_recuperacion = bin2hex(random_bytes(32));
                    $expiracion = date('Y-m-d H:i:s', strtotime('+1 hour'));

                    $sql_update = "UPDATE usuarios SET token_recuperacion = ?, token_expiracion = ? WHERE email = ?";
                    $stmt_update = $conn->prepare($sql_update);
                    $stmt_update->bind_param("sss", $token_recuperacion, $expiracion, $email);
                    $stmt_update->execute();

                    try {
                        if (enviarEmailRecuperacion($email, $user['usuario'], $token_recuperacion)) {
                            $mensaje = "Email enviado. Revisa tu bandeja de entrada.";
                            $tipo_mensaje = "success";
                        } else {
                            $mensaje = "Error al enviar email. Intenta de nuevo.";
                            $tipo_mensaje = "danger";
                        }
                    } catch (Exception $e) {
                        $mensaje = "Error al enviar email. Contacta al administrador.";
                        $tipo_mensaje = "danger";
                    }
                } else {
                    $mensaje = "Sistema de recuperación no configurado. Contacta al administrador.";
                    $tipo_mensaje = "warning";
                }
            } else {
                $mensaje = "No existe una cuenta con ese email.";
                $tipo_mensaje = "danger";
            }
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
<meta name="theme-color" content="#667eea">
<title>Login - Bike Store</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

html, body {
    height: 100%;
    overflow-x: hidden;
}

body {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 100vh;
    padding: 1rem;
}

/* Animación de fondo sutil */
body::before {
    content: '';
    position: fixed;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: 
        radial-gradient(circle at 20% 50%, rgba(255,255,255,0.08) 0%, transparent 50%),
        radial-gradient(circle at 80% 80%, rgba(255,255,255,0.08) 0%, transparent 50%);
    animation: bgMove 25s ease-in-out infinite;
    pointer-events: none;
    z-index: 0;
}

@keyframes bgMove {
    0%, 100% { transform: translate(0, 0) rotate(0deg); }
    50% { transform: translate(30px, 30px) rotate(5deg); }
}

.login-container {
    width: 100%;
    max-width: 480px;
    position: relative;
    z-index: 1;
    margin: auto;
}

.brand-header {
    text-align: center;
    margin-bottom: 2rem;
    animation: fadeInDown 0.6s ease-out;
}

.brand-header .icon-container {
    background: rgba(255,255,255,0.15);
    width: 90px;
    height: 90px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.25rem;
    backdrop-filter: blur(20px);
    border: 2px solid rgba(255,255,255,0.25);
    box-shadow: 0 10px 40px rgba(0,0,0,0.15);
    transition: transform 0.3s ease;
}

.brand-header .icon-container:hover {
    transform: scale(1.05);
}

.brand-header i {
    font-size: 3rem;
    color: white;
    filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));
}

.brand-header h1 {
    color: white;
    font-weight: 700;
    font-size: 2.25rem;
    margin-bottom: 0.5rem;
    text-shadow: 0 2px 10px rgba(0,0,0,0.2);
    letter-spacing: -0.5px;
}

.brand-header p {
    color: rgba(255,255,255,0.95);
    font-size: 1rem;
    font-weight: 400;
    letter-spacing: 0.3px;
}

.card {
    border: none;
    border-radius: 24px;
    box-shadow: 0 25px 50px rgba(0,0,0,0.25);
    overflow: hidden;
    animation: fadeInUp 0.6s ease-out;
    background: white;
}

.card-body {
    padding: 2.5rem;
}

.nav-pills {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    padding: 0.4rem;
    border-radius: 14px;
    margin-bottom: 2rem;
    box-shadow: inset 0 2px 4px rgba(0,0,0,0.06);
}

.nav-pills .nav-link {
    color: #667eea;
    border-radius: 11px;
    padding: 0.8rem 1.25rem;
    font-weight: 600;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border: none;
    position: relative;
    overflow: hidden;
}

.nav-pills .nav-link::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(102, 126, 234, 0.08);
    transform: scaleX(0);
    transition: transform 0.3s ease;
    border-radius: 11px;
}

.nav-pills .nav-link:hover::before {
    transform: scaleX(1);
}

.nav-pills .nav-link.active {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    box-shadow: 0 6px 16px rgba(102, 126, 234, 0.35);
    transform: translateY(-1px);
}

.form-label {
    font-weight: 600;
    color: #334155;
    margin-bottom: 0.6rem;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.form-label i {
    color: #667eea;
    font-size: 1rem;
}

.password-wrapper {
    position: relative;
}

.password-wrapper input {
    padding-right: 3rem;
}

.password-toggle {
    position: absolute;
    right: 1rem;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: #667eea;
    cursor: pointer;
    padding: 0.5rem;
    transition: all 0.3s ease;
    font-size: 1.1rem;
    z-index: 10;
}

.password-toggle:hover {
    color: #764ba2;
    transform: translateY(-50%) scale(1.1);
}

.form-control,
.form-control-lg {
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    padding: 0.85rem 1.1rem;
    transition: all 0.3s ease;
    font-size: 0.95rem;
    background: #f8fafc;
}

.form-control:hover {
    border-color: #cbd5e1;
    background: white;
}

.form-control:focus,
.form-control-lg:focus {
    border-color: #667eea;
    background: white;
    box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
    outline: none;
}

.form-control-lg {
    padding: 1rem 1.25rem;
    font-size: 1rem;
}

.form-control::placeholder {
    color: #94a3b8;
    opacity: 1;
}

.btn {
    border-radius: 12px;
    padding: 0.85rem 1.5rem;
    font-weight: 600;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border: none;
    position: relative;
    overflow: hidden;
}

.btn::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    border-radius: 50%;
    background: rgba(255,255,255,0.2);
    transform: translate(-50%, -50%);
    transition: width 0.6s, height 0.6s;
}

.btn:hover::before {
    width: 300px;
    height: 300px;
}

.btn-lg {
    padding: 1rem 2rem;
    font-size: 1.05rem;
}

.btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    box-shadow: 0 4px 14px rgba(102, 126, 234, 0.3);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(102, 126, 234, 0.4);
}

.btn-primary:active {
    transform: translateY(0);
}

.btn-success {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    box-shadow: 0 4px 14px rgba(16, 185, 129, 0.3);
    color: white;
}

.btn-success:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(16, 185, 129, 0.4);
}

.btn-danger {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: white;
    box-shadow: 0 4px 14px rgba(239, 68, 68, 0.3);
}

.btn-danger:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(239, 68, 68, 0.4);
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background: #5a6268;
}

.alert {
    border: none;
    border-radius: 14px;
    padding: 1rem 1.25rem;
    margin-bottom: 1.5rem;
    animation: fadeIn 0.4s ease-out;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-weight: 500;
}

.alert i {
    font-size: 1.25rem;
}

.alert-success {
    background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
    color: #065f46;
    border-left: 4px solid #10b981;
}

.alert-danger {
    background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
    color: #991b1b;
    border-left: 4px solid #ef4444;
}

.alert-warning {
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    color: #92400e;
    border-left: 4px solid #f59e0b;
}

.modal-content {
    border: none;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 25px 50px rgba(0,0,0,0.25);
}

.modal-header {
    padding: 1.5rem 1.75rem;
    border: none;
}

.modal-body {
    padding: 1.5rem 1.75rem;
}

.modal-footer {
    padding: 1.25rem 1.75rem;
    border: none;
    background: #f8f9fa;
}

/* Animaciones */
@keyframes fadeInDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

/* Link de recuperación */
.recovery-link {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    color: #667eea;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
    padding: 0.6rem 1.2rem;
    border-radius: 10px;
    font-size: 0.9rem;
}

.recovery-link:hover {
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
    color: #764ba2;
    transform: translateY(-1px);
}

.recovery-link i {
    transition: transform 0.3s ease;
}

.recovery-link:hover i {
    transform: rotate(15deg);
}

/* Small text improvements */
small.text-muted {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    color: #64748b;
}

/* Responsive Tablet */
@media (max-width: 768px) {
    .login-container {
        max-width: 100%;
    }

    .brand-header {
        margin-bottom: 1.5rem;
    }

    .brand-header .icon-container {
        width: 75px;
        height: 75px;
    }

    .brand-header i {
        font-size: 2.5rem;
    }

    .brand-header h1 {
        font-size: 2rem;
    }

    .card-body {
        padding: 2rem 1.5rem;
    }
}

/* Responsive Mobile */
@media (max-width: 480px) {
    body {
        padding: 0.75rem;
    }

    .brand-header .icon-container {
        width: 70px;
        height: 70px;
    }

    .brand-header i {
        font-size: 2.25rem;
    }

    .brand-header h1 {
        font-size: 1.75rem;
    }

    .card-body {
        padding: 1.5rem 1.25rem;
    }

    .nav-pills .nav-link {
        padding: 0.7rem 0.75rem;
        font-size: 0.85rem;
    }
}

/* Better focus visibility for accessibility */
.form-control:focus-visible,
.btn:focus-visible {
    outline: 2px solid #667eea;
    outline-offset: 2px;
}
</style>
</head>
<body>
<div class="login-container">
    <div class="brand-header">
        <div class="icon-container">
            <i class="bi bi-bicycle"></i>
        </div>
        <h1>Bike Store</h1>
        <p>Sistema de Gestión</p>
    </div>

    <?php if ($mensaje): ?>
    <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
        <i class="bi bi-<?php echo $tipo_mensaje === 'success' ? 'check-circle-fill' : ($tipo_mensaje === 'danger' ? 'exclamation-triangle-fill' : 'info-circle-fill'); ?>"></i>
        <?php echo $mensaje; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <ul class="nav nav-pills nav-fill" id="pills-tab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="pills-login-tab" data-bs-toggle="pill" 
                            data-bs-target="#pills-login" type="button" role="tab">
                        <i class="bi bi-box-arrow-in-right"></i> Iniciar Sesión
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="pills-register-tab" data-bs-toggle="pill" 
                            data-bs-target="#pills-register" type="button" role="tab">
                        <i class="bi bi-person-plus"></i> Registrarse
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="pills-tabContent">
                <!-- LOGIN -->
                <div class="tab-pane fade show active" id="pills-login" role="tabpanel">
                    <form method="POST" action="">
                        <div class="mb-4">
                            <label class="form-label"><i class="bi bi-person-circle"></i> Usuario</label>
                            <input type="text" class="form-control form-control-lg" name="usuario" 
                                   placeholder="Ingresa tu usuario" required autocomplete="username"
                                   maxlength="30" pattern="[a-zA-Z0-9_-]{3,30}"
                                   title="Solo letras, números y guiones (3-30 caracteres)">
                        </div>

                        <div class="mb-4">
                            <label class="form-label"><i class="bi bi-shield-lock"></i> Contraseña</label>
                            <div class="password-wrapper">
                                <input type="password" class="form-control form-control-lg" name="password" 
                                       id="loginPassword" placeholder="Ingresa tu contraseña" required 
                                       autocomplete="current-password" maxlength="72">
                                <button type="button" class="password-toggle" onclick="togglePassword('loginPassword', this)">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" name="login" class="btn btn-primary btn-lg">
                                <i class="bi bi-box-arrow-in-right"></i> Iniciar Sesión
                            </button>
                        </div>

                        <?php if ($phpmailer_disponible): ?>
                        <div class="mt-4 text-center">
                            <a href="#" data-bs-toggle="modal" data-bs-target="#recuperarModal" class="recovery-link">
                                <i class="bi bi-key-fill"></i> ¿Olvidaste tu contraseña?
                            </a>
                        </div>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- REGISTRO -->
                <div class="tab-pane fade" id="pills-register" role="tabpanel">
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label class="form-label"><i class="bi bi-person-circle"></i> Usuario *</label>
                            <input type="text" class="form-control" name="reg_usuario" 
                                   placeholder="Elige un nombre de usuario" required autocomplete="username"
                                   maxlength="30" pattern="[a-zA-Z0-9_-]{3,30}"
                                   title="Solo letras, números y guiones (3-30 caracteres)">
                            <small class="text-muted d-block mt-1">
                                <i class="bi bi-info-circle"></i> 3-30 caracteres, solo letras, números y guiones
                            </small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label"><i class="bi bi-envelope-fill"></i> Email *</label>
                            <input type="email" class="form-control" name="reg_email" 
                                   placeholder="tu@email.com" required autocomplete="email" maxlength="100">
                            <?php if ($phpmailer_disponible): ?>
                            <small class="text-muted d-block mt-1">
                                <i class="bi bi-info-circle"></i> Enviaremos un correo de verificación
                            </small>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label class="form-label"><i class="bi bi-shield-lock"></i> Contraseña *</label>
                            <div class="password-wrapper">
                                <input type="password" class="form-control" name="reg_password" 
                                       id="regPassword" placeholder="Mínimo 6 caracteres" 
                                       minlength="6" maxlength="72" required autocomplete="new-password">
                                <button type="button" class="password-toggle" onclick="togglePassword('regPassword', this)">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <small class="text-muted d-block mt-1">
                                <i class="bi bi-info-circle"></i> Mínimo 6 caracteres
                            </small>
                        </div>

                        <div class="mb-4">
                            <label class="form-label"><i class="bi bi-shield-check"></i> Confirmar Contraseña *</label>
                            <div class="password-wrapper">
                                <input type="password" class="form-control" name="reg_confirm_password" 
                                       id="regConfirmPassword" placeholder="Repite tu contraseña" 
                                       minlength="6" maxlength="72" required autocomplete="new-password">
                                <button type="button" class="password-toggle" onclick="togglePassword('regConfirmPassword', this)">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" name="register" class="btn btn-success btn-lg">
                                <i class="bi bi-person-plus-fill"></i> Crear Cuenta
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($phpmailer_disponible): ?>
<div class="modal fade" id="recuperarModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="bi bi-key-fill"></i> Recuperar Contraseña</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <p class="mb-3">Ingresa tu correo electrónico y te enviaremos un enlace para restablecer tu contraseña.</p>
                    <div class="mb-3">
                        <label class="form-label"><i class="bi bi-envelope-fill"></i> Email</label>
                        <input type="email" class="form-control" name="recovery_email" 
                               placeholder="tu@email.com" required autocomplete="email" maxlength="100">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="submit" name="recuperar" class="btn btn-danger">
                        <i class="bi bi-send-fill"></i> Enviar Enlace
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function togglePassword(inputId, button) {
    const input = document.getElementById(inputId);
    const icon = button.querySelector('i');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('bi-eye');
        icon.classList.add('bi-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('bi-eye-slash');
        icon.classList.add('bi-eye');
    }
}
</script>
</body>
</html>