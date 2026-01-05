<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');
ob_clean();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(400);
    die(json_encode(['status'=>'error','message'=>'Invalid request method']));
}

file_put_contents(__DIR__.'/debug.log', json_encode($_POST) . PHP_EOL, FILE_APPEND);

// Validate input
$action = $_POST['action'] ?? '';
$id = intval($_POST['id'] ?? 0);
$type = $_POST['type'] ?? '';

if (!$action || !$id || !$type) {
    http_response_code(400);
    die(json_encode(['status'=>'error','message'=>'Missing required parameters']));
}

// Map table names
$table = match($type) {
    'maintenance' => 'tbl_admin_vehicle_maintenance',
    'service' => 'tbl_admin_vehicle_service',
    'license' => 'tbl_admin_vehicle_licensing_insurance',
    default => ''
};
file_put_contents('debugservice.log', "Table: $table | Type: $type".PHP_EOL, FILE_APPEND);

if (!$table) {
    http_response_code(400);
    die(json_encode(['status'=>'error','message'=>'Invalid type']));
}

require_once 'connections/connection.php';

// Approve
if ($action === 'approve') {
    $stmt = $conn->prepare("UPDATE $table SET status='Approved', approved_by=?, approved_at=NOW() WHERE id=?");
    $admin = $_SESSION['hris'] ?? 'system';
    $stmt->bind_param("si", $admin, $id);
    if ($stmt->execute()) {
        echo json_encode(['status'=>'success']);
    } else {
        http_response_code(400);
        echo json_encode(['status'=>'error','message'=>'Failed to approve']);
    }
    exit;
}

// Reject
if ($action === 'reject') {
    $reason = trim($_POST['reason'] ?? '');
    if (!$reason) {
        http_response_code(400);
        die(json_encode(['status'=>'error','message'=>'Missing rejection reason']));
    }
    $stmt = $conn->prepare("UPDATE $table SET status='Rejected', rejection_reason=?, rejected_by=?, rejected_at=NOW() WHERE id=?");
    $admin = $_SESSION['hris'] ?? 'system';
    $stmt->bind_param("ssi", $reason, $admin, $id);
    if ($stmt->execute()) {
        echo json_encode(['status'=>'success']);
    } else {
        http_response_code(400);
        echo json_encode(['status'=>'error','message'=>'Failed to reject']);
    }
    exit;
}

// Delete
if ($action === 'delete') {
    $stmt = $conn->prepare("UPDATE $table SET status='Deleted', deleted_by=?, deleted_at=NOW() WHERE id=?");
    $admin = $_SESSION['hris'] ?? 'system';
    $stmt->bind_param("si", $admin, $id);
    if ($stmt->execute()) {
        echo json_encode(['status'=>'success']);
    } else {
        http_response_code(400);
        echo json_encode(['status'=>'error','message'=>'Failed to delete']);
    }
    exit;
}

http_response_code(400);
echo json_encode(['status'=>'error','message'=>'Invalid action']);
?>
