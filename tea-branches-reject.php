<?php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');
date_default_timezone_set('Asia/Colombo');

$id     = (int)($_POST['id'] ?? 0);
$reason = trim($_POST['rejection_reason'] ?? '');
$other  = trim($_POST['other_reason'] ?? '');

$hris = trim($_SESSION['hris'] ?? '');
$name = trim($_SESSION['name'] ?? '');
$ip   = $_SERVER['REMOTE_ADDR'] ?? 'N/A';

if(!$id || $reason===''){
  echo json_encode(["status"=>"error","message"=>"Invalid request."]);
  exit;
}

$final_reason = ($reason === "Other (specify below)") ? $other : $reason;

// Fetch record for dual control
$stmt = $conn->prepare("
  SELECT entered_hris, approval_status, branch, branch_code, month_applicable
  FROM tbl_admin_actual_tea_branches
  WHERE id=? LIMIT 1
");
$stmt->bind_param("i",$id);
$stmt->execute();
$res = $stmt->get_result();

if(!$res || $res->num_rows===0){
  echo json_encode(["status"=>"error","message"=>"Record not found."]);
  exit;
}

$row = $res->fetch_assoc();
$entered_hris = trim((string)($row['entered_hris'] ?? ''));
$status = strtolower(trim((string)($row['approval_status'] ?? 'pending')));

if($entered_hris !== '' && $hris !== '' && $entered_hris === $hris){
  echo json_encode(["status"=>"error","message"=>"You cannot reject a record you entered (dual control)."]);
  exit;
}
if(in_array($status, ['approved','rejected','deleted'], true)){
  echo json_encode(["status"=>"error","message"=>"Record is already processed."]);
  exit;
}

// Reject
$upd = $conn->prepare("
  UPDATE tbl_admin_actual_tea_branches
  SET approval_status='rejected',
      rejected_hris=?,
      rejected_name=?,
      rejected_by=?,
      rejected_at=NOW(),
      rejection_reason=?
  WHERE id=? LIMIT 1
");
$rejected_by = $name;
$upd->bind_param("ssssi", $hris, $name, $rejected_by, $final_reason, $id);

if($upd->execute() && $upd->affected_rows > 0){
  userlog("🚫 Tea Branch rejected | HRIS:$hris | User:$name | Record:$id | Reason:$final_reason | IP:$ip");
  echo json_encode(["status"=>"success","message"=>"Record rejected successfully."]);
  exit;
}

echo json_encode(["status"=>"error","message"=>"Record not found or already processed."]);
