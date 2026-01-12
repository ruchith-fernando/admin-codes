<?php
require_once 'connections/connection.php';
if (!headers_sent()) header('Content-Type: text/html; charset=UTF-8');

function esc($v){ return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

$mobile = preg_replace('/\D+/', '', $_POST['mobile'] ?? '');

if ($mobile === '' || !preg_match('/^\d{9}$/', $mobile)) {
  echo "<div class='alert alert-danger'>Mobile must be exactly 9 digits (example: 765455585).</div>";
  exit;
}

// 1) check active open allocation
$stmt = $conn->prepare("
  SELECT id, mobile_number, hris_no, owner_name, effective_from
  FROM tbl_admin_mobile_allocations
  WHERE mobile_number = ?
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
  echo "<div class='alert alert-warning'>
    <b>Status:</b> <span class='badge bg-success'>ACTIVE</span><br>
    ⚠️ <b>".esc($active['mobile_number'])."</b> is allocated to <b>".esc($active['owner_name'])."</b>
    (HRIS: <b>".esc($active['hris_no'])."</b>) since <b>".esc($active['effective_from'])."</b>.<br>
    <small class='text-muted'>Saving will perform a TRANSFER (previous allocation will be closed).</small>
  </div>";
  exit;
}

// 2) check if has any history (disconnected) or none (free)
$stmt = $conn->prepare("SELECT COUNT(*) c FROM tbl_admin_mobile_allocations WHERE mobile_number=?");
$stmt->bind_param("s", $mobile);
$stmt->execute();
$total = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

if ($total > 0) {
  // show most recent history row
  $stmt = $conn->prepare("
    SELECT id, hris_no, owner_name, effective_from, effective_to, status
    FROM tbl_admin_mobile_allocations
    WHERE mobile_number=?
    ORDER BY effective_from DESC, id DESC
    LIMIT 1
  ");
  $stmt->bind_param("s", $mobile);
  $stmt->execute();
  $last = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  echo "<div class='alert alert-info'>
    <b>Status:</b> <span class='badge bg-secondary'>DISCONNECTED / PREVIOUSLY USED</span><br>
    ℹ️ <b>".esc($mobile)."</b> has allocation history but no active allocation.<br>
    <small>
      Last: HRIS <b>".esc($last['hris_no'] ?? '')."</b>,
      Owner <b>".esc($last['owner_name'] ?? '')."</b>,
      From <b>".esc($last['effective_from'] ?? '')."</b>
      To <b>".esc($last['effective_to'] ?? '')."</b>
    </small>
  </div>";
} else {
  echo "<div class='alert alert-success'>
    <b>Status:</b> <span class='badge bg-success'>FREE</span><br>
    ✅ <b>".esc($mobile)."</b> is not used before (no allocation records).
  </div>";
}
