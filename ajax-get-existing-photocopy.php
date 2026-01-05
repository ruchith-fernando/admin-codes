<?php
// ajax-get-existing-photocopy.php
require_once 'connections/connection.php';
header('Content-Type: application/json');

$month = trim($_POST['month'] ?? '');
$branch_code = trim($_POST['branch_code'] ?? '');

if ($month === '' || $branch_code === '') {
    echo json_encode(['status' => 'error', 'message' => 'Missing fields']);
    exit;
}

// 🔹 Get branch name from budget table (for display consistency)
$budget_sql = "
    SELECT branch_name 
    FROM tbl_admin_budget_photocopy
    WHERE budget_year = '".mysqli_real_escape_string($conn,$month)."'
      AND branch_code = '".mysqli_real_escape_string($conn,$branch_code)."'
    LIMIT 1
";
$budget_res = mysqli_query($conn, $budget_sql);
$budget_branch = '';
if ($budget_res && mysqli_num_rows($budget_res) > 0) {
    $budget_row = mysqli_fetch_assoc($budget_res);
    $budget_branch = $budget_row['branch_name'];
}

// 🔹 Check if record exists in actuals
$sql = "
    SELECT 
        serial_number,
        branch_name,
        rate,
        number_of_copy,
        sscl,
        vat,
        total,
        is_provision,
        provision_reason
    FROM tbl_admin_actual_photocopy
    WHERE record_date = '".mysqli_real_escape_string($conn,$month)."'
      AND branch_code = '".mysqli_real_escape_string($conn,$branch_code)."'
    LIMIT 1
";
$res = mysqli_query($conn, $sql);

if ($res && mysqli_num_rows($res) > 0) {
    $row = mysqli_fetch_assoc($res);
    $branch_name = $budget_branch !== '' ? $budget_branch : $row['branch_name'];

    echo json_encode([
        'exists' => true,
        'serial_number' => $row['serial_number'] ?? '',
        'branch_name' => $branch_name,
        'rate' => (float)($row['rate'] ?? 0),
        'copies' => (int)($row['number_of_copy'] ?? 0),
        'sscl' => number_format((float)($row['sscl'] ?? 0), 2, '.', ''),
        'vat' => number_format((float)($row['vat'] ?? 0), 2, '.', ''),
        'total' => number_format((float)($row['total'] ?? 0), 2, '.', ''),
        'is_provision' => strtolower($row['is_provision'] ?? 'no'),
        'provision_reason' => $row['provision_reason'] ?? ''
    ]);
} else {
    echo json_encode([
        'exists' => false,
        'serial_number' => '',
        'branch_name' => $budget_branch,
        'rate' => '',
        'copies' => '',
        'sscl' => '',
        'vat' => '',
        'total' => '',
        'is_provision' => 'no',
        'provision_reason' => ''
    ]);
}
?>
