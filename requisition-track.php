<?php
// requisition-track.php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
date_default_timezone_set('Asia/Colombo');

if (session_status() === PHP_SESSION_NONE) { session_start(); }

$uid = (int)($_SESSION['id'] ?? 0);
$logged = !empty($_SESSION['loggedin']);
if (!$logged || $uid <= 0) { die('<div class="alert alert-danger">Session expired. Please login again.</div>'); }

$action = strtoupper(trim($_POST['action'] ?? ''));

// ===== SLA SETTINGS (NO DB CHANGE) =====
$SLA_HOURS_PER_STEP = 24;

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function bsAlert($type,$msg){
  return '<div class="alert alert-'.$type.' alert-dismissible fade show" role="alert">'
    .$msg.'<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
}

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
   LIST (Requester tracking list)
   =========================== */
if ($action === 'LIST') {

  // Pull requester’s requisitions + current pending step + approver + department + items count
  $sql = "
    SELECT
      r.req_id,
      r.req_no,
      r.status,
      r.required_date,
      r.submitted_at,
      r.created_at,
      d.department_name,
      (SELECT COUNT(*) FROM tbl_admin_requisition_lines l WHERE l.req_id=r.req_id) AS item_count,

      -- current pending step/order
      (SELECT MIN(s.step_order)
         FROM tbl_admin_requisition_approval_steps s
        WHERE s.req_id=r.req_id AND s.action='PENDING') AS cur_step,

      -- current approver snapshot
      (SELECT s2.approver_name_snapshot
         FROM tbl_admin_requisition_approval_steps s2
        WHERE s2.req_id=r.req_id AND s2.action='PENDING'
        ORDER BY s2.step_order ASC
        LIMIT 1) AS cur_approver,

      (SELECT s2.approver_designation_snapshot
         FROM tbl_admin_requisition_approval_steps s2
        WHERE s2.req_id=r.req_id AND s2.action='PENDING'
        ORDER BY s2.step_order ASC
        LIMIT 1) AS cur_approver_desig
    FROM tbl_admin_requisitions r
    LEFT JOIN tbl_admin_departments d ON d.department_id = r.department_id
    WHERE r.requester_user_id=?
    ORDER BY COALESCE(r.submitted_at, r.created_at) DESC
    LIMIT 200
  ";

  $rows = [];
  if ($st = $conn->prepare($sql)) {
    $st->bind_param("i", $uid);
    $st->execute();
    $rs = $st->get_result();
    while($rw = $rs->fetch_assoc()) $rows[] = $rw;
    $st->close();
  }

  if (!$rows) {
    echo '<div class="text-muted">No requisitions found.</div>';
    exit;
  }

  $now = time();

  echo '<div class="table-responsive"><table class="table table-sm table-bordered align-middle">
    <thead class="table-light">
      <tr>
        <th>Req #</th>
        <th>Status</th>
        <th>Department</th>
        <th>Required Date</th>
        <th>Items</th>
        <th>Current Step</th>
        <th>Current Approver</th>
        <th>Parked For</th>
        <th>SLA</th>
        <th>Submitted</th>
        <th style="width:120px">Action</th>
      </tr>
    </thead><tbody>';

  foreach($rows as $r){
    $reqId = (int)$r['req_id'];
    $reqNo = h($r['req_no'] ?? '');
    $status = (string)($r['status'] ?? '');
    $dept = h($r['department_name'] ?? '-');
    $rdate = h($r['required_date'] ?? '-');
    $submitted = $r['submitted_at'] ?: $r['created_at'];
    $submittedText = $submitted ? h($submitted) : '-';
    $items = (int)($r['item_count'] ?? 0);

    $curStep = (int)($r['cur_step'] ?? 0);
    $curAppr = trim((string)($r['cur_approver'] ?? ''));
    $curApprDes = trim((string)($r['cur_approver_desig'] ?? ''));

    // status badge
    $badge = 'secondary';
    if ($status === 'IN_APPROVAL') $badge = 'warning text-dark';
    if ($status === 'APPROVED') $badge = 'success';
    if ($status === 'REJECTED') $badge = 'danger';
    if ($status === 'DRAFT') $badge = 'secondary';

    // Parked/SLA only meaningful when IN_APPROVAL and has current step
    $parkHtml = "<span class='text-muted'>-</span>";
    $slaHtml  = "<span class='text-muted'>-</span>";

    if ($status === 'IN_APPROVAL' && $curStep > 0) {
      $startTs = findStepStartTs($conn, $reqId, $curStep, $r['submitted_at'] ?? null, $r['created_at'] ?? null);
      $elapsed = $now - $startTs;
      $elapsedHuman = humanDuration($elapsed);

      $slaSec = $SLA_HOURS_PER_STEP * 3600;
      $isOver = ($elapsed > $slaSec);

      $parkHtml = $isOver
        ? "<span class='badge bg-dark'>{$elapsedHuman}</span><div class='small text-danger'>since ".h(date('Y-m-d H:i', $startTs))."</div>"
        : "<span class='badge bg-secondary'>{$elapsedHuman}</span><div class='small text-muted'>since ".h(date('Y-m-d H:i', $startTs))."</div>";

      $slaHtml = $isOver
        ? "<span class='badge bg-danger'>OVERDUE</span><div class='small text-muted'>{$SLA_HOURS_PER_STEP}h/step</div>"
        : "<span class='badge bg-success'>OK</span><div class='small text-muted'>{$SLA_HOURS_PER_STEP}h/step</div>";
    }

    $stepHtml = ($status === 'IN_APPROVAL' && $curStep > 0)
      ? "<span class='badge bg-warning text-dark'>Step {$curStep}</span>"
      : "<span class='text-muted'>-</span>";

    $apprHtml = ($status === 'IN_APPROVAL' && $curStep > 0)
      ? "<div class='fw-bold'>".h($curAppr ?: '-')."</div><div class='small text-muted'>".h($curApprDes ?: '-')."</div>"
      : "<span class='text-muted'>-</span>";

    echo "<tr>
      <td><b>{$reqNo}</b></td>
      <td><span class='badge bg-{$badge}'>".h($status)."</span></td>
      <td>{$dept}</td>
      <td>{$rdate}</td>
      <td><span class='badge bg-info text-dark'>{$items}</span></td>
      <td>{$stepHtml}</td>
      <td>{$apprHtml}</td>
      <td>{$parkHtml}</td>
      <td>{$slaHtml}</td>
      <td>{$submittedText}</td>
      <td class='text-end'>
        <button type='button' class='btn btn-outline-primary btn-sm btn-track-view' data-id='{$reqId}'>View</button>
      </td>
    </tr>";
  }

  echo '</tbody></table></div>';
  exit;
}

/* ===========================
   VIEW (Requester modal details)
   =========================== */
if ($action === 'VIEW') {
  $req_id = (int)($_POST['req_id'] ?? 0);
  if ($req_id <= 0) { echo bsAlert('danger','Invalid requisition.'); exit; }

  // Ensure requester owns this requisition
  $owns = false;
  if ($st = $conn->prepare("SELECT req_id FROM tbl_admin_requisitions WHERE req_id=? AND requester_user_id=? LIMIT 1")) {
    $st->bind_param("ii", $req_id, $uid);
    $st->execute();
    $rs = $st->get_result();
    $owns = ($rs && $rs->num_rows > 0);
    $st->close();
  }
  if (!$owns) { echo bsAlert('danger','Forbidden.'); exit; }

  // Header
  $hdr = null;
  if ($st = $conn->prepare("
    SELECT r.req_id, r.req_no, r.status, r.required_date, r.submitted_at, r.created_at, r.overall_justification,
           u.name AS requester_name,
           d.department_name,
           c.chain_name, c.version_no
    FROM tbl_admin_requisitions r
    INNER JOIN tbl_admin_users u ON u.id = r.requester_user_id
    LEFT JOIN tbl_admin_departments d ON d.department_id = r.department_id
    LEFT JOIN tbl_admin_approval_chains c ON c.chain_id = r.approval_chain_id
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

  // Steps
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

  // current step + parked
  $submitted = $hdr['submitted_at'] ?: $hdr['created_at'];
  $curStep = 0;
  foreach($steps as $s){ if ($curStep===0 && ($s['action'] ?? '')==='PENDING') $curStep = (int)$s['step_order']; }

  $startTs = findStepStartTs($conn, $req_id, max(1,$curStep), $hdr['submitted_at'] ?? null, $hdr['created_at'] ?? null);
  $elapsedSec = time() - $startTs;
  $slaSec = $SLA_HOURS_PER_STEP * 3600;
  $over = ($elapsedSec > $slaSec);

  $slaBadge = $over ? "<span class='badge bg-danger'>OVERDUE</span>" : "<span class='badge bg-success'>OK</span>";
  $parkBadge = $over ? "<span class='badge bg-dark'>".h(humanDuration($elapsedSec))."</span>" : "<span class='badge bg-secondary'>".h(humanDuration($elapsedSec))."</span>";

  echo "
  <div class='mb-2 d-flex align-items-center justify-content-between flex-wrap gap-2'>
    <div>
      <div class='h5 mb-1'>Requisition <span class='text-primary'>".h($hdr['req_no'])."</span></div>
      <div class='text-muted small'>
        Department: <b>".h($hdr['department_name'] ?? '-')."</b> • Chain: <b>".h(($hdr['chain_name'] ?? '-') . (isset($hdr['version_no']) ? ' (v'.$hdr['version_no'].')' : ''))."</b>
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
        <span class='badge bg-secondary'>Status: <b>".h($hdr['status'] ?? '-')."</b></span>
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

  <ul class='nav nav-tabs' role='tablist'>
    <li class='nav-item' role='presentation'>
      <button class='nav-link active' data-bs-toggle='tab' data-bs-target='#tabItemsT' type='button' role='tab'>Items</button>
    </li>
    <li class='nav-item' role='presentation'>
      <button class='nav-link' data-bs-toggle='tab' data-bs-target='#tabDocsT' type='button' role='tab'>Documents</button>
    </li>
    <li class='nav-item' role='presentation'>
      <button class='nav-link' data-bs-toggle='tab' data-bs-target='#tabFlowT' type='button' role='tab'>Approval Flow</button>
    </li>
  </ul>

  <div class='tab-content border border-top-0 rounded-bottom p-3 bg-white'>
    <div class='tab-pane fade show active' id='tabItemsT' role='tabpanel'>";

  // Items
  if (!$lines) {
    echo "<div class='text-muted'>No line items found.</div>";
  } else {
    echo "<div class='d-flex flex-column gap-3'>";
    $i = 0;
    foreach($lines as $ln){
      $i++;
      $item = h($ln['item_name'] ?? '');
      $qtyRaw = (string)($ln['qty'] ?? '');
      $qty = h($qtyRaw === '' ? '-' : $qtyRaw);
      $uom = h($ln['uom'] ?? '-');
      $bud = h($ln['budget_code'] ?? '-');

      $spec = trim((string)($ln['specifications'] ?? ''));
      $just = trim((string)($ln['line_justification'] ?? ''));
      if ($spec === '') $spec = '-';
      if ($just === '') $just = '-';

      echo "
        <div class='card border-0 shadow-sm'>
          <div class='card-body'>
            <div class='h6 mb-1'><span class='text-primary fw-bold'>Item {$i}</span></div>
            <div class='fw-bold' style='font-size:1.05rem'>{$item}</div>

            <div class='border rounded bg-white p-2 mt-2'>
              <div class='py-1'><span class='text-muted fw-bold'>Quantity</span> - <span class='fw-bold'>{$qty}</span></div>
              <div class='py-1'><span class='text-muted fw-bold'>UOM</span> - <span class='fw-bold'>{$uom}</span></div>
              <div class='py-1'><span class='text-muted fw-bold'>Budget</span> - <span class='fw-bold'>{$bud}</span></div>
            </div>

            <div class='row g-3 mt-2'>
              <div class='col-md-6'>
                <div class='small text-muted fw-bold mb-1'>Specifications</div>
                <div class='p-2 bg-light border rounded' style='white-space:pre-wrap; word-break:break-word;'>".h($spec)."</div>
              </div>
              <div class='col-md-6'>
                <div class='small text-muted fw-bold mb-1'>Justification</div>
                <div class='p-2 bg-light border rounded' style='white-space:pre-wrap; word-break:break-word;'>".h($just)."</div>
              </div>
            </div>
          </div>
        </div>
      ";
    }
    echo "</div>";
  }

  echo "</div>

    <div class='tab-pane fade' id='tabDocsT' role='tabpanel'>";

  // Documents
  if (!$atts) {
    echo "<div class='text-muted'>No documents uploaded.</div>";
  } else {
    echo "<div class='row g-3'>";
    foreach($atts as $a){
      $orig = h($a['orig'] ?? 'Document');
      $ext  = strtolower((string)($a['ext'] ?? ''));
      $isImg = in_array($ext, ['jpg','jpeg','png','webp','gif'], true);
      $isPdf = ($ext === 'pdf');

      // secure proxy
      $openUrl = '';
      if (($a['source'] ?? '') === 'table' && (int)($a['att_id'] ?? 0) > 0) {
        $openUrl = 'requisition-file.php?att_id='.(int)$a['att_id'];
      } else {
        $fname = basename((string)($a['path'] ?? ''));
        $openUrl = 'requisition-file.php?req_id='.(int)$req_id.'&file='.rawurlencode($fname);
      }
      $u = h($openUrl);

      echo "<div class='col-md-6'>
        <div class='border rounded p-2 h-100'>
          <div class='d-flex align-items-center justify-content-between'>
            <div class='fw-bold text-truncate' title='{$orig}'>{$orig}</div>
            <a class='btn btn-sm btn-outline-primary' href='{$u}' target='_blank' rel='noopener'>Open</a>
          </div>";

      if ($isImg) {
        echo "<div class='mt-2'>
          <a href='{$u}' target='_blank' rel='noopener'>
            <img src='{$u}' class='img-fluid rounded border' alt='{$orig}'>
          </a>
        </div>";
      } elseif ($isPdf) {
        echo "<div class='mt-2 small text-muted'>PDF document</div>
              <iframe src='{$u}' style='width:100%;height:260px;border:1px solid #eee;border-radius:8px'></iframe>";
      } else {
        echo "<div class='mt-2 small text-muted'>File type: ".h($ext)."</div>";
      }

      echo "</div></div>";
    }
    echo "</div>";
  }

  echo "</div>

    <div class='tab-pane fade' id='tabFlowT' role='tabpanel'>
      <div class='table-responsive'>
        <table class='table table-sm table-bordered align-middle'>
          <thead class='table-light'>
            <tr><th style='width:8%'>Step</th><th>Approver</th><th style='width:18%'>Action</th><th style='width:22%'>Action Time</th></tr>
          </thead><tbody>";

  foreach($steps as $s){
    $act = (string)($s['action'] ?? '');
    $badge = 'secondary';
    if ($act==='APPROVED') $badge='success';
    if ($act==='PENDING') $badge='warning text-dark';
    if ($act==='REJECTED') $badge='danger';

    $remarks = trim((string)($s['remarks'] ?? ''));
    $remarksHtml = $remarks!=='' ? "<div class='small text-muted mt-1'><b>Remarks:</b> ".nl2br(h($remarks))."</div>" : "";

    echo "<tr>
      <td>".(int)($s['step_order'] ?? 0)."</td>
      <td>
        <b>".h($s['approver_name_snapshot'] ?? 'Approver')."</b>
        <div class='small text-muted'>".h($s['approver_designation_snapshot'] ?? '-')."</div>
        {$remarksHtml}
      </td>
      <td><span class='badge bg-{$badge}'>".h($act)."</span></td>
      <td>".h($s['action_at'] ?? '-')."</td>
    </tr>";
  }

  echo "</tbody></table></div></div></div>";
  exit;
}

echo bsAlert('danger','Invalid action.');
