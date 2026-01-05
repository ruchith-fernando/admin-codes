<?php
session_start();
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
header('Content-Type: application/json; charset=utf-8');

function out($a){ while(ob_get_level()) ob_end_clean(); echo json_encode($a, JSON_INVALID_UTF8_SUBSTITUTE); exit; }
function me(){
  if(!empty($_SESSION['hris'])) return (string)$_SESSION['hris'];
  if(!empty($_SESSION['user_id'])) return (string)$_SESSION['user_id'];
  return '';
}
function nowts(){ return date('Y-m-d H:i:s'); }

$action = $_POST['action'] ?? '';
$id     = (int)($_POST['id'] ?? 0);
$type   = $_POST['type'] ?? '';
$reason = trim($_POST['reason'] ?? '');

$map = [
  'maintenance' => 'tbl_admin_vehicle_maintenance',
  'service'     => 'tbl_admin_vehicle_service',
  'license'     => 'tbl_admin_vehicle_licensing_insurance',
];
$table = $map[$type] ?? '';
if(!$id || !$table || !in_array($action, ['approve','reject'], true)){
  out(['status'=>'error','message'=>'Invalid request.']);
}
$user = me();
if($user===''){ out(['status'=>'error','message'=>'Not logged in.']); }

$conn->set_charset('utf8mb4');
$rs = $conn->query("SELECT * FROM $table WHERE id=$id LIMIT 1");
if(!$rs || !$rs->num_rows){ out(['status'=>'error','message'=>'Record not found.']); }
$row = $rs->fetch_assoc();

$vehicle = $row['vehicle_number'] ?? '';
$sr      = $row['sr_number'] ?? '';

if($action==='approve' && (string)($row['entered_by'] ?? '') === $user){
  out(['status'=>'error','message'=>'You cannot approve your own entry.']);
}

$username = $_SESSION['name'] ?? $user;
$hris     = $_SESSION['hris'] ?? $user;

/* APPROVE ACTION */
if($action==='approve'){
  $stmt = $conn->prepare("UPDATE $table SET status='Approved', approved_by=?, approved_at=? WHERE id=? AND status='Pending'");
  $ts = nowts(); $stmt->bind_param('ssi', $user, $ts, $id);

  if($stmt->execute() && $stmt->affected_rows>0){

    userlog("✅ $username ($hris) APPROVED VEHICLE RECORD | Type: $type | SR: $sr | Vehicle: $vehicle");

    out(['status'=>'success']);
  }

  out(['status'=>'error','message'=>'Approve failed or already processed.']);
}

/* REJECT ACTION */
if($action==='reject'){
  if($reason==='') out(['status'=>'error','message'=>'Rejection reason is required.']);

  $stmt = $conn->prepare("UPDATE $table SET status='Rejected', rejected_by=?, rejected_at=?, rejection_reason=? WHERE id=? AND status='Pending'");
  $ts = nowts(); $stmt->bind_param('sssi', $user, $ts, $reason, $id);

  if($stmt->execute() && $stmt->affected_rows>0){

    userlog("✅ $username ($hris) REJECTED VEHICLE RECORD | Type: $type | SR: $sr | Vehicle: $vehicle | Reason: $reason");

    out(['status'=>'success']);
  }

  out(['status'=>'error','message'=>'Reject failed or already processed.']);
}
