<?php
require_once 'connections/connection.php';
header('Content-Type: application/json');

$branch_code = trim($_POST['branch_code'] ?? '');
if ($branch_code === '') {
  echo json_encode(['success'=>false,'message'=>'Branch code is required.']);
  exit;
}

$stmt = $conn->prepare("SELECT branch_name FROM tbl_admin_branches WHERE branch_code = ? AND is_active=1 LIMIT 1");
$stmt->bind_param("s", $branch_code);
$stmt->execute();
$res = $stmt->get_result();

if ($row = $res->fetch_assoc()) {
  echo json_encode(['success'=>true,'branch_name'=>$row['branch_name']]);
} else {
  echo json_encode(['success'=>false,'message'=>'Branch not found in tbl_admin_branches (or inactive).']);
}
