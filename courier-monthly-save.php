<?php
// <!-- courier-monthly-save.php -->
require_once 'connections/connection.php';
require_once 'includes/userlog.php'; 
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$response = ['status' => 'error', 'message' => 'Unknown error'];

$month = trim($_POST['month'] ?? '');
$branch_code = trim($_POST['branch_code'] ?? '');
$branch = trim($_POST['branch_name'] ?? ''); 
$amount = (float)($_POST['amount'] ?? 0);    
$is_provision = trim($_POST['provision'] ?? 'no');
$provision_reason = trim($_POST['provision_reason'] ?? '');

if ($month === '' || $branch_code === '') {
    $response['message'] = 'Missing required fields';
    echo json_encode($response);
    exit;
}

// Check if record exists
$check_sql = "
    SELECT id 
    FROM tbl_admin_actual_courier 
    WHERE month_applicable = '".mysqli_real_escape_string($conn,$month)."' 
      AND branch_code = '".mysqli_real_escape_string($conn,$branch_code)."'
";
$check_res = mysqli_query($conn, $check_sql);

if ($check_res && mysqli_num_rows($check_res) > 0) {
    // Update
    $row = mysqli_fetch_assoc($check_res);
    $id = $row['id'];
    $sql = "
        UPDATE tbl_admin_actual_courier
        SET branch = '".mysqli_real_escape_string($conn,$branch)."',
            total_amount = '".mysqli_real_escape_string($conn,$amount)."',
            is_provision = '".mysqli_real_escape_string($conn,$is_provision)."',
            provision_reason = '".mysqli_real_escape_string($conn,$provision_reason)."',
            provision_updated_at = NOW()
        WHERE id = {$id}
    ";
    if (mysqli_query($conn, $sql)) {
        $response = ['status' => 'success', 'success' => true, 'message' => 'Record updated'];

        // ✅ Log update
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'N/A';
        $user = $_SESSION['name'] ?? 'Unknown';
        $hris = $_SESSION['hris'] ?? 'N/A';
        $msg = sprintf(
            "✏️ Courier Record Updated | HRIS: %s | User: %s | Branch: %s (%s) | Month: %s | Amount: %.2f | Provision: %s | Reason: %s | IP: %s",
            $hris, $user, $branch, $branch_code, $month, $amount, $is_provision, $provision_reason, $ip
        );
        userlog($msg);

    } else {
        $response['message'] = 'Update failed: '.mysqli_error($conn);
    }
} else {
    // Insert
    $sql = "
        INSERT INTO tbl_admin_actual_courier 
            (month_applicable, branch_code, branch, total_amount, is_provision, provision_reason)
        VALUES (
            '".mysqli_real_escape_string($conn,$month)."',
            '".mysqli_real_escape_string($conn,$branch_code)."',
            '".mysqli_real_escape_string($conn,$branch)."',
            '".mysqli_real_escape_string($conn,$amount)."',
            '".mysqli_real_escape_string($conn,$is_provision)."',
            '".mysqli_real_escape_string($conn,$provision_reason)."'
        )
    ";
    if (mysqli_query($conn, $sql)) {
        $response = ['status' => 'success', 'success' => true, 'message' => 'Record saved'];
        // ✅ Log insert
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'N/A';
        $user = $_SESSION['name'] ?? 'Unknown';
        $hris = $_SESSION['hris'] ?? 'N/A';
        $msg = sprintf(
            "🆕 Courier Record Added | HRIS: %s | User: %s | Branch: %s (%s) | Month: %s | Amount: %.2f | Provision: %s | Reason: %s | IP: %s",
            $hris, $user, $branch, $branch_code, $month, $amount, $is_provision, $provision_reason, $ip
        );
        userlog($msg);

    } else {
        $response['message'] = 'Insert failed: '.mysqli_error($conn);
    }
}

echo json_encode($response);
?>
