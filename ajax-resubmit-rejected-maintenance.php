<?php
session_start();
require_once 'connections/connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['hris'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$hris = $_SESSION['hris'];
$id = $_POST['id'] ?? '';
$type = $_POST['type'] ?? '';

if (empty($id) || $type !== 'Battery') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
    exit;
}

$vehicle_number = $conn->real_escape_string($_POST['vehicle_number'] ?? '');
$purchase_date = $conn->real_escape_string($_POST['purchase_date'] ?? '');
$invoice_no = $conn->real_escape_string($_POST['invoice_no'] ?? '');
$supplier = $conn->real_escape_string($_POST['supplier'] ?? '');
$price = floatval($_POST['price'] ?? 0);
$warranty_period = $conn->real_escape_string($_POST['warranty_period'] ?? '');

// Update the rejected record
$sql = "
    UPDATE tbl_admin_vehicle_maintenance
    SET 
        vehicle_number = '$vehicle_number',
        purchase_date = '$purchase_date',
        invoice_no = '$invoice_no',
        supplier = '$supplier',
        price = '$price',
        warranty_period = '$warranty_period',
        status = 'Pending',
        updated_at = NOW()
    WHERE id = '$id' AND maintenance_type = 'Battery' AND entered_by = '$hris'
";

if ($conn->query($sql)) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Database update failed.']);
}
