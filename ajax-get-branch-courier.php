<?php
require_once 'connections/connection.php';
header('Content-Type: application/json');

$code = $_POST['branch_code'] ?? '';
if(!$code){ echo json_encode(['success'=>false]); exit; }

$q = $conn->prepare("SELECT branch_name FROM tbl_admin_branch_courier WHERE branch_code=? LIMIT 1");
$q->bind_param("s", $code);
$q->execute();
$r = $q->get_result();

if($r && $row = $r->fetch_assoc()){
  echo json_encode(['success'=>true, 'branch'=>$row['branch_name']]);
} else {
  echo json_encode(['success'=>false]);
}
