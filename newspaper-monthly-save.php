<!-- newspaper-monthly-save.php -->
<?php
require_once 'connections/connection.php';
header('Content-Type: application/json');

$response = ['status' => 'error', 'message' => 'Unknown error'];

$month = trim($_POST['month'] ?? '');
$branch_code = trim($_POST['branch_code'] ?? '');
$branch = trim($_POST['branch_name'] ?? ''); // DB column = branch
$amount = (float)($_POST['amount'] ?? 0);    // posted from JS as "amount"
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
    FROM tbl_admin_actual_newspaper 
    WHERE month_applicable = '".mysqli_real_escape_string($conn,$month)."' 
      AND branch_code = '".mysqli_real_escape_string($conn,$branch_code)."'
";
$check_res = mysqli_query($conn, $check_sql);

if ($check_res && mysqli_num_rows($check_res) > 0) {
    // Update
    $row = mysqli_fetch_assoc($check_res);
    $id = $row['id'];
    $sql = "
        UPDATE tbl_admin_actual_newspaper
        SET branch = '".mysqli_real_escape_string($conn,$branch)."',
            total_amount = '".mysqli_real_escape_string($conn,$amount)."',
            is_provision = '".mysqli_real_escape_string($conn,$is_provision)."',
            provision_reason = '".mysqli_real_escape_string($conn,$provision_reason)."',
            provision_updated_at = NOW()
        WHERE id = {$id}
    ";
    if (mysqli_query($conn, $sql)) {
        $response = ['status' => 'success', 'message' => 'Record updated'];
    } else {
        $response['message'] = 'Update failed: '.mysqli_error($conn);
    }
} else {
    // Insert
    $sql = "
        INSERT INTO tbl_admin_actual_newspaper 
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
        $response = ['status' => 'success', 'message' => 'Record saved'];
    } else {
        $response['message'] = 'Insert failed: '.mysqli_error($conn);
    }
}

echo json_encode($response);
