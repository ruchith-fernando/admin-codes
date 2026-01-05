<?php
require_once 'connections/connection.php';
header('Content-Type: application/json');

$month = $_POST['month'] ?? '';
if(!$month){
  echo json_encode(['success'=>false,'message'=>'Month not provided']); exit;
}

$prev = date("F Y", strtotime("-1 month", strtotime($month)));

$q = $conn->prepare("SELECT branch_code, branch, total_amount, is_provision, provision_reason FROM tbl_admin_actual_courier WHERE month_applicable=?");
$q->bind_param("s", $prev);
$q->execute();
$res = $q->get_result();

$data = [];
while($r = $res->fetch_assoc()){ $data[] = $r; }

if(count($data)>0)
  echo json_encode(['success'=>true,'data'=>$data,'prev_month'=>$prev]);
else
  echo json_encode(['success'=>false,'message'=>"No data found for $prev"]);
