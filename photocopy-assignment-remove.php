<?php
require_once 'connections/connection.php';
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();

$assign_id  = (int)($_POST['assign_id'] ?? 0);
$removed_at = trim($_POST['removed_at'] ?? '');
$remarks    = trim($_POST['remarks'] ?? '');

if ($assign_id <= 0) {
  echo json_encode(['success'=>false,'message'=>'assign_id is required.']);
  exit;
}
if ($removed_at === '') $removed_at = date('Y-m-d');

$stmt = $conn->prepare("
  UPDATE tbl_admin_photocopy_machine_assignments
  SET removed_at=?, remarks=CONCAT(IFNULL(remarks,''), IF(IFNULL(remarks,'')='', '', ' | '), ?)
  WHERE assign_id=? AND removed_at IS NULL
  LIMIT 1
");
$stmt->bind_param("ssi", $removed_at, $remarks, $assign_id);

if ($stmt->execute() && $stmt->affected_rows > 0) {
  echo json_encode(['success'=>true,'message'=>'Assignment removed (closed).']);
} else {
  echo json_encode(['success'=>false,'message'=>'Nothing updated. Maybe already removed or invalid assign_id.']);
}
