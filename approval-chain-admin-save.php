<?php
// approval-chain-admin-save.php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
date_default_timezone_set('Asia/Colombo');

if (session_status() === PHP_SESSION_NONE) { session_start(); }
$uid = (int)($_SESSION['id'] ?? 0);
$logged = !empty($_SESSION['loggedin']);
if (!$logged || $uid <= 0) { die('<div class="alert alert-danger">Session expired. Please login again.</div>'); }

$action = strtoupper(trim($_POST['action'] ?? ''));

function bsAlert($type,$msg){
  return '<div class="alert alert-'.$type.' alert-dismissible fade show" role="alert">'
    .$msg.'<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
}

if ($action === 'CREATE_CHAIN') {
  $department_id = (int)($_POST['department_id'] ?? 0);
  $chain_name = trim($_POST['chain_name'] ?? '');
  $version_no = (int)($_POST['version_no'] ?? 1);
  $is_active = (int)($_POST['is_active'] ?? 0);

  if ($department_id <= 0) { echo bsAlert('danger','Department is required.'); exit; }
  if ($chain_name === '') { echo bsAlert('danger','Chain name is required.'); exit; }
  if ($version_no <= 0) $version_no = 1;
  $is_active = ($is_active === 1) ? 1 : 0;

  if ($stmt = $conn->prepare("
    INSERT INTO tbl_admin_approval_chains (department_id, chain_name, version_no, is_active, created_by)
    VALUES (?, ?, ?, ?, ?)
  ")) {
    $stmt->bind_param("isiii", $department_id, $chain_name, $version_no, $is_active, $uid);
    $stmt->execute();
    $stmt->close();
    echo bsAlert('success','Approval chain created.');
    exit;
  }
  echo bsAlert('danger','DB error.');
  exit;
}

if ($action === 'LIST_CHAINS') {
  $department_id = (int)($_POST['department_id'] ?? 0);
  if ($department_id <= 0) { echo '<div class="text-muted">Select a department.</div>'; exit; }

  $rows = [];
  if ($stmt = $conn->prepare("
    SELECT chain_id, chain_name, version_no, is_active, created_at
    FROM tbl_admin_approval_chains
    WHERE department_id=?
    ORDER BY is_active DESC, version_no DESC, chain_id DESC
  ")) {
    $stmt->bind_param("i", $department_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while($r = $res->fetch_assoc()) $rows[] = $r;
    $stmt->close();
  }

  if (!$rows) { echo '<div class="text-muted">No chains found for this department.</div>'; exit; }

  echo '<div class="table-responsive"><table class="table table-sm table-bordered">
    <thead class="table-light">
      <tr>
        <th>ID</th><th>Name</th><th>Version</th><th>Active</th><th>Created</th><th style="width:200px">Action</th>
      </tr>
    </thead><tbody>';

  foreach($rows as $r){
    $id = (int)$r['chain_id'];
    $name = htmlspecialchars($r['chain_name']);
    $ver = (int)$r['version_no'];
    $act = (int)$r['is_active'] === 1 ? 'Yes' : 'No';
    $badge = (int)$r['is_active'] === 1 ? 'success' : 'secondary';
    $created = htmlspecialchars($r['created_at']);

    echo "<tr>
      <td>{$id}</td>
      <td>{$name}</td>
      <td>{$ver}</td>
      <td><span class='badge bg-{$badge}'>{$act}</span></td>
      <td>{$created}</td>
      <td class='d-flex gap-2'>
        <button class='btn btn-outline-primary btn-sm btn-select-chain' data-id='{$id}'>Select</button>
        <button class='btn btn-outline-secondary btn-sm btn-toggle-chain' data-id='{$id}'>Toggle Active</button>
      </td>
    </tr>";
  }

  echo '</tbody></table></div>';
  exit;
}

if ($action === 'ADD_STEP') {
  $chain_id = (int)($_POST['chain_id'] ?? 0);
  $step_order = (int)($_POST['step_order'] ?? 0);
  $approver_user_id = (int)($_POST['approver_user_id'] ?? 0);

  if ($chain_id <= 0) { echo bsAlert('danger','Invalid chain.'); exit; }
  if ($step_order <= 0) { echo bsAlert('danger','Invalid step order.'); exit; }
  if ($approver_user_id <= 0) { echo bsAlert('danger','Approver is required.'); exit; }

  // upsert behavior: if same step exists, update approver
  if ($stmt = $conn->prepare("
    INSERT INTO tbl_admin_approval_chain_steps (chain_id, step_order, approver_user_id, is_active)
    VALUES (?, ?, ?, 1)
    ON DUPLICATE KEY UPDATE approver_user_id=VALUES(approver_user_id), is_active=1
  ")) {
    $stmt->bind_param("iii", $chain_id, $step_order, $approver_user_id);
    $stmt->execute();
    $stmt->close();
    echo bsAlert('success','Step saved.');
    exit;
  }

  echo bsAlert('danger','DB error saving step.');
  exit;
}

if ($action === 'LIST_STEPS') {
  $chain_id = (int)($_POST['chain_id'] ?? 0);
  if ($chain_id <= 0) { echo bsAlert('warning','Select a chain.'); exit; }

  $rows = [];
  if ($stmt = $conn->prepare("
    SELECT s.step_id, s.step_order, u.name, u.designation, s.is_active
    FROM tbl_admin_approval_chain_steps s
    INNER JOIN tbl_admin_users u ON u.id = s.approver_user_id
    WHERE s.chain_id=?
    ORDER BY s.step_order ASC
  ")) {
    $stmt->bind_param("i", $chain_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while($r = $res->fetch_assoc()) $rows[] = $r;
    $stmt->close();
  }

  if (!$rows) { echo '<div class="text-muted">No steps for this chain.</div>'; exit; }

  echo '<div class="table-responsive"><table class="table table-sm table-bordered">
    <thead class="table-light"><tr>
      <th>Order</th><th>Approver</th><th>Designation</th><th>Active</th><th style="width:120px">Action</th>
    </tr></thead><tbody>';

  foreach($rows as $r){
    $sid = (int)$r['step_id'];
    $ord = (int)$r['step_order'];
    $nm = htmlspecialchars($r['name']);
    $ds = htmlspecialchars($r['designation'] ?? '-');
    $act = (int)$r['is_active'] === 1 ? 'Yes' : 'No';
    $badge = (int)$r['is_active'] === 1 ? 'success' : 'secondary';

    echo "<tr>
      <td>{$ord}</td>
      <td>{$nm}</td>
      <td>{$ds}</td>
      <td><span class='badge bg-{$badge}'>{$act}</span></td>
      <td>
        <button class='btn btn-outline-danger btn-sm btn-del-step' data-id='{$sid}'>Delete</button>
      </td>
    </tr>";
  }

  echo '</tbody></table></div>';
  exit;
}

if ($action === 'DELETE_STEP') {
  $step_id = (int)($_POST['step_id'] ?? 0);
  if ($step_id <= 0) { echo bsAlert('danger','Invalid step.'); exit; }

  if ($stmt = $conn->prepare("DELETE FROM tbl_admin_approval_chain_steps WHERE step_id=?")) {
    $stmt->bind_param("i", $step_id);
    $stmt->execute();
    $stmt->close();
    echo bsAlert('success','Step deleted.');
    exit;
  }
  echo bsAlert('danger','DB error.');
  exit;
}

if ($action === 'TOGGLE_CHAIN') {
  $chain_id = (int)($_POST['chain_id'] ?? 0);
  if ($chain_id <= 0) { echo bsAlert('danger','Invalid chain.'); exit; }

  if ($stmt = $conn->prepare("UPDATE tbl_admin_approval_chains SET is_active = IF(is_active=1,0,1) WHERE chain_id=?")) {
    $stmt->bind_param("i", $chain_id);
    $stmt->execute();
    $stmt->close();
    echo bsAlert('success','Chain status updated.');
    exit;
  }
  echo bsAlert('danger','DB error.');
  exit;
}

echo bsAlert('danger','Invalid action.');
