<?php
/**
 * SCRIPT DE ACTUALIZACIÓN DE CONTRASEÑAS
 * Este archivo actualiza las contraseñas de todos los usuarios automáticamente al ejecutarse
 * 
 * ✅ MODIFICADO: Se puede ejecutar múltiples veces
 * ✅ Útil para desarrollo, testing o resetear contraseñas
 */

// Bandera de seguridad - cambiar a true para permitir la ejecución
define('PERMITIR_EJECUCION', true);

if (!PERMITIR_EJECUCION) {
    die('Ejecución no permitida. Cambia PERMITIR_EJECUCION a true en el código.');
}

require_once 'config.php';

// Configuración de contraseñas
$usuarios_passwords = [
    'admin' => [
        'password' => 'admin123',
        'role' => 'admin',
        'email' => 'admin@bikestore.com'
    ],
    'empleado1' => [
        'password' => 'empleado123', 
        'role' => 'empleado',
        'email' => 'empleado1@bikestore.com'
    ],
    'jperez' => [
        'password' => 'password123',
        'role' => 'usuario', 
        'email' => 'juan.perez@email.com'
    ],
    'mgomez' => [
        'password' => 'password123',
        'role' => 'usuario',
        'email' => 'maria.gomez@email.com'
    ],
    'crodriguez' => [
        'password' => 'password123',
        'role' => 'usuario',
        'email' => 'carlos.rodriguez@email.com'
    ],
    'alopez' => [
        'password' => 'password123',
        'role' => 'admin',
        'email' => 'ana.lopez@email.com'
    ],
    'rmartinez' => [
        'password' => 'password123',
        'role' => 'usuario',
        'email' => 'roberto.martinez@email.com'
    ]
];

$conn = getConnection();
$resultados = [];
$errores = [];
$total_actualizados = 0;
$total_creados = 0;
$total_sin_cambios = 0;

// Iniciar transacción
$conn->begin_transaction();

try {
    foreach ($usuarios_passwords as $usuario => $datos) {
        $password = $datos['password'];
        $role = $datos['role'];
        $email = $datos['email'];
        
        // Generar hash seguro
        $password_hash = password_hash($password, PASSWORD_BCRYPT);
        
        // Verificar si el usuario existe
        $check = $conn->prepare("SELECT user_id, usuario, password, role FROM usuarios WHERE usuario = ?");
        $check->bind_param("s", $usuario);
        $check->execute();
        $result = $check->get_result();
        
        if ($result->num_rows > 0) {
            // Usuario existe, verificar si necesita actualización
            $user_data = $result->fetch_assoc();
            $current_hash = $user_data['password'];
            $current_role = $user_data['role'];
            
            // Verificar si la contraseña ya está actualizada
            if (password_verify($password, $current_hash) && $current_role == $role) {
                // No necesita cambios
                $resultados[] = [
                    'usuario' => $usuario,
                    'password' => $password,
                    'accion' => 'sin_cambios',
                    'role' => $role
                ];
                $total_sin_cambios++;
            } else {
                // Actualizar contraseña y/o rol
                $sql_update = "UPDATE usuarios SET password = ?, role = ?, verificado = 1 WHERE usuario = ?";
                $stmt_update = $conn->prepare($sql_update);
                $stmt_update->bind_param("sss", $password_hash, $role, $usuario);
                
                if ($stmt_update->execute()) {
                    $resultados[] = [
                        'usuario' => $usuario,
                        'password' => $password,
                        'accion' => 'actualizado',
                        'role' => $role,
                        'cambio' => ($current_role != $role) ? 'contraseña y rol' : 'contraseña'
                    ];
                    $total_actualizados++;
                } else {
                    throw new Exception("Error al actualizar usuario: $usuario");
                }
            }
        } else {
            // Usuario no existe, crearlo
            $sql_insert = "INSERT INTO usuarios (usuario, password, email, role, verificado) VALUES (?, ?, ?, ?, 1)";
            $stmt_insert = $conn->prepare($sql_insert);
            $stmt_insert->bind_param("ssss", $usuario, $password_hash, $email, $role);
            
            if ($stmt_insert->execute()) {
                $resultados[] = [
                    'usuario' => $usuario,
                    'password' => $password,
                    'accion' => 'creado',
                    'role' => $role
                ];
                $total_creados++;
            } else {
                throw new Exception("Error al crear usuario: $usuario");
            }
        }
    }
    
    // Todo salió bien, confirmar transacción
    $conn->commit();
    
    // Registrar la ejecución (solo para registro, no bloquea)
    file_put_contents('passwords_actualizados.log', 
        "Ejecutado: " . date('Y-m-d H:i:s') . 
        " - Actualizados: $total_actualizados, Creados: $total_creados, Sin cambios: $total_sin_cambios\n", 
        FILE_APPEND
    );
    
} catch (Exception $e) {
    // Error: revertir todo
    $conn->rollback();
    $errores[] = $e->getMessage();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Actualización de Contraseñas - Bike Store</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 30px 0;
        }
        .result-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            padding: 30px;
            margin-bottom: 20px;
        }
        .password-box {
            background: #f8f9fa;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin: 10px 0;
        }
        .password-text {
            font-family: 'Courier New', monospace;
            font-size: 1.1em;
            font-weight: bold;
            color: #0d6efd;
        }
        .badge-sin-cambios {
            background-color: #6c757d;
        }
        .badge-actualizado {
            background-color: #198754;
        }
        .badge-creado {
            background-color: #0dcaf0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="text-center text-white mb-4">
                    <i class="bi bi-arrow-repeat" style="font-size: 5rem;"></i>
                    <h1 class="display-4">Actualización de Contraseñas</h1>
                    <p class="lead">Script reutilizable - Puede ejecutarse múltiples veces</p>
                </div>

                <?php if (empty($errores)): ?>
                <!-- ÉXITO -->
                <div class="result-card">
                    <div class="alert alert-success">
                        <h4 class="alert-heading">
                            <i class="bi bi-check-circle-fill"></i> ¡Actualización Exitosa!
                        </h4>
                        <hr>
                        <p class="mb-0">
                            <strong><?php echo $total_actualizados; ?></strong> usuario(s) actualizados<br>
                            <strong><?php echo $total_creados; ?></strong> usuario(s) creados<br>
                            <strong><?php echo $total_sin_cambios; ?></strong> usuario(s) sin cambios necesarios
                        </p>
                    </div>

                    <h5 class="mb-3"><i class="bi bi-key"></i> Estado de Credenciales:</h5>

                    <div class="row">
                        <?php foreach ($resultados as $resultado): ?>
                        <div class="col-md-6 mb-3">
                            <div class="password-box">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1">
                                            <?php 
                                            if ($resultado['role'] == 'admin') echo '<i class="bi bi-shield-fill text-danger"></i>';
                                            elseif ($resultado['role'] == 'empleado') echo '<i class="bi bi-briefcase-fill text-info"></i>';
                                            else echo '<i class="bi bi-person-circle"></i>';
                                            ?>
                                            <?php echo $resultado['usuario']; ?>
                                        </h6>
                                        <small class="text-muted">
                                            Password: <span class="password-text"><?php echo $resultado['password']; ?></span>
                                        </small>
                                        <?php if (isset($resultado['cambio'])): ?>
                                        <br><small class="text-warning"><i class="bi bi-arrow-right"></i> <?php echo $resultado['cambio']; ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <span class="badge badge-<?php echo $resultado['accion']; ?>">
                                        <?php 
                                        if ($resultado['accion'] == 'creado') echo '➕ Creado';
                                        elseif ($resultado['accion'] == 'actualizado') echo '✏️ Actualizado';
                                        else echo '✅ Listo';
                                        ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="alert alert-info mt-4">
                        <h6><i class="bi bi-info-circle"></i> Características:</h6>
                        <ul class="mb-0">
                            <li><strong>Reutilizable:</strong> Puedes ejecutar este script cuantas veces necesites</li>
                            <li><strong>Inteligente:</strong> Solo actualiza usuarios que necesitan cambios</li>
                            <li><strong>Seguro:</strong> Usa transacciones para evitar datos inconsistentes</li>
                            <li><strong>Registro:</strong> Mantiene un log de ejecuciones</li>
                        </ul>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-between">
                        <div>
                            <a href="login.php" class="btn btn-primary btn-lg">
                                <i class="bi bi-box-arrow-in-right"></i> Ir a Iniciar Sesión
                            </a>
                            <a href="index.php" class="btn btn-secondary">
                                <i class="bi bi-house"></i> Ir al Inicio
                            </a>
                        </div>
                        <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-warning">
                            <i class="bi bi-arrow-repeat"></i> Ejecutar Nuevamente
                        </a>
                    </div>
                </div>

                <!-- Tabla de Resumen -->
                <div class="result-card">
                    <h5 class="mb-3"><i class="bi bi-table"></i> Resumen Detallado</h5>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead class="table-dark">
                                <tr>
                                    <th>Usuario</th>
                                    <th>Contraseña</th>
                                    <th>Rol</th>
                                    <th>Estado</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($resultados as $res): ?>
                                <tr>
                                    <td><strong><?php echo $res['usuario']; ?></strong></td>
                                    <td><code class="password-text"><?php echo $res['password']; ?></code></td>
                                    <td>
                                        <?php 
                                        if ($res['role'] == 'admin') echo '<span class="badge bg-danger">Admin</span>';
                                        elseif ($res['role'] == 'empleado') echo '<span class="badge bg-info">Empleado</span>';
                                        else echo '<span class="badge bg-secondary">Usuario</span>';
                                        ?>
                                    </td>
                                    <td>
                                        <?php if (isset($res['cambio'])): ?>
                                        <small class="text-warning"><?php echo $res['cambio']; ?></small>
                                        <?php else: ?>
                                        <small class="text-muted">-</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge 
                                            <?php 
                                            if ($res['accion'] == 'creado') echo 'bg-info';
                                            elseif ($res['accion'] == 'actualizado') echo 'bg-success';
                                            else echo 'bg-secondary';
                                            ?>
                                        ">
                                            <?php 
                                            if ($res['accion'] == 'creado') echo '➕ Creado';
                                            elseif ($res['accion'] == 'actualizado') echo '✏️ Actualizado';
                                            else echo '✅ Listo';
                                            ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <?php else: ?>
                <!-- ERROR -->
                <div class="result-card">
                    <div class="alert alert-danger">
                        <h4 class="alert-heading">
                            <i class="bi bi-x-circle-fill"></i> Error en la Actualización
                        </h4>
                        <hr>
                        <?php foreach ($errores as $error): ?>
                        <p class="mb-0"><strong>Error:</strong> <?php echo $error; ?></p>
                        <?php endforeach; ?>
                    </div>

                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i> 
                        <strong>Nota:</strong> Por seguridad, todos los cambios fueron revertidos. 
                        Ninguna contraseña fue modificada.
                    </div>

                    <div class="d-grid gap-2">
                        <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-warning">
                            <i class="bi bi-arrow-repeat"></i> Reintentar
                        </a>
                        <a href="index.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Volver al Inicio
                        </a>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Información del Sistema -->
                <div class="result-card">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="alert alert-light">
                                <h6><i class="bi bi-clock-history"></i> Historial de Ejecuciones</h6>
                                <?php if (file_exists('passwords_actualizados.log')): ?>
                                    <pre class="small"><?php echo file_get_contents('passwords_actualizados.log'); ?></pre>
                                <?php else: ?>
                                    <p class="text-muted">No hay ejecuciones previas registradas.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="alert alert-light">
                                <h6><i class="bi bi-gear"></i> Opciones</h6>
                                <ul class="mb-0">
                                    <li><strong>Ejecutar nuevamente:</strong> Recarga esta página</li>
                                    <li><strong>Limpiar log:</strong> Elimina el archivo "passwords_actualizados.log"</li>
                                    <li><strong>Modificar usuarios:</strong> Edita el array <code>$usuarios_passwords</code> en el código</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>