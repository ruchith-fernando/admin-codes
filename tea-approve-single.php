<?php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
date_default_timezone_set('Asia/Colombo');

$id   = (int)($_POST['id'] ?? 0);
$hris = trim((string)($_SESSION['hris'] ?? ''));
$name = trim((string)($_SESSION['name'] ?? ''));

if(!$id) { echo json_encode(["status"=>"error","message"=>"Invalid record."]); exit; }
if($hris===''){ echo json_encode(["status"=>"error","message"=>"Session expired."]); exit; }

/* dual control check */
$stmt = $conn->prepare("SELECT entered_hris, approval_status FROM tbl_admin_tea_service_hdr WHERE id=? LIMIT 1");
$stmt->bind_param("i",$id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if(!$row){ echo json_encode(["status"=>"error","message"=>"Record not found."]); exit; }

$entered_hris = trim((string)($row['entered_hris'] ?? ''));
$status = strtolower(trim($row['approval_status'] ?? 'pending'));

if($entered_hris !== '' && $entered_hris === $hris){
  echo json_encode(["status"=>"error","message"=>"You cannot approve a record you entered (dual control)."]);
  exit;
}
if($status !== 'pending'){
  echo json_encode(["status"=>"error","message"=>"Record already processed."]);
  exit;
}

/* approve */
$upd = $conn->prepare("
  UPDATE tbl_admin_tea_service_hdr
  SET approval_status='approved',
      approved_hris=?,
      approved_name=?,
      approved_at=NOW()
  WHERE id=? AND approval_status='pending'
  LIMIT 1
");
$upd->bind_param("ssi",$hris,$name,$id);
$upd->execute();

if($upd->affected_rows <= 0){
  echo json_encode(["status"=>"error","message"=>"Record already approved or not pending."]);
  exit;
}

userlog("✅ Tea Approved | ID: {$id} | By: {$name} ({$hris})");
echo json_encode(["status"=>"success","message"=>"Record approved successfully."]);
