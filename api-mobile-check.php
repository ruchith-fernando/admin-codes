<?php
require_once 'connections/connection.php';
header('Content-Type: application/json');

function out($ok, $arr=[]){ echo json_encode(array_merge(['ok'=>$ok], $arr)); exit; }

$mobile = trim($_GET['mobile'] ?? '');
$mobile = preg_replace('/\D+/', '', $mobile);

if ($mobile === '') out(false, ['error'=>'Mobile is required']);
if (!preg_match('/^\d{9}$/', $mobile)) out(false, ['error'=>'Mobile must be exactly 9 digits (example: 765455585)']);

$stmt = $conn->prepare("
  SELECT id, mobile_number, hris_no, owner_name, effective_from
  FROM tbl_admin_mobile_allocations
  WHERE mobile_number = ?
    AND status='Active'
    AND effective_to IS NULL
  ORDER BY effective_from DESC, id DESC
  LIMIT 1
");
$stmt->bind_param("s", $mobile);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

out(true, [
  'mobile' => $mobile,
  'allocated' => $row ? true : false,
  'row' => $row
]);
