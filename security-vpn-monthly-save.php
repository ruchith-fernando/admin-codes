<?php
require_once 'connections/connection.php';
header('Content-Type: application/json');

$month = trim($_POST['month'] ?? '');
$amount = trim($_POST['amount'] ?? '');
$provision = trim($_POST['provision'] ?? 'no');
$reason = trim($_POST['provision_reason'] ?? '');

if ($month==='' || $amount==='') {
    echo json_encode(['success'=>false, 'message'=>'Missing fields']);
    exit;
}
if (!is_numeric($amount) || $amount <= 0) {
    echo json_encode(['success'=>false, 'message'=>'Invalid amount']);
    exit;
}

$month_esc = mysqli_real_escape_string($conn, $month);
$amount_esc = mysqli_real_escape_string($conn, $amount);
$prov_esc = mysqli_real_escape_string($conn, $provision);
$reason_esc = mysqli_real_escape_string($conn, $reason);

$sql = "
INSERT INTO tbl_admin_actual_security_vpn (month_name, total_amount, is_provision, provision_reason)
VALUES ('$month_esc', '$amount_esc', '$prov_esc', '$reason_esc')
ON DUPLICATE KEY UPDATE
 total_amount = VALUES(total_amount),
 is_provision = VALUES(is_provision),
 provision_reason = VALUES(provision_reason),
 provision_updated_at = NOW()
";

if (mysqli_query($conn, $sql)) {
    echo json_encode(['success'=>true, 'message'=>'Saved successfully.']);
} else {
    echo json_encode(['success'=>false, 'message'=>'Database error: '.mysqli_error($conn)]);
}
