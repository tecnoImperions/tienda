<?php
require_once 'config.php';
session_start();

// Verificar si está logueado
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// SOLO ADMIN puede acceder a este módulo
if ($_SESSION['role'] != 'admin') {
    header('Location: index.php');
    exit();
}

$conn = getConnection();

// Variables
$mensaje = '';
$tipo_mensaje = '';
$editando = false;
$usuario_editar = null;

// CREAR o ACTUALIZAR Usuario
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $usuario = $_POST['usuario'];
    $email = $_POST['email'];
    $role = $_POST['role'];
    
    if (isset($_POST['user_id']) && !empty($_POST['user_id'])) {
        // ACTUALIZAR
        $user_id = $_POST['user_id'];
        
        // Si se proporciona nueva contraseña, actualizarla
        if (!empty($_POST['password'])) {
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $sql = "UPDATE Usuarios SET usuario=?, password=?, email=?, role=? WHERE user_id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssi", $usuario, $password, $email, $role, $user_id);
        } else {
            $sql = "UPDATE Usuarios SET usuario=?, email=?, role=? WHERE user_id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssi", $usuario, $email, $role, $user_id);
        }
        
        if ($stmt->execute()) {
            $mensaje = "Usuario actualizado exitosamente";
            $tipo_mensaje = "success";
        } else {
            $mensaje = "Error al actualizar: " . $conn->error;
            $tipo_mensaje = "danger";
        }
    } else {
        // CREAR
        if (empty($_POST['password'])) {
            $mensaje = "La contraseña es requerida para crear un nuevo usuario";
            $tipo_mensaje = "danger";
        } else {
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            
            // Si es empleado, marcar como verificado automáticamente
            $verificado = ($role == 'empleado') ? 1 : 0;
            
            $sql = "INSERT INTO Usuarios (usuario, password, email, role, verificado) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssi", $usuario, $password, $email, $role, $verificado);
            
            if ($stmt->execute()) {
                $mensaje = "Usuario creado exitosamente";
                if ($role == 'empleado') {
                    $mensaje .= " (Empleado verificado automáticamente)";
                }
                $tipo_mensaje = "success";
            } else {
                if ($conn->errno == 1062) {
                    $mensaje = "El nombre de usuario ya existe";
                    $tipo_mensaje = "danger";
                } else {
                    $mensaje = "Error al crear: " . $conn->error;
                    $tipo_mensaje = "danger";
                }
            }
        }
    }
}

// BORRAR Usuario
if (isset($_GET['eliminar'])) {
    $user_id = $_GET['eliminar'];
    
    // Prevenir eliminar al usuario admin principal
    if ($user_id == 1) {
        $mensaje = "No se puede eliminar el usuario administrador principal";
        $tipo_mensaje = "warning";
    } else {
        $sql = "DELETE FROM Usuarios WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        
        if ($stmt->execute()) {
            $mensaje = "Usuario eliminado exitosamente";
            $tipo_mensaje = "success";
        } else {
            $mensaje = "Error al eliminar: " . $conn->error;
            $tipo_mensaje = "danger";
        }
    }
}

// EDITAR - Cargar datos
if (isset($_GET['editar'])) {
    $editando = true;
    $user_id = $_GET['editar'];
    $sql = "SELECT * FROM Usuarios WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $usuario_editar = $stmt->get_result()->fetch_assoc();
}

// LISTAR Usuarios
$sql = "SELECT * FROM Usuarios ORDER BY 
        CASE 
            WHEN role = 'admin' THEN 1
            WHEN role = 'empleado' THEN 2
            WHEN role = 'usuario' THEN 3
        END, user_id DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - Bike Store</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-bicycle"></i> Bike Store
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Inicio</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="productos.php">Productos</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="categorias.php">Categorías</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="usuarios.php">Usuarios</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i> <?php echo $_SESSION['usuario']; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><span class="dropdown-item-text">
                                <small class="text-muted">
                                    <i class="bi bi-shield-check"></i> Administrador
                                </small>
                            </span></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php">
                                <i class="bi bi-box-arrow-right"></i> Cerrar Sesión
                            </a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container my-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>
                <i class="bi bi-people"></i> Gestión de Usuarios
            </h1>
            <div>
                <a href="actualizar_empleado.php" class="btn btn-info me-2">
                    <i class="bi bi-key"></i> Actualizar Contraseña Empleado
                </a>
                <span class="badge bg-warning text-dark">
                    <i class="bi bi-shield-lock"></i> Solo Administradores
                </span>
            </div>
        </div>

        <?php if ($mensaje): ?>
        <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
            <?php echo $mensaje; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="row">
            <!-- Formulario -->
            <div class="col-md-4">
                <div class="card shadow">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-<?php echo $editando ? 'pencil' : 'person-plus'; ?>"></i>
                            <?php echo $editando ? 'Editar Usuario' : 'Nuevo Usuario'; ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <?php if ($editando): ?>
                            <input type="hidden" name="user_id" value="<?php echo $usuario_editar['user_id']; ?>">
                            <?php endif; ?>

                            <div class="mb-3">
                                <label class="form-label">Usuario *</label>
                                <input type="text" class="form-control" name="usuario" 
                                       value="<?php echo $editando ? $usuario_editar['usuario'] : ''; ?>"
                                       required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">
                                    Contraseña <?php echo $editando ? '(dejar en blanco para mantener)' : '*'; ?>
                                </label>
                                <input type="password" class="form-control" name="password" 
                                       <?php echo $editando ? '' : 'required'; ?>>
                                <?php if ($editando): ?>
                                <div class="form-text">Solo completa si deseas cambiar la contraseña</div>
                                <?php endif; ?>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Email *</label>
                                <input type="email" class="form-control" name="email" 
                                       value="<?php echo $editando ? $usuario_editar['email'] : ''; ?>"
                                       required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Rol *</label>
                                <select class="form-select" name="role" required id="roleSelect">
                                    <option value="usuario" 
                                            <?php echo ($editando && $usuario_editar['role'] == 'usuario') ? 'selected' : ''; ?>>
                                        👤 Usuario Normal
                                    </option>
                                    <option value="empleado" 
                                            <?php echo ($editando && $usuario_editar['role'] == 'empleado') ? 'selected' : ''; ?>>
                                        💼 Empleado
                                    </option>
                                    <option value="admin" 
                                            <?php echo ($editando && $usuario_editar['role'] == 'admin') ? 'selected' : ''; ?>>
                                        🛡️ Administrador
                                    </option>
                                </select>
                                <div class="form-text" id="roleDescription">
                                    <strong>Usuario:</strong> Puede comprar productos<br>
                                    <strong>Empleado:</strong> Gestiona clientes y cambia estados (Entregado/Anulado)<br>
                                    <strong>Admin:</strong> Acceso completo al sistema
                                </div>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-info text-white">
                                    <i class="bi bi-save"></i> <?php echo $editando ? 'Actualizar' : 'Guardar'; ?>
                                </button>
                                <?php if ($editando): ?>
                                <a href="usuarios.php" class="btn btn-secondary">
                                    <i class="bi bi-x-circle"></i> Cancelar
                                </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Lista de Usuarios -->
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-list-ul"></i> Lista de Usuarios
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Usuario</th>
                                        <th>Email</th>
                                        <th>Rol</th>
                                        <th>Estado</th>
                                        <th>Fecha de Registro</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($result->num_rows > 0): ?>
                                        <?php while($row = $result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo $row['user_id']; ?></td>
                                            <td>
                                                <?php 
                                                if ($row['role'] == 'admin') echo '<i class="bi bi-shield-fill text-danger"></i>';
                                                elseif ($row['role'] == 'empleado') echo '<i class="bi bi-briefcase-fill text-info"></i>';
                                                else echo '<i class="bi bi-person-circle"></i>';
                                                ?>
                                                <strong><?php echo $row['usuario']; ?></strong>
                                            </td>
                                            <td><?php echo $row['email']; ?></td>
                                            <td>
                                                <?php if ($row['role'] == 'admin'): ?>
                                                <span class="badge bg-danger">
                                                    <i class="bi bi-shield-check"></i> Admin
                                                </span>
                                                <?php elseif ($row['role'] == 'empleado'): ?>
                                                <span class="badge bg-info">
                                                    <i class="bi bi-briefcase"></i> Empleado
                                                </span>
                                                <?php else: ?>
                                                <span class="badge bg-secondary">
                                                    <i class="bi bi-person"></i> Usuario
                                                </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php
                                                // Verificar si existe la columna verificado
                                                $verificado = isset($row['verificado']) ? $row['verificado'] : 1;
                                                if ($verificado == 1): ?>
                                                <span class="badge bg-success">
                                                    <i class="bi bi-check-circle"></i> Verificado
                                                </span>
                                                <?php else: ?>
                                                <span class="badge bg-warning text-dark">
                                                    <i class="bi bi-clock"></i> Pendiente
                                                </span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($row['created_at'])); ?></td>
                                            <td>
                                                <a href="?editar=<?php echo $row['user_id']; ?>" 
                                                   class="btn btn-sm btn-warning" title="Editar">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <?php if ($row['user_id'] != 1 && $row['user_id'] != $_SESSION['user_id']): ?>
                                                <a href="?eliminar=<?php echo $row['user_id']; ?>" 
                                                   class="btn btn-sm btn-danger"
                                                   onclick="return confirm('¿Está seguro de eliminar este usuario?')"
                                                   title="Eliminar">
                                                    <i class="bi bi-trash"></i>
                                                </a>
                                                <?php else: ?>
                                                <button class="btn btn-sm btn-secondary" disabled title="Usuario protegido">
                                                    <i class="bi bi-lock"></i>
                                                </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center">No hay usuarios registrados</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Estadísticas -->
                <div class="row mt-4">
                    <div class="col-md-4">
                        <div class="card text-center">
                            <div class="card-body">
                                <h3 class="text-danger">
                                    <?php echo $conn->query("SELECT COUNT(*) as total FROM Usuarios WHERE role='admin'")->fetch_assoc()['total']; ?>
                                </h3>
                                <p class="text-muted mb-0">Administradores</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-center">
                            <div class="card-body">
                                <h3 class="text-info">
                                    <?php echo $conn->query("SELECT COUNT(*) as total FROM Usuarios WHERE role='empleado'")->fetch_assoc()['total']; ?>
                                </h3>
                                <p class="text-muted mb-0">Empleados</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-center">
                            <div class="card-body">
                                <h3 class="text-secondary">
                                    <?php echo $conn->query("SELECT COUNT(*) as total FROM Usuarios WHERE role='usuario'")->fetch_assoc()['total']; ?>
                                </h3>
                                <p class="text-muted mb-0">Usuarios</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Actualizar descripción según rol seleccionado
        document.getElementById('roleSelect').addEventListener('change', function() {
            const descriptions = {
                'usuario': '<strong>Usuario:</strong> Puede comprar productos en el catálogo',
                'empleado': '<strong>Empleado:</strong> Gestiona clientes y cambia estados de órdenes (solo Entregado/Anulado)',
                'admin': '<strong>Admin:</strong> Acceso completo: productos, categorías, usuarios, clientes'
            };
            document.getElementById('roleDescription').innerHTML = descriptions[this.value];
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>