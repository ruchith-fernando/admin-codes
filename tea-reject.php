<?php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
date_default_timezone_set('Asia/Colombo');

$id     = (int)($_POST['id'] ?? 0);
$reason = trim($_POST['reason'] ?? '');
$other  = trim($_POST['other_reason'] ?? '');

$hris = trim((string)($_SESSION['hris'] ?? ''));
$name = trim((string)($_SESSION['name'] ?? ''));

if(!$id || $reason===''){
  echo json_encode(["status"=>"error","message"=>"Invalid request."]);
  exit;
}

$final_reason = ($reason === "Other (specify below)") ? $other : $reason;
if(trim($final_reason)===''){
  echo json_encode(["status"=>"error","message"=>"Rejection reason is required."]);
  exit;
}

/* dual control check */
$stmt = $conn->prepare("SELECT entered_hris, approval_status FROM tbl_admin_tea_service_hdr WHERE id=? LIMIT 1");
$stmt->bind_param("i",$id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if(!$row){ echo json_encode(["status"=>"error","message"=>"Record not found."]); exit; }

$entered_hris = trim((string)($row['entered_hris'] ?? ''));
$status = strtolower(trim($row['approval_status'] ?? 'pending'));

if($entered_hris !== '' && $entered_hris === $hris){
  echo json_encode(["status"=>"error","message"=>"You cannot reject a record you entered (dual control)."]);
  exit;
}
if($status !== 'pending'){
  echo json_encode(["status"=>"error","message"=>"Record already processed."]);
  exit;
}

$upd = $conn->prepare("
  UPDATE tbl_admin_tea_service_hdr
  SET approval_status='rejected',
      rejected_hris=?,
      rejected_name=?,
      rejected_at=NOW(),
      rejection_reason=?
  WHERE id=? AND approval_status='pending'
  LIMIT 1
");
$upd->bind_param("sssi",$hris,$name,$final_reason,$id);
$upd->execute();

if($upd->affected_rows <= 0){
  echo json_encode(["status"=>"error","message"=>"Reject failed. Record not pending."]);
  exit;
}

userlog("🚫 Tea Rejected | ID: {$id} | By: {$name} ({$hris}) | Reason: {$final_reason}");
echo json_encode(["status"=>"success","message"=>"Record rejected successfully."]);
