<?php
require_once 'connections/connection.php';

$branch_code = $_GET['code'] ?? '';
$month = $_GET['month'] ?? '';

header('Content-Type: application/json');

if (!$branch_code) {
    echo json_encode([]);
    exit;
}

$branch_name = null;
$account_no = null;
$bank_paid_to = null;

// 1) Lookup static branch info (account and paid_by) from branch_electricity table
$stmt = $conn->prepare("SELECT branch_name, account_no, bank_paid_to 
                        FROM tbl_admin_branch_electricity 
                        WHERE branch_code = ?");
$stmt->bind_param("s", $branch_code);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    $branch_name  = $row['branch_name'];
    $account_no   = $row['account_no'];
    $bank_paid_to = $row['bank_paid_to'];
}
$stmt->close();

// 2) If month provided, check if an entry already exists for that (branch_code, month_applicable)
$exists = false;
if ($month) {
    $stmt2 = $conn->prepare("SELECT 1 FROM tbl_admin_actual_electricity WHERE branch_code = ? AND month_applicable = ? LIMIT 1");
    $stmt2->bind_param("ss", $branch_code, $month);
    $stmt2->execute();
    $stmt2->store_result();
    $exists = $stmt2->num_rows > 0;
    $stmt2->close();
}

echo json_encode([
    'branch_name'  => $branch_name,
    'account_no'   => $account_no,
    'bank_paid_to' => $bank_paid_to,
    'exists'       => $exists
]);
