<?php
// courier-ajax-get-existing-record.php
require_once 'connections/connection.php';
header('Content-Type: application/json');

$branch_code = $_POST['branch_code'] ?? '';
$month       = $_POST['month'] ?? '';

if ($branch_code === '' || $month === '') {
    echo json_encode(['exists' => false, 'message' => 'Invalid request.']);
    exit;
}

$stmt = $conn->prepare("
    SELECT branch_code, branch, total_amount, is_provision, provision_reason
    FROM tbl_admin_actual_courier
    WHERE branch_code = ? AND month_applicable = ?
    LIMIT 1
");
$stmt->bind_param("ss", $branch_code, $month);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $raw_amount = str_replace(',', '', $row['total_amount']);
    $amount     = is_numeric($raw_amount) ? (float)$raw_amount : 0.00;

    echo json_encode([
        'exists'           => true,
        'branch_code'      => $row['branch_code'],
        'branch'           => $row['branch'],
        'amount'           => number_format($amount, 2),
        'is_provision'     => $row['is_provision'],
        'provision_reason' => $row['provision_reason']
    ]);
} else {
    echo json_encode(['exists' => false]);
}
