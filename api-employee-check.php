<?php
require_once 'connections/connection.php';
header('Content-Type: application/json');

function out($ok, $arr=[]){ echo json_encode(array_merge(['ok'=>$ok], $arr)); exit; }

$hris = trim($_GET['hris'] ?? '');
if ($hris === '') out(false, ['error'=>'HRIS is required']);

// Only lookup for numeric 6-digit HRIS
if (!preg_match('/^\d{6}$/', $hris)) {
  out(true, ['found'=>false, 'skipped'=>true]);
}

$stmt = $conn->prepare("
  SELECT name_of_employee, status
  FROM tbl_admin_employee_details
  WHERE TRIM(hris)=?
  LIMIT 1
");
$stmt->bind_param("s", $hris);
$stmt->execute();
$r = $stmt->get_result()->fetch_assoc();
$stmt->close();

out(true, [
  'found' => $r ? true : false,
  'owner_name' => $r['name_of_employee'] ?? null,
  'emp_status' => $r['status'] ?? null
]);
