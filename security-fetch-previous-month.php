<?php
// security-fetch-previous-month.php
require_once 'connections/connection.php';
header('Content-Type: application/json');

$branch_code = $_POST['branch_code'] ?? '';
$month       = $_POST['month'] ?? '';
$firm_id     = intval($_POST['firm_id'] ?? 0);

if(!$branch_code || !$month || !$firm_id){
    echo json_encode(['found'=>false]);
    exit;
}

// Convert current month ("April 2025") to previous month
$date = DateTime::createFromFormat('F Y', $month);
if(!$date){
    echo json_encode(['found'=>false]);
    exit;
}
$date->modify('-1 month');
$prev_month = $date->format('F Y');

$stmt = $conn->prepare("
    SELECT actual_shifts, total_amount 
    FROM tbl_admin_actual_security_firmwise
    WHERE firm_id = ? 
      AND branch_code = ? 
      AND month_applicable = ?
      AND approval_status = 'approved'
    LIMIT 1
");

$stmt->bind_param("iss", $firm_id, $branch_code, $prev_month);
$stmt->execute();
$res = $stmt->get_result();

if($res && $row = $res->fetch_assoc()){
    echo json_encode([
        'found'  => true,
        'shifts' => (int)$row['actual_shifts'],
        'amount' => number_format((float)$row['total_amount'],2),
        'month'  => $prev_month
    ]);
} else {
    echo json_encode(['found'=>false]);
}
