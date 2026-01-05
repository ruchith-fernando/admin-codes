<?php
// water-delete.php
require_once 'connections/connection.php';
require_once 'includes/userlog.php'; // ✅ Include centralized logging
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

$current_hris  = $_SESSION['hris'] ?? '';
$current_name  = $_SESSION['name'] ?? '';
$ip            = $_SERVER['REMOTE_ADDR'] ?? 'N/A';

// Collect POST data
$id          = $_POST['id'] ?? '';
$branch      = $_POST['branch'] ?? '';
$branch_code = $_POST['branch_code'] ?? '';
$month       = $_POST['month'] ?? '';

if (!$id) {
    userlog("❌ Delete failed — missing record ID | HRIS: $current_hris | User: $current_name | IP: $ip");
    echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
    exit;
}

// ✅ Soft delete (mark as deleted)
$stmt = $conn->prepare("
    UPDATE tbl_admin_actual_water
    SET 
        approval_status = 'deleted',
        rejected_name = ?,
        rejected_hris = ?,
        rejected_at = NOW()
    WHERE id = ? 
      AND entered_hris = ?
");
$stmt->bind_param('ssis', $current_name, $current_hris, $id, $current_hris);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        // ✅ Log successful delete
        userlog("🗑 Marked water record as deleted | HRIS: $current_hris | User: $current_name | Branch: $branch ($branch_code) | Month: $month | Record ID: $id | IP: $ip");
        echo json_encode(['status' => 'success', 'message' => '🗑 Record marked as deleted successfully.']);
    } else {
        // No matching record (user may be trying to delete another user’s entry)
        userlog("⚠️ Delete attempt blocked (unauthorized or missing record) | HRIS: $current_hris | Record ID: $id | IP: $ip");
        echo json_encode(['status' => 'error', 'message' => 'Record not found or not authorized.']);
    }
} else {
    // Log database failure
    userlog("❌ Database error on delete | HRIS: $current_hris | Record ID: $id | Error: {$conn->error} | IP: $ip");
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
}
?>
