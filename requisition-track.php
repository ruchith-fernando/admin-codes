<?php
// requisition-track.php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
date_default_timezone_set('Asia/Colombo');

if (session_status() === PHP_SESSION_NONE) { session_start(); }

$uid = (int)($_SESSION['id'] ?? 0);
$logged = !empty($_SESSION['loggedin']);

function bsAlert($type,$msg){
  return '<div class="alert alert-'.$type.' alert-dismissible fade show" role="alert">'
    .$msg.'<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function humanDuration($seconds){
  $seconds = max(0, (int)$seconds);
  $d = intdiv($seconds, 86400); $seconds %= 86400;
  $h = intdiv($seconds, 3600);  $seconds %= 3600;
  $m = intdiv($seconds, 60);

  if ($d > 0) return "{$d}d {$h}h {$m}m";
  if ($h > 0) return "{$h}h {$m}m";
  return "{$m}m";
}

if (!$logged || $uid <= 0) {
  echo bsAlert('danger','Session expired. Please login again.');
  exit;
}

$action = strtoupper(trim($_POST['action'] ?? ''));

if ($action === 'LIST') {

  // My requisitions list with current pending step info
  $sql = "
    SELECT
      r.req_id, r.req_no, r.status, r.required_date, r.submitted_at, r.created_at,
      p.cur_step,
      s.approver_name_snapshot AS cur_approver_name,
      s.approver_designation_snapshot AS cur_approver_desig,
      s.approver_user_id AS cur_approver_user_id
    FROM tbl_admin_requisitions r
    LEFT JOIN (
      SELECT req_id, MIN(step_order) AS cur_step
      FROM tbl_admin_requisition_approval_steps
      WHERE action='PENDING'
      GROUP BY req_id
    ) p ON p.req_id = r.req_id
    LEFT JOIN tbl_admin_requisition_approval_steps s
      ON s.req_id = r.req_id
     AND s.step_order = p.cur_step
     AND s.action='PENDING'
    WHERE r.requester_user_id=?
    ORDER BY COALESCE(r.submitted_at, r.created_at) DESC
    LIMIT 50
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
    echo '<div class="text-muted">No requisitions found.</div>';
    exit;
  }

  echo '<div class="table-responsive">
    <table class="table table-sm table-bordered align-middle">
      <thead class="table-light">
        <tr>
          <th style="width:140px">Req #</th>
          <th>Status</th>
          <th>Parked At</th>
          <th>Parked For</th>
          <th>Submitted</th>
          <th style="width:120px">Action</th>
        </tr>
      </thead>
      <tbody>
  ';

  $now = time();

  foreach($rows as $r){
    $reqId = (int)$r['req_id'];
    $reqNo = h($r['req_no']);
    $status = (string)$r['status'];
    $badge = 'secondary';
    if ($status === 'IN_APPROVAL') $badge = 'warning';
    if ($status === 'APPROVED') $badge = 'success';
    if ($status === 'REJECTED') $badge = 'danger';

    $submitted_at = $r['submitted_at'] ?: $r['created_at'];
    $submittedText = $submitted_at ? h($submitted_at) : '-';

    $curStep = (int)($r['cur_step'] ?? 0);

    $parkedAt = '-';
    $parkedFor = '-';

    if ($status === 'IN_APPROVAL' && $curStep > 0) {
      $parkedAt = 'Step <b>'.(int)$curStep.'</b> — <b>'.h($r['cur_approver_name'] ?: 'Approver').'</b>'
        .'<div class="small text-muted">'.h($r['cur_approver_desig'] ?: '-').'</div>';

      // Estimate when this step became pending:
      // - if step 1 => from submitted_at
      // - else from previous step action_at
      $startTs = 0;
      if ($curStep === 1) {
        $startTs = $submitted_at ? strtotime($submitted_at) : $now;
      } else {
        $prevAt = null;
        if ($st2 = $conn->prepare("
          SELECT action_at
          FROM tbl_admin_requisition_approval_steps
          WHERE req_id=? AND step_order=? AND action='APPROVED'
          ORDER BY action_at DESC
          LIMIT 1
        ")) {
          $prevOrder = $curStep - 1;
          $st2->bind_param("ii", $reqId, $prevOrder);
          $st2->execute();
          $rs2 = $st2->get_result();
          if ($rw2 = $rs2->fetch_assoc()) $prevAt = $rw2['action_at'] ?? null;
          $st2->close();
        }
        if ($prevAt) $startTs = strtotime($prevAt);
        else $startTs = $submitted_at ? strtotime($submitted_at) : $now;
      }

      $parkedFor = '<span class="badge bg-dark">'.humanDuration($now - $startTs).'</span>';
      $parkedFor .= '<div class="small text-muted">since '.h(date('Y-m-d H:i', $startTs)).'</div>';
    } else {
      // not in approval - show summary
      if ($status === 'APPROVED') $parkedAt = '<span class="text-success fw-bold">Completed</span>';
      if ($status === 'REJECTED') $parkedAt = '<span class="text-danger fw-bold">Rejected</span>';
    }

    echo '<tr>
      <td><b>'.$reqNo.'</b></td>
      <td><span class="badge bg-'.$badge.'">'.h($status).'</span></td>
      <td>'.$parkedAt.'</td>
      <td>'.$parkedFor.'</td>
      <td>'.$submittedText.'</td>
      <td class="text-center">
        <button type="button" class="btn btn-outline-primary btn-sm btn-track-view" data-id="'.$reqId.'">
          View
        </button>
      </td>
    </tr>';
  }

  echo '</tbody></table></div>';
  exit;
}

if ($action === 'VIEW') {
  $req_id = (int)($_POST['req_id'] ?? 0);
  if ($req_id <= 0) { echo bsAlert('danger','Invalid requisition.'); exit; }

  // Ensure requester owns this requisition
  $req = null;
  if ($stmt = $conn->prepare("
    SELECT req_id, req_no, status, required_date, submitted_at, created_at, overall_justification
    FROM tbl_admin_requisitions
    WHERE req_id=? AND requester_user_id=?
    LIMIT 1
  ")) {
    $stmt->bind_param("ii", $req_id, $uid);
    $stmt->execute();
    $res = $stmt->get_result();
    $req = $res->fetch_assoc();
    $stmt->close();
  }
  if (!$req) { echo bsAlert('danger','Not found.'); exit; }

  // Steps
  $steps = [];
  if ($stmt = $conn->prepare("
    SELECT step_order, approver_name_snapshot, approver_designation_snapshot, action, action_at, remarks
    FROM tbl_admin_requisition_approval_steps
    WHERE req_id=?
    ORDER BY step_order ASC
  ")) {
    $stmt->bind_param("i", $req_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while($r = $res->fetch_assoc()) $steps[] = $r;
    $stmt->close();
  }

  $status = (string)$req['status'];
  $badge = 'secondary';
  if ($status === 'IN_APPROVAL') $badge = 'warning';
  if ($status === 'APPROVED') $badge = 'success';
  if ($status === 'REJECTED') $badge = 'danger';

  $submitted_at = $req['submitted_at'] ?: $req['created_at'];
  $submittedTs = $submitted_at ? strtotime($submitted_at) : time();
  $now = time();

  $total = count($steps);
  $approvedCount = 0;
  $pendingStep = 0;

  foreach($steps as $s){
    if ($s['action'] === 'APPROVED') $approvedCount++;
    if ($pendingStep === 0 && $s['action'] === 'PENDING') $pendingStep = (int)$s['step_order'];
  }

  $progress = ($total > 0) ? (int)round(($approvedCount / $total) * 100) : 0;

  // parked since
  $parkedFor = '-';
  $parkedAt = '-';
  if ($status === 'IN_APPROVAL' && $pendingStep > 0) {
    $startTs = $submittedTs;

    if ($pendingStep > 1) {
      $prevAt = null;
      if ($st2 = $conn->prepare("
        SELECT action_at
        FROM tbl_admin_requisition_approval_steps
        WHERE req_id=? AND step_order=? AND action='APPROVED'
        ORDER BY action_at DESC
        LIMIT 1
      ")) {
        $prevOrder = $pendingStep - 1;
        $st2->bind_param("ii", $req_id, $prevOrder);
        $st2->execute();
        $rs2 = $st2->get_result();
        if ($rw2 = $rs2->fetch_assoc()) $prevAt = $rw2['action_at'] ?? null;
        $st2->close();
      }
      if ($prevAt) $startTs = strtotime($prevAt);
    }

    $parkedFor = humanDuration($now - $startTs).' (since '.date('Y-m-d H:i', $startTs).')';
    $parkedAt = 'Step '.$pendingStep;
  }

  // Pretty view
  echo '
  <div class="mb-2 d-flex align-items-center justify-content-between">
    <div>
      <div class="h5 mb-0">Requisition: <span class="text-primary">'.h($req['req_no']).'</span></div>
      <div class="text-muted small">Submitted: '.h($submitted_at).' • Total time: <b>'.humanDuration($now - $submittedTs).'</b></div>
    </div>
    <div class="text-end">
      <span class="badge bg-'.$badge.' px-3 py-2">'.h($status).'</span>
    </div>
  </div>

  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <div class="fw-bold">Approval Progress</div>
        <div class="small text-muted">'.$approvedCount.' / '.$total.' steps approved</div>
      </div>
      <div class="progress" style="height: 10px;">
        <div class="progress-bar" role="progressbar" style="width: '.$progress.'%;" aria-valuenow="'.$progress.'" aria-valuemin="0" aria-valuemax="100"></div>
      </div>
      <div class="mt-2 d-flex flex-wrap gap-2">
        <span class="badge bg-secondary">Parked At: <b>'.h($parkedAt).'</b></span>
        <span class="badge bg-dark">Parked For: <b>'.h($parkedFor).'</b></span>
        <span class="badge bg-info text-dark">Required Date: <b>'.h($req['required_date'] ?? '-').'</b></span>
      </div>
      '.(($req['overall_justification'] ?? '') !== '' ? '<div class="mt-2 small"><b>Justification:</b> '.nl2br(h($req['overall_justification'])).'</div>' : '').'
    </div>
  </div>

  <style>
    .stepper { list-style:none; padding:0; margin:0; }
    .stepper li { display:flex; gap:12px; padding:12px 0; border-bottom:1px solid #eee; }
    .dot { width:14px; height:14px; border-radius:50%; margin-top:6px; flex:0 0 14px; }
    .dot.approved { background:#198754; }
    .dot.pending  { background:#ffc107; }
    .dot.rejected { background:#dc3545; }
    .dot.other    { background:#adb5bd; }
    .step-title { font-weight:700; }
    .step-meta { color:#6c757d; font-size: 0.85rem; }
    .step-remark { background:#f8f9fa; border:1px solid #eee; padding:8px 10px; border-radius:8px; margin-top:6px; }
  </style>

  <div class="card border-0 shadow-sm">
    <div class="card-body">
      <div class="fw-bold mb-2">Approval Timeline</div>
      <ul class="stepper">
  ';

  foreach($steps as $s){
    $ord = (int)$s['step_order'];
    $an  = h($s['approver_name_snapshot'] ?? 'Approver');
    $ad  = h($s['approver_designation_snapshot'] ?? '-');
    $act = (string)$s['action'];
    $at  = $s['action_at'] ? h($s['action_at']) : '-';
    $rm  = trim((string)($s['remarks'] ?? ''));

    $dot = 'other';
    $labelBadge = 'secondary';
    if ($act === 'APPROVED'){ $dot='approved'; $labelBadge='success'; }
    if ($act === 'PENDING'){ $dot='pending';  $labelBadge='warning'; }
    if ($act === 'REJECTED'){ $dot='rejected'; $labelBadge='danger'; }

    echo '
      <li>
        <div class="dot '.$dot.'"></div>
        <div class="flex-grow-1">
          <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="step-title">Step '.$ord.' — '.$an.'</div>
            <div><span class="badge bg-'.$labelBadge.'">'.h($act).'</span></div>
          </div>
          <div class="step-meta">'.$ad.' • Action time: <b>'.$at.'</b></div>
          '.($rm !== '' ? '<div class="step-remark"><b>Remarks:</b> '.nl2br(h($rm)).'</div>' : '').'
        </div>
      </li>
    ';
  }

  echo '
      </ul>
    </div>
  </div>
  ';
  exit;
}

echo bsAlert('danger','Invalid action.');
