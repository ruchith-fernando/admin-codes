<?php
include 'connections/connection.php';
session_start();
header('Content-Type: application/json');

function fail($msg){ echo json_encode(['status'=>'error','message'=>$msg]); exit; }

$hdr_id = (int)($_POST['hdr_id'] ?? 0);
$action = $_POST['action'] ?? ''; // APPROVE | REJECT
$remark = trim($_POST['remark'] ?? '');

$checker_id = $_SESSION['user_id'] ?? null;
$is_checker = ($_SESSION['role'] ?? '') === 'CHECKER'; // adjust to your auth

if(!$is_checker) fail("Not authorized.");
if(!$hdr_id) fail("Invalid record.");

$hdr = $conn->prepare("SELECT status, maker_id FROM tbl_admin_tea_service_hdr WHERE id=?");
$hdr->bind_param("i", $hdr_id);
$hdr->execute();
$row = $hdr->get_result()->fetch_assoc();
if(!$row) fail("Record not found.");
if($row['status'] !== 'PENDING') fail("Only PENDING can be processed.");
if($checker_id && $row['maker_id'] && (int)$checker_id === (int)$row['maker_id']) fail("Maker cannot approve/reject own entry.");

if($action === 'APPROVE'){
  $upd = $conn->prepare("UPDATE tbl_admin_tea_service_hdr SET status='APPROVED', checker_id=?, checker_at=NOW(), checker_remark=? WHERE id=?");
  $upd->bind_param("isi", $checker_id, $remark, $hdr_id);
  $upd->execute();
  echo json_encode(['status'=>'success','message'=>'Approved']);
  exit;
}

if($action === 'REJECT'){
  if($remark === '') fail("Remark is required for reject.");
  $upd = $conn->prepare("UPDATE tbl_admin_tea_service_hdr SET status='REJECTED', checker_id=?, checker_at=NOW(), checker_remark=? WHERE id=?");
  $upd->bind_param("isi", $checker_id, $remark, $hdr_id);
  $upd->execute();
  echo json_encode(['status'=>'success','message'=>'Rejected']);
  exit;
}

fail("Invalid action.");
