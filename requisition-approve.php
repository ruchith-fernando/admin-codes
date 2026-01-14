<?php
// requisition-approve.php
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

if ($action === 'LIST') {

  $sql = "
    SELECT r.req_id, r.req_no, r.required_date, r.total_estimated_amount, r.created_at,
           u.name AS requester_name,
           s.step_order
    FROM tbl_admin_requisitions r
    INNER JOIN tbl_admin_users u ON u.id = r.requester_user_id
    INNER JOIN tbl_admin_requisition_approval_steps s ON s.req_id = r.req_id
    WHERE r.status='IN_APPROVAL'
      AND s.action='PENDING'
      AND s.approver_user_id=?
      AND s.step_order = (
        SELECT MIN(s2.step_order)
        FROM tbl_admin_requisition_approval_steps s2
        WHERE s2.req_id = r.req_id AND s2.action='PENDING'
      )
    ORDER BY r.created_at DESC
  ";

  $rows = [];
  if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $res = $stmt->get_result();
    while($r = $res->fetch_assoc()) $rows[] = $r;
    $stmt->close();
  }

  if (!$rows) {
    echo '<div class="text-muted">No pending approvals.</div>';
    exit;
  }

  echo '<div class="table-responsive"><table class="table table-sm table-bordered">
    <thead class="table-light">
      <tr>
        <th>Req #</th>
        <th>Requester</th>
        <th>Required Date</th>
        <th>Est. Amount</th>
        <th>Step</th>
        <th style="width:160px">Action</th>
      </tr>
    </thead><tbody>';

  foreach($rows as $r){
    $reqId = (int)$r['req_id'];
    $reqNo = htmlspecialchars($r['req_no']);
    $reqBy = htmlspecialchars($r['requester_name']);
    $rdate = htmlspecialchars($r['required_date'] ?? '');
    $amt   = htmlspecialchars($r['total_estimated_amount'] ?? '');
    $step  = (int)$r['step_order'];

    echo "<tr>
      <td>{$reqNo}</td>
      <td>{$reqBy}</td>
      <td>{$rdate}</td>
      <td>{$amt}</td>
      <td>{$step}</td>
      <td class='d-flex gap-2'>
        <button class='btn btn-success btn-sm btn-approve' data-id='{$reqId}'>Approve</button>
        <button class='btn btn-danger btn-sm btn-reject' data-id='{$reqId}'>Reject</button>
      </td>
    </tr>";
  }

  echo '</tbody></table></div>';
  exit;
}

if ($action === 'APPROVE') {
  $req_id = (int)($_POST['req_id'] ?? 0);
  if ($req_id <= 0) { echo bsAlert('danger','Invalid requisition.'); exit; }

  $conn->begin_transaction();
  try {
    $now = date('Y-m-d H:i:s');

    // Find current pending step for this req (lowest step_order)
    $curStepId = 0;
    if ($stmt = $conn->prepare("
      SELECT req_approval_step_id
      FROM tbl_admin_requisition_approval_steps
      WHERE req_id=? AND action='PENDING'
      ORDER BY step_order ASC
      LIMIT 1
    ")) {
      $stmt->bind_param("i", $req_id);
      $stmt->execute();
      $res = $stmt->get_result();
      $row = $res->fetch_assoc();
      $stmt->close();
      $curStepId = (int)($row['req_approval_step_id'] ?? 0);
    }
    if ($curStepId <= 0) throw new Exception('No pending step found.');

    // Ensure the current pending step belongs to this user
    if ($stmt = $conn->prepare("
      SELECT approver_user_id
      FROM tbl_admin_requisition_approval_steps
      WHERE req_approval_step_id=? LIMIT 1
    ")) {
      $stmt->bind_param("i", $curStepId);
      $stmt->execute();
      $res = $stmt->get_result();
      $row = $res->fetch_assoc();
      $stmt->close();
      if ((int)($row['approver_user_id'] ?? 0) !== $uid) {
        throw new Exception('You are not the current approver for this requisition.');
      }
    }

    // Approve
    if ($stmt = $conn->prepare("
      UPDATE tbl_admin_requisition_approval_steps
      SET action='APPROVED', action_by_user_id=?, action_at=?
      WHERE req_approval_step_id=? AND action='PENDING'
    ")) {
      $stmt->bind_param("isi", $uid, $now, $curStepId);
      $stmt->execute();
      $stmt->close();
    }

    // If no pending left => mark requisition APPROVED
    $pending = 0;
    if ($stmt = $conn->prepare("
      SELECT COUNT(*) AS c
      FROM tbl_admin_requisition_approval_steps
      WHERE req_id=? AND action='PENDING'
    ")) {
      $stmt->bind_param("i", $req_id);
      $stmt->execute();
      $res = $stmt->get_result();
      $row = $res->fetch_assoc();
      $stmt->close();
      $pending = (int)($row['c'] ?? 0);
    }

    if ($pending === 0) {
      if ($stmt = $conn->prepare("UPDATE tbl_admin_requisitions SET status='APPROVED' WHERE req_id=?")) {
        $stmt->bind_param("i", $req_id);
        $stmt->execute();
        $stmt->close();
      }
    }

    $conn->commit();
    echo bsAlert('success', $pending===0 ? 'Approved. Requisition is fully approved.' : 'Approved. Sent to next approver.');
    exit;

  } catch (Throwable $e) {
    $conn->rollback();
    echo bsAlert('danger', htmlspecialchars($e->getMessage()));
    exit;
  }
}

if ($action === 'REJECT') {
  $req_id = (int)($_POST['req_id'] ?? 0);
  $reason = trim($_POST['reject_reason'] ?? '');
  if ($req_id <= 0) { echo bsAlert('danger','Invalid requisition.'); exit; }
  if ($reason === '') { echo bsAlert('danger','Reject reason is required.'); exit; }

  $conn->begin_transaction();
  try {
    // Find current pending step
    $curStepId = 0;
    $curApprover = 0;

    if ($stmt = $conn->prepare("
      SELECT req_approval_step_id, approver_user_id
      FROM tbl_admin_requisition_approval_steps
      WHERE req_id=? AND action='PENDING'
      ORDER BY step_order ASC
      LIMIT 1
    ")) {
      $stmt->bind_param("i", $req_id);
      $stmt->execute();
      $res = $stmt->get_result();
      $row = $res->fetch_assoc();
      $stmt->close();
      $curStepId = (int)($row['req_approval_step_id'] ?? 0);
      $curApprover = (int)($row['approver_user_id'] ?? 0);
    }

    if ($curStepId <= 0) throw new Exception('No pending step found.');
    if ($curApprover !== $uid) throw new Exception('You are not the current approver for this requisition.');

    $now = date('Y-m-d H:i:s');

    // Mark step rejected
    if ($stmt = $conn->prepare("
      UPDATE tbl_admin_requisition_approval_steps
      SET action='REJECTED', action_by_user_id=?, action_at=?, remarks=?
      WHERE req_approval_step_id=? AND action='PENDING'
    ")) {
      $stmt->bind_param("issi", $uid, $now, $reason, $curStepId);
      $stmt->execute();
      $stmt->close();
    }

    // Mark requisition rejected
    if ($stmt = $conn->prepare("UPDATE tbl_admin_requisitions SET status='REJECTED' WHERE req_id=?")) {
      $stmt->bind_param("i", $req_id);
      $stmt->execute();
      $stmt->close();
    }

    $conn->commit();
    echo bsAlert('success', 'Requisition rejected.');
    exit;

  } catch (Throwable $e) {
    $conn->rollback();
    echo bsAlert('danger', htmlspecialchars($e->getMessage()));
    exit;
  }
}

echo bsAlert('danger','Invalid action.');
