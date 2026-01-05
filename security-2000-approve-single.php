<?php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if (!$id) {
  echo json_encode(['status'=>'danger','message'=>'Missing ID']);
  exit;
}

$current_hris = trim((string)($_SESSION['hris'] ?? ''));
$current_name = trim((string)($_SESSION['name'] ?? ''));
$current_user = trim((string)($_SESSION['username'] ?? $current_name));

/* Prevent approving own entry */
$chk = $conn->prepare("SELECT entered_hris, approval_status FROM tbl_admin_actual_security_2000_invoices WHERE id=? LIMIT 1");
$chk->bind_param("i", $id);
$chk->execute();
$res = $chk->get_result();
$row = $res->fetch_assoc();

if (!$row) {
  echo json_encode(['status'=>'danger','message'=>'Record not found']);
  exit;
}

if (trim((string)$row['entered_hris']) !== '' && trim((string)$row['entered_hris']) === $current_hris) {
  echo json_encode(['status'=>'danger','message'=>'You cannot approve your own entry.']);
  exit;
}

if (($row['approval_status'] ?? 'pending') !== 'pending') {
  echo json_encode(['status'=>'danger','message'=>'This record is not pending anymore.']);
  exit;
}

/* Approve */
$stmt = $conn->prepare("
  UPDATE tbl_admin_actual_security_2000_invoices
  SET approval_status='approved',
      approved_hris=?,
      approved_name=?,
      approved_by=?,
      approved_at=NOW()
  WHERE id=? AND approval_status='pending'
");
$stmt->bind_param("sssi", $current_hris, $current_name, $current_user, $id);

if ($stmt->execute() && $stmt->affected_rows > 0) {
  userlog("✅ Approved 2000 invoice | ID={$id} | HRIS={$current_hris} | Name={$current_name}");
  echo json_encode(['status'=>'success','message'=>'2000 invoice approved successfully.']);
  exit;
}

echo json_encode(['status'=>'danger','message'=>'Approve failed (maybe already processed).']);
