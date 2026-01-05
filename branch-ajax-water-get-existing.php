<?php
// branch-ajax-water-get-existing.php
require_once 'connections/connection.php';
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

$branch_code = trim($_POST['branch_code'] ?? '');
$month       = trim($_POST['month'] ?? '');

if ($branch_code === '' || $month === '') {
    echo json_encode(['exists' => false]);
    exit;
}

$sql = "
    SELECT 
        branch,
        branch_code,
        water_type,
        account_number,
        from_date,
        to_date,
        number_of_days,
        usage_qty,
        total_amount
    FROM tbl_admin_actual_water
    WHERE branch_code = '" . mysqli_real_escape_string($conn, $branch_code) . "'
      AND month_applicable = '" . mysqli_real_escape_string($conn, $month) . "'
      AND approval_status = 'approved'
    LIMIT 1
";

$res = mysqli_query($conn, $sql);

if (!$res || mysqli_num_rows($res) === 0) {
    echo json_encode(['exists' => false]);
    exit;
}

$row = mysqli_fetch_assoc($res);

// Format amount
$formatted_amount = number_format((float)$row['total_amount'], 2);

echo json_encode([
    'exists'          => true,
    'branch'          => $row['branch'],
    'branch_code'     => $row['branch_code'],
    'water_type'      => $row['water_type'],
    'account_number'  => $row['account_number'],

    'from_date'       => $row['from_date'],
    'to_date'         => $row['to_date'],
    'number_of_days'  => $row['number_of_days'],
    'usage_qty'       => $row['usage_qty'],
    'total_amount'    => $formatted_amount,

    'locked'          => true,
    'status_msg'      => "✔ Record already entered and approved. Contact Admin for changes."
]);
?>
