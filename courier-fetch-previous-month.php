<?php
// courier-fetch-previous-month.php
require_once 'connections/connection.php';
header('Content-Type: application/json');

$branch_code = $_POST['branch_code'] ?? '';
$month       = $_POST['month'] ?? '';

if ($branch_code === '' || $month === '') {
    echo json_encode(['found' => false, 'message' => 'Invalid request.']);
    exit;
}

// Convert "April 2025" style into previous month string
$ts = strtotime('first day of ' . $month);
if ($ts === false) {
    echo json_encode(['found' => false, 'message' => 'Invalid month format.']);
    exit;
}

$prev_ts    = strtotime('-1 month', $ts);
$prev_month = date('F Y', $prev_ts);

$stmt = $conn->prepare("
    SELECT total_amount, month_applicable, is_provision
    FROM tbl_admin_actual_courier
    WHERE branch_code = ? AND month_applicable = ?
    LIMIT 1
");
$stmt->bind_param("ss", $branch_code, $prev_month);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $raw_amount = str_replace(',', '', $row['total_amount']);
    $amount     = is_numeric($raw_amount) ? (float)$raw_amount : 0.00;

    echo json_encode([
        'found'        => true,
        'amount'       => number_format($amount, 2),
        'month'        => $prev_month,
        'is_provision' => $row['is_provision']
    ]);
} else {
    echo json_encode([
        'found' => false
    ]);
}
