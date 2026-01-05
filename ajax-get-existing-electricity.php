<?php
// ajax-get-exiting-electricity.php
require_once 'connections/connection.php';
header('Content-Type: application/json');

$branch_code = isset($_POST['branch_code']) ? trim($_POST['branch_code']) : '';
$month       = isset($_POST['month']) ? trim($_POST['month']) : '';

if ($branch_code === '' || $month === '') { echo json_encode(['exists'=>false]); exit; }

$q = mysqli_query($conn, "
  SELECT branch, account_no, bank_paid_to, actual_units, total_amount, is_provision, provision_reason
  FROM tbl_admin_actual_electricity
  WHERE branch_code = '".mysqli_real_escape_string($conn,$branch_code)."' 
    AND month_applicable = '".mysqli_real_escape_string($conn,$month)."'
  LIMIT 1
");
if ($q && mysqli_num_rows($q) > 0) {
  $r = mysqli_fetch_assoc($q);
  echo json_encode([
    'exists'=>true,
    'branch'=>$r['branch'],
    'account_no'=>$r['account_no'],
    'bank_paid_to'=>$r['bank_paid_to'],
    'actual_units'=>$r['actual_units'],
    'total_amount'=>$r['total_amount'],
    'is_provision'=>$r['is_provision'],
    'provision_reason'=>$r['provision_reason']
  ]);
} else {
  echo json_encode(['exists'=>false]);
}
