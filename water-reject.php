<?php
// water-reject.php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');
date_default_timezone_set('Asia/Colombo');

$id          = $_POST['id'] ?? '';
$reason      = trim($_POST['rejection_reason'] ?? '');
$other       = trim($_POST['other_reason'] ?? '');
$branch      = trim($_POST['branch'] ?? '');
$branch_code = trim($_POST['branch_code'] ?? '');
$month       = trim($_POST['month'] ?? '');
$hris        = $_SESSION['hris'] ?? '';
$name        = $_SESSION['name'] ?? '';
$ip          = $_SERVER['REMOTE_ADDR'] ?? 'N/A';

// ✅ Validate
if (!$id || !$reason) {
    userlog("❌ Rejection failed — missing fields | HRIS: $hris | User: $name | Record ID: $id | IP: $ip");
    echo json_encode(["status" => "error", "message" => "Invalid request."]);
    exit;
}

$final_reason = ($reason === "Other (specify below)") ? $other : $reason;

// ✅ Update only by unique ID
$stmt = $conn->prepare("
    UPDATE tbl_admin_actual_water
    SET 
        approval_status = 'rejected',
        rejected_hris = ?,
        rejected_name = ?,
        rejected_by = ?,
        rejected_at = NOW(),
        rejection_reason = ?
    WHERE id = ?
    LIMIT 1
");
$stmt->bind_param("ssssi", $hris, $name, $name, $final_reason, $id);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        // ✅ Log full details
        userlog(sprintf(
            "🚫 Record rejected | HRIS: %s | User: %s | Record ID: %s | Branch: %s (%s) | Month: %s | Reason: %s | IP: %s",
            $hris,
            $name,
            $id,
            $branch ?: 'N/A',
            $branch_code ?: 'N/A',
            $month ?: 'N/A',
            $final_reason,
            $ip
        ));
        echo json_encode(["status" => "success", "message" => "Record rejected successfully."]);
    } else {
        userlog("⚠️ Rejection attempt — no matching record or already rejected | HRIS: $hris | Record ID: $id | IP: $ip");
        echo json_encode(["status" => "error", "message" => "Record not found or already processed."]);
    }
} else {
    userlog("❌ Database error on reject | HRIS: $hris | Record ID: $id | Error: {$conn->error} | IP: $ip");
    echo json_encode(["status" => "error", "message" => "Database error: " . $conn->error]);
}
?>
