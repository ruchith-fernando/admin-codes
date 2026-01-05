<?php
// security-2000-invoice-totals.php
require_once 'connections/connection.php';
header('Content-Type: application/json');

$firm_id = isset($_POST['firm_id']) ? (int)$_POST['firm_id'] : 0;
$month   = trim($_POST['month'] ?? '');

if (!$firm_id || $month === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Missing firm or month',
        'totals'  => []
    ]);
    exit;
}

// Only these branch codes
$codes = ['2014','2015','2016'];

// default output (always return all 3)
$totals = [];
foreach ($codes as $c) {
    $totals[$c] = ['pending' => 0.0, 'approved' => 0.0];
}

$sql = "
    SELECT
        branch_code,
        SUM(CASE WHEN COALESCE(approval_status,'pending') = 'pending'  THEN amount ELSE 0 END) AS pending_total,
        SUM(CASE WHEN COALESCE(approval_status,'pending') = 'approved' THEN amount ELSE 0 END) AS approved_total
    FROM tbl_admin_actual_security_2000_invoices
    WHERE firm_id = ?
      AND month_applicable = ?
      AND branch_code IN ('2014','2015','2016')
    GROUP BY branch_code
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode([
        'success' => false,
        'message' => 'Prepare failed: ' . $conn->error,
        'totals'  => []
    ]);
    exit;
}

$stmt->bind_param("is", $firm_id, $month);
$stmt->execute();
$res = $stmt->get_result();

while ($row = $res->fetch_assoc()) {
    $bc = (string)$row['branch_code'];
    if (!isset($totals[$bc])) continue;

    $totals[$bc]['pending']  = (float)($row['pending_total'] ?? 0);
    $totals[$bc]['approved'] = (float)($row['approved_total'] ?? 0);
}

echo json_encode([
    'success' => true,
    'totals'  => $totals
]);
