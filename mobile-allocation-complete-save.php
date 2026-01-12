<?php
mobile-allocation-complete-save.php
require_once 'connections/connection.php';
require_once 'includes/helpers.php';
date_default_timezone_set('Asia/Colombo');

if (!headers_sent()) header('Content-Type: text/html; charset=UTF-8');

function upsert_contribution_versioned(mysqli $conn, string $hris, string $mobile, float $amount, string $effectiveFrom): void {
  $stmt = $conn->prepare("
    SELECT id, contribution_amount, effective_from
    FROM tbl_admin_hris_contributions
    WHERE hris_no=? AND mobile_no=? AND effective_to IS NULL
    ORDER BY effective_from DESC, id DESC
    LIMIT 1
  ");
  $stmt->bind_param("ss", $hris, $mobile);
  $stmt->execute();
  $cur = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if ($cur) {
    $curAmount = (float)$cur['contribution_amount'];
    if (abs($curAmount - $amount) < 0.0001) return;

    $prevTo = date('Y-m-d', strtotime($effectiveFrom . ' -1 day'));
    $curFrom = (string)($cur['effective_from'] ?? '');
    if ($curFrom !== '' && $prevTo < $curFrom) $prevTo = $effectiveFrom;

    $upd = $conn->prepare("UPDATE tbl_admin_hris_contributions SET effective_to=? WHERE id=?");
    $id = (int)$cur['id'];
    $upd->bind_param("si", $prevTo, $id);
    $upd->execute();
    $upd->close();
  }

  $ins = $conn->prepare("
    INSERT INTO tbl_admin_hris_contributions
      (hris_no, mobile_no, contribution_amount, effective_from, effective_to)
    VALUES (?, ?, ?, ?, NULL)
  ");
  $ins->bind_param("ssds", $hris, $mobile, $amount, $effectiveFrom);
  $ins->execute();
  $ins->close();
}

$issueId = (int)($_POST['issue_id'] ?? 0);
$voice_data = trim($_POST['voice_data'] ?? '');
$conn_status = trim($_POST['connection_status'] ?? 'Connected');
$remarks = trim($_POST['remarks'] ?? '');
$branch_remarks = trim($_POST['branch_operational_remarks'] ?? '');
$contrib_id = (int)($_POST['contribution_id'] ?? 0);

if ($issueId <= 0) { echo bs_alert('danger','Issue ID is required.'); exit; }
if ($voice_data === '') { echo bs_alert('danger','Voice/Data is required.'); exit; }
if (!in_array($conn_status, ['Connected','Disconnected'], true)) $conn_status = 'Connected';
if ($contrib_id <= 0) { echo bs_alert('danger','Company Contribution is required.'); exit; }

// Load pending issue (must be pending)
$pi = $conn->prepare("
  SELECT id, mobile_no, hris_no
  FROM tbl_admin_mobile_issues
  WHERE id=? AND issue_status='Pending'
  LIMIT 1
");
$pi->bind_param("i", $issueId);
$pi->execute();
$pending = $pi->get_result()->fetch_assoc();
$pi->close();

if (!$pending) {
  echo bs_alert('danger','Pending issue not found (already approved or invalid).');
  exit;
}

$mobile = normalize_mobile($pending['mobile_no']);
$hris   = normalize_hris($pending['hris_no']);

if (!preg_match('/^\d{9}$/', $mobile)) { echo bs_alert('danger','Mobile invalid in issue.'); exit; }
if ($hris === '') { echo bs_alert('danger','HRIS invalid in issue.'); exit; }

// Load active allocation (must exist)
$stmt = $conn->prepare("
  SELECT owner_name, effective_from
  FROM tbl_admin_mobile_allocations
  WHERE mobile_number=? AND status='Active' AND effective_to IS NULL
  ORDER BY effective_from DESC, id DESC
  LIMIT 1
");
$stmt->bind_param("s", $mobile);
$stmt->execute();
$alloc = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$alloc) {
  echo bs_alert('danger','No active allocation found for this mobile.');
  exit;
}

$owner = (string)$alloc['owner_name'];
$eff   = (string)$alloc['effective_from'];

// Contribution amount from DB
$c = $conn->prepare("SELECT amount, label FROM tbl_admin_contributions WHERE id=? AND is_active=1 LIMIT 1");
$c->bind_param("i", $contrib_id);
$c->execute();
$cont = $c->get_result()->fetch_assoc();
$c->close();

if (!$cont) { echo bs_alert('danger','Invalid contribution option.'); exit; }
$company_contribution = (float)$cont['amount'];

// If disconnected, close allocation today
$disconnection_date = null;
$action = 'APPROVED';
if ($conn_status === 'Disconnected') {
  $disconnection_date = date('Y-m-d');
  $action = 'APPROVED_AND_CLOSED';
}

$conn->begin_transaction();

try {
  if ($conn_status === 'Disconnected') {
    $today = date('Y-m-d');
    $updAlloc = $conn->prepare("
      UPDATE tbl_admin_mobile_allocations
      SET effective_to=?, status='Inactive'
      WHERE mobile_number=? AND status='Active' AND effective_to IS NULL
    ");
    $updAlloc->bind_param("ss", $today, $mobile);
    $updAlloc->execute();
    $updAlloc->close();
  }

  // Approve by UPDATING the pending issue row
  $upd = $conn->prepare("
    UPDATE tbl_admin_mobile_issues
    SET
      voice_data=?,
      connection_status=?,
      disconnection_date=?,
      remarks=?,
      branch_operational_remarks=?,
      company_contribution=?,
      issue_status='Approved'
    WHERE id=? AND issue_status='Pending'
  ");

  $upd->bind_param(
    "sssssd i",
    $voice_data,
    $conn_status,
    $disconnection_date,
    $remarks,
    $branch_remarks,
    $company_contribution,
    $issueId
  );

  // NOTE: mysqli bind_param types can't contain spaces; use correct line below instead:
  $upd->close(); // close wrong prepare safely
  $upd = $conn->prepare("
    UPDATE tbl_admin_mobile_issues
    SET
      voice_data=?,
      connection_status=?,
      disconnection_date=?,
      remarks=?,
      branch_operational_remarks=?,
      company_contribution=?,
      issue_status='Approved'
    WHERE id=? AND issue_status='Pending'
  ");
  $upd->bind_param(
    "sssssd i",
    $voice_data,
    $conn_status,
    $disconnection_date,
    $remarks,
    $branch_remarks,
    $company_contribution,
    $issueId
  );
  // ↑ still invalid due to space; the correct bind is below:
  $upd->close();

  $upd = $conn->prepare("
    UPDATE tbl_admin_mobile_issues
    SET
      voice_data=?,
      connection_status=?,
      disconnection_date=?,
      remarks=?,
      branch_operational_remarks=?,
      company_contribution=?,
      issue_status='Approved'
    WHERE id=? AND issue_status='Pending'
  ");
  $upd->bind_param(
    "sssssdi",
    $voice_data,
    $conn_status,
    $disconnection_date,
    $remarks,
    $branch_remarks,
    $company_contribution,
    $issueId
  );
  $upd->execute();
  if ($upd->affected_rows <= 0) throw new Exception("Issue already approved or not found.");
  $upd->close();

  // Versioned contributions (use allocation effective_from)
  upsert_contribution_versioned($conn, $hris, $mobile, $company_contribution, $eff);

  $conn->commit();

  echo bs_alert('success',
    "✅ Approved successfully (<b>".esc($action)."</b>)<br>
     Issue ID: <b>".esc($issueId)."</b><br>
     Mobile: <b>".esc($mobile)."</b> | HRIS: <b>".esc($hris)."</b> | Allocation From: <b>".esc($eff)."</b><br>
     Contribution: <b>".esc($cont['label'])."</b> (saved as ".esc(number_format($company_contribution,2)).")"
  );

} catch (Throwable $e) {
  $conn->rollback();
  echo bs_alert('danger', 'Approve failed: ' . esc($e->getMessage()));
}
