<?php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

$id = (int)($_POST['id'] ?? 0);
$action = strtolower(trim($_POST['action'] ?? ''));
$reason = trim($_POST['reason'] ?? '');

$hris = $_SESSION['hris'] ?? 'N/A';
$name = $_SESSION['name'] ?? 'Unknown';

if (!$id || !in_array($action, ['approve','reject'], true)) {
  echo json_encode(['success'=>false,'message'=>'Invalid request']);
  exit;
}

// load row (must still be pending)
$chk = $conn->prepare("SELECT approval_status FROM tbl_admin_actual_photocopy WHERE id=? LIMIT 1");
$chk->bind_param("i",$id);
$chk->execute();
$r = $chk->get_result()->fetch_assoc();
if (!$r || strtolower($r['approval_status'])!=='pending') {
  echo json_encode(['success'=>false,'message'=>'Item is not pending.']);
  exit;
}

if ($action === 'approve') {
  $u = $conn->prepare("
    UPDATE tbl_admin_actual_photocopy
    SET approval_status='approved',
        approved_hris=?, approved_name=?, approved_at=NOW()
    WHERE id=? LIMIT 1
  ");
  $u->bind_param("ssi",$hris,$name,$id);
  if ($u->execute()) {
    userlog("✅ Photocopy Approved | ID: {$id} | By: {$name}");
    echo json_encode(['success'=>true,'message'=>'Approved']);
    exit;
  }
  echo json_encode(['success'=>false,'message'=>'Approve failed']);
  exit;
}

if ($reason === '') $reason = 'Rejected';
$u = $conn->prepare("
  UPDATE tbl_admin_actual_photocopy
  SET approval_status='rejected',
      rejected_hris=?, rejected_name=?, rejected_at=NOW(),
      rejection_reason=?
  WHERE id=? LIMIT 1
");
$u->bind_param("sssi",$hris,$name,$reason,$id);
if ($u->execute()) {
  userlog("❌ Photocopy Rejected | ID: {$id} | By: {$name} | Reason: {$reason}");
  echo json_encode(['success'=>true,'message'=>'Rejected']);
  exit;
}
echo json_encode(['success'=>false,'message'=>'Reject failed']);
exit;
