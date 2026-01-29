<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../includes/config.php';
session_start();

// ============================================
// SEGURIDAD - Verificar autenticación
// ============================================
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$es_admin = ($_SESSION['role'] === 'admin');
$conn = getConnection();

// ============================================
// FUNCIÓN DE VALIDACIÓN Y SANITIZACIÓN
// ============================================
function validarDescripcion($descripcion) {
    $errores = [];
    
    // Limpiar espacios
    $descripcion = trim($descripcion);
    
    // Validar longitud mínima
    if (empty($descripcion)) {
        $errores[] = "La descripción no puede estar vacía";
        return ['valido' => false, 'errores' => $errores, 'valor' => ''];
    }
    
    // Validar longitud máxima (50 caracteres es razonable para categorías)
    if (mb_strlen($descripcion, 'UTF-8') > 50) {
        $errores[] = "La descripción no puede exceder 50 caracteres";
    }
    
    // Validar longitud mínima (al menos 2 caracteres)
    if (mb_strlen($descripcion, 'UTF-8') < 2) {
        $errores[] = "La descripción debe tener al menos 2 caracteres";
    }
    
    // Validar caracteres permitidos (letras, números, espacios, acentos y algunos símbolos básicos)
    if (!preg_match('/^[a-záéíóúñA-ZÁÉÍÓÚÑ0-9\s\-_.áéíóúÁÉÍÓÚñÑüÜ]+$/u', $descripcion)) {
        $errores[] = "La descripción contiene caracteres no permitidos. Solo se permiten letras, números, espacios y guiones";
    }
    
    // Validar que no contenga solo espacios
    if (preg_match('/^\s+$/', $descripcion)) {
        $errores[] = "La descripción no puede contener solo espacios";
    }
    
    // Validar que no contenga múltiples espacios consecutivos
    if (preg_match('/\s{2,}/', $descripcion)) {
        $errores[] = "La descripción no puede contener múltiples espacios consecutivos";
    }
    
    if (count($errores) > 0) {
        return ['valido' => false, 'errores' => $errores, 'valor' => ''];
    }
    
    // Limpiar espacios extras y normalizar
    $descripcion = preg_replace('/\s+/', ' ', $descripcion);
    $descripcion = trim($descripcion);
    
    return ['valido' => true, 'errores' => [], 'valor' => $descripcion];
}

// ============================================
// MANEJO POST - CREAR/EDITAR (PRG)
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $es_admin && !isset($_POST['eliminar'])) {
    
    $descripcion_raw = isset($_POST['descripcion']) ? $_POST['descripcion'] : '';
    $validacion = validarDescripcion($descripcion_raw);
    
    if (!$validacion['valido']) {
        $_SESSION['swal'] = [
            'title' => 'Error de validación',
            'text'  => implode('. ', $validacion['errores']),
            'icon'  => 'error'
        ];
        header("Location: categorias.php" . (isset($_POST['category_id']) ? '?editar=' . (int)$_POST['category_id'] : ''));
        exit();
    }
    
    $descripcion_limpia = $validacion['valor'];
    
    // Verificar duplicados
    if (!empty($_POST['category_id'])) {
        $id = (int)$_POST['category_id'];
        $stmt = $conn->prepare("SELECT category_id FROM categorias WHERE LOWER(descripcion) = LOWER(?) AND category_id != ?");
        $stmt->bind_param("si", $descripcion_limpia, $id);
    } else {
        $stmt = $conn->prepare("SELECT category_id FROM categorias WHERE LOWER(descripcion) = LOWER(?)");
        $stmt->bind_param("s", $descripcion_limpia);
    }
    
    $stmt->execute();
    $duplicado = $stmt->get_result()->fetch_assoc();
    
    if ($duplicado) {
        $_SESSION['swal'] = [
            'title' => 'Categoría duplicada',
            'text'  => 'Ya existe una categoría con ese nombre',
            'icon'  => 'warning'
        ];
        header("Location: categorias.php" . (isset($_POST['category_id']) ? '?editar=' . (int)$_POST['category_id'] : ''));
        exit();
    }
    
    // UPDATE o INSERT
    if (!empty($_POST['category_id'])) {
        $id = (int)$_POST['category_id'];
        
        // Verificar que la categoría existe
        $stmt = $conn->prepare("SELECT category_id FROM categorias WHERE category_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        if (!$stmt->get_result()->fetch_assoc()) {
            $_SESSION['swal'] = [
                'title' => 'Error',
                'text'  => 'La categoría no existe',
                'icon'  => 'error'
            ];
            header("Location: categorias.php");
            exit();
        }
        
        // UPDATE
        $stmt = $conn->prepare("UPDATE categorias SET descripcion = ? WHERE category_id = ?");
        $stmt->bind_param("si", $descripcion_limpia, $id);
        
        if ($stmt->execute()) {
            $_SESSION['swal'] = [
                'title' => 'Actualizado',
                'text'  => 'Categoría actualizada correctamente',
                'icon'  => 'success'
            ];
        } else {
            $_SESSION['swal'] = [
                'title' => 'Error',
                'text'  => 'Error al actualizar la categoría',
                'icon'  => 'error'
            ];
        }
    } else {
        // INSERT
        $stmt = $conn->prepare("INSERT INTO categorias (descripcion) VALUES (?)");
        $stmt->bind_param("s", $descripcion_limpia);
        
        if ($stmt->execute()) {
            $_SESSION['swal'] = [
                'title' => 'Creado',
                'text'  => 'Categoría creada correctamente',
                'icon'  => 'success'
            ];
        } else {
            $_SESSION['swal'] = [
                'title' => 'Error',
                'text'  => 'Error al crear la categoría',
                'icon'  => 'error'
            ];
        }
    }
    
    header("Location: categorias.php");
    exit();
}

// ============================================
// ELIMINAR (ADMIN) - CORREGIDO
// ============================================
if (isset($_POST['eliminar']) && $es_admin) {
    $id = (int)$_POST['eliminar'];
    
    // Verificar que el ID es válido
    if ($id <= 0) {
        $_SESSION['swal'] = [
            'title' => 'Error',
            'text'  => 'ID de categoría inválido',
            'icon'  => 'error'
        ];
        header("Location: categorias.php");
        exit();
    }
    
    // Verificar que la categoría existe
    $stmt = $conn->prepare("SELECT category_id FROM categorias WHERE category_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) {
        $_SESSION['swal'] = [
            'title' => 'Error',
            'text'  => 'La categoría no existe',
            'icon'  => 'error'
        ];
        header("Location: categorias.php");
        exit();
    }
    
    // Verificar productos asociados
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM productos WHERE category_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $check = $stmt->get_result()->fetch_assoc();
    
    if ($check['total'] > 0) {
        $_SESSION['swal'] = [
            'title' => 'No permitido',
            'text'  => 'La categoría tiene ' . $check['total'] . ' producto(s) asociado(s). Debes reasignar o eliminar los productos primero.',
            'icon'  => 'warning'
        ];
    } else {
        // Eliminar la categoría
        $stmt = $conn->prepare("DELETE FROM categorias WHERE category_id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $_SESSION['swal'] = [
                'title' => 'Eliminado',
                'text'  => 'Categoría eliminada correctamente',
                'icon'  => 'success'
            ];
        } else {
            $_SESSION['swal'] = [
                'title' => 'Error',
                'text'  => 'Error al eliminar la categoría',
                'icon'  => 'error'
            ];
        }
    }
    
    header("Location: categorias.php");
    exit();
}

// ============================================
// EDITAR - Cargar datos
// ============================================
$editando = false;
$categoria = null;

if (isset($_GET['editar']) && $es_admin) {
    $id = (int)$_GET['editar'];
    
    if ($id > 0) {
        $stmt = $conn->prepare("SELECT * FROM categorias WHERE category_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $categoria = $stmt->get_result()->fetch_assoc();
        
        if ($categoria) {
            $editando = true;
        } else {
            $_SESSION['swal'] = [
                'title' => 'Error',
                'text'  => 'La categoría no existe',
                'icon'  => 'error'
            ];
            header("Location: categorias.php");
            exit();
        }
    }
}

// ============================================
// LISTADO
// ============================================
$sql = "SELECT c.*, COUNT(p.product_id) as total
        FROM categorias c
        LEFT JOIN productos p ON c.category_id = p.category_id
        GROUP BY c.category_id
        ORDER BY c.category_id DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Categorías | Bike Store</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<link href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css" rel="stylesheet">

<style>
@media (max-width: 768px){
    .btn-sm{margin-bottom:4px}
}
.form-text {
    font-size: 0.875rem;
    color: #6c757d;
}
.is-invalid {
    border-color: #dc3545 !important;
}
</style>
</head>

<body>
<?php include '../includes/navbar_admin.php'; ?>

<div class="container my-4">
<div class="row">
<?php if($es_admin): ?>
<div class="col-lg-4 mb-3">
<div class="card shadow">
<div class="card-header bg-success text-white">
    <i class="bi bi-<?= $editando ? 'pencil-square' : 'plus-circle' ?>"></i>
    <?= $editando ? 'Editar' : 'Nueva' ?> categoría
</div>
<div class="card-body">
<form method="POST" id="formCategoria">
<?php if($editando): ?>
    <input type="hidden" name="category_id" value="<?= htmlspecialchars($categoria['category_id']) ?>">
<?php endif; ?>

    <div class="mb-3">
        <label for="descripcion" class="form-label">Nombre de categoría *</label>
        <input 
            type="text" 
            class="form-control" 
            id="descripcion"
            name="descripcion" 
            required
            maxlength="50"
            value="<?= $editando ? htmlspecialchars($categoria['descripcion']) : '' ?>"
            placeholder="Ej: Bicicletas de Ruta">
        <div class="form-text">
            Máximo 50 caracteres. Solo letras, números, espacios y guiones.
        </div>
        <div class="invalid-feedback" id="errorDescripcion"></div>
    </div>

    <button type="submit" class="btn btn-success w-100">
        <i class="bi bi-<?= $editando ? 'check-circle' : 'save' ?>"></i>
        <?= $editando ? 'Actualizar' : 'Guardar' ?>
    </button>
    
    <?php if($editando): ?>
    <a href="categorias.php" class="btn btn-secondary w-100 mt-2">
        <i class="bi bi-x-circle"></i> Cancelar
    </a>
    <?php endif; ?>
</form>
</div>
</div>
</div>
<?php endif; ?>

<div class="<?= $es_admin ? 'col-lg-8' : 'col-12' ?>">
<div class="card shadow">
<div class="card-header bg-dark text-white">
    <i class="bi bi-list-ul"></i> Listado de Categorías
</div>
<div class="card-body">
<table id="tabla" class="table table-striped table-hover nowrap" style="width:100%">
<thead>
<tr>
    <th>ID</th>
    <th>Descripción</th>
    <th>Productos</th>
    <th>Fecha</th>
    <?php if($es_admin): ?><th>Acciones</th><?php endif; ?>
</tr>
</thead>
<tbody>
<?php while($r = $result->fetch_assoc()): ?>
<tr>
    <td><?= htmlspecialchars($r['category_id']) ?></td>
    <td><?= htmlspecialchars($r['descripcion']) ?></td>
    <td><span class="badge bg-primary"><?= htmlspecialchars($r['total']) ?></span></td>
    <td><?= date('d/m/Y', strtotime($r['created_at'])) ?></td>
    <?php if($es_admin): ?>
    <td>
        <a href="?editar=<?= htmlspecialchars($r['category_id']) ?>" 
           class="btn btn-warning btn-sm" 
           title="Editar">
            <i class="bi bi-pencil"></i>
        </a>
        <form method="POST" class="d-inline eliminar-form">
            <input type="hidden" name="eliminar" value="<?= htmlspecialchars($r['category_id']) ?>">
            <button type="button" 
                    class="btn btn-danger btn-sm btn-eliminar"
                    title="Eliminar"
                    data-productos="<?= htmlspecialchars($r['total']) ?>">
                <i class="bi bi-trash"></i>
            </button>
        </form>
    </td>
    <?php endif; ?>
</tr>
<?php endwhile; ?>
</tbody>
</table>
</div>
</div>
</div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.0/dist/jquery.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>

<script>
$(function(){
    // Inicializar DataTable
    $('#tabla').DataTable({
        responsive: true,
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'
        },
        order: [[0, 'desc']]
    });

    // Validación del formulario
    $('#formCategoria').on('submit', function(e) {
        const descripcion = $('#descripcion').val().trim();
        const input = $('#descripcion');
        const errorDiv = $('#errorDescripcion');
        
        // Limpiar errores previos
        input.removeClass('is-invalid');
        errorDiv.text('');
        
        // Validaciones
        if (descripcion.length === 0) {
            e.preventDefault();
            input.addClass('is-invalid');
            errorDiv.text('La descripción no puede estar vacía');
            return false;
        }
        
        if (descripcion.length < 2) {
            e.preventDefault();
            input.addClass('is-invalid');
            errorDiv.text('La descripción debe tener al menos 2 caracteres');
            return false;
        }
        
        if (descripcion.length > 50) {
            e.preventDefault();
            input.addClass('is-invalid');
            errorDiv.text('La descripción no puede exceder 50 caracteres');
            return false;
        }
        
        // Validar caracteres permitidos
        const regex = /^[a-záéíóúñA-ZÁÉÍÓÚÑ0-9\s\-_.áéíóúÁÉÍÓÚñÑüÜ]+$/u;
        if (!regex.test(descripcion)) {
            e.preventDefault();
            input.addClass('is-invalid');
            errorDiv.text('Solo se permiten letras, números, espacios y guiones');
            return false;
        }
        
        // Validar espacios múltiples
        if (/\s{2,}/.test(descripcion)) {
            e.preventDefault();
            input.addClass('is-invalid');
            errorDiv.text('No se permiten múltiples espacios consecutivos');
            return false;
        }
        
        return true;
    });

    // Contador de caracteres
    $('#descripcion').on('input', function() {
        const maxLength = 50;
        const currentLength = $(this).val().length;
        const remaining = maxLength - currentLength;
        
        let color = 'text-muted';
        if (remaining < 10) color = 'text-warning';
        if (remaining < 5) color = 'text-danger';
        
        $(this).next('.form-text').html(
            `Caracteres restantes: <span class="${color}">${remaining}</span> / ${maxLength}`
        );
    });

    // Confirmación de eliminación
    $('.btn-eliminar').click(function(){
        const form = $(this).closest('form');
        const productos = $(this).data('productos');
        
        if (productos > 0) {
            Swal.fire({
                title: 'No se puede eliminar',
                html: `Esta categoría tiene <strong>${productos}</strong> producto(s) asociado(s).<br><br>
                       Debes reasignar o eliminar los productos primero.`,
                icon: 'warning',
                confirmButtonText: 'Entendido',
                confirmButtonColor: '#3085d6'
            });
        } else {
            Swal.fire({
                title: '¿Eliminar categoría?',
                text: 'Esta acción no se puede deshacer',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        }
    });
});
</script>

<?php if(isset($_SESSION['swal'])): ?>
<script>
Swal.fire(<?= json_encode($_SESSION['swal']) ?>);
</script>
<?php unset($_SESSION['swal']); endif; ?>

</body>
</html>
<?php $conn->close(); ?>