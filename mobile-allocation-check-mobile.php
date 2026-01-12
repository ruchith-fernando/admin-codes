<?php
// mobile-allocation-check-mobile.php
require_once 'connections/connection.php';
require_once 'includes/helpers.php';

if (!headers_sent()) header('Content-Type: text/html; charset=UTF-8');

$mobile = normalize_mobile($_POST['mobile'] ?? '');
if ($mobile === '' || !preg_match('/^\d{9}$/', $mobile)) {
  echo bs_alert('danger', "Mobile must be exactly 9 digits (example: 765455585).");
  exit;
}

// latest issue snapshot
$s = $conn->prepare("
  SELECT voice_data, connection_status, disconnection_date, issue_status
  FROM tbl_admin_mobile_issues
  WHERE mobile_no=?
  ORDER BY id DESC
  LIMIT 1
");
$s->bind_param("s", $mobile);
$s->execute();
$issue = $s->get_result()->fetch_assoc();
$s->close();

$issueLine = "";
if ($issue) {
  $vd = esc($issue['voice_data'] ?? '-');
  $cs = (string)($issue['connection_status'] ?? '-');
  $dd = esc($issue['disconnection_date'] ?? '-');
  $st = (string)($issue['issue_status'] ?? 'Approved');

  $badge = "<span class='badge bg-secondary'>".esc($cs)."</span>";
  if (strcasecmp($cs,'Connected')===0) $badge = "<span class='badge bg-success'>Connected</span>";
  if (strcasecmp($cs,'Disconnected')===0) $badge = "<span class='badge bg-danger'>Disconnected</span>";

  $stBadge = "<span class='badge bg-success'>Approved</span>";
  if (strcasecmp($st,'Pending')===0) $stBadge = "<span class='badge bg-warning text-dark'>Pending</span>";

  $issueLine = "<div class='mt-2 small'>
    <b>Latest Issue:</b> Status: {$stBadge} | Voice/Data: <b>{$vd}</b> | Conn: {$badge} | Disconnection: <b>{$dd}</b>
  </div>";
}

// active allocation?
$stmt = $conn->prepare("
  SELECT mobile_number, hris_no, owner_name, effective_from
  FROM tbl_admin_mobile_allocations
  WHERE mobile_number=?
    AND status='Active'
    AND effective_to IS NULL
  ORDER BY effective_from DESC, id DESC
  LIMIT 1
");
$stmt->bind_param("s", $mobile);
$stmt->execute();
$active = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($active) {
  echo bs_alert('warning',
    "<b>Status:</b> <span class='badge bg-success'>ACTIVE</span><br>
     ⚠️ <b>".esc($mobile)."</b> is allocated to <b>".esc($active['owner_name'])."</b>
     (HRIS: <b>".esc($active['hris_no'])."</b>) since <b>".esc($active['effective_from'])."</b>.<br>
     <small class='text-muted'>Saving again will TRANSFER (previous allocation will be closed).</small>
     {$issueLine}"
  );
  exit;
}

// history?
$stmt = $conn->prepare("SELECT COUNT(*) c FROM tbl_admin_mobile_allocations WHERE mobile_number=?");
$stmt->bind_param("s", $mobile);
$stmt->execute();
$total = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

if ($total > 0) {
  $stmt = $conn->prepare("
    SELECT hris_no, owner_name, effective_from, effective_to
    FROM tbl_admin_mobile_allocations
    WHERE mobile_number=?
    ORDER BY effective_from DESC, id DESC
    LIMIT 1
  ");
  $stmt->bind_param("s", $mobile);
  $stmt->execute();
  $last = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  echo bs_alert('info',
    "<b>Status:</b> <span class='badge bg-secondary'>PREVIOUSLY USED</span><br>
     ℹ️ <b>".esc($mobile)."</b> has history but no active allocation.<br>
     <small>
       Last: HRIS <b>".esc($last['hris_no'] ?? '')."</b>,
       Owner <b>".esc($last['owner_name'] ?? '')."</b>,
       From <b>".esc($last['effective_from'] ?? '')."</b>
       To <b>".esc($last['effective_to'] ?? '')."</b>
     </small>
     {$issueLine}"
  );
  exit;
}

echo bs_alert('success',
  "<b>Status:</b> <span class='badge bg-success'>FREE</span><br>
   ✅ <b>".esc($mobile)."</b> has no allocation records.
   {$issueLine}"
);
