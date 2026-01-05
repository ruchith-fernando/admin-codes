<?php
// ajax-approve-service-entry.php
header('Content-Type: application/json');
session_start();
require_once 'connections/connection.php';

$id = $_POST['id'] ?? null;
$approved_by = $_SESSION['hris'] ?? 'system';
$approved_at = date('Y-m-d H:i:s');
// file_put_contents('service-approval.log', "[" . date('Y-m-d H:i:s') . "] Approving Service ID: $id by $approved_by\n", FILE_APPEND);
$logPath = __DIR__ . 'service-approval.log';
$logEntry = "[" . date('Y-m-d H:i:s') . "] Approving Service ID: $id by $approved_by\n";

if (!is_dir(__DIR__ . '/logs')) {
    mkdir(__DIR__ . '/logs', 0777, true);
}
file_put_contents($logPath, $logEntry, FILE_APPEND);


if (!$id || !is_numeric($id)) {
    echo json_encode(['status' => 'error', 'message' => 'Missing or invalid record ID']);
    exit;
}

$sql = "UPDATE tbl_admin_vehicle_service 
        SET status = 'Approved', approved_by = ?, approved_at = ? 
        WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssi", $approved_by, $approved_at, $id);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Service record approved successfully.']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Approval failed: ' . $conn->error]);
}
$stmt->close();
$conn->close();
