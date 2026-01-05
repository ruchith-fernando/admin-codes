<?php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$reason = trim($_POST['rejection_reason'] ?? '');
$other  = trim($_POST['other_reason'] ?? '');

if (!$id) {
  echo json_encode(['status'=>'danger','message'=>'Missing ID']);
  exit;
}
if ($reason === '') {
  echo json_encode(['status'=>'danger','message'=>'Rejection reason is required']);
  exit;
}

if (stripos($reason, 'Other') !== false && $other !== '') {
  $reason = "Other: " . $other;
}

$current_hris = trim((string)($_SESSION['hris'] ?? ''));
$current_name = trim((string)($_SESSION['name'] ?? ''));
$current_user = trim((string)($_SESSION['username'] ?? $current_name));

/* Prevent rejecting own entry */
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
  echo json_encode(['status'=>'danger','message'=>'You cannot reject your own entry.']);
  exit;
}

if (($row['approval_status'] ?? 'pending') !== 'pending') {
  echo json_encode(['status'=>'danger','message'=>'This record is not pending anymore.']);
  exit;
}

$stmt = $conn->prepare("
  UPDATE tbl_admin_actual_security_2000_invoices
  SET approval_status='rejected',
      rejected_hris=?,
      rejected_name=?,
      rejected_by=?,
      rejected_at=NOW(),
      rejection_reason=?
  WHERE id=? AND approval_status='pending'
");
$stmt->bind_param("ssssi", $current_hris, $current_name, $current_user, $reason, $id);

if ($stmt->execute() && $stmt->affected_rows > 0) {
  userlog("❌ Rejected 2000 invoice | ID={$id} | Reason={$reason} | HRIS={$current_hris} | Name={$current_name}");
  echo json_encode(['status'=>'success','message'=>'2000 invoice rejected successfully.']);
  exit;
}

echo json_encode(['status'=>'danger','message'=>'Reject failed (maybe already processed).']);
