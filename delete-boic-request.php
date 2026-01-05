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

// Allow deletion if: status = draft OR (pending_admin AND created_by = this user)
$sql = "UPDATE tbl_admin_stationary_orders 
        SET status = 'deleted' 
        WHERE order_number = ? 
        AND (status = 'draft' OR (status = 'pending_admin' AND created_by = ?))";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $order_number, $hris);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo json_encode(['status' => 'success', 'message' => 'Request deleted successfully.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Deletion not allowed.']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to delete request.']);
}
exit;
?>
