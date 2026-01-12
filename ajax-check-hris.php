<?php
// ajax-check-hris.php
require_once 'connections/connection.php';
header('Content-Type: application/json');

$hris = trim($_GET['hris'] ?? '');
if ($hris === '') {
  echo json_encode(['ok'=>false, 'error'=>'HRIS is required']);
  exit;
}

// If numeric -> must be exactly 6 digits
if (ctype_digit($hris) && !preg_match('/^\d{6}$/', $hris)) {
  echo json_encode(['ok'=>false, 'error'=>'Numeric HRIS must be exactly 6 digits']);
  exit;
}

/* employee lookup */
$found = false;
$owner = null;
$empStatus = null;

$emp_stmt = $conn->prepare("
  SELECT name_of_employee, status
  FROM tbl_admin_employee_details
  WHERE TRIM(hris) = ?
  LIMIT 1
");
$emp_stmt->bind_param("s", $hris);
$emp_stmt->execute();
$emp_res = $emp_stmt->get_result();
if ($r = $emp_res->fetch_assoc()) {
  $found = true;
  $owner = $r['name_of_employee'] ?? null;
  $empStatus = $r['status'] ?? null;
}
$emp_stmt->close();

/* does this HRIS already have an active mobile? */
$active_mobile = null;
$mob_stmt = $conn->prepare("
  SELECT mobile_number
  FROM tbl_admin_mobile_allocations
  WHERE TRIM(hris_no) = ?
    AND status='Active'
    AND effective_to IS NULL
  ORDER BY effective_from DESC, id DESC
  LIMIT 1
");
$mob_stmt->bind_param("s", $hris);
$mob_stmt->execute();
$mob_res = $mob_stmt->get_result();
if ($m = $mob_res->fetch_assoc()) {
  $active_mobile = $m['mobile_number'];
}
$mob_stmt->close();

echo json_encode([
  'ok' => true,
  'found' => $found,
  'owner_name' => $owner,
  'emp_status' => $empStatus,
  'active_mobile' => $active_mobile
]);
