<?php
session_start();
header('Content-Type: application/json');
require_once 'connections/connection.php';

$id = $_POST['id'] ?? null;
$reason = $_POST['reason'] ?? null;
$rejected_by = $_SESSION['hris'] ?? 'system';
$rejected_at = date('Y-m-d H:i:s');

if (!$id || !$reason) {
    echo json_encode(['status' => 'error', 'message' => 'Missing ID or reason.']);
    exit;
}

$sql = "UPDATE tbl_admin_vehicle_service 
        SET status = 'Rejected',
            rejection_reason = ?, 
            rejected_by = ?, 
            rejected_at = ?
        WHERE id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("sssi", $reason, $rejected_by, $rejected_at, $id);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => "Service entry ID {$id} rejected successfully."]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
}
?>
