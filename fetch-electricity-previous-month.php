<?php
// fetch-electricity-previous-month.php
require_once 'connections/connection.php';
header('Content-Type: application/json');

function prevMonth($m) {
  $t = strtotime($m);
  if ($t === false) return '';
  return date("F Y", strtotime("-1 month", $t));
}

$branch_code = isset($_POST['branch_code']) ? trim($_POST['branch_code']) : '';
$month       = isset($_POST['month']) ? trim($_POST['month']) : '';

if ($branch_code === '' || $month === '') { echo json_encode(['found'=>false]); exit; }

$pm = prevMonth($month);
if ($pm === '') { echo json_encode(['found'=>false]); exit; }

$q = mysqli_query($conn, "
  SELECT actual_units, total_amount 
  FROM tbl_admin_actual_electricity
  WHERE branch_code = '".mysqli_real_escape_string($conn,$branch_code)."'
    AND month_applicable = '".mysqli_real_escape_string($conn,$pm)."'
  LIMIT 1
");
if ($q && mysqli_num_rows($q) > 0) {
  $r = mysqli_fetch_assoc($q);
  echo json_encode([
    'found'=>true,
    'units'=>$r['actual_units'],
    'amount'=>$r['total_amount'],
    'month'=>$pm
  ]);
} else {
  echo json_encode(['found'=>false]);
}
