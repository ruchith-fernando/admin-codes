<?php
// mobile-allocation-load.php
require_once 'connections/connection.php';
require_once 'includes/helpers.php';

if (!headers_sent()) header('Content-Type: text/html; charset=UTF-8');

$issueId = (int)($_POST['issue_id'] ?? 0);
if ($issueId <= 0) {
  echo bs_alert('danger', 'Issue ID is required.');
  exit;
}

// Load the pending issue
$stmt = $conn->prepare("
  SELECT id, mobile_no, hris_no, name_of_employee, voice_data, issue_status
  FROM tbl_admin_mobile_issues
  WHERE id=? AND issue_status='Pending'
  LIMIT 1
");
$stmt->bind_param("i", $issueId);
$stmt->execute();
$issue = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$issue) {
  echo bs_alert('danger', 'Pending issue not found (already approved or invalid).');
  exit;
}

$mobile = normalize_mobile($issue['mobile_no']);
$hris = trim((string)$issue['hris_no']);
$voice = (string)($issue['voice_data'] ?? '');

// hidden meta for JS
echo "<div id='maPendingMeta' data-issue-id='".(int)$issueId."' data-voice='".esc($voice)."' style='display:none;'></div>";

// show mobile status alert
$_POST['mobile'] = $mobile;
ob_start();
include __DIR__ . '/mobile-allocation-check-mobile.php';
echo ob_get_clean();

// load active allocation
$a = $conn->prepare("
  SELECT mobile_number, hris_no, owner_name, effective_from
  FROM tbl_admin_mobile_allocations
  WHERE mobile_number=?
    AND status='Active'
    AND effective_to IS NULL
  ORDER BY effective_from DESC, id DESC
  LIMIT 1
");
$a->bind_param("s", $mobile);
$a->execute();
$alloc = $a->get_result()->fetch_assoc();
$a->close();

if (!$alloc) {
  echo bs_alert('warning', 'No active allocation found for this mobile. (Initiation may have failed)');
  exit;
}

echo "<div class='alert alert-warning mt-3'>
  🟡 <b>Pending Approval</b> — Issue ID: <b>".esc($issueId)."</b>
</div>";

echo "<div class='card mt-2 p-3 border'>
  <h6 class='text-primary mb-2'>Loaded Allocation</h6>
  <div class='row g-2'>
    <div class='col-md-3'><div class='small text-muted'>Mobile</div><div><b>".esc($mobile)."</b></div></div>
    <div class='col-md-3'><div class='small text-muted'>HRIS</div><div><b>".esc($alloc['hris_no'])."</b></div></div>
    <div class='col-md-4'><div class='small text-muted'>Owner</div><div><b>".esc($alloc['owner_name'])."</b></div></div>
    <div class='col-md-2'><div class='small text-muted'>From</div><div><b>".esc($alloc['effective_from'])."</b></div></div>
  </div>
</div>";

// HRIS active connections + latest issue snapshot per mobile
$q = $conn->prepare("
  SELECT a.mobile_number, a.effective_from
  FROM tbl_admin_mobile_allocations a
  WHERE TRIM(a.hris_no)=?
    AND a.status='Active'
    AND a.effective_to IS NULL
  ORDER BY a.effective_from DESC, a.id DESC
");
$q->bind_param("s", $hris);
$q->execute();
$rs = $q->get_result();

$rows = [];
while ($r = $rs->fetch_assoc()) $rows[] = $r;
$q->close();

echo "<div class='alert alert-info mt-3 mb-2'>
  ℹ️ HRIS <b>".esc($hris)."</b> has <b>".count($rows)."</b> active connection(s).
</div>";

echo "<div class='table-responsive'>
<table class='table table-sm table-bordered align-middle mb-0'>
<thead class='table-light'><tr>
  <th>Mobile</th><th>Effective From</th><th>Voice/Data</th><th>Issue Status</th><th>Conn Status</th>
</tr></thead><tbody>";

foreach ($rows as $r) {
  $m2 = (string)$r['mobile_number'];

  $s2 = $conn->prepare("
    SELECT voice_data, connection_status, issue_status
    FROM tbl_admin_mobile_issues
    WHERE mobile_no=?
    ORDER BY id DESC
    LIMIT 1
  ");
  $s2->bind_param("s", $m2);
  $s2->execute();
  $snap = $s2->get_result()->fetch_assoc();
  $s2->close();

  $vd = $snap['voice_data'] ?? '-';
  $cs = $snap['connection_status'] ?? '-';
  $st = $snap['issue_status'] ?? 'Approved';

  $stBadge = "<span class='badge bg-success'>Approved</span>";
  if (strcasecmp((string)$st,'Pending')===0) $stBadge = "<span class='badge bg-warning text-dark'>Pending</span>";

  $csBadge = "<span class='badge bg-secondary'>".esc($cs)."</span>";
  if (strcasecmp((string)$cs,'Connected')===0) $csBadge = "<span class='badge bg-success'>Connected</span>";
  if (strcasecmp((string)$cs,'Disconnected')===0) $csBadge = "<span class='badge bg-danger'>Disconnected</span>";

  echo "<tr>
    <td><b>".esc($m2)."</b></td>
    <td>".esc($r['effective_from'])."</td>
    <td>".esc($vd)."</td>
    <td>{$stBadge}</td>
    <td>{$csBadge}</td>
  </tr>";
}
echo "</tbody></table></div>";
