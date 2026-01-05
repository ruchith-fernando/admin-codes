<?php
session_start();
include 'connections/connection.php';
header('Content-Type: application/json');

$order_number = $_POST['order_number'] ?? '';
$hris = $_SESSION['hris'] ?? '';
if (!$order_number) {
    echo json_encode(['status' => 'error', 'message' => 'Missing order number']);
    exit;
}

$stmt = $conn->prepare("UPDATE tbl_admin_stationary_orders 
                        SET status = 'pending_admin' 
                        WHERE order_number = ?");
$stmt->bind_param("s", $order_number);
if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Approved and sent to Admin.']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to update status.']);
}
exit;
?>
