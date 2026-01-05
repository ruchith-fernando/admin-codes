<?php
include 'connections/connection.php';
header('Content-Type: application/json');

$id = $_POST['id'] ?? '';

if (!is_numeric($id) || $id < 1) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid ID']);
    exit;
}

$sql = "UPDATE tbl_admin_stationary_stock_out SET status = 'deleted' WHERE id = ?";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => 'Prepare failed: ' . $conn->error]);
    exit;
}

$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Item marked as deleted']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Update failed: ' . $stmt->error]);
}
?>
