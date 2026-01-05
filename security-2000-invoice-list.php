<?php
// security-2000-invoice-list.php
require_once 'connections/connection.php';

header('Content-Type: application/json');

$firm_id = isset($_POST['firm_id']) ? (int)$_POST['firm_id'] : 0;
$month   = trim($_POST['month'] ?? '');

if (!$firm_id || $month === '') {
    echo json_encode([
        'success'  => false,
        'message'  => 'Missing firm or month',
        'invoices' => []
    ]);
    exit;
}

$month_esc = mysqli_real_escape_string($conn, $month);

$sql = "
    SELECT
        branch_code,
        branch,
        invoice_no,
        amount,
        provision,
        COALESCE(approval_status, 'pending') AS approval_status
    FROM tbl_admin_actual_security_2000_invoices
    WHERE firm_id = {$firm_id}
      AND month_applicable = '{$month_esc}'
      AND (approval_status IS NULL 
           OR approval_status IN ('pending','approved','rejected'))
    ORDER BY
        CAST(branch_code AS UNSIGNED),
        branch_code,
        id
";

$res = mysqli_query($conn, $sql);

if (!$res) {
    echo json_encode([
        'success'  => false,
        'message'  => mysqli_error($conn),
        'invoices' => []
    ]);
    exit;
}

$invoices = [];
while ($row = mysqli_fetch_assoc($res)) {
    $invoices[] = [
        'branch_code' => $row['branch_code'],
        'branch_name' => $row['branch'],
        'invoice_no'  => $row['invoice_no'],
        'amount'      => $row['amount'],
        'provision'   => $row['provision'],        // ✅ added
        'status'      => $row['approval_status'],
    ];
}

echo json_encode([
    'success'  => true,
    'invoices' => $invoices
]);
