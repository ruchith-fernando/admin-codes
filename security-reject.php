<?php
// security-reject.php
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
    userlog("❌ Security rejection failed — missing fields | HRIS: $hris | User: $name | Record ID: $id | IP: $ip");
    echo json_encode(["status" => "error", "message" => "Invalid request."]);
    exit;
}

$final_reason = ($reason === "Other (specify below)") ? $other : $reason;

// Fetch record for dual control and status
$stmtSel = $conn->prepare("
    SELECT entered_hris, approval_status
    FROM tbl_admin_actual_security_firmwise
    WHERE id = ?
    LIMIT 1
");
$stmtSel->bind_param("i", $id);
$stmtSel->execute();
$res = $stmtSel->get_result();

if(!$res || !$row = $res->fetch_assoc()){
    echo json_encode(["status" => "error", "message" => "Record not found."]);
    exit;
}

$entered_hris     = trim((string)($row['entered_hris'] ?? ''));
$approval_status  = $row['approval_status'] ?? 'pending';

// Only pending can be rejected
if(!in_array($approval_status, ['pending', null, ''], true)){
    echo json_encode([
        "status"  => "error",
        "message" => "Record is not pending."
    ]);
    exit;
}

// Dual control – optional for reject; if you also want to block self-reject, uncomment:
// if($entered_hris !== '' && $entered_hris === trim((string)$hris)){
//     echo json_encode([
//         "status"  => "error",
//         "message" => "You cannot reject a record you entered (dual control)."
//     ]);
//     exit;
// }

$stmt = $conn->prepare("
    UPDATE tbl_admin_actual_security_firmwise
    SET 
        approval_status   = 'rejected',
        rejected_hris     = ?,
        rejected_name     = ?,
        rejected_by       = ?,
        rejected_at       = NOW(),
        rejection_reason  = ?
    WHERE id = ?
    LIMIT 1
");
$stmt->bind_param("ssssi", $hris, $name, $name, $final_reason, $id);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        // ✅ Log full details
        userlog(sprintf(
            "🚫 Security record rejected | HRIS: %s | User: %s | Record ID: %s | Branch: %s (%s) | Month: %s | Reason: %s | IP: %s",
            $hris,
            $name,
            $id,
            $branch ?: 'N/A',
            $branch_code ?: 'N/A',
            $month ?: 'N/A',
            $final_reason,
            $ip
        ));
        echo json_encode(["status" => "success", "message" => "Security record rejected successfully."]);
    } else {
        userlog("⚠️ Security rejection attempt — no matching record or already processed | HRIS: $hris | Record ID: $id | IP: $ip");
        echo json_encode(["status" => "error", "message" => "Record not found or already processed."]);
    }
} else {
    userlog("❌ Security database error on reject | HRIS: $hris | Record ID: $id | Error: {$conn->error} | IP: $ip");
    echo json_encode(["status" => "error", "message" => "Database error: " . $conn->error]);
}
?>
