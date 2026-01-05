<?php
session_start();
header('Content-Type: application/json');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'connections/connection.php';

$response = [];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

$id = $_POST['id'] ?? '';
$reason = trim($_POST['reason'] ?? '');

if (empty($id) || empty($reason)) {
    echo json_encode(['status' => 'error', 'message' => 'Missing ID or reason.']);
    exit;
}

// Validate record
$query = "SELECT id FROM tbl_admin_vehicle_maintenance WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
if (!$result || $result->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Entry not found.']);
    exit;
}

$rejected_by = $_SESSION['hris'] ?? 'admin';

$updateQuery = "
    UPDATE tbl_admin_vehicle_maintenance 
    SET status = 'Rejected',
        rejected_by = ?,
        rejected_at = NOW(),
        rejection_reason = ?,
        approved_by = NULL,
        approved_at = NULL
    WHERE id = ?
";

$stmt = $conn->prepare($updateQuery);
$stmt->bind_param('ssi', $rejected_by, $reason, $id);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => "Maintenance entry ID {$id} rejected successfully."]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to reject entry.']);
}
exit;
?>
