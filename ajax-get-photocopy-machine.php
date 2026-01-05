<?php
require_once 'connections/connection.php';
header('Content-Type: application/json');

$serial_no = trim($_POST['serial_no'] ?? '');
if ($serial_no === '') {
  echo json_encode(['success'=>false,'message'=>'Serial number is required.']);
  exit;
}

$stmt = $conn->prepare("
  SELECT m.machine_id, m.model_name, m.serial_no, m.vendor_id AS machine_vendor_id, v.vendor_name AS machine_vendor_name
  FROM tbl_admin_photocopy_machines m
  LEFT JOIN tbl_admin_vendors v ON v.vendor_id = m.vendor_id
  WHERE m.serial_no = ?
  LIMIT 1
");
$stmt->bind_param("s", $serial_no);
$stmt->execute();
$r = $stmt->get_result();
$machine = $r->fetch_assoc();

if (!$machine) {
  echo json_encode(['success'=>false,'message'=>'Machine not found for this serial.']);
  exit;
}

// current assignment (removed_at IS NULL)
$mid = (int)$machine['machine_id'];
$stmt2 = $conn->prepare("
  SELECT a.branch_code, b.branch_name, a.installed_at
  FROM tbl_admin_photocopy_machine_assignments a
  LEFT JOIN tbl_admin_branches b ON b.branch_code = a.branch_code
  WHERE a.machine_id = ? AND a.removed_at IS NULL
  ORDER BY a.assign_id DESC
  LIMIT 1
");
$stmt2->bind_param("i", $mid);
$stmt2->execute();
$r2 = $stmt2->get_result();
$curr = $r2->fetch_assoc();

echo json_encode([
  'success' => true,
  'machine_id' => (int)$machine['machine_id'],
  'model_name' => $machine['model_name'],
  'serial_no'  => $machine['serial_no'],
  'machine_vendor_id' => $machine['machine_vendor_id'],
  'machine_vendor_name' => $machine['machine_vendor_name'],
  'current_assignment' => $curr ? $curr : null
]);
