<?php
require_once 'connections/connection.php';
header('Content-Type: application/json');

$hris = trim($_GET['hris'] ?? '');
if ($hris === '') { echo json_encode(['ok'=>false,'error'=>'HRIS is required']); exit; }

if (!preg_match('/^\d{6}$/', $hris)) {
  echo json_encode(['ok'=>true, 'found'=>false, 'skipped'=>true]);
  exit;
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

echo json_encode([
  'ok' => true,
  'found' => $r ? true : false,
  'owner_name' => $r['name_of_employee'] ?? null,
  'emp_status' => $r['status'] ?? null
]);
