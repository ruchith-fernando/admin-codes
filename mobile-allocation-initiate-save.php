<?php
// mobile-allocation-initate-save.php
require_once 'connections/connection.php';
require_once 'includes/helpers.php';
date_default_timezone_set('Asia/Colombo');

if (!headers_sent()) header('Content-Type: text/html; charset=UTF-8');

$mobile = normalize_mobile($_POST['mobile'] ?? '');
$hris   = normalize_hris($_POST['hris'] ?? '');
$eff    = trim($_POST['effective_from'] ?? '');
$voice  = trim($_POST['voice_data'] ?? '');

if (!preg_match('/^\d{9}$/', $mobile)) { echo bs_alert('danger','Mobile must be 9 digits.'); exit; }
if ($hris === '') { echo bs_alert('danger','HRIS is required.'); exit; }
if ($eff === '') { echo bs_alert('danger','Effective From is required.'); exit; }
if ($voice === '') { echo bs_alert('danger','Voice/Data is required.'); exit; }

if (ctype_digit($hris) && !preg_match('/^\d{6}$/', $hris)) {
  echo bs_alert('danger','Numeric HRIS must be exactly 6 digits (example: 006428).');
  exit;
}

// Enforce Active employee for numeric HRIS + enrich
$owner = 'N/A';
$hierarchy = $nic = null;

if (preg_match('/^\d{6}$/', $hris)) {
  $st = $conn->prepare("
    SELECT name_of_employee, nic_no, company_hierarchy, status
    FROM tbl_admin_employee_details
    WHERE TRIM(hris)=?
    LIMIT 1
  ");
  $st->bind_param("s", $hris);
  $st->execute();
  $emp = $st->get_result()->fetch_assoc();
  $st->close();

  if (!$emp || strcasecmp((string)$emp['status'], 'Active') !== 0) {
    echo bs_alert('danger','⛔ Cannot initiate. HRIS must be Active.');
    exit;
  }

  $owner = $emp['name_of_employee'] ?? 'N/A';
  $hierarchy = $emp['company_hierarchy'] ?? null;
  $nic = $emp['nic_no'] ?? null;
}

$conn->begin_transaction();

try {
  $action = 'NEW';

  // Transfer if active allocation exists
  $cur = $conn->prepare("
    SELECT id, effective_from
    FROM tbl_admin_mobile_allocations
    WHERE mobile_number=? AND status='Active' AND effective_to IS NULL
    ORDER BY effective_from DESC, id DESC
    LIMIT 1
  ");
  $cur->bind_param("s", $mobile);
  $cur->execute();
  $active = $cur->get_result()->fetch_assoc();
  $cur->close();

  if ($active) {
    $oldId = (int)$active['id'];
    $oldFrom = (string)$active['effective_from'];

    if ($eff <= $oldFrom) throw new Exception("Effective From must be after existing effective_from ($oldFrom).");

    $closeTo = date('Y-m-d', strtotime($eff . ' -1 day'));

    $upd = $conn->prepare("
      UPDATE tbl_admin_mobile_allocations
      SET effective_to=?, status='Inactive'
      WHERE id=? AND effective_to IS NULL
    ");
    $upd->bind_param("si", $closeTo, $oldId);
    $upd->execute();
    $upd->close();

    $action = 'TRANSFER';
  }

  // Insert new active allocation
  $ins = $conn->prepare("
    INSERT INTO tbl_admin_mobile_allocations
      (mobile_number, hris_no, owner_name, effective_from, effective_to, status, created_at, updated_at)
    VALUES (?, ?, ?, ?, NULL, 'Active', NOW(), NOW())
  ");
  $ins->bind_param("ssss", $mobile, $hris, $owner, $eff);
  $ins->execute();
  $allocId = $ins->insert_id;
  $ins->close();

  // Insert PENDING issue (minimal required fields)
  $remarks = "Initiated (pending approval).";
  $branch_remarks = "";
  $conn_status = "Connected";

  $iss = $conn->prepare("
    INSERT INTO tbl_admin_mobile_issues
      (mobile_no, remarks, voice_data, branch_operational_remarks,
       name_of_employee, hris_no, company_hierarchy, nic_no,
       connection_status, disconnection_date, issue_status)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, 'Pending')
  ");
  $iss->bind_param(
    "sssssssss",
    $mobile,
    $remarks,
    $voice,
    $branch_remarks,
    $owner,
    $hris,
    $hierarchy,
    $nic,
    $conn_status
  );
  $iss->execute();
  $issueId = $iss->insert_id;
  $iss->close();

  $conn->commit();

  echo bs_alert('success',
    "✅ Initiated successfully (<b>".esc($action)."</b>)<br>
     Allocation ID: <b>".esc($allocId)."</b> | Pending Issue ID: <b>".esc($issueId)."</b><br>
     Mobile: <b>".esc($mobile)."</b> | HRIS: <b>".esc($hris)."</b> | From: <b>".esc($eff)."</b> | Voice/Data: <b>".esc($voice)."</b>"
  );

} catch (Throwable $e) {
  $conn->rollback();
  echo bs_alert('danger', 'Initiation failed: ' . esc($e->getMessage()));
}
