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

// ===== SLA SETTINGS (NO DB CHANGE) =====
// Default SLA hours per approval step (change as you need)
$SLA_HOURS_PER_STEP = 24;

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

function findStepStartTs(mysqli $conn, int $reqId, int $curStep, ?string $submitted_at, ?string $created_at): int {
  $now = time();
  $submitted = $submitted_at ?: $created_at;
  $submittedTs = $submitted ? strtotime($submitted) : $now;

  if ($curStep <= 1) return $submittedTs;

  // Start time for current step = time previous step got approved
  $prevAt = null;
  if ($st = $conn->prepare("
    SELECT action_at
    FROM tbl_admin_requisition_approval_steps
    WHERE req_id=? AND step_order=? AND action='APPROVED'
    ORDER BY action_at DESC
    LIMIT 1
  ")) {
    $prevOrder = $curStep - 1;
    $st->bind_param("ii", $reqId, $prevOrder);
    $st->execute();
    $rs = $st->get_result();
    if ($rw = $rs->fetch_assoc()) $prevAt = $rw['action_at'] ?? null;
    $st->close();
  }

  if ($prevAt) return (int)strtotime($prevAt);
  return $submittedTs;
}

function getAttachments(mysqli $conn, int $reqId): array {
  $atts = [];

  $hasTbl = false;
  if ($st = $conn->prepare("SHOW TABLES LIKE 'tbl_admin_attachments'")) {
    $st->execute();
    $r = $st->get_result();
    $hasTbl = ($r && $r->num_rows > 0);
    $st->close();
  }

  if ($hasTbl) {
    if ($st = $conn->prepare("
      SELECT id AS att_id, file_name, file_path
      FROM tbl_admin_attachments
      WHERE entity_type='REQ' AND entity_id=?
      ORDER BY id DESC
    ")) {
      $st->bind_param("i", $reqId);
      $st->execute();
      $rs = $st->get_result();
      while($rw = $rs->fetch_assoc()){
        $path = (string)($rw['file_path'] ?? '');
        $orig = (string)($rw['file_name'] ?? basename($path));
        $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $aid  = (int)($rw['att_id'] ?? 0);
        if ($path !== '') $atts[] = ['att_id'=>$aid, 'orig'=>$orig, 'path'=>$path, 'ext'=>$ext, 'source'=>'table'];
      }
      $st->close();
    }
  } else {
    $dir = __DIR__ . '/uploads/requisitions/' . $reqId;
    if (is_dir($dir)) {
      $files = @scandir($dir);
      if (is_array($files)) {
        foreach($files as $f){
          if ($f === '.' || $f === '..') continue;
          $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
          $rel = 'uploads/requisitions/' . $reqId . '/' . $f;
          $atts[] = ['att_id'=>0, 'orig'=>$f, 'path'=>$rel, 'ext'=>$ext, 'source'=>'dir'];
        }
      }
    }
  }

  return $atts;
}


/* ===========================
   LIST (Approver pending list)
   =========================== */
if ($action === 'LIST') {

  // Show only requisitions where current pending step belongs to this user
  $sql = "
    SELECT
      r.req_id, r.req_no, r.status, r.required_date, r.submitted_at, r.created_at,
      r.overall_justification,
      u.name AS requester_name,
      s.step_order,
      s.approver_name_snapshot,
      s.approver_designation_snapshot
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
    ORDER BY COALESCE(r.submitted_at, r.created_at) DESC
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

  echo '<div class="table-responsive"><table class="table table-sm table-bordered align-middle">
    <thead class="table-light">
      <tr>
        <th>Req #</th>
        <th>Requester</th>
        <th>Required Date</th>
        <th>Submitted</th>
        <th>Parked For</th>
        <th>SLA</th>
        <th>Step</th>
        <th style="width:240px">Action</th>
      </tr>
    </thead><tbody>';

  $now = time();

  foreach($rows as $r){
    $reqId = (int)$r['req_id'];
    $reqNo = h($r['req_no']);
    $reqBy = h($r['requester_name']);
    $rdate = h($r['required_date'] ?? '');
    $submitted = $r['submitted_at'] ?: $r['created_at'];
    $submittedText = $submitted ? h($submitted) : '-';

    $step  = (int)$r['step_order'];

    $startTs = findStepStartTs($conn, $reqId, $step, $r['submitted_at'] ?? null, $r['created_at'] ?? null);
    $elapsedSec = $now - $startTs;
    $elapsedHuman = humanDuration($elapsedSec);

    $slaSec = $SLA_HOURS_PER_STEP * 3600;
    $isOver = ($elapsedSec > $slaSec);

    $slaBadge = $isOver
      ? "<span class='badge bg-danger' data-bs-toggle='tooltip' title='Over SLA ({$SLA_HOURS_PER_STEP}h per step)'>OVERDUE</span>"
      : "<span class='badge bg-success' data-bs-toggle='tooltip' title='Within SLA ({$SLA_HOURS_PER_STEP}h per step)'>OK</span>";

    $parkBadge = $isOver
      ? "<span class='badge bg-dark'>{$elapsedHuman}</span><div class='small text-danger'>since ".h(date('Y-m-d H:i', $startTs))."</div>"
      : "<span class='badge bg-secondary'>{$elapsedHuman}</span><div class='small text-muted'>since ".h(date('Y-m-d H:i', $startTs))."</div>";

    // make row clickable + keep buttons too
    echo "<tr class='tr-approve-open' role='button' data-id='{$reqId}' style='cursor:pointer'>
      <td><b>{$reqNo}</b></td>
      <td>{$reqBy}</td>
      <td>{$rdate}</td>
      <td>{$submittedText}</td>
      <td>{$parkBadge}</td>
      <td>{$slaBadge}</td>
      <td><span class='badge bg-warning text-dark'>Step {$step}</span></td>
      <td class='d-flex gap-2 justify-content-end'>
        <button type='button' class='btn btn-outline-primary btn-sm btn-approve-view' data-id='{$reqId}'>View</button>
        <button type='button' class='btn btn-success btn-sm btn-approve' data-id='{$reqId}'>Approve</button>
        <button type='button' class='btn btn-danger btn-sm btn-reject' data-id='{$reqId}'>Reject</button>
      </td>
    </tr>";
  }

  echo '</tbody></table></div>';
  exit;
}

/* ===========================
   VIEW (Approver modal details)
   =========================== */
if ($action === 'VIEW') {
  $req_id = (int)($_POST['req_id'] ?? 0);
  if ($req_id <= 0) { echo bsAlert('danger','Invalid requisition.'); exit; }

  // Ensure this user is CURRENT approver for this req (same rule as approve)
  $isMine = false;
  if ($st = $conn->prepare("
    SELECT s.req_approval_step_id
    FROM tbl_admin_requisitions r
    INNER JOIN tbl_admin_requisition_approval_steps s ON s.req_id = r.req_id
    WHERE r.req_id=?
      AND r.status='IN_APPROVAL'
      AND s.action='PENDING'
      AND s.approver_user_id=?
      AND s.step_order = (
        SELECT MIN(s2.step_order)
        FROM tbl_admin_requisition_approval_steps s2
        WHERE s2.req_id=r.req_id AND s2.action='PENDING'
      )
    LIMIT 1
  ")) {
    $st->bind_param("ii", $req_id, $uid);
    $st->execute();
    $rs = $st->get_result();
    $isMine = ($rs && $rs->num_rows > 0);
    $st->close();
  }
  if (!$isMine) {
    echo bsAlert('danger','You are not the current approver for this requisition.');
    exit;
  }

  // Header
  $hdr = null;
  if ($st = $conn->prepare("
    SELECT r.req_id, r.req_no, r.status, r.required_date, r.submitted_at, r.created_at, r.overall_justification,
           u.name AS requester_name,
           d.department_name
    FROM tbl_admin_requisitions r
    INNER JOIN tbl_admin_users u ON u.id = r.requester_user_id
    LEFT JOIN tbl_admin_departments d ON d.department_id = r.department_id
    WHERE r.req_id=?
    LIMIT 1
  ")) {
    $st->bind_param("i", $req_id);
    $st->execute();
    $rs = $st->get_result();
    $hdr = $rs->fetch_assoc();
    $st->close();
  }
  if (!$hdr) { echo bsAlert('danger','Requisition not found.'); exit; }

  // Lines
  $lines = [];
  if ($st = $conn->prepare("
    SELECT item_name, specifications, qty, uom, budget_code, line_justification
    FROM tbl_admin_requisition_lines
    WHERE req_id=?
    ORDER BY line_id ASC
  ")) {
    $st->bind_param("i", $req_id);
    $st->execute();
    $rs = $st->get_result();
    while($rw = $rs->fetch_assoc()) $lines[] = $rw;
    $st->close();
  }

  // Steps timeline
  $steps = [];
  if ($st = $conn->prepare("
    SELECT step_order, approver_name_snapshot, approver_designation_snapshot, action, action_at, remarks
    FROM tbl_admin_requisition_approval_steps
    WHERE req_id=?
    ORDER BY step_order ASC
  ")) {
    $st->bind_param("i", $req_id);
    $st->execute();
    $rs = $st->get_result();
    while($rw = $rs->fetch_assoc()) $steps[] = $rw;
    $st->close();
  }

  // Attachments
  $atts = getAttachments($conn, $req_id);

  // Parked/SLA
  $submitted = $hdr['submitted_at'] ?: $hdr['created_at'];
  $curStep = 0;
  foreach($steps as $s){ if ($curStep===0 && $s['action']==='PENDING') $curStep = (int)$s['step_order']; }
  $startTs = findStepStartTs($conn, $req_id, max(1,$curStep), $hdr['submitted_at'] ?? null, $hdr['created_at'] ?? null);
  $elapsedSec = time() - $startTs;
  $slaSec = $SLA_HOURS_PER_STEP * 3600;
  $over = ($elapsedSec > $slaSec);

  $slaBadge = $over ? "<span class='badge bg-danger'>OVERDUE</span>" : "<span class='badge bg-success'>OK</span>";
  $parkBadge = $over ? "<span class='badge bg-dark'>".h(humanDuration($elapsedSec))."</span>" : "<span class='badge bg-secondary'>".h(humanDuration($elapsedSec))."</span>";

  // UI
  echo "
  <div class='mb-2 d-flex align-items-center justify-content-between flex-wrap gap-2'>
    <div>
      <div class='h5 mb-1'>Requisition <span class='text-primary'>".h($hdr['req_no'])."</span></div>
      <div class='text-muted small'>
        Requester: <b>".h($hdr['requester_name'])."</b> • Department: <b>".h($hdr['department_name'] ?? '-')."</b>
      </div>
    </div>
    <div class='text-end'>
      <div class='small text-muted'>Required Date</div>
      <div class='fw-bold'>".h($hdr['required_date'] ?? '-')."</div>
    </div>
  </div>

  <div class='card border-0 shadow-sm mb-3'>
    <div class='card-body'>
      <div class='d-flex flex-wrap gap-2 align-items-center'>
        <span class='badge bg-warning text-dark'>Current Step: <b>".h($curStep ?: '-')."</b></span>
        <span class='badge bg-info text-dark'>Submitted: <b>".h($submitted ?: '-')."</b></span>
        <span class='badge bg-secondary'>Parked For: {$parkBadge}</span>
        <span class='badge bg-secondary'>SLA: {$slaBadge} <span class='ms-1 small'>( {$SLA_HOURS_PER_STEP}h/step )</span></span>
      </div>
      ".((trim((string)($hdr['overall_justification'] ?? ''))!=='')
        ? "<div class='mt-2'><div class='small text-muted'>Overall Justification</div><div class='p-2 bg-light border rounded'>".nl2br(h($hdr['overall_justification']))."</div></div>"
        : "<div class='mt-2 text-muted small'>No overall justification provided.</div>"
      )."
    </div>
  </div>

  <ul class='nav nav-tabs' id='reqViewTabs' role='tablist'>
    <li class='nav-item' role='presentation'>
      <button class='nav-link active' data-bs-toggle='tab' data-bs-target='#tabItems' type='button' role='tab'>Items</button>
    </li>
    <li class='nav-item' role='presentation'>
      <button class='nav-link' data-bs-toggle='tab' data-bs-target='#tabDocs' type='button' role='tab'>Documents</button>
    </li>
    <li class='nav-item' role='presentation'>
      <button class='nav-link' data-bs-toggle='tab' data-bs-target='#tabFlow' type='button' role='tab'>Approval Flow</button>
    </li>
  </ul>

  <div class='tab-content border border-top-0 rounded-bottom p-3 bg-white'>
    <div class='tab-pane fade show active' id='tabItems' role='tabpanel'>";

  if (!$lines) {
    echo "<div class='text-muted'>No line items found.</div>";
  } else {
    echo "<div class='table-responsive'><table class='table table-sm table-bordered align-middle'>
      <thead class='table-light'>
        <tr>
          <th style='width:20%'>Item</th>
          <th style='width:30%'>Specifications</th>
          <th style='width:8%'>Qty</th>
          <th style='width:10%'>UOM</th>
          <th style='width:12%'>Budget</th>
          <th style='width:20%'>Justification</th>
        </tr>
      </thead><tbody>";
    foreach($lines as $ln){
      echo "<tr>
        <td><b>".h($ln['item_name'])."</b></td>
        <td>".nl2br(h($ln['specifications'] ?? ''))."</td>
        <td>".h($ln['qty'] ?? '')."</td>
        <td>".h($ln['uom'] ?? '')."</td>
        <td>".h($ln['budget_code'] ?? '')."</td>
        <td>".nl2br(h($ln['line_justification'] ?? ''))."</td>
      </tr>";
    }
    echo "</tbody></table></div>";
  }

  echo "
    </div>
    <div class='tab-pane fade' id='tabFlow' role='tabpanel'>
      <div class='table-responsive'>
        <table class='table table-sm table-bordered align-middle'>
          <thead class='table-light'>
            <tr><th style='width:8%'>Step</th><th>Approver</th><th style='width:18%'>Action</th><th style='width:22%'>Action Time</th></tr>
          </thead><tbody>
  ";

  foreach($steps as $s){
    $act = (string)$s['action'];
    $badge = 'secondary';
    if ($act==='APPROVED') $badge='success';
    if ($act==='PENDING') $badge='warning';
    if ($act==='REJECTED') $badge='danger';

    $remarks = trim((string)($s['remarks'] ?? ''));
    $remarksHtml = $remarks!=='' ? "<div class='small text-muted mt-1'><b>Remarks:</b> ".nl2br(h($remarks))."</div>" : "";

    echo "<tr>
      <td>".(int)$s['step_order']."</td>
      <td>
        <b>".h($s['approver_name_snapshot'] ?? 'Approver')."</b>
        <div class='small text-muted'>".h($s['approver_designation_snapshot'] ?? '-')."</div>
        {$remarksHtml}
      </td>
      <td><span class='badge bg-{$badge}'>".h($act)."</span></td>
      <td>".h($s['action_at'] ?? '-')."</td>
    </tr>";
  }

  echo "
          </tbody></table>
      </div>
    </div>

    <div class='tab-pane fade' id='tabDocs' role='tabpanel'>
  ";

  if (!$atts) {
    echo "<div class='text-muted'>No documents uploaded.</div>";
  } else {
    echo "<div class='row g-3'>";
    foreach($atts as $a){

      $orig = h($a['orig'] ?? 'Document');
      $ext  = strtolower((string)($a['ext'] ?? ''));

      $isImg = in_array($ext, ['jpg','jpeg','png','webp','gif'], true);
      $isPdf = ($ext === 'pdf');

      // ✅ Build secure URL via PHP proxy (avoids 403 on /uploads)
      $openUrl = '';
      if (($a['source'] ?? '') === 'table' && (int)($a['att_id'] ?? 0) > 0) {
        $openUrl = 'requisition-file.php?att_id='.(int)$a['att_id'];
      } else {
        $fname = basename((string)($a['path'] ?? ''));
        $openUrl = 'requisition-file.php?req_id='.(int)$req_id.'&file='.rawurlencode($fname);
      }
      $openUrlEsc = h($openUrl);

      echo "<div class='col-md-6'>
        <div class='border rounded p-2 h-100'>
          <div class='d-flex align-items-center justify-content-between'>
            <div class='fw-bold text-truncate' title='{$orig}'>{$orig}</div>
            <a class='btn btn-sm btn-outline-primary' href='{$openUrlEsc}' target='_blank' rel='noopener'>Open</a>
          </div>";

      if ($isImg) {
        echo "<div class='mt-2'>
          <a href='{$openUrlEsc}' target='_blank' rel='noopener'>
            <img src='{$openUrlEsc}' class='img-fluid rounded border' alt='{$orig}'>
          </a>
        </div>";
      } elseif ($isPdf) {
        echo "<div class='mt-2 small text-muted'>PDF document</div>
              <iframe src='{$openUrlEsc}' style='width:100%;height:260px;border:1px solid #eee;border-radius:8px'></iframe>";
      } else {
        echo "<div class='mt-2 small text-muted'>File type: ".h($ext)."</div>";
      }

      echo "</div></div>";
    }

    echo "</div>";
  }

  echo "
    </div>
  </div>
  ";

  exit;
}

/* ===========================
   APPROVE
   =========================== */
if ($action === 'APPROVE') {
  $req_id = (int)($_POST['req_id'] ?? 0);
  if ($req_id <= 0) { echo bsAlert('danger','Invalid requisition.'); exit; }

  $conn->begin_transaction();
  try {
    $now = date('Y-m-d H:i:s');

    // Find current pending step for this req
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
    echo bsAlert('danger', h($e->getMessage()));
    exit;
  }
}

/* ===========================
   REJECT
   =========================== */
if ($action === 'REJECT') {
  $req_id = (int)($_POST['req_id'] ?? 0);
  $reason = trim($_POST['reject_reason'] ?? '');
  if ($req_id <= 0) { echo bsAlert('danger','Invalid requisition.'); exit; }
  if ($reason === '') { echo bsAlert('danger','Reject reason is required.'); exit; }

  $conn->begin_transaction();
  try {
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

    if ($stmt = $conn->prepare("
      UPDATE tbl_admin_requisition_approval_steps
      SET action='REJECTED', action_by_user_id=?, action_at=?, remarks=?
      WHERE req_approval_step_id=? AND action='PENDING'
    ")) {
      $stmt->bind_param("issi", $uid, $now, $reason, $curStepId);
      $stmt->execute();
      $stmt->close();
    }

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
    echo bsAlert('danger', h($e->getMessage()));
    exit;
  }
}

echo bsAlert('danger','Invalid action.');
