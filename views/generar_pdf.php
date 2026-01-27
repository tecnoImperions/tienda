<?php
require_once '../includes/config.php';
require_once '../fpdf/fpdf.php';
session_start();

if (!isset($_SESSION['user_id']) || !isset($_GET['order_id'])) {
    die('acceso no autorizado');
}

$conn = getConnection();
$order_id = (int)$_GET['order_id'];

/* ===============================
   verificar orden del usuario
================================ */
$stmt = $conn->prepare("
    select o.*, s.store_name, s.street, s.city, s.state, s.phone
    from orders o
    left join stores s on o.store_id = s.store_id
    where o.order_id = ? and o.usuario_id = ?
");
$stmt->bind_param("ii", $order_id, $_SESSION['user_id']);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    die('orden no encontrada');
}

/* ===============================
   items de la orden
================================ */
$stmt = $conn->prepare("
    select oi.*, p.product_name
    from order_items oi
    join productos p on oi.product_id = p.product_id
    where oi.order_id = ?
");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$items = $stmt->get_result();

/* ===============================
   datos del usuario (reales)
================================ */
$stmt = $conn->prepare("
    select usuario, email
    from usuarios
    where user_id = ?
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$cliente = $stmt->get_result()->fetch_assoc();

/* ===============================
   generar pdf mejorado
================================ */
$pdf = new FPDF('P', 'mm', [80, 250]);
$pdf->AddPage();
$pdf->SetMargins(5, 5, 5);
$pdf->SetAutoPageBreak(true, 10);

/* ===== ENCABEZADO CON ESTILO ===== */
$pdf->SetFillColor(40, 40, 40);
$pdf->Rect(5, 5, 70, 28, 'F');

$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial', 'B', 16);
$pdf->SetY(8);
$pdf->Cell(0, 6, "BIKE STORE", 0, 1, 'C');

$pdf->SetFont('Arial', '', 9);
$pdf->Cell(0, 4, utf8_decode($order['store_name']), 0, 1, 'C');
$pdf->SetFont('Arial', '', 8);
$pdf->Cell(0, 3.5, utf8_decode($order['street']), 0, 1, 'C');
$pdf->Cell(0, 3.5, utf8_decode($order['city'] . ", " . $order['state']), 0, 1, 'C');
$pdf->Cell(0, 3.5, "Tel: " . $order['phone'], 0, 1, 'C');
$pdf->Cell(0, 3.5, "NIT: 123456789", 0, 1, 'C');

$pdf->SetTextColor(0, 0, 0);
$pdf->Ln(4);

/* ===== INFORMACIÓN DE FACTURA ===== */
$pdf->SetFillColor(230, 230, 230);
$pdf->Rect(5, $pdf->GetY(), 70, 18, 'F');

$pdf->Ln(2);
$pdf->SetFont('Arial', 'B', 13);
$pdf->Cell(0, 5, "FACTURA", 0, 1, 'C');

$pdf->SetFont('Arial', '', 9);
$y_pos = $pdf->GetY();
$pdf->SetXY(10, $y_pos);
$pdf->Cell(30, 4, "No. Factura:", 0, 0);
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(0, 4, str_pad($order_id, 6, "0", STR_PAD_LEFT), 0, 1);

$pdf->SetFont('Arial', '', 9);
$pdf->SetX(10);
$pdf->Cell(30, 4, "Fecha:", 0, 0);
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(0, 4, date('d/m/Y', strtotime($order['created_at'])), 0, 1);

$pdf->SetFont('Arial', '', 9);
$pdf->SetX(10);
$pdf->Cell(30, 4, "Hora:", 0, 0);
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(0, 4, date('H:i', strtotime($order['created_at'])), 0, 1);

$pdf->Ln(3);

/* ===== DATOS DEL CLIENTE ===== */
$pdf->SetDrawColor(200, 200, 200);
$pdf->SetLineWidth(0.3);
$pdf->Line(5, $pdf->GetY(), 75, $pdf->GetY());
$pdf->Ln(3);

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 5, "CLIENTE", 0, 1);

$pdf->SetFont('Arial', '', 9);
$pdf->Cell(18, 4, "Nombre:", 0, 0);
$pdf->SetFont('Arial', 'B', 9);
$pdf->MultiCell(0, 4, utf8_decode($cliente['usuario']), 0);

$pdf->SetFont('Arial', '', 9);
$pdf->Cell(18, 4, "Email:", 0, 0);
$pdf->SetFont('Arial', '', 8);
$pdf->MultiCell(0, 4, $cliente['email'], 0);

$pdf->Ln(2);

/* ===== LÍNEA DIVISORIA ===== */
$pdf->SetDrawColor(200, 200, 200);
$pdf->Line(5, $pdf->GetY(), 75, $pdf->GetY());
$pdf->Ln(3);

/* ===== DETALLE DE PRODUCTOS ===== */
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 5, "DETALLE", 0, 1);
$pdf->Ln(1);

/* Encabezado de tabla */
$pdf->SetFillColor(245, 245, 245);
$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell(38, 5, "PRODUCTO", 0, 0, 'L', true);
$pdf->Cell(12, 5, "CANT", 0, 0, 'C', true);
$pdf->Cell(20, 5, "TOTAL", 0, 1, 'R', true);

$pdf->SetDrawColor(220, 220, 220);
$pdf->Line(5, $pdf->GetY(), 75, $pdf->GetY());
$pdf->Ln(2);

/* Items de la orden */
$subtotal_general = 0;
$pdf->SetFont('Arial', '', 8);

while ($item = $items->fetch_assoc()) {
    $subtotal_general += $item['subtotal'];
    
    // Nombre del producto
    $pdf->SetFont('Arial', 'B', 9);
    $nombre_producto = utf8_decode($item['product_name']);
    $pdf->MultiCell(70, 4, $nombre_producto, 0);
    
    // Cantidad, precio y subtotal
    $pdf->SetFont('Arial', '', 8);
    $y_before = $pdf->GetY();
    
    $pdf->SetY($y_before - 4);
    $pdf->SetX(5);
    $pdf->Cell(38, 4, "", 0, 0);
    $pdf->Cell(12, 4, $item['quantity'], 0, 0, 'C');
    $pdf->Cell(20, 4, "Bs " . number_format($item['subtotal'], 2), 0, 1, 'R');
    
    // Precio unitario
    $pdf->SetFont('Arial', '', 7);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->Cell(38, 3, "", 0, 0);
    $pdf->Cell(12, 3, "@ Bs " . number_format($item['price'], 2), 0, 1, 'C');
    $pdf->SetTextColor(0, 0, 0);
    
    $pdf->Ln(1);
}

/* ===== LÍNEA ANTES DE TOTALES ===== */
$pdf->SetDrawColor(150, 150, 150);
$pdf->SetLineWidth(0.5);
$pdf->Line(5, $pdf->GetY(), 75, $pdf->GetY());
$pdf->Ln(3);

/* ===== TOTALES ===== */
$total = $subtotal_general;

$pdf->Ln(1);

/* Total destacado */
$pdf->SetFillColor(40, 40, 40);
$pdf->Rect(5, $pdf->GetY(), 70, 8, 'F');

$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(45, 8, "TOTAL:", 0, 0, 'R');
$pdf->SetFont('Arial', 'B', 13);
$pdf->Cell(25, 8, "Bs " . number_format($total, 2), 0, 1, 'R');

$pdf->SetTextColor(0, 0, 0);
$pdf->Ln(4);

/* ===== PIE DE PÁGINA ===== */
$pdf->SetFont('Arial', 'I', 7);
$pdf->SetTextColor(100, 100, 100);
$pdf->MultiCell(0, 3, utf8_decode("Gracias por su compra"), 0, 'C');
$pdf->MultiCell(0, 3, utf8_decode("¡Vuelva pronto!"), 0, 'C');
$pdf->Ln(2);

$pdf->SetFont('Arial', '', 6);
$pdf->MultiCell(0, 3, "Este documento es una factura valida", 0, 'C');

/* ===== GUARDAR PDF ===== */
$dir = "../facturas/";
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}
$file = $dir . "factura_" . str_pad($order_id, 6, "0", STR_PAD_LEFT) . ".pdf";
$pdf->Output("F", $file);
$pdf->Output("I", "factura_" . str_pad($order_id, 6, "0", STR_PAD_LEFT) . ".pdf");

$conn->close();
?>