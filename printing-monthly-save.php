<?php
// printing-monthly-save.php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$response = ['status' => 'error', 'message' => 'Unknown error'];

// --- Collect POST inputs safely ---
$month = trim($_POST['month'] ?? '');
$branch_code = trim($_POST['branch_code'] ?? '');
$branch = trim($_POST['branch_name'] ?? '');
$amount = (float)($_POST['amount'] ?? 0);
$is_provision = trim($_POST['provision'] ?? 'no');
$provision_reason = trim($_POST['provision_reason'] ?? '');

if ($month === '' || $branch_code === '' || $branch === '') {
    echo json_encode(['status' => 'error', 'message' => '❌ Missing required fields.']);
    exit;
}

if ($amount <= 0) {
    echo json_encode(['status' => 'error', 'message' => '⚠️ Amount must be greater than zero.']);
    exit;
}

// --- Check if record exists ---
$check_sql = "
    SELECT id 
    FROM tbl_admin_actual_printing
    WHERE month_applicable = '" . mysqli_real_escape_string($conn, $month) . "'
      AND branch_code = '" . mysqli_real_escape_string($conn, $branch_code) . "'
    LIMIT 1
";
$check_res = mysqli_query($conn, $check_sql);

if ($check_res && mysqli_num_rows($check_res) > 0) {
    // --- UPDATE existing record ---
    $row = mysqli_fetch_assoc($check_res);
    $id = (int)$row['id'];

    $sql = "
        UPDATE tbl_admin_actual_printing
        SET 
            branch = '" . mysqli_real_escape_string($conn, $branch) . "',
            total_amount = '" . mysqli_real_escape_string($conn, $amount) . "',
            is_provision = '" . mysqli_real_escape_string($conn, $is_provision) . "',
            provision_reason = '" . mysqli_real_escape_string($conn, $provision_reason) . "',
            provision_updated_at = NOW()
        WHERE id = $id
    ";
    $action = 'updated';
} else {
    // --- INSERT new record ---
    $sql = "
        INSERT INTO tbl_admin_actual_printing
            (branch_code, branch, total_amount, is_provision, provision_reason, provision_updated_at, month_applicable, uploaded_at)
        VALUES (
            '" . mysqli_real_escape_string($conn, $branch_code) . "',
            '" . mysqli_real_escape_string($conn, $branch) . "',
            '" . mysqli_real_escape_string($conn, $amount) . "',
            '" . mysqli_real_escape_string($conn, $is_provision) . "',
            '" . mysqli_real_escape_string($conn, $provision_reason) . "',
            NOW(),
            '" . mysqli_real_escape_string($conn, $month) . "',
            NOW()
        )
    ";
    $action = 'inserted';
}

// --- Execute and handle result ---
if (mysqli_query($conn, $sql)) {
    $response = [
        'status' => 'success',
        'message' => "✅ Record $action successfully."
    ];

    // --- Logging ---
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'N/A';
    $user = $_SESSION['name'] ?? 'Unknown';
    $hris = $_SESSION['hris'] ?? 'N/A';

    userlog(sprintf(
        "🖨️ Printing %s | HRIS: %s | User: %s | Branch: %s (%s) | Month: %s | Amount: %.2f | Provision: %s | Reason: %s | IP: %s",
        ucfirst($action), $hris, $user, $branch, $branch_code, $month, $amount, $is_provision, $provision_reason, $ip
    ));
} else {
    $response = [
        'status' => 'error',
        'message' => '❌ Database error: ' . mysqli_error($conn)
    ];
}

echo json_encode($response);
?>
