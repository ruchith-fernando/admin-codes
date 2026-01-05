<?php
// ajax-approve-maintenance.php
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
if (empty($id)) {
    echo json_encode(['status' => 'error', 'message' => 'Missing maintenance ID.']);
    exit;
}

$approved_by = $_SESSION['hris'] ?? $_SESSION['username'] ?? 'admin';

$stmt = $conn->prepare("SELECT id FROM tbl_admin_vehicle_maintenance WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
if (!$result || $result->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Entry not found.']);
    exit;
}

$updateQuery = "
    UPDATE tbl_admin_vehicle_maintenance 
    SET 
        status = 'Approved',
        approved_by = ?,
        approved_at = NOW(),
        rejected_by = NULL,
        rejected_at = NULL,
        rejection_reason = NULL
    WHERE id = ?
";

$stmt = $conn->prepare($updateQuery);
$stmt->bind_param('si', $approved_by, $id);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Maintenance entry approved successfully.']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to approve entry.']);
}
exit;
