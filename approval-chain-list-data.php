<?php
// approval-chain-list-data.php
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

if ($action === 'LIST_CHAINS') {
  $department_id = (int)($_POST['department_id'] ?? 0);
  if ($department_id <= 0) { echo bsAlert('warning','Select a department.'); exit; }

  $rows = [];
  if ($stmt = $conn->prepare("
    SELECT c.chain_id, c.chain_name, c.version_no, c.is_active, c.created_at,
           u.name AS created_by_name
    FROM tbl_admin_approval_chains c
    LEFT JOIN tbl_admin_users u ON u.id = c.created_by
    WHERE c.department_id=?
    ORDER BY c.is_active DESC, c.version_no DESC, c.chain_id DESC
  ")) {
    $stmt->bind_param("i", $department_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while($r = $res->fetch_assoc()) $rows[] = $r;
    $stmt->close();
  }

  if (!$rows) {
    echo '<div class="text-muted">No approval chains found for this department.</div>';
    exit;
  }

  echo '<div class="table-responsive"><table class="table table-sm table-bordered">
    <thead class="table-light">
      <tr>
        <th>ID</th>
        <th>Chain Name</th>
        <th>Version</th>
        <th>Active</th>
        <th>Created By</th>
        <th>Created At</th>
        <th style="width:140px">Steps</th>
      </tr>
    </thead><tbody>';

  foreach($rows as $r){
    $id = (int)$r['chain_id'];
    $name = htmlspecialchars($r['chain_name']);
    $ver = (int)$r['version_no'];
    $act = ((int)$r['is_active'] === 1);
    $badge = $act ? 'success' : 'secondary';
    $actText = $act ? 'Yes' : 'No';
    $by = htmlspecialchars($r['created_by_name'] ?? '-');
    $at = htmlspecialchars($r['created_at'] ?? '');

    echo "<tr>
      <td>{$id}</td>
      <td>{$name}</td>
      <td>{$ver}</td>
      <td><span class='badge bg-{$badge}'>{$actText}</span></td>
      <td>{$by}</td>
      <td>{$at}</td>
      <td><button class='btn btn-outline-primary btn-sm btn-view-steps' data-id='{$id}'>View Steps</button></td>
    </tr>";
  }

  echo '</tbody></table></div>';
  exit;
}

if ($action === 'LIST_STEPS') {
  $chain_id = (int)($_POST['chain_id'] ?? 0);
  if ($chain_id <= 0) { echo bsAlert('warning','Invalid chain.'); exit; }

  $rows = [];
  if ($stmt = $conn->prepare("
    SELECT s.step_order, s.is_active,
           u.name AS approver_name, u.designation AS approver_designation
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

  if (!$rows) {
    echo '<div class="text-muted">No steps found for this chain.</div>';
    exit;
  }

  echo '<div class="table-responsive"><table class="table table-sm table-bordered">
    <thead class="table-light">
      <tr>
        <th>Step</th>
        <th>Approver</th>
        <th>Designation</th>
        <th>Active</th>
      </tr>
    </thead><tbody>';

  foreach($rows as $r){
    $step = (int)$r['step_order'];
    $nm = htmlspecialchars($r['approver_name'] ?? '');
    $ds = htmlspecialchars($r['approver_designation'] ?? '-');
    $act = ((int)$r['is_active'] === 1);
    $badge = $act ? 'success' : 'secondary';
    $actText = $act ? 'Yes' : 'No';

    echo "<tr>
      <td>{$step}</td>
      <td>{$nm}</td>
      <td>{$ds}</td>
      <td><span class='badge bg-{$badge}'>{$actText}</span></td>
    </tr>";
  }

  echo '</tbody></table></div>';
  exit;
}

echo bsAlert('danger','Invalid action.');
