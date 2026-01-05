<?php
include 'connections/connection.php';
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

$id = $_POST['id'] ?? '';
$qty = $_POST['quantity'] ?? '';

if (!is_numeric($id) || !is_numeric($qty) || $qty < 1) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid input']);
    exit;
}

$sql = "UPDATE tbl_admin_stationary_stock_out SET quantity = ? WHERE id = ?";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => 'Prepare failed: ' . $conn->error]);
    exit;
}

$stmt->bind_param("ii", $qty, $id);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Quantity updated successfully']);
    exit;
} else {
    echo json_encode(['status' => 'error', 'message' => 'Execute failed: ' . $stmt->error]);
    exit;
}
