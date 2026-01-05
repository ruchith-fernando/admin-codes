<?php
// ajax-get-existing-printing.php
require_once 'connections/connection.php';
header('Content-Type: application/json');

$month = trim($_POST['month'] ?? '');
$branch_code = trim($_POST['branch_code'] ?? '');

if ($month === '' || $branch_code === '') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Missing fields'
    ]);
    exit;
}

// --- First check budget for branch name ---
$budget_sql = "SELECT branch_name 
    FROM tbl_admin_budget_printing
    WHERE budget_year = '".mysqli_real_escape_string($conn,$month)."'
      AND branch_code = '".mysqli_real_escape_string($conn,$branch_code)."'
    LIMIT 1";
$budget_res = mysqli_query($conn, $budget_sql);
$budget_branch = '';
if ($budget_res && mysqli_num_rows($budget_res) > 0) {
    $budget_row = mysqli_fetch_assoc($budget_res);
    $budget_branch = $budget_row['branch_name'];
}

// --- Check actuals ---
$sql = "SELECT * FROM tbl_admin_actual_printing
    WHERE month_applicable = '".mysqli_real_escape_string($conn,$month)."'
      AND branch_code = '".mysqli_real_escape_string($conn,$branch_code)."'
    LIMIT 1";
$res = mysqli_query($conn, $sql);

if ($res && mysqli_num_rows($res) > 0) {
    $row = mysqli_fetch_assoc($res);

    // ✅ Prefer budget branch_name, else actual.branch
    $branch_name = $budget_branch !== '' ? $budget_branch : $row['branch'];

    echo json_encode([
        'exists' => true,
        'branch' => $branch_name,
        'total_amount' => $row['total_amount'],
        'is_provision' => $row['is_provision'],
        'provision_reason' => $row['provision_reason']
    ]);
} else {
    // ✅ No actual exists → return defaults, but include budget branch name
    echo json_encode([
        'exists' => false,
        'branch' => $budget_branch,
        'total_amount' => '',
        'is_provision' => 'no',
        'provision_reason' => ''
    ]);
}
