<?php
// requisition-chain.php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
date_default_timezone_set('Asia/Colombo');

if (session_status() === PHP_SESSION_NONE) { session_start(); }

$uid = (int)($_SESSION['id'] ?? 0);
$logged = !empty($_SESSION['loggedin']);
header('Content-Type: application/json; charset=utf-8');

if (!$logged || $uid <= 0) {
  echo json_encode(['ok'=>false,'msg'=>'Session expired. Please login again.']);
  exit;
}

$action = strtoupper(trim($_POST['action'] ?? ''));

function jfail($msg){ echo json_encode(['ok'=>false,'msg'=>$msg]); exit; }
function jsucc($arr){ echo json_encode(array_merge(['ok'=>true], $arr)); exit; }

if ($action === 'CHAINS') {
  $department_id = (int)($_POST['department_id'] ?? 0);
  if ($department_id <= 0) jfail('Invalid department.');

  $chains = [];
  if ($stmt = $conn->prepare("
    SELECT chain_id, chain_name, version_no
    FROM tbl_admin_approval_chains
    WHERE department_id=? AND is_active=1
    ORDER BY version_no DESC, chain_id DESC
  ")) {
    $stmt->bind_param("i", $department_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while($r = $res->fetch_assoc()) $chains[] = $r;
    $stmt->close();
  }

  jsucc(['chains'=>$chains]);
}

if ($action === 'STEPS') {
  $chain_id = (int)($_POST['chain_id'] ?? 0);
  if ($chain_id <= 0) jfail('Invalid chain.');

  // chain must be active
  $okChain = 0;
  if ($stmt = $conn->prepare("SELECT chain_id FROM tbl_admin_approval_chains WHERE chain_id=? AND is_active=1 LIMIT 1")) {
    $stmt->bind_param("i", $chain_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();
    $okChain = (int)($row['chain_id'] ?? 0);
  }
  if ($okChain <= 0) jfail('Chain is not active.');

  // steps
  $steps = [];
  if ($stmt = $conn->prepare("
    SELECT step_order, approver_user_id
    FROM tbl_admin_approval_chain_steps
    WHERE chain_id=? AND is_active=1
    ORDER BY step_order ASC
  ")) {
    $stmt->bind_param("i", $chain_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while($r = $res->fetch_assoc()) $steps[] = $r;
    $stmt->close();
  }
  if (!$steps) jfail('No steps found for this chain.');

  // users for dropdown
  $users = [];
  if ($stmt = $conn->prepare("SELECT id, name, designation, branch_name FROM tbl_admin_users ORDER BY name")) {
    $stmt->execute();
    $res = $stmt->get_result();
    while($r = $res->fetch_assoc()) $users[] = $r;
    $stmt->close();
  }

  jsucc(['steps'=>$steps, 'users'=>$users]);
}

jfail('Invalid action.');
