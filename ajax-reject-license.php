<?php
session_start();
header('Content-Type: application/json');
require_once 'connections/connection.php';

$id = $_POST['id'] ?? 0;
$reason = trim($_POST['reason'] ?? '');
$rejector = $_SESSION['hris'] ?? 'system';

if (!$id || !$reason) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid ID or reason.']);
    exit;
}

$sql = "UPDATE tbl_admin_vehicle_licensing_insurance 
        SET status = 'Rejected',
            rejected_by = ?,
            rejected_at = NOW(),
            rejection_reason = ?
        WHERE id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ssi", $rejector, $reason, $id);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => "License entry ID {$id} rejected successfully."]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to reject license entry.']);
}
?>
