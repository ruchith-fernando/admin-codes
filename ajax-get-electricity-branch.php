<?php
require_once 'connections/connection.php';
header('Content-Type: application/json');

$branch_code = isset($_POST['branch_code']) ? trim($_POST['branch_code']) : '';
if ($branch_code === '') { echo json_encode(['success'=>false,'message'=>'No branch code']); exit; }

$q = mysqli_query($conn, "
  SELECT branch_name, account_no, bank_paid_to 
  FROM tbl_admin_branch_electricity 
  WHERE branch_code = '".mysqli_real_escape_string($conn,$branch_code)."' 
  LIMIT 1
");
if ($q && mysqli_num_rows($q) > 0) {
  $r = mysqli_fetch_assoc($q);
  echo json_encode([
    'success'=>true,
    'branch_name'=>$r['branch_name'],
    'account_no'=>$r['account_no'],
    'bank_paid_to'=>$r['bank_paid_to']
  ]);
} else {
  echo json_encode(['success'=>false,'message'=>'Not found']);
}
